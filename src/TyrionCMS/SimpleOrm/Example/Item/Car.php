<?php namespace TyrionCMS\SimpleOrm\Example\Item;

use TyrionCMS\SimpleOrm\Item\AbstractModelItem;
use TyrionCMS\SimpleOrm\Item\DbTableRowItem;

class Car extends AbstractModelItem implements DbTableRowItem
{
    private const DB_TABLE = "car";

    private $brand;
    private $year_of_production;
    private $price;
    private $model;

    /**
     * @return mixed
     */
    public function getBrand()
    {
        return $this->brand;
    }

    /**
     * @return mixed
     */
    public function getYearOfProduction()
    {
        return $this->year_of_production;
    }

    /**
     * @return mixed
     */
    public function getPrice()
    {
        return $this->price;
    }

    /**
     * @return mixed
     */
    public function getModel()
    {
        return $this->model;
    }


}
