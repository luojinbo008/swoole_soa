<?php
/**
 * Created by PhpStorm.
 * User: luojinbo
 * Date: 2018/10/31
 * Time: 11:03
 */

namespace Swoole\Coroutine\Component;

use Swoole\Coroutine\Context;
use Swoole\Coroutine\MySQL as CoMySQL;
use Swoole\IDatabase;
use Swoole\IDbRecord;

class MySQL extends Base implements IDatabase
{
    protected $type = 'mysql';

    public function __construct($config)
    {
        parent::__construct($config);
        \Swoole\Swoole::getInstance()->beforeAction([$this, '_createObject'], \Swoole\Swoole::coroModuleDb);
        \Swoole\Swoole::getInstance()->afterAction([$this, '_freeObject'], \Swoole\Swoole::coroModuleDb);
    }

    public function create()
    {
        $db = new CoMySQL;
        if ($db->connect($this->config) === false) {
            return false;
        } else {
            return $db;
        }
    }

    public function query($sql)
    {
        /**
         * @var $db CoMySQL
         */
        $db = $this->_getObject();
        if (!$db) {
            return false;
        }

        $result = false;
        for ($i = 0; $i < 2; $i++) {
            $result = $db->query($sql);
            if ($result === false) {
                $db->close();
                Context::delete($this->type);
                $db = $this->_createObject();
                continue;
            }
            break;
        }
        return new MySQLRecordSet($result);
    }

    public function quote($val)
    {
        /**
         * @var $db CoMySQL
         */
        $db = $this->_getObject();
        if (!$db) {
            return false;
        }

        return $db->escape($val);
    }

    public function lastInsertId()
    {
        /**
         * @var $db CoMySQL
         */
        $db = $this->_getObject();
        if (!$db) {
            return false;
        }

        return $db->insert_id;
    }

    public function getAffectedRows()
    {
        /**
         * @var $db CoMySQL
         */
        $db = $this->_getObject();
        if (!$db) {
            return false;
        }
        return $db->affected_rows;
    }

    public function errno()
    {
        /**
         * @var $db CoMySQL
         */
        $db = $this->_getObject();
        if (!$db) {
            return -1;
        }
        return $db->errno;
    }

    public function close()
    {

    }

    public function connect()
    {

    }
}

class MySQLRecordSet implements IDbRecord
{
    public $result;

    public function __construct($result)
    {
        $this->result = $result;
    }

    public function fetch()
    {
        return isset($this->result[0]) ? $this->result[0] : null;
    }

    public function fetchall()
    {
        return $this->result;
    }

    public function __get($key)
    {
        return $this->result->$key;
    }
}