# pdo-db-wrapper
Wrapper voor database communicatie voor de PDO class.

## Installatie
Dit gaat het meest eenvoudig via composer:
`composer require reclamestal/pdo-db-wrapper`

## Hoe te gebruiken
Vervolgens gebruik je de class als volgt.

````php
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_USER', 'username');
define('DB_PASS', 'password');
define('DB_NAME', 'database_name');

//Autoloader van Composer
require_once 'vendor/autoload.php';

use reclamestal\PdoDbWrapper\Db;

try {
    $db = new Db('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . '', DB_USER, DB_PASS);
    $db->setErrorCallbackFunction('echo', $errorMsgFormat = "html");
} catch (Exception $e) {
    print_r($e);
}

$rows   = $db->select('table_name');
$row    = $db->selectSingle('table_name');
$insert = $db->update('table_name',['column'=>'value']);
$update = $db->update('table_name',['column'=>'newvalue']);
$delete = $db->delete('table_name','column = :value',[':value'=>'value']);
$run    = $db->run('SELECT * FROM ...',[':value'=>'value']);

```

## Version
Current version 1.0.3