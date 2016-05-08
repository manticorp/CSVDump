<?php

/**
 * Processor_DB_Abstract
 */

abstract class Processor_DB_Abstract
{
    /**
     * All the constants below are taken from the Magento Varien Library!
     */

    /**
     * Types of columns
     */
    const TYPE_BOOLEAN          = 'boolean';
    const TYPE_SMALLINT         = 'smallint';
    const TYPE_INTEGER          = 'integer';
    const TYPE_BIGINT           = 'bigint';
    const TYPE_FLOAT            = 'float';
    const TYPE_NUMERIC          = 'numeric';
    const TYPE_DECIMAL          = 'decimal';
    const TYPE_DATE             = 'date';
    const TYPE_TIMESTAMP        = 'timestamp'; // Capable to support date-time from 1970 + auto-triggers in some RDBMS
    const TYPE_DATETIME         = 'datetime'; // Capable to support long date-time before 1970
    const TYPE_TEXT             = 'text';
    const TYPE_BLOB             = 'blob'; // Used for back compatibility, when query param can't use statement options
    const TYPE_VARBINARY        = 'varbinary'; // A real blob, stored as binary inside DB

    // Deprecated column types, support is left only in MySQL adapter.
    const TYPE_TINYINT          = 'tinyint';        // Internally converted to TYPE_SMALLINT
    const TYPE_CHAR             = 'char';           // Internally converted to TYPE_TEXT
    const TYPE_VARCHAR          = 'varchar';        // Internally converted to TYPE_TEXT
    const TYPE_LONGVARCHAR      = 'longvarchar';    // Internally converted to TYPE_TEXT
    const TYPE_CLOB             = 'cblob';          // Internally converted to TYPE_TEXT
    const TYPE_DOUBLE           = 'double';         // Internally converted to TYPE_FLOAT
    const TYPE_REAL             = 'real';           // Internally converted to TYPE_FLOAT
    const TYPE_TIME             = 'time';           // Internally converted to TYPE_TIMESTAMP
    const TYPE_BINARY           = 'binary';         // Internally converted to TYPE_BLOB
    const TYPE_LONGVARBINARY    = 'longvarbinary';  // Internally converted to TYPE_BLOB

    /**
     * Default and maximal TEXT and BLOB columns sizes we can support for different DB systems.
     */
    const DEFAULT_TEXT_SIZE     = 1024;
    const MAX_TEXT_SIZE         = 2147483648;
    const MAX_VARBINARY_SIZE    = 2147483648;

    /**
     * Default values for timestampses - fill with current timestamp on inserting record, on changing and both cases
     */
    const TIMESTAMP_INIT_UPDATE = 'TIMESTAMP_INIT_UPDATE';
    const TIMESTAMP_INIT        = 'TIMESTAMP_INIT';
    const TIMESTAMP_UPDATE      = 'TIMESTAMP_UPDATE';

    /**
     * Actions used for foreign keys
     */
    const ACTION_CASCADE        = 'CASCADE';
    const ACTION_SET_NULL       = 'SET NULL';
    const ACTION_NO_ACTION      = 'NO ACTION';
    const ACTION_RESTRICT       = 'RESTRICT';
    const ACTION_SET_DEFAULT    = 'SET DEFAULT';

    /**
     * Indexes
     */
    const INDEX_TYPE_PRIMARY    = 'primary';
    const INDEX_TYPE_UNIQUE     = 'unique';
    const INDEX_TYPE_INDEX      = 'index';
    const INDEX_TYPE_FULLTEXT   = 'fulltext';

    const FK_ACTION_CASCADE     = 'CASCADE';
    const FK_ACTION_SET_NULL    = 'SET NULL';
    const FK_ACTION_NO_ACTION   = 'NO ACTION';
    const FK_ACTION_RESTRICT    = 'RESTRICT';
    const FK_ACTION_SET_DEFAULT = 'SET DEFAULT';

    const INSERT_ON_DUPLICATE   = 1;
    const INSERT_IGNORE         = 2;

    const ISO_DATE_FORMAT       = 'yyyy-MM-dd';
    const ISO_DATETIME_FORMAT   = 'yyyy-MM-dd HH-mm-ss';

