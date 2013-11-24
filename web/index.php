<?php
require('dbconfig.php');
require('../src/include_test.php');

AnimalPropertyType::cacheAll();
AnimalProperty::cacheAll();

$animals = Animal::getAll();
$animalInventories = AnimalInventory::getAll();

/* @var $animal Animal */
foreach ($animals as $id => $animal) {
	echo($id.': '.$animal->getName().' ('.$animal->getLegs().') '.(isset($animalInventories[$id]) ? ' with ' . $animalInventories[$id]->getQoh() : '')."<br>");
	/* @var $ap AnimalProperty */
	foreach ($animal->getAnimalProperties() AS $ap) {
		echo(' - ' . $ap->getTypeName());
		if ($ap->getComment()) {
			echo(' = '. $ap->getComment());
		}
		echo('<br>');
	}
	$animal->incrementLegs();
	$animal->save();
}
