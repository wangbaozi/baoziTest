<?php
/**
 * Created by PhpStorm.
 * User: 55haitao
 * Date: 2018/8/29
 * Time: 上午9:56
 * 所有的方法参数验证 -- 集合
 */


/**
 * 标准品牌管理验证规则
 */
if (! function_exists('brandRulesMsgs')) {
    function brandRulesMsgs($type)
    {
        $res = [
            'setBrandRela' => [
                'rules' => [
                    'brand_id' => 'required|int',
                    'rela_id'  => 'required|int',
                ],
                'msgs' => [
                    'brand_id.required' => '品牌id必传',
                    'brand_id.int'      => '标准品牌id必须为整数',

                    'rela_id.required'  => '标准品牌id必选',
                    'rela_id.int'       => '标准品牌id必须为整数',
                ]
            ],
        ];

        return empty($res[$type]) ? ['rules' => [], 'msgs' => []] : $res[$type];
    }
}


/**
 * 标准品牌管理验证规则
 */
if (! function_exists('brandRelaRulesMsgs')) {
    function brandRelaRulesMsgs($type)
    {
        $res = [
            'brandRelaAdd' => [
                'rules' => [
                    'brand_name_en' => 'required|string|max:50|unique:sl_brand_rela,brand_name_en',
                ],
                'msgs' => [
                    'brand_name_en.required'     => '标准品牌英文名称必填',
                    'brand_name_en.max'          => '标准品牌英文名称填写过长',
                    'brand_name_en.unique'       => '标准品牌英文名称已存在',
                ]
            ],

            'brandRelaEdit' => [
                'rules' => [
                    'brand_id'       => 'required|int',
                    'brand_name_en'  => 'required|string|max:50|unique:sl_brand_rela,brand_name_en',
                ],
                'msgs' => [
                    'brand_id.required'      => '标准品牌id必传',
                    'brand_id.numeric'       => '标准品牌id必须为整数',

                    'brand_name_en.required' => '标准品牌英文名称必填',
                    'brand_name_en.max'      => '标准品牌英文名称填写过长',
                    'brand_name_en.unique'   => '标准品牌英文名称已存在',
                ]
            ],

            'updateStatus' => [
                'rules' => [
                    'brand_rela_id_list' => 'required|array',
                    'status'             => 'required|numeric',
                ],
                'msgs' => [
                    'brand_rela_id_list.required' => '标准品牌id数组必传',
                    'brand_rela_id_list.array'    => '标准品牌id必须是数组格式',

                    'status.required'       => '标准品牌状态必传',
                    'status.numeric'        => '标准品牌状态必须为数字',
                ]
            ],
        ];

        return empty($res[$type]) ? ['rules' => [], 'msgs' => []] : $res[$type];
    }
}


/**
 * 商家管理验证规则
 */
if (! function_exists('suppRulesMsgs')) {
    function suppRulesMsgs($type)
    {
        $res = [
            'suppAdd' => [
                'rules' => [
                    //'supp_mcid' => 'required|max:50',
                    'supp_mcid' => 'required|string|max:50|unique:sl_basic_supp,supp_mcid',
                    'supp_name' => 'required|string|max:100|unique:sl_basic_supp,supp_name',
                    'supp_homepage' => 'required|max:200',
                    'supp_aff_min'  => 'required|numeric|min:0.00001|max:9999999999.99999',
                ],
                'msgs' => [
                    'supp_mcid.required'     => 'mcid必填',
                    'supp_mcid.max'          => 'mcid填写过长',
                    'supp_mcid.unique'       => '商家mcid已存在',

                    'supp_name.required'     => '标准名必填',
                    'supp_name.max'          => '标准名填写过长',
                    'supp_name.unique'       => '标准名已存在',

                    'supp_homepage.required' => '官网必填',
                    'supp_homepage.max'      => '官网填写过长',

                    'supp_aff_min.required'  => '佣金必填',
                    'supp_aff_min.numeric'   => '佣金必须为数字',
                    'supp_aff_min.min'       => '佣金数字必须大于0.00001',
                    'supp_aff_min.max'       => '佣金数字填写过大',
                ]
            ],

            'suppEdit' => [
                'rules' => [
                    'supp_id'       => 'required|int',
                    //'supp_mcid'     => 'required|string|max:50',
                    'supp_mcid'     => 'required|string|max:50|unique:sl_basic_supp,supp_mcid',
                    'supp_name'     => 'required|string|max:100|unique:sl_basic_supp,supp_name',
                    'supp_homepage' => 'required|string|max:200',
                    'supp_aff_min'  => 'required|numeric|min:0.00001|max:9999999999.99999',
                ],
                'msgs' => [
                    'supp_id.required'       => '商家id必传',
                    'supp_id.numeric'        => '商家id必须为整数',

                    'supp_mcid.required'     => 'mcid必填',
                    'supp_mcid.max'          => 'mcid填写过长',
                    'supp_mcid.unique'       => '商家mcid已存在',

                    'supp_name.required'     => '标准名必填',
                    'supp_name.max'          => '标准名填写过长',
                    'supp_name.unique'       => '标准名已存在',

                    'supp_homepage.required' => '官网必填',
                    'supp_homepage.max'      => '官网填写过长',

                    'supp_aff_min.required'  => '佣金必填',
                    'supp_aff_min.numeric'   => '佣金必须为数字',
                    'supp_aff_min.min'       => '佣金数字必须大于0.00001',
                    'supp_aff_min.max'       => '佣金数字填写过大',
                ]
            ],

            'updateStatus' => [
                'rules' => [
                    'supp_id_list' => 'required|array',
                    'status'       => 'required|numeric',
                ],
                'msgs' => [
                    'supp_id_list.required' => '商家id数组必传',
                    'supp_id_list.array'    => '商家id必须是数组格式',

                    'status.required'       => '商家状态必传',
                    'status.numeric'        => '商家状态必须为数字',
                ]
            ],
        ];

        return empty($res[$type]) ? ['rules' => [], 'msgs' => []] : $res[$type];
    }
}

