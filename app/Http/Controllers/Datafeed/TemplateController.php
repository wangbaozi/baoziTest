<?php

namespace App\Http\Controllers\Datafeed;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use App\Models\Link\FileDb;
use Auth;
use Validator;

use Illuminate\Support\Facades\DB;

class TemplateController extends Controller
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

    //获取所有有效映射模板
    public function getValidTemplate(){
        $params['status'] = 1;
        $data_list = FileDb::getIns()->getDataList($params);

        if (empty($data_list)) {
            msg(self::$success, '更新成功');
        }

        $new_list = [];
        $i = 0;
        foreach ($data_list as $k => $v) {
            $new_list[$i]['id']      = $v['fd_id'];
            $new_list[$i]['fd_name'] = $v['fd_name'];
            $i++;
        }
        msg(self::$success, '更新成功', $new_list);
    }

    //账号管理列表--视图
    public function templateList()
    {
        return view('datafeed.templateList')->with('tree_menu','template');
    }


    //模板管理列表
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
            'lk_fd_id' => $request->input('fd_id', ''),
            'lk_fd_name' => $request->input('fd_name', ''),
            'lk_desc'    => $request->input('desc', ''),
            'status'     => $request->input('status', 0),
            'start'      => $request->input('start', 0),
            'length'     => $request->input('length', 10)
        ];

        $total_count = FileDb::getIns()->getDataList($params, true);
        if(empty($total_count)){
            return json_encode($result);
        }

        $data_list = FileDb::getIns()->getDataList($params);

        //返回前端数据 -- 组装
        $new_list = [];
        $i = 0;
        foreach ($data_list as $k => $v) {
            $new_list[$i]['id']            = $v['fd_id'];
            $new_list[$i]['name']          = $v['fd_name'];
            $new_list[$i]['desc']          = strlen($v['desc'])>90 ? substr($v['desc'], 0, 90).'...' : $v['desc'];
            $new_list[$i]['all_desc']      = $v['desc'];
            $new_list[$i]['update_time']   = $v['update_time'];
            $new_list[$i]['create_time']   = $v['create_time'];
            $new_list[$i]['status']        = $v['status'];
            $i++;
        }

        $result['data']  = $new_list;
        $result['recordsFiltered'] = $result['recordsTotal'] = $total_count;
        return json_encode($result);
    }


    //模板新增
    public function templateAdd(Request $request)
    {
        if ($request->method() == 'POST') {
            //参数验证
            $rules_msgs = templateRulesMsgs('templateAdd');
            $this->addEditValidate($request, $rules_msgs['rules'], $rules_msgs['msgs'], 'add');

            $res = FileDb::insert($this->data['data']);
            if ($res !== false) {
                msg(self::$success, '添加成功');
            }
            msg('0300', '添加失败');
        }

        return view('datafeed.templateAdd')->with([
            'tree_menu'        => 'template',
            'table_field_list' => getTableFields('product'),
            'must_field_list'  => getTableMustFields('product'),
        ]);
    }

    //模板编辑
    public function templateEdit(Request $request)
    {
        if ($request->method() == 'POST') {
            //参数验证
            $rules_msgs = templateRulesMsgs('templateEdit');
            $this->addEditValidate($request, $rules_msgs['rules'], $rules_msgs['msgs']);

            $res = FileDb::where('fd_id', $this->data['params']['fd_id'])->update($this->data['data']);
            if ($res !== false) {
                msg(self::$success, '更新成功');
            }
            msg('0320', '更新失败');
        }

        $template_info = FileDb::find($request->input('id', 0));
        $template_info['file_db'] = empty($template_info['file_db']) ? [] : json_decode($template_info['file_db'], true);

        return view('datafeed.templateEdit')->with([
            'tree_menu'        => 'template',
            'table_field_list' => getTableFields('product'),
            'must_field_list'  => getTableMustFields('product'),
            'template_info'    => $template_info,
        ]);
    }


    //模板更新
    public function updateStatus(Request $request)
    {
        //参数验证
        $rules_msgs = templateRulesMsgs('updateStatus');
        $params     = $request->all();
        $validator  = Validator::make($params, $rules_msgs['rules'], $rules_msgs['msgs']);
        if ($validator->fails()) {
            msg('0400', $validator->errors()->first());
        }

        //更新
        $user = Auth::user();
        $res  = FileDb::whereIn('fd_id',$params['template_id_list'])->update([
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
            if ($params['fd_id']) {
                $rules['fd_name'] .= ',' . $params['fd_id'] . ',fd_id';
            }
        }

        //数据表字段描述 -> 表字段
        $table_field_list = getTableFields('product');

        //表字段 -> 数据表字段描述
        $field_list = array_flip($table_field_list);

        //数据表必填字段
        $must_table_fields = getTableMustFields('product');

        //必填字段验证规则组装
        if (!empty($must_table_fields)) {
            foreach ($must_table_fields as $k => $v){
                $must_rules[$v]   = 'required';
                if (!empty($field_list[$v]) && ($field_list[$v]!='-') ) {
                    $must_msgs[$v.'.required'] = $field_list[$v].'必填';
                }
            }
            $rules = array_merge($rules, $must_rules);
            $msgs  = array_merge($msgs,  $must_msgs);
        }

        $validator = Validator::make($params, $rules, $msgs);

        if ($validator->fails()) {
            msg('0400', $validator->errors()->first());
        }

        //模板映射数据准备
        foreach ($table_field_list as $k => $v) {
            if(!empty($params[$v])){
                $template_data[$v] = $params[$v];
            }
        }

        $now  = date('Y-m-d H:i:s', time());
        $user = Auth::user();

        $this->data['params'] = $params;
        $this->data['data'] = [
            'fd_name'      => $params['fd_name'],
            'fd_type'      => $params['fd_type'],
            'fd_prefix'    => $params['fd_prefix'],
            'file_db'      => json_encode($template_data),
            'desc'         => empty($params['desc']) ? '' : $params['desc'],
            'updator'      => $user['id'],
            'update_time'  => $now,
        ];

        if ( $type == 'add' ) {
            $this->data['data']['creator']     = $user['id'];
            $this->data['data']['create_time'] = $now;
        }
    }



}
