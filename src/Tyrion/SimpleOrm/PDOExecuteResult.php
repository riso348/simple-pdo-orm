<?php namespace TyrionCMS\SimpleOrm;


use PDOStatement;

class PDOExecuteResult
{
    private $result;
    private $statement;

    public function __construct(PDOStatement $statement, bool $result)
    {
        $this->result = $result;
        $this->statement = $statement;
    }
    /**
     * @return bool
     */
    public function getResult():bool
    {
        return $this->result;
    }


    /**
     * @return PDOStatement
     */
    public function getStatement():PDOStatement
    {
        return $this->statement;
    }



}