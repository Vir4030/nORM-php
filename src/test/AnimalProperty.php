<?php
class AnimalProperty extends DBEntity {
	
	protected static $_database = 'norm';
	
	protected static $_tableName = 'animal_property';
	
	protected static $_idField = array('animal_id', 'property_type_id');
	
	public function _setDefaultValues() {
		$this->set_on_date = new DateTime();
	}
	
	public function getPropertyType() {
		return AnimalPropertyType::get($this->getPropertyTypeId());
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
}
