<?php

/**
 * A Simple PHP "Origin Pull" CDN Passthrough caching class,
 *  which enables you to create a simple CDN for automatic
 *  mirroring of static content on a faster, lesser loaded server.
 *
 * Usage: new SimplePOPCDN('http://origin.com', './cache/', '/subdir', 2592000);
 * @version 1.1
 * @author Lawrence Cherone <lawrence@cherone.co.uk>
 */

 require_once 'functions.php';


class SimplePOPCDN
{

    public $origin;
    public $request;
    public $request_part;
    public $request_info;
    public $cache_path;
    public $cache_expire;

    public $cache_max_age = 60 * 60 * 24 * 30 * 3; //3月 


    public $cache_full_path;
    public $cache_is_exits;
    public $cache_is_valid;
    public $cache_last_modified_date;


    /**
     * @param string $origin Host that we want to mirror resources
     * @param string $cache_path Path to cache directory
     * @param string $fix_request Remove part of the request path to fix if THIS script is sitting in a subdir
     * @param int $cache_expire Amount of time in seconds cache is valid for. 3600 = 1 小时 , 3600 * 24  = 1 天
     * 
     * $_REQUEST['ver'] 使用 ver 参数 可以给缓存文件添加版本别名
     * $_REQUEST['force'] 使用 force 参数 可以强制更新缓存
     */
    function __construct($origin = null, $cache_path = null, $fix_request = null, $cache_expire = 3600)
    {
        $this->origin = $origin;
        $this->request = ($fix_request !== null) ? str_replace($fix_request, '', $_SERVER['REQUEST_URI']) : $_SERVER['REQUEST_URI'];
        //$this->request = str_replace('cdn/', '', $this->request);

        $this->request_part = parse_url($this->request);

        $this->request_info = pathinfo(urldecode($this->request_part['path']));

        $this->cache_path = $cache_path;
        $this->cache_expire = (int)$cache_expire;


        // 初始化参数
        $this->setup(array(
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
        ));

        // 处理请求
        $this->handle();
    }

    /**
     *初始化参数
     */
    private function setup($accepted = array())
    {
        // 使用null为默认格式 如果格式不存在
        if (!isset($this->request_info['extension']))
        {
            $this->request_info['extension'] = null;
        }

        // 根据文件后缀 获取对应 mime格式
        $this->request_info['mime'] = get_mime_type($this->request_info['extension'], $accepted);

        // 如果文件后缀不存在
        if (empty($this->request_info['extension']))
        {
            $this->error($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request: extension is null');
        }
        //如果 mime数据不存在
        elseif (empty($this->request_info['mime']))
        {
            // path extension is not supported
            $this->error($_SERVER['SERVER_PROTOCOL'] . ' 415 Unsupported Mime type');
        }




        //保持原有目录和文件名称
        $cache_file_name = $this->request_info['dirname'] . '/' . $this->request_info['filename'];

        // 加密文件名
        //$cache_file_name = basename($this->request);
        // set cache name hash from request
        //$cache_file_name = hash('sha256', $this->request);

        //如果有存在ver版本变量
        if (isset($_REQUEST['ver']))
        {
            //添加版本号到文件末端
            $cache_file_name .= '_' . $_REQUEST['ver'];
        }


        //生成完整文件路径
        $this->cache_full_path = $this->cache_path . $cache_file_name . '.' . $this->request_info['extension'];

        //检测文件是否存在
        $this->cache_is_exits = file_exists($this->cache_full_path);



        //获取文件最后修改时间, 如果文件存在
        $this->cache_last_modified_date = $this->cache_is_exits ? filemtime($this->cache_full_path) : false;

        $this->cache_is_valid = $this->cache_is_exits;

        //如果文件存在, 但是已过期
        if ($this->cache_is_valid && ($this->cache_last_modified_date +  $this->cache_expire < time()))
        {
            $this->cache_is_valid = false;
        }

        //如果需要强制刷新
        if (isset($_REQUEST['force']))
        {
            $this->cache_is_exits = false;
            $this->cache_is_valid = false;
            //删除本地缓存文件 
            unlink($this->cache_full_path);
        }
    }

    /**
     *处理请求
     */
    private function handle()
    {


        //清空 输出缓冲区
        ob_clean();

        //启动 默认输出缓冲区
        ob_start();

        /*
        // Setup Gzip based on client accept header
        if (isset($_SERVER['HTTP_ACCEPT_ENCODING']) && substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip'))
        {
            //启动 GZIP版 输出缓冲区
            ob_start("ob_gzhandler");
        }
        else
        {
            //启动 默认输出缓冲区
            ob_start();
        }*/

        //关闭缓冲区绝对刷送
        ob_implicit_flush(false);


        //如果文件未过期
        if ($this->cache_is_valid)
        {
            //直接输出缓存文件
            $this->print_output(true);
        }
        //否则 需要和回源确认缓存有效性
        else
        {
            //发送head请求, 确认缓存是否完整
            $this->send_head_request();
            //如果缓存完整 直接输出缓存文件
            if ($this->cache_is_valid)
            {
                $this->print_output(true);
            }
            //如果缓存不完整 需要重新从源地址下载
            else
            {
                $this->send_get_request();
                //输出文件, 客户端需要重新加载
                $this->print_output(false);
            }
        }
    }

    /**
     * 发送 head 请求 获取header响应标头信息 , 来判断缓存文件是否有效
     *
     * @return void
     */
    private function send_head_request()
    {


        $headers = [];

        // 发送 HEAD request - 检测源文件是否存在
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_USERAGENT => "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.17 (KHTML, like Gecko) Chrome/24.0.1312.52 Safari/537.17",
            CURLOPT_AUTOREFERER => true,
            CURLOPT_URL => $this->origin . $this->request,
            // CURLOPT_URL => 'http://www.mikuclub.cc',
            //CURLOPT_REFERER => 'http://www.mikuclub.cc',
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_FAILONERROR => true,
            CURLOPT_RETURNTRANSFER => true,
            //CURLOPT_HEADER => false,
            CURLOPT_NOBODY => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_DNS_CACHE_TIMEOUT  => 60 * 60,
            CURLOPT_HEADERFUNCTION => function ($curl, $header) use (&$headers)
            {
                $len = strlen($header);
                $header = explode(':', $header, 2);
                //只获取有效的标头信息
                if (count($header) > 1)
                {
                    $header_key = strtolower(trim($header[0]));
                    $header_value = trim($header[1]);
                    //提取标头信息, 保存到外部的数组变脸里
                    $headers[$header_key] = $header_value;
                }
                return $len;
            },

        ));

