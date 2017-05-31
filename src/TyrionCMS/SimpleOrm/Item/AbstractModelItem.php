<?php namespace TyrionCMS\SimpleOrm\Item;


abstract class AbstractModelItem
{
    protected $id;

    /**
     * @return mixed
     */
    public function getId(): int
    {
        return $this->id;
    }

}