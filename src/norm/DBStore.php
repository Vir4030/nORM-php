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
	private function queryPrimitive($selector = null, $orderBy = null) {
		$class = $this->_class;
		$sql = 'SELECT * FROM ' . $class::getTableName();
		$sep = ' WHERE ';
		if (!is_null($selector)) {
			 if (is_array($selector)) {
			 	foreach ($selector as $key => $value) {
			 		if (is_array($value)) {
			 			// array value means an 'in' clause
			 			
			 			$sql .= $sep . $key . ' In (';
			 			$count = 0;
			 			foreach ($value AS $in_value) {
			 				if ($count++) {
			 					$sql .= ',';
			 				}
			 				$sql .= $this->_connection->quote($in_value);
			 			}
			 			$sql .= ')';
			 		}
			 		else {
			 			$sql .= $sep . $key . ' = ' . $this->_connection->quote($value);
			 		}
			 		$sep = ' AND ';
			 	}
			 }
			 else {
			 	if (is_array($class::getIdField())) {
			 		throw new Exception('key with multiple fields requires array-based selector');
			 	}
			 	$sql .= $sep . $class::getIdField() . ' = ' . $this->_connection->quote($selector);
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
		return $this->_connection->query($sql);
	}
	
	/**
	 * Fetch each row of the resultset, creating an entity for each and returning the resulting array.
	 * When there is a singular primary key column, that will be used as the key for this array.
	 * Otherwise, it will just be a numerically-indexed array.
	 * 
	 * @param mixed $rs
	 *  the implementation-specific resultset object, to be passed into the connection
	 * @param string $indexedBy
	 *   the field to index the results by
	 * @return array[DBEntity]
	 *  the array of entities created from the resultset
	 */
	
	private function createEntitiesFromResultset($rs, $indexedBy = null) {
		$entities = array();
		/* @var $entity DBEntity */
		$entity = null;
		$class = $this->_class;
		while (($row = $this->_connection->fetch_assoc($rs)) != null) {
			$key = $class::getIdField();
			if ($indexedBy) {
				$key = $indexedBy;
			}
			$entity = new $class($row);
			if (is_array($key)) {
				$entities[] = $entity;
			}
			else {
				$entities[$row[$key]] = $entity;
			}
			$idKey = $entity->getGlobalUniqueIdentifier();
			if (!$idKey)
				throw new Exception("entity does not have an id value - this should NEVER happen, since this data is being loaded from the database");
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
		$rs = $this->queryPrimitive($selector);
		$entities = $this->createEntitiesFromResultset($rs);
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
	 * SQL => SELECT * FROM {_tableName}
	 * 
	 * @return array[DBEntity]
	 */
	public function getAll($selector = null, $orderBy = null, $indexedBy = null) {
		$results = array();
		$this->_profileArray['query'] -= round(microtime(true) * 1000);
		$rs = $this->queryPrimitive($selector, $orderBy);
		$this->_profileArray['query'] += round(microtime(true) * 1000);
		$this->_profileArray['fetch'] -= round(microtime(true) * 1000);
		$rs = $this->createEntitiesFromResultset($rs, $indexedBy);
		$this->_profileArray['fetch'] += round(microtime(true) * 1000);
		return $rs;
	}

	/**
	 * Saves the given entity back to the database.
	 * 
	 * @param DBEntity $entity
	 */
	public function save($entity) {
		if (isset($this->_cachedEntities[$entity->getGlobalUniqueIdentifier()]))
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
		$rowsAffected = $this->_connection->update($entity::getTableName(), $entity->getDirtyProperties(), $idArray);
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
		if ($entity->getGlobalUniqueIdentifier())
			throw new Exception("cannot insert entity which already has ID value");
		
		$id = $this->_connection->insert($entity::getTableName(), $entity->getDirtyProperties());
		if ($id && ($id !== TRUE)) {
			$entity->setId($id);
		}
	}
	
	/**
	 * Resets the profile array.  Call this before any getAll calls that should be accumulated.
	 */
	public function resetProfileArray() {
		$this->_profileArray = array(
				'query' => 0,
				'fetch' => 0
		);
	}
	
	/**
	 * Gets the profile array.  Call this to get the profile array.  Key-value pairs show
	 * performance of query vs fetch time.
	 * 
	 * @return array[numeric]
	 */
	public function getProfileArray() {
		return $this->_profileArray;
	}
}
