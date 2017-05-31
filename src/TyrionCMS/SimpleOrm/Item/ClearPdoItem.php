<?php namespace TyrionCMS\SimpleOrm\Item;

use TyrionCMS\SimpleOrm\DbTableRowItem;

class ClearPdoItem implements DbTableRowItem
{
    private $id;
    private $data;

    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getData($key)
    {
        if($key){
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


}