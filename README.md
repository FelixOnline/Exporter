# Exporter

A progamatic database exporter. Currently only MySQL.

## Installation 

Add this to your `composer.json`
    
    {
        "require": {
            "felixonline/exporter": "0.1.*"
        }
    }

Then run:

    composer install

And add `require 'vendor/autoload.php'` to your php file;

## Example


```php
class FooExporter extends \FelixOnline\Exporter\MySQLExporter
{
    function processTable($table)
    {
    	if ($table == 'table_to_skip') {
    		return false; // still outputs create table sql
    	}
    	return $table;
    }
    
    function processRow($row, $table)
    {
    	if ($table == 'users') {
    		$row['password'] = 'password';
    	}
    	
    	return $row;
    }
}

$exporter = new FooExporter(array(
    'db_name' => 'DB_NAME',
    'db_user' => 'DB_USER',
    'db_pass' => 'DB_PASS',
    'file' => 'foo-' . time() . '.sql',
));

$exporter->run();
```

## License

MIT
