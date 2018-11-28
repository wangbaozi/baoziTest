<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use App\Services\OSS;

class CommonController extends Controller
{
    /**
     * 阿里云oss上传图片方法
     * @params = [
     *      'imgFile'   => '必须',    // file文件
     *      'file_type' => '非必须',  // 上传文件的地址类型，默认supp,  即data/upload/images/datafeed/默认supp
     * ]
     *
     *
     *
     * @return \Illuminate\Http\Response
     */
    public function upload(Request $request)
    {
        $file_type = $request->input('file_type', 'supp');
        // 获取表单提交的图片
        $pic = $request->file('imgFile');

        //如果有上传图片
        if (empty($pic)) {
            msg('0300', '请选择上传图片');
        }

        if (!$pic->isValid()) {
            msg('0300', '上传图片无效..');
        }

        //获取后缀名和地址
        $titles  = $pic->getClientOriginalExtension();
        $picpath = $pic->getRealPath();

        $save_fold = sprintf('data/upload/images/datafeed/%s/%s', $file_type,  date('Ymd'));
        //保存文件名
        $filename = sprintf(
            '%s_%d.%s',
            date('YmdHis'),
            rand(100000, 999999),
            $titles
        );
        //最终地址
        $save_path = sprintf('%s/%s', $save_fold, $filename);

        //阿里 OSS 图片上传
        $result = OSS::getIns()->publicUpload($save_path, $picpath);

        if (!$result) {
            msg('0500', '上传图片失败..');
        }
        msg('0200', '上传成功', config('alioss.PREFIXURLCDNIMG') . $save_path);
    }

    /**
     * 异步处理
     * @param $url
     * @param $data
     * @param int $port
     * @return array
     */
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
