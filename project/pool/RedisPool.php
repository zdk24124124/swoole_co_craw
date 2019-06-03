<?php
namespace   pool;
use Swoole;

/*
 * go(function (){//协程内的ip操作
    $connect=RedisPool::getInstance()->getConnect();
    RedisPool::getInstance()->free($connect);
});
 * */

class RedisPool {

    protected $host;
    protected $port;
    protected $pool;
    protected $size;
    protected $listName;
    protected $wait_time;

    static private $instances;

   public function __construct() {
        $this->size=50;
        $this->host = "127.0.0.1";
        $this->port =  6379;
        $this->wait_time=10;

        $this->pool = new Swoole\Coroutine\Channel($this->size);

    }

    public function init(){
        for ($i = 0; $i < $this->size; $i++) {
            go(function(){
                $redis = new Swoole\Coroutine\Redis();
                $res = $redis->connect($this->host, $this->port);

                if ($res) {
                    $this->pool->push($redis);
                } else {
                    throw new RuntimeException("Redis connect error [$this->host] [$this->port]");
                }
            });

        }
        echo "Redis连接池初始化成功";


    }

    public function  free($obj){
       return $this->pool->push($obj);
    }

    public function getConnect(){
        $obj=false;
        echo $this->pool->length();
        if ($this->pool->isEmpty()){//连接池为空
            $obj= $this->pool->pop($this->wait_time);
        }else{
            $obj= $this->pool->pop();
        }


        return $obj;
    }




    static public function getInstance() {
        if(is_null(self::$instances)){
            self::$instances = new self();
        }
        return self::$instances;
    }


//    public function __call($func, $args) {
//        $ret = null;
//        try {
//            $redis = $this->pool->pop();
//            $ret = call_user_func_array(array($redis, $func), $args);
//            if ($ret === false) {
//
//                //重连一次
//                $redis->close();
//                $res = $redis->connect($this->host, $this->port);
//                if (!$res) {
//                    throw new RuntimeException("Redis reconnect error [{$this->host}][{$this->port}]");
//                }
//                $ret = call_user_func_array(array($redis, $func), $args);
//                if ($ret === false) {
//                    throw new RuntimeException("redis error after reconnect");
//                }
//            }
//            $this->pool->push($redis);
//        } catch (Exception $e) {
//            $this->pool->push($redis);
//
//            throw new RuntimeException("Redis catch exception [".$e->getMessage()."] [$func]");
//        }
//        return $ret;
//    }
}
