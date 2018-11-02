<?php

/**
 * Created by PhpStorm.
 * User: luojinbo
 * Date: 2018/10/31
 * Time: 11:04
 */
namespace Swoole\Coroutine;

use Swoole\Coroutine;

class Context
{
    protected static $pool = [];

    public static function get($type)
    {
        $cid = Coroutine::getuid();
        if ($cid < 0) {
            return false;
        }
        return self::$pool[$type][$cid];
    }

    public static function put($type, $object)
    {
        $cid = Coroutine::getuid();
        if ($cid < 0) {
            return;
        }
        self::$pool[$type][$cid] = $object;
    }

    public static function delete($type)
    {
        $cid = Coroutine::getuid();
        if ($cid < 0) {
            return;
        }
        unset(self::$pool[$type][$cid]);
    }
}