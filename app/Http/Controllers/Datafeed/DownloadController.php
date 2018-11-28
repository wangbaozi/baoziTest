<?php

namespace App\Http\Controllers\Datafeed;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use App\Models\Link\Download;
use Auth;
use Validator;

use Illuminate\Support\Facades\DB;

class DownloadController extends Controller
{
    public $data;

    private static $success = '0200';

    public function __construct()
    {
        $this->middleware('auth');

        if(env('APP_ENV') == 'test') {
            DB::enableQueryLog();
        }
    }


    //下载文件列表--视图
    public function downList()
    {
        return view('datafeed.downList')->with('tree_menu','download');
    }

    //下载文件列表--视图
    public function failList()
    {
        $failMsg = $this->failMsg;
        return view('datafeed.downFailList')->with([
            'tree_menu'=>'download_fail',
            'failMsg'=>$failMsg
        ]);
    }



    //下载文件列表
    public function getDownList(Request $request)
    {
        $result = [
            'code'   => self::$success,
            'msg'    => '数据获取成功',
            'data'   => [],
            'recordsFiltered' => 0,
            'recordsTotal'    => 0,
            'draw'            => $request->input('draw', 1)
        ];
        $params = [
            'status_range' => $request->input('status_range', '3'), //1下载中，2结束，3失败  小于3
            'status'       => $request->input('status', ''),
            'read_status'  => $request->input('read_status',''),
            'down_url'  => $request->input('down_url', ''),
            'file_url'  => $request->input('file_url', ''),
            'down_id'  => $request->input('down_id', ''),
            'cron_id'  => $request->input('cron_id', 0),
            'start'   => $request->input('start', 0),
            'length'  => $request->input('length', 10)
        ];

        $total_count = Download::getIns()->getDataList($params, true);
        if(empty($total_count)){
            return json_encode($result);
        }

        $data_list = Download::getIns()->getDataList($params);

        //返回前端数据 -- 组装
        $new_list = [];
        $i = 0;
        foreach ($data_list as $k => $v) {
            $new_list[$i]['id']            = $v['down_id'];
            $new_list[$i]['down_url']      = (strlen($v['down_url']) > 90) ? substr($v['down_url'], 0,90).'...' : $v['down_url'];
            $new_list[$i]['all_down_url']  = $v['down_url'];
            $new_list[$i]['file_url']      = $v['file_url'];
            $new_list[$i]['file_size']     = $v['file_size'];
            $new_list[$i]['file_unit']     = $v['file_unit'];
            $new_list[$i]['down_num']     = $v['down_num'];
            $new_list[$i]['create_time']   = $v['create_time'];
            $new_list[$i]['update_time']   = $v['update_time'];
            $new_list[$i]['status']        = $v['status'];
            $new_list[$i]['read_status']   = $v['read_status'];
            $new_list[$i]['fail_no']   = $v['fail_no'];
            $new_list[$i]['fail_msg']   = $this->failMsg[$v['fail_no']];
            $i++;
        }

        $result['data']  = $new_list;
        $result['recordsFiltered'] = $result['recordsTotal'] = $total_count;
        return json_encode($result);
    }



    //文件编辑
    public function downDetail(Request $request)
    {
        $info = Download::find($request->input('id', 0));

        $down_info = [];
        if (!empty($info)){
            $down_info = [
                'id'          => $info['down_id'],
                'cron_id'     => $info['cron_id'],
                'down_url'    => $info['down_url'],
                'file_url'    => $info['file_url'],
                'size'        => (empty($info['file_size']) || empty($info['file_unit'])) ? '0K' : $info['file_size'].$info['file_unit'],
                'status'      => getDownStatusInfo($info['status']),
                'read_status' => getDownParseStatusInfo($info['read_status']),
                'create_time' => $info['create_time'],
                'update_time' => $info['update_time'],
                'read_time'   => $info['read_time'],
                'down_time'   => $info['down_time'],
                'fd_id'     => $info['fd_id'],
                'down_text'   => $info['down_text'],
                'fail_no'   => $info['fail_no'],
                'fail_msg'   => $this->failMsg[$info['fail_no']],

            ];
        }

        return view('datafeed.downDetail')->with([
            'tree_menu' => 'download',
            'down_info' => $down_info
        ]);
    }

    //文件编辑
    public function downAdd(Request $request)
    {

        if ($request->method() == 'POST') {
            $insert = [];
            $insert['file_url'] = $request->input('file_url');
            $insert['down_url'] = $request->input('down_url');
            $insert['fd_id'] = $request->input('fd_id');
            $insert['down_text'] = $request->input('down_text');
            $insert['status'] = $request->input('status');//0未下载，2已下载
            $insert['create_time'] = date('Y-m-d H:i:s');
            $insert['update_time'] = date('Y-m-d H:i:s');
            $res = Download::getIns()->insert($insert);
            if ($res !== false) {
                msg(self::$success, '更新成功');
            }

            msg('0320', '新增失败');
        }

        return view('datafeed.downAdd')->with([
            'tree_menu' => 'download',
        ]);
    }


    //文件状态更新
    public function updateStatus(Request $request)
    {
        //参数验证
        $params    = $request->all();
        $user = Auth::user();
        $update = [
            'update_time' => date('Y-m-d H:i:s', time()),
            'updator'     => $user['id']
        ];
        if(isset($params['status']))
            $update['status']   = $params['status'];

        if(isset($params['read_status']))
            $update['read_status']   = $params['read_status'];

        //更新
        $res  = Download::whereIn('down_id',$params['down_id_list'])->update($update);

        if($res !== false) {
            msg(self::$success, '更新成功');
        }
        msg('0320', '更新失败');
    }

    
    public function downFailHandle (Request $request) {
        $url = empty(env('SP_ONLINE_DOWNLOAD_URL')) ? env('APP_URL') : env('SP_ONLINE_DOWNLOAD_URL');
        $url .= '/redown/down';
        post_async($url, [
            'down_id_list' => $request->input('down_id_list',[])
        ]);

        msg('0200', '重新下载请求成功，请等待');
        
    }


}
