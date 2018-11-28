<?php

namespace App\Http\Controllers\Datafeed;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use App\Models\Link\Account;
use Auth;
use Validator;


use Illuminate\Support\Facades\DB;

class AccountController extends Controller
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

    //获取所有有效账号
    public function getValidAccount(){
        $params['status'] = 1;
        $data_list = Account::getIns()->getDataList($params);

        if (empty($data_list)) {
            msg(self::$success, '更新成功');
        }

        $new_list = [];
        $i = 0;
        foreach ($data_list as $k => $v) {
            $new_list[$i]['id']   = $v['account_id'];
            $new_list[$i]['name'] = $v['name'];
            $i++;
        }
        msg(self::$success, '更新成功', $new_list);
    }

    //账号管理列表--视图
    public function accountList()
    {
        return view('datafeed.accountList')->with('tree_menu','account');
    }


    //账号管理列表
    public function getAccountList(Request $request)
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
            'lk_name' => $request->input('name', ''),
            'status'  => $request->input('status', 0),
            'start'   => $request->input('start', 0),
            'length'  => $request->input('length', 10)
        ];

        $total_count = Account::getIns()->getDataList($params, true);
        if(empty($total_count)){
            return json_encode($result);
        }

        $data_list = Account::getIns()->getDataList($params);

        //返回前端数据 -- 组装
        $new_list = [];
        $i = 0;
        foreach ($data_list as $k => $v) {
            $new_list[$i]['id']            = $v['account_id'];
            $new_list[$i]['name']          = $v['name'];
            $new_list[$i]['username']      = $v['username'];
            $new_list[$i]['password']      = $v['password'];
            $new_list[$i]['create_time']   = $v['create_time'];
            $new_list[$i]['status']        = $v['status'];
            $i++;
        }

        $result['data']  = $new_list;
        $result['recordsFiltered'] = $result['recordsTotal'] = $total_count;
        return json_encode($result);
    }


    //账号新增
    public function accountAdd(Request $request)
    {
        if ($request->method() == 'POST') {
            //参数验证
            $rules_msgs = accountRulesMsgs('accountAdd');
            $this->addEditValidate($request, $rules_msgs['rules'], $rules_msgs['msgs'], 'add');

            $res = Account::insert($this->data['data']);
            if ($res !== false) {
                msg(self::$success, '添加成功');
            }
            msg('0300', '添加失败');
        }

        return view('datafeed.accountAdd')->with([
            'tree_menu' => 'account',
        ]);
    }

    //账号编辑
    public function accountEdit(Request $request)
    {
        if ($request->method() == 'POST') {

            //参数验证
            $rules_msgs = accountRulesMsgs('accountEdit');
            $this->addEditValidate($request, $rules_msgs['rules'], $rules_msgs['msgs']);

            $res = Account::where('account_id', $this->data['params']['account_id'])->update($this->data['data']);
            if ($res !== false) {
                msg(self::$success, '更新成功');
            }
            msg('0320', '更新失败');
        }

        return view('datafeed.accountEdit')->with([
            'tree_menu'    => 'account',
            'account_info' => Account::find($request->input('id', 0)),
        ]);
    }


    //账号状态更新
    public function updateStatus(Request $request)
    {
        //参数验证
        $rules_msgs = accountRulesMsgs('updateStatus');
        $params    = $request->all();

        $validator = Validator::make($params, $rules_msgs['rules'], $rules_msgs['msgs']);
        if ($validator->fails()) {
            msg('0400', $validator->errors()->first());
        }

        //更新
        $user = Auth::user();
        $res  = Account::whereIn('account_id',$params['account_id_list'])->update([
            'status'      => $params['status'],
            'update_time' => date('Y-m-d H:i:s', time()),
            'updator'     => $user['id']
        ]);

        if($res !== false) {
            msg(self::$success, '更新成功');
        }
        msg('0320', '更新失败');
    }




    private function addEditValidate($request, $rules=[], $msgs=[], $type=''){
        $params = $request->all();
        if ( $type != 'add' ) {
            if ($params['account_id']){
                $rules['name'] .= ',' . $params['account_id'] . ',account_id';
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
            'name'         => $params['name'],
            'username'     => $params['username'],
            'password'     => $params['password'],
            'updator'      => $user['id'],
            'update_time'  => $now,
            'desc'         => empty($params['desc']) ? '' : $params['desc'],
        ];

        if ( $type == 'add' ) {
            $this->data['data']['creator']     = $user['id'];
            $this->data['data']['create_time'] = $now;
        }
    }



}
