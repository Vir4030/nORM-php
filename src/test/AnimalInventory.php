<?php
class AnimalInventory extends DBEntity {
	
	protected static $_database = 'norm';
	
	protected static $_tableName = 'animal_inventory';
	
	protected static $_idField = 'animal_id';
	
	const FK_ANIMAL_ANIMAL_ID = 'FK_Animal_Inventory_Animal_Id';
	
	public function getQoh() {
		return $this->qoh;
	}
	
	public function setQoh($qoh) {
		$this->qoh = $qoh;
	}
	
	public function addQoh($qoh) {
		$this->qoh += $qoh;
	}
	
	public function getLastIntoStock() {
		return $this->last_into_stock;
	}
	
	public function setLastIntoStock($lastIntoStock) {
		$this->last_into_stock = $lastIntoStock;
	}
}

DBEntity::initForeignKeys(array(
		new DBForeignKey(AnimalProperty::FK_ANIMAL_ANIMAL_ID, 'Animal', 'id', 'AnimalProperty', 'animal_id'),
));
