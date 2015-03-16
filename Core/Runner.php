<?php

include_once(__DIR__ . "/Config.php");

/**
 * CsvRunner
 */
class CSVRunner {
    private $success = false;
    private $mysqli = null;
    private $pu = null;
    private $vars = array(
        'ifn'           => '',
        'ofn'           => '',
        'chunks'        => 1000,
        'limit'         => INF,
        'replace'       => true,
        'hh'            => true,
        'aa'            => true,
        'jp'            => true,
        'rowProcessor'  => false,
        'dbProcessor'   => false,
        'method'        => 1,
        'tableName'     => '',
        'db' => array(
            'host'      => 'localhost',
            'user'      => 'root',
            'password'  => '',
            'db'        => '',
            'table'     => '',
        ),
    );

    private $state = array(
        'numlines' => 0,
        'ifh'      => null,
        'ofh'      => null,
    );

    public function __construct($pu, $db, $memoryLimit = '512M', $timezone = 'UTC', $timeLimit = 600)
    {
        $this->pu = $pu;
        $this->vars['db'] = $db;
        // Increase memory limit
        ini_set('memory_limit', $memoryLimit);
        // Set time limit
        set_time_limit($timeLimit);
        // TZ settings
        date_default_timezone_set($timezone);
        // Turn on all error reporting.
        error_reporting(E_ALL);
        // Register a shutdown function to handle any errors that cause quitting or early termination,
        // including timeouts and anything that doesn't trigger errors.
        register_shutdown_function('shutdown');
        return $this;
    }

    public function dump()
    {
        // initiate a MySQLi connection
        $this->getMysqli();

        // Number of lines in the input file
        $numLines = ($method = 1) ? count(CSVRunner::csvFileToArray($this->vars['ifn'])) : CSVRunner::numRowsInFile($this->vars['ifn']);

        $rowProcessor = $this->getProcessor($this->vars['rowProcessor']);
        $dbProcessor  = $this->getProcessor(
            $this->vars['dbProcessor'],
            $this->vars['db']
        );

        if(!$this->tableExists()){
            $this->createTable();
        }

        if($this->vars['replace']){
            $SQL = 'TRUNCATE TABLE `' . $this->vars['db']['table'] . '`';
            $this->executeSql($SQL);
        }

        $stageOptions = array(
            'name'       => 'Dumping ',
            'message'    => 'Dumping data',
            'totalItems' => $numLines, // The total amount of items processed in this stage
        );
        $this->pu->nextStage($stageOptions);

        // Get column names from existing table
        $SQL = <<<EOF
    SELECT `COLUMN_NAME`
    FROM `INFORMATION_SCHEMA`.`COLUMNS`
    WHERE `TABLE_SCHEMA`='%s'
        AND `TABLE_NAME`='%s';
EOF;

        $SQL = sprintf($SQL, $this->vars['db']['db'], $this->vars['db']['table']);

        // Die if result is invalid.
        $r       = $this->executeSql($SQL);
        $columns = $r->fetch_all();

        $columns = call_user_func_array('array_merge', $columns);
        if(($key = array_search('id', $columns)) !== false) {
            unset($columns[$key]);
        }

        if (file_exists($this->vars['ofn'])) {
            unlink($this->vars['ofn']);
        }

        if($dbProcessor !== false) $dbProcessor->preImport();

        switch($this->vars['method']){
            case 1:
                $csv = self::csvFileToArray($this->vars['ifn']);
                $i = 0;
                $numLines = count($csv);
                foreach($csv as $row){
                    if($rowProcessor !== false){
                        $row = $rowProcessor->process($row);
                    }
                    $i++;
                    if ($i % $this->vars['chunks'] === 0) {
                        $this->loadAndEmptyCSV(array_keys($row));

                        // printf('%7d / %7d <br />',$i, $numLines);

                        @ob_end_flush();
                        @flush();

                        $this->pu->setStageMessage("Processing Item $i / $numLines");
                        $this->pu->incrementStageItems($chunks, true);
                    }
                    fwrite($this->getOutputFileHandle(), $this->processLine($row, $this->vars['method']));
                }
                $this->loadAndEmptyCSV(array_keys($row));
                break;
            case 2:
            default:
                if ($this->getInputFileHandle()) {
                    $i = 0;
                    while (($buffer = fgets($infile)) !== false) {
                        $i++;
                        if (($i === 1 & $this->vars['hh']) || $i > $this->vars['limit']) {
                            continue;
                        } elseif ($i % $this->vars['chunks'] === 0) {

                            $this->loadAndEmptyCSV($columns);

                            @ob_end_flush();
                            @flush();

                            $this->pu->setStageMessage("Processing Item $i / $numLines");
                            $this->pu->incrementStageItems($chunks, true);
                        }
                        fwrite($this->getOutputFileHandle(), $this->processLine($buffer, $this->vars['method']));
                    }
                    if (!feof($infile)) {
                        trigger_error('Error: Unexpected FGETS() fail.');
                    }
                    fclose($infile);
                } else {
                    trigger_error("Failed to open $fn");
                }
                $this->loadAndEmptyCSV($columns);
                break;
        }

        fclose($this->state['ofh']);
        if (file_exists($this->vars['ofn'])) {
            @unlink($this->vars['ofn']);
        }

        if($dbProcessor !== false) $dbProcessor->postImport();

        $msg = 'Totally Completed';
        $this->pu->totallyComplete($msg);

        return $this;
    }

