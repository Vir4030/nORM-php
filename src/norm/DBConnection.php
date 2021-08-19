<?php 
/**
 * Contains all the information needed to connect to a database.
 * 
 * @author erick.robertson
 */
abstract class DBConnection {

	/**
	 * The type of the database
	 * @var string
	 */
	private $_type;
	
	/**
	 * The hostname for the database connection
	 * @var string
	 */
	private $_host;
	
	/**
	 * The username for authentication into the database
	 * @var string
	 */
	private $_username;
	
	/**
	 * The password for authentication into the database
	 * @var string
	 */
	private $_password;
	
	/**
	 * The TCP port number for the database connection
	 * @var int
	 */
	private $_port;
	
	/**
	 * The catalog name to use after connecting to the database
	 * @var string
	 */
	private $_catalog;
	
	/**
	 * True if this connection should use pooling
	 * @var boolean
	 */
	private $_pooling;
	
	/**
	 * The MySQL type string to be used in configuration files.
	 * 
	 * @var string
	 */
	const TYPE_MYSQL = 'mysql';
	
	/**
	 * The Microsoft SQL Server type string to be used in configuration files.
	 * 
	 * @var string
	 */
	const TYPE_MSSQL = 'mssql';
	
	/**
	 * cached database connections
	 * 
	 * @var array[DBConnection]
	 */
	private static $_cachedConnections = array();
	
	/**
	 * query log details
	 * 
	 * @var array[mixed]
	 */
	private $_queryLogArray = array();
	
	/**
	 * query log total
	 * @var number
	 */
	private $_queryLogTotal = 0.0;
	
	/**
	 * the current query
	 * 
	 * @var DBLog
	 */
	private $_currentQueryLog = null;
	
	/**
	 * enables the query log
	 * 
	 * @var boolean
	 */
	private static $_queryLogEnabled = false;
	
	/**
	 * Enables query logging for all database connections.
	 */
	public static function enableQueryLog() {
		DBConnection::$_queryLogEnabled = true;
	}
	
	/**
	 * Registers the given configuration with the given identifier (name).
	 * 
	 * @param string $name
	 *   the identifier for this configuration
	 * @param DBConnection $connection
	 *   the configuration
	 */
	public static function register($name, $connection) {
		DBConnection::$_cachedConnections[$name] = $connection;
	}
	
	/**
	 * Gets the connection for the given identifier.
	 * 
	 * @param string $name
	 *   the identifier for this connection
	 * @return DBConnection
	 *   the connection
	 */
	public static function get($name) {
		if (!$name)
			throw new Exception('database connection name not supplied');
		return isset(DBConnection::$_cachedConnections[$name]) ? DBConnection::$_cachedConnections[$name] : null;
	}
	
	/**
	 * Creates a new configuration with the given options.
	 * 
	 * @param string $type     the type of database found in the DBType class
	 * @param string $host     the database hostname to connect to
	 * @param string $username the username to connect with
	 * @param string $password the password to connect with
	 * @param int    $port     the port number to connect to
	 * @param string $catalog  the catalog name to use
	 */
	public static function create($type, $host, $username, $password, $port, $catalog = '', $pooling = false) {
		switch ($type) {
			case DBConnection::TYPE_MYSQL:
				return new MySQLConnection($host, $username, $password, $port, $catalog, $pooling);
			case DBConnection::TYPE_MSSQL:
				return new MSSQLConnection($host, $username, $password, $port, $catalog, $pooling);
		}
		return null;
	}
	
	/**
	 * Class constructor creates a new configuration with the given options.
	 * 
	 * @param string $type     the type of database found in the DBType class
	 * @param string $host     the database hostname to connect to
	 * @param string $username the username to connect with
	 * @param string $password the password to connect with
	 * @param int    $port     the port number to connect to
	 * @param string $catalog  the catalog name to use
	 */
	private function __construct($host, $username, $password, $port, $catalog, $pooling) {
		$this->_host = $host;
		$this->_username = $username;
		$this->_password = $password;
		$this->_port = $port;
		$this->_catalog = $catalog;
		$this->_pooling = $pooling;
	}

	/*******************************************************************************
	 * ABSTRACT FUNCTIONS
	 * 
	 * These abstract functions will be called by specific database implementations.
	 * MS SQL and MySQL will be included initially.  Peachtree, DynamicsAX, JIRA,
	 * and other databases may be able to use one of these existing connection types.
	 * If not, new types can easily be added by implementing these methods.
	 * 
	 *******************************************************************************/
	
	/**
	 * Connects this to the database.
	 */
	public abstract function connect();
	
