# Simple ORM using PDO & Reflection

Usefull library that help you access to database entities using queries and transfer into Object you pass as parameter.
 
| id    | Model     | Brand  |  Price   |
|:-----:|-----------| -------|---------:|
| 1     | 500       | Fiat   | 45000    |
| 2     | Punto     | Fiat   | 32000    |
| 3     | Octavia   | Skoda  | 28000    |

```sql
SELECT * FROM car
```
Will return you all entities that you are able to work with using PDO.

## Example of usage

```PHP
$dbWrapper = new DbWrapper($config);                                // Create connection from config data

$dbStatement = new DbStatement($dbWrapper->getConnection());        // get DB manager that run queries

// SELECT all entities from `car` table
$cars = $dbStatement
    ->setRowItemInstance(new Car())                                 // Set Class we want to generate using reflection form entities
    ->setQuery("SELECT * FROM {$dbStatement->getModelTableName()}") // set sql Query
    ->findResult();                                                 // return DbTableRowIterator

while ($cars->hasNextItem()) {                                      // Iterate selected entities
    $car = $cars->getNextItem();
    echo $car->getModel() . "<br/>";
}

// SELECT specific car entity
$car = $dbStatement
    ->setRowItemInstance(new Car())
    ->setQuery("SELECT * FROM {$dbStatement->getModelTableName()} WHERE id = ?")
    ->setArguments(array(1))
    ->findOne();
    
// SELECT unspecified model item: ClearPDOItem
$data = $dbStatement
    ->setQuery("SELECT * FROM `car` WHERE `brand` LIKE '%?%'")
    ->setArguments(array("Fiat"))
    ->findResult()
```
Car class:

```PHP
class Car implements DbTableRowItem
{
    private const DB_TABLE = "car";
    
    private $id;
    private $brand;
    private $year_of_production;
    private $price;
    private $model;
    /**
     * Support for property annotation for ignore unrelated columns
     * @tyrion-orm-ignore
    */
    private $test;

    public function getId():int
    {
        return $this->id;
    }
    
    public function getBrand():String
    {
        return $this->brand;
    }
    
    ...
    // No setter necessary, only if you need to for UPDATE / INSERT
    
}
```


Full example is included in Example folder of this package with simple autoloader included.