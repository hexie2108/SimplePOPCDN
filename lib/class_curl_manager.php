<?php


/**
 * 返回错误信息
 */
class Curl_Manager
{

    const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 Safari/537.36';

    /**
     * 创建默认的CURL参数数组
     *
     * @return array<int,mixed>
     */
    public static function create_default_options()
    {

        $options = [
            //模拟的浏览器信息
            CURLOPT_USERAGENT => static::USER_AGENT,
            //自动跳转
            CURLOPT_AUTOREFERER => true,
            //处理HTTP错误码
            CURLOPT_FAILONERROR => true,
            //返回请求结果 避免直接输出
            CURLOPT_RETURNTRANSFER => true,
            //关闭证书检查
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_DNS_CACHE_TIMEOUT  => 60 * 60, //1小时
            // CURLOPT_TIMEOUT => 15,
            // CURLOPT_CONNECTTIMEOUT => 15,
            // CURLOPT_NOBODY => true,

        ];

        return $options;
    }

    /**
     * 发送请求, 但是只获取HTTP头部信息
     *
     * @param string $url
     * @param callable $success_callback
     */
    public static function get_head($url, $success_callback)
    {
        $ch = curl_init();

        $response_header = [];

        $options = [
            CURLOPT_URL => $url,
            //只获取HTTP头部信息
            CURLOPT_NOBODY => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 10,

            //在获取结果的HEAD信息时, 调用回调函数, 储存HEAD信息数组
            CURLOPT_HEADERFUNCTION => function ($curl, $header) use (&$response_header)
            {
                $len = strlen($header);
                $header = explode(':', $header, 2);
                //只获取有效的标头信息
                if (count($header) > 1)
                {
                    $header_key = strtolower(trim($header[0]));
                    $header_value = trim($header[1]);
                    //提取标头信息, 保存到外部的数组变脸里
                    $response_header[$header_key] = $header_value;
                }
                return $len;
            },
        ];

        curl_setopt_array($ch, $options + static::create_default_options());

        //发送请求
        $response = curl_exec($ch);

        // echo '<pre>';
        // var_dump($response);
        // echo '<br>';
        // var_dump($response_header);
        // echo '<br>';
        // var_dump(curl_getinfo($ch));
        // echo '</pre>';
        // exit();

        //如果未发现错误
        if ($response !== false)
        {
            $success_callback($response_header);
            //注销ch资源
            curl_close($ch);
        }
        //如果请求失败
        else
        {
            $error_message = curl_error($ch);
            //注销ch资源
            curl_close($ch);

            Error_Manager::send(Error_Manager::HEADER_INTERNAL_SERVER_ERROR, $error_message);
        }
    }

    /**
     * 发送请求, 并存储到本地文件
     *
     * @param string $url
     * @param callable $success_callback
     * @param callable $error_callback
     */
    public static function get_file($url, $success_callback, $error_callback)
    {
        $ch = curl_init();


        $options = [
            CURLOPT_URL => $url,
            //只获取HTTP正文
            CURLOPT_HEADER => false,
            //把正文储存到对应的文件中
            // CURLOPT_FILE => $file,
   
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 10,
        ];

        curl_setopt_array($ch, $options + static::create_default_options());

        //发送请求
        $response = curl_exec($ch);


        //如果未发现错误
        if ($response !== false)
        {
            $success_callback($response);
            
            //注销ch资源
            curl_close($ch);
        }
        //如果请求失败
        else
        {
            $error_callback();

            $error_message = curl_error($ch);
            //注销ch资源
            curl_close($ch);

            Error_Manager::send(Error_Manager::HEADER_INTERNAL_SERVER_ERROR, $error_message);
        }
    }
}
