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
	
	public static function SQL_FOR($conn, $class, $key, $value) {
		$sql = '';
		
		if ($value instanceof DBQuery) {
			$sql .= $key . ' In (' . $value->generateSQL($conn) . ')';
		}
		else if (is_array($value)) {
			if (isset($value['or'])) {
				$count = 0;
				foreach ($value['or'] AS $key => $value) {
					if ($count++) {
						$sql .= ' OR ';
					}
					self::SQL_FOR($conn, $class, $key, $value);
				}
			} else if (isset($value['compare'])) {
				$compare = $value['compare'];
				if (isset($value['not']))
					$sql .= 'Not ';
				$sql .= $key;
				if ($compare == 'between') {
					$low = $class::convertToDatabase($key, $value['low']);
					$high = $class::convertToDatabase($key, $value['high']);
					$sql .= ' BETWEEN ' . $conn->quote($low, $class::requiresQuoting($key));
					$sql .= ' AND ' . $conn->quote($high, $class::requiresQuoting($key));
				} else {
					$value = $class::convertToDatabase($key, $value['value']);
					$sql .= ' ' . $compare . ' ' . $conn->quote($value, $class::requiresQuoting($key));
				}
			} else {
				// array value means an 'in' clause
				 
				$sql .= $key . ' In (';
				$count = 0;
				foreach ($value AS $in_value) {
					if ($count++) {
						$sql .= ',';
					}
					$sql .= $conn->quote($in_value, $class::requiresQuoting($key));
				}
				$sql .= ')';
			}
		}
		else if (is_null($value)) {
			$sql .= $key . ' Is Null';
		}
		else {
			$sql .= $key . ' = ' . $conn->quote($class::convertToDatabase($key, $value), $class::requiresQuoting($key));
		}
		
		return $sql;
	}
	
	/**
	 * 
	 * @param DBConnection $conn
	 * @return string
	 */
	private function _generateWhere($conn) {
		$class = $this->_entityClass;
		$sql  = '';
		if (is_array($this->_selector)) {
			$count = 0;
			foreach ($this->_selector AS $key => $value) {
				if ($count++ > 0) {
					$sql .= ' AND ';
				}
				$sql .= self::SQL_FOR($conn, $class, $key, $value);
			}
		} else if ($this->_selector !== null) {
			if (is_array($class::getIdField())) {
				throw new Exception('key with multiple fields requires array-based selector');
			}
			if (is_array($this->_selector)) {
				$count = 0;
				foreach ($this->_selector AS $field => $value) {
					if ($count++) {
						$sql .= ' AND ';
					}
					$sql .= $field . ' = ' . $conn->quote($value, $class::requiresQuoting($field));
				}
			} else {
				$sql .= $class::getIdField() . ' = ' . $conn->quote($this->_selector, $class::requiresQuoting($class::getIdField()));
			}
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
