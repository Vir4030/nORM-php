<?php
class Animal extends DBEntity {
	
	protected static $_database = 'norm';
	
	protected static $_tableName = 'animal';
	
	public function getAnimalProperties() {
		return $this->_getOwnedObjects('AnimalProperty', AnimalProperty::FK_ANIMAL_ANIMAL_ID);
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
