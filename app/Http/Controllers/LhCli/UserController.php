<?php

namespace App\Http\Controllers\LhCli;


use App\Models\Lh\ShoplooksUserOplog;
use App\Models\Lh\ShoplooksUser;
use App\Models\Lh\User;
use App\Models\Lh\UserAccount;
use App\Models\Lh\Withdraw;

use App\Services\Util;

use Illuminate\Support\Collection;
use App\Http\Controllers\Controller;

class UserController extends Controller
{
    /*
     *  针对设置默认自动提现账户的shoplooks账户
     *
     *  每月4号23:59，14号23:59在 -- 自动完成提现申请操作
     *  余额小于14美金的账号不操作，余额大于14美金的全部余额提现包括小数点后2位。
     *
     */
    public function withdraw() {
        # 1、余额大于14美金的有效shoplooks用户信息
        $user_list = User::where('from_source', 1)->where('locked', 0)->where('balance', '>=', 14)->get([
                'uid', 'frozen_balance', 'balance', 'updatetime'
            ])->toArray();

        if (empty($user_list)) {
            echo '暂无可符合条件自动提现用户';
            die();
        }


        # 2、设置过默认自动提现账户的用户账户信息
        $user_account_list = UserAccount::where('status', 1)->where('is_delete', 0)->where('set_defaut_autowithdraw', 1)
            ->whereIN('uid', array_column($user_list, 'uid'))->get([
                'id', 'uid'
            ])->toArray();

        if (empty($user_account_list)) {
            echo '暂无用户设置默认自动提现账户';
            die();
        }

        # 3、循环处理提现 -- 设置了默认自动账户符合的用户
        $fin_user_list = Collection::make($user_list)->keyBy('uid');

        # 自动提现成功 发送邮件
        $user_email_list = ShoplooksUser::where('is_closed',0)->whereIN('uuid',array_column($user_account_list, 'uid'))->get([
                'uuid', 'email', 'username'
            ])->toArray();
        $fin_user_email_list = Collection::make($user_email_list)->keyBy('uuid');

        foreach ($user_account_list as $k => $v){
            $handle_res = Withdraw::getIns()->handle([
                'account_id'      => $v['id'],
                'uid'             => $v['uid'],
                'frozen_balance'  => $fin_user_list[$v['uid']]['frozen_balance'],
                'balance'         => $fin_user_list[$v['uid']]['balance'],
                'amount'          => $fin_user_list[$v['uid']]['balance'],
                'updatetime'      => $fin_user_list[$v['uid']]['updatetime']
            ]);
            #如果自动提现成功，发送邮件
            if ($handle_res && !empty($fin_user_email_list[$v['uid']]['email'])) {
                $this->sendEmail([
                        'username' => empty($fin_user_email_list[$v['uid']]['username']) ? '-' : $fin_user_email_list[$v['uid']]['username'],
                        'to_email' => $fin_user_email_list[$v['uid']]['email'],
                        'amount'   => $fin_user_list[$v['uid']]['balance']
                    ]);
            }
        }
    }

    /*
     *  $data = [
     *      'username' => 用户名,
     *      'to_email' => 发送的邮件
     *  ],
     *  $type = 发送类型
     */
    public function sendEmail($data, $type=''){
        $res = Util::email($data['to_email'], [
            '%username%' => $data['username'],
            '%amount%'   => $data['amount'],
        ], 'user_withdraw');

        dd($res);
    }
}
