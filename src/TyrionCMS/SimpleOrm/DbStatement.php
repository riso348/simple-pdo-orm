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

        foreach ($obj->getProperties() as $property) {
            $property->setAccessible(true);
            $property_name = $property->getName();
            if (isset($result->$property_name)) {
                $property->setValue($new_object, $result->$property_name);
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

    public function getModelTableName(? DbTableRowItem $dbTableRowItem = null): String
    {

        $reflectionObject = new \ReflectionObject($dbTableRowItem ? $dbTableRowItem : $this->getRowItemInstance());
        $db_table = $reflectionObject->getConstant("DB_TABLE");
        if ($db_table === false) {
            throw new DatabaseUpdateModelException("Unable to find model constant: DB_TABLE");
        }
        return $db_table;
    }

    private function prepareUpdateModelItem($dbTableRowItem, String $db_table, \ReflectionObject $reflectionObject)
    {

        $values = array();
        $sql = "UPDATE `{$db_table}` SET ";
        foreach ($reflectionObject->getProperties() as $property) {
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

    private function prepareCreateNewModelItem($dbTableRowItem, String $db_table, \ReflectionObject $reflectionObject)
    {
        $values = array();
        $sql = "INSERT INTO `{$db_table}` (";
        foreach ($reflectionObject->getProperties() as $property) {
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


}