/**
 * 账号管理验证规则
 */
if (! function_exists('accountRulesMsgs')) {
    function accountRulesMsgs($type)
    {
        $res = [
            'accountAdd' => [
                'rules' => [
                    'name' => 'required|max:100|unique:sl_link_account',
                    'username' => 'required|max:100',
                    'password' => 'required|max:100',
                ],
                'msgs' => [
                    'name.required' => '标准名必填',
                    'name.max'      => '标准名填写过长',
                    'name.unique'   => '标准名已存在',

                    'username.required' => '账号名必填',
                    'username.max'      => '账号名填写过长',

                    'password.required' => '密码必填',
                    'password.max'      => '密码填写过长',
                ]
            ],

            'accountEdit' => [
                'rules' => [
                    'account_id' => 'required|int',
                    'name'       => 'required|max:100|unique:sl_link_account,name',
                    'username'   => 'required|max:100',
                    'password'   => 'required|max:100',
                ],
                'msgs' => [
                    'account_id.required' => '账户id必传',
                    'account_id.numeric'  => '账户id必须为整数',

                    'name.unique'         => '标准名已存在',
                    'name.required'       => '标准名必填',
                    'name.max'            => '标准名填写过长',

                    'username.required'   => '账号名必填',
                    'username.max'        => '账号名填写过长',

                    'password.required'   => '密码必填',
                    'password.max'        => '密码填写过长',
                ]
            ],

            'updateStatus' => [
                'rules' => [
                    'account_id_list' => 'required|array',
                    'status'          => 'required|numeric',
                ],
                'msgs' => [
                    'account_id_list.required' => '账户id数组必传',
                    'account_id_list.array'    => '账户id必须是数组格式',

                    'status.required' => '账户状态必传',
                    'status.numeric'  => '账户状态必须为数字',
                ]
            ],
        ];

        return empty($res[$type]) ? ['rules' => [], 'msgs' => []] : $res[$type];
    }
}

/**
 * 模板管理验证规则
 */
if (! function_exists('templateRulesMsgs')) {
    function templateRulesMsgs($type)
    {
        $res = [
            'templateAdd' => [
                'rules' => [
                    'fd_name'   => 'required|max:100|unique:sl_link_filedb',
                    'fd_type'   => 'required|int',
                    'fd_prefix' => 'required|max:10',
                ],
                'msgs' => [
                    'fd_name.required' => '模板名必填',
                    'fd_name.max'      => '模板名填写过长',
                    'fd_name.unique'   => '模板名已存在',

                    'fd_type.required' => '类型必选',
                    'fd_type.int'      => '类型格式必须是整数',

                    'fd_prefix.required' => '前缀符号',
                    'fd_prefix.max'      => '前缀符号填写过长',
                ]
            ],

            'templateEdit' => [
                'rules' => [
                    'fd_id'   => 'required|int',
                    'fd_name' => 'required|max:100|unique:sl_link_filedb,fd_name',
                    'fd_type'   => 'required|int',
                    'fd_prefix' => 'required|max:10',
                ],
                'msgs' => [
                    'fd_id.required'   => '模板id必传',
                    'fd_id.numeric'    => '模板id必须为整数',

                    'fd_name.required' => '模板名必填',
                    'fd_name.max'      => '模板名填写过长',
                    'fd_name.unique'   => '模板名已存在',

                    'fd_type.required' => '类型必选',
                    'fd_type.int'      => '类型格式必须是整数',

                    'fd_prefix.required' => '前缀符号',
                    'fd_prefix.max'      => '前缀符号填写过长',
                ]
            ],


            'updateStatus' => [
                'rules' => [
                    'template_id_list' => 'required|array',
                    'status'           => 'required|numeric',
                ],
                'msgs' => [
                    'template_id_list.required' => '账户id数组必传',
                    'template_id_list.array'    => '账户id必须是数组格式',

                    'status.required' => '账户状态必传',
                    'status.numeric'  => '账户状态必须为数字',
                ]
            ],
        ];
        return empty($res[$type]) ? ['rules' => [], 'msgs' => []] : $res[$type];
    }
}

