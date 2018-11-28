<?php

namespace App\Http\Controllers\Home;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use App\Models\Basic\Brand;
use App\Models\Basic\BrandRela;
use Auth;
use Validator;


use Illuminate\Support\Facades\DB;

//品牌控制器
class BrandController extends Controller
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


    //品牌管理列表
    public function brandList()
    {
        return view('brand.brandList')->with('tree_menu','brand');
    }

    //设置标准品牌
    public function setBrandRela(Request $request)
    {
        if ($request->method() == 'POST') {
            //参数验证
            $rules_msgs = brandRulesMsgs('setBrandRela');
            $params     = $request->all();
            $validator  = Validator::make($params, $rules_msgs['rules'], $rules_msgs['msgs']);
            if ($validator->fails()) {
                msg('0400', $validator->errors()->first());
            }

            //兼容历史品牌数据
            $brand_info = Brand::find($params['brand_id']);
            if (empty($brand_info)) {
                msg('0410', '该品牌不存在');
            }
            $brand_list = Brand::where(
                'brand_name_en', $brand_info['brand_name_en']
            )->get(['brand_id', 'status'])->toarray();

            if (empty($brand_list)) {
                msg('0420', '该品牌不存在');
            }

            //更新
            $user = Auth::user();
            $res  = Brand::whereIN('brand_id', array_column($brand_list, 'brand_id'))->update([
                'brand_relation' => $params['rela_id'],
                'update_time'    => date('Y-m-d H:i:s', time()),
                'updator'        => $user['id']
            ]);

            if($res !== false) {
                msg(self::$success, '更新成功');
            }
            msg('0320', '更新失败');
        }

        return view('brand.setBrandRela')->with([
            'tree_menu'       => 'brand',
            'brand_id'        => $request->input('brand_id', 0),
            'brand_rela_info' => BrandRela::find($request->input('rela_id', 0)),
        ]);
    }




    //品牌管理列表数据
    public function getBrandList(Request $request)
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
            'lk_brand_rela_name_en' => $request->input('brand_rela_name_en', ''),
            'lk_brand_name_en'      => $request->input('brand_name_en', ''),
            'lk_brand_id'      => $request->input('brand_id', ''),
            'brand_relation'        => $request->input('brand_relation', ''),
            'status'                => $request->input('status', 0),
            'start'                 => $request->input('start', 0),
            'length'                => $request->input('length', 10)
        ];

        $total_count = Brand::getIns()->getBrandRelaDataList($params, true);
        if(empty($total_count)){
            $result['sql_list'] = response()->json(DB::getQueryLog());
            return json_encode($result);
        }

        $data_list = Brand::getIns()->getBrandRelaDataList($params);


        //返回前端数据 -- 组装
        $new_list = [];
        $i = 0;
        foreach ($data_list as $k => $v) {
            $new_list[$i]['id']                 = $v->brand_id;
            $new_list[$i]['brand_rela_id']      = empty($v->brand_relation) ? 0 : $v->brand_relation;
            $new_list[$i]['brand_name_en']      = empty($v->brand_name_en)      ? '-' : $v->brand_name_en;
            $new_list[$i]['brand_rela_name_en'] = empty($v->brand_rela_name_en) ? '-' : $v->brand_rela_name_en;
            $new_list[$i]['status']             = $v->status;
            $i++;
        }

        $result['sql_list'] = response()->json(DB::getQueryLog());
        $result['data']  = $new_list;
        $result['recordsFiltered'] = $result['recordsTotal'] = $total_count;
        return json_encode($result);
    }







}
