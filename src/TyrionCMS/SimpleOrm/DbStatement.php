<?php namespace TyrionCMS\SimpleOrm;


use TyrionCMS\SimpleOrm\Exceptions\DatabaseItemNotFoundException;
use TyrionCMS\SimpleOrm\Exceptions\DatabaseModelException;
use TyrionCMS\SimpleOrm\Exceptions\DatabaseUpdateModelException;
use TyrionCMS\SimpleOrm\Item\ClearPdoItem;
use TyrionCMS\SimpleOrm\Item\DbTableRowItem;

final class DbStatement
{
    private $connection;
    private $query;
    private $arguments = null;
    private $rowItemInstance;
    private $create_dynamic_properties = false;
    private $tableFields = array();

    public function __construct(\PDO $connection)
    {
        $this->connection = $connection;
        $this->rowItemInstance = new ClearPdoItem();
    }

    /**
     * @param DbTableRowItem $rowItemInstance
     * @return $this
     */
    public function setRowItemInstance(DbTableRowItem $rowItemInstance)
    {
        $this->rowItemInstance = $rowItemInstance;
        return $this;
    }

    public function findResult(): DbTableRowIterator
    {
        $stmt = $this->execute()->getStatement();
        $rowList = new DbTableRowList();
        while ($result = $stmt->fetchObject()) {
            if (!$this->getRowItemInstance() instanceof ClearPdoItem) {
                $rowList->addItem($this->generateTableRowItem($result, $this->getRowItemInstance()));
            } else {
                $clearDbItem = new ClearPdoItem();
                $clearDbItem->setData($result);
                $rowList->addItem($clearDbItem);
            }
        }
        $rowListIterator = new DbTableRowIterator($rowList);
        return $rowListIterator;
    }

    public function execute(): PDOExecuteResult
    {
        $stmt = $this->connection->prepare($this->query);
        $result = $stmt->execute($this->arguments);
        $dbResult = new PDOExecuteResult($stmt, $result);
        return $dbResult;
    }

    private function getRowItemInstance(): DbTableRowItem
    {
        try {
            $instance = (new \ReflectionClass($this->rowItemInstance))->newInstance();
        } catch (\Exception $e) {
            throw new DatabaseModelException("Unable to create model from class: {$this->rowItemInstance}");
        }
        if (!$instance instanceof DbTableRowItem) {
            throw new DatabaseModelException("Create instance has to implement DbTableRowItem");
        }
        return $instance;
    }

    private function generateTableRowItem($result, DbTableRowItem $rowItem): DbTableRowItem
    {
        $obj = new \ReflectionObject($rowItem);
        $new_object = $obj->newInstance();
        $obj_properties = array();

        $result = (array)$result;
        foreach ($obj->getProperties() as $property) {
            $property->setAccessible(true);
            $property_name = $property->getName();
            $obj_properties[$property_name] = null;
            if (array_key_exists($property_name , $result)) {
                $property->setValue($new_object, $result[$property_name]);
            }
        }

        if($this->isCreateDynamicProperties()){
            if(!array_key_exists($obj->getName(), $this->tableFields)) {
                $previousRowInstance = $this->getRowItemInstance();
                $this->setRowItemInstance(new ClearPdoItem());
                $tableFields = $this->setQuery("DESCRIBE product")->findResult()->getTableList()->getItems();
                $this->tableFields[$obj->getName()] = $tableFields;
            }else{
                $tableFields = $this->tableFields[$obj->getName()];
            }
            /** @var ClearPdoItem $field */
            foreach($tableFields as $field){
                $field = $field->getData('Field');
                if(!array_key_exists($field, $obj_properties)){
                    $new_object->$field = (array_key_exists($field , $result)) ? $result[$field] : null;
                }
            }

            if(isset($previousRowInstance)) {
                $this->setRowItemInstance($previousRowInstance);
            }
        }
        return $new_object;
    }

    public function findOne(): ? DbTableRowItem
    {
        $stmt = $this->execute()->getStatement();
        $row = $stmt->fetchObject();
        $row = ($row) ? $this->generateTableRowItem($row, $this->getRowItemInstance()) : null;
        if (is_null($row)) {
            throw new DatabaseItemNotFoundException("Unable to find item");
        }
        return $row;
    }

    public function getLastInsertedId(): int
    {
        return $this->connection->lastInsertId();
    }

    public function saveModelItem(DbTableRowItem $dbTableRowItem)
    {
        $reflectionObject = new \ReflectionObject($dbTableRowItem);
        $db_table = $this->getModelTableName($dbTableRowItem);
        try {
            $dbTableRowItem->getId();
            $this->prepareUpdateModelItem($dbTableRowItem, $db_table, $reflectionObject);
        } catch (\TypeError $e) {
            $this->prepareCreateNewModelItem($dbTableRowItem, $db_table, $reflectionObject);
        }
        $result = $this->execute();
        if ($result->getResult() !== true) {
            throw new DatabaseUpdateModelException("Unable to update model item: " . $result->getResult());
        }
    }

