<?php

namespace App\Http\Controllers\Home;

use App\Models\Basic\Category;
use App\Models\Basic\CategoryRela;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;

class CategoryController extends Controller
{
    public $data;
    private static $success = '0200';


    public function __construct()
    {
        $this->middleware('auth');
    }



    //品类管理列表
    public function categoryList()
    {
        return view('category.categoryList')->with('tree_menu','category');
    }

    /**
     * @return string
     */
    public function getCategoryList(Request $request){

        $result = [
            'code'   => self::$success,
            'msg'    => '数据获取成功',
            'data'   => [],
            'recordsFiltered' => 0,
            'recordsTotal'    => 0,
            'draw'            => $request->input('draw', 1)
        ];

        $params = [
            'lk_category_id' => $request->input('lk_category_id', ''),
            'lk_category_name' => $request->input('lk_category_name', ''),
            'lk_category_rela_name'       => $request->input('lk_category_rela_name', 0),
            'status'       => $request->input('status', 0),
            'start'        => $request->input('start', 0),
            'length'        => $request->input('length', 10)
        ];

        $total_count = Category::getIns()->getBaseCategoryList($params,true);
        if(empty($total_count)){
            return json_encode($result);
        }

        $category_list = Category::getIns()->getBaseCategoryList($params);

        //返回前端数据
        $new_list = [];

        foreach ($category_list as $k => $v) {

            $new_list[$k]['id']               = $v->cat_id;
            $new_list[$k]['cat_name_en']      = empty($v->cat_name_en) ? '-' : $v->cat_name_en;
            $new_list[$k]['cat_name_cn']      = empty($v->cat_name_cn) ? '' : $v->cat_name_cn;
            $new_list[$k]['cat_rela_id']      = empty($v->cat_relation) ? '' : $v->cat_relation;
            $new_list[$k]['cat_relation']     = empty($v->rela_name_en)     ? '-' : $v->rela_name_en;
            $new_list[$k]['status']           = empty($v->status)     ? '-' : $v->status;

        }

        $result['data']  = $new_list;
        $result['recordsFiltered'] = $result['recordsTotal'] = $total_count;
        return json_encode($result);
    }

    //品类管理列表
    public function setCategory(Request $request)
    {
        $cat_id = $request->input('cat_id');
        $rela_id = $request->input('rela_id');

        $firstCategoryReal = CategoryRela::getIns()->getFirstCategory();
        //根据rela_id查询对应的分类属性
        $CategoryRelaList = CategoryRela::getIns()->getCategoryRelaList();

        //三级类目
        if(!empty($rela_id) && $rela_id > 0){

            $threeCategory = CategoryRela::getIns()->getParentCategory($rela_id);
        }

        //二级类目
        if(!empty($threeCategory['cat_parent'])){
            $secondCategory = CategoryRela::getIns()->getParentCategory($threeCategory['cat_parent']);

            //一级类目
            if(!empty($secondCategory['cat_parent'])){
                $firstCategory = CategoryRela::getIns()->getParentCategory($secondCategory['cat_parent']);
            }
        }



        $params = [
            'cat_id'=>$cat_id,
            'rela_id'=>$rela_id,
            'threeCategory'=> !empty($threeCategory) ? $threeCategory['cat_name_en'] :'',
            'secondCategory'=>!empty($secondCategory)? $secondCategory['cat_name_en'] :'' ,
            'firstCategory'=>!empty($firstCategory) ? $firstCategory['cat_name_en'] :'',
        ];

        return view('category.setCategory')->with('firstCategoryReal',$firstCategoryReal)->with('params',$params);
    }

    //获取二级分类
    public function getSecondCategory(Request $request){
        $parent_id = $request->input('parent_id');
        $categoryRela = CategoryRela::where('cat_parent',$parent_id)->get();

        if(empty($categoryRela)){
            msg('0100','未获取到数据');
        }

        foreach ($categoryRela as $key => $val){
            $category_list[$key] =[
                    'cat_id'=> $val['cat_id'],
                    'cat_name_en'=> $val['cat_name_en'],
                ];
        }

        msg('0200','数据获取成功', [
            'data'  => $category_list,
        ]);

    }

    //获取二级分类
    public function updateCategory(Request $request){

        if ($request->method()=='POST'){
            $cat_id = $request->input('cat_id');
            $cat_relation_id = $request->input('cat_relation_id');
            if(empty($cat_relation_id)){
                msg('0310', '标准名称不能为空,请选择');
            }

            $update_data = [
                'cat_relation'   => $cat_relation_id,
                'update_time'   => date('Y-m-d H:i:s',time()),

            ];

            $res = Category::where('cat_id',$cat_id)->update($update_data);

            if($res !== false) {
                $this->updateOtherCategory($cat_id,$cat_relation_id);

                msg('0200', '更新成功');
            }
            msg('0320', '更新失败');
        }


    }

    /**
     * 更新相同名称的其他分类的cat_relation
     * @param $cat_id
     * @param $cat_relation_id
     * @return mixed
     */
    public function updateOtherCategory($cat_id,$cat_relation_id){
        $currenyCategory = Category::where('cat_id',$cat_id)->first();
        if(!empty($currenyCategory)){
            $cat_name_en = $currenyCategory -> cat_name_en;
        }

        $update_data = [
            'cat_relation'   => $cat_relation_id,
            'update_time'   => date('Y-m-d H:i:s',time()),
        ];

        $res = Category::where('cat_name_en',$cat_name_en)->where('is_valid',0)->update($update_data);

        return $res;
    }

}
