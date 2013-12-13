<?php
class AnimalInventory extends DBEntity {
	
	protected static $_database = 'norm';
	
	protected static $_tableName = 'animal_inventory';
	
	protected static $_idField = 'animal_id';
	
	protected static $_foreignKeys = array(
		'Animal' => 'animal_id'
	);
	
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
