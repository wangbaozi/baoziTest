<?php

namespace App\Models\Link;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

//文件cron设置表
class Cron extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'sl_link_cron';

    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = false;

    /*
     *  主键ID
     */
    protected $primaryKey = 'cron_id';


    public static function getIns() {
        static $ins;

        if (empty($ins) || !($ins instanceof self)) {
            $ins = new self();
        }
        return $ins;
    }

    /*
     * 根据条件查询cron设置记录列表或总数
     *
     * $params = [
     *      'lk_mcid'      => 商家mcid（模糊查询）
     *      'lk_link_host' => host地址（模糊查询）
     *      'lk_file_path' => 文件地址（模糊查询）
     *      'status'       => 模板是否有效状态 100失效、1有效（默认所有）
     *      'start'        => 起始数（分页）
     *      'length'   => 偏移长度（分页）
     *      'fields'   => 指定查询字段，默认['*']
     * ]
     *
     * $count = true  统计总数
     *
     */
    public function getDataList($params, $count = false)
    {
        $where = $this::whereIN('status',[0,1,2])->where(
            function($query) use($params){
                if (!empty($params['lk_mcid'])) {
                    $query->where('mcid', 'like', '%'.$params['lk_mcid'].'%');
                }
                if (!empty($params['lk_link_host'])) {
                    $query->where('link_host', 'like', '%'.$params['lk_link_host'].'%');
                }
                if (!empty($params['lk_file_path'])) {
                    $query->where('file_path', 'like', '%'.$params['lk_file_path'].'%');
                }
                if (!empty($params['cron_id'])) {
                    $query->where('cron_id', $params['cron_id']);
                }
                if (!empty($params['fd_id'])) {
                    $query->where('fd_id', $params['fd_id']);
                }
                if (isset($params['status']) && $params['status'] != '') {
                    $params['status'] = ($params['status']==0) ? 0 : $params['status'];
                    $query->where('status', $params['status']);
                }
                if (isset($params['cron_status']) && $params['cron_status'] != '') {
                    $params['cron_status'] = ($params['cron_status']==0) ? 0 : $params['cron_status'];
                    $query->where('cron_status', $params['cron_status']);
                }
                if (!empty($params['nextrun'])) {
                    $query->where('nextrun', '<', $params['nextrun']);
                }
            });

        if ($count) {
            return $where->count();
        }
        $this->primaryKey = 'nextrun';
        //指定查询字段
        $fields = ( empty($params['fields']) ) ? ['*'] : $params['fields'];
        if (isset($params['start']) && !empty($params['length'])) {
            $list_data = $where->orderBy('status', 'desc')->orderBy('nextrun', 'asc')->orderBy('cron_id', 'asc')->offset($params['start'])->limit($params['length'])->get($fields);
        } else {
            $list_data = $where->orderBy('status', 'desc')->orderBy('nextrun', 'asc')->orderBy('cron_id', 'asc')->get($fields);
        }

        if ( !empty($params['to_array']) ) {
            $list_data = $list_data->toArray();
        }

        return $list_data;
    }

    public function getCronList_bak($limit){
        //取可执行的数据，执行状态为0或者2，符合执行时间 nextrun <= time()
       return DB::table('sl_link_cron as c')
            ->leftJoin('sl_link_account as a','c.account_id','=','a.account_id')
            ->whereIn('c.cron_status',[0,2])
            ->where('c.status',1)
            ->where('a.status',1)
            ->where('c.nextrun','<=',time())
            ->limit($limit)->get();

    }

    public function getCronList($limit){
        //取可执行的数据，执行状态为0或者2，符合执行时间 nextrun <= time()
        return DB::table('sl_link_cron as c')
            ->whereIn('c.cron_status',[0,2])
            ->where('c.status',1)
            ->where('c.nextrun','<=',time())
            ->where('c.nextrun','>',0)
            ->orderBy('c.nextrun','asc')
            ->orderBy('c.cron_id','asc')
            ->limit($limit)->get();

    }

    //SELECT brand_id,brand_name_en FROM sl_basic_brand where brand_name_en = 'Hem Tape' OR brand_name_en = 'Thong' OR brand_name_en = 'Girl Short';
    //批量查询
    public function getArrayQuery($key,$fields=[],$array=[]){
        if(!is_array($array)) return false;
        $result = [];
        $sql = "SELECT ".$fields['field']." FROM ".$fields['table']." WHERE ";
        foreach ($array as $k => $val) {
            $sql .= sprintf(" %s = '%s' OR ", $key, $val);
        }
        $sql = substr($sql,0,-4);
        $query = DB::select(DB::raw($sql));
        $query = json_encode($query);
        $query = json_decode($query,true);
        foreach ((array)$query as $qk=>$qv){
            $result[$qv[$fields['key']]] = $qv[$fields['value']];
        }
        return $result;
    }

}
