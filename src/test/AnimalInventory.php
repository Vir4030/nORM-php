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

AnimalInventory::declareForeignKey(AnimalInventory::FK_ANIMAL_ANIMAL_ID, 'animal_id', 'Animal', true);