        //发送请求
        $response = curl_exec($ch);

        //如果未发现错误
        if ($response !== false)
        {

            //查询源文件大小
            $origin_file_size = $headers['content-length'] ?? 0;
            $origin_file_size = intval($origin_file_size);

            //如果缓存文件存在
            if ($this->cache_is_exits)
            {
                //获取本地缓存大小
                $local_file_size = filesize($this->cache_full_path);


                //如果源文件 小于 本地文件 + 1kb, 大于 本地文件 + 1kb 的情况 说明本地缓存文件和源文件偏差不大
                if ($origin_file_size < $local_file_size + 1024 && $origin_file_size > $local_file_size - 1024)
                {

                    //不需要重新下载源文件
                    $this->cache_is_valid = true;

                    //更新文件修改时间
                    touch($this->cache_full_path);
                }
                //否则  文件不完整
                else
                {
                    //删除本地缓存文件 
                    unlink($this->cache_full_path);
                }
            }


            //注销ch资源
            curl_close($ch);
        }
        //如果请求失败, 文件不存在等
        else
        {
            $error_message = curl_error($ch);
            //注销ch资源
            curl_close($ch);

            $this->error($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found', $error_message);
        }
    }

    /**
     * 从源地址下载文件
     */
    private function send_get_request()
    {

        //标记是否有请求错误
        $is_curl_error = false;

        $ch = curl_init();

        //如果对应文件夹目录不存在
        $dirname = dirname($this->cache_full_path);
        if (!is_dir($dirname))
        {
            //递归创建需要的文件夹
            mkdir($dirname, 0755, true);
        }

        //创建文件
        $fp = fopen($this->cache_full_path, 'a+b');

        //锁定文件
        if (flock($fp, LOCK_EX | LOCK_NB))
        {
            //清空文件内容
            ftruncate($fp, 0);
            //重置文件指针到开头
            rewind($fp);

            //发送 GET 请求, 把响应信息直接储存到文件里

            curl_setopt_array($ch, array(
                CURLOPT_USERAGENT => "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.17 (KHTML, like Gecko) Chrome/24.0.1312.52 Safari/537.17",
                CURLOPT_URL => $this->origin . $this->request,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_CONNECTTIMEOUT => 120,
                CURLOPT_FAILONERROR => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HEADER => false,
                //   CURLOPT_ENCODING => 'gzip',
                CURLOPT_FILE => $fp,
                CURLOPT_DNS_CACHE_TIMEOUT  => 60 * 60,

            ));

            // 如果请求失败
            if (curl_exec($ch) === false)
            {
                //清空文件内容
                ftruncate($fp, 0);
                //激活标记
                $is_curl_error = true;
            }

            //将缓冲内容输出到文件
            fflush($fp);
            //解除文件锁定
            flock($fp, LOCK_UN);
        }

        //关闭文件
        fclose($fp);

        //如果存在请求错误
        if ($is_curl_error)
        {
            //删除文件 
            unlink($this->cache_full_path);

            $error_message = curl_error($ch);
            //注销ch资源
            curl_close($ch);

            $this->error($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', $error_message);
        }

        //注销ch资源
        curl_close($ch);
    }

    /**
     * 输出内容
     *
     * @param bool $not_modified 是否使用304让用户继续使用客户端缓存
     * @return void
     */
    private function print_output($not_modified = true)
    {

        // Check client cache if found send 304
        //如果客户端已保存 返回 304
        if ($not_modified && isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && (strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) == $this->cache_last_modified_date))
        {
            header($_SERVER['SERVER_PROTOCOL'] . ' 304 Not Modified');
        }
        //如果未保存
        else
        {

            header('Pragma: public');
            header("Cache-Control: max-age={$this->cache_max_age}");
            header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + $this->cache_max_age));
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s \G\M\T', $this->cache_last_modified_date));
            header("Content-Type: {$this->request_info['mime']}");
            header("Access-Control-Allow-Origin: *");
            header('X-Content-Type-Options: nosniff');
            header('X-XSS-Protection: 1; mode=block');
            header('Server: CDNServer');
            header('X-Powered-By: SimplePOPCDN');

