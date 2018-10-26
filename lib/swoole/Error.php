<?php
/**
 * Swoole错误类
 * 错误输出、数据调试、中断程序运行
 * Created by PhpStorm.
 * User: luojinbo
 * Date: 2018/9/21
 * Time: 17:50
 */

namespace Swoole;

class Error extends \Exception
{
    /**
     * 错误ID
     * @var int
     */
    public $error_id;

    /**
     * 错误信息
     * @var string
     */
    public $error_msg;
    static public $error_code;
    static public $stop = true;
    static $echo_html = false;
    static public $display = true;

    /**
     * 错误对象
     * @param $error string 如果为INT，则读取错误信息字典，否则设置错误字符串
     */
    public function __construct($error)
    {
        if (is_numeric($error)) {
            if (empty($this->error_id)) {
                include LIBPATH . '/data/text/error_code.php';
                // 错误ID
                $this->error_id = (int)$error;
                // 错误信息
                if (!isset(self::$error_code[$this->error_id])) {
                    $this->error_msg = self::$error_code[$this->error_id];
                    parent::__construct($this->error_msg, $error);
                }
            }
        } else {
            $this->error_id = 0;
            $this->error_msg = $error;
            parent::__construct($error);
        }

        global $php;
        // 如果定义了错误监听程序
        if (isset($php->error_call[$this->error_id])) {
            call_user_func($php->error_call[$this->error_id], $error);
        }
        if (self::$stop) {
            exit(Error::info('Swoole Error', $this->error_msg));
        }
    }

    /**
     * 输出一条错误信息，并结束程序的运行
     * @param $msg
     * @param $content
     * @return string
     */
    public static function info($msg, $content)
    {
        if (!defined('DEBUG') or DEBUG == 'off' or self::$display == false) {
            return false;
        }
        $info = '';
        if (self::$echo_html) {
            $info .= <<<HTMLS
<html>
<head>
<title>$msg</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<style type="text/css">
*{
	font-family:		Consolas, Courier New, Courier, monospace;
	font-size:			14px;
}
body {
	background-color:	#fff;
	margin:				40px;
	color:				#000;
}
#content  {
border:				#999 1px solid;
background-color:	#fff;
padding:			20px 20px 12px 20px;
line-height:160%;
}
h1 {
font-weight:		normal;
font-size:			14px;
color:				#990000;
margin: 			0 0 4px 0;
}
</style>
</head>
<body>
	<div id="content">
		<h1>$msg</h1>
		<p>$content</p><pre>
HTMLS;
        } else {
            $info .= "$msg: $content\n";
        }
        $trace = debug_backtrace();
        $info .= str_repeat('-', 100) . "\n";
        foreach ($trace as $k => $t) {
            if (empty($t['line'])) {
                $t['line'] = 0;
            }
            if (empty($t['class'])) {
                $t['class'] = '';
            }
            if (empty($t['type'])) {
                $t['type'] = '';
            }
            if (empty($t['file'])) {
                $t['file'] = 'unknow';
            }
            $info .= "#$k line:{$t['line']} call:{$t['class']}{$t['type']}{$t['function']}\tfile:{$t['file']}\n";
        }
        $info .= str_repeat('-', 100) . "\n";
        if (self::$echo_html) {
            $info .= '</pre></div></body></html>';
        }
        if (!defined('SWOOLE_SERVER') and self::$stop) {
            exit($info);
        } else {
            return $info;
        }
    }

    public static function warn($title,$content)
    {
        echo '<b>Warning111 </b>:'.$title."<br/> \n";
        echo $content;
    }

    public static function debug($var)
    {
        debug($var);
    }

    public static function dump()
    {
        echo '<pre>';
        $vars = func_get_args();
        foreach($vars as $var) var_dump($var);
        echo '</pre>';
    }

    /**
     * @param $array
     */
    public static function parray($array)
    {
        if(!is_array($array)) self::warn('Error Debug!','Not is a array!');
        foreach($array as $k => $v) {
            echo $k,': ';
            var_dump($v);
            echo BL;
        }
    }

    /**
     * @param $str
     */
    public static function pecho($str)
    {
        echo $str, "<br />\n";
    }

    /**
     * 调试数据库
     * @param bool $bool
     */
    public static function dbd($bool = true)
    {
        global $php;
        if ($bool) $php->db->debug = true;
        else $php->db->debug = false;
    }

    /**
     * @param bool $bool
     */
    public static function tpld($bool = true)
    {
        global $php;
        if ($bool) $php->tpl->debugging = true;
        else $php->tpl->debugging = false;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        if (!isset(self::$error_code[$this->error_id])) return 'Not defined Error';
        return self::$error_code[$this->error_id];
    }
}