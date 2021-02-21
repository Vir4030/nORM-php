<?php
class DBLog {
	
	/**
	 * The string used to query the database.
	 * @var string
	 */
	private $_queryString;
	
	/**
	 * The time, in seconds, between the unix epoch and the start of the query.
	 * @var float
	 */
	private $_startTime = 0.0;

	/**
	 * The time, in seconds, between the unix epoch and the first record being returned from the query.
	 * The value is 0.0 if there is no split time.
	 * @var float
	 */
	private $_splitTime = 0.0;
	
	/**
	 * The time, in seconds, between the unix epoch and the final record being returned from the query.
	 * This value is 0.0 if the query did not successfully complete.
	 * @var float
	 */
	private $_completeTime = 0.0;
	
	/**
	 * Returns true if completed.
	 * 
	 * @var boolean
	 */
	private $_isComplete = false;
	
	/**
	 * The number of rows affected or returned.
	 * @var int
	 */
	private $_rowsAffected = 0;
	
	/**
	 * The error message generated by this query.  This value is null if there was no error.
	 * @var string
	 */
	private $_errorMessage = null;
	
	/**
	 * Creates a new DB Log entry for the given querystring.  The start time of the query is timestamped
	 * during this function call.  So this object should be created immediately before calling the function
	 * which executes the query.
	 * 
	 * @param string $queryString the query string being called
	 */
	public function __construct($queryString) {
		$this->_queryString = $queryString;
		$this->_startTime = microtime(true);
	}
	
	/**
	 * Timestamps the split time function, which should be called immediately after the query returns, if
	 * there is a resultset to be processed.  These times may be different if the resultset returns but only
	 * has a pointer to the data on the server side.  Then the client will be advancing the cursor during
	 * retrieval which may cause additional time to be requried.  This split time ensures that we also have the
	 * timing of the initial query in these cases.  For updates and inserts, this function is never called.
	 */
	public function split() {
		$this->_splitTime = microtime(true);
	}
	
	/**
	 * Sets the number of rows affected or returned by this query.
	 * 
	 * @param int $rowsAffected
	 */
	public function setRowsAffected($rowsAffected) {
		$this->_rowsAffected = $rowsAffected;
	}
	
	/**
	 * Timestamps the end of processing.  This method should be called when the resultset is freed in the case
	 * of a select statement or anything else which returns a resultset.  Otherwise, it should be called as soon
	 * as the query is completed.
	 */
	public function end() {
		$this->_isComplete = true;
		$this->_completeTime = microtime(true);
		if ($this->_splitTime == 0.0)
			$this->_splitTime = $this->_completeTime;
	}
	
	/**
	 * Signifies that an error has occurred with the given message.  This method should be called whenever an
	 * error occurs during an sql execution attempt.
	 * 
	 * @param string $message the database error message
	 */
	public function error($message) {
		$this->_errorMessage = $message;
	}
	
	/**
	 * Checks if this log represents a completed query.  A completed query is one where the end() method
	 * has been called on the log file.
	 * 
	 * @return boolean true if this query has completed
	 */
	public function isCompleted() {
		return $this->_isComplete;
	}
	
	/**
	 * Gets the query string logged in this log.
	 * 
	 * @return string the query string
	 */
	public function getQueryString() {
		return $this->_queryString;
	}
	
	/**
	 * Gets the complete time of execution for this query.  This includes all time spent process resultset
	 * records into objects.
	 * 
	 * @return number the number of seconds, with at least millisecond resolution
	 */
	public function getCompleteTime() {
		return $this->_completeTime - $this->_startTime;
	}
	
	/**
	 * Gets the time of execution for the query part of this query.  This does not include any time spent
	 * processing resultset records into objects.  As such, it may not represent the full time of the query.
	 * Mostly, it's here as informational purposes.
	 * 
	 * @return number the number of seconds, with at least millisecond resolution
	 */
	public function getSplitTime() {
		return $this->_splitTime - $this->_startTime;
	}
	
	/**
	 * Gets the error message that was generated during the execution of this query.
	 * 
	 * @return string the error message
	 */
	public function getErrorMessage() {
		return $this->_errorMessage ? $this->_errorMessage : 'No message. Possible unclosed resultset.';
	}
	
	/**
	 * Gets the number of rows affected or returned.
	 * 
	 * @return number the number of rows
	 */
	public function getRowsAffected() {
		return $this->_rowsAffected;
	}
}
