<?php

namespace App\Models\Basic;

use Illuminate\Database\Eloquent\Model;

class Title extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'sl_basic_title';

    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = false;

    /*
     *  主键ID
     */
    protected $primaryKey = 'tit_id';


    public static function getIns() {
        static $ins;

        if (empty($ins) || !($ins instanceof self)) {
            $ins = new self();
        }
        return $ins;
    }


}
