<?php

/**
 * 基于PHP的简易CDN程序
 * 原作者 Lawrence Cherone
 * 改进者 hexie2108
 * 
 * @version 3.00
 * @author Lawrence Cherone <lawrence@cherone.co.uk>
 */

require_once 'class_error_manager.php';
require_once 'class_curl_manager.php';
require_once 'functions.php';


class Simple_PHP_CDN
{
    /**
     * 回源域名
     * @var string
     */
    public $origin_domain;
    /**
     * 缓存ROOT目录
     * @var string
     */
    public $cache_root_directory;
    /**
     * 请求的URI地址 , 不包含域名
     * ES: http://a.com/path/resource?name=1 => /path/resource?name=1
     *
     * @var string
     */
    public $request_uri;

    /**
     * 文件的文件夹路径
     *
     * @var string
     */
    public $request_dirname;

    /**
     * 文件的全名
     *
     * @var string
     */
    public $request_basename;

    /**
     * 文件的后缀名
     *
     * @var string
     */
    public $request_extension;

    /**
     * 文件名 (无后缀)
     *
     * @var string
     */
    public $request_filename;

    /**
     * 文件的MIME类型
     *
     * @var string
     */
    public $request_mime_type;

    /**
     * 缓存文件的完整路径
     *
     * @var string
     */
    public $full_cache_file_path;

    /**
     * 缓存是否存在
     *
     * @var bool
     */
    public $cache_is_exits;
    /**
     * 缓存是否还有效 没过期
     *
     * @var bool
     */
    public $cache_is_valid;

    /**
     * 缓存的创建时间
     *
     * @var int
     */
    public $cache_last_modified_date;


    /**
     * @param string $origin_domain 回源地址
     * @param string $cache_root_directory 缓存ROOT目录
     * @param string $fix_request_path 如果脚本在子目录中, 需要修正URI路径
     * 
     */
    function __construct($origin_domain = null, $cache_root_directory = null, $fix_request_path = null)
    {
        $this->origin_domain = $origin_domain;
        $this->cache_root_directory = $cache_root_directory;

        $this->request_uri = $_SERVER['REQUEST_URI'];
        //如果需要修正路径
        if ($fix_request_path)
        {
            $this->request_uri = str_replace($fix_request_path, '', $this->request_uri);
        }
        //获取请求的文件URL路径, 移除query参数, URL解码
        $request_path = urldecode(parse_url($this->request_uri, PHP_URL_PATH));

        //根据请求 生成请求文件的信息
        // $path_parts = pathinfo($request_path);
        $this->request_dirname = pathinfo($request_path, PATHINFO_DIRNAME);
        $this->request_basename =  pathinfo($request_path, PATHINFO_BASENAME);
        $this->request_extension =  pathinfo($request_path, PATHINFO_EXTENSION);
        $this->request_filename = pathinfo($request_path, PATHINFO_FILENAME);
        $this->request_mime_type = get_mime_type($this->request_extension);


        //错误检查

        //判断文件夹路径是否合法
        if (strlen($this->request_dirname) > 1)
        {
            $directory_is_valid = false;
            foreach (ARRAY_ACCEPTED_DIRECTORY as $accepted_directory)
            {
                if (strpos($this->request_dirname, $accepted_directory) !== false)
                {
                    $directory_is_valid = true;
                }
            }
            if ($directory_is_valid === false)
            {
                Error_Manager::send(Error_Manager::HEADER_BAD_REQUEST, '不支持的路径文件夹');
            }
        }

        // 如果文件后缀不存在
        if (empty($this->request_extension))
        {
            Error_Manager::send(Error_Manager::HEADER_BAD_REQUEST, '缺少后缀名');
        }
        if (in_array($this->request_extension, ARRAY_ACCEPTED_EXTENSION) === false)
        {
            Error_Manager::send(Error_Manager::HEADER_BAD_REQUEST, '不支持的后缀名');
        }
        //如果 mime数据不存在/不符合要求
        elseif (empty($this->request_mime_type))
        {
            Error_Manager::send(Error_Manager::HEADER_UNSUPPORTED_MEDIA_TYPE, '无效MIME类型');
        }



        // 处理请求
        $this->handle();
    }

