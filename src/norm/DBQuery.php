<?php
class DBQuery {
	
	private $_entityClass;
	
	private $_fields;
	
	private $_selector;
	
	private $_order;
	
	public function __construct($entityClass, $fields = null, $selector = null, $order = null) {
		$this->_entityClass = $entityClass;
		$this->_fields = $fields;
		$this->_selector = $selector;
		$this->_order = $order;
	}
	
	private function _generateSelect($conn) {
		$sql = '';
		if (is_array($this->_fields)) {
			$count = 0;
			foreach ($this->_fields AS $key => $value) {
				if ($count++ > 0)
					$sql .= ', ';
				if (is_numeric($key))
					$sql .= $value;
				else
					$sql .= $value . ' AS ' . $key;
			}
		}
		else if (is_null($this->_fields)) {
			$sql = '*';
		}
		else {
			$sql = $this->_fields;
		}
		return $sql;
	}
	
	/**
	 * 
	 * @param DBConnection $conn
	 * @return string
	 */
	private function _generateWhere($conn) {
		$sql  = '';
		if (is_array($this->_selector)) {
			$count = 0;
			foreach ($this->_selector AS $key => $value) {
				if ($count++ > 0) {
					$sql .= ' AND ';
				}
				$sql .= $key;
				if ($value instanceof DBQuery) {
					$sql .= ' In (' . $value->generateSQL($conn) . ')';
				}
				else {
					$sql .= ' = ' . $conn->quote($value);
				}
			}
		} else {
			$class = $this->_entityClass;
			$key = $class::getIdField();
		}
		return $sql;
	}
	
	private function _generateOrder($conn) {
		$sql = '';
		if (is_array($this->_order)) {
			$sql = implode(', ', $this->_order);
		}
		else {
			$sql = $this->_order;
		}
		return $sql;
	}
	
	public function generateSQL($conn) {
		$class = $this->_entityClass;
		$sql = 'SELECT ' . $this->_generateSelect($conn) . ' FROM ' . $class::getTableName();
		$where = $this->_generateWhere($conn);
		if ($where)
			$sql .= ' WHERE ' . $where;
		$order = $this->_generateOrder($conn);
		if ($order)
			$sql .= ' ORDER BY ' . $order;
		return $sql;
	}
}
