<?php

namespace Horseloft\Core\Drawer;

use Horseloft\Core\Attic\Initialize;
use Horseloft\Core\Attic\Stater;
use Horseloft\Core\Attic\Container;

class Server
{
    use Container,Stater,Initialize;

    /**
     * @var int
     */
    protected $connectTime;

    /**
     * @var \Swoole\Http\Server
     */
    protected $server;

    /**
     * @var \Horseloft\Core\Drawer\Building
     */
    protected $container;

    /**
     * Server constructor.
     * @param string $applicationPath
     * @throws \Exception
     */
    public function __construct(string $applicationPath)
    {
        $applicationPath = rtrim($applicationPath, '/');
        if (version_compare(phpversion(), '7.1.0', '<')) {
            exit('PHP-版本不能低于7.1.0');
        }
        if (!extension_loaded('swoole')) {
            exit('PHP-Swoole扩展不存在');
        }
        if (floatval(phpversion('swoole')) < 4.4) {
            exit('PHP-Swoole扩展的版本不能低于4.4');
        }
        if (!is_dir($applicationPath)) {
            exit('无效的applicationPath');
        }
        if (!is_dir($applicationPath . '/Config')) {
            exit('配置文件目录缺失');
        }
        if (!is_file($applicationPath . '/env.ini')) {
            exit('env.ini文件不存在，可以重命名env.ini.example为env.ini');
        }
        $this->container = $this->container();
        $this->initialize($applicationPath);
    }

    /**
     * --------------------------------------------------------------------------
     * 启动服务器
     * --------------------------------------------------------------------------
     *
     */
    final public function start()
    {
        //设置配置项
        $this->server->set($this->container->getSwooleConfig());

        // 毫秒定时器
        $this->timerStarter();

        // 用户自定义进程
        $this->processStater();

        // 定时任务
        $this->crontabStarter();

        //服务信息展示
        Spanner::cliPrint('start -> ' . $this->container->getHost() . ':' . $this->container->getPort());

        //Swoole启动
        if (!$this->server->start()) {
            exit('server start fail');
        }
    }

    /**
     * --------------------------------------------------------------------------
     * 设置服务的必须环境参数
     * --------------------------------------------------------------------------
     *
     * @param string $applicationPath
     * @throws \Exception
     */
    private function initialize(string $applicationPath)
    {
        // 设置服务的应用路径
        $this->container->setApplicationPath($applicationPath);

        // 设置请求指向的命名空间
        $this->container->setNamespace('Application');

        // 设置服务配置文件路径
        $this->container->setConfigDir($applicationPath . '/Config');

        // 设置服务端口号 - 日志存储路径 等 Env
        $this->readAndSetEnv($applicationPath);

        // 读取配置文件 并加入容器
        $this->readSetConfig();

        // 读取拦截器配置 并加入容器
        $this->readSetInterceptor();

        // 读取路由配置 并加入容器
        $this->readSetRoute();
    }
}
