<?php

namespace App\Http\Middleware;

use App\Models\Basic\AdminUser;
use Closure;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VerifyPermiss
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $adminUser = Auth::user();

        if(empty($adminUser)){
           redirect('/login');
           return $next($request);
        }

        $verify_result = $this->loginPermiss($adminUser);
        if (!$verify_result['status']) {
            //弹出提示框
            /*$message = '此操作您没有权限, 权限ID：' . $verify_result['id'];
            $this->errormsg($message);*/
            //跳转页面
            $message = '此操作您没有权限, 权限ID：' . $verify_result['id'];

            $data = [
                'message'=>$message,
                'waitSecond'=>1,
                'jumpUrl'=>'javascript:history.back(-1);'
            ];

            return response()
                ->view('errormsg',$data,200)
                ->header('Content-Type', 'x-www-form-urlencoded');
        }

        return $next($request);
    }

    public function loginPermiss($adminUser){

        $result = [
            'status'=>true,
            'id'=>0,
        ];
        //1.获取到当前操作的控制器和方法名
        $currentAction = request()->route()->uri();

        $admin_id = $adminUser->id;  //登录账号为超级管理员
        $role_id = $adminUser->role_id; //所属角色为超级管理员

        if(!empty($currentAction)){

            if($currentAction == 'home' || $currentAction == 'login' || $currentAction == 'register'  || $admin_id == 1 || $role_id == 1){  //role_id =1的为超级管理员
                return $result;
            }
            $currentMothod = explode('/',$currentAction);

            $mod_name = isset($currentMothod[0]) ? $currentMothod[0] :'';
            $op_name  = isset($currentMothod[1]) ? $currentMothod[1] : '';

            //2.根据roleid 控制器，方法名 查询对应的权限

            $data = DB::table('sl_permissions as p')
                ->leftJoin('sl_role_permissions as rp','rp.p_id','=','p.id')
                ->select('p.*','rp.roleid')
                ->where('rp.roleid',$role_id)
                ->where('p.mod_name',$mod_name)
                ->where('p.op_name',$op_name)
                ->first();


            //3.如果未查出到权限 根据控制器 方法名 查询对应的权限返回
            if(!$data){
                $permiss = DB::table('sl_permissions')
                    ->where('mod_name',$mod_name)->where('op_name',$op_name)
                    ->first();
                if($permiss){
                    $result['status'] = false;
                    $result['id'] = $permiss->id;
                }

            }
            return $result;

        }

    }

    /**
     * @param 权限验证提醒
     */
    public function errormsg($message)
    {
        //return    redirect('/register');

        echo '<script type="text/javascript">';
        echo 'alert("' . $message . '");';
        echo 'javascript:history.back();';
        echo '</script>';
        exit;

    }
}
