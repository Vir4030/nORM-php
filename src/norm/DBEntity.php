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
	 * An array of fields, as defined by the subclass.  Fields do not need to be declared in this
	 * array, however, doing so will enable helper functionality throughout the DB library.
	 * This array is filled through calls to static::declareField.
	 * 
	 * @var array[DBField]
	 */
	protected static $_fields = array();
	
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
	 * True if marked for deletion.  This allows sub-objects to be deleted without deleting the parent
	 * object.
	 * 
	 * @var boolean
	 *  true if marked for deletion
	 */
	private $_markedForDeletion = false;
	
	/**
	 * Class constructor creates a new DBEntity with the given properties.  The properties
	 * array is copied by reference into the object for performance.  When this method is
	 * called, be sure the array provided is not altered after it has been used in this
	 * constructor and stored in the object.
	 * 
	 * Scripts outside of the nORM library
	 * 
	 * @param array[mixed] $properties
	 */
	public function __construct(array $properties = array()) {
		$this->_properties = $properties;
	}
	
	/**
	 * Creates a new Entity, without an ID value, which is completely dirty.  Please keep in mind that
	 * this does nothing to cleanse the data.  Bit values and dates should probably not be assigned here.
	 * Otherwise, this will set any values to defaults before applying the array.
	 * 
	 * @param array $properties
	 *  the properties to initially assign
	 */
	public static function create(array $properties = array()) {
		$class = get_called_class();
		/* @var $entity DBEntity */
		$entity = new $class();
		$entity->_setDefaultValues();
		foreach ($properties AS $key => $value) {
			$entity->$key = $value;
		}
		return $entity;
	}
	
	/**
	 * Gets the value for the field with the given name.
	 * 
	 * @param string $field the name of the field
	 * @return mixed the value of the field
	 */
	public function __get($field) {
		$value = null;
		if (isset($this->_changedProperties[$field]))
			$value = $this->_changedProperties[$field];
		else if (isset($this->_properties[$field]))
			$value = $this->_properties[$field];
		if (isset(static::$_fields[$field])) {
			$value = static::$_fields[$field]->convertFromDatabase($value);
		}
		return $value;
	}
	
	/**
	 * Sets the given value for the given field.
	 * 
	 * @param string $field the name of the field
	 * @param mixed  $value the value of the field
	 */
	public function __set($field, $value) {
		if (isset(static::$_fields[$field]))
			$value = static::$_fields[$field]->convertToDatabase($value);
		if (isset($this->_properties[$field]) && ($value === $this->_properties[$field])) {
			if (isset($this->_changedProperties[$field]))
				unset($this->_changedProperties[$field]);
		} else if (!isset($this->_changedProperties[$field]) || ($value !== $this->_changedProperties[$field])) {
			$this->_changedProperties[$field] = $value;
		}
	}
	
	/**
	 * This method should be extended to set default values.
	 */
	protected function _setDefaultValues() {
		
	}
	
	public static function requiresQuoting($field) {
		$value = true;
		if (isset(static::$_fields[$field]))
			$value = static::$_fields[$field]->requiresQuoting();
		return $value;
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
	 * Marks this entity for deletion.
	 */
	public function markForDeletion() {
		$this->_markedForDeletion = true;
	}
	
	/**
	 * Checks if this entity is marked for deletion.
	 * 
	 * @return boolean
	 *  true if this entity is marked for deletion
	 */
	public function isMarkedForDeletion() {
		return $this->_markedForDeletion;
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
	
	public function delete() {
		foreach ( $this->_ownedObjectCache as $keyName => $ownedObjectArray ) {
			/* @var $ownedEntity DBEntity */
			foreach ( $ownedObjectArray as $ownedEntity ) {
				$ownedEntity->delete();
			}
		}
		static::getStore()->delete($this);
		return;
	}
	
	/**
	 * Saves all cached entities of this class back into the database.
	 */
	public static function saveAll() {
		static::getStore()->saveAll();
	}
	
	/**
	 * Saves this object to the backing database.
	 */
	public function save() {
		if (count($this->_changedProperties) > 0) {
			static::getStore()->save($this);
			foreach ($this->_changedProperties AS $key => $value)
				$this->_properties[$key] = $value;
			$this->_changedProperties = array();
		}
		foreach ($this->_ownedObjectCache AS $keyName => $ownedObjectArray) {
			/* @var $key DBForeignKey */
			$key = DBForeignKey::get($keyName);
			$foreignColumn = $key->getForeignColumns();
			if (is_array($foreignColumn))
				error_log('warning: will not save multiple column key '.$keyName.'\n');
			foreach ($ownedObjectArray AS $id => $ownedEntity) {
				if (!$ownedEntity)
					throw new Exception('no owned entity for key "' . $keyName . '" and id ' . $id . ' (null in cache)');
				if ($ownedEntity->isMarkedForDeletion()) {
					$ownedEntity->delete();
					unset($ownedObjectArray[$id]);
				} else {
					$ownedEntity->__set($foreignColumn, $this->getId());
					$ownedEntity->save();
				}
			}
		}
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
	
	public static function first($selector, $orderBy, $maxResults = 1) {
		return static::getStore()->getFirst($selector, $orderBy, $maxResults);
	}
	
	/**
	 * Gets one entity based on a selector.
	 * 
	 * @param mixed $selector
	 * @return DBEntity
	 */
	public static function get($selector) {
		if (!$selector) {
			throw new Exception('selector must be specified with get');
		}
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
	
	protected function _setupOwnedInstanceCache($keyName) {
		if (!isset($this->_ownedObjectCache[$keyName])) {
			$this->_ownedObjectCache[$keyName] = array();
		}
	}
	
	/**
	 * 
	 * @param DBForeignKey $key
	 * @param DBEntity $ownedInstance
	 */
	protected function _addOwnedInstance($keyName, $ownedInstance) {
		if (!$ownedInstance)
			throw new Exception('owned instance is null');
		$key = DBForeignKey::get($keyName);
		$this->_setupOwnedInstanceCache($keyName);
		$id = $ownedInstance->getId();
		if (is_array($id) || !$ownedInstance->getId()) {
			$this->_ownedObjectCache[$keyName][] = $ownedInstance;
		} else {
			$this->_ownedObjectCache[$keyName][$ownedInstance->getId()] = $ownedInstance;
		}
	}
	
	/**
	 * Gets all owned instances for the given owning foreign key relationship.
	 * 
	 * @param string $keyName
	 *  foreign key name
	 */
	protected function _getOwnedInstances($keyName) {
		$key = DBForeignKey::get($keyName);
		$retVal = array();
		
		if (!isset($this->_ownedObjectCache[$keyName])) {
			// TODO: move this foreign key logic into the store
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
			$foreignClass = $key->getForeignEntityClass();
			$this->_ownedObjectCache[$keyName] = $foreignClass::getAll($selector);
		}
		$retVal = $this->_ownedObjectCache[$keyName];
		return $retVal;
	}
	
	/**
	 * Gets a singular owned instance for the given owning foreign key relationship,
	 * according to the given selector.  This method searches the cache for a matching
	 * entity.  Responsibility for indexing this search rests in the store.
	 * 
	 * @param string $keyName
	 *  foreign key name
	 */
	protected function _getOwnedInstance($keyName, $selector) {
		$key = DBForeignKey::get($keyName);
		$retVal = null;
		
		if (!isset($this->_ownedObjectCache[$keyName])) {
			$this->_ownedObjectCache[$keyName] = array();
		}
		
		// TODO: move this foreign key logic into the store
		$foreignColumns = $key->getForeignColumns();
		$primaryColumns = $key->getPrimaryColumns();
		if ((is_array($foreignColumns) && (count($foreignColumns) > 1)) ||
		(is_array($primaryColumns) && (count($primaryColumns) > 1)))
			throw new Exception('cannot load owned data through multi-column foreign key - yet');
		if (is_array($foreignColumns))
			$foreignColumns = $foreignColumns[0];
		if (is_array($primaryColumns))
			$primaryColumns = $primaryColumns[0];
		$selector[$foreignColumns] = $this->$primaryColumns;
		
		// TODO: we should be more efficient than this, but so far we're not dealing with more than 20 rows max here
		$count = 0;
		foreach ($this->_ownedObjectCache[$keyName] AS $instance) {
			$match = true;
			foreach($selector AS $key => $value) {
				if ($instance->$key != $value) {
					$match = false;
					break;
				} 
			}
			if ($match) {
				$count++;
				$retVal = $instance;
			}
		}
		
		if ($count > 1) {
			throw new Exception('more than one instance was found matching the given selector');
		}
		
		if (!$retVal) {
			$foreignClass = $key->getForeignEntityClass();
			$retVal = $foreignClass::get($selector);
			if ($retVal)
				$this->_ownedObjectCache[$keyName][] = $retVal;
		}
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
			try {
				$parentObject = static::get($ownedObject->__get($foreignColumns));
			} catch (Exception $e) {
				throw new Exception('foreign key ' . $key->getName() . ' defined in ' . $key->getForeignEntityClass() . ' has an invalid foreign column "' . $foreignColumns . '"');
			}
			if ($parentObject) {
				$parentObject->_addOwnedInstance($key->getName(), $ownedObject);
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
		if (!is_array($foreignArray))
			$foreignArray = array($foreignArray);
		foreach ($foreignArray AS $keyName => $value) {
			$subForeignArray = array();
			if (is_numeric($keyName)) {
				$keyName = $value;
			} else {
				$subForeignArray = $value;
				if (!is_array($subForeignArray))
					$subForeignArray = array($subForeignArray);
			}
			$key = DBForeignKey::get($keyName);
			if (get_called_class() == $key->getPrimaryEntityClass()) {
				static::_loadOwnedData(static::$_ownedData[$keyName], $selector, $subForeignArray);
			}
			else if (get_called_class() == $key->getForeignEntityClass()) {
				static::_loadForeignData(static::$_foreignKeys[$keyName], $selector, $subForeignArray);
			}
			else {
				throw new Exception('Foreign data undefined for key ' . $keyName . ' and class ' . get_called_class());
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
	
	public static function declareField($name, $field) {
		static::$_fields[$name] = $field;
	}
}