	/**
	 * Disconnects from the database.
	 */
	public abstract function disconnect();
	
	/**
	 * Executes the given query, returning the language-standard resultset object.
	 * 
	 * @param string $sql
	 *  the SQL string for the query
	 * @return mixed
	 */
	public abstract function query($sql);
	
	/**
	 * Executes the given query, returning the first field from the first row of the resultset.
	 * 
	 * @param string $sql
	 *  the SQL string for the query
	 * @return mixed
	 */
	public abstract function field($sql);
	
	/**
	 * Updates data in the given table to set the dirty properties for the record specified by the ID array.
	 * 
	 * @param string       $class     the class being updated
	 * @param array[mixed] $fields    a key-value array of fields and values
	 * @param array[mixed] $idArray   always an array, even if there is only one ID value - this becomes a where clause
	 * @return int the number of rows affected
	 */
	public abstract function update($class, $fields, $idArray);
	
	/**
	 * Inserts data into the table as a new record.
	 * 
	 * @param string       $class     the class being updated
	 * @param array[mixed] $fields    a key-value array of fields and values
	 * @return int|boolean the auto-increment ID, if existing, otherwise a boolean indicating success
	 */
	public abstract function insert($class, $fields);
	
	/**
	 * Deletes data from the table.
	 * 
	 * @param string       $class    the class being deleted
	 * @param array[mixed] $idArray  always an array ,even if there is only one ID value - this becomes a where clause
	 */
	public abstract function delete($class, $idArray);
	
	public abstract function beginTransaction();
	
	public abstract function commit();
	
	public abstract function rollback();
	
	/**
	 * Fetches an associative key-value array for the next record in the resultset.
	 * If there isn't a next record, null is returned.
	 * 
	 * @param mixed $rs
	 *  the language-standard resultset object returned from a call to query($sql)
	 * @return array[mixed]|null
	 *  the return value
	 */
	public abstract function fetch_assoc($rs);
	
	/**
	 * Frees the resources used in the given resultset.
	 * 
	 * @param mixed $rs
	 *  the language-standard resultset
	 */
	public abstract function free_result($rs);
	
	/**
	 * Pings the database.
	 * 
	 * @return bool true if the ping was successful
	 */
	public abstract function ping();
	
	/**
	 * Protects and quotes, if necessary, the given unsafe value.  It is possible to
	 * write a connection that would query column data from the database itself on
	 * first use of a specific table.  These connections could also clean up data
	 * for insert at this point, throwing exceptions when there are problems.
	 * 
	 * @param mixed $unsafeValue
	 *  an unsafe and value of unknown type - could be anything
	 * @return string
	 *  the safe, quoted value, ready for use in an SQL statement
	 */
	public abstract function quote($unsafeValue, $requiresQuoting = true);
	
	/**
	 * Protects the given unsafe value as a number which does not require quoting.
	 * 
	 * @param number $unsafeValue
	 * @return string the safe value ready for use in an SQL statement
	 */
	public function number($unsafeValue) {
	  return $this->quote($unsafeValue, false);
	}
	
	/**
	 * Gets the SQL to use for pagination after the SELECT keyword.  This method does
	 * not return any trailing or leading spaces.
	 * 
	 * @param int $maxRecords
	 *  the maximum number of records, 0 = unlimited
	 * @param int $offset
	 *  the number of records to ignore at the beginning of the resultset
	 */
	public abstract function getPaginationAfterSelect($maxRecords, $offset);
	
	/**
	 * Gets the SQL to use for pagination at the end of the SQL statement.  This method
	 * does not return any trailing or leading spaces.
	 * 
	 * @param int $maxRecords
	 *  the maximum number of records, 0 = unlimited
	 * @param int $offset
	 *  the number of records to ignore at the beginning of the resultset
	 */
	public abstract function getPaginationAfterStatement($maxRecords, $offset);
	
	/*******************************************************************************
	 * GETTERS
	 *
	 * So that's the end of the abstract functions.
	 *
	 *******************************************************************************/
	
	/**
	 * Gets the type of database to be used.  This is one of the constants found in the DBType class.
	 * 
	 * @return string the type, a constant from the DBType class
	 */
	public function getType() {
		return $this->_type;
	}
	
	/**
	 * Gets the hostname for the database connection.
	 * 
	 * @return string the hostname
	 */
	public function getHost() {
		return $this->_host;
	}
	
	public function setHost($host) {
	  $this->_host = $host;
	}
	
	/**
	 * Gets the username for authentication into the database.
	 * 
	 * @return string the username
	 */
	public function getUsername() {
		return $this->_username;
	}
	
