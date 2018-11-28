<?php

namespace App\Models\Link;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

//文件download表

class Download extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'sl_link_download';

    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = false;

    /*
     *  主键ID
     */
    protected $primaryKey = 'down_id';
    //针对下载失败文件最多重试次数
    const DOWNNUM = 2;


    public static function getIns() {
        static $ins;

        if (empty($ins) || !($ins instanceof self)) {
            $ins = new self();
        }
        return $ins;
    }

    /**
     * download 列表
     * $count = true  统计总数
     * @param array
     * @return void
     * @author lonn.chen
     */
    public function getDataList($params, $count = false)
    {
        $where = $this->where(
            function($query) use($params){
                if (!empty($params['status_range'])) {
                    $query->where('status', '<', $params['status_range']);
                }

                if (isset($params['status'])  && $params['status'] != '') {
                    $params['status'] = empty($params['status']) ? 0 : $params['status'];
                    $query->where('status', $params['status']);
                }
                if (isset($params['read_status'])  && $params['read_status'] != '') {
                    $params['read_status'] = empty($params['read_status']) ? 0 : $params['read_status'];
                    $query->where('read_status', $params['read_status']);
                }
                if (!empty($params['down_url'])) {
                    $query->where('down_url', 'like', '%'.$params['down_url'].'%');
                }
                if (!empty($params['file_url'])) {
                    $query->where('file_url', 'like', '%'.$params['file_url'].'%');
                }
                if (!empty($params['down_id'])) {
                    $query->whereIN('down_id', (array)$params['down_id']);
                }
                if (!empty($params['cron_id'])) {
                    $query->whereIN('cron_id', $params['cron_id']);
                }
                if(isset($params['is_down']) && $params['is_down'] === false){
                    $query->where('down_num', '<=', self::DOWNNUM);
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

    /**
     * download 列表
     * @param array
     * @return void
     * @author lonn.chen
     */
    public function getDownList($params = []){
        //'status'=>2,'read_status'=>0,'start'=>0
        return DB::table('sl_link_download')
            ->where('read_status',$params['read_status'])
            ->where('status',$params['status'])
            ->limit($params['length'])
            ->offset($params['start'])
            ->get();
    }
}
