<?php


/**
 * Processor_Row_Abstract
 */

abstract class Processor_Row_Abstract {

  public function __construct($params = null){
    return $this->init($params);
  }

  /**
   * init
   * @return null
   */
  public function init($params = null) {
    return null;
  }

  /**
   * processRow
   * @param $row The row array
   * @return string
   */
  abstract public function process($row);

}