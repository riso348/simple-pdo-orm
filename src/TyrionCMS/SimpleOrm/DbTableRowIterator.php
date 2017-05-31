<?php namespace TyrionCMS\SimpleOrm;


use TyrionCMS\SimpleOrm\Item\DbTableRowItem;

class DbTableRowIterator
{

    private $itemList;
    private $currentItem = 0;


    public function __construct(DbTableRowList $itemList)
    {
        $this->itemList = $itemList;
    }

    public function getCurrentItem(): ? DbTableRowItem
    {
        return $this->itemList->getItem($this->currentItem);
    }

    public function getNextItem(): ? DbTableRowItem
    {
        if ($this->hasNextItem()) {
            return $this->itemList->getItem($this->currentItem++);
        } else {
            return null;
        }
    }

    public function hasNextItem()
    {
        return $this->itemList->getItemCount() > $this->currentItem;
    }

    public function resetIterator()
    {
        $this->currentItem = 0;
    }

    public function getTableList():DbTableRowList
    {
        return $this->itemList;
    }
}