<?php 
require('../src/include.php');

//DBConnection::register('norm', DBConnection::create(DBConnection::TYPE_MYSQL, 'localhost', 'norm', 'norm', 3306, 'norm'));
DBConnection::register('norm', DBConnection::create(DBConnection::TYPE_MYSQL, 'srv-dev-65', 'script', 'op', 3306, 'erickr'));
