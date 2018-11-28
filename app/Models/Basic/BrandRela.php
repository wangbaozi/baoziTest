<?php

namespace App\Models\Basic;

use Illuminate\Database\Eloquent\Model;
use DB;

class BrandRela extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'sl_brand_rela';

    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = false;

    /*
     *  主键ID
     */
    protected $primaryKey = 'brand_id';


    public static function getIns() {
        static $ins;

        if (empty($ins) || !($ins instanceof self)) {
            $ins = new self();
        }
        return $ins;
    }


    /*
     * 根据条件查询商家列表或总数
     *
     * $params = [
     *      'lk_brand_name_en' => 标准品牌名（模糊查询）
     *      'status'           => 上下架状态 100下架、1上线（默认上下架）
     *      'start'            => 起始数（分页）
     *      'length'          => 偏移长度（分页）
     *      'fields'          => 指定查询字段，默认['*']
     * ]
     *
     * $count = true  统计总数
     *
     */
    public function getDataList($params, $count = false)
    {
        $where = $this::whereIN('status',[0,1])->where('is_valid',1)->where(
            function($query) use($params){
                if (!empty($params['lk_brand_id'])) {
                    $query->where('brand_id', '=', $params['lk_brand_id'].'%');
                }
                if (!empty($params['lk_brand_name_en'])) {
                    $query->where('brand_name_en', 'like', $params['lk_brand_name_en'].'%');
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

    public function dataByNameList ($params) {

        //指定查询字段
        $fields = ( empty($params['fields']) ) ? ['*'] : $params['fields'];

        return collect(DB::table($this->table)->where(
            function($query) use($params){
                if (!empty($params['lk_brand_id'])) {
                    $query->where('brand_id', '=', $params['lk_brand_id'].'%');
                }
                if (!empty($params['brand_name_en_list'])) {
                    $query->whereIN('brand_name_en', $params['brand_name_en_list']);
                }
            })->get($fields))->toArray();
    }

    //批量更新
    public function updateBatch($multipleData = [])
    {
        try {
            if (empty($multipleData)) {
                throw new \Exception("数据不能为空");
            }
            $tableName = DB::getTablePrefix() . $this->getTable(); // 表名
            $firstRow  = current($multipleData);

            $updateColumn = array_keys($firstRow);
            // 默认以id为条件更新，如果没有ID则以第一个字段为条件
            $referenceColumn = isset($firstRow[$this->primaryKey]) ? $this->primaryKey : current($updateColumn);
            unset($updateColumn[0]);
            // 拼接sql语句
            $updateSql = "UPDATE " . $tableName . " SET ";
            $sets      = [];
            $bindings  = [];
            foreach ($updateColumn as $uColumn) {
                $setSql = "`" . $uColumn . "` = CASE ";
                foreach ($multipleData as $data) {
                    $setSql .= "WHEN `" . $referenceColumn . "` = ? THEN ? ";
                    $bindings[] = $data[$referenceColumn];
                    $bindings[] = $data[$uColumn];
                }
                $setSql .= "ELSE `" . $uColumn . "` END ";
                $sets[] = $setSql;
            }
            $updateSql .= implode(', ', $sets);
            $whereIn   = collect($multipleData)->pluck($referenceColumn)->values()->all();
            $bindings  = array_merge($bindings, $whereIn);
            $whereIn   = rtrim(str_repeat('?,', count($whereIn)), ',');
            $updateSql = rtrim($updateSql, ", ") . " WHERE `" . $referenceColumn . "` IN (" . $whereIn . ")";
            // 传入预处理sql语句和对应绑定数据
//            return [
//                'sql'  => $updateSql,
//                'data' => $bindings
//            ];
            return DB::update($updateSql, $bindings);
        } catch (\Exception $e) {
            return false;
        }
    }
}
