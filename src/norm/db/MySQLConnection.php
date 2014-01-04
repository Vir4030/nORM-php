<?php
class MySQLConnection extends DBConnection {
	
	private $_db;
	
	public function connect() {
		$this->_db = mysqli_connect(
			$this->getHost(),
			$this->getUsername(),
			$this->getPassword(),
			$this->getCatalog(),
			$this->getPort()
		);
	}
	
	public function disconnect() {
		if ($this->_db)
			$this->_db->disconnect();
		$this->_db = null;
	}
	
	public function query($sql) {
		$this->logQueryBegin($sql);
		if (($rs = mysqli_query($this->_db, $sql)) === false) {
			$message = mysqli_error($this->_db) . ': ' . $sql;
			$this->logQueryError($message);
			throw new Exception($message);
		}
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
		if (!mysqli_query($this->_db, $sql)) {
			$message = mysqli_error($this->_db);
			$this->logQueryError($message);
			throw new Exception($message . ': ' . $sql);
		}
		$this->logQueryEnd();
		return mysqli_affected_rows($this->_db);
	}
	
	/**
	 * Inserts data into the table as a new record.
	 *
	 * @param string       $class     the class being updated
	 * @param array[mixed] $fields    a key-value array of fields and values
	 * @return int|true the auto-increment ID, if existing, otherwise a true, indicating success
	 * @throws Exception when the SQL causes an exception
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
		if (!mysqli_query($this->_db, $sql)) {
			$message = mysqli_error($this->_db);
			$this->logQueryError($message);
			throw new Exception($message);
		}
		$this->logQueryEnd();
		
		$id = mysqli_insert_id($this->_db);
		if (!$id)
			$id = true;
		return $id;
	}
	
	public function fetch_assoc($rs) {
		return mysqli_fetch_assoc($rs);
	}
	
	public function free_result($rs) {
		mysqli_free_result($rs);
		$this->logQueryEnd();
	}
	
	public function quote($unsafeValue, $requiresQuoting = true) {
		if (is_array($unsafeValue))
			throw new Exception('cannot quote an array');
		$safeValue = mysqli_real_escape_string($this->_db, ''.$unsafeValue);
		if ($requiresQuoting)
			$safeValue = "'" . $safeValue . "'";
		return $safeValue;
	}
}
