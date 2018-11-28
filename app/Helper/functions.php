<?php
/**
 * Created by PhpStorm.
 * User: 55haitao
 * Date: 2018/8/29
 * Time: 上午9:56
 */

/**
 * 公用的方法  返回json数据，进行信息的提示
 * @param $status 状态
 * @param string $message 提示信息
 * @param array $data 返回数据
 */
if (! function_exists('msg')) {
    function msg($code = '', $msg = '', $payload = '', $die = true)
    {
        if (env('APP_ENV') == 'test') {
            get_json(['code' => $code, 'msg' => $msg, 'payload' => $payload, 'sql_list' => response()->json(DB::getQueryLog())], true, $die);
        }

        get_json(['code' => $code, 'msg' => $msg, 'payload' => $payload], true, $die);
    }
}

if (! function_exists('get_json')) {
    function get_json($data = array(), $echo = true, $die = true)
    {
        $str = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($echo) {
            echo $str;
        } else {
            return $str;
        }
        $die && die();
    }
}



/*
 * 异步调用post
 */
if (!function_exists('post_async')) {
    function post_async($url, $data, $port = 80)
    {
        $query = http_build_query($data);
        $port = $port ? $port : 80;
        $host = parse_url($url,PHP_URL_HOST);
        $scheme = parse_url($url,PHP_URL_SCHEME);
        if($scheme == 'https'){
            $host = 'ssl://'.$host;
        }

        $fp = fsockopen($host, $port, $error_code, $error_msg,1);
        if(!$fp){
            return array('error_code' => $error_code, 'error_msg' => $error_msg);
        }else{
            stream_set_blocking($fp,true);
            stream_set_timeout($fp,1);

            $header = "POST ${url} HTTP/1.1\r\n";
            $header .= "Host:${host}\r\n";
            $header .= "Content-type:application/x-www-form-urlencoded\r\n";
            $header .= "Content-length:".strlen($query)."\r\n";
            $header .= "Connection:close\r\n\r\n";
            $header .= "${query}";

            fwrite($fp, $header);
            usleep(20000);
            fclose($fp);
        }
    }
}


//获取oss客户端，用于文件上传
if (!function_exists('load_oss_sdk')) {
    function get_oss_client() {
        include_once (app_path() . '/Services/oss_sdk/lhoss.class.php');

        $client = lhoss::get_oss_client();
        $bucket = lhoss::get_bucket_name();

        return compact('client', 'bucket');
    }

}