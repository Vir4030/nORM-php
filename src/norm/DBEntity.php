<?php
/**
 * Parent class for all database models.  This class represents one record in a database.
 * The class also has a static presence, representing a database table.  This method makes
 * heavy use of the late static binding feature introduced in PHP 5.3.
 * 
 * @author erick.robertson
 */
abstract class DBEntity {
	
	/**
	 * The database ID from which this entity should load.  This is a string constant which
	 * must be registered as a database configuration for this entity to be used.
	 * 
	 * @var string
	 */
	protected static $_database;
	
	/**
	 * The table name for the entity, to be defined by the subclass.
	 * 
	 * @var string
	 */
	protected static $_tableName;
	
	/**
	 * The field or array of fields to use as a unique identifier.
	 * 
	 * @var mixed
	 */
	protected static $_idField = 'id';
	
	/**
	 * This properties array holds the raw record data straight from the database.
	 * 
	 * @var array[mixed]
	 */
	private $_properties = array();
	
	/**
	 * This properties array holds the new values for each of the properties which
	 * have changed.  These are the values that will be sent to the database when saved.
	 * 
	 * @var array[mixed]
	 */
	private $_changedProperties = array();
	
	/**
	 * Class constructor creates a new DBEntity with the given properties.  The properties
	 * array is copied by reference into the object for performance.  When this method is
	 * called, be sure the array provided is not altered after it has been used in this
	 * constructor and stored in the object.
	 * 
	 * @param array[mixed] $properties
	 */
	public function __construct(array $properties) {
		$this->_properties = $properties;
	}
	
	/**
	 * Gets the value for the field with the given name.
	 * 
	 * @param string $field the name of the field
	 * @return mixed the value of the field
	 */
	public function __get($field) {
		return isset($this->_changedProperties[$field]) ? $this->_changedProperties[$field] :
			isset($this->_properties[$field]) ? $this->_properties[$field] : null;
	}
	
	/**
	 * Sets the given value for the given field.
	 * 
	 * @param string $field the name of the field
	 * @param mixed  $value the value of the field
	 */
	public function __set($field, $value) {
		if (isset($this->_changedProperties[$field]) && isset($this->_properties[$field]) &&
			($value == $this->_properties[$field])) {
			unset($this->_changedProperties[$field]);
		} else {
			$this->_changedProperties[$field] = $value;
		}
	}
	
	/**
	 * This method should be extended to set default values.
	 */
	protected function _setDefaultValues() {
		
	}
	
	/**
	 * Checks to see if a value has been changed in this object.  In other words, this method
	 * returns true if there is unsaved data.
	 * 
	 * @return boolean true if there is changed data to be written back to the database
	 */
	public function isDirty() {
		return (count($this->_changedProperties) > 0);
	}
	
	/**
	 * Clears the dirty bit and marks all properties as saved.  This should be called immediately
	 * after this objcet is saved back to the database.
	 */
	public function clearDirty() {
		foreach ($this->_changedProperties as $field => $value) {
			$this->_properties[$field] = $value;
		}
		$this->_changedProperties = array();
	}
	
	/**
	 * Gets the dirty properties array containing the keys and values for all properties which have
	 * been modified from their initial value.  This can be used very nicely for creating UPDATE and
	 * INSERT statements.
	 * 
	 * @return array[mixed]
	 */
	public function getDirtyProperties() {
		return $this->_changedProperties;
	}
	
	/**
	 * Gets the identifier for the database where this object is stored.
	 * 
	 * @return string
	 *   the identifier for the database
	 */
	public static function getDatabase() {
		return static::$_database;
	}
	
	/**
	 * Gets the name for the database table represented by this object.
	 * 
	 * @return string
	 *   the name of the database table
	 */
	public static function getTableName() {
		return static::$_tableName;
	}
	
	/**
	 * Gets the unique identifier field or fields for the database table represented by this object.  If there
	 * is only one Primary Key field, then this method returns the string name of that field.  If there
	 * are multiple fields in the primary key, then this method returns an array of string names.
	 * 
	 * @return string|array[string]
	 *   the field name or an array of field names which make up the unique identifier for this object
	 */
	public static function getIdField() {
		return static::$_idField;
	}

	/**
	 * Gets the unique identifier values for this record.
	 * 
	 * @return mixed|array[mixed]
	 */
	public function getId() {
		$keyField = static::$_idField;
		$id = null;
		if (is_array($keyField)) {
			$id = array();
			foreach ($keyField AS $field) {
				$id[$field] = $this->$field;
			}
		}
		else {
			$id = $this->$keyField;
		}
		return $id;
	}
	
	/**
	 * Gets the store for this class.
	 * 
	 * @return DBStore
	 *  the store
	 */
	public static function getStore() {
		return DBStore::getStore(get_called_class());
	}
	
	/**
	 * Gets records for this class based on a selector.
	 * 
	 * @param mixed $selector
	 * @return array[DBEntity]
	 */
	public static function get($selector = null, $orderedBy = null, $indexed = null) {
		return static::getStore()->get($selector, $orderedBy, $indexed);
	}
	
	/**
	 * Gets all records for this class.
	 * 
	 * @return array[DBEntity]
	 */
	public static function getAll($selector = null, $orderedBy = null, $indexed = null) {
		return static::getStore()->getAll($selector, $orderedBy, $indexed);
	}
}
