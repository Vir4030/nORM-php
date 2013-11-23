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
		if (($rs = mysqli_query($this->_db, $sql)) === false) {
			throw new Exception('There has been a MySQL error: ' . mysqli_error($this->_db) . ' with SQL: ' . $sql);
		}
		return $rs;
	}
	
	public function fetch_assoc($rs) {
		return mysqli_fetch_assoc($rs);
	}
	
	public function free_result($rs) {
		mysqli_free_result($rs);
	}
	
	public function quote($unsafeValue) {
		$safeValue = mysqli_real_escape_string($this->_db, $unsafeValue);
		if (is_numeric($safeValue)) {
			$safeValue = sprintf('%d', $safeValue);
		}
		else {
			$safeValue = "'" . $safeValue . "'";
		}
		return $safeValue;
	}
}
