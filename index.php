<?php

$config_file = 'config.php';

if (file_exists($config_file))
{
    require_once $config_file;
}
else
{
    die('缺少config.php文件, 请复制一份config-sample.php然后改名为config.php并修改里面的配置信息');
}

require_once 'lib/simple_pop_cdn.php';


set_time_limit(0);


 new Simple_PHP_CDN(ORIGIN_URL, CACHE_PATH, null);

//在url里添加force参数可以强制刷新

// $request_uri = $_SERVER['REQUEST_URI'];
// $request_part = parse_url($request_uri, PHP_URL_PATH);
// $request_info = pathinfo(urldecode($request_part));


// echo '<pre>';
// var_dump($request_uri);
// echo '<br>';
// var_dump($request_part);
// echo '<br>';
// var_dump($request_info);
// echo '</pre>';