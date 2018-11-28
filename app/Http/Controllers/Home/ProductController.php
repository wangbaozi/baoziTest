<?php

namespace App\Http\Controllers\Home;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ProductController extends Controller
{
    public $data;

    public function __construct()
    {
        $this->middleware('auth');
    }



    //品类管理列表
    public function brandList()
    {
        return view('product.suppList')->with('tree_menu','brand');
    }

    //商家管理列表
    public function suppList()
    {
        return view('product.suppList')->with('tree_menu','supp');
    }

    //商家管理列表
    public function suppAdd(Request $request)
    {
        if ($request->method()=='POST') {

            return json_encode($request->input());

        }
        return view('product.suppAdd')->with('tree_menu','supp');
    }


    //商家管理列表
    public function getSuppList(Request $request)
    {

        //dd($request->input());
        $data = [
            ['id' => 1, 'name' => 'wangbao', 'year' => 1993, 'country' => 'China', 'phone' => '18017872797', 'qq' => '2361804010', 'status' => 0],
            ['id' => 2, 'name' => 'baozi', 'year' => 1992, 'country' => 'China', 'phone' => '18017872790', 'qq' => '1172846540', 'status' => 1],
            ['id' => 3, 'name' => 'wangbao', 'year' => 1993, 'country' => 'China', 'phone' => '18017872797', 'qq' => '2361804010', 'status' => 1],
            ['id' => 4, 'name' => 'baozi', 'year' => 1992, 'country' => 'China', 'phone' => '18017872790', 'qq' => '1172846540', 'status' => 0],
            ['id' => 5, 'name' => 'wangbao', 'year' => 1993, 'country' => 'China', 'phone' => '18017872797', 'qq' => '2361804010', 'status' => 1],
            ['id' => 6, 'name' => 'baozi', 'year' => 1992, 'country' => 'China', 'phone' => '18017872790', 'qq' => '1172846540', 'status' => 1],
            ['id' => 7, 'name' => 'wangbao', 'year' => 1993, 'country' => 'China', 'phone' => '18017872797', 'qq' => '2361804010', 'status' => 1],
            ['id' => 8, 'name' => 'baozi', 'year' => 1992, 'country' => 'China', 'phone' => '18017872790', 'qq' => '1172846540', 'status' => 1],
            ['id' => 9, 'name' => 'wangbao', 'year' => 1993, 'country' => 'China', 'phone' => '18017872797', 'qq' => '2361804010', 'status' => 1],
            ['id' => 10, 'name' => 'baozi', 'year' => 1992, 'country' => 'China', 'phone' => '18017872790', 'qq' => '1172846540', 'status' => 1],
        ];

        $return_data = [
            'data'   => $data,
            'total'  => 30,
            'params' => $request->input()
        ];
        return json_encode($return_data);
    }


}
