<?php

//回源地址
define('ORIGIN_URL', 'https://www.abc.com');

//缓存文件主路径
define('CACHE_PATH', './cache');

//服务端回源文件的过期时间
define('CACHE_EXPIRY', 60 * 60 * 24);
//客户端缓存文件过期时间
define('CLIENT_CACHE_EXPIRY', 60 * 60 * 24 * 30 * 3);

//默认支持缓存的文件夹路径
define('ARRAY_ACCEPTED_DIRECTORY', [
    'wp-includes',
    'wp-content',
    'wp-admin',
    'img',
]);

//默认支持缓存的文件
define('ARRAY_ACCEPTED_EXTENSION', [
    'jpeg',
    'jpg',
    'png',
    'gif',
    'webp',
    'jfif',
    'bmp',
    'tif',
    'ico',
    'js',
    'css',
    'html',
    'htm',
    'xml',
    'kml',
    'json',
    'txt',
    'eot',
    'svg',
    'otf',
    'ttf',
    'woff',
    'woff2',
]);