    const INTERVAL_SECOND       = 'SECOND';
    const INTERVAL_MINUTE       = 'MINUTES';
    const INTERVAL_HOUR         = 'HOURS';
    const INTERVAL_DAY          = 'DAYS';
    const INTERVAL_MONTH        = 'MONTHS';
    const INTERVAL_YEAR         = 'YEARS';

    private $mysqli;
    private $params;

    /**
     * construct
     * @return void
     */
    public function __construct($params = null)
    {
        $this->init($params);
        return $this;
    }

    public function init($params)
    {
        $this->params = $params;
        $this->getConnection();
        return $this;
    }

    public function getTableName()
    {
        return $this->params['table'];
    }

    public function getDbName()
    {
        return $this->params['db'];
    }

    public function getColumnDefinition($col)
    {
        $sql = "SHOW FIELDS FROM `".$this->getTableName()."`;";
        $r = $this->query($sql);
        while ($row = $r->fetch_array(MYSQLI_ASSOC)) {
            if($row['Field'] == $col) return $row;
        }
        trigger_error('Column ' . $col . ' doesn\'t exist');
        exit(1);
    }

    public function getConnection()
    {
        $mysqlServer   = $this->params['host'];
        $mysqlUser     = $this->params['user'];
        $mysqlPassword = $this->params['password'];
        $mysqlDb       = $this->params['db'];
        $this->mysqli   = new mysqli($mysqlServer, $mysqlUser, $mysqlPassword, $mysqlDb);
        if ($this->mysqli->connect_errno) {
            printf("Connection failed: %s \n", $this->mysqli->connect_error);
            exit();
        }
        $this->mysqli->set_charset("utf8");
        return $this->mysqli;
    }

    public function query($sql, $suppressErrors = false)
    {
        if (!$r = $this->mysqli->query($sql)) {
            if (!$suppressErrors) {
                user_error($this->mysqli->error);
            }
        }
        return $r;
    }

    public function changeColumnCharset($column, $newCharSet)
    {
        $def = $this->getColumnDefinition($column);
        $sql = 'ALTER TABLE `' . $this->getTableName() .'` MODIFY `' . $column . '` ' . strtoupper($def['Type']) . ' CHARACTER SET ' . $newCharSet .';';
        $this->query($sql);
        return $this;
    }

    public function changeColumnCollation($column, $newCollation, $newCharSet = null)
    {
        $def = $this->getColumnDefinition($column);
        if(is_null($newCharSet)) {
            $newCharSet = explode("_",$newCollation)[0];
        }
        $sql = 'ALTER TABLE `' . $this->getTableName() .'` MODIFY `' . $column . '` ' . strtoupper($def['Type']) . ' CHARACTER SET ' . $newCharSet . ' COLLATE ' . $newCollation .';';
        $this->query($sql);
        return $this;
    }

    public function dropColumn($name)
    {
        $sql = "ALTER TABLE `" . $this->getTableName() . "` DROP `" . $name . "`;";
        $this->query($sql);
        return $this;
    }

    public function modifyColumn($name, $type, $size = null, $options = array(), $comment = null, $newname = null)
    {
        if (!$this->tableColumnExists($name)) {
            trigger_error('Column: \'' . $name .'\' doesn\'t exist!');
            exit(1);
        }
        // Because of our requirements, modifying is essentially the same as adding.
        return $this->addColumn($name, $type, $size, $options, $comment, $newname);
    }

