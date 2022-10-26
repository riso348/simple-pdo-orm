<?php namespace TyrionCMS\SimpleOrm\Item;

class ClearPdoItem implements DbTableRowItem
{
    private $id;
    private array $data;
    private string|null $db_table = null;

    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getData(?string $key = null)
    {
        if ($key) {
            return (isset($this->data->$key)) ? $this->data->$key : null;
        }
        return $this->data;
    }

    /**
     * @param mixed $data
     */
    public function setData(array $data)
    {
        $this->data = $data;
    }

    /**
     * @param null $db_table
     */
    public function setDbTable(string $db_table)
    {
        $this->db_table = $db_table;
    }

}