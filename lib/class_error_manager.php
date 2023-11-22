<?php


/**
 * 返回错误信息
 */
class Error_Manager
{

    const HEADER_NOT_FOUND = '404 Not Found';
    const HEADER_BAD_REQUEST = '400 Bad Request';
    const HEADER_UNSUPPORTED_MEDIA_TYPE = '415 Unsupported Media Type';
    const HEADER_INTERNAL_SERVER_ERROR = '500 Internal Server Error';


    /**
     * 
     *
     * @param string $header
     * @param string $message
     */
    public static function send($header, $message = "")
    {
        header($_SERVER['SERVER_PROTOCOL'] . ' ' . $header);
        header('Content-Type: text/html');
        header('Cache-Control: private');

        $header_html_encoded = htmlspecialchars($header);

        $output = <<<HTML

            <!DOCTYPE html>
            <html>
                <head>
                    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
                    <title>{$header_html_encoded}</title>
                </head>
                <body>
                    <h4> Simple CDN</h4>
                    <h4>{$header_html_encoded}</h4>
                    <p>{$message}</p>
                </body>
            </html>

HTML;

        exit($output);
    }
}
