<?php
/**
 * Created by PhpStorm.
 * User: luojinbo
 * Date: 2018/10/8
 * Time: 12:50
 */

namespace Swoole\Exception;


class InvalidParam extends \Exception
{
    const ERROR_REQUIRED            = 1000;
    const ERROR_TYPE_INCORRECTLY    = 1001;
    const ERROR_USER_DEFINED        = 1002;

    public $key;
}