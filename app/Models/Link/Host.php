<?php

namespace App\Models\Link;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

//域名管理表

class Host extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'sl_link_host';

    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = false;

    /*
     *  主键ID
     */
    protected $primaryKey = 'host_id';



    public static function getIns() {
        static $ins;

        if (empty($ins) || !($ins instanceof self)) {
            $ins = new self();
        }
        return $ins;
    }

    /**
     * host 列表
     * $count = true  统计总数
     * @param array
     * @return void
     * @author lonn.chen
     */
    public function getDataList($params, $count = false)
    {
        $where = $this->where(
            function($query) use($params){
                if (isset($params['status'])  && $params['status'] != '') {
                    $params['status'] = empty($params['status']) ? 0 : $params['status'];
                    $query->where('status', $params['status']);
                }
                if (isset($params['host_name'])  && $params['host_name'] != '') {
                    $query->where('host_name','LIKE','%'.$params['host_name'].'%');
                }

                if (!empty($params['host_id'])) {
                    $query->whereIN('host_id', (array)$params['host_id']);
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
