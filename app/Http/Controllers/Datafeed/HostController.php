<?php

namespace App\Http\Controllers\Datafeed;

use App\Models\Link\Host;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use Auth;
use Validator;

use Illuminate\Support\Facades\DB;

class HostController extends Controller
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


    //列表--视图
    public function hostList()
    {
        return view('host.hostList')->with('tree_menu','host');
    }




    //列表
    public function getHostList(Request $request)
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
            'status'       => $request->input('status', ''),
            'host_name'  => $request->input('host_name',''),
            'start'   => $request->input('start', 0),
            'length'  => $request->input('length', 10)
        ];

        $total_count = Host::getIns()->getDataList($params, true);
        if(empty($total_count)){
            return json_encode($result);
        }

        $data_list = Host::getIns()->getDataList($params);

        //返回前端数据 -- 组装
        $new_list = [];
        $i = 0;
        foreach ($data_list as $k => $v) {
            $new_list[$i]['host_id']            = $v['host_id'];
            $new_list[$i]['host_name']  = $v['host_name'];
            $new_list[$i]['create_time']   = $v['create_time'];
            $new_list[$i]['update_time']   = $v['update_time'];
            $new_list[$i]['status']        = $v['status'];
            $i++;
        }

        $result['data']  = $new_list;
        $result['recordsFiltered'] = $result['recordsTotal'] = $total_count;
        return json_encode($result);
    }



    //修改
    public function hostEdit(Request $request)
    {
        if ($request->method() == 'POST') {

            $params = [
                'host_id'  => $request->input('host_id',''),
                'host_name'  => strtolower(trim($request->input('host_name',''))),
            ];

            if(empty($params['host_id'])){
                msg('0320', '域名编号不能为空');
            }
            if(empty($params['host_name']) || strlen($params['host_name']) > 200){
                msg('0320', '域名不能为空且不长度超过200字符');
            }

            $count = Host::getIns()->where('host_name',$params['host_name'])->where('host_id','<>',$params['host_id'])->count();
            if($count > 0){
                msg('0320', '已经存在此域名信息');
            }

            $update = ['host_name'=>$params['host_name'],'update_time'=>date('Y-m-d H:i:s')];
            $res = Host::where('host_id', $params['host_id'])->update($update);
            if ($res !== false) {
                msg(self::$success, '更新成功');
            }
            msg('0320', '更新失败');
        }

        return view('host.hostEdit')->with([
            'tree_menu'       => 'host',
            'hostInfo' => Host::find($request->input('host_id', 0)),
        ]);
    }

    //新增
    public function hostAdd(Request $request)
    {

        if ($request->method() == 'POST') {

            $params = [
                'host_id'  => $request->input('host_id',''),
                'host_name'  => strtolower(trim($request->input('host_name',''))),
            ];

            if(empty($params['host_name']) || strlen($params['host_name']) > 200){
                msg('0320', '域名不能为空且不长度超过200字符');
            }

            $count = Host::getIns()->where('host_name',$params['host_name'])->count();
            if($count > 0){
                msg('0320', '已经存在此域名信息');
            }

            $user = Auth::user();
            $insert = [];
            $insert['host_name'] = $params['host_name'];
            $insert['create_time'] = date('Y-m-d H:i:s');
            $insert['update_time'] = date('Y-m-d H:i:s');
            $insert['creator'] = $user['id'];
            $insert['updator'] = $user['id'];
            $res = Host::getIns()->insert($insert);
            if ($res !== false) {
                msg(self::$success, '新增成功');
            }

            msg('0320', '新增失败');
        }

        return view('host.hostAdd')->with([
            'tree_menu' => 'host',
        ]);
    }


    //状态更新
    public function updateStatus(Request $request)
    {
        //参数验证
        $params    = $request->all();
        $user = Auth::user();
        $update = [
            'update_time' => date('Y-m-d H:i:s', time()),
            'updator'     => $user['id']
        ];
        if(isset($params['status'])){
            $update['status']   = $params['status'];
        }
        //更新
        $res  = Host::whereIn('host_id',$params['host_id_list'])->update($update);

        if($res !== false) {
            msg(self::$success, '更新成功');
        }
        msg('0320', '更新失败');
    }

}
