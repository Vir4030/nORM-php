<?php
class AnimalPropertyType extends DBEntity {
	
	protected static $_database = 'norm';
	
	protected static $_tableName = 'animal_property_type';
	
	private static $_propertyTypeCache = array();
	
	public static function cacheAll() {
		/* @var $apt AnimalPropertyType */
		foreach (self::getAll() AS $apt) {
			self::$_propertyTypeCache[$apt->getId()] = $apt;
		}
	}
	
	public static function getById($id) {
		if (!isset(self::$_propertyTypeCache[$id])) {
			self::$_propertyTypeCache[$id] = self::get($id);
		}
		return self::$_propertyTypeCache[$id];
	}
	
	public function getName() {
		return $this->name;
	}
}
