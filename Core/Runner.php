<?php

// Include our config file
include_once(__DIR__ . "/Config.php");

/**
 * CsvRunner
 */
class CSVRunner {

    private $success = false; // Whether we were succesful or not (unused atm)
    private $mysqli = null;   // MySQL connection
    private $pu = null;       // Progress Updater
    private $vars = array(
        'ifn'           => '',            // Input Filename
        'ofn'           => '',            // Output Filename (temp file)
        'chunks'        => 1000,          // Amount of rows in each chunk
        'limit'         => INF,           // Upper limit on rows to process
        'replace'       => true,          // Whether to replace data in table (or, alternatively, append)
        'hh'            => true,          // 'Has Headers' whether file has headers
        'aa'            => true,          // Unused.
        'jp'            => true,          // 'Just Processing' - deprecated
        'rowProcessor'  => false,         // The Row Processor Instance
        'dbProcessor'   => false,         // The DB  Processor Instance
        'method'        => 1,             // Method (used internally - not really needed, duplicates has header functionality)
        'tableName'     => '',            // For storing the name of the table (deprecated)
        'delimeter'     => false,         // The CSV file delimiter
        'quotechar'     => false,         // The CSV file quote character
        'escapechar'    => false,         // The CSV file escape character
        'db' => array(                    // DB details
            'host'      => 'localhost',     // DB Host
            'user'      => 'root',          // DB Username
            'password'  => '',              // DB Password
            'db'        => '',              // DB Database
            'table'     => '',              // DB Table to dump to
        ),
    );

    // Possible CSV delimiters (not limited to this, but used as a guideline)
    public static $delimiters = array(
        ',',';','|',':',"\t"
    );

    // Possible CSV quote characters (not limited to this, but used as a guideline)
    public static $quoteChars = array(
        '"','`',"'"
    );

    // Possible CSV escape characters (not limited to this, but used as a guideline)
    public static $escapeChars = array(
        '\\','`',"'"
    );

    // Used to store state variables
    private $state = array(
        'numlines' => 0,
        'ifh'      => null,
        'ofh'      => null,
    );

    /**
     * Constructs our Runner!
     * @param \Manticorp\ProgressUpdater $pu           A ProgressUpdater instance
     * @param array                      $db           The database configuration
     * @param string                     $memoryLimit  Memory limit to use
     * @param integer                    $timeLimit    Time limit for script execution
     */
    public function __construct(\Manticorp\ProgressUpdater $pu, $db, $memoryLimit = '512M', $timeLimit = 600)
    {
        $this->pu = $pu;
        $this->vars['db'] = array_merge($this->vars['db'],$db);
        // Increase memory limit
        ini_set('memory_limit', $memoryLimit);
        // Set time limit
        set_time_limit($timeLimit);
        // Turn on all error reporting.
        error_reporting(E_ALL);
        // Register a shutdown function to handle any errors that cause quitting or early termination,
        // including timeouts and anything that doesn't trigger errors.
        register_shutdown_function('shutdown');
        return $this;
    }

