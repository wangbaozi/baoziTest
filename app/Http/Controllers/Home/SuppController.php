<?php

namespace App\Http\Controllers\Home;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use App\Models\Basic\Supp;
use Excel;
use Auth;
use Validator;
use Illuminate\Support\Facades\Log;


use Illuminate\Support\Facades\DB;

class SuppController extends Controller
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

    //获取所有有效商家
    public function getValidSupp(){
        $params = [
            'status'        => 1,
            'neq_supp_mcid' => ''
        ];
        $data_list = Supp::getIns()->getDataList($params);

        if (empty($data_list)) {
            msg(self::$success, '更新成功');
        }

        $new_list = [];
        $i = 0;
        foreach ($data_list as $k => $v) {
            $new_list[$i]['id']            = $v['supp_id'];
            $new_list[$i]['supp_mcid']     = $v['supp_mcid'];
            $i++;
        }
        msg(self::$success, '更新成功', $new_list);
    }


    //商家管理列表
    public function suppList(Request $request)
    {
        return view('supp.suppList')->with('tree_menu','supp');
    }


    //商家管理列表
    public function getSuppList(Request $request)
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
            'lk_supp_id' => $request->input('supp_id', ''),
            'lk_supp_mcid' => $request->input('supp_mcid', ''),
            'lk_supp_name' => $request->input('supp_name', ''),
            'status'       => $request->input('status', 0),
            'start'        => $request->input('start', 0),
            'length'        => $request->input('length', 10)
        ];

        $total_count = Supp::getIns()->getDataList($params, true);
        if(empty($total_count)){
            return json_encode($result);
        }

        $data_list = Supp::getIns()->getDataList($params);

        //返回前端数据 -- 组装
        $new_list = [];
        $i = 0;
        foreach ($data_list as $k => $v) {
            $new_list[$i]['id']            = $v['supp_id'];
            $new_list[$i]['supp_mcid']     = empty($v['supp_mcid'])     ? '-' : $v['supp_mcid'];
            $new_list[$i]['supp_name']     = empty($v['supp_name'])     ? '-' : $v['supp_name'];
            $new_list[$i]['supp_name_en']  = empty($v['supp_name_en'])  ? '-' : $v['supp_name_en'];
            $new_list[$i]['supp_name_cn']  = empty($v['supp_name_cn'])  ? '-' : $v['supp_name_cn'];
            $new_list[$i]['supp_homepage'] = empty($v['supp_homepage']) ? '-' : $v['supp_homepage'];
            $new_list[$i]['status']        = $v['status'];
            $i++;
        }

        $result['data']  = $new_list;
        $result['recordsFiltered'] = $result['recordsTotal'] = $total_count;
        return json_encode($result);
    }


    //商家新增
    public function suppAdd(Request $request)
    {
        if ($request->method() == 'POST') {

            //参数验证
            $rules_msgs = suppRulesMsgs('suppAdd');
            $this->addEditValidate($request, $rules_msgs['rules'], $rules_msgs['msgs'], 'add');

            $res = Supp::insert($this->data['data']);
            if ($res !== false) {
                msg(self::$success, '添加成功');
            }
            msg('0300', '添加失败');
        }

        return view('supp.suppAdd')->with([
            'tree_menu' => 'supp',
        ]);
    }

    //商家编辑
    public function suppEdit(Request $request)
    {
        if ($request->method() == 'POST') {

            //参数验证
            $rules_msgs = suppRulesMsgs('suppEdit');
            $this->addEditValidate($request, $rules_msgs['rules'], $rules_msgs['msgs']);  //参数验证

            $res = Supp::where('supp_id', $this->data['params']['supp_id'])->update($this->data['data']);
            if ($res !== false) {
                msg(self::$success, '更新成功');
            }
            msg('0320', '更新失败');
        }

        return view('supp.suppEdit')->with([
            'tree_menu' => 'supp',
            'supp_info' => Supp::find($request->input('id', 0)),
        ]);
    }


    //商家状态更新
    public function updateStatus(Request $request)
    {
        //参数验证
        $rules_msgs = suppRulesMsgs('updateStatus');
        $params    = $request->all();
        $validator = Validator::make($params, $rules_msgs['rules'], $rules_msgs['msgs']);
        if ($validator->fails()) {
            msg('0400', $validator->errors()->first());
        }

        //更新
        $user = Auth::user();
        $res  = Supp::whereIn('supp_id',$params['supp_id_list'])->update([
            'status'      => $params['status'],
            'update_time' => date('Y-m-d H:i:s', time()),
            'updator'     => $user['id']
        ]);

        if($res !== false) {
            msg(self::$success, '更新成功');
        }
        msg('0320', '更新失败');
    }


    //导出
    public function export(Request $request){
        $params = [
            'lk_supp_name' => $request->input('supp_name', ''),
            'status'       => $request->input('status', 0),
        ];
        $supp_list = Supp::getIns()->getDataList($params);

        $supp_list_data = [];
        if($supp_list) {
            $i = 0;
            foreach ($supp_list as $k => $v) {
                //$supp_list_data[$i]['id']            = $v['supp_id'];
                $supp_list_data[$i]['supp_mcid']     = empty($v['supp_mcid'])     ? '-' : $v['supp_mcid'];
                $supp_list_data[$i]['supp_name']     = empty($v['supp_name'])     ? '-' : $v['supp_name'];
                $supp_list_data[$i]['supp_name_en']  = empty($v['supp_name_en'])  ? '-' : $v['supp_name_en'];
                $supp_list_data[$i]['supp_name_cn']  = empty($v['supp_name_cn'])  ? '-' : $v['supp_name_cn'];
                $supp_list_data[$i]['supp_homepage'] = empty($v['supp_homepage']) ? '-' : $v['supp_homepage'];
                $supp_list_data[$i]['status']        = getSuppStatusInfo($v['status']);
                $i++;
            }
        }

        //$supp_list_data = $this->index();
        $header = [['mcid','商家名称','英文名称','中文名称','官网','上架状态']];

        //$header = [['城市','点击量','订单量','销售额','佣金','回扣','利润']];
        $cellData = array_merge($header, $supp_list_data);

        Excel::create('商家列表',function($excel) use ($cellData){
            $excel->sheet('score', function($sheet) use ($cellData){
                $sheet->rows($cellData);
            });
        })->export('csv');
    }

    //批量导入商家xlsx文件
    public function importSupp(Request $request){
        return view('supp.importSupp')->with('tree_menu','supp');
    }


    private function addEditValidate($request, $rules=[], $msgs=[], $type=''){
        $params    = $request->all();
        if ( $type != 'add' ) {
            if ($params['supp_id']) {
                $rules['supp_mcid'] .= ',' . $params['supp_id'] . ',supp_id';
                $rules['supp_name'] .= ',' . $params['supp_id'] . ',supp_id';
            }
        }

        $validator = Validator::make($params, $rules, $msgs);

        if ($validator->fails()) {
            msg('0400', $validator->errors()->first());
        }

        $now  = date('Y-m-d H:i:s', time());
        $user = Auth::user();

        $this->data['params'] = $params;
        $this->data['data'] = [
            'supp_mcid'     => $params['supp_mcid'],
            'supp_name'     => $params['supp_name'],
            'supp_region'   => empty($params['supp_region'])   ? '' : $params['supp_region'],
            'supp_logo'     => empty($params['supp_logo'])     ? '' : $params['supp_logo'],
            'supp_name_en'  => empty($params['supp_name_en'])  ? '' : $params['supp_name_en'],
            'supp_name_cn'  => empty($params['supp_name_cn'])  ? '' : $request->input('supp_name_cn'),
            'supp_homepage' => empty($params['supp_homepage']) ? '' : $request->input('supp_homepage'),
            'supp_aff_min'  => empty($params['supp_aff_min'])  ? '' : $request->input('supp_aff_min'),
            'supp_desc_cn'  => empty($params['supp_desc_cn'])  ? '' : $request->input('supp_desc_cn'),
            'supp_desc_en'  => empty($params['supp_desc_en'])  ? '' : $request->input('supp_desc_en'),
            'updator'       => $user['id'],
            'update_time'   => $now,
        ];

        if ( $type == 'add' ) {
            $this->data['data']['creator']     = $user['id'];
            $this->data['data']['create_time'] = $now;
        }
    }


}
