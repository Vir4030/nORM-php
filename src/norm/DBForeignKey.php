<?php

class DBForeignKey {
	
	private $_name;
	
	private $_primaryEntityClass;
	
	private $_primaryColumns;
	
	private $_foreignEntityClass;
	
	private $_foreignColumns;
	
	private static $_keyCache = array();
	
	public static function get($keyName) {
		if (is_array($keyName) || is_object($keyName))
			throw new Exception('key name cannot be an array or object');
		if (!isset(static::$_keyCache[$keyName]))
			throw new Exception('Foreign Key '.$keyName.' was not declared.');
		return static::$_keyCache[$keyName];
	}
	
	public function __construct($name, $primaryEntityClass, $primaryColumns, $foreignEntityClass, $foreignColumns) {
		$this->_name = $name;
		$this->_primaryEntityClass = $primaryEntityClass;
		$this->_primaryColumns = $primaryColumns;
		$this->_foreignEntityClass = $foreignEntityClass;
		$this->_foreignColumns = $foreignColumns;
		static::$_keyCache[$name] = $this;
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
