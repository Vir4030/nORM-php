<?php
class MySQLConnection extends DBConnection {
	
	private $_db;
	
	private $_connected = false;
	
	public function connect() {
		if (!$this->_db) {
			$this->logQueryBegin('CONNECTING TO ' . $this->getHost() . ', CATALOG ' . $this->getCatalog());
			if ($this->getPooling()) {
				$this->_db = mysqli_connect(
					'p:'.$this->getHost(),
					$this->getUsername(),
					$this->getPassword(),
					$this->getCatalog(),
					$this->getPort()
				);
			} else {
			  $this->_db = mysqli_connect(
					$this->getHost(),
					$this->getUsername(),
					$this->getPassword(),
					$this->getCatalog(),
					$this->getPort()
				);
			}
			if (!$this->_db) {
			  throw new Exception('database could not be connected: '.mysqli_connect_error());
			}
			$this->logQueryEnd();
			$this->_connected = true;
		} else if (!$this->_connected) {
		  if ($this->getPooling()) {
		    $this->_db->connect(
		        'p:'.$this->getHost(),
		        $this->getUsername(),
		        $this->getPassword(),
		        $this->getCatalog(),
		        $this->getPort());
		  } else {
		    $this->_db->connect(
		        $this->getHost(),
		        $this->getUsername(),
		        $this->getPassword(),
		        $this->getCatalog(),
		        $this->getPort());
		  }
		}
		if (!$this->_db->ping())
		  throw new Exception('no ping after connection');
		$this->_connected = true;
	}
	
	public function disconnect() {
		if ($this->_db)
			$this->_db->close();
		$this->_connected = false;
	}
	
	public function query($sql) {
		$this->logQueryBegin($sql);
		/* @var $rs mysqli_result */
// 		echo('SQL: '.$sql."\r\n");
		if (!$this->_db)
			throw new Exception('cannot query when database is not connected');
		if (($rs = mysqli_query($this->_db, $sql)) === false) {
			$message = mysqli_error($this->_db) . ': ' . $sql;
			$this->logQueryError($message);
			throw new Exception($message);
		}
		if (is_object($rs)) {
			$this->logQueryRows($rs->num_rows);
			$this->logQuerySplit();
		} else
		  $this->logQueryEnd();
		return $rs;
	}

	public function field($sql) {
		$field = null;
		$rs = $this->query($sql);
		if (($row = mysqli_fetch_array($rs, MYSQLI_NUM)) !== null)
			$field = $row[0];
		$this->free_result($rs);
		return $field;
	}
	
	private function execute($sql) {
	    $this->logQueryBegin($sql);
	    if (!mysqli_query($this->_db, $sql)) {
	        $message = mysqli_error($this->_db);
	        $this->logQueryError($message);
	        throw new Exception($message . ': ' . $sql);
	    }
	    $rows = mysqli_affected_rows($this->_db);
	    $this->logQueryRows($rows);
	    $this->logQueryEnd();
	    return $rows;
	}
	
	/**
	 *
	 * {@inheritDoc}
	 * @see DBConnection::rollback()
	 */
	public function rollback() {
	    return $this->execute('ROLLBACK;');
	}
	
	/**
	 *
	 * {@inheritDoc}
	 * @see DBConnection::beginTransaction()
	 */
	public function beginTransaction() {
	    return $this->execute('START TRANSACTION;');
	}
	
	/**
	 *
	 * {@inheritDoc}
	 * @see DBConnection::commit()
	 */
	public function commit() {
	    return $this->execute('COMMIT;');
	}
	
	/**
	 * (non-PHPdoc)
	 * @see DBConnection::delete()
	 */
	public function delete($class, $idArray) {
		$tableName = $class::getTableName();
		$sql = 'DELETE FROM ' . $tableName . ' WHERE ';
		$count = 0;
		foreach ($idArray AS $key => $value) {
			if ($count++)
				$sql .= ' AND ';
			$sql .= $key . ' = ' . $this->quote($value, $class::requiresQuoting($key));
		}
		$sql .= ';';
		return $this->execute($sql);
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
				$sql .= ', ';
			if (is_array($value))
			  throw new Exception('trying to update = array for '.$class.'.'.$key);
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
		return $this->execute($sql);
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
			throw new Exception($tableName.': '.$message);
		}
		$rows = mysqli_affected_rows($this->_db);
		$this->logQueryRows($rows);
		$id = mysqli_insert_id($this->_db);
		if (!$id)
			$id = true;
		$this->logQueryEnd();
		
		return $id;
	}
	
	public function fetch_assoc($rs) {
		return mysqli_fetch_assoc($rs);
	}
	
	public function free_result($rs) {
		mysqli_free_result($rs);
		$this->logQueryEnd();
	}
	
	public function ping() {
	  return $this->_db->ping();
	}
	
	public function quote($unsafeValue, $requiresQuoting = true) {
		if (is_array($unsafeValue))
			throw new Exception('cannot quote an array');
		if (($unsafeValue === null) || ($unsafeValue === DBField::NULL))
			$safeValue = 'null';
		else {
			$safeValue = mysqli_real_escape_string($this->_db, ''.$unsafeValue);
			if ($requiresQuoting)
				$safeValue = "'" . $safeValue . "'";
			else if ((trim($safeValue) === '') || (strcasecmp($safeValue, 'null') == 0))
				$safeValue = 'null';
		}
		return $safeValue;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see DBConnection::getPaginationAfterSelect()
	 */
	public function getPaginationAfterSelect($maxRecords, $offset) {
		return '';
	}
	
	/**
	 * (non-PHPdoc)
	 * @see DBConnection::getPaginationAfterStatement()
	 */
	public function getPaginationAfterStatement($maxRecords, $offset) {
		if ($offset && !$maxRecords)
			throw new Exception('specifying an offset requires a max records value');
		$sql = $maxRecords ? 'LIMIT '.$maxRecords : '';
		if ($offset)
			$sql .= ' OFFSET '.$offset;
		return $sql;
	}
}
