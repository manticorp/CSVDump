# MySQL CSVDump

CSVDump allows you to easily and effectively dump CSV files into a MySQL database without hassle.

CSVDump has the following features:

* Easily process data row-by-row before entering into the database.
* Pre and post import database processing.
* Live updates on the progress of the dump.
* Processes 1000's of rows per second. Without processing, speeds can reach ~100,000 rows per second.
* Handles NULL values.

## Installation

Simply clone the repo into a local directory accessible on your web server:

    git clone https://github.com/manticorp/CSVDump.git ./public_html/CSVDump

or just:

    git clone https://github.com/manticorp/CSVDump.git

then point your browser to:

[http://localhost/CSVDump](http://localhost/CSVDump)

## Usage

### Basic usage

1. Edit ```/Core/Config.example.php``` with your database details and save as ```Config.php```.
2. Place a CSV file in ```/CSVDump/input```.
3. Navigate to the [CSVDump directory](http://localhost/CSVDump) in your web browser.
4. Type your database name
5. Click on '**Dump to database.tablename**'

![Screenshot of basic screen](http://i.imgur.com/VwzOOxC.png)

### Selecting Column Types

CSVDump tries to automatically guess the column types for your data - it can be particularly good with numerical columns vs text columns, and 99% of the time it works fine - but if you want to explicity tell it what type a column should be, you can expand a row on the file table to explicitly tell CSVDump what type a column is:

![Selecting column data types](http://i.imgur.com/7ubI7z9.png)

### Advanced Usage

CSVDump allows you to do several things that are unavailable in lots of other CSV dumping tools, including row by row processing and pre and post import database functions.

### Row by Row Processing

To create a row processor, it's best to copy one of the example files, found in ```Core/Processor/Row/ProcessorName```. There are certain syntax requirements for your processor to be picked up by the software:

* The class must inherit the Abstract class
* The name of your class must be Processor_Row_ProcessorName
* The name of the file must be ProcessorName.php

As an example of capitalising the 'title' column in your CSV file:

File: ```/Core/Processor/Row/MyRowProcessor.php```

```php
<?php
include "Abstract.php";

/**
 * Processor_Row_MyRowProcessor
 */
class Processor_Row_MyRowProcessor extends Processor_Row_Abstract
{
    /**
     * process the row
     * @param  array $row the row
     * @return array $row the modified row
     */
    public function process($row)
    {
        $row['title'] = strtoupper($row['title']);
        return $row;
    }
}
```

You can use the row processor to do any field by field or row by row processing.

```php
<?php
include "Abstract.php";

/**
 * Processor_Row_MyRowProcessor
 */
class Processor_Row_MyRowProcessor extends Processor_Row_Abstract
{
    /**
     * process the row
     * @param  array $row the row
     * @return array $row the modified row
     */
    public function process($row)
    {
        $row['title'] = $this->titleCase($row['title']);
        $row['combinedTitleDescription'] =
            $row['title'] . ' ' . $row['description'];
        return $row;
    }

    /**
     * Converts a string to Title Case
     * 
     * @param  string  $str  The string to be titlecased
     * @return string        The modified string
     */
    private function titleCase($str)
    {
        return ucwords(strtolower($str));
    }
}
```

### Database Processing

Similar to row by row processing, you can use the DB processor to do preimport and postimport processing on the database.

Example: /Core/Processor/DB/MyDBProcessor.php

```php
<?php
include "Abstract.php";

/**
 * Processor_DB_MyDBProcessor
 */
class Processor_DB_MyDBProcessor extends Processor_DB_Abstract
{
    public function preImport(){
        $SQL  = "ALTER TABLE `".$this->getTableName()."` ";
        $SQL .= "ADD `TEST ADD COL` VARCHAR(255) NULL DEFAULT NULL ;";
        try{
            $this->query($SQL, true);
        } catch (\Exception $e){
            // do nothing, this is okay because on multiple imports it might fail on
            // the second import when the column already exists.
        }
        return $this;
    }

    public function postImport(){
        $SQL  = "UPDATE `".$this->getTableName()."` SET ";
        $SQL .= "`TEST ADD COL`='TEST';";
        $this->query($SQL);
        return $this;
    }
}
```

In addition there are some convenience functions defined for creating/modifying/deleting columns and other general query related things:

* ```query($SQL)```
    - Perform a direct SQL query
* ```getConnection()```
    - Returns a myqli object
* ```tableColumnExists($name)```
    - Whether ```$name``` column exists.
* ```fetchAll($query, $mode)```
    - Fetch all results of ```$query``` in ```$mode``` (default ```MYSQLI_ASSOC```).
* ```getTableName()```
    - gets the current table name
* ```addColumn($name, $type, $size = null, $options = array(), $comment = null, $newname = null)```
    - Creates a column
* ```modifyColumn($name, $type, $size = null, $options = array(), $comment = null, $newname = null)```
    - Modifies a column
* ```dropColumn($name)```
    - Drops the ```$name``` column

For the add/modifyColumn usage, I suggest looking either at the [example processor](https://github.com/manticorp/CSVDump/blob/master/Core/Processor/DB/Example.php) or looking at the [Abstract.php](https://github.com/manticorp/CSVDump/blob/master/Core/Processor/DB/Abstract.php) file.

### Using Processors

Using the processor is simple, just select it in the 'Processor' dropdown box and run the dump as normal:

![Using a Processor](http://i.imgur.com/IoY3dog.png)