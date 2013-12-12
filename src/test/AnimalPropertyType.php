<?php
class AnimalPropertyType extends DBEntity {
	
	protected static $_database = 'norm';
	
	protected static $_tableName = 'animal_property_type';
	
	public function getName() {
		return $this->name;
	}
}
