<?php namespace TyrionCMS\SimpleOrm\Item;

class ClearPdoItem implements DbTableRowItem
{
    private $id;
    private $data;
    private $db_table = null;

    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getData($key)
    {
        if ($key) {
            return (isset($this->data->$key)) ? $this->data->$key : null;
        }
        return $this->data;
    }

    /**
     * @param mixed $data
     */
    public function setData($data)
    {
        $this->data = $data;
    }

    /**
     * @param null $db_table
     */
    public function setDbTable($db_table)
    {
        $this->db_table = $db_table;
    }

}