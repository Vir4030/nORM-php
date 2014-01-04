<?php
class MSSQLConnection extends DBConnection {

	/**
	 * The actual database object being used to connect to MSSQL
	 * @var resource
	 */
	private $_db;
	
	public function connect() {
		if ($this->_db)
			$this->disconnect();
		$this->_db = mssql_connect(
			$this->getHost() . ':' . $this->getPort(),
			$this->getUsername(),
			$this->getPassword()
		);
		if ($this->getCatalog()) {
			mssql_select_db($this->getCatalog(), $this->_db);
		}
	}
	
	public function disconnect() {
		if ($this->_db)
			$this->_db->disconnect();
		$this->_db = null;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see DBConnection::query()
	 */
	public function query($sql) {
		$this->logQueryBegin($sql);
		$rs = mssql_query($sql, $this->_db);
		if ($rs === false) {
			$message = mssql_get_last_message();
			$this->logQueryError($message);
			throw new Exception($message);
		}
		if ($rs === true)
			throw new Exception("No resultset returned: "+$sql);
		$this->logQuerySplit();
		return $rs;
	}
	
	/**
	 * Updates data in the given table to set the dirty properties for the record specified by the ID array.
	 * 
	 * @param string       $class     the class being updated
	 * @param array[mixed] $fields    a key-value array of fields and values
	 * @param array[mixed] $idArray   always an array, even if there is only one ID value - this becomes a where clause
	 * @return int the number of rows affected
	 */
	public function update($class, $fields, $idArray) {
		$tableName = $class::getTableName();
		$sql = 'UPDATE ' . $tableName . ' SET ';
		$count = 0;
		foreach ($fields AS $key => $value) {
			if ($count++)
				$sql .= ',';
			$sql .= $key . ' = ' . $this->quote($value, $class::requiresQuoting($key));
		}
		$sql .= ' WHERE ';
		$count = 0;
		foreach ($idArray AS $key => $value) {
			if ($count++)
				$sql .= ' AND ';
			$sql .= $key . ' = ' . $this->quote($value, $class::requiresQuoting($key));
		}
		$sql .= ';';
		
		$this->logQueryBegin($sql);
		if (mssql_query($sql, $this->_db) === false) {
			$message = mssql_get_last_message();
			$this->logQueryError($message);
			throw new Exception($message);
		}
		
		$this->logQueryEnd();
		return mssql_rows_affected($this->_db);
	}
	
	/**
	 * Inserts data into the table as a new record.
	 * 
	 * @param string       $class     the class being updated
	 * @param array[mixed] $fields    a key-value array of fields and values
	 * @return int|boolean the auto-increment ID, if existing, otherwise a boolean indicating success
	 */
	public function insert($class, $fields) {
		$tableName = $class::getTableName();
		$sqlFields = 'INSERT INTO ' . $tableName . '(';
		$sqlValues = ') VALUES (';
		$count = 0;
		foreach ($fields AS $key => $value) {
			if ($count++) {
				$sqlFields .= ',';
				$sqlValues .= ',';
			}
			$sqlFields .= $key;
			$sqlValues .= $this->quote($value, $class::requiresQuoting($key));
		}
		$sql = $sqlFields . $sqlValues . ');';
		
		$this->logQueryBegin($sql);
		if (!mssql_query($sql, $this->_db)) {
			$message = mssql_get_last_message();
			$this->logQueryError($message);
			throw new Exception($message);
		}
		$this->logQueryEnd();
		
		$rs = mssql_query("SELECT @@IDENTITY as last_insert_id");
		$id = mssql_fetch_field($rs, 0);
		mssql_free_result($rs);
		
		// returning 
		$returnVal = true;
		if ($id)
			$returnVal = $id;
		return $returnVal;
	}
	
	public function fetch_assoc($rs) {
		return mssql_fetch_assoc($rs);
	}
	
	public function free_result($rs) {
		mssql_free_result($rs);
	}

	public function quote($unsafeValue, $requiresQuoting = true) {
		$safeValue = str_replace("'", "''", $unsafeValue);
		if ($requiresQuoting)
			$safeValue = "'" . $safeValue . "'";
		return $safeValue;
	}
}
