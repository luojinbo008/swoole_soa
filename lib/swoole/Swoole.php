<?php
/**
 * Created by PhpStorm.
 * User: luojinbo
 * Date: 2018/9/21
 * Time: 18:18
 */
namespace Swoole;

// 加载核心的文件
require_once __DIR__ . '/Loader.php';
require_once __DIR__ . '/ModelLoader.php';

use Swoole\Exception\NotFound;

class Swoole
{
    public static $app_path;

    public $config;

    /**
     * 可使用的组件
     */
    static public $modules = [
        'db' => true,  // 数据库
    ];

    /**
     * 允许多实例的模块
     * @var array
     */
    static public $multi_instance = [
        'cache' => true,
        'db'    => true,
    ];

    static $charset             = 'utf-8';
    static $debug               = false;

    /**
     * @var bool
     */
    static $enableCoroutine         = false;
    protected static $coroutineInit = false;

    const coroModuleDb              = 1;
    const coroModuleRedis           = 2;
    const coroModuleCache           = 3;

    /**
     * 是否缓存 echo 输出
     * @var bool
     */
    static $enableOutputBuffer      = true;

    static $setting                 = [];
    public $error_call              = [];

    /**
     * Swoole类的实例
     * @var Swoole
     */
    static public $php;
    public $pagecache;

    /**
     * 命令
     * @var array
     */
    protected $commands = [];

    /**
     * 捕获异常
     */
    protected $catchers = [];

    /**
     * 对象池
     * @var array
     */
    protected $objects = [];

    /**
     * 传给factory
     */
    public $factory_key = 'master';

    /**
     * 发生错误时的回调函数
     */
    public $error_callback;

    public $load;

    /**
     * @var ModelLoader
     */
    public $model;
    public $env;

    protected $hooks = [];
    protected $router_function;

    const HOOK_INIT             = 1;    // 初始化
    const HOOK_ROUTE            = 2;    // URL路由
    const HOOK_CLEAN            = 3;    // 清理
    const HOOK_BEFORE_ACTION    = 4;
    const HOOK_AFTER_ACTION     = 5;

    private function __construct($appDir = '') {

        if (!defined('DEBUG')) define('DEBUG', 'on');

        $this->env['sapi_name'] = php_sapi_name();

        if ($this->env['sapi_name'] != 'cli') {
            Error::$echo_html = true;
        }

        if (!empty($appDir)) {
            self::$app_path = $appDir;
        } elseif (defined('APPSPATH')) {
            self::$app_path = APPSPATH;
        } elseif (defined('WEBPATH')) {
            self::$app_path = WEBPATH . '/apps';
            define('APPSPATH', self::$app_path);
        }

        if (empty(self::$app_path)) {
            Error::info("core error", __CLASS__ . ": Swoole::\$app_path and APPPATH empty.");
        }

        // 将此目录作为App命名空间的根目录
        Loader::addNameSpace('App', self::$app_path . '/classes');

        $this->load = new Loader($this);
        $this->model = new ModelLoader($this);
        $this->config = new Config;
        $this->config->setPath(self::$app_path . '/configs');
    }

    /**
     * 初始化
     * @return Swoole
     */
    public static function getInstance()
    {
        if (!self::$php) {
            self::$php = new Swoole;
        }
        return self::$php;
    }

    /**
     * 获取资源消耗
     * @return array
     */
    public function runtime()
    {
        // 显示运行时间
        $return['time'] = number_format((microtime(true)-$this->env['runtime']['start']),4).'s';

        $startMem =  array_sum(explode(' ',$this->env['runtime']['mem']));
        $endMem   =  array_sum(explode(' ',memory_get_usage()));
        $return['memory'] = number_format(($endMem - $startMem)/1024).'kb';
        return $return;
    }

    /**
     * 压缩内容
     * @return null
     */
    public function gzip()
    {
        // 不要在文件中加入UTF-8 BOM头
        // ob_end_clean();
        ob_start("ob_gzhandler");
        // 是否开启压缩
        if (function_exists('ob_gzhandler')) {
            ob_start('ob_gzhandler');
        } else {
            ob_start();
        }
    }

