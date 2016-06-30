<?php
namespace pwframe\lib\frame;

use Exception;
use ReflectionClass;
use pwframe\lib\frame\ioc\WebApplicationContext;
use pwframe\lib\frame\exception\ApplicationException;

class Application {
    
    private $autoloadExtension = '.php'; // 文件后缀
    private $rootNamespace = 'pwframe\\'; // 根命名空间
    private $webDirectory = 'web'; // Web目录
    private $configDirectory = 'config'; // 配置文件目录
    private $appUri, $appUrl, $appPath, $rootPath;
    private $applicationConfig, $webApplicationContext;
    
    public function getAutoloadExtension(){
        return $this->autoloadExtension;
    }

    public function getRootNamespace() {
        return $this->rootNamespace;
    }

    public function getWebDirectory() {
        return $this->webDirectory;
    }

    public function getConfigDirectory() {
        return $this->configDirectory;
    }

    public function getAppUri() {
        return $this->appUri;
    }

    public function getAppUrl() {
        return $this->appUrl;
    }

    public function getAppPath() {
        return $this->appPath;
    }

    public function getRootPath() {
        return $this->rootPath;
    }

    public function getApplicationConfig() {
        return $this->applicationConfig;
    }

    public function __construct($appUri, $rootPath) {
        if(empty($appUri)) {
            $appUri = '/';
        } else {
            if('/' != substr($appUri, 0, 1)) $appUri = '/'.$appUri;
            if('/' != substr($appUri, -1)) $appUri .= '/';
        }
        $this->appUri = $appUri;
        $this->appUrl = $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST'];
        if(80 != $_SERVER['SERVER_PORT']) $this->appUrl .= ':'.$_SERVER['SERVER_PORT'];
        $this->appPath = $this->appUrl.$this->appUri;
        if(DIRECTORY_SEPARATOR != substr($rootPath, -1)) $rootPath .= DIRECTORY_SEPARATOR;
        $this->rootPath = substr($rootPath, 0, strlen($rootPath) - strlen($this->webDirectory) - 1);
        $this->init();
    }
    
    private function init() {
        date_default_timezone_set('Asia/Shanghai');
        if(defined('DEBUG') && true === DEBUG) {
            ini_set('display_errors', 'On');
            error_reporting(E_ALL);
        } else {
            ini_set('display_errors', 'Off');
            error_reporting(0);
        }
        spl_autoload_extensions($this->autoloadExtension);
        spl_autoload_register(array($this, 'autoload'));
        $this->applicationConfig = require_once $this->rootPath.$this->configDirectory.DIRECTORY_SEPARATOR.'application.config.php';
        $this->webApplicationContext = WebApplicationContext::getInstance();
        $this->webApplicationContext->setBean(self::class, function () {
            return $this;
        });
    }
    
    public function autoload($className) {
        $rootLength = strlen($this->rootNamespace);
        if(strlen($className) < $rootLength) {
            throw new ApplicationException('class namespace error!');
        }
        $className = substr($className, $rootLength);
        $classPath = $this->rootPath.str_replace('\\', DIRECTORY_SEPARATOR, $className).$this->autoloadExtension;
        if(!is_file($classPath)) {
            throw new ApplicationException('can not found class file![className:'.$className.',classPath:'.$classPath.']');
        }
        require_once $classPath;
    }
    
    public function run() {
        $route = Router::getInstance()->parse($this->appUri, $this->applicationConfig);
        try {
            if(null == $route) throw new Exception('uriError');
            $this->processInvoke($route, null, 0);
        } catch (Exception $e) {
            $route['controller'] = $this->applicationConfig['defaultErrorController'];
            $route['action'] = $this->applicationConfig['defaultErrorAction'];
            try {
                $this->processInvoke($route, $e, 0);
            } catch (Exception $e1) {
                $this->proessError($route, $e1, 1);
            }
        }
    }
    
    private function processInvoke($route, $e, $count) {
        $className = $this->rootNamespace.$this->applicationConfig['controllerNamePath']
            .'\\'.$route['module']."\\controller".'\\'
            .ucfirst($route['controller']).$this->applicationConfig['defaultControllerSuffix'];
        $instance = $this->webApplicationContext->getBean($className);
        $controller = new ReflectionClass($instance);
        $instance->setAppUrl($this->appUrl);
        $instance->setAppUri($this->appUri);
        $instance->setAppPath($this->appPath);
        $instance->setRootPath($this->rootPath);
        $instance->setModuleName($route['module']);
        $instance->setControllerName($route['controller']);
        $instance->setActionName($route['action']);
        $instance->setParams($_REQUEST);
        $instance->setAssign(array());
        $initVal = $instance->init();
		if (null !== $initVal) {
			$this->proessError($route, new Exception("initError"), $count);
			return;
		}
		$action = $controller->getMethod($route['action'].$this->applicationConfig['defaultActionSuffix']);
		if (null == $e) {
		    $actionVal = $action->invoke($instance);
		} else {
		    $actionVal = $action->invoke($instance, $e);
		}
		$destroyVal = $instance->destroy($actionVal);
		if (null != $destroyVal) {
			$this->proessError($route, new Exception("destroyError"), $count);
			return;
		}
    }
    
    private function proessError($route, $e, $count) {
        if ($count > 0) {
            throw new Exception('route:'.implode(',', $route), 0, $e);
		}
		$route['controller'] = $this->applicationConfig['defaultErrorController'];
		$route['action'] = $this->applicationConfig['defaultErrorAction'];
		try {
			$this->processInvoke($route, $e, $count++);
		} catch (Exception $e1) {
			$this->proessError($route, new Exception("proessError"), $count++);
		}
    }
    
}
