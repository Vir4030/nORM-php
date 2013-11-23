<?php
require('dbconfig.php');
require('../src/include_test.php');

$iterations = 10000;
$start = round(microtime(true) * 1000);
/* @var $animals array[Animal] */
for ($x = 0; $x < $iterations; $x++)
	$animals = Animal::getAll();
$end = round(microtime(true) * 1000);

$time = 1000 * ($end - $start) / $iterations;

echo("total time: ".$time."us / ".count($animals)." records = ".($time / count($animals))."us/rec<br>");

$pa = Animal::getStore()->getProfileArray();
foreach ($pa as $profile => $millis) {
	echo($profile.' = '.$millis.'ms<br>');
}
