<?php

set_time_limit(0);

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


//如果检测到特殊参数
if (isset($_REQUEST['delete_empty_files']))
{
    //删除缓存目录里的空文件
    delete_empty_files(CACHE_PATH);
    echo '已清理缓存空文件';
}
else if (isset($_REQUEST['delete_timeout_files']))
{
    //删除缓存目录里的过期文件
    delete_timeout_files(CACHE_PATH);
    echo '已清理缓存过期的文件';
}
else
{
    new Simple_PHP_CDN(ORIGIN_URL, CACHE_PATH, null);
}



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