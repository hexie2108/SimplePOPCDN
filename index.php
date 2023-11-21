<?php

$config_file = 'config.php';

if (file_exists($config_file))
{
    require_once $config_file;
}
else
{
    die('缺少config.php文件');
}

require_once 'lib/simple_pop_cdn.php';


set_time_limit(0);


new SimplePOPCDN(ORIGIN_URL, CACHE_PATH, null, CACHE_EXPIRY);

//在url里添加force参数可以强制刷新
