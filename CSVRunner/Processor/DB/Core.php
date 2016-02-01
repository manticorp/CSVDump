<?php
require_once "Abstract.php";

/**
 * Processor_DB_Core
 */
class Processor_DB_Core extends Processor_DB_Abstract
{
    public function changeColTypes($cols){
        // Adding/modifying a column using the built in method
        foreach($cols as $col => $type){
            if($type !== "auto") $this->modifyColumn($col, $type);
        }
        return $this;
    }
}