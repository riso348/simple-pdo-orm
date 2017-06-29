<?php

include __DIR__ . "/autoloader.php";

use TyrionCMS\SimpleOrm\DbStatement;
use TyrionCMS\SimpleOrm\DbWrapper;
use TyrionCMS\SimpleOrm\Example\Item\Car;
use TyrionCMS\SimpleOrm\Example\Item\Product;


$config = array(
    "db_host" => "localhost",
    "db_password" => "",
    "db_port" => "3306",
    "db_name" => "example_database",
    "db_username" => "root"
);

$dbWrapper = new DbWrapper($config);

$dbStatement = new DbStatement($dbWrapper->getConnection());

$cars = $dbStatement
    ->setRowItemInstance(new Car())
    ->setQuery("SELECT * FROM {$dbStatement->getModelTableName()}")->findResult();

/** @var TyrionCMS\SimpleOrm\Example\Item\Car $car */
while ($cars->hasNextItem()) {
    $car = $cars->getNextItem();
    echo $car->getModel() . "<br/>";
}

$car = $dbStatement
    ->setRowItemInstance(new Car())
    ->setQuery("SELECT * FROM {$dbStatement->getModelTableName()} WHERE id = ?")
    ->setArguments(array(1))
    ->findOne();

$data = $dbStatement
    ->setQuery("SELECT * FROM `car` WHERE `brand` LIKE '%?%'")
    ->setArguments(array("Fiat"))
    ->findResult();