    public function deleteModelItem(DbTableRowItem $dbTableRowItem)
    {
        $db_table = $this->getModelTableName($dbTableRowItem);
        try {
            $dbTableRowItem->getId();
            $sql = "DELETE FROM `{$db_table}` WHERE `id` = ?";
            $this->setQuery($sql);
            $this->setArguments(array($dbTableRowItem->getId()));
        } catch (\TypeError $e) {
            throw new DatabaseModelException("Unable to find primary_key ID.");
        }
        $result = $this->execute();
        if ($result->getResult() !== true) {
            throw new DatabaseUpdateModelException("Unable to update model item: " . $result->getResult());
        }
    }

    public function getModelTableName(? DbTableRowItem $dbTableRowItem = null): String
    {

        $reflectionObject = new \ReflectionObject($dbTableRowItem ? $dbTableRowItem : $this->getRowItemInstance());
        if($reflectionObject instanceof ClearPdoItem){
            try {
                $db_table = $reflectionObject->getProperty('db_table');
                if(strlen($db_table) < 1){
                    throw new DatabaseUpdateModelException("ClearPdoItem db_table is empty.");
                }
            } catch (\ReflectionException $e) {
                throw new DatabaseUpdateModelException("ClearPdoItem db_table is empty.");
            }
        }else {
            $db_table = $reflectionObject->getConstant("DB_TABLE");
        }
        if ($db_table === false) {
            throw new DatabaseUpdateModelException("Unable to find model constant: DB_TABLE");
        }
        return $db_table;
    }

    private function prepareUpdateModelItem($dbTableRowItem, String $db_table, \ReflectionObject $reflectionObject)
    {

        $values = array();
        $sql = "UPDATE `{$db_table}` SET ";

        $temp_reflection_class = $reflectionObject;
        $properties = array();
        while($temp_reflection_class instanceof \ReflectionClass){
            foreach($temp_reflection_class->getProperties() as $property){
                if(!array_key_exists($property->getName() , $properties)){
                    $properties[$property->getName()] = $property;
                }
            }
            $temp_reflection_class = $temp_reflection_class->getParentClass();
        }

        foreach ($properties as $property) {
            if ($property->getName() != 'id') {
                $property->setAccessible(true);
                $value = $property->getValue($dbTableRowItem);
                if ($value instanceof \DateTime) {
                    $value = $value->format("Y-m-d H:i:s");
                }
                $values[] = $value;
                $sql .= "`{$property->getName()}` = ? , ";
            }
        }
        $sql = rtrim($sql, ' , ');
        $sql .= " WHERE `id` = ?";
        $values[] = $dbTableRowItem->getId();
        $this->setQuery($sql);
        $this->setArguments($values);
    }

    public function setQuery(String $query)
    {
        $this->query = $query;
        return $this;
    }

    public function setArguments(array $arg)
    {
        $this->arguments = $arg;
        return $this;
    }

    public function setCreateDynamicProperties(bool $value)
    {
        $this->create_dynamic_properties = $value;
        return $this;
    }

    private function prepareCreateNewModelItem($dbTableRowItem, String $db_table, \ReflectionObject $reflectionObject)
    {
        $values = array();
        $sql = "INSERT INTO `{$db_table}` (";

        $temp_reflection_class = $reflectionObject;
        $properties = array();
        while($temp_reflection_class instanceof \ReflectionClass){
            foreach($temp_reflection_class->getProperties() as $property){
                if(!array_key_exists($property->getName() , $properties)){
                    $properties[$property->getName()] = $property;
                }
            }
            $temp_reflection_class = $temp_reflection_class->getParentClass();
        }

        foreach ($properties as $property) {
            if ($property->getName() != 'id') {
                $property->setAccessible(true);
                $value = $property->getValue($dbTableRowItem);
                if ($value instanceof \DateTime) {
                    $value = $value->format("Y-m-d H:i:s");
                }
                $values[] = $value;
                $sql .= "`{$property->getName()}` , ";
            }
        }
        $sql = rtrim($sql, ' , ');
        $sql .= ") VALUES (";
        foreach ($values as $value) {
            $sql .= " ? , ";
        }
        $sql = rtrim($sql, ' , ');
        $sql .= " ) ";
        $this->setQuery($sql);
        $this->setArguments($values);
    }

    private function isCreateDynamicProperties():bool
    {
        return $this->create_dynamic_properties;
    }


}