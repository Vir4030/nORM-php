<?php
class MSSQLConnection extends DBConnection {

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
