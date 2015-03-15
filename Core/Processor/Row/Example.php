<?php

include "Abstract.php";

/**
 * Processor_Row_Example
 */

class Processor_Row_Example extends Processor_Row_Abstract
{

    /**
     * init
     * @param  $row   The row array
     * @return void
     */
    public function init($params = null)
    {
        parent::init($params);
    }

    /**
     * processRow
     * @return string
     */
    public function process($row)
    {
        $row = array_map('strtoupper', $row);
        return $row;
    }
}
