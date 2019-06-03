<?php
function curls($url, $data_string) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
          'Accept: */*',  
        'Accept-Encoding: gzip, deflate, br',  
        'Accept-Language:zh-CN,zh;q=0.8',  
        'Connection:keep-alive',  
        'Content-Length:198',  
        'Content-Type:application/json',  
        'Host:web-api.juejin.im',  
        'Origin:https://juejin.im',  
        'Referer:https://juejin.im/timeline/backend',  
        'User-Agent:Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36 SE 2.X MetaSr 1.0',  
        'X-Agent:Juejin/Web',  
        'X-Legacy-Device-Id:',  
        'X-Legacy-Token:',  
        'X-Legacy-Uid:' ) 
);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

$url="https://web-api.juejin.im/query";
$params='{"operationName":"","query":"","variables":{"tags":[],"category":"5562b419e4b00c57d9b94ae2","first":20,"after":"","order":"POPULAR"},"extensions":{"query":{"id":"653b587c5c7c8a00ddf67fc66f989d42"}}}';


$data=curls($url,$params);

$data=json_decode($data,true);


if (empty($data)) {
	sleep(3);
	$data=curls($url,$params);

	$data=json_decode($data,true);
	if (empty($data)) {
		return false;
	}
}



require_once dirname(dirname(__FILE__))."/pool/RedisPool.php";
use lib\ProxyIp;
use pool\RedisPool;


$RedisPool=RedisPool::getInstance();
$RedisPool->init();


$needData=$data["data"]["articleFeed"]["items"]["edges"];



foreach ($needData as $key => $v) {
	    go(function() use($v){
             $swooleRedis = RedisPool::getInstance()->getConnect();

                $unique_str=$v["node"]["id"];

                $SqlCollectionList=date("Y:m").":Articles:UniqueStrlists";
                if(!$swooleRedis->sismember($SqlCollectionList,$unique_str)) {//如果不是他的成员 则允许发送
                    $create_time = date("Y-m-d H:i:s", time());
                    $sql = "INSERT INTO article_list ( title , link   , create_time ,laiyuan ,unique_str) VALUES";
                    $sql .= "( '{$v['node']['title']}' , '{$v['node']['originalUrl']}'   ,'{$create_time}' , 'Juejin' ,'{$unique_str}')";

                    $res = $swooleRedis->rpush("SqlInsertList", $sql);
                    var_dump($sql);
                    $swooleRedis->sadd($SqlCollectionList,$unique_str);

                }else{
                    $dataStr=date("Y-m-d H:i:s")."--".$unique_str."已经采集";
                    var_dump($dataStr);
                }
                RedisPool::getInstance()->free($swooleRedis);
            });
}