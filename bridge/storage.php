<?php
require('./mystorage.php');

$cfg = array('dbTable' => 'mystorage', 'maxCreateVars' => 1000, 'maxVarNameSize' => 100, 'executeLimit' => 50);
$cfg['db'] = array('charset' => 'utf8', 'host' => '127.0.0.1', 'dbname' => 'main', 'user' => 'root', 'pass' => '');

$data = isset($_POST) ? $_POST : null;

$storage = new MyStorage($data, $cfg);

$storage->addAdmin('vk000');

$storage->addApp('vk000', 'защищённый_ключ');

$storage->run();
?>