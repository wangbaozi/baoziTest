<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class Controller extends BaseController
{
    //异常编号+信息
    public $failMsg = [
        0 => '正常',
        1 => 'FTP文件列表返回失败文件不存在',
        2 => 'FTP文件下载失败文件不存在',
        3 => '文件执行失败',
        4 => '读取文件失败或文件不存在',
        5 => '解析文件内容失败',
        6 => '写入失败',
        7 => '解析模版失败或模版选择错误'
    ];

    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
}
