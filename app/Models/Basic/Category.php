<?php

namespace App\Models\Basic;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Category extends Model
{

    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'sl_basic_category';

    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = false;

    /*
     *  主键ID
     */
    protected $primaryKey = 'cat_id';

    public static function getIns() {
        static $ins;

        if (empty($ins) || !($ins instanceof self)) {
            $ins = new self();
        }
        return $ins;
    }

    //base_category 和 category_rela关联查询
    public function categoryRela()
    {

        return $this->belongsTo(CategoryRela::class,'cat_relation','cat_id');
    }

    /**
     * 获取分类列表
     * @param $params
     * @param bool $count
     * @return \Illuminate\Support\Collection|int
     */
    public function getBaseCategoryList($params,$count = false){


        $data = DB::table('sl_basic_category as c')
            ->leftJoin('sl_category_rela as cr', 'cr.cat_id', '=', 'c.cat_relation')
            ->select('c.*','cr.cat_name_en as rela_name_en')
            ->where(function ($query) use($params){
                if (!empty($params['status'])) {
                    if( $params['status']==100 ){
                        //$query->where('c.cat_relation', '=', 0);
                    } else{
                        $query->where('c.cat_relation', '>', 0);
                    }
                }

                $query->where('c.is_valid', 1);  //针对新版datafeed后台品牌管理
                if (!empty($params['lk_category_id'])) {
                    $query->where('c.cat_id','=',$params['lk_category_id']);
                }
                if (!empty($params['lk_category_name'])) {
                    $query->where('c.cat_name_en', 'like', '%'.$params['lk_category_name'].'%');
                }
                if (!empty($params['lk_category_rela_name'])) {
                    $query->where('cr.cat_name_en', 'like', '%'.$params['lk_category_rela_name'].'%');
                }
            });

            if ($count) {
                return $data->count();
            }

            //指定查询字段

            if (isset($params['start']) && !empty($params['length'])) {
                $list_data = $data->orderBy('c.cat_id', 'desc')->offset($params['start'])->limit($params['length'])->get();
            } else {
                $list_data = $data->orderBy('c.cat_id', 'desc')->get();
            }

            return $list_data;

    }

}
