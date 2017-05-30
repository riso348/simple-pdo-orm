<?php

include __DIR__ . "/autoloader.php";

use Tyrion\SimpleOrm\DbStatement;
use Tyrion\SimpleOrm\DbWrapper;
use Tyrion\SimpleOrm\Example\Item\Car;


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

/** @var Tyrion\SimpleOrm\Example\Item\Car $car */
while($cars->hasNextItem()){
    $car = $cars->getNextItem();
    echo $car->getModel() . "<br/>";
}

