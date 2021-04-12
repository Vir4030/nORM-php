<?php
/**
 * Interacts with a backing database to perform basic operations for a given class of entities.
 * 
 * Get and save operations can be done individually or in bulk.  This store provides for the
 * efficient execution of these basic operations.
 * 
 * Stores will be extended to provide for caching functionality.  Caching cannot really be defined at
 * the object level across a complex application.  When the only client is a web browser, it can be.
 * We will have clients that are reports, scripts, websites, portals, all with different audiences.
 * These places may all have different caching rules for data, because they will be producers of
 * some data and consumers of others.
 * 
 * When getting data without sorted results, all data is cached by id.  When data is then retrived
 * one record at a time by id, it will be returned from cache and retrieved from the database only
 * when necessary.  The _createEntitiesFromResultset() method will always check the cache for a
 * matching object and throw out the one from the database if it's found.  By always using the cached
 * object, it guarantees that a dirty read cannot be made from the database.
 * 
 * In the future, a search($selector) method will be made that will return only cache hits.  When index
 * support is added, this method will search a cache that is kept by index, so it will be very fast.
 * 
 * @author Erick
 */
class DBStore {
	
	/**
	 * The configuration which defines the connection
	 * @var DBConnection
	 */
	private $_connection;
	
	/**
	 * The DBEntity class.
	 * 
	 * @var string
	 *  the class name
	 */
	private $_class;
	
	/**
	 * cached entity objects
	 * @var array[DBEntity]
	 */
	private $_cachedEntities = array();
	
	/**
	 * new entity objects
	 * @var array[DBEntity]
	 */
	private $_newEntities = array();
	
	private $_fieldList = null;
	
	private $_globalFilter = null;
	
	/**
	 * cached database stores
	 * @var array[DBStore]
	 */
	private static $_cachedStores = array();
	
	/**
	 * Gets the store for the given class.  If not created, it is created.  Database connections will
	 * be shared among stores which are backed by the same database.
	 * 
	 * @param string $class
	 *  the class
	 * @return DBStore
	 *  the store
	 */
	public static function getStore($class) {
		if (!isset(DBStore::$_cachedStores[$class])) {
			DBStore::$_cachedStores[$class] = new DBStore(DBConnection::get($class::getDatabase()), $class);
		}
		return DBStore::$_cachedStores[$class];
	}
	
	/**
	 * Creates a new store object.
	 * 
	 * @param DBConnection $connection
	 *  the db connection to use
	 * @param string $class
	 *  the class
	 */
	private function __construct($connection, $class) {
		$this->_connection = $connection;
		$this->_class = $class;
		
		$this->connect();
	}

	/**
	 * Connects this store to its backing database.
	 */
	public function connect() {
		$this->_connection->connect();
	}
	
	/**
	 * Disconnects this store from its backing database.
	 */
	public function disconnect() {
		$this->_connection->disconnect();
	}
	
	public function setFieldList($fieldList) {
		$this->_fieldList = $fieldList;
	}
	
	public function hasFieldList() {
	  return $this->_fieldList !== null;
	}
	
	public function setGlobalFilter($globalFilter) {
		$this->_globalFilter = $globalFilter;
	}
	
	public function applyGlobalFilter($selector) {
	  if (is_array($selector) && $this->_globalFilter)
	    $selector = array_merge($selector, $this->_globalFilter);
	  return $selector;
	}
	
	/**
	 * Executes a query on the backing store for data described by the passed selector.
	 * 
	 * Options for the selector are as follows:
	 * 
	 * - null - default - get everything
	 * - numeric - integer id primary key value - get single record
	 * - string - replace ' with '', quote it, and select it against the primary key
	 * - array - key-value pair, each pair where key = 'value', regardless of type
	 * 
	 * This method is getting complex.  I imagine the details would eventually be
	 * farmed off into a system that handles more complex queries.
	 * 
	 * @param mixed $selector
	 *  the selector, as defined in this method for now
	 * @param null|string|array[string]
	 *  the columns to order by
	 * @throws Exception
	 *  for a malformed selector
	 */
	private function queryPrimitive($selector = null, $orderBy = null, $maxRecords = 0, $offset = 0) {
	  $selector = $this->applyGlobalFilter($selector);
		$query = new DBQuery($this->_class, $this->_fieldList, $selector, $orderBy);
		$sql = $query->generateSQL($this->_connection);
		if ($maxRecords || $offset)
			$sql .= ' ' . $this->_connection->getPaginationAfterStatement($maxRecords, $offset);
		return $this->_connection->query($sql);
	}
	
