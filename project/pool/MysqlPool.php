<?php

namespace   pool;
use Swoole;
use Swoole\Coroutine as co;

/*
 * go(function (){//协程内的ip操作
    $mysql=MysqlPool::getInstance()->getConnect();
    var_dump($mysql);
    MysqlPool::getInstance()->free($mysql);
});
 * */

class MysqlPool {

    static private $instance;

    protected $db;
    protected $pool;
    protected $size;
    protected $listName;
    protected $wait_time;

    public function __construct() {
        $this->size=50;
        $this->host = "127.0.0.1";
        $this->port =  6379;
        $this->wait_time=10;

        require_once  dirname(dirname(__FILE__))."/config/DBconfig.php";

        $this->db=$configDb;
        $this->pool = new Swoole\Coroutine\Channel($this->size);

    }

    public function init(){//初始化mysql的数量

        for ($i=0;$i<$this->size;$i++){
            go(function (){
                $swoole_mysql = new co\MySQL();
                $swoole_mysql->connect($this->db);
                $this->pool->push($swoole_mysql);
            });

        }
        echo "初始化mysql的数目为".$this->size;
    }

    public static  function getInstance(){

        if(is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function  free($obj){
        $this->pool->push($obj);
    }

    public function getConnect(){
        $obj=false;

        if ($this->pool->isEmpty()){//连接池为空
            $obj= $this->pool->pop($this->wait_time);
        }else{
            $obj= $this->pool->pop();
        }


        return $obj;
    }

}