    /**
     * Main dump function
     * @return CSVRunner $this
     */
    public function dump()
    {
        // initiate a MySQLi connection
        $this->getMysqli();

        // Number of lines in the input file
        $numLines = ($method = 1) ? $this->numLines() : CSVRunner::numRowsInFile($this->vars['ifn']);

        $rowProcessor = $this->getProcessor($this->vars['rowProcessor']);
        $dbProcessor  = $this->getProcessor(
            $this->vars['dbProcessor'],
            $this->vars['db']
        );

        // If the table doesn't exist, create it.
        if(!$this->tableExists()){
            $this->createTable();
        }

        // If we want to replace the data in the table, truncate it
        if($this->vars['replace']){
            $SQL = 'TRUNCATE TABLE `' . $this->vars['db']['table'] . '`';
            $this->executeSql($SQL);
        }

        // Update the progressupdater accordingly
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

        // Gets a nice array of column names
        $columns = call_user_func_array('array_merge', $columns);

        // We want the 'id' column to be autogenerated
        if(($key = array_search('id', $columns)) !== false) {
            unset($columns[$key]);
        }

        // Delete any old temp files
        if (file_exists($this->vars['ofn'])) {
            unlink($this->vars['ofn']);
        }

        // Call the dbProcessor preImport function
        if($dbProcessor !== false) $dbProcessor->preImport();

        switch($this->vars['method']){
            case 1: // Currently the method for files with headers
                // Funnily, this method is actually faster than looping through
                // using the SPL file thing :/ it would seem, anyway...
                // Also, it may seem counter intuitive to load the whole
                // CSV into an array, but it's the only way to get an accurate line
                // count...and it helps in so many ways...
                //
                // Really, method 2 can be used for everything if the line count isn't
                // important to you...
                $csv = self::csvFileToArray($this->vars['ifn'], $this->vars['delimiter'], $this->vars['quotechar'], $this->vars['escapechar']);
                $i = 0;
                $numLines = count($csv);
                $this->pu->stage->setTotalItems($numLines);
                $headers = array_keys($csv[0]);
                $this->pu->setStageTotalItems($numLines);
                foreach($csv as $row){
                    if($rowProcessor !== false){
                        $row = $rowProcessor->process($row);
                    }
                    $i++;
                    if ($i % $this->vars['chunks'] === 0) {
                        $this->loadAndEmptyCSV(array_keys($row));
                        $this->pu->setStageMessage("Processing Item $i / $numLines - " . round(($i/$numLines)*100, 1) . "% Complete");
                        $this->pu->incrementStageItems($this->vars['chunks'], true);
                    }
                    $mysqli = $this->getMysqli();
                    $row = array_map(function($a) use ($mysqli){ return $mysqli->real_escape_string($a);}, $row);
                    fwrite($this->getOutputFileHandle(), $this->processLine($row));
                }
                $this->loadAndEmptyCSV(array_keys($row));
                break;
            case 2: // Currently the method for files without headers.
            default:
                // Here we use the super duper SplFileObject...
                $file = new SplFileObject($this->vars['ifn']);
                $i = 0;
                while (!$file->eof()) {
                    $i++;
                    if (($i === 1 && $this->vars['hh']) || $i > $this->vars['limit']) {
                        continue;
                    } elseif ($i % $this->vars['chunks'] === 0) {
                        $this->loadAndEmptyCSV($columns);
                        $this->pu->setStageMessage("Processing Item $i / $numLines");
                        $this->pu->incrementStageItems($this->vars['chunks'], true);
                    }
                    $row = $file->fgetcsv($this->vars['delimiter'], $this->vars['quotechar'], $this->vars['escapechar']);
                    if($rowProcessor !== false){
                        $row = $rowProcessor->process($row);
                    }
                    fwrite($this->getOutputFileHandle(), $this->processLine($row));
                }
                $this->loadAndEmptyCSV($columns);
                break;
        }

        // Close the file and delete it.
        fclose($this->state['ofh']);
        if (file_exists($this->vars['ofn'])) {
            @unlink($this->vars['ofn']);
        }

        // Do our post import function.
        if($dbProcessor !== false) $dbProcessor->postImport();

        // We're all done!
        $msg = 'Totally Completed';
        $this->pu->totallyComplete($msg);

        return $this;
    }