	/**
	 * Fetch each row of the resultset, creating an entity for each and returning the resulting array.
	 * When there is a singular primary key column, that will be used as the key for this array.
	 * Otherwise, it will just be a numerically-indexed array.
	 * 
	 * If any entities returned match an existing object in the cache, the object in the cache is
	 * returned instead.
	 * 
	 * @param mixed $rs
	 *  the implementation-specific resultset object, to be passed into the connection
	 * @param boolean $indexed
	 *  true if the array should be indexed by the global unique identifier for each object
	 * @param string $indexedBy
	 *  the field to index by
	 * @return array[DBEntity]
	 *  the array of entities created from the resultset
	 */
	
	private function createEntitiesFromResultset($rs, $indexed = false, $indexedBy = null) {
		$entities = array();
		/* @var $entity DBEntity */
		$entity = null;
		$class = $this->_class;
		while (($row = $this->_connection->fetch_assoc($rs)) != null) {
			// TODO: don't instantiate the object when it's a cache hit
			$entity = new $class($row);
			$idKey = $entity->getLocalUniqueIdentifier();
			if ($idKey == null)
				throw new Exception('id key is excluded or not properly configured for class ' . $this->_class . ' (id = '.$entity->getId().')');
			if (isset($this->_cachedEntities[$idKey]))
				$entity = $this->_cachedEntities[$idKey];
			if ($indexed) {
				if ($indexedBy)
					$entities[$entity->$indexedBy] = $entity;
				else
					$entities[$idKey] = $entity;
			} else {
				$entities[] = $entity;
			}
			$this->addToCache($entity);
		}
		$this->_connection->free_result($rs);
		return $entities;
	}
	
	/**
	 * Adds the given entity to the cache.  Almost an internal function, but apparently there's some non-DB generation that requires it.
	 * 
	 * @param DBEntity $entity
	 */
	public function addToCache($entity) {
		$this->_cachedEntities[$entity->getLocalUniqueIdentifier()] = $entity;
	}
	
	/**
	 * Gets the entity identified by the given ID.  The most standard case, by far, is that this
	 * is a value generated from an auto-increment column in the database.  This value dosen't
	 * have to be numeric, though.  Tables that have a primary key containing multiple columns
	 * will pass a key-value array.
	 * 
	 * This method can also be used for executing simple where-based selects.  Simply create
	 * a key-value array where the keys are the columns and the values are an = statement.
	 * 
	 * @param mixed $selector
	 *   the value of the primary key or a key-value pairing array
	 */
	public function get($selector) {
		$class = $this->_class;
		if (!is_array($selector) && isset($this->_cachedEntities[$selector]))
			return $this->_cachedEntities[$selector];

		$rs = $this->queryPrimitive($selector);
		$entities = $this->createEntitiesFromResultset($rs);
		if (count($entities) == 0)
			return null;
		if (count($entities) > 1) {
			throw new Exception('get returned more than one record');
		}
		return array_pop($entities);
	}
	
	/**
	 * Refreshes the given entity from the database.
	 * 
	 * @param DBEntity $entity
	 * @throws Exception
	 */
	public function refresh($entity) {
		$class = $this->_class;
		if (!$entity->getLocalUniqueIdentifier())
			throw new Exception("cannot refresh entity which doesn't have an ID value");
	
		$rs = $this->queryPrimitive($entity->getId());
		$row = $this->_connection->fetch_assoc($rs);
		if ($row) {
			$entity->clearDirty();
			$entity->setProperties($row);
		} else {
			$entity->markForDeletion();
		}
	}
	
	/**
	 * Gets all of the entities in this store.  This is essentially calling the following SQL:
	 * 
	 * SQL => SELECT * FROM {_tableName} ORDER BY {_orderBy}
	 * 
	 * When the selector is null, this returns the entire cache, if anything is there.
	 * This should always be the first method called for any entity if it is to be called at all
	 * without an argument.  With an argument, it will always fetch the results from the database.
	 * All results will always be cached by ID for individual gets.
	 * 
	 * @return array[DBEntity]
	 */
	public function getAll($selector = null, $orderBy = null, $indexedBy = null) {
		$results = array();
		
		if (!$selector && count($this->_cachedEntities)) {
			if (count($this->_newEntities))
				return array_merge($this->_cachedEntities, $this->_newEntities);
			return $this->_cachedEntities;
		}
		
		$rs = $this->queryPrimitive($selector, $orderBy);
		return $this->createEntitiesFromResultset($rs, (($orderBy == null) || $indexedBy), $indexedBy);
	}

	/**
	 * Gets the first number of entities from the database matching the given criteria.
	 * Ignores cached data, but the engine still merges if a record is re-selected.
	 * 
	 * @param string $selector the query selector
	 * @param string $orderBy field or array of fields by which to order
	 * @param int $maxRecords zero will get them all
	 * @param int $offset number of records to skip in sequence, for paging
	 * 
	 * @return array[DBEntity] the entities
	 */
	public function getFirst($selector = null, $orderBy = null, $maxRecords, $offset = 0) {
		$results = array();
		
		$rs = $this->queryPrimitive($selector, $orderBy, $maxRecords, $offset);
		return $this->createEntitiesFromResultset($rs);
	}
	
