<?php
class DBField {
	
	/**
	 * Field Type for String fields
	 * @var DBField
	 */
	public static $TYPE_STRING;
	
	/**
	 * Field Type for Numeric fields
	 * @var DBField
	 */
	public static $TYPE_NUMERIC;
	
	/**
	 * Field Type for Datetime fields
	 * @var DBField
	 */
	public static $TYPE_DATETIME;
	
	/**
	 * Field Type for Date fields
	 * @var DBField
	 */
	public static $TYPE_DATE;
	
	/**
	 * Field Type for Bit fields
	 * @var DBField
	 */
	public static $TYPE_BIT;
	
	private $requiresQuoting;
	
	private $dateFormat;
	
	private $binaryOnly;
	
	private $maxLength = 0;
	
	public function __construct($requiresQuoting = true, $dateFormat = null, $binaryOnly = false) {
		$this->requiresQuoting = $requiresQuoting;
		$this->dateFormat = $dateFormat;
		$this->binaryOnly = $binaryOnly;
	}
	
	public function requiresQuoting() {
		return $this->requiresQuoting;
	}
	
	public function getDateFormat() {
		return $this->dateFormat;
	}
	
	public function isBinaryOnly() {
		return $this->binaryOnly;
	}
	
	public function withMaxLength($maxLength) {
		$field = clone $this;
		$field->maxLength = $maxLength;
		return $field;
	}
	
	public function convertToDatabase($inValue) {
		if (is_object($inValue))
			throw new Exception("cannot convert an object to the database");
		$outValue = $inValue;
		if ($this->binaryOnly)
			$outValue = $outValue ? 1 : 0;
		else if ($this->getDateFormat() && $outValue) {
			if (!is_numeric($outValue))
				throw new Exception('invalid PHP date value (expecting numeric time) - ' . $outValue);
			$outValue = gmdate($this->getDateFormat(), $outValue);
		}
		return ''.$outValue;
	}
	
	public function convertFromDatabase($inValue) {
		$outValue = $inValue;
		if ($this->binaryOnly)
			$outValue = $outValue ? true : false;
		else if ($this->getDateFormat() && $outValue)
			$outValue = strtotime($outValue . ' GMT');
		return $outValue;
	}
}

DBField::$TYPE_STRING = new DBField();
DBField::$TYPE_NUMERIC = new DBField(false);
DBField::$TYPE_DATETIME = new DBField(true, 'Y-m-d G:i:s');
DBField::$TYPE_DATE = new DBField(true, 'Y-m-d');
DBField::$TYPE_BIT = new DBField(false, null, true);