            $filesize =  readfile($this->cache_full_path);

            /*
            // Cont.. Gzip header check
            if (headers_sent())
            {
                $encoding = false;
            }
            elseif (isset($_SERVER['HTTP_ACCEPT_ENCODING']) && strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'x-gzip') !== false)
            {
                $encoding = 'x-gzip';
            }
            elseif (isset($_SERVER['HTTP_ACCEPT_ENCODING']) && strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false)
            {
                $encoding = 'gzip';
            }
            else
            {
                $encoding = false;
            }

            //关闭gzip压缩
            $encoding = false;


            // Finally output the buffer
            if ($encoding)
            {
                $contents = ob_get_contents();
                ob_end_clean();
                header('Content-Encoding: ' . $encoding);
                echo "\x1f\x8b\x08\x00\x00\x00\x00\x00";
                $size = strlen($contents);
                exit(substr(gzcompress($contents, 9), 0, $size));
            }
            else
            {

                //添加长度数据
                //   $contents = ob_get_contents();
                //  header('Content-Length: ' . strlen($contents));

                ob_end_flush();
                exit();
            }*/


            ob_end_flush();
            exit();


            // Stream file
            //set_time_limit(0);
            //$h = gzopen($this->cache_full_path, 'r');
            //while ($line = gzgets($h, 4096)) {
            //   echo $line;
            //}
            //gzclose($h);

            //exit();
        }
    }


    /**
     * Error response, sets message in basic html response for non success
     *
     * @param string $header
     * @param string $message
     */
    private function error($header = "HTTP/1.1 404 Not Found", $message = "")
    {
        header($header);
        header('Content-Type: text/html');
        header('Cache-Control: private');
        //exit('<!DOCTYPE HTML><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"><title>' . $this->origin . ' CDN | ' . htmlspecialchars($header) . '</title></head><body><h1><a href="' . $this->origin . '">' . $this->origin . '</a> CDN - ' . htmlspecialchars($header) . '</h1><p>' . $message . '</p></body></html>');
        exit('<!DOCTYPE HTML><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"><title> CDN | ' . htmlspecialchars($header) . '</title></head><body><h1> CDN - ' . htmlspecialchars($header) . '</h1><p>' . $message . '</p></body></html>');
    }

    
}
