<?php
class Animal extends DBEntity {
	
	protected static $_database = 'norm';
	
	protected static $_tableName = 'animal';
	
	private $_animalPropertyObjects = null;
	
	private $_animalProperties = null;
	
	/**
	 * Gets the AnimalProperty entities associated with this animal.
	 * 
	 * @return array[AnimalProperty]
	 *  the animal properties
	 */
	public function getAnimalPropertiesObjects() {
		if ($this->_animalPropertyObjects === null) {
			$this->_animalPropertyObjects = AnimalProperty::getByAnimalId($this->getId());
		}
		return $this->_animalPropertyObjects;
	}
	
	public function getAnimalProperties() {
		if ($this->_animalProperties === null) {
			$this->_animalProperties = array();
			foreach ($this->getAnimalPropertiesObjects() AS $ap) {
				$this->_animalProperties[$ap->getTypeName()] = $ap;
			}
		}
		return $this->_animalProperties;
	}
	
	public function getName() {
		return $this->name;
	}
	
	public function setName($name) {
		$this->name = $name;
	}
	
	public function getLegs() {
		return $this->legs;
	}
	
	public function setLegs($legs) {
		$this->legs = $legs;
	}
	
	public function incrementLegs() {
		$this->legs++;
	}
	
	public function getSound() {
		return $this->sound;
	}
	
	public function setSound($sound) {
		$this->sound = sound;
	}
	
	public function getDescription() {
		return $this->description;
	}
	
	public function setDescription($description) {
		$this->description = $description;
	}

	protected function _setDefaultValues() {
		$this->description = 'new animal ready to go';
	}
}
