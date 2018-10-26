<?php
$configs = \Swoole\Swoole::$php->config['cache'];
if (empty($configs[\Swoole\Swoole::$php->factory_key])) {
    throw new Swoole\Exception\Factory("cache->".\Swoole\Swoole::$php->factory_key." is not found.");
}
return Swoole\Factory::getCache(\Swoole\Swoole::$php->factory_key);