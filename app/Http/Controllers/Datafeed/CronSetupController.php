<?php

namespace App\Http\Controllers\Datafeed;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use App\Models\Link\Cron;
use App\Models\Basic\Supp;
use App\Models\Link\FileDb;
use Auth;
use Validator;


use Illuminate\Support\Facades\DB;

class CronSetupController extends Controller
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


    //文件cron设置管理列表--视图
    public function cronSetupList()
    {
        return view('datafeed.cronSetupList')->with('tree_menu','cronSetup');
    }


    //文件cron设置管理列表
    public function getDataList(Request $request)
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
            'cron_id'        => $request->input('cron_id', ''),
            'lk_mcid'      => $request->input('mcid', ''),
            'lk_link_host' => $request->input('link_host', ''),
            'lk_file_path' => $request->input('file_path', ''),
            'fd_id'        => $request->input('fd_id', ''),
            'status'       => $request->input('status', ''),
            'cron_status'  => $request->input('cron_status', ''),
            'start'        => $request->input('start', 0),
            'length'       => $request->input('length', 10),
            'to_array'     => true
        ];

        $total_count = Cron::getIns()->getDataList($params, true);
        if(empty($total_count)){
            return json_encode($result);
        }

        $data_list = Cron::getIns()->getDataList($params);

        $template_id_list = array_unique(array_column($data_list, 'fd_id'));

        $template_list = [];
        if (!empty($template_id_list)){
            $template_list  = FileDb::whereIn('fd_id',$template_id_list)->get(['fd_id', 'fd_name'])->keyBy('fd_id')->toArray();
        }

        //返回前端数据 -- 组装
        $new_list = [];
        $i = 0;
        foreach ($data_list as $k => $v) {
            $new_list[$i]['id']          = $v['cron_id'];
            $new_list[$i]['fd_name']     = empty($template_list[$v['fd_id']]['fd_name']) ? '-' : $template_list[$v['fd_id']]['fd_name'];
            $new_list[$i]['mcid']        = $v['mcid'];
            $new_list[$i]['link_type']   = $v['link_type'];
            $new_list[$i]['url']         = (strlen($v['link_host'].$v['file_path']) > 90) ? substr($v['link_host'].$v['file_path'],0,90).'...' : $v['link_host'].$v['file_path'];
            $new_list[$i]['all_url']     = $v['link_host'].$v['file_path'];
            $new_list[$i]['hour_run']    = $v['hour_run'];
            $new_list[$i]['hour_rate']   = $v['hour_rate'];
            $new_list[$i]['lastrun']     = empty($v['lastrun']) ? '-' : date('Y-m-d H:i:s', $v['lastrun']);
            $new_list[$i]['nextrun']     = empty($v['nextrun']) ? '-' : date('Y-m-d H:i:s', $v['nextrun']);
            $new_list[$i]['status']      = $v['status'];
            $new_list[$i]['cron_status'] = $v['cron_status'];
            $new_list[$i]['is_full']     = $v['is_full'];
            $i++;
        }

        $result['data']  = $new_list;
        $result['recordsFiltered'] = $result['recordsTotal'] = $total_count;
        return json_encode($result);
    }


    //文件cron设置新增
    public function cronSetupAdd(Request $request)
    {
        if ($request->method() == 'POST') {
            //参数验证
            $rules_msgs = cronSetupRulesMsgs('cronSetupAdd');
            $this->addEditValidate($request, $rules_msgs['rules'], $rules_msgs['msgs'], 'add');

            $res = Cron::insert($this->data['data']);
            if ($res !== false) {
                msg(self::$success, '添加成功');
            }
            msg('0300', '添加失败');
        }

        return view('datafeed.cronSetupAdd')->with([
            'tree_menu' => 'cronSetup',
        ]);
    }

    //文件定时记录编辑
    public function cronSetupEdit(Request $request)
    {
        if ($request->method() == 'POST') {

            //参数验证
            $rules_msgs = cronSetupRulesMsgs('cronSetupEdit');
            $this->addEditValidate($request, $rules_msgs['rules'], $rules_msgs['msgs']);

            $res = Cron::where('cron_id', $this->data['params']['cron_id'])->update($this->data['data']);
            if ($res !== false) {
                msg(self::$success, '更新成功');
            }
            msg('0320', '更新失败');
        }

        return view('datafeed.cronSetupEdit')->with([
            'tree_menu'       => 'cronSetup',
            'cron_setup_info' => Cron::find($request->input('id', 0)),
            'cron_fail_msg' => $this->failMsg,
        ]);
    }


    //文件定时记录状态更新
    public function updateStatus(Request $request)
    {
        //参数验证
        $rules_msgs = cronSetupRulesMsgs('updateStatus');
        $params     = $request->all();
        $validator  = Validator::make($params, $rules_msgs['rules'], $rules_msgs['msgs']);
        if ($validator->fails()) {
            msg('0400', $validator->errors()->first());
        }

        //更新
        $user = Auth::user();
        $update = [
            'update_time' => date('Y-m-d H:i:s', time()),
            'updator'     => $user['id']
        ];

        if(isset($params['status'])) {
            $update['status'] = $params['status'];
        }

        if(isset($params['cron_status'])){
            $update['cron_status'] = $params['cron_status'];
        }

        if(isset($params['is_full'])){
            $update['is_full'] = $params['is_full'];
        }

        $res  = Cron::whereIn('cron_id',$params['cron_id_list'])->update($update);

        if($res !== false) {
            msg(self::$success, '更新成功');
        }
        msg('0320', '更新失败');
    }



    private function addEditValidate($request, $rules=[], $msgs=[], $type=''){
        $params    = $request->all();
        $link_host = $file_path = '';
        if ($params['link_host'] && $params['file_path']) {
            $link_host = (substr($params['link_host'], -1) == '/') ? substr($params['link_host'],0,-1) : $params['link_host'];
            $file_path = (substr($params['file_path'], 0,1) == '/')  ? $params['file_path'] : '/'.$params['file_path'];

            $file_path = (json_decode(substr($file_path, 1), true)) ? substr($file_path, 1) : $file_path;
        }

        #mcid、类型、地址（host地址和文件地址）唯一 (更新排除自身)
//        if ($link_host && $file_path && $params['link_type']) {
//            $cron_id = ($type != 'add' && !empty($params['cron_id'])) ? $params['cron_id'] : 0;
//            $rules['mcid'] .= ',' . $cron_id . ',cron_id,link_type,'.$params['link_type'].',link_host,'.$link_host.',file_path,'.$file_path;
//        }

        $validator = Validator::make($params, $rules, $msgs);
        if ($validator->fails()) {
            msg('0400', $validator->errors()->first());
        }

        $time     = time();
        $now      = date('Y-m-d H:i:s', $time);
        $now_hour = date('H',$time);
        $user = Auth::user();

        $supp_info = Supp::where('supp_mcid',$params['mcid'])->first();
        if (empty($supp_info)) {
            msg('0410', '该商家数据异常，请重新选择商家！');
        }

        $next_run = ($params['hour_run'] > $now_hour) ? date("Y-m-d").' '.$params['hour_run'].':00:00' : date("Y-m-d",strtotime("+1 day")).' '.$params['hour_run'].':00:00';
        $this->data['params'] = $params;
        $this->data['data'] = [
            'mcid'         => $params['mcid'],
            'supp_id'      => $supp_info['supp_id'],
            'link_type'    => $params['link_type'],
            'fd_id'        => $params['fd_id'],
            'account_id'   => empty($params['account_id']) ? 0 : $params['account_id'],
            'link_host'    => $link_host,
            'file_path'    => $file_path,
            'file_ext'     => '',  //暂不保存
            'hour_run'     => $params['hour_run'],
            'hour_rate'    => $params['hour_rate'],

            'updator'      => $user['id'],
            'update_time'  => $now,

            'nextrun'      => strtotime($next_run),
        ];

        if ( $type == 'add' ) {
            $this->data['data']['lastrun']     = 0;
            $this->data['data']['creator']     = $user['id'];
            $this->data['data']['create_time'] = $now;
        }
    }



}