	public function setUsername($username) {
	  $this->_username = $username;
	}
	
	/**
	 * Gets the password for authentication into the database.
	 * 
	 * @return string the password
	 */
	public function getPassword() {
		return $this->_password;
	}
	
	public function setPassword($password) {
	  $this->_password = $password;
	}
	
	/**
	 * Gets the port number for the database connection.
	 * 
	 * @return int the port number
	 */
	public function getPort() {
		return $this->_port;
	}
	
	public function setPort($port) {
	  $this->_port = $port;
	}
	
	/**
	 * Gets the catalog name to use after connecting to the database.
	 * 
	 * @return string the catalog
	 */
	public function getCatalog() {
		return $this->_catalog;
	}
	
	public function setCatalog($catalog) {
	  $this->_catalog = $catalog;
	}
	
	/**
	 * Checks if this connection uses pooling.
	 * 
	 * @return boolean true if it uses pooling
	 */
	public function getPooling() {
		return $this->_pooling;
	}
	
	public function setPooling($pooling) {
	  $this->_pooling = $pooling;
	}
	
	/**
	 * Gets the query log.
	 * 
	 * @return array[DBLog] the query log
	 */
	public function getQueryLog() {
		return $this->_queryLogArray;
	}
	
	protected function logQueryBegin($sql) {
		if (!DBConnection::$_queryLogEnabled) return;
		$this->_currentQueryLog = new DBLog($sql);
		$this->_queryLogArray[] = $this->_currentQueryLog;
	}
	
	protected function logQuerySplit() {
		if (!DBConnection::$_queryLogEnabled) return;
		$this->_currentQueryLog->split();
	}
	
	protected function logQueryRows($rows) {
		if (!DBConnection::$_queryLogEnabled) return;
		$this->_currentQueryLog->setRowsAffected($rows);
	}
	
	protected function logQueryEnd() {
		if (!DBConnection::$_queryLogEnabled) return;
		$this->_currentQueryLog->end();
		$this->_queryLogTotal += $this->_currentQueryLog->getCompleteTime();
	}
	
	protected function logQueryError($message) {
		if (!DBConnection::$_queryLogEnabled) return;
		$this->_currentQueryLog->error($message);
	}
	
	public function dumpTextQueryLog() {

		$br = "\n";
		echo('total db time ' . number_format($this->_queryLogTotal * 1000, 2) . 'ms');
		echo($br . '== Query Log ==' . $br);
		foreach ($this->getQueryLog() AS $queryLog) {
			if ($queryLog->isCompleted()) {
				echo($queryLog->getQueryString() . $br . ' - ' . $queryLog->getRowsAffected() . ' rows affected in ' . number_format($queryLog->getCompleteTime() * 1000.0, 2) . 'ms');
			} else
				echo($queryLog->getQueryString() . $br . ' - error: ' . $queryLog->getErrorMessage());
			echo($br);
		}
		
	}
	
	public function dumpHtmlQueryLog() {
		echo('<div class="log">');
		echo('<h4>Query Log</h4>');
		echo('<div class="log-summary">total db time ' . number_format($this->_queryLogTotal * 1000, 2) . 'ms</div>');
		foreach ($this->getQueryLog() AS $queryLog) {
			echo('<div class="log-entry">');
			$query = rtrim($queryLog->getQueryString(), ';');
			$query = str_replace(',', ',<wbr>', $query);
			$query = str_replace(')', ')<wbr>', $query);
			echo('<div class="log-key">'.$query.'</div>');
			if ($queryLog->isCompleted()) {
				echo('<div class="log-value">' . $queryLog->getRowsAffected() . ' rows affected in ' . number_format($queryLog->getCompleteTime() * 1000.0, 2) . 'ms</div>');
			} else
				echo('<div class="log-error">ERROR: ' . $queryLog->getErrorMessage().'</div>');
			echo('</div>');
		}
		echo('</div>');
	}
	
	public function dumpQueryLog($html = false, $force = false) {
		if (!DBConnection::$_queryLogEnabled) return;
		if (!$force && error_get_last()) {
			if ($html)
				echo('<h4>Query Log Suppressed due to Error (norm/DBConnection::dumpQueryLog, line '.__LINE__.')');
			else
				echo('Query Log Suppressed due to Error (norm/DBConnection::dumpQueryLog, line '.__LINE__.")\n");
		} else {
			if ($html)
				$this->dumpHtmlQueryLog();
			else
				$this->dumpTextQueryLog();
		}
	}
}
