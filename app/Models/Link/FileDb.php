<?php

namespace App\Models\Link;

use Illuminate\Database\Eloquent\Model;

//数据映射模板表
class FileDb extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'sl_link_filedb';

    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = false;

    /*
     *  主键ID
     */
    protected $primaryKey = 'fd_id';


    public static function getIns() {
        static $ins;

        if (empty($ins) || !($ins instanceof self)) {
            $ins = new self();
        }
        return $ins;
    }

    /*
     * 根据条件查询模板列表或总数
     *
     * $params = [
     *      'lk_fd_name' => 模板名（模糊查询）
     *      'lk_desc'    => 模板描述（模糊查询）
     *      'status'     => 模板是否有效状态 100失效、1有效（默认所有）
     *      'start'      => 起始数（分页）
     *      'length'     => 偏移长度（分页）
     *      'fields'     => 指定查询字段，默认['*']
     * ]
     *
     * $count = true  统计总数
     *
     */
    public function getDataList($params, $count = false)
    {
        $where = $this::whereIN('status',[0,1])->where(
            function($query) use($params){
                if (!empty($params['lk_fd_id'])) {
                    $query->where('fd_id', '=', $params['lk_fd_id']);
                }
                if (!empty($params['lk_fd_name'])) {
                    $query->where('fd_name', 'like', '%'.$params['lk_fd_name'].'%');
                }
                if (!empty($params['lk_desc'])) {
                    $query->where('desc', 'like', '%'.$params['lk_desc'].'%');
                }
                if (!empty($params['status'])) {
                    $params['status'] = ($params['status']==100) ? 0 : $params['status'];
                    $query->where('status', $params['status']);
                }
            });

        if ($count) {
            return $where->count();
        }

        //指定查询字段
        $fields = ( empty($params['fields']) ) ? ['*'] : $params['fields'];
        if (isset($params['start']) && !empty($params['length'])) {
            $list_data = $where->orderBy($this->primaryKey, 'desc')->offset($params['start'])->limit($params['length'])->get($fields);
        } else {
            $list_data = $where->orderBy($this->primaryKey, 'desc')->get($fields);
        }

        return $list_data;
    }
}