	public function getCached() {
		return $this->_cachedEntities;
	}
	
	public function getNew() {
		return $this->_newEntities;
	}
	
	public function cache($selector = null, $indexedBy = null) {
		static::getAll($selector, null, $indexedBy);
	}
	
	public function clearCache() {
		$this->_cachedEntities = array();
	}
	
	public function countAll() {
		return count($this->getAll());
	}
	
	/**
	 * Puts a new entity into this store, without an ID.
	 * 
	 * @param DBEntity $entity
	 */
	public function putNew($entity) {
		if ($entity->getLocalUniqueIdentifier())
			throw new Exception('Entity '.get_class($entity).' with ID '.$entity->getId().' cannot be addded into store for '.$this->_class);
		$this->_newEntities[] = $entity;
	}
	
	/**
	 * Gets a maximum number of records from the store using the given
	 * selector in the order specified by the order by column or array of columns.
	 * An optional offset can be provided if the driver supports it.
	 * 
	 * @param mixed $selector
	 *  the selector to filter
	 * @param string|array[string] $orderBy
	 *  the order to return
	 * @param int $maxRecords
	 *  the maximum number of records returned
	 * @param int $offset
	 *  the offset from the beginning of the recordset
	 * 
	 * @return array[DBEntity]
	 *  an array of entities
	 */
	public function getPaginated($selector, $orderBy, $maxRecords, $offset = 0) {
		$rs = $this->queryPrimitive($selector, $orderBy, $maxRecords, $offset);
		return $this->createEntitiesFromResultset($rs, ($orderBy == null));
	}

	/**
	 * Refreshes all of the cached entities from the database.
	 */
	public function refreshAll() {
		// TODO: Optimize this for only one query
		foreach ($this->_cachedEntities AS $entity) {
			$entity->refresh(false); // seems awkward, but it's the pattern used in saveAll()
		}
	}
	
	/**
	 * Saves all of the cached entities back to the database.  It does this by calling save() on each DBEntity.
	 * This ensures save-time behavior can be modified for a given class or type of entity.
	 */
	public function saveAll() {
		foreach ($this->_cachedEntities AS $entity) {
			$entity->save(); // seems awkward, but necessary to ensure foreign key values are always set, for example
			
			// after overriding save() in an entity, then coming here to ensure this method was called by saveAll()
			// this no longer seems awkward.
		}
		foreach ($this->_newEntities AS $entity) {
			$entity->save();
			$this->addToCache($entity);
		}
		$this->_newEntities = array();
	}
	
	
	/**
	 * Saves the given entity back to the database.
	 * 
	 * @param DBEntity $entity
	 */
	public function save($entity) {
		if ($entity->getLocalUniqueIdentifier())
			$this->update($entity);
		else
			$this->insert($entity);
	}
	
	/**
	 * Updates the entity, already known to be in the database, with new values.
	 * 
	 * This private method is called from within the save() method.
	 * 
	 * @param DBEntity $entity
	 * @return boolean true on success
	 */
	private function update($entity) {
		$class = $this->_class;
		$idArray = $entity->getId();
		if (!is_array($idArray))
			$idArray = array($entity::getIdField() => $idArray);

		// $idArray should now be key-value pair for ID fields
		$rowsAffected = $this->_connection->update($class, $entity->getDirtyProperties(), $idArray);
		if ($rowsAffected > 1)
			throw new Exception('multiple rows have been affected in entity update for ID ' . $entity->getGlobalUniqueIdentifier());
		return ($rowsAffected == 1);
	}
	
	/**
	 * Inserts the entity, already known to NOT be in the database, with new values.
	 * 
	 * This private method is called from within the save() method.
	 * 
	 * @param DBEntity $entity
	 */
	private function insert($entity) {
		$class = $this->_class;
		if ($entity->getLocalUniqueIdentifier())
			throw new Exception("cannot insert entity which already has ID value");
		
		$id = $this->_connection->insert($class, $entity->getDirtyProperties());
		if ($id && ($id !== TRUE)) {
			$entity->setId($id);
			$this->addToCache($entity);
		}
	}
	
	/**
	 * Deletes the entity, which must be already known in the database.
	 * 
	 * @param DBEntity $entity
	 */
	public function delete($entity) {
		$class = $this->_class;
		if (!$entity->getLocalUniqueIdentifier())
			throw new Exception("cannot delete entity which doesn't have an ID value");
		
		$idArray = $entity->getId();
		if (!is_array($idArray))
			$idArray = array($entity::getIdField() => $idArray);
		
		unset($this->_cachedEntities[$entity->getLocalUniqueIdentifier()]);
		$this->_connection->delete($class, $idArray);
	}
}