    /**
     * Adds column to table.
     *
     * $options contains additional options for columns. Supported values are:
     * - 'unsigned', for number types only. Default: FALSE.
     * - 'precision', for numeric and decimal only. Default: taken from $size, if not set there then 0.
     * - 'scale', for numeric and decimal only. Default: taken from $size, if not set there then 10.
     * - 'default'. Default: not set.
     * - 'nullable'. Default: TRUE.
     * - 'primary', add column to primary index. Default: do not add.
     * - 'primary_position', only for column in primary index. Default: count of primary columns + 1.
     * - 'identity' or 'auto_increment'. Default: FALSE.
     * - 'charset' e.g. utf8
     * - 'collate' e.g. utf8_general_ci
     *
     * @param string $name the column name
     * @param string $type the column data type
     * @param string|int|array $size the column length
     * @param array $options array of additional options
     * @param string $comment column description
     * @return $this
     */
    public function addColumn($name, $type, $size = null, $options = array(), $comment = null, $newname = null)
    {
        $position           = 0;
        $default            = false;
        $nullable           = true;
        $length             = null;
        $scale              = null;
        $precision          = null;
        $unsigned           = false;
        $primary            = false;
        $primaryPosition    = 0;
        $identity           = false;
        $charset            = null;
        $collate            = null;

        // Convert deprecated types
        switch ($type) {
            case self::TYPE_CHAR:
            // case self::TYPE_VARCHAR:
            case self::TYPE_LONGVARCHAR:
            case self::TYPE_CLOB:
                $type = self::TYPE_TEXT;
                break;
            case self::TYPE_TINYINT:
                $type = self::TYPE_SMALLINT;
                break;
            case self::TYPE_DOUBLE:
            case self::TYPE_REAL:
                $type = self::TYPE_FLOAT;
                break;
            case self::TYPE_TIME:
                $type = self::TYPE_TIMESTAMP;
                break;
            case self::TYPE_BINARY:
            case self::TYPE_LONGVARBINARY:
                $type = self::TYPE_BLOB;
                break;
        }

        // Prepare different properties
        switch ($type) {
            case self::TYPE_BOOLEAN:
                break;

            case self::TYPE_SMALLINT:
            case self::TYPE_INTEGER:
            case self::TYPE_BIGINT:
                if (!empty($options['unsigned'])) {
                    $unsigned = true;
                }

                break;

            case self::TYPE_FLOAT:
                if (!empty($options['unsigned'])) {
                    $unsigned = true;
                }
                break;

            case self::TYPE_DECIMAL:
            case self::TYPE_NUMERIC:
                $match      = array();
                $scale      = 10;
                $precision  = 0;
                // parse size value
                if (is_array($size)) {
                    if (count($size) == 2) {
                        $size       = array_values($size);
                        $precision  = $size[0];
                        $scale      = $size[1];
                    }
                } else if (preg_match('#^(\d+),(\d+)$#', $size, $match)) {
                    $precision  = $match[1];
                    $scale      = $match[2];
                }
                // check options
                if (isset($options['precision'])) {
                    $precision = $options['precision'];
                }

                if (isset($options['scale'])) {
                    $scale = $options['scale'];
                }

                if (!empty($options['unsigned'])) {
                    $unsigned = true;
                }
                break;
            case self::TYPE_DATE:
            case self::TYPE_DATETIME:
            case self::TYPE_TIMESTAMP:
                break;
            case self::TYPE_VARCHAR:
            case self::TYPE_TEXT:
            case self::TYPE_BLOB:
            case self::TYPE_VARBINARY:
            case self::TYPE_CHAR:
            case self::TYPE_LONGVARCHAR:
            case self::TYPE_CLOB:
                $length = $size;
                $charset = isset($options['charset']) ? $options['charset'] : null;
                $collate = isset($options['collate']) ? $options['collate'] : null;
                break;
            default:
                trigger_error('Invalid column data type "' . $type . '"');
                exit(1);
        }

        if (array_key_exists('default', $options)) {
            $default = $options['default'];
        }
        if (array_key_exists('nullable', $options)) {
            $nullable = (bool)$options['nullable'];
        }
        if (!empty($options['primary'])) {
            $primary = true;
            if (isset($options['primary_position'])) {
                $primaryPosition = (int)$options['primary_position'];
            } else {
                $primaryPosition = 0;
                foreach ($this->_columns as $v) {
                    if ($v['PRIMARY']) {
                        $primaryPosition ++;
                    }
                }
            }
        }
        if (!empty($options['identity']) || !empty($options['auto_increment'])) {
            $identity = true;
        }

        if ($comment === null) {
            $comment = ucfirst($name);
        }

        $upperName = strtoupper($name);
        $newname = ($newname === null) ? $name : $newname;
        $column = array(
            'COLUMN_NAME'       => $name,
            'COLUMN_TYPE'       => $type,
            'COLUMN_POSITION'   => $position,
            'DATA_TYPE'         => $type,
            'DEFAULT'           => $default,
            'NULLABLE'          => $nullable,
            'LENGTH'            => $length,
            'SCALE'             => $scale,
            'PRECISION'         => $precision,
            'UNSIGNED'          => $unsigned,
            'PRIMARY'           => $primary,
            'PRIMARY_POSITION'  => $primaryPosition,
            'IDENTITY'          => $identity,
            'COMMENT'           => $comment,
            'CHARACTER_SET'     => $charset,
            'COLLATE'           => $collate
        );

        if($column['COLLATE'] && !!$column['CHARACTER_SET']) {
            $temp = explode("_",$column['COLLATE']);
            $column['CHARACTER_SET']  = $temp[0];
        }

        $sql  = "ALTER TABLE `".$this->getTableName()."` ";

        // Here we modify the column if it already exists,
        // because on repeat runs we don't want it to throw
        // an error!
        if (!$this->tableColumnExists($column['COLUMN_NAME'])) {
            $sql .= "ADD";
        } else {
            $sql .= "CHANGE";
        }

        $sql .= " `".$column['COLUMN_NAME']."` ";
        if ($this->tableColumnExists($column['COLUMN_NAME'])) {
            $sql .= "`" . $newname . "` ";
        }
        $sql .= $column['COLUMN_TYPE'];
        if (
            $column['COLUMN_TYPE'] == self::TYPE_DECIMAL ||
            $column['COLUMN_TYPE'] == self::TYPE_NUMERIC
        ) {
            $sql .= " (".$column['SCALE'].", " . $column['PRECISION'] . ")";
        } else if ($column['LENGTH']) {
            $sql .= " (" . $column['LENGTH'] . ")";
        }
        if ($column['CHARACTER_SET']) {
            $sql .= ' CHARACTER SET ' . $column['CHARACTER_SET'];
        }
        if ($column['COLLATE']) {
            $sql .= ' COLLATE ' . $column['COLLATE'];
        }
        if ($column['UNSIGNED']) {
            $sql .= " UNSIGNED";
        }
        if ($column['NULLABLE']) {
            $sql .= " NULL";
        } else {
            $sql .= " NOT NULL";
        }
        if ($column['DEFAULT']) {
            $sql .= " DEFAULT ";
            if (
                $column['COLUMN_TYPE'] == self::TYPE_TEXT ||
                $column['COLUMN_TYPE'] == self::TYPE_BLOB
            ) {
                $sql .= "\"" . $this->mysqli->real_escape_string($column['DEFAULT']) . "\"";
            } else {
                $sql .= $column['DEFAULT'];
            }
        } else if ($column['NULLABLE']) {
            $sql .= " DEFAULT NULL";
        }
        $sql .= " COMMENT '" . $column['COMMENT'] . "';";
        $this->query($sql);

        return $this;
    }

    public function tableColumnExists($columnName)
    {
        foreach ($this->fetchAll('DESCRIBE `'.$this->getTableName().'`') as $row) {
            if ($row['Field'] == $columnName) {
                return true;
            }
        }
        return false;
    }

    /**
     * Fetches all SQL result rows as a sequential array.
     * Uses the current fetchMode for the adapter.
     *
     * @param string|Zend_Db_Select $sql  An SQL SELECT statement.
     * @param mixed                 $bind Data to bind into SELECT placeholders.
     * @param mixed                 $fetchMode Override current fetch mode.
     * @return array
     */
    public function fetchAll($sql, $fetchMode = null)
    {
        if ($fetchMode === null) {
            $fetchMode = MYSQLI_ASSOC;
        }
        $r = $this->query($sql);
        $result = $r->fetch_all(MYSQLI_ASSOC);
        return $result;
    }

    public function preImport()
    {
        return $this;
    }

    public function postImport()
    {
        return $this;
    }

    public function preRow()
    {
        return $this;
    }

    public function postRow()
    {
        return $this;
    }

}