/**
 * 文件cron设置管理验证规则
 */
if (! function_exists('cronSetupRulesMsgs')) {
    function cronSetupRulesMsgs($type)
    {
        $res = [
            'cronSetupAdd' => [
                'rules' => [
                    'link_type' => 'required|max:50',
                    'fd_id'     => 'required|numeric',
                    'link_host' => 'required|max:100',
                    'file_path' => 'required',

                    'mcid'      => 'required|max:50', //'required|max:50|unique:sl_link_cron,mcid',
                    'hour_run'  => 'required|numeric|min:1|max:3000',
                    'hour_rate' => 'required|numeric|min:0.01|max:100',
                ],
                'msgs' => [
                    'link_type.required' => '类型必选',
                    'link_type.max'      => '类型填写过长',

                    'fd_id.required'     => '映射模板必选',
                    'fd_id.numeric'      => '映射模板id必须为数字',

                    'link_host.required' => 'host地址必填',
                    'link_host.max'      => 'host地址填写过长',

                    'file_path.required' => '文件地址必填',
                    //'file_path.max'      => '文件地址填写过长',

                    'mcid.required' => '商家mcid必选',
                    'mcid.max'      => '商家mcid填写过长',
                    'mcid.unique'   => 'mcid、类型、host地址和文件地址已存在，请检查',

                    'hour_run.required'  => '执行时间必填',
                    'hour_run.numeric'   => '执行时间必须为数字',
                    'hour_run.min'       => '执行时间数字填写过小',
                    'hour_run.max'       => '执行时间数字填写过大',

                    'hour_rate.required' => '时间间隔必填',
                    'hour_rate.numeric'  => '时间间隔必须为数字',
                    'hour_rate.min'      => '时间间隔数字填写过小',
                    'hour_rate.max'      => '时间间隔数字填写过大',
                ]
            ],

            'cronSetupEdit' => [
                'rules' => [
                    'cron_id'   => 'required|int',
                    'link_type' => 'required|max:50',
                    'fd_id'     => 'required|numeric',
                    'link_host' => 'required|max:100',
                    'file_path' => 'required',
                    'mcid'      => 'required|max:50',  //'required|max:50|unique:sl_link_cron,mcid',
                    'hour_run'  => 'required|numeric|min:1|max:3000',
                    'hour_rate' => 'required|numeric|min:0.01|max:100',
                ],
                'msgs' => [
                    'cron_id.required'   => '执行文件id必传',
                    'cron_id.numeric'    => '执行文件id必须为整数',

                    'link_type.required' => '类型必选',
                    'link_type.max'      => '类型填写过长',

                    'fd_id.required'     => '映射模板必选',
                    'fd_id.numeric'      => '映射模板id必须为数字',

                    'link_host.required' => 'host地址必填',
                    'link_host.max'      => 'host地址填写过长',

                    'file_path.required' => '文件地址必填',
                    //'file_path.max'      => '文件地址填写过长',

                    'mcid.required' => '商家mcid必选',
                    'mcid.max'      => '商家mcid填写过长',
                    'mcid.unique'   => 'mcid、类型、host地址和文件地址已存在，请检查',

                    'hour_run.required'  => '执行时间必填',
                    'hour_run.numeric'   => '执行时间必须为数字',
                    'hour_run.min'       => '执行时间数字填写过小',
                    'hour_run.max'       => '执行时间数字填写过大',

                    'hour_rate.required' => '时间间隔必填',
                    'hour_rate.numeric'  => '时间间隔必须为数字',
                    'hour_rate.min'      => '时间间隔数字填写过小',
                    'hour_rate.max'      => '时间间隔数字填写过大',
                ]
            ],

            'updateStatus' => [
                'rules' => [
                    'cron_id_list' => 'required|array',
                    'status'       => 'numeric',
                    'cron_status'       => 'numeric',
                ],
                'msgs' => [
                    'cron_id_list.required' => 'id数组必传',
                    'cron_id_list.array'    => 'id必须是数组格式',

//                    'status.required' => '记录状态必传',
                    'status.numeric'  => '记录状态必须为数字',
                    'cron_status.numeric'  => '记录状态必须为数字',
                ]
            ],

        ];
        return empty($res[$type]) ? ['rules' => [], 'msgs' => []] : $res[$type];
    }
}