    private function processLine($line, $method = 1)
    {
        // return $line;
        $line = ($method == 1) ? $line : str_getcsv($line);
        return
        '"' .
        implode(
            "\",\"",
            array_map(
                function ($e) {
                    if($e == '' || $e == 'NULL' || $e == null) return '\\N';
                    return str_replace('NULL', '\\N',
                        str_replace("\n", '\n',
                            str_replace('"', "\\'", $e)
                        )
                    );
                },
                $line
            )
        )
        . "\"\n";
    }

    private function loadAndEmptyCSV($columns = null)
    {
        fclose($this->state['ofh']);
        $SQL = "LOAD DATA LOCAL INFILE '" . str_replace('\\', '\\\\', realpath($this->vars['ofn'])) . "'
    INTO TABLE {$this->vars['db']['table']}
    FIELDS TERMINATED BY ',' ENCLOSED BY '\"' ESCAPED BY '\\\\'
    LINES TERMINATED BY '\\n'";

        if ($columns !== null) {
            $SQL .= "\n(`" . implode("`, `", $columns) . "`)";
        }

        $this->executeSql($SQL);

        rename($this->vars['ofn'], $this->vars['ofn'].'.bak');

        do {
            usleep(500);
            $this->state['ofh'] = @fopen($this->vars['ofn'], 'w');
        } while ($this->state['ofh']  === false);

        return $this->state['ofh'] ;
    }

