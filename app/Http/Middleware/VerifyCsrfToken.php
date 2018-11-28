<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    protected $except = [
        '/home/getList',

        '/brand/getBrandList',
        '/brand/setBrandRela',

        '/brandRela/getBrandRelaList',
        '/brandRela/brandRelaAdd',
        '/brandRela/brandRelaEdit',
        '/brandRela/updateStatus',

        '/supp/getSuppList',
        '/supp/suppAdd',
        '/supp/updateStatus',
        '/supp/suppEdit',
        '/supp/importSupp',

        '/category/getCategoryList',
        '/category/getSecondCategory',

        '/manager/getRoleList',
        '/manager/getSetRolePermission',
        '/manager/setPermission',
        '/manager/loadPermissionList',
        'manager/saveSelectPermissions',
        '/manager/roleAdd',
        '/manager/roleEdit',
        '/manager/updateRoleStatus',
        '/category/updateCategory',

        '/manager/getAdminList',
        '/manager/adminAdd',

        '/manager/updateAdminStatus',
        '/manager/adminResetPwd',
        '/manager/adminPerview',

        '/manager/permissionGroupList',
        '/manager/getPermissionGroupList',
        '/manager/permissionGroupAdd',
        'manager/delPermissGroup',

        '/manager/permissionList',
        '/manager/getPermissionList',
        '/manager/permissionAdd',
        'manager/delPermiss',
        '/manager/errormsg',


        '/account/getAccountList',
        '/account/accountAdd',
        '/account/accountEdit',
        '/account/updateStatus',

        '/template/getDataList',
        '/template/templateAdd',
        '/template/templateEdit',
        '/template/updateStatus',

        '/cronSetup/getDataList',
        '/cronSetup/cronSetupAdd',
        '/cronSetup/cronSetupEdit',
        '/cronSetup/updateStatus',

        //ajax api
        '/supp/getValidSupp',
        '/account/getValidAccount',
        '/template/getValidTemplate',
        '/brandRela/getValidBrandRela',

        //download
        '/download/getDownList',
        '/download/updateStatus',
        '/download/downAdd',

        //上传图片和excel操作
        '/common/upload',
        '/excel/importSupp',
         //下载失败重试
        '/redown/down',


        //异常处理 -- ajax 请求api
        '/down/downFailHandle',

        //域名管理
        '/host/getHostList',
        '/host/updateStatus',
        '/host/hostAdd',
        '/host/hostEdit',
    ];
}
