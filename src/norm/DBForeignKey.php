<?php

class DBForeignKey {
	
	private $_name;
	
	private $_primaryTable;
	
	private $_primaryColumns;
	
	private $_foreignTable;
	
	private $_foreignColumns;
	
	public function __construct($name, $primaryTable, $primaryColumns, $foreignTable, $foreignColumns) {
		$this->_name = $name;
		$this->_primaryTable = $primaryTable;
		$this->_primaryColumns = $primaryColumns;
		$this->_foreignTable = $foreignTable;
		$this->_foreignColumns = $foreignColumns;
	}
	
	public function getName() {
		return $this->_name;
	}
	
	public function getPrimaryTable() {
		return $this->_primaryTable;
	}
	
	public function getPrimaryColumns() {
		return $this->_primaryColumns;
	}
	
	public function getForeignTable() {
		return $this->_foreignTable;
	}
	
	public function getForeignColumns() {
		return $this->_foreignColumns;
	}
}
