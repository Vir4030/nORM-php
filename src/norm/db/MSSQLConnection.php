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
	
	public function query($sql) {
		return mssql_query($sql, $this->_db);
	}
	
	/**
	 * Updates data in the given table to set the dirty properties for the record specified by the ID array.
	 * 
	 * @param string       $tableName the name of the table being updated
	 * @param array[mixed] $fields    a key-value array of fields and values
	 * @param array[mixed] $idArray   always an array, even if there is only one ID value - this becomes a where clause
	 * @return int the number of rows affected
	 */
	public function update($tableName, $fields, $idArray) {
		$sql = 'UPDATE ' . $tableName . ' SET ';
		$count = 0;
		foreach ($fields AS $key => $value) {
			if ($count++)
				$sql .= ',';
			$sql .= $key . ' = ' . $this->quote($value);
		}
		$sql .= ' WHERE ';
		$count = 0;
		foreach ($idArray AS $key => $value) {
			if ($count++)
				$sql .= ' AND ';
			$sql .= $key . ' = ' . $this->quote($value);
		}
		$sql .= ';';
		mssql_query($sql, $this->_db);
		return mssql_rows_affected($this->_db);
	}
	
	/**
	 * Inserts data into the table as a new record.
	 * 
	 * @param string       $tableName the name of the table being inserted into
	 * @param array[mixed] $fields    a key-value array of fields and values
	 * @return int|boolean the auto-increment ID, if existing, otherwise a boolean indicating success
	 */
	public function insert($tableName, $fields) {
		$sqlFields = 'INSERT INTO ' . $tableName . '(';
		$sqlValues = ') VALUES (';
		$count = 0;
		foreach ($fields AS $key => $value) {
			if ($count++) {
				$sqlFields .= ',';
				$sqlValues .= ',';
			}
			$sqlFields .= $key;
			$sqlValues .= $this->quote($value);
		}
		$sql = $sqlFields . $sqlValues . ');';
		if (!mssql_query($sql, $this->_db))
			throw new Exception(mssql_get_last_message());
		
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

	public function quote($unsafeValue) {
		$safeValue = '';
		if (is_numeric($safeValue)) {
			$safeValue = sprintf('%d', $safeValue);
		}
		else {
			$safeValue = "'" . str_replace("'", "''", $safeValue);
		}
		return $safeValue;
	}
}
