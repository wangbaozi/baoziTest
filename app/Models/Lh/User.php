<?php

namespace App\Models\Lh;

use App\Models\Lh\ShoplooksUserOplog;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{

    protected $connection = 'mysql_linkhaitao_v2';

    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'link_user';

    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = false;

    /*
     *  主键ID
     */
    protected $primaryKey = 'uid';


    public static function getIns() {
        static $ins;

        if (empty($ins) || !($ins instanceof self)) {
            $ins = new self();
        }
        return $ins;
    }

    /**
     * 更新用户的提现金额
     *
     * @param int $uid 用户ID
     * @param int $amount 提现金额
     * @param array $finance_summary 用户的佣金概览数据
     * @return int 0|1
     */
    public function updateBalance($uid, $amount, $finance_summary = [])
    {
        if ( ! empty($finance_summary)) {
            $finance_summary = $this::where('uid',$uid)->first(['balance', 'frozen_balance'])->toArray();
        }

        $new_balance = bcsub(
            $finance_summary['balance'],
            $amount,
            2
        );

        $new_frozen_balance = bcadd(
            $finance_summary['frozen_balance'],
            $amount,
            2
        );

        $data = [
            'balance' => $new_balance,
            'frozen_balance' => $new_frozen_balance,
            'updatetime' => time()
        ];

        $result = $this::where([
            'uid'            => $uid,
            'balance'        => $finance_summary['balance'],
            'frozen_balance' => $finance_summary['frozen_balance'],
        ])->update($data);

        if ($result !== false) {
            //记录操作记录
            ShoplooksUserOplog::insert([
                'data_before' => json_encode($finance_summary),
                'data_after'  => json_encode($data),
                'uid'         => $uid,
                'dateline'    => time(),
                'comment'     => '自动提现--更新账户佣金'
            ]);
        }
        return $result;
    }



}
