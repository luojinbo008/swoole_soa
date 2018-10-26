<?php

/**
 * Created by PhpStorm.
 * User: luojinbo
 * Date: 2018/10/8
 * Time: 14:22
 */
namespace Swoole\Network;

use Swoole\Server\Base;
use Swoole\Server\Driver;

class Server extends Base implements Driver
{
    protected static $beforeStopCallback;
    protected static $beforeReloadCallback;

    public static $swooleMode;
    public static $useSwooleHttpServer = false;
    public static $optionKit;
    public static $pidFile;

    public static $defaultOptions = [
        'd|daemon'  => '启用守护进程模式',
        'h|host?'   => '指定监听地址',
        'p|port?'   => '指定监听端口',
        'help'      => '显示帮助界面',
        'b|base'    => '使用BASE模式启动',
        'w|worker?' => '设置Worker进程的数量',
        'r|thread?' => '设置Reactor线程的数量',
        't|tasker?' => '设置Task进程的数量',
    ];

    /**
     * @var \swoole_server
     */
    protected $sw;
    protected $pid_file;
    static public $swoole;

    /**
     * 设置PID文件
     * @param $pidFile
     */
    public static function setPidFile($pidFile)
    {
        self::$pidFile = $pidFile;
    }

    /**
     * 杀死所有进程
     * @param $name
     * @param int $signo
     * @return string
     */
    public static function killProcessByName($name, $signo = 9)
    {
        $cmd = 'ps -eaf |grep "' . $name . '" | grep -v "grep"| awk "{print $2}"|xargs kill -' . $signo;
        return exec($cmd);
    }

    /**
     *
     * $opt->add( 'f|foo:' , 'option requires a value.' );
     * $opt->add( 'b|bar+' , 'option with multiple value.' );
     * $opt->add( 'z|zoo?' , 'option with optional value.' );
     * $opt->add( 'v|verbose' , 'verbose message.' );
     * $opt->add( 'd|debug'   , 'debug message.' );
     * $opt->add( 'long'   , 'long option name only.' );
     * $opt->add( 's'   , 'short option name only.' );
     *
     * @param $specString
     * @param $description
     * @throws ServerOptionException
     */
    public static function addOption($specString, $description)
    {
        if (!self::$optionKit) {
            \Swoole\Loader::addNameSpace('GetOptionKit', LIBPATH . '/module/GetOptionKit/src/GetOptionKit');
            self::$optionKit = new \GetOptionKit\GetOptionKit;
        }
        foreach (self::$defaultOptions as $k => $v) {
            if ($k[0] == $specString[0]) {
                throw new ServerOptionException("不能添加系统保留的选项名称");
            }
        }
        self::$optionKit->add($specString, $description);
    }

    /**
     * @param callable $function
     */
    public static function beforeStop(callable $function)
    {
        self::$beforeStopCallback = $function;
    }

    /**
     * @param callable $function
     */
    public static function beforeReload(callable $function)
    {
        self::$beforeReloadCallback = $function;
    }

    /**
     * 显示命令行指令
     */
    public static function start($startFunction)
    {
        if (empty(self::$pidFile)) {
            throw new \Exception("require pidFile.");
        }
        $pid_file = self::$pidFile;
        if (is_file($pid_file)) {
            $server_pid = file_get_contents($pid_file);
        } else {
            $server_pid = 0;
        }

        if (!self::$optionKit) {
            \Swoole\Loader::addNameSpace('GetOptionKit', LIBPATH . '/module/GetOptionKit/src/GetOptionKit');
            self::$optionKit = new \GetOptionKit\GetOptionKit;
        }

        $kit = self::$optionKit;
        foreach(self::$defaultOptions as $k => $v) {
            $kit->add($k, $v);
        }

        global $argv;
        $opt = $kit->parse($argv);
        if (empty($argv[1]) or isset($opt['help'])) {
            goto usage;
        } elseif ($argv[1] == 'reload') {
            if (empty($server_pid)) {
                exit("Server is not running");
            }
            if (self::$beforeReloadCallback) {
                call_user_func(self::$beforeReloadCallback, $opt);
            }

            \Swoole\Swoole::$php->os->kill($server_pid, SIGUSR1);
            exit;
        } elseif ($argv[1] == 'stop') {
            if (empty($server_pid)) {
                exit("Server is not running\n");
            }
            if (self::$beforeStopCallback) {
                call_user_func(self::$beforeStopCallback, $opt);
            }
            \Swoole\Swoole::$php->os->kill($server_pid, SIGTERM);
            exit;
        } elseif ($argv[1] == 'start') {

            // 已存在ServerPID，并且进程存在
            if (!empty($server_pid) and \Swoole\Swoole::$php->os->kill($server_pid, 0)) {
                exit("Server is already running.\n");
            }
        } else {
            usage:
            $kit->specs->printOptions("php {$argv[0]} start|stop|reload");
            exit;
        }
        self::$options = $opt;
        $startFunction($opt);
    }

    /**
     * @param $host
     * @param $port
     * @param bool $ssl
     * @return Server
     */
    public static function autoCreate($host, $port, $ssl = false)
    {
        return new self($host, $port, $ssl);
    }

