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
	 * cached database configurations
	 * 
	 * @var array[DBConfiguration]
	 */
	private static $_cachedConfigurations = array();
	
	/**
	 * query log details
	 * 
	 * @var array[mixed]
	 */
	private $_queryLogArray = array();
	
	/**
	 * the current query
	 * 
	 * @var DBLog
	 */
	private $_currentQueryLog = null;
	
	/**
	 * Registers the given configuration with the given identifier (name).
	 * 
	 * @param string $name
	 *   the identifier for this configuration
	 * @param DBConnection $connection
	 *   the configuration
	 */
	public static function register($name, $connection) {
		DBConnection::$_cachedConfigurations[$name] = $connection;
	}
	
	/**
	 * Gets the configuration for the given identifier.
	 * 
	 * @param string $name
	 *   the identifier for this configuration
	 * @return DBConfiguration
	 *   the configuration
	 */
	public static function get($name) {
		if (!$name)
			throw new Exception('database configuration name not supplied');
		if (!isset(DBConnection::$_cachedConfigurations[$name]))
			throw new Exception('cannot find database configuration for ' . $name);
		return DBConnection::$_cachedConfigurations[$name];
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
	public static function create($type, $host, $username, $password, $port, $catalog = '') {
		switch ($type) {
			case DBConnection::TYPE_MYSQL:
				return new MySQLConnection($host, $username, $password, $port, $catalog);
			case DBConnection::TYPE_MSSQL:
				return new MSSQLConnection($host, $username, $password, $port, $catalog);
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
	private function __construct($host, $username, $password, $port, $catalog = '') {
		$this->_host = $host;
		$this->_username = $username;
		$this->_password = $password;
		$this->_port = $port;
		$this->_catalog = $catalog;
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
	 * @see DBType
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
	
	/**
	 * Gets the username for authentication into the database.
	 * 
	 * @return string the username
	 */
	public function getUsername() {
		return $this->_username;
	}
	
	/**
	 * Gets the password for authentication into the database.
	 * 
	 * @return string the password
	 */
	public function getPassword() {
		return $this->_password;
	}
	
	/**
	 * Gets the port number for the database connection.
	 * 
	 * @return int the port number
	 */
	public function getPort() {
		return $this->_port;
	}
	
	/**
	 * Gets the catalog name to use after connecting to the database.
	 * 
	 * @return string the catalog
	 */
	public function getCatalog() {
		return $this->_catalog;
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
		$this->_currentQueryLog = new DBLog($sql);
		$this->_queryLogArray[] = $this->_currentQueryLog;
	}
	
	protected function logQuerySplit() {
		$this->_currentQueryLog->split();
	}
	
	protected function logQueryRows($rows) {
		$this->_currentQueryLog->setRowsAffected($rows);
	}
	
	protected function logQueryEnd() {
		$this->_currentQueryLog->end();
	}
	
	protected function logQueryError($message) {
		$this->_currentQueryLog->error($message);
	}
	
	public function dumpQueryLog($html = false) {
		$br = $html ? '<br>' : "\n";
		echo($br . '== Query Log ==' . $br);
		foreach ($this->getQueryLog() AS $queryLog) {
			if ($queryLog->isCompleted()) {
				echo($queryLog->getQueryString() . $br . ' - ' . $queryLog->getRowsAffected() . ' rows affected in ' . number_format($queryLog->getCompleteTime() * 1000.0, 2) . 'ms');
			} else
				echo($queryLog->getQueryString() . $br . ' - error: ' . $queryLog->getErrorMessage());
			echo($br);
		}
	}
}
