<?php
namespace pwframe\lib\frame\database;

use \PDO;

class MySQLConnection extends Connection {
    
    private static $instance;
    private $resourceArray = array();
    
    private function __construct() {}
    
    public static function getInstance() {
        if(null == self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getResource($isMaster = false, $dbName = null, $charset = null) {
        $config = $this->loadConfig('mysql');
        if(empty($config)) return null; // 配置不存在
        if($isMaster) {
            $msKey = 'master';
            if(!isset($config[$msKey]) || empty($config[$msKey])) return null; // 主节点配置不存在
            $config = $config[$msKey];
        } else {
            $msKey = 'slaves';
            if(!isset($config[$msKey]) || empty($config[$msKey])) return null; // 从节点配置不存在
            $config = $config[$msKey][array_rand($config[$msKey])];
        }
        if(empty($config)) return null;
        if(empty($dbName)) $dbName = $config['dbname'];
        if(empty($charset)) $charset = $config['charset'];
        $key = '___'.$msKey.'___'.$dbName.'___'.$charset;
        if(!isset($this->resourceArray[$key])) {
            if(empty($config['options'])) $config['options'] = [
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ];
            $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$this->dbname}";
            if (!empty($this->charset)) $dsn .= ';charset='.$charset;
            $this->resourceArray[$key] = new PDO($dsn, $config['port'], $config['port'], $config['options']);
        }
        return $this->resourceArray[$key];
    }
    
}