    public function __construct($host, $port, $ssl = false)
    {
        $flag = $ssl ? (SWOOLE_SOCK_TCP | SWOOLE_SSL) : SWOOLE_SOCK_TCP;
        if (!empty(self::$options['base'])) {
            self::$swooleMode = SWOOLE_BASE;
        } elseif (extension_loaded('swoole')) {
            self::$swooleMode = SWOOLE_PROCESS;
        }

        if (self::$useSwooleHttpServer) {
            $this->sw = new \swoole_http_server($host, $port, self::$swooleMode, $flag);
        } else {
            $this->sw = new \swoole_server($host, $port, self::$swooleMode, $flag);
        }

        $this->host = $host;
        $this->port = $port;
        \Swoole\Error::$stop = false;

        $this->runtimeSetting = [
            'backlog'       => 128,        // listen backlog
        ];
    }

    public function daemonize()
    {
        $this->runtimeSetting['daemonize'] = 1;
    }

    public function connections()
    {
        return $this->sw->connections;
    }

    public function connection_info($fd)
    {
        return $this->sw->connection_info($fd);
    }

    public function onMasterStart($serv)
    {
        \Swoole\Console::setProcessName($this->getProcessName() . ': master -host=' . $this->host . ' -port=' . $this->port);
        if (!empty($this->runtimeSetting['pid_file'])) {
            file_put_contents(self::$pidFile, $serv->master_pid);
        }
        if (method_exists($this->protocol, 'onMasterStart')) {
            $this->protocol->onMasterStart($serv);
        }
    }

    public function onMasterStop($serv)
    {
        if (!empty($this->runtimeSetting['pid_file'])) {
            unlink(self::$pidFile);
        }

        if (method_exists($this->protocol, 'onMasterStop')) {
            $this->protocol->onMasterStop($serv);
        }
    }

    public function onManagerStop()
    {

    }

    public function onWorkerStart($serv, $worker_id)
    {
        /**
         * 清理Opcache缓存
         */
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        /**
         * 清理APC缓存
         */
        if (function_exists('apc_clear_cache')) {
            apc_clear_cache();
        }

        if ($worker_id >= $serv->setting['worker_num']) {
            \Swoole\Console::setProcessName($this->getProcessName() . ': task');
        } else {
            \Swoole\Console::setProcessName($this->getProcessName() . ': worker');
        }

        if (method_exists($this->protocol, 'onStart')) {
            $this->protocol->onStart($serv, $worker_id);
        }
        if (method_exists($this->protocol, 'onWorkerStart')) {
            $this->protocol->onWorkerStart($serv, $worker_id);
        }
    }

    public function run($setting = [])
    {
        $this->runtimeSetting = array_merge($this->runtimeSetting, $setting);
        if (self::$pidFile) {
            $this->runtimeSetting['pid_file'] = self::$pidFile;
        }
        if (!empty(self::$options['daemon'])) {
            $this->runtimeSetting['daemonize'] = true;
        }
        if (!empty(self::$options['worker'])) {
            $this->runtimeSetting['worker_num'] = intval(self::$options['worker']);
        }
        if (!empty(self::$options['thread'])) {
            $this->runtimeSetting['reator_num'] = intval(self::$options['thread']);
        }
        if (!empty(self::$options['tasker'])) {
            $this->runtimeSetting['task_worker_num'] = intval(self::$options['tasker']);
        }
        $this->sw->set($this->runtimeSetting);
        $this->sw->on('ManagerStart', function ($serv) {
            \Swoole\Console::setProcessName($this->getProcessName() . ': manager');
        });
        $this->sw->on('Start', [$this, 'onMasterStart']);
        $this->sw->on('Shutdown', [$this, 'onMasterStop']);
        $this->sw->on('ManagerStop', [$this, 'onManagerStop']);
        $this->sw->on('WorkerStart', [$this, 'onWorkerStart']);

        if (is_callable([$this->protocol, 'onConnect'])) {
            $this->sw->on('Connect', [$this->protocol, 'onConnect']);
        }
        if (is_callable([$this->protocol, 'onClose'])) {
            $this->sw->on('Close', [$this->protocol, 'onClose']);
        }
        if (self::$useSwooleHttpServer) {
            $this->sw->on('Request', [$this->protocol, 'onRequest']);
        } else {
            $this->sw->on('Receive', [$this->protocol, 'onReceive']);
        }
        if (is_callable([$this->protocol, 'WorkerStop'])) {
            $this->sw->on('WorkerStop', [$this->protocol, 'WorkerStop']);
        }

        // swoole-1.8已经移除了onTimer回调函数
        if (version_compare(SWOOLE_VERSION, '1.8.0') < 0) {
            if (is_callable([$this->protocol, 'onTimer'])) {
                $this->sw->on('Timer', [$this->protocol, 'onTimer']);
            }
        }

        if (is_callable([$this->protocol, 'onTask'])) {
            $this->sw->on('Task', [$this->protocol, 'onTask']);
            $this->sw->on('Finish', [$this->protocol, 'onFinish']);
        }
        self::$swoole = $this->sw;
        $this->sw->start();
    }

    public function shutdown()
    {
        return $this->sw->shutdown();
    }

    public function close($client_id)
    {
        return $this->sw->close($client_id);
    }

    /**
     * @param $protocol
     * @throws \Exception
     */
    public function setProtocol($protocol)
    {
        if (self::$useSwooleHttpServer) {
            $this->protocol = $protocol;
        } else {
            parent::setProtocol($protocol);
        }
    }

    public function send($client_id, $data)
    {
        return $this->sw->send($client_id, $data);
    }

    public static function task($data,$func)
    {
        $params = [
            'func' => $func,
            'data' => $data,
        ];
        self::$swoole->task($params);
    }

    public function __call($func, $params)
    {
        return call_user_func_array([$this->sw, $func], $params);
    }
}

class ServerOptionException extends \Exception
{

}