    /**
     * 初始化环境
     * @return null
     */
    public function __init()
    {
        // DEBUG
        if (defined('DEBUG') and strtolower(DEBUG) == 'on') {
            // 记录运行时间和内存占用情况
            $this->env['runtime']['start'] = microtime(true);
            $this->env['runtime']['mem'] = memory_get_usage();

            // 使用whoops美化错误页面
            if (class_exists('\\Whoops\\Run')) {
                $whoops = new \Whoops\Run;
                if ($this->env['sapi_name'] == 'cli') {
                    $whoops->pushHandler(new \Whoops\Handler\PlainTextHandler());
                } else {
                    $whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler);
                }
                $whoops->register();
            }
        }
        $this->callHook(self::HOOK_INIT);
    }

    /**
     * @param $func
     * @return mixed
     */
    public static function go($func)
    {
        $app = self::getInstance();

        if (!self::$coroutineInit) {
            if (Swoole::$enableCoroutine === false) {
                throw new \RuntimeException("Swoole::\$enableCoroutine cannot be false.");
            }
            $app->loadAllModules();
        }

        return \Swoole\Coroutine::create(function () use ($func, $app) {
            $app->callHook(self::HOOK_INIT);
            $app->callHook(self::HOOK_BEFORE_ACTION);
            $func();
            $app->callHook(self::HOOK_AFTER_ACTION);
            $app->callHook(self::HOOK_CLEAN);
        });
    }

    /**
     * 执行Hook函数列表
     * @param $type
     * @param $subtype
     */
    public function callHook($type,$subtype = false)
    {
        if ($subtype and isset($this->hooks[$type][$subtype])) {
            foreach ($this->hooks[$type][$subtype] as $f) {
                if (!is_callable($f)) {
                    trigger_error("SwooleFramework: hook function[$f] is not callable.");
                    continue;
                }
                $f();
            }
        } elseif (isset($this->hooks[$type])) {
            foreach ($this->hooks[$type] as $f) {
                // has subtype
                if (is_array($f) and !is_callable($f)) {
                    foreach ($f as $subtype => $ff) {
                        if (!is_callable($ff)) {
                            trigger_error("SwooleFramework: hook function[$ff] is not callable.");
                            continue;
                        }
                        $ff();
                    }
                } else {
                    if (!is_callable($f)) {
                        trigger_error("SwooleFramework: hook function[$f] is not callable.");
                        continue;
                    }
                    $f();
                }
            }
        }
    }

    /**
     * 清理
     */
    public function __clean()
    {
        $this->env['runtime'] = [];
        $this->callHook(self::HOOK_CLEAN);
    }

    /**
     * 增加钩子函数
     * @param $type
     * @param $func
     * @param $prepend bool
     * @param $subtype bool
     */
    public function addHook($type, $func, $prepend = false, $subtype = false)
    {
        if ($subtype) {
            if ($prepend) {
                array_unshift($this->hooks[$type][$subtype], $func);
            } else {
                $this->hooks[$type][$subtype][] = $func;
            }
        } else {
            if ($prepend) {
                array_unshift($this->hooks[$type], $func);
            } else {
                $this->hooks[$type][] = $func;
            }
        }
    }

    /**
     * 清理钩子程序
     * @param $type
     */
    public function clearHook($type = 0)
    {
        if ($type == 0) {
            $this->hooks = array();
        } else {
            $this->hooks[$type] = array();
        }
    }

    /**
     * 在请求之前执行一个函数
     * @param callable $callback
     */
    public function beforeRequest(callable $callback)
    {
        $this->addHook(self::HOOK_INIT, $callback);
    }

    /**
     * 在请求之后执行一个函数
     * @param callable $callback
     */
    public function afterRequest(callable $callback)
    {
        $this->addHook(self::HOOK_CLEAN, $callback);
    }

    /**
     * 在Action执行前回调
     * @param callable $callback
     * @param mixed $subtype
     */
    public function beforeAction(callable $callback, $subtype = false)
    {
        $this->addHook(self::HOOK_BEFORE_ACTION, $callback, false, $subtype);
    }

    /**
     * 在Action执行后回调
     * @param callable $callback
     * @param mixed $subtype
     */
    public function afterAction(callable $callback, $subtype = false)
    {
        $this->addHook(self::HOOK_AFTER_ACTION, $callback, false, $subtype);
    }

    /**
     * @param $lib_name
     * @return mixed
     */
    public function __get($lib_name)
    {
        // 如果不存在此对象，从工厂中创建一个
        if (empty($this->$lib_name)) {
            // 载入组件
            $this->$lib_name = $this->loadModule($lib_name);
        }
        return $this->$lib_name;
    }

    /**
     * 加载内置的Swoole模块
     * @param $module
     * @param $id
     * @throws NotFound
     * @return mixed
     */
    protected function loadModule($module, $id = 'master')
    {
        $key = $module . '_' . $id;
        if (empty($this->objects[$key])) {
            $this->factory_key = $id;
            $user_factory_file = self::$app_path . '/factory/' . $module . '.php';

            // 尝试从用户工厂构建对象
            if (is_file($user_factory_file)) {
                $object = require $user_factory_file;
            } elseif (self::$enableCoroutine) { // Swoole 2.0 协程模式
                $system_factory_2x_file = LIBPATH . '/factory_2x/' . $module . '.php';

                // 不存在，继续使用 1.x 的工厂
                if (!is_file($system_factory_2x_file)) {
                    goto get_factory_file;
                }
                $object = require $system_factory_2x_file;
            } else {
                // 系统默认
                get_factory_file: $system_factory_file = LIBPATH . '/factory/' . $module . '.php';
                // 组件不存在，抛出异常
                if (!is_file($system_factory_file)) {
                    throw new NotFound("module [$module] not found.");
                }
                $object = require $system_factory_file;
            }
            $this->objects[$key] = $object;
        }
        return $this->objects[$key];
    }

    /**
     * 卸载的Swoole模块
     * @param $module
     * @param $object_id
     * @throws NotFound
     * @return bool
     */
    public function unloadModule($module, $object_id = 'all')
    {
        // 卸载全部
        if ($object_id == 'all') {
            // 清除配置
            if (isset($this->config[$module])) {
                unset($this->config[$module]);
            }
            $find = false;
            foreach($this->objects as $key => $object) {
                list($name, $id) = explode('_', $key, 2);
                // 找到了此模块
                if ($name === $module) {
                    $this->unloadModule($module, $id);
                    $find = true;
                }
            }
            return $find;
        } else {    // 卸载某个对象
            // 清除配置
            if (isset($this->config[$module][$object_id])) {
                unset($this->config[$module][$object_id]);
            }
            $key = $module.'_'.$object_id;
            if (empty($this->objects[$key])) {
                return false;
            }
            $object = $this->objects[$key];
            // 存在close方法，自动调用
            if (is_object($object) and method_exists($object, 'close')) {
                call_user_func(array($object, 'close'));
            }
            // 删除对象
            unset($this->objects[$key]);
            // master
            if ($object_id == 'master') {
                $this->{$module} = null;
            }
            return true;
        }
    }

    /**
     * @param $func
     * @param $param
     * @return mixed
     */
    public function __call($func, $param)
    {
        // swoole built-in module
        if (isset(self::$multi_instance[$func])) {
            if (empty($param[0]) or !is_string($param[0])) {
                throw new \Exception("module name cannot be null.");
            }
            return $this->loadModule($func, $param[0]);
        } elseif(is_file(self::$app_path . '/factory/' . $func . '.php')) { // 尝试加载用户定义的工厂类文件
            $object_id = $func . '_' . $param[0];
            // 已创建的对象
            if (isset($this->objects[$object_id])) {
                return $this->objects[$object_id];
            } else {
                $this->factory_key = $param[0];
                $object = require self::$app_path . '/factory/' . $func . '.php';
                $this->objects[$object_id] = $object;
                return $object;
            }
        } else {
            throw new \Exception("call an undefine method[$func].");
        }
    }

    /**
     * 设置应用程序路径
     * @param $dir
     */
    public static function setAppPath($dir)
    {
        if (is_dir($dir)) {
            self::$app_path = $dir;
        } else {
            Error::info("fatal error", "app_path[$dir] is not exists.");
        }
    }


    /**
     * 加载所有模块
     */
    public function loadAllModules()
    {
        // db
        $db_conf = $this->config['db'];
        if (!empty($db_conf)) {
            foreach ($db_conf as $k => $v) {
                $this->loadModule('db', $k);
            }
        }
    }

    /**
     * @param callable $catcher
     * @param bool $persistent
     */
    public function addCatcher(callable $catcher, $persistent = false)
    {
        $this->catchers[] = ['handler' => $catcher, 'persistent' => $persistent];
    }
}