    /**
     * 检测缓存是否存在, 以及缓存的有效性
     * 
     * $_REQUEST['ver'] 使用 ver 参数 可以给缓存文件添加版本别名
     * $_REQUEST['force'] 使用 force 参数 可以强制更新缓存
     * 
     */
    private function check_cache_existence_and_validity()
    {
        //保持原有目录和文件名称
        $file_name = $this->request_filename;

        //如果有ver版本参数
        if (isset($_REQUEST['ver']))
        {
            //添加版本号到文件末端
            $file_name .= '_' . $_REQUEST['ver'];
        }

        //生成完整文件路径
        $this->full_cache_file_path = $this->cache_root_directory . $this->request_dirname . DIRECTORY_SEPARATOR . $file_name . '.' . $this->request_extension;

        //如果有强制刷新的参数
        if (isset($_REQUEST['force']))
        {
            //删除缓存文件 
            unlink($this->full_cache_file_path);
        }

        //检测文件是否存在
        $this->cache_is_exits = file_exists($this->full_cache_file_path);

        //获取文件最后修改时间, 如果文件存在
        $this->cache_last_modified_date = $this->cache_is_exits ? filemtime($this->full_cache_file_path) : 0;

        //如果文件不存在, 或者已过期
        if (!$this->cache_is_exits || ($this->cache_last_modified_date +  CACHE_EXPIRY < time()))
        {
            $this->cache_is_valid = false;
        }
        else
        {
            $this->cache_is_valid = true;
        }
    }

    /**
     *处理请求
     */
    private function handle()
    {

        // 检测缓存有效性
        $this->check_cache_existence_and_validity();

        // //清空输出缓冲
        // ob_clean();
        // //启动输出缓冲
        // ob_start();

        // //启动 GZIP版 输出缓冲区
        // ob_start("ob_gzhandler");

        //关闭缓冲区绝对刷送
        // ob_implicit_flush(false);

        //如果文件未过期
        if ($this->cache_is_valid)
        {
            //直接输出缓存文件
            $this->print_output(true);
        }
        //否则 需要和回源确认缓存有效性
        else
        {
            //发送head请求, 确认源文件是否存在 和 是否和缓存有区别
            $this->send_head_request();

            //如果和缓存没区别 直接输出缓存文件
            if ($this->cache_is_valid)
            {
                $this->print_output(true);
            }
            //如果和缓存有区别 
            else
            {
                $this->send_file_request();

                //如果是图片文件 就替换成webp
                $this->convert_img_to_webp();

                //输出文件, 客户端需要重新加载
                $this->print_output(false);
            }
        }

        // ob_end_flush();
        // exit();
    }

    /**
     * 发送 head 请求 获取header响应标头信息 , 来判断源文件是否存在
     *
     * @return void
     */
    private function send_head_request()
    {

        Curl_Manager::get_head($this->origin_domain . $this->request_uri, function ($response_header)
        {
            //如果缓存文件已存在
            if ($this->cache_is_exits)
            {
                //读取源文件大小
                $origin_file_size = intval($response_header['content-length'] ?? 0);
                //获取本地缓存大小
                $cache_file_size = filesize($this->full_cache_file_path);

                // //如果源文件 和缓存文件 差距小于1KB, 假设是有效的
                if (abs($origin_file_size - $cache_file_size) < 1024)
                {
                    //判定缓存有效, 不需要重新下载源文件
                    $this->cache_is_valid = true;
                    //更新缓存文件的最后修改时间
                    touch($this->full_cache_file_path);
                }
                //否则
                else
                {
                    //删除缓存文件 
                    unlink($this->full_cache_file_path);
                }
            }
        });
    }

