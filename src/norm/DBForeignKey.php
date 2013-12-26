<?php

class DBForeignKey {
	
	private $_name;
	
	private $_primaryEntityClass;
	
	private $_primaryColumns;
	
	private $_foreignEntityClass;
	
	private $_foreignColumns;
	
	public function __construct($name, $primaryEntityClass, $primaryColumns, $foreignEntityClass, $foreignColumns) {
		$this->_name = $name;
		$this->_primaryEntityClass = $primaryEntityClass;
		$this->_primaryColumns = $primaryColumns;
		$this->_foreignEntityClass = $foreignEntityClass;
		$this->_foreignColumns = $foreignColumns;
	}
	
	public function getName() {
		return $this->_name;
	}
	
	public function getPrimaryEntityClass() {
		return $this->_primaryEntityClass;
	}
	
	public function getPrimaryColumns() {
		return $this->_primaryColumns;
	}
	
	public function getForeignEntityClass() {
		return $this->_foreignEntityClass;
	}
	
	public function getForeignColumns() {
		return $this->_foreignColumns;
	}
}
