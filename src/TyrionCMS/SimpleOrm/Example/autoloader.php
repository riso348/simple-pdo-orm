<?php
$classesDir = array(
    __DIR__ . '/../../../',
);
function __autoload($class_name)
{
    global $classesDir;
    foreach ($classesDir as $directory) {
        $class_name = str_replace('\\' , DIRECTORY_SEPARATOR , $class_name);
        if (file_exists($directory . $class_name . '.php')) {
            require_once($directory . $class_name . '.php');
            return;
        }
    }
}
