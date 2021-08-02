<?php
$NORM_CLASS_RACK = array(
		'DBConnection'		=>	'norm/DBConnection.php',
		'DBEntity'			=>	'norm/DBEntity.php',
		'DBField'			=>	'norm/DBField.php',
		'DBForeignKey'		=>	'norm/DBForeignKey.php',
		'DBLog'				=>	'norm/DBLog.php',
		'DBQuery'			=>	'norm/DBQuery.php',
		'DBStore'			=>	'norm/DBStore.php',
        'NormJS'            =>  'norm/js/NormJS.php',
		'MSSQLConnection'	=>	'norm/db/MSSQLConnection.php',
		'MySQLConnection'	=>	'norm/db/MySQLConnection.php',
);

function autoload_norm($class) {
	global $NORM_CLASS_RACK;
	if (isset($NORM_CLASS_RACK[$class]))
		include $NORM_CLASS_RACK[$class];
}

spl_autoload_register('autoload_norm');

date_default_timezone_set('UTC');
