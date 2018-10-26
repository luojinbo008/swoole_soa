<?php
global $php;
$configs = $php->config['db'];
if (empty($configs[$php->factory_key])) {
    throw new Swoole\Exception\Factory("db->{$php->factory_key} is not found.");
}

$config = $configs[$php->factory_key];
$db = new Swoole\Database($config);
$db->debug =  true;
$db->connect();
return $db;
