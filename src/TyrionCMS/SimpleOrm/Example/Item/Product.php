<?php namespace TyrionCMS\SimpleOrm\Example\Item;


use TyrionCMS\SimpleOrm\Item\AbstractModelItem;
use TyrionCMS\SimpleOrm\Item\DbTableRowItem;

class Product extends AbstractModelItem implements DbTableRowItem
{
    private const DB_TABLE = "product";
}