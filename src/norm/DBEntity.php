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
	 * True if the id field is numeric in value, i.e. doesn't need quoting.
	 * 
	 * @var bool
	 */
	protected static $_idFieldIsNumeric = true;
	
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
	 * New functionality when this is true.
	 * 
	 * @var boolean
	 */
	protected static $_autoCacheNewEntities = false;
	
	/**
	 * True if this entity should store references to owned data.
	 * 
	 * @var boolean
	 */
	protected static $_indexOwnedData = true;
	
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
		if ($class::$_autoCacheNewEntities)
			static::getStore()->putNew($entity);
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
		$value = static::convertFromDatabase($field, $value);
		return $value;
	}
	
	/**
	 * Sets the given value for the given field.
	 * 
	 * @param string $field the name of the field
	 * @param mixed  $value the value of the field
	 */
	public function __set($field, $value) {
	  if (is_array($value))
	    throw new Exception('cannot set any field to an array: '.get_class($this).'.'.$field);
	  if ($value !== null)
	    $value = $this->convertToDatabase($field, $value);
	  if (isset($this->_properties[$field]) && ($value === $this->_properties[$field])) {
			if (isset($this->_changedProperties[$field]))
				unset($this->_changedProperties[$field]);
	  } else if ($value === null) {
	    $this->_changedProperties[$field] = DBField::NULL;
		} else if (!isset($this->_changedProperties[$field]) || ($value !== $this->_changedProperties[$field])) {
			$this->_changedProperties[$field] = $value;
		}
	}
	
	public static function convertFromDatabase($field, $value) {
		$className = get_called_class();
		if (isset(self::$_fields[$className][$field]))
			$value = self::$_fields[$className][$field]->convertFromDatabase($value);
		else if ($field == $className::getIdField())
		    $value = intval($value);
		return $value;
	}
	
	public static function convertToDatabase($field, $value) {
		$className = get_called_class();
		if (isset(self::$_fields[$className][$field]))
			$value = self::$_fields[$className][$field]->convertToDatabase($value);
		return $value;
	}
	
	/**
	 * This method should be extended to set default values.
	 */
	protected function _setDefaultValues() {
		
	}
	
	public static function requiresQuoting($field) {
		$className = get_called_class();
		$value = true;
		if (isset(self::$_fields[$className][$field]))
			$value = self::$_fields[$className][$field]->requiresQuoting();
		if ($className::$_idField == $field)
		  $value = !$className::$_idFieldIsNumeric;
		return $value;
	}
	
	/**
	 * Sets the properties array directly.  Use with care.
	 * 
	 * @param array $properties
	 */
	public function setProperties($properties) {
		$this->_properties = $properties;
	}
	
	/**
	 * Reverts back to all saved values.  Does not affect child objects.
	 */
	public function revert() {
	  $this->_changedProperties = array();
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
	
	public function getOriginalValue($key) {
		return isset($this->_properties[$key]) ? $this->_properties[$key] : null;
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
	 * Gets the backing database object.
	 * 
	 * @return DBConnection the database connection
	 */
	public static function DB() {
		return DBConnection::get(static::getDatabase());
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
	 * Disables the caching of owned data.  Useful when loading data related to this data
	 * which is indexed separately from the object hierarchy.
	 */
	public static function disableOwnershipCache() {
		$class = get_called_class();
		$class::$_indexOwnedData = false;
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
			$id = intval($this->$keyField);
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
	 * entity type.  Returns null if the ID hasn't been assigned a value.
	 * 
	 * @return string local unique identifier
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
		if ($uqid == '')
			$uqid = null;
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
	 * Deletes this entity immediately in the backing store, as well as all owned entities.
	 */
	public function delete() {
		foreach ($this->_ownedObjectCache as $ownedObjectArray) {
			/* @var $ownedEntity DBEntity */
			foreach ($ownedObjectArray as $ownedEntity)
				$ownedEntity->delete();
		}
		static::getStore()->delete($this);
	}
	
	/**
	 * Refreshes this entity from the backing store, as well as all owned entities.  Does not search for
	 * new owned entities not already loaded.
	 */
	public function refresh($refreshedOwned = true) {
		if ($refreshedOwned) {
			foreach ($this->_ownedObjectCache AS $ownedObjectArray) {
				/* @var $ownedEntity DBEntity */
				foreach ($ownedObjectArray as $ownedEntity)
					$ownedEntity->refresh();
			}
		}
		static::getStore()->refresh($this);
	}
	
	/**
	 * Saves all cached entities of this class back into the database.
	 */
	public static function saveAll() {
		static::getStore()->saveAll();
	}
	
	public static function refreshAll() {
		foreach (self::$_ownedData AS $foreignKey) {
			if (get_called_class() == $foreignKey->getPrimaryEntityClass()) {
				/* @var $class DBEntity */
				$class = $foreignKey->getForeignEntityClass();
				$class::refreshAll();
			}
		}
		static::getStore()->refreshAll();
	}
	
	public static function getOwnedKeys() {
	  return array_keys(self::$_ownedData);
	}
	
	/**
	 * Gets all the owned data for this object.
	 * 
	 * @return DBEntity[]
	 */
	public function getOwnedData() {
	  $data = array();
	  foreach (array_keys(self::$_ownedData) AS $key) { //  => $bool
	    if (isset($this->_ownedObjectCache[$key]))
	     $data[$key] = $this->_ownedObjectCache[$key];
	  }
	  return $data;
	}
	
	/**
	 * Saves this object to the backing database.  This method is always called by the backing store
	 * to save the data, and can be used by child classes to modify this behavior.
	 * 
	 * @return true if this record was updated
	 */
	public function save() {
		$recordUpdated = false;
		if (count($this->_changedProperties) > 0) {
			static::getStore()->save($this);
			foreach ($this->_changedProperties AS $key => $value) {
			  if ($value === DBField::NULL)
			    unset($this->_properties[$key]);
			  else
			    $this->_properties[$key] = $value;
			}
			$this->_changedProperties = array();
			$recordUpdated = true;
		}
		foreach ($this->_ownedObjectCache AS $keyName => $ownedObjectArray) {
			/* @var $key DBForeignKey */
			$key = DBForeignKey::get($keyName);
			if ($key->getPrimaryEntityClass() != get_called_class()) {
			  continue;
			}
			$foreignColumn = $key->getForeignColumns();
			if (is_array($foreignColumn))
				error_log('warning: will not save multiple column key '.$keyName.'\n');
			foreach ($ownedObjectArray AS $id => $ownedEntity) {
				if (!$ownedEntity)
					throw new Exception('no owned entity for key "' . $keyName . '" and id ' . $id . ' (null in cache)');
				if ($ownedEntity->isMarkedForDeletion()) {
				    if ($ownedEntity->getId()) {
    					$ownedEntity->delete();
    					unset($ownedObjectArray[$id]);
				    }
				} else {
					$ownedEntity->__set($foreignColumn, $this->getId());
					$ownedEntity->save();
				}
			}
		}
		return $recordUpdated;
	}
	
	const KEY_PROPERTIES = '_properties';
	const KEY_CHILDREN = '_children';
	const KEY_CLASS = '_class';
	
	private function getJsonArray($includeClass = true) {
	    $array = [];
		
        if ($includeClass) $array[self::KEY_CLASS] = get_class($this);
		
		foreach ($this->_properties AS $key => $value) {
		    if (!isset($array[$key]))
		      $array[$key] = self::convertFromDatabase($key, $value);
		}
		
		$childrenArray = array();
		foreach ($this->_ownedObjectCache AS $foreignKey => $ownedObjects) {
			$ownedArray = array();
			/* @var $object DBEntity */
			foreach ($ownedObjects AS $id => $object) {
				$ownedArray[$id] = $object->getJsonArray($includeClass);
			}
			$childrenArray[$foreignKey] = $ownedArray;
		}
		
		if (count($childrenArray))
			$array[self::KEY_CHILDREN] = $childrenArray;

		return $array;
	}
	
	/**
	 * Exports this object into a JSON record.
	 */
	public function saveToJson($includeClass = true) {
		return json_encode($this->getJsonArray($includeClass));
	}
	
	/**
	 * Exports the objects provided into JSON as an array of objects.
	 * 
	 * @param DBEntity[] $entities
	 * @return string JSON
	 */
	public static function SAVE_TO_JSON($entities, $includeClass = true) {
	    $array = [];
	    foreach ($entities AS $entity)
	        $array[] = $entity->getJsonArray($includeClass);
	    return json_encode($array);
	}
	
	/**
	 * Loads data into this object from the given JSON array.
	 * 
	 * @param mixed[] $array
	 * @param boolean $clearUnusedFields
	 */
	public function loadFromJsonArray($array, $clearUnusedFields = false) {
		if (isset($array[$this->getIdField()]))
			$this->setId($array[$this->getIdField()]);
		
		/* @var $field DBField */
		foreach ($this->getFields() AS $key => $field) {
			if (isset($array[$key]))
				$this->__set($key, $array[$key]);
			else if ($clearUnusedFields)
			    $this->__set($key, null);
		}
		
		if (isset($array[self::KEY_CHILDREN])) {
			foreach ($array[self::KEY_CHILDREN] AS $keyname => $children) {
				
				foreach ($children AS $childArray) {
					$className = $childArray[self::KEY_CLASS];
					/* @var $child DBEntity */
					$child = $className::CREATE();
					$child->loadFromJsonArray($childArray);
					$foreignKey = $child::_getForeignKey($keyname);
					$this->_addOwnedInstance($foreignKey->getName(), $child);
				}
			}
		}
	}
	
	/**
	 * Loads data into this object from the given JSON.
	 * 
	 * @param string $json
	 * @param boolean $clearUnusedFields
	 * @throws Exception
	 */
	public function loadFromJson($json, $clearUnusedFields = false) {
		$array = json_decode($json, true);
		if ($array == null)
		    throw new Exception('json could not be decoded: '.$json);
		$this->loadFromJsonArray($array, $clearUnusedFields);
	}
	
	public function getOneToManyData($class, $foreignField) {
		/* @var $store DBStore */
		$store = $class::getStore();
		return $store->getAll(array($foreignField => $this->getId()));
	}
	
	/**
	 * Loads any fields found as a key in the given array.  Any keys in the array which
	 * do not match a field on this object are ignored.
	 * 
	 * @param mixed[] $array
	 */
	public function loadFromArray($array) {
	  /* @var $field DBField */
	  foreach ($this->getFields() AS $key => $field) {
	    if (isset($array[$key]))
	      $this->__set($key, $array[$key]);
	  }
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
	 * Gets the first number of records matching the given selector in the order specified.
	 * Suppling a value for maxResults will cause the return value to be an array.  Not
	 * specifying the value will use maxResults 1 and take it out of the array or return null.
	 * 
	 * @param mixed $selector
	 *  the selector to filter by
	 * @param string|array[string] $orderBy
	 *  the field or fields to order by
	 * @param int $maxResults
	 *  the maximum number of results to return (default = 1)
	 * @return DBEntity|array[DBEntity]
	 *  for maxResults is unspecified, just return the entity, otherwise an array
	 */
	public static function first($selector, $orderBy, $maxResults = 0) {
		$deArray = ($maxResults == 0);
		$maxResults = max($maxResults, 1);
		$results = static::page($selector, $orderBy, $maxResults);
		if ($deArray) {
			if (count($results))
				$results = array_pop($results);
			else
				$results = null;
		}
		return $results;
	}
	
	/**
	 * Gets the page of records matching the given selector in the order specified.  The page
	 * will be of the given size and have the given number of records as an offset.
	 * 
	 * @param mixed $selector
	 *  the selector to filter by
	 * @param string|array[string] $orderBy
	 *  the field or fields to order by
	 * @param int $maxResults
	 *  the maximum number of results to return
	 * @param int $offset
	 *  the number of records at the beginning to skip (default = 0)
	 */
	public static function page($selector, $orderBy, $maxResults, $offset = 0) {
		return static::getStore()->getPaginated($selector, $orderBy, $maxResults, $offset);
	}
	
	/**
	 * Gets one entity based on a selector.  This method returns null if the entity
	 * is not found.  It throws an exception if more than one entity matches the $selector.
	 * 
	 * @param mixed $selector
	 * @return DBEntity
	 */
	public static function GET($selector) {
		if ($selector === null) {
			throw new Exception('selector must be specified with get');
		}
		return static::getStore()->get($selector);
	}
	
	/**
	 * Gets the first specified number of entities based on the selector, in the given order, skipping the given number.
	 * If the max records is 1 or unspecified, then this returns the one result or null.
	 * Otherwise, this returns an array of results.
	 * 
	 * @param mixed $selector
	 * @param string|array[string] $orderedBy
	 * @param int $maxRecords
	 * @param int $offset
	 * @return DBEntity|array[DBEntity]
	 */
	public static function getFirst($selector = null, $orderedBy = null, $maxRecords = 1, $offset = 0) {
		$results = static::getStore()->getFirst($selector, $orderedBy, $maxRecords, $offset);
		if ($maxRecords == 1)
			return isset($results[0]) ? $results[0] : null;
		return $results;
	}
	
	/**
	 * Gets all records for this class.  If a selector is provided, then these records are
	 * loaded from the database.
	 * 
	 * @return array[DBEntity]
	 */
	public static function getAll($selector = null, $orderedBy = null, $indexed = null) {
		return static::getStore()->getAll($selector, $orderedBy, $indexed);
	}
	
	/**
	 * @return array[DBEntity]
	 */
	public static function getCached() {
		return static::getStore()->getCached();
	}
	
	public static function clearCache() {
		static::getStore()->clearCache();
	}
	
	public static function cache($selector = null, $indexedBy = null) {
		static::getStore()->cache($selector, $indexedBy);
	}
	
	public static function countAll() {
		return static::getStore()->countAll();
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
		
		// set foreign ID
		$key = DBForeignKey::get($keyName);
		$foreignColumns = $key->getForeignColumns();
		if (!is_array($foreignColumns))
			$ownedInstance->__set($foreignColumns, $this->getId());
		if ($key->getPrimaryEntityClass() != get_called_class())
		  throw new Exception('key name '.$keyName.' belongs to '.$key->getPrimaryEntityClass().' instead of '.get_called_class());
		
		$class = get_class($this);
		if ($class::$_indexOwnedData) {
			// store in cache
			$this->_setupOwnedInstanceCache($keyName);
			$id = $ownedInstance->getId();
			if (is_array($id) || !$ownedInstance->getId()) {
				$this->_ownedObjectCache[$keyName][] = $ownedInstance;
			} else {
				$this->_ownedObjectCache[$keyName][$ownedInstance->getId()] = $ownedInstance;
			}
		}
	}

	/**
	 * Uncaches the given owned instance.  It will not be marked for deletion.
	 * 
	 * @param String $keyName
	 * @param DBEntity $ownedInstance
	 */
	protected function _uncacheOwnedInstance($keyName, $ownedInstance) {
		$key = DBForeignKey::get($keyName);
		
		if (!isset($this->_ownedObjectCache[$keyName]))
			throw new Exception("Instance is not owned by this cache");
		
		$id = $ownedInstance->getId();
		if (is_array($id)) {
			throw new Exception("Array not supported for key in DBEntity::_deleteOwnedInstance");
		} else {
			unset($this->_ownedObjectCache[$keyName][$ownedInstance->getId()]);
		}
		$ownedInstance->markForDeletion();
	}
	
	/**
	 * Removes the given owned instance.  It will be marked for deletion.
	 * 
	 * @param String $keyName
	 * @param DBEntity $ownedInstance
	 */
	protected function _removeOwnedInstance($keyName, $ownedInstance) {
		$this->_uncacheOwnedInstance($keyName, $ownedInstance);
		$ownedInstance->markForDeletion();
	}
	
	/**
	 * Removes all of the owned instances from this object for the given key.
	 * 
	 * @param String $keyName
	 */
	protected function _removeOwnedInstances($keyName) {
		$this->_ownedObjectCache[$keyName] = array();
	}
	
	/**
	 * Deletes the given owned instance.  It is removed from the backing store immediately.
	 * 
	 * @param String $keyName
	 * @param DBEntity $ownedInstance
	 */
	protected function _deleteOwnedInstance($keyName, $ownedInstance) {
			$key = DBForeignKey::get($keyName);
		
		if (!isset($this->_ownedObjectCache[$keyName]))
			throw new Exception("Instance is not owned by this cache");
		
		$id = $ownedInstance->getId();
		if (is_array($id)) {
			throw new Exception("Array not supported for key in DBEntity::_deleteOwnedInstance");
		} else {
			unset($this->_ownedObjectCache[$keyName][$ownedInstance->getId()]);
		}
		$ownedInstance->delete();
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
			if ($this->$primaryColumns) {
				$selector = array($foreignColumns => $this->$primaryColumns);
				$foreignClass = $key->getForeignEntityClass();
				$this->_ownedObjectCache[$keyName] = $foreignClass::getAll($selector);
			} else {
				$this->_ownedObjectCache[$keyName] = array();
			}
		}
		$retVal = $this->_ownedObjectCache[$keyName];
		return $retVal;
	}
	
	/**
	 * Gets a singular owned instance for the given owning foreign key relationship,
	 * according to the given selector.  This method searches the cache for a matching
	 * entity.  Responsibility for indexing this search rests in the store.
	 * 
	 * @param string $foreignKeyName
	 *  foreign key name
	 */
	protected function _getOwnedInstance($foreignKeyName, $selector) {
		$foreignKey = DBForeignKey::get($foreignKeyName);
		if (!$foreignKey)
			throw new Exception('specified key not found: '.$foreignKeyName);
		$retVal = null;
		
		if (!isset($this->_ownedObjectCache[$foreignKeyName])) {
			$this->_ownedObjectCache[$foreignKeyName] = array();
		}
		
		// TODO: move this foreign key logic into the store
		$foreignColumns = $foreignKey->getForeignColumns();
		$primaryColumns = $foreignKey->getPrimaryColumns();
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
		foreach ($this->_ownedObjectCache[$foreignKeyName] AS $instance) {
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
			$foreignClass = $foreignKey->getForeignEntityClass();
			$retVal = $foreignClass::get($selector);
			if ($retVal)
				$this->_ownedObjectCache[$foreignKeyName][] = $retVal;
		}
		return $retVal;
	}
	
	/**
	 * 
	 * @param DBForeignKey $key
	 * @param mixed $selector
	 * @param mixed[] $subForeignArray
	 */
	private static function _loadOwnedData($key, $selector, $subForeignArray = array(), $subFilter = array()) {
		$foreignColumns = $key->getForeignColumns();
		$primaryColumns = $key->getPrimaryColumns();
		if ((is_array($foreignColumns) && (count($foreignColumns) > 1)) ||
			(is_array($primaryColumns) && (count($primaryColumns) > 1)))
			throw new Exception('cannot load owned data through multi-column foreign key - yet');
		if (is_array($foreignColumns))
			$foreignColumns = $foreignColumns[0];
		if (is_array($primaryColumns))
			$primaryColumns = $primaryColumns[0];
		
		// eventually, this needs to be handled by a selector class
		// an array which is indexed and has the same number of values as the number of keys
		// ^ this should not be handled with a new DBQuery (subquery)
		// of course, this is not supported yet, and will not be needed for any future databases
		if (is_array($selector)) {
		  
		  // if there's only one selector and it's the primary key of the reference table,
		  // then just apply the filter to the foreign column
		  if ((count($selector) == 1) && isset($selector[$primaryColumns]))
		    $subSelector = array($foreignColumns => $selector[$primaryColumns]);
		  else
		    $subSelector = array($foreignColumns => new DBQuery($key->getPrimaryEntityClass(), $primaryColumns, $selector));
		} else if (is_null($selector))
			$subSelector = null;
		else
			$subSelector = array($foreignColumns => $selector);
		
		if (count($subFilter) > 0) {
			if (is_array($subSelector)) {
				foreach ($subFilter AS $field => $value) {
					$foreignClass = $key->getForeignEntityClass();
					if ($foreignClass::hasField($field))
						$subSelector[$field] = $value;
				}
			} else {
				$subSelector = $subFilter;
			}
		}
		$ownedClass = $key->getForeignEntityClass();
		
		/* @var $store DBStore */
		$store = $ownedClass::getStore();
		$subSelector = $store->applyGlobalFilter($subSelector);
		
		/* @var $ownedClass DBEntity */
		$ownedData = $ownedClass::getAll($subSelector);
		if ((count($subForeignArray) > 0) && (count($ownedData) > 0)) {
			$ownedClass::loadForeign($subForeignArray, $subSelector, $subFilter);
		}
		/* @var $ownedObject DBEntity */
		foreach ($ownedData AS $ownedObject) {
			$foreignValue = $ownedObject->__get($foreignColumns);
			if (!$foreignValue)
				continue;
			try {
				$parentObject = static::get($ownedObject->__get($foreignColumns));
			} catch (Exception $e) {
				throw new Exception('foreign key ' . $key->getName() . ' defined in ' . $key->getForeignEntityClass() . ' has an invalid foreign column "' . $foreignColumns . '": '.$e->getMessage());
			}
			if ($parentObject) {
				$parentObject->_addOwnedInstance($key->getName(), $ownedObject);
			}
		}
	}
	
	/**
	 * Reassigns all owned instances for the given key so child-parent relationships are all created.
	 * 
	 * @param String $keyName
	 */
	public static function cleanupOwnedInstances($keyName) {
		$key = self::$_ownedData[$keyName];
		foreach (self::getCached() AS $parentObject)
			$parentObject->_removeOwnedInstances($keyName);
		
		if ($key->getPrimaryEntityClass() != get_called_class())
			throw new Exception('key '.$keyName.' is not a foreign key for primary entity '.get_called_class());
		
		$ownedClass = $key->getForeignEntityClass();
		$ownedData = $ownedClass::getCached();
		/* @var $ownedObject DBEntity */
		foreach ($ownedData AS $ownedObject) {
			try {
				$parentObject = static::get($ownedObject->__get($key->getForeignColumns()));
			} catch (Exception $e) {
				throw new Exception('foreign key ' . $key->getName() . ' defined in ' . $key->getForeignEntityClass() . ' has an invalid foreign column "' . $key->getForeignColumns() . '": '.$e->getMessage());
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
	 * @param mixed[] $subForeignArray
	 */
	private static function _loadForeignData($key, $selector, $subForeignArray, $subFilter = array()) {
		//$primaryClass = $key->getPrimaryEntityClass();
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
		if (count($subFilter) > 0) {
			if (is_array($subSelector)) {
				foreach ($subFilter AS $field => $value) {
					$foreignClass = $key->getForeignEntityClass();
					if ($foreignClass::hasField($field))
						$subSelector[$field] = $value;
				}
			} else {
				$subSelector = $subFilter;
			}
		}
		$ownedClass = $key->getPrimaryEntityClass();
		
		/* @var $ownedClass DBEntity */
		if (count($ownedClass::getAll($subSelector)) > 0)
		  $ownedClass::loadForeign($subForeignArray, $subSelector, $subFilter);
	}
	
	public function clearOwnedCache($foreignKey) {
		unset($this->_ownedObjectCache[$foreignKey]);
	}
	
	public static function loadForeign($foreignArray, $selector = false, $filter = array()) {
		if ($selector === false)
			throw new Exception('you must specify a selector when loading foreign data');
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
			$subForeignFilter = array();
			if (isset($filter[$keyName]))
				$subForeignFilter = $filter[$keyName];
			$key = DBForeignKey::get($keyName);
			if (get_called_class() == $key->getPrimaryEntityClass()) {
				if (!isset(static::$_ownedData[$keyName]))
					throw new Exception('did not declare owned data in foreign key definition '.$keyName);
				static::_loadOwnedData(static::$_ownedData[$keyName], $selector, $subForeignArray, $subForeignFilter);
			}
			else if (get_called_class() == $key->getForeignEntityClass()) {
				static::_loadForeignData(DBEntity::$_foreignKeys[$keyName], $selector, $subForeignArray, $subForeignFilter);
			}
			else {
				throw new Exception('Foreign data redefined for key ' . $keyName . ' and class ' . get_called_class());
			}
			
			// by creating an empty array in the cache, this indicates that the data has been loaded
			foreach (self::getCached() AS $entity)
				if (!isset($entity->_ownedObjectCache[$keyName]))
					$entity->_ownedObjectCache[$keyName] = array();
		}
	}

	/**
	 * 
	 * @param DBForeignKey $foreignKey
	 */
	private static function _registerForeignKey($foreignKey) {
		if (isset(DBEntity::$_foreignKeys[$foreignKey->getName()])) {
			$existingKey = DBEntity::$_foreignKeys[$foreignKey->getName()];
			throw new Exception('foreign key already defined for '.$foreignKey->getname().' in '.$existingKey->getForeignEntityClass());
		}
		
		DBEntity::$_foreignKeys[$foreignKey->getName()] = $foreignKey;
	}
	
	private static function _getForeignKey($keyName) {
		return DBEntity::$_foreignKeys[$keyName];
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
		$className = get_called_class();
		self::$_fields[$className][$name] = $field;
	}
	
	public static function hasField($name) {
		$className = get_called_class();
		return isset(self::$_fields[$className][$name]);
	}
	
	/**
	 * Gets the fields for this entity.
	 * 
	 * @return DBField[]
	 */
	public static function getFields() {
		$className = get_called_class();
		return self::$_fields[$className];
	}
	
	public static function getFieldNames() {
		$className = get_called_class();
		return array_keys(self::$_fields[$className]);
	}
	
	public static function createSchemaArray() {
	    $className = get_called_class();
	    
	    $propertiesArray = [
	        self::getIdField() => [
	            'type' => 'integer'
	        ]
	    ];
	    $requiredArray = [
	        self::getIdField()
	    ];
	    
	    $ownedArray = [];
	    
	    /* @var $field DBField */
	    foreach (self::getFields() AS $fieldName => $field) {
	        $fieldArray = [
	            'type' => $field->isBinaryOnly() ? 'boolean' : ($field->requiresQuoting() ? 'string' : 'number')
	        ];
	        
	        $propertiesArray[$fieldName] = $fieldArray;
	    }
	    
	    foreach (self::$_foreignKeys AS $keyName => $foreignKey) {
	        $isOwned = isset(self::$_ownedData[$keyName]);
	        if ($isOwned && ($foreignKey->getPrimaryEntityClass() == $className)) {
	            $propertiesArray[$foreignKey->getForeignEntityClass()] = [
	                '$ref' => '/'.$foreignKey->getForeignEntityClass().'/schema'
	            ];
	            $ownedArray[$foreignKey->getForeignEntityClass()] = $foreignKey->getForeignColumns();
	        }
	    }
	    
	    $schema = [
	        '$schema' => 'http://json-schema.org/draft-07/schema#',
	        '$id' => $className,
	        'type' => 'object',
	        '$filters' => [
	            '$func' => 'GenericId',
	            '$vars' => [
	                'key' => self::getIdField()
	            ]
	        ],
	        'properties' => $propertiesArray,
	        'required' => $requiredArray
	    ];
	    
	    if (count($ownedArray)) {
	        $schema['children'] = $ownedArray;
	    }
	    
	    return $schema;
	}
}