    public function createTable()
    {
        // Create out table
        // First line of the file.
        $fh = $this->getInputFileHandle();
        rewind($fh);
        $fl = fgets($fh);
        // Trim and htmlentities the first line of the file to make column names
        $cols = array_map('trim', array_map('htmlentities', str_getcsv($fl)));
        // array to hold definitions
        $defs = array();
        // if our table *doesn't* have headers, give generic names
        if (!$this->vars['hh']) {
            $oc   = $cols;
            $c    = count($cols);
            $cols = array();
            for ($i = 1; $i <= $c; $i++) {
                $col = "    `Column_" . $i . "` ";
                $col .= is_numeric($oc[$i]) ? "DECIMAL(12,6) DEFAULT NULL" :
                                                       "VARCHAR(512) DEFAULT NULL";
                $cols[] = $col;
            }
        } else {
            // if our table *does* have headers
            $sl = array_values(CSVRunner::csvFileToArray($this->vars['ifn'])[0]);
            if(count($sl) !== count($cols)){
                $baseurl = explode('?', $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'])[0];
                trigger_error("Number of columns inconsistent throughout file. For more information, see the error documentation.");
                exit(1);
            }
            foreach ($cols as $i => &$col) {
                $col = '    `' . $col . '` ';
                $col .= is_numeric($sl[$i]) ? "DECIMAL(12,6) DEFAULT NULL" :
                                                        "VARCHAR(512) DEFAULT NULL";
            }
            array_unshift($cols, '    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY');
        }
        $SQL = "CREATE TABLE IF NOT EXISTS `{$this->vars['db']['db']}`.`{$this->vars['db']['table']}` (\n";
        $SQL .= implode(",\n", $cols);
        $SQL .= "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
        $this->executeSql($SQL);
        return $this;
    }

    public function getInputFileHandle()
    {
        if($this->state['ifh'] === null || $this->state['ifh'] === false){
            $this->state['ifh'] = $this->getFileHandle($this->vars['ifn']);
        }
        return $this->state['ifh'];
    }

    public function getOutputFileHandle()
    {
        if($this->state['ofh'] === null || $this->state['ofh'] === false){
            $this->state['ofh'] = $this->getFileHandle($this->vars['ofn'], 'w');
        }
        return $this->state['ofh'];
    }

    private function getFileHandle($fn, $mode = 'r')
    {
        if(!file_exists($fn) && $mode == 'r') trigger_error($fn . ' does not exist');

        $fh = @fopen($fn, $mode);
        if($fh === false){
            trigger_error('File: ' . $fn . ' cannot be opened');
            exit(1);
        }

        return $fh;
    }

    public function tableExists()
    {
        /**
         * Here we check to see if a table with that name already exists.
         */
        $mysqli = $this->getMysqli();
        $SQL = "SHOW TABLES LIKE '".$this->vars['db']['table']."'";
        // echo $SQL;
        if (!$result = $mysqli->query($SQL)) {
            echo $mysqli->error;
            exit();
        }
        return !($result->num_rows == 0);
    }

    public function getVars()
    {
        $this->vars['db']['db'] = (isset($_GET['db']) && !empty($_GET['db'])) ? $_GET['db'] : $this->vars['db']['db'];

        if (!isset($_GET['fn'])) {
            trigger_error('Filename not set');
        } else {
            $this->vars['ifn'] = urldecode($_GET['fn']);
        }

        if (!file_exists($this->vars['ifn'])) {
            trigger_error($this->vars['ifn'] . ' doesn\'t exist');
        }
        $this->vars['ifn'] = realpath($this->vars['ifn']);

        // Get a bunch of settings.
        $this->vars['chunks']  = (isset($_GET["chunks"])) ? intval($_GET["chunks"]) : 1000;
        $this->vars['limit']   = (isset($_GET["limit"])) ? intval($_GET["limit"]) : INF;
        $this->vars['replace'] = (isset($_GET["replace"])) ? filter_var($_GET["replace"],FILTER_VALIDATE_BOOLEAN) : false;
        // has headers
        $this->vars['hh'] = (isset($_GET["hh"])) ? filter_var($_GET["hh"], FILTER_VALIDATE_BOOLEAN) :false;
        $this->vars['aa'] = (isset($_GET["aa"])) ? filter_var($_GET["aa"], FILTER_VALIDATE_BOOLEAN) :true;
        // 'just processing'
        $this->vars['jp'] = (isset($_GET["jp"])) ? filter_var($_GET["jp"], FILTER_VALIDATE_BOOLEAN) : false;
        // Processors
        $this->vars['rowProcessor'] = (isset($_GET["processor"])) ? "Processor_Row_" . $_GET["processor"] : false;
        $this->vars['dbProcessor']  = (isset($_GET["processor"])) ? "Processor_DB_"  . $_GET["processor"] : false;

        $this->vars['method'] = ($this->vars['hh']) ? 1 : 2;

        // This is the output filename, used as a temporary file for dumping to DB
        $this->vars['ofn'] = APPLICATION_PATH . '/var/output-' . (int) (rand() * 1000) . '.csv';

        // Gets the table name
        $this->vars['db']['table'] = (isset($_GET['table']) && !empty($_GET['table'])) ? $_GET['table'] : CSVRunner::getDBName($this->vars['ifn']);

        return $this;
    }

    public static function getDBName($fn)
    {
        $parts = explode('.',basename($fn));
        $parts = array_reverse($parts);
        unset($parts[0]);
        $parts = array_reverse($parts);
        return CSVRunner::toTableName(implode('.',$parts));
    }

    public static function numRowsInFile($fn)
    {
        $numLines = 0;

        $fp = fopen($fn, 'r');

        if(!$fp){
            trigger_error('Failed to open file: ' . $fn);
        }

        while (($buffer = fgets($fp)) !== false) {
        $numLines++;
        }

        fclose($fp);
        return $numLines;
    }

    public static function toTableName($name)
    {
        $name = str_replace(" ","_",$name);
        return $name;
    }

    public static function fileSizeString($fn, $precision = 2, $wu = true)
    {
        return CSVRunner::formatBytes(filesize($fn), $precision, $wu);
    }

    public static function formatBytes($bytes, $precision = 2, $wu = true)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        // Uncomment one of the following alternatives
        $bytes /= pow(1024, $pow);
        // $bytes /= (1 << (10 * $pow));

        $r = sprintf('%.'.$precision.'f',round($bytes, $precision));
        if($wu) $r .= ' ' . $units[$pow];

        return $r;
    }

