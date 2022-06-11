<?php

require_once 'simple_pop_cdn.php';


set_time_limit(0);

//回源地址
$url = 'https://static.mikuclub.cc';

//主文件夹
$cache_path = './cache';

//缓存文件回源请求间隔, 用来确保缓存是正确, 12小时 
$cache_expire =60 * 60 * 12;
//$cache_expire = 0;


new SimplePOPCDN($url,$cache_path, null, $cache_expire);


