<?php

require_once ( '../../vendor/autoload.php');
use QL\QueryList;
use QL\Ext\PhantomJs;
require_once dirname(dirname(__FILE__))."/lib/Proxy.php";
require_once dirname(dirname(__FILE__))."/pool/RedisPool.php";
require_once dirname(dirname(__FILE__))."/lib/Pinyin.php";
use lib\ProxyIp;
use pool\RedisPool;
use lib\Pinyin;

$RedisPool=RedisPool::getInstance();
$RedisPool->init();
$pinyin = new Pinyin();



$data=array(
    "https://toutiao.io/subjects/25390?",
    "https://toutiao.io/subjects/67913?",
    "https://toutiao.io/subjects/132007?",
    "https://toutiao.io/subjects/58560?",
    "https://toutiao.io/subjects/4?",
);

foreach ($data as $key => $value) {
  

go(function () use($pinyin,$value){

    $url=$value;

    try{

     

        $returnData = QueryList::get($url,[
            'f' => 'new',         
        ],[
            'timeout' => 30000,    
        ]) ->rules([
            
            'title' => array('.content>.title>a', 'html'),
            'link' => array('.content>.title>a', 'href'),

    

        ]) ->queryData(function ($item) {
            $item["link"] = "https://toutiao.io/" . $item["link"];
            return $item;
        });

        foreach ($returnData as $k => $v) {//处理数据放入队列


            go(function() use($v,$pinyin){
             $swooleRedis = RedisPool::getInstance()->getConnect();

                $unique_str=$pinyin->getPinyin($v['title']);

                $SqlCollectionList=date("Y:m").":Articles:UniqueStrlists";
                if(!$swooleRedis->sismember($SqlCollectionList,$unique_str)) {//如果不是他的成员 则允许发送
                    $create_time = date("Y-m-d H:i:s", time());
                    $sql = "INSERT INTO article_list ( title , link   , create_time ,laiyuan ,unique_str) VALUES";
                    $sql .= "( '{$v['title']}' , '{$v['link']}'   ,'{$create_time}' , 'Toutiao' ,'{$unique_str}')";

                    $res = $swooleRedis->rpush("SqlInsertList", $sql);
                    var_dump($res);
                    $swooleRedis->sadd($SqlCollectionList,$unique_str);

                }else{
                   $dataStr=date("Y-m-d H:i:s")."--".$unique_str."已经采集";
                    var_dump($dataStr);
                }
                RedisPool::getInstance()->free($swooleRedis);
            });

        }

    }catch (e $e){
        echo "代理失败";

    }

});


}