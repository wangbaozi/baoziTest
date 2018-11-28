<?php

namespace App\Models\Basic;

use Illuminate\Database\Eloquent\Model;

class AdminRole extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'sl_admin_role';

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
     * 根据条件查询列表或总数
     *
     * $params = [
     *      'lk_user_name' => 管理员名称（模糊查询）
     *      'status'       => 商家上下架状态 100下架、1上线（默认上下架商家）
     *      'start'        => 起始数（分页）
     *      'length'       => 偏移长度（分页）
     *      'fields'       => 指定查询字段，默认['*']
     * ]
     *
     * $count = true  统计总数
     *
     */
    public function getDataList($params, $count = false)
    {
        $where = $this::where(
            function($query) use($params){
                if (!empty($params['lk_role_name'])) {
                    $query->where('title', 'like', '%'.$params['lk_role_name'].'%');
                }
                $query->where('is_deleted', 'N');  //只显示正常的

                /*if (!empty($params['is_deleted'])) {
                    $query->where('is_deleted', $params['is_deleted']);
                }*/

            });

        if ($count) {
            return $where->count();
        }

        //指定查询字段
        $fields = ( empty($params['fields']) ) ? ['*'] : $params['fields'];
        if (isset($params['start']) && !empty($params['length'])) {
            $list_data = $where->orderBy($this->primaryKey, 'asc')->offset($params['start'])->limit($params['length'])->get($fields);
        } else {
            $list_data = $where->orderBy($this->primaryKey, 'asc')->get($fields);
        }
        return $list_data;
    }
}
