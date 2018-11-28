<?php

namespace App\Http\Controllers\Home;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Basic\AdminRole;
use App\Models\Basic\AdminUser;
use App\Models\Basic\Permissions;
use App\Models\Basic\PermissionsGroups;
use App\Models\Basic\RolePermissions;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Auth;
use Validator;


class ManagerController extends Controller
{
    public $data;
    private static $success = '0200';

    public function __construct()
    {

        $this->middleware('auth');
        //$this->middleware('verifyPermiss');

        if(env('APP_ENV') == 'test') {
            DB::enableQueryLog();
        }

    }

    /**
     * 管理员列表
     * @return $this
     */
    public function adminList()
    {
        return view('manager.adminList')->with('tree_menu','admin');
    }

    /**
     * 管理员列表
     * @param Request $request
     * @return string
     */
    public function getAdminList(Request $request){
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
            'lk_truename' => $request->input('truename', ''),
            'status'       => $request->input('status', 0),
            'start'        => $request->input('start', 0),
            'length'        => $request->input('length', 10)
        ];

        $total_count = AdminUser::getIns()->getDataList($params, true);
        if(empty($total_count)){
            return json_encode($result);
        }

        $data_list = AdminUser::getIns()->getDataList($params);
        //角色列表
        $adminRole = AdminRole::where('is_deleted','N')->get(['id','title'])->toArray();
        $adminRoleMap = array_combine(array_column($adminRole,'id'),array_column($adminRole,'title'));
        //返回前端数据
        $new_list = [];

        foreach ($data_list as $k => $v) {
            $new_list[$k] = $v;
            $new_list[$k]['role_name'] = !empty($adminRoleMap[$v['role_id']]) ? $adminRoleMap[$v['role_id']] :'-';

        }

