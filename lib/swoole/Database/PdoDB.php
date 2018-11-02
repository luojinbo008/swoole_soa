<?php
/**
 * PDO数据库封装类
 * Created by PhpStorm.
 * User: luojinbo
 * Date: 2018/9/21
 * Time: 16:30
 */

namespace Swoole\Database;
use Swoole;

class PdoDB extends \PDO implements Swoole\IDatabase
{
    public $debug = false;

    protected $config;

    /**
     * @var \PDOStatement
     */
    protected $lastStatement;

    public function __construct($db_config)
    {
        $this->config = $db_config;
    }

    /**
     * 连接到数据库
     */
    public function connect()
    {
        $db_config = &$this->config;
        $dsn = $db_config['dbms'] . ":host=" . $db_config['host'] . ";dbname=" . $db_config['name'];
        if (!empty($db_config['persistent'])) {
            parent::__construct($dsn, $db_config['user'], $db_config['passwd'], array(\PDO::ATTR_PERSISTENT => true));
        } else {
            parent::__construct($dsn, $db_config['user'], $db_config['passwd']);
        }
        if ($db_config['setname']) {
            parent::query('set names ' . $db_config['charset']);
        }
        $this->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    /**
     * 执行一个SQL语句
     * @param string $sql
     * @return bool|\PDOStatement
     */
    public final function query($sql)
    {
        if ($this->debug) {
            echo "$sql<br />\n<hr />";
        }
        $res = parent::query($sql) or \Swoole\Error::info(
            "SQL Error",
            implode(", ", $this->errorInfo()) . "<hr />$sql"
        );
        $this->lastStatement = $res;

        // 非查询语句直接返回结果
        if ($sql[0] !== 's') {
            return !empty($res);
        }
        return $res;
    }

    public final function ping()
    {
        try {
            $this->getAttribute(\PDO::ATTR_SERVER_INFO);
        } catch (\PDOException $e) {
            if(strpos($e->getMessage(), 'MySQL server has gone away') !== false){
                return false;
            }
        }
        return true;
    }

    /**
     * 执行一个参数化SQL语句,并返回一行结果
     * @param string $sql 执行的SQL语句
     * @param  mixed $_     [optional]
     * @return mixed
     */
    public final function queryLine($sql, $_)
    {
        $params = func_get_args();
        if ($this->debug) {
            var_dump($params);
        }
        array_shift($params);
        $stm = $this->prepare($sql);
        if ($stm->execute($params)) {
            $ret = $stm->fetch();
            $stm->closeCursor();
            return $ret;
        } else {
            \Swoole\Error::info("SQL Error", implode(", ", $this->errorInfo()) . "<hr />$sql");
            return false;
        }
    }

    /**
     * 执行一个参数化SQL语句,并返回所有结果
     * @param string $sql 执行的SQL语句
     * @param  mixed $_     [optional]
     * @return mixed
     */
    public final function queryAll($sql, $_)
    {
        $params = func_get_args();
        if ($this->debug) {
            var_dump($params);
        }
        array_shift($params);
        $stm = $this->prepare($sql);
        if ($stm->execute($params)) {
            $ret = $stm->fetchAll();
            $stm->closeCursor();
            return $ret;
        } else {
            \Swoole\Error::info("SQL Error", implode(", ", $this->errorInfo()) . "<hr />$sql");
            return false;
        }
    }

    /**
     * 执行一个参数化SQL语句
     * @param $sql
     * @param $_
     * @return bool|string
     */
    public final function execute($sql, $_)
    {
        $params = func_get_args();
        if ($this->debug) {
            var_dump($params);
        }
        array_shift($params);
        $stm = $this->prepare($sql);
        if ($stm->execute($params)) {
            return $this->lastInsertId();
        } else {
            \Swoole\Error::info("SQL Error", implode(", ", $this->errorInfo()) . "<hr />$sql");
            return false;
        }
    }

    /**
     * 获取错误码
     */
    public function errno()
    {
        $this->errorCode();
    }

    /**
     * 获取受影响的行数
     * @return int
     */
    public function getAffectedRows()
    {
        return $this->lastStatement ? $this->lastStatement->rowCount() : false;
    }

    /**
     * 关闭连接，释放资源
     * @return null
     */
    public function close()
    {

        //unset($this);
        return;
    }

    public function quote($str, $parameter_type = \PDO::PARAM_STR)
    {
        $safeStr = parent::quote($str);
        return substr($safeStr, 1, strlen($safeStr) - 2);
    }
}