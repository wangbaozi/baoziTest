<?php

namespace App\Models\Lh;

use App\Models\Lh\User;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class Withdraw extends Model
{
    protected $connection = 'mysql_linkhaitao_v2';

    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'link_withdraw';

    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = false;

    /*
     *  主键ID
     */
    protected $primaryKey = 'id';


    public static function getIns() {
        static $ins;

        if (empty($ins) || !($ins instanceof self)) {
            $ins = new self();
        }
        return $ins;
    }

    /*
     *  $withdraw_data = [
     *      'uid'            => link_user的uid
     *      'account_id'     => 用户提现账户id
     *      'frozen_balance' => 冻结金额
     *      'balance'        => 账户余额
     *      'amount'         => 提现金额
     *  ]
     */
    public function handle($withdraw_data){

        $time     = time();
        $date     = date("Y-m-d", $time);
        $datetime = date("Y-m-d H:i:s", $time);
        $amount   = bcmul(floatval($withdraw_data['amount']), 1, 2);

        # 开启事务
        DB::connection($this->connection)->beginTransaction();

        # 1、添加withdraw记录
        $res_new = $this::insert([
            'u_id'             => $withdraw_data['uid'],
            'account_id'       => $withdraw_data['account_id'],
            'amount'           => $amount,
            'status'           => 'pending for verifing',
            'created_time'     => $time,
            'created_date'     => $date,
            'updated_time'     => $time,
            'updated_date'     => $date,
            'updated_datetime' => $datetime
        ]);
        if ($res_new === false){
            DB::connection($this->connection)->rollBack();
            return false;
        }

        # 2、更新用户balance记录
        $user_balance_res = User::getIns()->updateBalance(
            $withdraw_data['uid'],
            $amount,
            [
                'frozen_balance' => $withdraw_data['frozen_balance'],
                'balance'        => $withdraw_data['balance'],
            ]
        );

        if ($user_balance_res === false){
            DB::connection($this->connection)->rollBack();
            return false;
        }

        DB::connection($this->connection)->commit();
        return true;
    }
}