    public static function getProcessors()
    {
        $folders = array(
            'DB',
            'Row',
        );
        $prefix  = APPLICATION_PATH . '/Core/Processor/';
        $suffix  = '/*.php';
        $folders = array_map(function ($e) use ($prefix, $suffix) {return $prefix . $e . $suffix;}, $folders);
        $files = array_map('basename', call_user_func_array('array_merge', array_map('glob', $folders)));
        $files = array_map(function ($e) {return str_replace('.php', '', $e);}, $files);
        $files = array_unique($files);
        $files = array_filter($files, function ($e) {return $e !== 'Abstract';});
        return $files;
    }

    private function getProcessor($className = false, $params = null)
    {
        if($className == false){
            return false;
        }
        $fn = __DIR__ . DIRECTORY_SEPARATOR . str_replace('_',DIRECTORY_SEPARATOR,$className).'.php';
        if(!file_exists($fn)){
            return false;
        }
        include($fn);
        $processor = new $className($params);
        return $processor;
    }

    private function getMysqli()
    {
        if($this->mysqli == null){
            $this->mysqli = new mysqli(
                $this->vars['db']['host'],
                $this->vars['db']['user'],
                $this->vars['db']['password'],
                $this->vars['db']['db']
            );
            if ($this->mysqli->connect_errno) {
                trigger_error("Connection failed: %s \n", $this->mysqli->connect_error);
                $this->mysqli = null;
                exit();
            }
            $this->mysqli->set_charset("utf8");
        }
        return $this->mysqli;
    }

    private function executeSql($SQL, $pu = null, $inc = 1)
    {
        $mysqli = $this->getMysqli();
        if (!$result = $mysqli->query($SQL)) {
            $msg  = $SQL;
            $msg .= PHP_EOL;
            $msg .= 'There was an error running the query [' . $mysqli->error . ']';
            trigger_error($msg);
            exit(0);
        }
        if ($pu !== null) {
            $pu->incrementStageItems($inc, true);
        }

        return $result;
    }

    public static function csvFileToArray($filename='', $delimiter=',')
    {
        if(!file_exists($filename) || !is_readable($filename))
            return FALSE;

        $f = fopen($filename, 'r');
        $data = array();
        if (($handle = fopen($filename, 'r')) !== FALSE)
        {
            $line = fgets($f);
            fclose($f);
            $header = str_getcsv($line);
            $csv = file_get_contents($filename);
            $csv = substr($csv, strpos($csv, "\n")+1);
            $csv = str_getcsv($csv);
            $numHeaders = count($header);
            $tempCsv = array();
            $numRows = count($csv);
            foreach($csv as $key => $field){
                if($key % ($numHeaders-1) == 0 && $key !== 0 && $key !== $numRows-1){
                    $parts = explode("\n", $field);
                    if(count($parts) == 2){
                        $nf = trim($parts[0]);
                        if($nf[0] == '"' && $nf[strlen($nf)-1] == '"'){
                            $nf = substr($nf, 1, strlen($nf)-2);
                        }
                        $tempCsv[] = trim($nf);
                        $nf = trim($parts[1]);
                        if($nf[0] == '"' && $nf[strlen($nf)-1] == '"'){
                            $nf = substr($nf, 1, strlen($nf)-2);
                        }
                        $tempCsv[] = $nf;
                    }
                } else {
                    $tempCsv[] = $field;
                }
            }
            $csv = $tempCsv;
            $csv = array_chunk($csv,count($header));
            $csv = array_map(function($row) use ($header){
                return array_combine($header,$row);
            }, $csv);
        }
        return $csv;
    }
}

function shutdown()
{
    global $success;
    if (isset($success) && $success === false) {
        $msg = '';
        $mmu = memory_get_peak_usage();
        $msg = sprintf("Max memory usage: %s", formatBytes($mmu, 1));
    }
    if (isset($_GET['print'])) {
        echo '<pre>' . $msg . '</pre>';
    }
}