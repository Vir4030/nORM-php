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
	 * The field or array of fields to use as a unique identifier.  By default, this is set to
	 * 'id', which is the simplest possible name for an ID field - nothing more.  It is strongly
	 * recommended that this value be set in all subobjects.
	 * 
	 * This field can also contain an array of field names.  In this case, the primary key has
	 * multiple columns in it.  This is not recommended, but it is supported.  It is assumed that
	 * all ID fields will be set at the same time.  Please do not set one but not all of them.
	 * I'm not sure what the use case could possibly be for that, but it will break things.
	 * 
	 * @var string|array[string]
	 */
	protected static $_idField = 'id';
	
	/**
	 * An array describing the data owned by this entity.  This array is filled through calls to
	 * static::_registerOwnedData, which is called from static::declareForeignKey.
	 * 
	 * The key is the unique name of the foreign key.
	 * 
	 * @var array[DBForeignKey]
	 */
	protected static $_ownedData = array();
	
	/**
	 * An array describing the data to which this entity links through a foreign-key-type
	 * relationship.  This array is filled through calls to static::_registerForeignKey, which is
	 * called from static::declareForeignKey.
	 * 
	 * The key is the unique name of the foreign key.
	 * 
	 * @var array[DBForeignKey]
	 */
	protected static $_foreignKeys = array();
	
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
	 * This array contains a cache of DBEntity objects that are owned objects to this entity.  It is
	 * an array of arrays.  The key for the first array is the name of the foreign key describing the
	 * owning relationship. The key for the second array is the ID of the owned entity.
	 * 
	 * @var array[array[DBEntity]]
	 */
	private $_ownedObjectCache = array();
	
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
	 * Checks if the value or values provided could be a valid ID value based on the count alone.
	 * 
	 * @param mixed|array[mixed] $idValue
	 *  the value or array of values to check for validity on count
	 * @return boolean
	 *  true if the argument represents a potentially valid value on count alone
	 */
	public static function isValidIdCount($idValue) {
		$idField = static::getIdField();
		if (is_array($idValue) != is_array($idField))
			return false;
		if (is_array($idValue) && (count($idValue) != count($idField)))
			return false;
		return true;
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
				if ($this->$field != null)
					$id[$field] = $this->$field;
			}
		}
		else {
			$id = $this->$keyField;
		}
		return $id;
	}

	/**
	 * Sets the assigned numeric ID value unique to this entity.
	 * 
	 * TODO: make this method support multi-field identifiers
	 * 
	 * @param int $id
	 * @throws Exception when the key is not a singular field
	 */
	public function setId($id) {
		$keyField = static::$_idField;
		if (is_array($keyField))
			throw new Exception('cannot set ID value for multi-field identifiers - only auto-increment, effectively');
		$this->$keyField = $id;
	}
	
	/**
	 * Gets an ID key that is guaranteed to be unique to this entity - only across this
	 * entity type.
	 * 
	 * @return unknown
	 */
	public function getLocalUniqueIdentifier() {
		$uqid = '';
		$keyField = static::$_idField;
		if (is_array($keyField)) {
			foreach ($keyField AS $field) {
				if ($uqid)
					$uqid .= '-';
				$uqid .= $this->$field;
			}
		} else {
			$uqid = $this->$keyField;
		}
		return $uqid;
	}
	
	/**
	 * Gets an ID key that is guaranteed to be unique to this entity - across all entities.  This ID key
	 * can serve to uniquely identify this object in all caching scenarios.
	 * 
	 * @return string
	 */
	public function getGlobalUniqueIdentifier() {
		return get_class($this) . '-' . $this->getLocalUniqueIdentifier();
	}
	
	/**
	 * Saves this object to the backing database.
	 */
	public function save() {
		if (count($this->_changedProperties) > 0) {
			static::getStore()->save($this);
		}
		foreach ($this->_ownedObjectCache AS $ownedObjectArray) {
			foreach ($ownedObjectArray AS $ownedEntity) {
				$ownedEntity->save();
			}
		}
		$this->_wasLoadedFromDatabase = true;
	}
	
	public function getOneToManyData($class, $foreignField) {
		/* @var $store DBStore */
		$store = $class::getStore();
		return $store->getAll(array($foreignField => $this->getId()));
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
	 * Gets one entity based on a selector.
	 * 
	 * @param mixed $selector
	 * @return DBEntity
	 */
	public static function get($selector = null) {
		return static::getStore()->get($selector);
	}
	
	/**
	 * Gets all records for this class.
	 * 
	 * @return array[DBEntity]
	 */
	public static function getAll($selector = null, $orderedBy = null, $indexed = null) {
		return static::getStore()->getAll($selector, $orderedBy, $indexed);
	}
	
	/**
	 * 
	 * @param DBForeignKey $key
	 * @param DBEntity $ownedObject
	 */
	private function _addOwnedObject($key, $ownedObject) {
		$keyName = $key->getName();
		if (!isset($this->_ownedObjectCache[$keyName])) {
			$this->_ownedObjectCache[$keyName] = array();
		}
		$id = $ownedObject->getId();
		if (is_array($id)) {
			$this->_ownedObjectCache[$keyName][] = $ownedObject;
		} else {
			$this->_ownedObjectCache[$keyName][$ownedObject->getId()] = $ownedObject;
		}
	}
	
	/**
	 * 
	 * @param DBForeignKey $key
	 */
	protected function _getOwnedObjects($ownedClass, $keyName) {
		$key = $ownedClass::_getForeignKey($keyName);
		$retVal = array();
		if (!isset($this->_ownedObjectCache[$keyName])) {
			$foreignColumns = $key->getForeignColumns();
			$primaryColumns = $key->getPrimaryColumns();
			if ((is_array($foreignColumns) && (count($foreignColumns) > 1)) ||
				(is_array($primaryColumns) && (count($primaryColumns) > 1)))
				throw new Exception('cannot load owned data through multi-column foreign key - yet');
			if (is_array($foreignColumns))
				$foreignColumns = $foreignColumns[0];
			if (is_array($primaryColumns))
				$primaryColumns = $primaryColumns[0];
			$selector = array($foreignColumns => $this->$primaryColumns);
			$this->_ownedObjectCache[$keyName] = $ownedClass::getAll($selector);
		}
		$retVal = $this->_ownedObjectCache[$keyName];
		return $retVal;
	}
	
	/**
	 * 
	 * @param DBForeignKey $key
	 * @param mixed $selector
	 * @param unknown $subForeignArray
	 */
	private static function _loadOwnedData($key, $selector, $subForeignArray = array()) {
		$foreignColumns = $key->getForeignColumns();
		$primaryColumns = $key->getPrimaryColumns();
		if ((is_array($foreignColumns) && (count($foreignColumns) > 1)) ||
			(is_array($primaryColumns) && (count($primaryColumns) > 1)))
			throw new Exception('cannot load owned data through multi-column foreign key - yet');
		if (is_array($foreignColumns))
			$foreignColumns = $foreignColumns[0];
		if (is_array($primaryColumns))
			$primaryColumns = $primaryColumns[0];
		$subSelector = array($foreignColumns => new DBQuery($key->getPrimaryEntityClass(), $primaryColumns, $selector));
		$ownedClass = $key->getForeignEntityClass();
		/* @var $ownedClass DBEntity */
		$ownedData = $ownedClass::getAll($subSelector);
		if (count($subForeignArray) > 0) {
			$ownedClass::loadForeign($subForeignArray, $subSelector);
		}
		/* @var $ownedObject DBEntity */
		foreach ($ownedData AS $ownedObject) {
			$parentObject = static::get($ownedObject->$foreignColumns);
			if ($parentObject) {
				$parentObject->_addOwnedObject($key, $ownedObject);
			}
		}
	}
	
	/**
	 * 
	 * @param DBForeignKey $key
	 * @param mixed $selector
	 * @param unknown $subForeignArray
	 */
	private static function _loadForeignData($key, $selector, $subForeignArray) {
		$primaryClass = $key->getPrimaryEntityClass();
		$primaryColumns = $key->getPrimaryColumns();
		$foreignColumns = $key->getForeignColumns();
		if ((is_array($foreignColumns) && (count($foreignColumns) > 1)) ||
			(is_array($primaryColumns) && (count($primaryColumns) > 1)))
			throw new Exception('cannot load owned data through multi-column foreign key - yet');
		if (is_array($foreignColumns))
			$foreignColumns = $foreignColumns[0];
		if (is_array($primaryColumns))
			$primaryColumns = $primaryColumns[0];
		$subSelector = array($primaryColumns => new DBQuery($key->getForeignEntityClass(), $foreignColumns, $selector));
		$ownedClass = $key->getPrimaryEntityClass();
		/* @var $ownedClass DBEntity */
		$ownedClass::getAll($subSelector);
		$ownedClass::loadForeign($subForeignArray, $subSelector);
	}
	
	public static function loadForeign($foreignArray, $selector = null) {
		foreach ($foreignArray AS $key => $value) {
			$subForeignArray = array();
			if (is_numeric($key)) {
				$key = $value;
			} else {
				$subForeignArray = $value;
				if (!is_array($subForeignArray))
					$subForeignArray = array($subForeignArray);
			}
			if (isset(static::$_ownedData[$key])) {
				static::_loadOwnedData(static::$_ownedData[$key], $selector, $subForeignArray);
			}
			else if (isset(static::$_foreignKeys[$key])) {
				static::_loadForeignData(static::$_foreignKeys[$key], $selector, $subForeignArray);
			}
			else {
				throw new Exception('Foreign data undefined for key ' . $key);
			}
		}
	}

	/**
	 * 
	 * @param DBForeignKey $foreignKey
	 */
	private static function _registerForeignKey($foreignKey) {
		static::$_foreignKeys[$foreignKey->getName()] = $foreignKey;
	}
	
	private static function _getForeignKey($keyName) {
		return static::$_foreignKeys[$keyName];
	}
	
	/**
	 * 
	 * @param DBForeignKey $foreignKey
	 */
	private static function _registerOwnedData($foreignKey) {
		static::$_ownedData[$foreignKey->getName()] = $foreignKey;
	}
	
	public static function declareForeignKey($name, $foreignColumns, $primaryClass, $owned = false) {
		if (!$primaryClass::isValidIdCount($foreignColumns))
			throw new Exception('Foreign column count invalid for primary class');
		$primaryColumns = $primaryClass::getIdField();
		$foreignClass = get_called_class();
		if ($foreignClass == 'DBEntity')
			throw new Exception('Cannot call declareForeignKey on DBEntity');
		$foreignKey = new DBForeignKey($name, $primaryClass, $primaryColumns, $foreignClass, $foreignColumns);
		
		$foreignClass::_registerForeignKey($foreignKey);
		if ($owned)
			$primaryClass::_registerOwnedData($foreignKey);
	}
}
