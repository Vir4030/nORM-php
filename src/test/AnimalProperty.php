<?php
class AnimalProperty extends DBEntity {
	
	protected static $_database = 'norm';
	
	protected static $_tableName = 'animal_property';
	
	protected static $_idField = array('animal_id', 'property_type_id');
	
	private static $_cacheByAnimalId = array();
	
	public static function cacheAll() {
		/* @var $animalProperty AnimalProperty */
		foreach (self::getAll() as $ap) {
			if (!isset(self::$_cacheByAnimalId[$ap->getAnimalId()])) {
				self::$_cacheByAnimalId[$ap->getAnimalId()] = array();
			}
			self::$_cacheByAnimalId[$ap->getAnimalId()][$ap->getPropertyTypeId()] = $ap;
		}
	}
	
	public static function clearAnimalIdCache() {
		self::$_cacheByAnimalId = array();
	}
	
	public static function getByAnimalId($animalId) {
		if (!isset(self::$_cacheByAnimalId[$animalId])) {
			self::$_cacheByAnimalId[$animalId] = self::getAll(array('animal_id' => $animalId), 'property_type_id', 'property_type_id');
		}
		return self::$_cacheByAnimalId[$animalId];
	}
	
	public function _setDefaultValues() {
		$this->set_on_date = new DateTime();
	}
	
	public function getTypeName() {
		return $this->getPropertyType()->getName();
	}
	
	public function getSetOnDate() {
		return $this->set_on_date;
	}
	
	public function getComment() {
		return $this->comment;
	}
	
	public function setComment($comment) {
		$this->comment = $comment;
	}
	
	private function getAnimalId() {
		return $this->animal_id;
	}
	
	private function getPropertyTypeId() {
		return $this->property_type_id;
	}
	
	/**
	 * Gets the property type object for this property.
	 * 
	 * @return AnimalPropertyType
	 */
	private function getPropertyType() {
		return AnimalPropertyType::getById($this->getPropertyTypeId());
	}
}
