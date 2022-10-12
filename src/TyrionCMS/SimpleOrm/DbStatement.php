<?php namespace TyrionCMS\SimpleOrm;


use TyrionCMS\SimpleOrm\Exceptions\DatabaseItemNotFoundException;
use TyrionCMS\SimpleOrm\Exceptions\DatabaseModelException;
use TyrionCMS\SimpleOrm\Exceptions\DatabaseUpdateModelException;
use TyrionCMS\SimpleOrm\Item\ClearPdoItem;
use TyrionCMS\SimpleOrm\Item\DbTableRowItem;

final class DbStatement
{
    const IGNORE_PROPERTY_ANNOTATION = 'tyrion-orm-ignore';
    private $connection;
    private $query;
    private $arguments = null;
    private $rowItemInstance;
    private $create_dynamic_properties = false;
    private $tableFields = array();
    private $transactionCounter;
    private $primaryKeyColumn = "id";

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

    /**
     * @return bool
     */
    public function beginTransaction():bool
    {
        if (!$this->transactionCounter++) {
            return $this->connection->beginTransaction();
        }
        $this->connection->exec('SAVEPOINT trans'.$this->transactionCounter);
        return $this->transactionCounter >= 0;
    }

    /**
     * @return bool
     */
    public function commitTransaction():bool
    {
        if (!--$this->transactionCounter) {
            return $this->connection->commit();
        }
        return $this->transactionCounter >= 0;
    }

    /**
     * @return bool
     */
    public function rollbackTransaction(): ? bool
    {
        if (--$this->transactionCounter) {
            $this->connection->exec('ROLLBACK TO trans '. $this->transactionCounter + 1);
            return true;
        }
        return $this->connection->rollback();
    }

    /**
     * @return DbTableRowIterator
     * @throws DatabaseModelException
     */
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

    /**
     * @return PDOExecuteResult
     */
    public function execute(): PDOExecuteResult
    {
        $stmt = $this->connection->prepare($this->query);
        $result = $stmt->execute($this->arguments);
        $this->setArguments([]);
        $dbResult = new PDOExecuteResult($stmt, $result);
        return $dbResult;
    }

    /**
     * @return DbTableRowItem
     * @throws DatabaseModelException
     */
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

    /**
     * @param $result
     * @param DbTableRowItem $rowItem
     * @return DbTableRowItem
     * @throws DatabaseModelException
     */
    private function generateTableRowItem($result, DbTableRowItem $rowItem): DbTableRowItem
    {
        $obj = new \ReflectionObject($rowItem);
        $new_object = $obj->newInstance();
        $obj_properties = array();

        $result = (array)$result;

        foreach ($this->generateRecursiveProperties($obj) as $property) {
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

    /**
     * @return null|DbTableRowItem
     * @throws DatabaseItemNotFoundException
     * @throws DatabaseModelException
     */
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

    /**
     * @return int
     */
    public function getLastInsertedId(): int
    {
        return $this->connection->lastInsertId();
    }

    /**
     * @param DbTableRowItem $dbTableRowItem
     * @throws DatabaseUpdateModelException
     * @throws DatabaseModelException
     */
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

    /**
     * @param DbTableRowItem $dbTableRowItem
     * @throws DatabaseModelException
     * @throws DatabaseUpdateModelException
     */
    public function deleteModelItem(DbTableRowItem $dbTableRowItem):void
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

    /**
     * @param null|DbTableRowItem $dbTableRowItem
     * @return String
     * @throws DatabaseModelException
     * @throws DatabaseUpdateModelException
     */
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

    /**
     * @param $dbTableRowItem
     * @param String $db_table
     * @param \ReflectionObject $reflectionObject
     */
    private function prepareUpdateModelItem($dbTableRowItem, String $db_table, \ReflectionObject $reflectionObject):void
    {

        $values = array();
        $sql = "UPDATE `{$db_table}` SET ";

        foreach ($this->generateRecursiveProperties($reflectionObject) as $property) {
            if ($property->getName() != $this->getPrimaryKeyColumn()) {
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
        $sql .= " WHERE `{$this->getPrimaryKeyColumn()}` = ?";
        $values[] = $dbTableRowItem->getId();
        $this->setQuery($sql);
        $this->setArguments($values);
    }

    /**
     * @param String $query
     * @return $this
     */
    public function setQuery(String $query)
    {
        $this->query = $query;
        return $this;
    }

    /**
     * @param array $arg
     * @return $this
     */
    public function setArguments(array $arg)
    {
        $this->arguments = $arg;
        return $this;
    }

    /**
     * @param bool $value
     * @return $this
     */
    public function setCreateDynamicProperties(bool $value)
    {
        $this->create_dynamic_properties = $value;
        return $this;
    }

    /**
     * @param $dbTableRowItem
     * @param String $db_table
     * @param \ReflectionObject $reflectionObject
     */
    private function prepareCreateNewModelItem($dbTableRowItem, String $db_table, \ReflectionObject $reflectionObject):void
    {
        $values = array();
        $sql = "INSERT INTO `{$db_table}` (";
        
        foreach ($this->generateRecursiveProperties($reflectionObject) as $property) {
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

    /**
     * @return bool
     */
    private function isCreateDynamicProperties():bool
    {
        return $this->create_dynamic_properties;
    }

    /**
     * @param \ReflectionObject $obj
     * @return array
     */
    private function generateRecursiveProperties(\ReflectionObject $obj):array{
        $temp_reflection_class = $obj;
        $properties = array();
        while($temp_reflection_class instanceof \ReflectionClass){
            foreach($temp_reflection_class->getProperties() as $property){
                $annotations = $this->getPropertyAnnotations($property);
                if(!array_key_exists($property->getName() , $properties) && !array_key_exists(self::IGNORE_PROPERTY_ANNOTATION , $annotations)){
                    $properties[$property->getName()] = $property;
                }
            }
            $temp_reflection_class = $temp_reflection_class->getParentClass();
        }
        return $properties;
    }

    /**
     * @param \ReflectionProperty $property
     * @return array
     */
    private function getPropertyAnnotations(\ReflectionProperty $property): array
    {
        $doc = $property->getDocComment();
        preg_match_all('#@(.*?)\n#s', $doc, $annotations);
        $clear_annotations = array();
        foreach(($annotations[1] ?? array()) as $annotation){
            $clear_annotations[$annotation] = null;
        }
        return $clear_annotations;
    }

    private function getPrimaryKeyColumn():string
    {
        return $this->primaryKeyColumn;
    }

    /**
     * @param string $primaryKeyColumn
     * @return DbStatement
     */
    public function setPrimaryKeyColumn(string $primaryKeyColumn): DbStatement
    {
        $this->primaryKeyColumn = $primaryKeyColumn;
        return $this;
    }

}