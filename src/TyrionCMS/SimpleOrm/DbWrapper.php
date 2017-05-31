<?php namespace TyrionCMS\SimpleOrm;

use Cms\CmsManager;
use Middleware\CompanyValidator;
use PDO;
use PDOException;
use TyrionCMS\SimpleOrm\Exceptions\WrongConfigParameterException;
use TyrionApi\Application;
use TyrionApi\Classes\Singleton;

/**
 * Singleton Class DbWrapper
 * Database wrapper for external DB - Tyrion
 * This DB holds all necessary global information such as Permissions, Settings, Updates
 * @package TyrionCMS\SimpleOrm
 */
final class DbWrapper
{
    protected static $_instance;
    private $connection;

    public function __construct($config)
    {
        if(!$this->connection instanceof PDO) {
            try {
                $this->validateConfig($config);
                $conn = new PDO("mysql:host=" . $config['db_host'] . ";port=" . $config['db_port'] . ";dbname=" . $config['db_name'] . ";charset=UTF8", $config['db_username'], $config['db_password']);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->connection = $conn;
            } catch (PDOException $e) {
                die("Unable to connect to remote DB");
            }
        }
    }

    public function getConnection():PDO
    {
        return $this->connection;
    }

    private function validateConfig($config)
    {
        if(!is_array($config)){
            throw new WrongConfigParameterException("Config has to be an Array");
        }
        $required_attributes = array("db_host" , "db_password" , "db_port" , "db_name" , "db_username");
        $diff = array_diff($required_attributes, array_keys($config));
        if(is_array($diff) && count($diff)){
            throw new WrongConfigParameterException("Missing config parameters: " . implode(" , " , $diff));
        }elseif(!is_array($diff)){
            throw new WrongConfigParameterException("Unable to diff config with required params!");
        }
    }

}