        $result['data']  = $new_list;
        $result['recordsFiltered'] = $result['recordsTotal'] = $total_count;
        return json_encode($result);
    }


    /**管理员新增
     * @param Request $request
     * @return $this
     */
    public function adminAdd(Request $request){

        if ($request->method() == 'POST') {

            //参数验证  先不加验证
            if(empty($request->input('name'))){
                msg('0301', '名称不能为空');
            }

            if(empty($request->input('admin_role_id'))){
                msg('0301', '对应的角色不能为空');
            }

            if(empty($request->input('email'))){
                msg('0301', 'email不能为空');
            }

            $pattern = "/^([0-9A-Za-z\\-_\\.]+)@([0-9a-z]+\\.[a-z]{2,3}(\\.[a-z]{2})?)$/i";
            if (!preg_match( $pattern, $request->input('email') ) ){
                msg('0302', 'email格式不正确');
            }


            $id = !empty($request->input('id')) ? $request->input('id') :'';
            $role_id = !empty($request->input('admin_role_id')) ? $request->input('admin_role_id') : '';

            $loginAdmin = Auth::user();
            $default_password = Hash::make('test123');
            $data = [
                'name'=>$request->input('name'),
                'role_id'=>$role_id,
                'truename'=>$request->input('truename'),
                'email'=>$request->input('email'),
                //'remember_token'=>$request->input('remember_token'),
                'mobile'=>$request->input('mobile'),
                'telphone'=>$request->input('telphone'),
                'identitycard'=>$request->input('identitycard'),

            ];

            if(empty($id)){
                $data['status'] = '0';
                $data['created_at']=date('Y-m-d H:i:s');
                $data['updated_at']=date('Y-m-d H:i:s');
                $data['password'] = $default_password;

                $res = AdminUser::insert($data);

            }else{
                $data['updated_at']=date('Y-m-d H:i:s');
                $res = AdminUser::where('id',$id)->update($data);
            }

            if ($res !== false) {
                msg(self::$success, '操作成功');
            }
            msg('0300', '操作失败');
        }

        $dataInfo = [];
        if ($request->method() == 'GET' && !empty($request->input('action')) && $request->input('action') == 'edit'){
            //dd($request->input());
            if(empty($request->input('id'))){
                msg('0300', '操作失败');
            }
            $id = $request->input('id');

            $dataInfo = AdminUser::find($id);

        }

        $adminRole = AdminRole::where('is_deleted','N')->get();

        return view('manager.adminAdd')->with([
            'tree_menu' => 'admin',
            'dataInfo'=>$dataInfo,
            'adminRole'=>$adminRole
        ]);
    }

    /**管理员预览
     * @param Request $request
     * @return $this
     */
    public function adminPerview(Request $request){

        $dataInfo = [];
        if ($request->method() == 'GET'){

            if(empty($request->input('id'))){
                msg('0300', '操作失败');
            }
            $id = $request->input('id');

            $dataInfo = AdminUser::find($id);

        }

        $adminRole = AdminRole::where('is_deleted','N')->get();

        return view('manager.adminPerview')->with([
            'tree_menu' => 'admin',
            'dataInfo'=>$dataInfo,
            'adminRole'=>$adminRole
        ]);
    }

    /**删除权限列表
     * @param Request $request
     */
    public function updateAdminStatus(Request $request){
        if ($request->method() == 'POST') {

            if(empty($request->input('id')) ){
                msg('0301', '操作失败');
            }

            $id = !empty($request->input('id')) ? $request->input('id') :'';
            $loginAdmin = Auth::user();
            if(!empty($id)){
                $data['status'] = $request->input('status');
                $data['updated_at']=date('Y-m-d H:i:s');

                $res = AdminUser::where('id',$id)->update($data);

            }

            if ($res !== false) {
                msg(self::$success, '操作成功');
            }
            msg('0300', '操作失败');
        }

    }


    //管理员重置密码
    public function adminResetPwd(Request $request){

        if($request->method() == 'POST'){
            if(empty($request->input('id')) ){
                msg('0301', '操作失败');
            }
            if(empty($request->input('password'))){
                msg('0302', '新密码不能为空');

            }
            if(empty($request->input('confirm_password'))){
                msg('0302', '确认密码不能为空');
            }

            if($request->input('password') != $request->input('confirm_password')){
                msg('0302', '两次输入密码不一致');

            }

            $id = $request->input('id') ;
            $password = Hash::make($request->input('password'));

            $loginAdmin = Auth::user();
            if(!empty($id)){
                $data = [
                    'password'=>$password,
                    'updated_at'=>date('Y-m-d H:i:s'),
                ];
                $res = AdminUser::where('id',$id)->update($data);

            }

            if ($res !== false) {
                msg(self::$success, '操作成功');
            }
            msg('0300', '操作失败');
        }

        $id = !empty($request->input('id')) ? $request->input('id') :'1';
        $dataInfo = AdminUser::find($id);
        return view('manager.adminResetPwd')->with('tree_menu','admin')->with('dataInfo',$dataInfo);



    }


    /**
     * 角色管理列表
     * @return $this
     */
    public function roleList()
    {
        return view('manager.roleList')->with('tree_menu','role');
    }


    /**获取角色列表
     * @param Request $request
     * @return string
     */
    public function getRoleList(Request $request)
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
            'lk_role_name' => $request->input('role_name', ''),
            'start'        => $request->input('start', 0),
            'length'        => $request->input('length', 10)
        ];

        $total_count = AdminRole::getIns()->getDataList($params, true);
        if(empty($total_count)){
            return json_encode($result);
        }

        $data_list = AdminRole::getIns()->getDataList($params);

        //返回前端数据
        $new_list = [];

        foreach ($data_list as $k => $v) {
            $new_list[$k] = $v;
        }

        $result['data']  = $new_list;
        $result['recordsFiltered'] = $result['recordsTotal'] = $total_count;
        return json_encode($result);
    }

    /**角色新增
     * @param Request $request
     * @return $this
     */
    public function roleAdd(Request $request){

        if ($request->method() == 'POST') {

            //参数验证  先不加验证
            /*$rules_msgs = suppRulesMsgs('suppAdd');
            $this->suppAddEditValidate($request, $rules_msgs['rules'], $rules_msgs['msgs'], 'add');*/
            if(empty($request->input('title'))){
                msg('0301', '角色名称不能为空');
            }

            if(empty($request->input('description'))){
                msg('0302', '角色描述信息不能为空');
            }

            $id = $request->input('role_id') ;

            $loginAdmin = Auth::user();

            $data = [
                'is_deleted' =>  $request->input('is_deleted'),
                'title'=>$request->input('title'),
                'description'=>$request->input('description'),
            ];

            if(empty($id)){

                $data['created_by'] = $loginAdmin['id'];
                $data['created_time']=date('Y-m-d H:i:s');
                $data['updated_by'] = $loginAdmin['id'];
                $data['updated_time']=date('Y-m-d H:i:s');
                $res = AdminRole::insert($data);

            }else{
                $data['updated_by'] = $loginAdmin['id'];
                $data['updated_time']=date('Y-m-d H:i:s');
                $res = AdminRole::where('id',$id)->update($data);
            }

            if ($res !== false) {
                msg(self::$success, '操作成功');
            }
            msg('0300', '操作失败');
        }

        $roleInfo = [];
        if ($request->method() == 'GET' && !empty($request->input('action'))){

            if(empty($request->input('id'))){
                msg('0300', '操作失败');
            }
            $id = $request->input('id');

            $roleInfo = AdminRole::find($id);

        }

        return view('manager.roleAdd')->with([
            'tree_menu' => 'role',
            'roleInfo'=>$roleInfo,
        ]);
    }

    /**删除角色
     * @param Request $request
     */
    public function updateRoleStatus(Request $request){
        if ($request->method() == 'POST') {

            if(empty($request->input('id')) || empty($request->input('is_deleted'))){
                msg('0301', '操作失败');
            }

            $id = $request->input('id');
            $is_deleted = !empty($request->input('is_deleted')) ? $request->input('is_deleted') :'Y';


            $loginAdmin = Auth::user();
            if(!empty($id)){
                $data['is_deleted'] = $is_deleted;
                $data['updated_by'] = $loginAdmin['id'];
                $data['updated_time']=date('Y-m-d H:i:s');

                $res = AdminRole::where('id',$id)->update($data);

            }

            if ($res !== false) {
                msg(self::$success, '操作成功');
            }
            msg('0300', '操作失败');
        }


    }

    /**
     * @return 设置权限的页面
     */

    public function getSetRolePermission(Request $request)
    {

        $roleid = $request->input('roleid');

        return view('manager.setPermission')->with('roleid',$roleid);
    }

    public function loadPermissionList(Request $request){
        $roleid = $request->input('roleid');


        //先获取菜单列表  permissions_groups
        $permissionGroups = PermissionsGroups::where('is_deleted','N')->get()->toArray();

        $permissionList = Permissions::where('is_deleted','N')->get()->toArray();

        $rolePermissionList = RolePermissions::where('roleid',$roleid)->get()->toArray();

        //获取rolepermission 中的对应的权限id
        $permissionids = array_combine(array_column($rolePermissionList,'p_id'),array_column($rolePermissionList,'id'));

        //一级菜单
        foreach ($permissionGroups as $key => $val){
            $parent_menu[] = [
                "id"=> 'parent_'.$val['id'],
                "parent"=> "#",
                "text"=>$val['title'],
                'state' => [
                    'opened'=>true,
                    'selected' => false
                ]
            ];
        }

        //二级菜单
        foreach ($permissionList as $key=>$val){
            $p_id = $val['id'];
            $state =  [];
            //判断当前的p_id 是否在设置的权限列表内 如果存在 需要显示选中状态
            if(!empty($permissionids[$p_id])){
                $state = [
                    'opened'=>true,
                    'selected' => true
                ];
            }

            $child_menu[] = [
                "id"=> $p_id,
                "parent"=> 'parent_'.$val['pg_id'],
                "text"=>$val['title'],
                'state' => $state,
            ];

        }

        $result_data = array_merge($parent_menu,$child_menu);
        return json_encode($result_data);


    }

    /**
     * jstree 保存选中的权限节点
     * @param Request $request
     */
    public function saveSelectPermissions(Request $request){
        if( $request->method() == 'POST'){

            if(empty($request->input('roleid')) || empty($request->input('permissionNodes'))){
                msg('0301', '操作失败');
            }

            $roleid = $request->input('roleid');
            $permissionNodes = $request->input('permissionNodes');
            //print_r($permissionNodes);
            foreach ($permissionNodes as $key => $node){

                if(intval($node)){
                    $permissionIds[] = $node;
                }
            }

            if(empty($permissionIds)){
                msg('0302', '请选择分配的权限');

            }
            $loginAdmin = Auth::user();
            if(RolePermissions::getIns()->saveRolePermission($permissionIds,$roleid,$loginAdmin['id'])){
                msg('0200', '分配权限设置成功');

            }else{
                msg('0303', '分配权限设置失败');

            }

        }




    }

    /**
     * 权限管理列表
     * @return $this
     */
    public function permissionGroupList()
    {
        return view('manager.permissionGroupList')->with('tree_menu','permission');
    }

    /**分组角色列表 一级菜单
     * @param Request $request
     * @return string
     */
    public function getPermissionGroupList(Request $request)
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
            'start'        => $request->input('start', 0),
            'length'        => $request->input('length', 10)
        ];

        $total_count = PermissionsGroups::getIns()->getDataList($params, true);
        if(empty($total_count)){
            return json_encode($result);
        }

        $data_list = PermissionsGroups::getIns()->getDataList($params);

        //返回前端数据
        $new_list = [];

        foreach ($data_list as $k => $v) {
            $new_list[$k] = $v;
        }

        $result['data']  = $new_list;
        $result['recordsFiltered'] = $result['recordsTotal'] = $total_count;
        return json_encode($result);
    }

    /**新增权限组别
     * @param Request $request
     * @return $this
     */
    public function permissionGroupAdd(Request $request){

        if ($request->method() == 'POST') {

            //参数验证  先不加验证
            /*$rules_msgs = suppRulesMsgs('suppAdd');
            $this->suppAddEditValidate($request, $rules_msgs['rules'], $rules_msgs['msgs'], 'add');*/
            if(empty($request->input('title'))){
                msg('0301', '名称不能为空');
            }


            $id = !empty($request->input('id')) ? $request->input('id') :'';

            $loginAdmin = Auth::user();

            $data = [
                'title'=>$request->input('title'),
            ];

            if(empty($id)){
                $data['is_deleted'] = 'N';
                $data['created_by'] = $loginAdmin['id'];
                $data['created_time']=date('Y-m-d H:i:s');
                $data['updated_by'] = $loginAdmin['id'];
                $data['updated_time']=date('Y-m-d H:i:s');
                $res = PermissionsGroups::insert($data);

            }else{
                $data['updated_by'] = $loginAdmin['id'];
                $data['updated_time']=date('Y-m-d H:i:s');
                $res = PermissionsGroups::where('id',$id)->update($data);
            }

            if ($res !== false) {
                msg(self::$success, '操作成功');
            }
            msg('0300', '操作失败');
        }

        $dataInfo = [];
        if ($request->method() == 'GET' && !empty($request->input('action')) && $request->input('action') == 'edit'){

            if(empty($request->input('id'))){
                msg('0300', '操作失败');
            }
            $id = $request->input('id');

            $dataInfo = PermissionsGroups::find($id);

        }

        return view('manager.permissionGroupAdd')->with([
            'tree_menu' => 'permission',
            'dataInfo'=>$dataInfo,
        ]);
    }

    /**删除权限组别
     * @param Request $request
     */
    public function delPermissGroup(Request $request){
        if ($request->method() == 'POST') {

            if(empty($request->input('id')) || empty($request->input('is_deleted'))){
                msg('0301', '操作失败');
            }

            $id = $request->input('id') ;
            $is_deleted = !empty($request->input('is_deleted')) ? $request->input('is_deleted') :'Y';


            $loginAdmin = Auth::user();
            if(!empty($id)){
                $data['is_deleted'] = $is_deleted;
                $data['updated_by'] = $loginAdmin['id'];
                $data['updated_time']=date('Y-m-d H:i:s');

                $res = PermissionsGroups::where('id',$id)->update($data);

                if($res){
                    $exists_permission = Permissions::where('pg_id',$id)->get();
                    if($exists_permission){
                        $permission_res = Permissions::where('pg_id',$id)->update(['is_deleted'=>'Y']);

                    }
                }

            }

            if ($res !== false) {
                //删除permission表中的对应的记录

                msg(self::$success, '操作成功');
            }
            msg('0300', '操作失败');
        }

    }

    /**
     * 权限管理列表
     * @return $this
     */
    public function permissionList(Request $request)
    {
       $pg_id = $request->input('pg_id');

        return view('manager.permissionList')->with('tree_menu','permission')->with('pg_id',$pg_id);
    }

    /**获取角色列表 二级菜单
     * @param Request $request
     * @return string
     */
    public function getPermissionList(Request $request)
    {

        $result = [
            'code'   => self::$success,
            'msg'    => '数据获取成功',
            'data'   => [],
            'recordsFiltered' => 0,
            'recordsTotal'    => 0,
            'draw'            => $request->input('draw', 1)
        ];

        if(empty($request->input('pg_id', 0))){
            $result['code'] = '0310';
            $result['msg'] = '数据获取失败';
            return json_encode($result);
        }

        $params = [
            'lk_permission_name' => $request->input('permission_name', ''),
            'pg_id'        => $request->input('pg_id', 0),
            'start'        => $request->input('start', 0),
            'length'        => $request->input('length', 10)
        ];

        $total_count = Permissions::getIns()->getDataList($params, true);
        if(empty($total_count)){
            return json_encode($result);
        }

        $data_list = Permissions::getIns()->getDataList($params);

        //返回前端数据
        $new_list = [];

        foreach ($data_list as $k => $v) {
            $new_list[$k] = $v;
        }

        $result['data']  = $new_list;
        $result['recordsFiltered'] = $result['recordsTotal'] = $total_count;
        return json_encode($result);
    }

    /**新增权限
     * @param Request $request
     * @return $this
     */
    public function permissionAdd(Request $request){

        if ($request->method() == 'POST') {

            //参数验证  先不加验证
            if(empty($request->input('title'))){
                msg('0301', '名称不能为空');
            }

            if(empty($request->input('permiss_group_id'))){
                msg('0301', '请选择分组权限不能为空');
            }

            if(empty($request->input('mod_name'))){
                msg('0301', 'mod名称不能为空');
            }

            if(empty($request->input('op_name'))){
                msg('0301', 'op名称不能为空');
            }


            $id = !empty($request->input('id')) ? $request->input('id') :'';
            $pg_id = !empty($request->input('permiss_group_id')) ? $request->input('permiss_group_id') : '';

            $loginAdmin = Auth::user();

            $data = [
                'title'=>$request->input('title'),
                'pg_id'=>$pg_id,
                'mod_name'=>$request->input('mod_name'),
                'op_name'=>$request->input('op_name'),

            ];

            if(empty($id)){
                $data['is_deleted'] = 'N';
                $data['created_by'] = $loginAdmin['id'];
                $data['created_time']=date('Y-m-d H:i:s');
                $data['updated_by'] = $loginAdmin['id'];
                $data['updated_time']=date('Y-m-d H:i:s');
                $res = Permissions::insert($data);

            }else{
                $data['updated_by'] = $loginAdmin['id'];
                $data['updated_time']=date('Y-m-d H:i:s');
                $res = Permissions::where('id',$id)->update($data);
            }

            if ($res !== false) {
                msg(self::$success, '操作成功',['pg_id'=>$pg_id]);
            }
            msg('0300', '操作失败');
        }

        $dataInfo = [];
        if ($request->method() == 'GET' && !empty($request->input('action')) && $request->input('action') == 'edit'){

            if(empty($request->input('id'))){
                msg('0300', '操作失败');
            }
            $id = $request->input('id');

            $dataInfo = Permissions::find($id);

        }

        $permissGroup = PermissionsGroups::where('is_deleted','N')->get();

        return view('manager.permissionAdd')->with([
            'tree_menu' => 'permission',
            'dataInfo'=>$dataInfo,
            'permissGroup'=>$permissGroup
        ]);
    }

    /**删除权限列表
     * @param Request $request
     */
    public function delPermiss(Request $request){
        if ($request->method() == 'POST') {

            if(empty($request->input('id')) || empty($request->input('is_deleted'))){
                msg('0301', '操作失败');
            }

            $id = $request->input('id');
            $is_deleted = !empty($request->input('is_deleted')) ? $request->input('is_deleted') :'Y';


            $loginAdmin = Auth::user();
            if(!empty($id)){
                $data['is_deleted'] = $is_deleted;
                $data['updated_by'] = $loginAdmin['id'];
                $data['updated_time']=date('Y-m-d H:i:s');

                $res = Permissions::where('id',$id)->update($data);

            }

            if ($res !== false) {
                msg(self::$success, '操作成功');
            }
            msg('0300', '操作失败');
        }

    }


}
