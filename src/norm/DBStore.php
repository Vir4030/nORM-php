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
		$class = $this->_class;
		$sql = 'SELECT ';
		if ($maxRecords || $offset)
			$sql .= $this->_connection->getPaginationAfterSelect($maxRecords, $offset). ' ';
		$sql .= '* FROM ' . $class::getTableName();
		$sep = ' WHERE ';
		if (!is_null($selector)) {
			 if (is_array($selector)) {
			 	foreach ($selector as $key => $value) {
			 		$sql .= $sep;
			 		if (is_array($value)) {
			 			if (isset($value['compare'])) {
			 				$compare = $value['compare'];
			 				if (isset($value['not']))
			 					$sql .= 'Not ';
			 				$value = $class::convertToDatabase($key, $value['value']);
			 				$sql .= $key . ' ' . $compare . ' ' . $this->_connection->quote($value, $class::requiresQuoting($key));
			 			} else {
				 			// array value means an 'in' clause
				 			
				 			$sql .= $key . ' In (';
				 			$count = 0;
				 			foreach ($value AS $in_value) {
				 				if ($count++) {
				 					$sql .= ',';
				 				}
				 				$sql .= $this->_connection->quote($in_value, $class::requiresQuoting($key));
				 			}
				 			$sql .= ')';
			 			}
			 		}
			 		else if ($value instanceof DBQuery) {
			 			$sql .= $key . ' In (' . $value->generateSQL($this->_connection) . ')';
			 		}
			 		else {
			 			$sql .= $key . ' = ' . $this->_connection->quote($value, $class::requiresQuoting($key));
			 		}
			 		$sep = ' AND ';
			 	}
			 }
			 else if ($selector) {
			 	if (is_array($class::getIdField())) {
			 		throw new Exception('key with multiple fields requires array-based selector');
			 	}
			 	if (is_array($selector)) {
			 		foreach ($selector AS $field => $value) {
			 			$sql .= $sep . $field . ' = ' . $this->_connection->quote($value, $class::requiresQuoting($field));
			 			$sep = ' AND ';
			 		}
			 	} else {
			 		$sql .= $sep . $class::getIdField() . ' = ' . $this->_connection->quote($selector, $class::requiresQuoting($class::getIdField()));
			 	}
			}
		}
		if (!is_null($orderBy)) {
			$sql .= ' ORDER BY ';
			if (is_array($orderBy)) {
				$count = 0;
				foreach ($orderBy AS $field) {
					if ($count++) {
						$sql .= ',';
					}
					$sql .= $field;
				}
			}
			else {
				$sql .= $orderBy;
			}
		}
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
				throw new Exception('id key is not properly configured for class ' . $this->_class . ' (id = '.$entity->getId().')');
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
			$this->_cachedEntities[$idKey] = $entity;
		}
		$this->_connection->free_result($rs);
		return $entities;
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
		if (!is_array($selector) && isset($this->_cachedEntities[$selector])) {
			return $this->_cachedEntities[$selector];
		}
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
	 * Stores the profiling array, as wrapped around getAll() to trace the performance impact
	 * of creating DBEntity objects.  This is only temporary, but will remain the last item in
	 * the source code until it is removed.
	 */
	private $_profileArray = array(
				'query' => 0,
				'fetch' => 0
	);
	
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
		
		$this->_profileArray['query'] -= round(microtime(true) * 1000);
		$rs = $this->queryPrimitive($selector, $orderBy);
		$this->_profileArray['query'] += round(microtime(true) * 1000);
		
		$this->_profileArray['fetch'] -= round(microtime(true) * 1000);
		$entities = $this->createEntitiesFromResultset($rs, (($orderBy == null) || $indexedBy), $indexedBy);
		$this->_profileArray['fetch'] += round(microtime(true) * 1000);
		
		return $entities;
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
		$results = array();
		$this->_profileArray['query'] -= round(microtime(true) * 1000);
		$rs = $this->queryPrimitive($selector, $orderBy, $maxRecords, $offset);
		$this->_profileArray['query'] += round(microtime(true) * 1000);
		
		$this->_profileArray['fetch'] -= round(microtime(true) * 1000);
		$entities = $this->createEntitiesFromResultset($rs, ($orderBy == null));
		$this->_profileArray['fetch'] += round(microtime(true) * 1000);
		
		return $entities;
	}
	
	/**
	 * Saves all of the cached entities back to the database.
	 */
	public function saveAll() {
		foreach ($this->_cachedEntities AS $entity) {
			$entity->save(); // seems awkward, but necessary to ensure foreign key values are always set, for example
		}
		foreach ($this->_newEntities AS $entity) {
			$entity->save();
			$this->_cachedEntities[$entity->getLocalUniqueIdentifier()] = $entity;
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
			$this->_cachedEntities[$entity->getLocalUniqueIdentifier()] = $entity;
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
