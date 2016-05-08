<?php
include "Abstract.php";

/**
 * Processor_DB_Example
 */
class Processor_DB_Example extends Processor_DB_Abstract
{
    public function preImport(){
        // Adding/modifying a column using the built in method
        $options = array(
            'nullable' => true,
            'default'  => null,
        );
        $this->addColumn('TEST_VARCHAR', Processor_DB_Abstract::TYPE_VARCHAR, 255, $options);

        $options = array(
            'unsigned' => true,
            'nullable' => true,
            'default'  => null,
        );
        $this->addColumn('TEST_INT', Processor_DB_Abstract::TYPE_INTEGER, 255, $options);

        // Adding/modifying a column using raw SQL
        $SQL = "ALTER TABLE `" . $this->getTableName() . "` ADD `TEST ADD COL` VARCHAR(255) NULL DEFAULT NULL ;";
        try{
            $this->query($SQL, true);
        } catch (\Exception $e){
            // do nothing, this is okay because on multiple imports it might fail on
            // the second import when the column already exists.
        }

        // Change a column to a UTF8 column
        $this->modifyColumn('utf8column', Processor_DB_Abstract::TYPE_VARCHAR, 255, ['collate' => 'utf8_general_ci'], 'My UTF8 column');
        // or
        $this->changeColumnCollation('utf8column', 'utf8_general_ci', 'utf8');
        // or
        $columns = $this->getColumns();
        foreach($columns as $columnName => $columnDefinition) {
            $this->changeColumnCollation($columnName, 'utf8_general_ci');
        }

        return $this;
    }

    public function postImport(){
        $options = array(
            'nullable' => true,
            'default'  => 1
        );

        // Because of how this works, repeat definitions need to be taken care of...
        $newname = 'TEST_BOOLEAN';
        if($this->tableColumnExists($newname)) {
            $this->dropColumn($newname);
        }
        $this->modifyColumn('TEST_INT', Processor_DB_Abstract::TYPE_BOOLEAN, null, $options, null, $newname);

        $SQL = "UPDATE `".$this->getTableName()."` SET `TEST ADD COL`='TEST';";
        $this->query($SQL);
        return $this;
    }
}