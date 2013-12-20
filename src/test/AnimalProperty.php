<?php
class AnimalProperty extends DBEntity {
	
	protected static $_database = 'norm';
	
	protected static $_tableName = 'animal_property';
	
	protected static $_idField = array('animal_id', 'property_type_id');
	
	const FK_ANIMAL_ANIMAL_ID = 'FK_Animal_Property_Animal_Id';
	
	const FK_ANIMAL_PROPERTY_TYPE_PROPERTY_TYPE_ID = 'FK_ANIMAL_PROPERTY_PROPERTY_TYPE_ID';
	
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

AnimalProperty::declareForeignKey(AnimalProperty::FK_ANIMAL_ANIMAL_ID, 'animal_id', 'Animal');
AnimalProperty::declareForeignKey(
	AnimalProperty::FK_ANIMAL_PROPERTY_TYPE_PROPERTY_TYPE_ID, 'property_type_id', 'AnimalPropertyType');
