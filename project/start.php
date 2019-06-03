<?php

ini_set("display_errors", 0);
error_reporting(E_ALL ^ E_NOTICE);
error_reporting(E_ALL ^ E_WARNING);

require_once dirname(__FILE__)."/lib/Proxy.php";
require_once dirname(__FILE__)."/pool/RedisPool.php";
require_once dirname(__FILE__)."/pool/MysqlPool.php";

use lib\ProxyIp;

use pool\RedisPool;
use pool\MysqlPool;


class Crontab
{


    public function __construct() {
        $this->serv = new swoole_server("127.0.0.1", 9550);
        $this->serv->set(array(
            'worker_num' => 1,
            'daemonize' => true,
            'max_request' => 10000,
            'dispatch_mode' => 2,
            'debug_mode'=> 1 ,
        ));

        $this->serv->on('WorkerStart', array($this, 'onWorkerStart'));
        $this->serv->on('Receive', array($this, 'onReceive'));
        $this->serv->start();
    }

    public function onWorkerStart($serv) {

        $RedisPool=RedisPool::getInstance();
        $RedisPool->init();
      
        $MysqlPool=MysqlPool::getInstance();
        $MysqlPool->init();


         Swoole\Timer::tick(1000, function() {
            go(function (){

                       $swoole_mysql=MysqlPool::getInstance()->getConnect();
                        $res = $swoole_mysql->query("select * from con_status where id = 1");
                        var_dump("维护mysql的连接");
                        MysqlPool::getInstance()->free($swoole_mysql);       
             
            });
        });


        Swoole\Timer::tick(400, function() {
            go(function (){
                $swooleRedis=RedisPool::getInstance()->getConnect();
                echo "剩余数量为".$swooleRedis->llen("SqlInsertList");
                if ($swooleRedis->llen("SqlInsertList")>0){
                    $data= $swooleRedis->lpop("SqlInsertList");

                    var_dump($data);

                    if (strlen($data)>30){

                        $swoole_mysql=MysqlPool::getInstance()->getConnect();
                        $res = $swoole_mysql->query($data);
                        var_dump($res);
                        MysqlPool::getInstance()->free($swoole_mysql);
                    }else{
                        var_dump("非法数据");
                    }



                }else{
                    var_dump("没有数据 等待数据");

                }
                $res=RedisPool::getInstance()->free($swooleRedis);
                echo "回收结果".$res;
            });
        });


    }



    public function onReceive( swoole_server $serv, $fd, $from_id, $data ) {
        echo "Get Message From Client {$fd}:{$data}\n";
    }







}

new Crontab();