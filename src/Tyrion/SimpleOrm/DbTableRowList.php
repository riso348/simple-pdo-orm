<?php namespace Tyrion\SimpleOrm;


use Tyrion\SimpleOrm\Item\DbTableRowItem;

class DbTableRowList
{

    private $items = array();
    private $itemCount = 0;

    public function getItemCount():int
    {
        return $this->itemCount;
    }

    public function setItemCount(int $count):void
    {
        $this->itemCount = $count;
    }

    public function getItem(int $position): ? DbTableRowItem
    {
        if($position < $this->getItemCount()){
            return $this->items[$position];
        }
        return null;
    }

    public function getItems():array
    {
        return $this->items;
    }

    public function addItem(DbTableRowItem $item):void
    {
        $this->items[$this->getItemCount()] = $item;
        $this->setItemCount($this->getItemCount()+1);
    }

    public function removeItem(DbTableRowItem $removeItem):void
    {
        foreach ($this->items as $key => $item) {
            if($item === $removeItem){
                unset($this->items[$key]);
            }
        }

        $this->items = array_values($this->items);
    }

}