    /**
     * 从源地址下载文件
     * @return void
     */
    private function send_file_request()
    {
        //如果对应文件夹目录不存在
        $dirname = dirname($this->full_cache_file_path);
        if (!is_dir($dirname))
        {
            //递归创建需要的文件夹
            mkdir($dirname, 0755, true);
        }

        //创建文件
        // $file = fopen($this->full_cache_file_path, 'w');
        //锁定文件
        // if (flock($file, LOCK_EX | LOCK_NB))
        // {

        Curl_Manager::get_file(
            $this->origin_domain . $this->request_uri,
            // $file,
            //获取成功的情况
            function ($response) //use ($file)
            {
                //内容不为空
                if ($response)
                {
                    file_put_contents($this->full_cache_file_path, $response);
                }
                //关闭文件句柄
                // fclose($file);
                //解除文件锁定
                // flock($file, LOCK_UN);
            },
            //出现错误情况
            function () //use ($file)
            {
                // 在发生错误时检查文件句柄是否已经打开，如果是，则关闭它
                // if ($file !== false)
                // {
                //     //关闭文件句柄
                //     fclose($file);
                //     //解除文件锁定
                //     // flock($file, LOCK_UN);
                // }
                // // 检查是否已经创建了文件，如果是则删除文件
                // if (file_exists($this->full_cache_file_path))
                // {
                //     unlink($this->full_cache_file_path);
                // }
            }
        );
        // }
        // else
        // {
        //     // 关闭文件句柄
        //     fclose($file);
        //     Error_Manager::send(Error_Manager::HEADER_INTERNAL_SERVER_ERROR, '无法获取文件锁');
        // }
    }

    /**
     * 把当前储存的图片文件 转换成webp
     *
     * @return void
     */
    private function convert_img_to_webp()
    {
        //如果不是支持的图片文件类型, 结束
        if (!in_array($this->request_mime_type, [
            'image/jpeg',
            'image/png',
            // 'image/gif',
            'image/bmp',
        ]))
        {
            return;
        }

        $new_webp_image_path = $this->cache_root_directory . $this->request_dirname . DIRECTORY_SEPARATOR . $this->request_filename . '.' . 'webp';

        //生成webp图片
        create_webp_file($this->full_cache_file_path, $new_webp_image_path);

        //更新mime type
        $this->request_mime_type = 'image/webp';
        

        //删除原始的jpg图片
        unlink($this->full_cache_file_path);
        //使用新的webp图片地址
        $this->full_cache_file_path = $new_webp_image_path;
    }

    /**
     * 输出文件内容
     *
     * @param bool $not_modified 缓存文件是否有更新
     * @return void
     */
    private function print_output($not_modified = true)
    {

        //如果无更新, 并且客户端有请求过, 并且请求时间大于缓存文件的创建时间
        if ($not_modified && isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && (strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $this->cache_last_modified_date))
        {
            //告诉客户端 可以直接使用本地缓存
            header($_SERVER['SERVER_PROTOCOL'] . ' 304 Not Modified');
        }
        //如果有更新, 或者客户端本地缓存已过期
        else
        {

            // header('Pragma: public');
            header('Cache-Control: public, max-age=' . CLIENT_CACHE_EXPIRY);
            header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + CLIENT_CACHE_EXPIRY));
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s \G\M\T', $this->cache_last_modified_date ?: time()));
            header('Content-Type: ' . $this->request_mime_type);
            // header('Access-Control-Allow-Origin: *');
            header('X-Content-Type-Options: nosniff');
            header('X-XSS-Protection: 1; mode=block');
            header('Server: Simple CDN');
            header('X-Powered-By: Simple CDN');
            header("Access-Control-Allow-Origin: *");

            // 检查文件是否存在
            if (file_exists($this->full_cache_file_path))
            {
                
                // flush();
                readfile($this->full_cache_file_path);
            }
            //如果文件不存在
            else
            {
                echo 'empty';
            }
        }
    }
}
