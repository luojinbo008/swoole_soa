<?php
/**
 * 基本函数，全局对象$php的构造
 */
if (PHP_OS == 'WINNT') {
    die("windows system not access this server！");
}

define("LIBPATH", __DIR__);
define("NL", "\n");
define("BL", "<br />" . NL);

require_once __DIR__ . '/swoole/Swoole.php';
require_once __DIR__ . '/swoole/Loader.php';

Swoole\Loader::addNameSpace('Swoole', __DIR__ . '/swoole');
spl_autoload_register('\\Swoole\\Loader::autoload');


/**
 * 产生类库的全局变量
 */
global $php;
$php = Swoole\Swoole::getInstance();
/**
 * 调试数据，终止程序的运行
 */
function debug()
{
    $vars = func_get_args();
    foreach ($vars as $var) {
        if (php_sapi_name() == 'cli') {
            var_export($var);
        } else {
            highlight_string("<?php\n" . var_export($var, true));
            echo '<hr />';
        }
    }
    exit;
}

/**
 * 引发一个错误
 * @param $error_id
 * @param $stop
 */
function error($error_id, $stop = true)
{
    global $php;
    $error = new Swoole\Error($error_id);
    if (isset($php->error_call[$error_id])) {
        call_user_func($php->error_call[$error_id], $error);
    } elseif ($stop) {
        exit($error);
    } else {
        echo $error;
    }
}

/**
 * 错误信息输出处理
 */
function swoole_error_handler($errno, $errstr, $errfile, $errline)
{
    $info = '';
    switch ($errno) {
        case E_USER_ERROR:
            $level = 'User Error';
            break;
        case E_USER_WARNING:
            $level = 'Warnning';
            break;
        case E_USER_NOTICE:
            $level = 'Notice';
            break;
        default:
            $level = 'Unknow';
            break;
    }

    $title = 'Swoole '.$level;
    $info .= '<b>File:</b> '.$errfile."<br />\n";
    $info .= '<b>Line:</b> '.$errline."<br />\n";
    $info .= '<b>Info:</b> '.$errstr."<br />\n";
    $info .= '<b>Code:</b> '.$errno."<br />\n";
    echo Swoole\Error::info($title, $info);
}