    /**
     * A far too convoluted, non-self documenting function
     * for processing each line. It basically does a bunch
     * of replacements on each cell and then it implodes it
     * into a regularised CSV format.
     *
     * This may seem counter intuitive, but basically this
     * allows us to get it into a distinct, reliable format
     * for use with inserting into MySQL tables using the
     * LOAD DATA INFILE functionality...
     *
     * From experience, this seems to be the fastest and most
     * hassle free way of doing it.
     * @param  array  $line The current 'line' or row from the CSV file
     * @return string       A csv string representing that line
     */
    private function processLine($line)
    {
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

    /**
     * Uses the LOAD DATA LOCAL INFILE function to load data
     * into our MySQL table based on our temp csv file.
     * @param  array        $columns The column names
     * @return file pointer          The output file pointer (as we create a new one)
     */
    private function loadAndEmptyCSV($columns = null)
    {
        fclose($this->state['ofh']);
        $SQL = "LOAD DATA LOCAL INFILE '" . str_replace('\\', '\\\\', realpath($this->vars['ofn'])) . "'
    INTO TABLE `{$this->vars['db']['table']}`
    FIELDS TERMINATED BY ',' ENCLOSED BY '\"' ESCAPED BY '\\\\'
    LINES TERMINATED BY '\\n'";

        if ($columns !== null) {
            $SQL .= "\n(`" . implode("`, `", $columns) . "`)";
        }

        $this->executeSql($SQL);

        do {
            usleep(500);
            $this->state['ofh'] = @fopen($this->vars['ofn'], 'w');
        } while ($this->state['ofh']  === false);

        return $this->state['ofh'] ;
    }

    /**
     * Used to create the database table in the case there is none.
     *
     * It basically gleans info from the first line of the data file.
     * For better definitions of columns, it's pretty essential to just
     * use a DB Processor class.
     * @return CSVRunner $this
     */
    public function createTable()
    {
        // Create out table
        // First line of the file.
        $fh = $this->getInputFileHandle();
        rewind($fh);
        $fl = fgets($fh);
        // Trim and htmlentities the first line of the file to make column names
        $cols = array_map('trim', array_map('htmlentities', str_getcsv($fl, $this->vars['delimiter'], $this->vars['quotechar'], $this->vars['escapechar'])));
        // array to hold definitions
        $defs = array();
        // if our table *doesn't* have headers, give generic names
        if (!$this->vars['hh']) {
            $oc   = $cols;
            $c    = count($cols);
            $cols = array();
            for ($i = 0; $i < $c; $i++) {
                $col = "    `Column_" . $i . "` ";
                $col .= is_numeric($oc[$i]) ? "DECIMAL(12,6) DEFAULT NULL" :
                                                       "VARCHAR(512) DEFAULT NULL";
                $cols[] = $col;
            }
        } else {
            // if our table *does* have headers
            $file = new SplFileObject($this->vars['ifn']);
            $headers = $file->fgetcsv($this->vars['delimiter'], $this->vars['quotechar'], $this->vars['escapechar']);
            $sl = $file->fgetcsv($this->vars['delimiter'], $this->vars['quotechar'], $this->vars['escapechar']);
            if(count($sl) !== count($cols)){
                $baseurl = explode('?', $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'])[0];
                trigger_error("Number of columns inconsistent throughout file. For more information, see the error documentation.");
                exit(1);
            }
            // We can pretty much only guess two data types from one row of information
            foreach ($cols as $i => &$col) {
                $cname = $col;
                $col = '    `' . $cname . '` ';
                $col .= is_numeric($sl[$i]) ? "DECIMAL(12,6) DEFAULT NULL" :
                                                        "VARCHAR(512) DEFAULT NULL";
                $col .= " COMMENT '$cname'";
            }
        }
        // Always have an id column!
        array_unshift($cols, '    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT \'id\'');
        $SQL = "CREATE TABLE IF NOT EXISTS `{$this->vars['db']['db']}`.`{$this->vars['db']['table']}` (\n";
        $SQL .= implode(",\n", $cols);
        $SQL .= "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
        $this->executeSql($SQL);
        return $this;
    }

    /**
     * Pretty self explanatory, gets our input file handle
     * or creates it if it doesn't exist yet.
     * @return file handle The input file handle
     */
    public function getInputFileHandle()
    {
        if($this->state['ifh'] === null || $this->state['ifh'] === false){
            $this->state['ifh'] = $this->getFileHandle($this->vars['ifn']);
        }
        return $this->state['ifh'];
    }

    /**
     * Pretty self explanatory, gets our output file handle
     * or creates it if it doesn't exist yet.
     * @return file handle The output file handle
     */
    public function getOutputFileHandle()
    {
        if($this->state['ofh'] === null || $this->state['ofh'] === false){
            $this->state['ofh'] = $this->getFileHandle($this->vars['ofn'], 'w');
        }
        return $this->state['ofh'];
    }


    /**
     * Returns a file handle for $fn
     * @return file handle The input file handle
     */
    /**
     * Returns a file handle for $fn
     * @param  string       $fn   The filename
     * @param  string       $mode Mode
     * @return file handle        Thefile handle
     */
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

    /**
     * Detects if our table already exists
     * @return boolean Whether or not a table with our name exists.
     */
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

    /**
     * Gets variables from the URL and loads them into our var variable.
     * @return CSVRunner $this
     */
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
        $this->vars['chunks']     = (isset($_GET["chunks"])) ? intval($_GET["chunks"]) : 1000;
        $this->vars['limit']      = (isset($_GET["limit"])) ? intval($_GET["limit"]) : INF;
        $this->vars['replace']    = (isset($_GET["replace"])) ? filter_var($_GET["replace"],FILTER_VALIDATE_BOOLEAN) : false;
        $this->vars['delimiter']  = (isset($_GET["delimiter"])  && $_GET["delimiter"]  !== '') ? $_GET["delimiter"]  : ',';
        $this->vars['quotechar']  = (isset($_GET["quotechar"])  && $_GET["quotechar"]  !== '') ? $_GET["quotechar"]  : '"';
        $this->vars['escapechar'] = (isset($_GET["escapechar"]) && $_GET["escapechar"] !== '') ? $_GET["escapechar"] : '\\';
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

    /**
     * Gets a database friendly table name from a filename
     * @param  string $fn Filename
     * @return string     The table name
     */
    public static function getDBName($fn)
    {
        $parts = explode('.',basename($fn));
        $parts = array_reverse($parts);
        unset($parts[0]);
        $parts = array_reverse($parts);
        return CSVRunner::toTableName(implode('.',$parts));
    }

    /**
     * Get the number of lines in our input file
     * @return int The number of lines
     */
    public function numLines()
    {
        if($this->state['numlines']) return $this->state['numlines'];
        else return self::numRowsInFile($this->vars['ifn']);
    }

    /**
     * Gets an estimate for the number of rows in a file.
     * @param  string $fn Filename
     * @return int        The number of lines in the file.
     */
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

    /**
     * Creates a database suitable table name from a string.
     *
     * This isn't strictly necessary - MySQL is pretty flexible
     * @param  string $name The input name
     * @return string       The output name
     */
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

    public static function csvFileToArray($filename='', $delimiter=',', $quotechar='"', $escapechar = "\\")
    {
        if(!file_exists($filename) || !is_readable($filename))
            return FALSE;

        if($delimiter === false){
            $delimiter = self::findDelimiter($filename);
        }

        if($quotechar === false){
            $quotechar = self::findQuoteChar($filename);
        }

        $f = fopen($filename, 'r');
        $data = array();

        $file = new SplFileObject($filename);
        $headers = $file->fgetcsv($delimiter, $quotechar, $escapechar);
        $i = 0;
        $output = array();
        $headerCount = count($headers);
        while (!$file->eof()) {
            $i++;
            $csv = $file->fgetcsv($delimiter, $quotechar, $escapechar);
            if($headerCount !== count($csv) && count($csv) == 1 && trim($csv[0]) == ''){
                continue;
            }
            $csv = array_combine($headers,$csv);
            $output[] = $csv;
        }
        $csv = $output;
        return $csv;
    }

    /**
     * Naively finds the delimiter in a file by string count, or use Spl if it exists
     * @param  string $fn The file
     * @return string     The suspected delimiter
     */
    public static function findDelimiter($fn){
        if(class_exists('SplFileObject')){
            $file = new SplFileObject($fn);
            $def = $file->getCsvControl();
            return $def[0];
        }
        $contents = file_get_contents($fn, null, null, 0, min(filesize($fn),64000));
        $occurences = array();
        foreach(self::$delimiters as $delim){
            $occurences[$delim] = 0;
        }

        foreach($occurences as $delim => &$count){
            $count = substr_count($contents, $delim);
        }
        arsort($occurences);
        $occurences = array_flip($occurences);
        $delim = array_shift($occurences);
        return $delim;
    }

    /**
     * Naively finds the delimiter in a file by string count, or use Spl if it exists
     * @param  string $fn The file
     * @return string     The suspected delimiter
     */
    public static function findQuoteChar($fn){
        if(class_exists('SplFileObject')){
            $file = new SplFileObject($fn);
            $def = $file->getCsvControl();
            if(isset($def[1])){
                return $def[1];
            } else {
                return '"';
            }
        }
        $contents = file_get_contents($fn, null, null, 0, min(filesize($fn),64000));
        $occurences = array();
        foreach(self::$quotechars as $quote){
            $occurences[$quote] = 0;
        }

        foreach($occurences as $quote => &$count){
            $count = substr_count($contents, $quote);
        }
        arsort($occurences);
        $occurences = array_flip($occurences);
        $quote = array_shift($occurences);
        return $quote;
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