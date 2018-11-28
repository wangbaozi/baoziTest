<?php

namespace App\Models\Basic;

use Illuminate\Database\Eloquent\Model;

use App\Models\Basic\BrandRela;
use Illuminate\Support\Facades\DB;

class Brand extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'sl_basic_brand';

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

    protected $groupBy = 'brand_name_en';


    public static function getIns() {
        static $ins;

        if (empty($ins) || !($ins instanceof self)) {
            $ins = new self();
        }
        return $ins;
    }

    /*
     * 根据条件查询品牌和关联标准品牌列表或总数
     *
     * $params = [
     *      'lk_brand_rela_name_en' => 标准品牌名（模糊查询）
     *      'lk_brand_name_en'      => 品牌名（模糊查询）
     *      'status'                => 品牌上下架状态 100下架、1上线（默认所有）
     *      'brand_relation'        => 品牌是否关联标准品牌  100否、1是
     *      'start'                 => 起始数（分页）
     *      'length'                => 偏移长度（分页）
     *      'fields'                => 指定查询字段，默认['*']
     * ]
     *
     * $count = true  统计总数
     *
     */
    public function getBrandRelaDataList($params, $count = false)
    {
        //关联查询优化统计总数
        if ($count) {
            $where = $this::where('is_valid',1)->where(
                function($query) use($params){
                    if (!empty($params['lk_brand_id'])) {
                        $query->where('brand_id', '=', $params['lk_brand_id']);
                    }
                    if (!empty($params['lk_brand_name_en'])) {
                        $query->where('brand_name_en', 'like', $params['lk_brand_name_en'].'%');
                    }
                    if (!empty($params['status'])) {
                        $params['status'] = ($params['status']==100) ? 0 : $params['status'];
                        $query->where('status', $params['status']);
                    }
                    if (!empty($params['brand_relation'])) {
                        if( $params['brand_relation']==100 ){
                            //$query->where('brand_relation', '=', 0);
                        } else{
                            $query->where('brand_relation', '>', 0);
                        }
                    }
                    if (!empty($params['lk_brand_rela_name_en'])) {
                        $brand_rela_list = BrandRela::where('brand_name_en', 'like', $params['lk_brand_rela_name_en'].'%')->get(['brand_id'])->toArray();
                        if (empty($brand_rela_list)){
                            return [];
                        }
                        $brand_rela_id = array_column($brand_rela_list, 'brand_id');
                        $query->whereIN('brand_relation', $brand_rela_id);
                    }
                });



            return $where->count();
        }



        $where = DB::table('sl_basic_brand as b')
            ->leftJoin('sl_brand_rela as br', 'br.brand_id', '=', 'b.brand_relation')
            ->where('b.is_valid',1)->where(
                function ($query) use($params){
                    if (!empty($params['lk_brand_id'])) {
                        $query->where('b.brand_id', '=', $params['lk_brand_id']);
                    }
                    if (!empty($params['lk_brand_name_en'])) {
                        $query->where('b.brand_name_en', 'like', $params['lk_brand_name_en'].'%');
                    }

                    if (!empty($params['lk_brand_rela_name_en'])) {
                        $query->where('br.brand_name_en', 'like', $params['lk_brand_rela_name_en'].'%');
                    }

                    if (!empty($params['brand_relation'])) {
                        if( $params['brand_relation']==100 ){
                            //$query->where('b.brand_relation', '=', 0);
                        } else{
                            $query->where('b.brand_relation', '>', 0);
                        }

                    }

                    if (!empty($params['status'])) {
                        $params['status'] = ($params['status']==100) ? 0 : $params['status'];
                        $query->where('b.status', $params['status']);
                    }
                }
            );


        //指定查询字段
        $fields = ( empty($params['fields']) ) ? ['b.*','br.brand_name_en as brand_rela_name_en'] : $params['fields'];
        if (isset($params['start']) && !empty($params['length'])) {
            $list_data = $where->orderBy('b.brand_id', 'desc')->offset($params['start'])->limit($params['length'])->get($fields);
        } else {
            $list_data = $where->orderBy('b.brand_id', 'desc')->get($fields);
        }

        return $list_data;
    }
}
