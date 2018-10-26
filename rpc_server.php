<?php
/**
 * Created by PhpStorm.
 * User: luojinbo
 * Date: 2018/10/26
 * Time: 16:19
 */

define('DEBUG', 'on');
define('WEBPATH', realpath(__DIR__ . '/'));
require __DIR__ . '/lib/lib_config.php';

use  Swoole\Protocol\RPCServer;
Swoole\Network\Server::setPidFile(__DIR__ . '/rpc_server.pid');

/**
 * 显示Usage界面
 * php app_server.php start|stop|reload
 */
Swoole\Network\Server::start(function () {
    $AppSvr = new RPCServer;
    $AppSvr->setLogger(new \Swoole\Log\EchoLog(true));  // Logger

    /**
     * 注册一个自定义的命名空间到SOA服务器
     * 默认使用 apps/classes
     */
/*    $AppSvr->addNameSpace('BL', __DIR__ . '/class');*/


    /**
     * IP白名单设置
     */
    $AppSvr->addAllowIP('127.0.0.1');

    /**
     * 设置用户名密码
     */
    $AppSvr->addAllowUser('luojinbo', 'luojinbo@123456');

    Swoole\Error::$echo_html = false;

    $server = Swoole\Network\Server::autoCreate('0.0.0.0', 7012);
    $server->setProtocol($AppSvr);

    $server->run([
        // TODO： 实际使用中必须调大进程数
        'worker_num'            => 20,
        'max_request'           => 5000,
        'dispatch_mode'         => 3,
        'open_length_check'     => 1,
        'package_max_length'    => $AppSvr->packet_maxlen,
        'package_length_type'   => 'N',
        'package_body_offset'   => \Swoole\Protocol\RPCServer::HEADER_SIZE,
        'package_length_offset' => 0,
    ]);
});