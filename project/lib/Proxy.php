<?php
namespace  lib;
require_once  dirname(dirname(__FILE__))."/pool/RedisPool.php";
use Swoole\Coroutine as co;
use pool\RedisPool;
use Swoole;
ini_set("display_errors", 0);
error_reporting(E_ALL ^ E_NOTICE);
error_reporting(E_ALL ^ E_WARNING);

//基于阿里云买的代理ip 100元/10w次
//获取代理ip的方式 getProxyIp
//释放FreeProxyIp
//创建连接池CreateProxyIp

//go(function (){//协程内的ip操作
//        $ProxyIp=new ProxyIp();
//        $Ip=$ProxyIp->getProxyIp();
//        $ProxyIp->FreeProxyIp($Ip);
//});

class ProxyIp{

        private   $IpProxyListName;
        private    $pushMethod;
        private    $popMethod;
        private    $AliServiceCode;

        public  function __construct()
        {
            $this->IpProxyListName=="IpProxyList";
            $this->pushMethod=="lpush";
            $this->popMethod=="rpop";

        }

    public function init(){//维持在最少30个没过期  过期时间 30分钟
        $this->CreateProxyIp();
//        $this->CheckProxyIpActive();
//        $this->CreateProxyIp();
        echo "代理ip连接池初始化成功";
    }

    public function CheckAllIp(){
        $this->CheckProxyIpNum();
    }


    public function killAllIp(){


            go(function (){
                $redis=RedisPool::getInstance()->getConnect();
                if (!$redis || empty($redis)){
                    var_dump("redis连接失败");
                    return false;
                }
                $ipLenth=$redis->llen("IpProxyList");
                for ($i=0;$i<$ipLenth;$i++){
                    go(function(){
                        $redisz=RedisPool::getInstance()->getConnect();
                        $ipData=$redisz->rpop("IpProxyList");
                        var_dump($ipData."成功释放");
                        $res=RedisPool::getInstance()->free($redisz);
                    });
                }


                $res=RedisPool::getInstance()->free($redis);
                var_dump($res);
            });

    }


    public function  getProxyIp(){

            $redis=RedisPool::getInstance()->getConnect();
            if (!$redis || empty($redis)){
                return false;
            }

            if ($redis->llen("IpProxyList")>0){
                $ipData= $redis->lpop("IpProxyList");


            }else{//没链接了
                $this->CreateProxyIp();
               $ipData= $redis->lpop("IpProxyList");



            }

        return json_decode($ipData,true);


    }

    public function  free($array){//返回的是数组
        if (!is_array($array)) return false;
        if (time()-$array["create_time"]>36000) return false;

            $redis=RedisPool::getInstance()->getConnect();
            if (!$redis || empty($redis)){
                return false;
            }
           $redis->rpush("IpProxyList",json_encode($array));


    }

    private  function CheckProxyIpNum(){
        Swoole\Timer::tick(100, function() {

            go(function (){
                $redis=RedisPool::getInstance()->getConnect();
                if (!$redis || empty($redis)){
                    var_dump("redis连接失败");
                    return false;
                }
                $ipData=$redis->rpop("IpProxyList");
                $ipDataJson=json_decode($ipData,true);
                if (time()-$ipDataJson["create_time"]<600){
                    $redis->lpush("IpProxyList",($ipData));
                    var_dump($ipData."成功放回代理Ip池");
                }
                $res=RedisPool::getInstance()->free($redis);
                var_dump($res);
            });
        });
    }



    public function  CreateProxyIp(){
        go(function (){
            $redis=RedisPool::getInstance()->getConnect();
            if (!$redis || empty($redis)){
                var_dump("redis连接失败");
                return false;
            }

            $ProxyData=$this->getIpService();


            $ProxyData=json_decode($ProxyData,true);

            if ($ProxyData["error_code"] == 0){

                foreach($ProxyData["result"] as $k=>$v){
                    $needPushData["ip"]=$v;
                    $needPushData["create_time"]=time();
                    $needPushData= json_encode($needPushData);
                    $redis->rpush("IpProxyList",($needPushData));
                    unset($needPushData);
                }
            }else{
                return false;
            }
        });
    }


    public function getIpService()
    {
        $host = "http://iphighproxyv2.haoservice.com";
        $path = "/devtoolservice/ipagency";
        $method = "GET";
        $appcode = "";//阿里云代理ip服务的code code
        $headers = array();
        array_push($headers, "Authorization:APPCODE " . $appcode);
        $querys = "foreigntype=1&protocol=0";
        $bodys = "";
        $url = $host . $path . "?" . $querys;

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        if (1 == strpos("$".$host, "https://"))
        {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }
        $res=(curl_exec($curl));
        return $res;
    }
}

