<?php

namespace App\Http\Controllers\Home;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use Auth;
use App\Models\Basic\Brand;
use App\Models\Basic\Product;
use App\Models\Basic\Category;
use App\Models\Basic\Supp;

class HomeController extends Controller
{
    public $data;

    private static $success = '0200';
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $update_info = $list = [];
        //更新时间,update_date = 昨天
        $update_info['update_date'] = date('Y-m-d',strtotime("-1 day"));
        $update_info['today'] = date('Y-m-d');//当天
        $update_info['tomorrow'] = date('Y-m-d',strtotime("+1 day"));//明天
        //更新商品数量
        $update_info['product_count'] = Product::getIns()->whereBetween('update_time',[$update_info['update_date'],$update_info['today']])->count();
        //更新品牌数量
        $update_info['brand_count'] = Brand::getIns()->whereBetween('update_time',[$update_info['update_date'],$update_info['today']])->count();
        //更新品类数量
        $update_info['category_count'] = Category::getIns()->whereBetween('update_time',[$update_info['update_date'],$update_info['today']])->count();
        //更新商家数量
        $update_info['supp_count'] = Supp::getIns()->whereBetween('update_time',[$update_info['update_date'],$update_info['today']])->count();


        $list['count'] = 0;//默认


        return view('home')->with([
            'tree_menu'=>'home',
            'update_info'=>$update_info,
            'list'=>$list,
        ]);
    }

    public function getList(Request $request){

        $result = [
            'code'   => self::$success,
            'msg'    => '数据获取成功',
            'data'   => [],
            'recordsFiltered' => 0,
            'recordsTotal'    => 0,
            'draw'            => $request->input('draw', 1)
        ];
        $params = [
            'search_type'                => $request->input('search_type'),
            'key'                => $request->input('key', ''),
            'status'                => $request->input('status', 0),
            'start'                 => $request->input('start', 0),
            'length'                => $request->input('length', 10)
        ];
        $result['recordsTotal'] = $result['recordsFiltered'] = Product::getIns()->getHomeList($params,true);
        $result['data'] = Product::getIns()->getHomeList($params);
        return json_encode($result);
    }


}
