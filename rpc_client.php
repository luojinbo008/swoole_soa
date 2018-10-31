<?php
define('DEBUG', 'on');
define('WEBPATH', dirname(__DIR__));

require __DIR__ . '/lib/lib_config.php';


$s1 = microtime(true);

foreach (xrange(1, 1) as $num) {
    $client = Swoole\Client\RPC::getInstance();

    $client->setEncodeType(Swoole\Protocol\RPCServer::DECODE_JSON, false);

    $client->auth('luojinbo', 'luojinbo@123456');


    $client->addServers(array('host' => '127.0.0.1', 'port' => 7012));

    $s = microtime(true);

    $ret1 = $client->task("BL\\User::info", ["areaid" => 1, "numid" => 57022644], function ($obj) use ($num, $s) {
        var_dump($obj->data);
        echo "$num : use " . (microtime(true) - $s) * 1000, "ms\n";
    });

    $n = $client->wait(0.5); // 500ms超时
    unset($client, $ret1);
}

echo "total use " . (microtime(true) - $s1) * 1000, "ms\n";

function xrange($start, $end, $step = 1) {
    for ($i = $start; $i <= $end; $i += $step) {
        yield $i;
    }
}
