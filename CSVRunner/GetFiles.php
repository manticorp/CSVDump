<?php

include_once(__DIR__ . "/Config.php");
include_once(__DIR__ . "/CSVRunner.php");

$fileList = glob(APPLICATION_PATH.'/input/*.csv');

$output = array();

foreach($fileList as $fn){
    $file = array();
    $file['fn']       = $fn;
    $file['data-fn']  = urlencode(realpath($fn));
    $file['idbase']   = CSVRunner::getDBName($fn);
    $file['basename'] = basename($fn);
    $file['size']     = CSVRunner::fileSizeString($fn, 1);
    $file['rowcount'] = CSVRunner::numRowsInFile($fn);
    $file['cols']     = CSVRunner::getFirstRow($fn);
    $file['coltypes'] = CSVRunner::$colTypes;
    $output[] = $file;
}

echo json_encode($output);