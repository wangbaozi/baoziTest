<?php

namespace App\Http\Controllers\Home;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use App\Models\Basic\BrandRela;
use Excel;
use Auth;
use Validator;


use Illuminate\Support\Facades\DB;
//标准品牌控制器

class BrandRelaController extends Controller
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


    //标准品牌管理列表
    public function brandRelaList()
    {
        return view('brandRela.brandRelaList')->with('tree_menu','brandRela');
    }

    //模糊查询标准品牌名
    public function getValidBrandRela(Request $request){
        $params = [
            'lk_brand_name_en' => $request->input('brand_name_en', ''),
            'status'           => 1,
            'start'            => 0,
            'length'           => 50
        ];
        $data_list = BrandRela::getIns()->getDataList($params);

        if (empty($data_list)) {
            msg(self::$success, '获取数据成功');
        }

        $new_list = [];
        $i = 0;
        foreach ($data_list as $k => $v) {
            $new_list[$i]['id']   = $v['brand_id'];
            $new_list[$i]['name'] = $v['brand_name_en'];
            $i++;
        }
        msg(self::$success, '获取数据成功', $new_list);
    }


    //标准品牌管理列表
    public function getBrandRelaList(Request $request)
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
            'lk_brand_id' => $request->input('brand_id', ''),
            'lk_brand_name_en' => $request->input('brand_name_en', ''),
            'status'           => $request->input('status', 0),
            'start'            => $request->input('start', 0),
            'length'           => $request->input('length', 10)
        ];

        $total_count = BrandRela::getIns()->getDataList($params, true);
        if(empty($total_count)){
            return json_encode($result);
        }

        $data_list = BrandRela::getIns()->getDataList($params);

        //返回前端数据 -- 组装
        $new_list = [];
        $i = 0;
        foreach ($data_list as $k => $v) {
            $new_list[$i]['id']            = $v['brand_id'];
            $new_list[$i]['brand_name_en'] = empty($v['brand_name_en']) ? '-' : $v['brand_name_en'];
            $new_list[$i]['brand_name_cn'] = empty($v['brand_name_cn']) ? '-' : $v['brand_name_cn'];
            $new_list[$i]['brand_desc_en'] = empty($v['brand_desc_en']) ? '-' : $v['brand_desc_en'];
            $new_list[$i]['brand_desc_cn'] = empty($v['brand_desc_cn']) ? '-' : $v['brand_desc_cn'];
            $new_list[$i]['update_time']   = empty($v['update_time'])   ? '-' : $v['update_time'];
            $new_list[$i]['status']        = $v['status'];
            $i++;
        }

        $result['sql_list'] = response()->json(DB::getQueryLog());
        $result['data']  = $new_list;
        $result['recordsFiltered'] = $result['recordsTotal'] = $total_count;
        return json_encode($result);
    }


    //标准品牌新增
    public function brandRelaAdd(Request $request)
    {
        if ($request->method() == 'POST') {

            //参数验证
            $rules_msgs = brandRelaRulesMsgs('brandRelaAdd');
            $this->addEditValidate($request, $rules_msgs['rules'], $rules_msgs['msgs'], 'add');

            $res = BrandRela::insert($this->data['data']);
            if ($res !== false) {
                msg(self::$success, '添加成功');
            }
            msg('0300', '添加失败');
        }

        return view('brandRela.brandRelaAdd')->with([
            'tree_menu' => 'brandRela',
        ]);
    }

    //标准品牌编辑
    public function brandRelaEdit(Request $request)
    {
        if ($request->method() == 'POST') {

            //参数验证
            $rules_msgs = brandRelaRulesMsgs('brandRelaEdit');
            $this->addEditValidate($request, $rules_msgs['rules'], $rules_msgs['msgs']);  //参数验证

            //兼容历史标准品牌数据
            $brand_rela_info = BrandRela::find($this->data['params']['brand_id']);
            if (empty($brand_rela_info)) {
                msg('0410', '该标准品牌不存在');
            }
            $brand_rela_list = BrandRela::where(
                'brand_name_en', $brand_rela_info['brand_name_en']
            )->get(['brand_id', 'status'])->toarray();

            if (empty($brand_rela_list)) {
                msg('0420', '该标准品牌不存在');
            }

            $res = BrandRela::whereIN('brand_id', array_column($brand_rela_list, 'brand_id'))->update($this->data['data']);
            if ($res !== false) {
                msg(self::$success, '更新成功');
            }
            msg('0320', '更新失败');
        }

        return view('brandRela.brandRelaEdit')->with([
            'tree_menu'       => 'brandRela',
            'brand_rela_info' => BrandRela::find($request->input('id', 0)),
        ]);
    }


    //标准品牌状态更新
    public function updateStatus(Request $request)
    {
        //参数验证
        $rules_msgs = brandRelaRulesMsgs('updateStatus');
        $params     = $request->all();
        $validator  = Validator::make($params, $rules_msgs['rules'], $rules_msgs['msgs']);
        if ($validator->fails()) {
            msg('0400', $validator->errors()->first());
        }

        //更新
        $user = Auth::user();
        $res  = BrandRela::whereIn('brand_id',$params['brand_rela_id_list'])->update([
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
        $params    = $request->all();

        $brand_id = ( ($type != 'add') && !empty($params['brand_id']) ) ? $params['brand_id'] : 0;
        $rules['brand_name_en'] .= ',' . $brand_id . ',brand_id,is_valid,1';

        $validator = Validator::make($params, $rules, $msgs);

        if ($validator->fails()) {
            msg('0400', $validator->errors()->first());
        }

        $now  = date('Y-m-d H:i:s', time());
        $user = Auth::user();

        $this->data['params'] = $params;
        $this->data['data'] = [
            'brand_name_en' => $params['brand_name_en'],
            'brand_region'  => empty($params['brand_region'])  ? '' : $params['brand_region'],
            'brand_desc_en' => empty($params['brand_desc_en']) ? '' : $params['brand_desc_en'],
            'brand_name_cn' => empty($params['brand_name_cn']) ? '' : $params['brand_name_cn'],
            'brand_desc_cn' => empty($params['brand_desc_cn']) ? '' : $params['brand_desc_cn'],
            'brand_logo'    => empty($params['brand_logo'])    ? '' : $params['brand_logo'],
            'updator'       => $user['id'],
            'update_time'   => $now,
        ];

        if ( $type == 'add' ) {
            $this->data['data']['creator']     = $user['id'];
            $this->data['data']['create_time'] = $now;
        }
    }



}
