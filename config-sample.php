<?php

//回源地址
define('ORIGIN_URL', 'https://www.abc.com');

//是否要自定义回源域名IP, 来绕过DNS服务器封锁
define('CUSTOM_ORIGIN_IP', '');

//默认的回源端口
define('CUSTOM_ORIGIN_GATEWAY_PORT', 443);

//缓存文件主路径
define('CACHE_PATH', './cache');

//服务端回源文件的过期时间
define('CACHE_EXPIRY', 60 * 60 * 24);
//客户端缓存文件过期时间
define('CLIENT_CACHE_EXPIRY', 60 * 60 * 24 * 30 * 3);

//是否强制把JPG PNG BMP图片转换为WEBP格式
define('FORCE_CONVERT_JPG_PNG_BMP_TO_WEBP', false);
//WEBP图片的压缩质量
define('WEBP_IMAGE_QUALITY', 90);

//默认支持缓存的文件夹路径
define('ARRAY_ACCEPTED_DIRECTORY', [
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
