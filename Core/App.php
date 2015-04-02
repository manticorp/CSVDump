<?php

include_once(__DIR__ . "/Config.php");
include_once(__DIR__ . "/Runner.php");
include_once(__DIR__ . "/ajaxProcessUpdater/Manticorp/ProgressUpdater.php");

$pu         = new \Manticorp\ProgressUpdater($puOptions);
$csvRunner  = new \CSVRunner($pu, $db, $memoryLimit, $timeLimit);

$csvRunner->getVars()->dump();