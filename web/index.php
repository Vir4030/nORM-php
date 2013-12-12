<?php
$start = microtime(true);
require('dbconfig.php');
require('../src/include_test.php');

AnimalPropertyType::getAll();
AnimalProperty::getAll();

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

/* @var $db DBConnection */
$db = DBConnection::get('norm');
echo('=== Query Log ===<br>');
foreach ($db->getQueryLog() as $queryLog) {
	if ($queryLog->isCompleted())
		echo($queryLog->getQueryString() . '<br> - completed in ' . number_format($queryLog->getCompleteTime() * 1000.0, 2) . 'ms');
	else
		echo($queryLog->getQueryString() . '<br> - error: ' . $queryLog->getErrorMessage());
	echo('<br>');
}
$end = microtime(true);
echo('<b>Processing completed in ' . number_format(1000.0 * ($end - $start), 2) . 'ms</b>');
