<?php

namespace App\Models\Basic;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Product extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'sl_product';

    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = false;

    /*
     *  主键ID
     */
    protected $primaryKey = 'product_id';


    public static function getIns() {
        static $ins;

        if (empty($ins) || !($ins instanceof self)) {
            $ins = new self();
        }
        return $ins;
    }

    /**
     * 主页搜索
     * @param $params
     * SELCET
        p.product_id,p.product_name,p.product_supp,p.update_time,
        s.supp_name_en,
        b.brand_name_en,
        c.cat_name_en,cr.cat_name_en as cat_name_en_rela
        FROM sl_product as p
        LEFT JOIN sl_basic_supp s ON s.supp_id = p.product_supp
        LEFT JOIN sl_basic_brand b ON b.brand_id = p.product_brand
        LEFT JOIN sl_basic_category as c ON c.cat_id = p.product_category
        LEFT JOIN sl_category_rela as cr ON cr.cat_id = c.cat_relation
        ORDER BY  p.update_time DESC
        LIMIT 10 OFFSET 0;
     */
    public function getHomeList($params,$count=false){
        $fields = ['p.product_id','p.product_name','p.product_supp','p.update_time',
                    's.supp_id','s.supp_name_en',
                    'b.brand_id','b.brand_name_en',
                    'br.brand_id as brand_rela_id','br.brand_name_en as brand_rela_name_en',
                    'c.cat_id','c.cat_name_en',
                    'cr.cat_id as cat_rela_id','cr.cat_name_en as cat_rela_name_en'
                ];
        $result = DB::table('sl_product as p')
            ->leftJoin('sl_basic_supp as s','s.supp_id','=','p.product_supp')
            ->leftJoin('sl_basic_brand as b','b.brand_id','=','p.product_brand')
            ->leftJoin('sl_brand_rela as br','br.brand_id','=','b.brand_relation')
            ->leftJoin('sl_basic_category as c','c.cat_id','=','p.product_category')
            ->leftJoin('sl_category_rela as cr','cr.cat_id','=','c.cat_relation')
            ->where( function ($query) use ($params){
                if(!empty($params['key'])){
                    switch ($params['search_type']){
                        case  'product_id':
                            $query->where('p.product_id',$params['key']);
                            break;
                        case  'product_name':
                            $query->where('p.product_name','LIKE','%'.$params['key'].'%');
                            break;
                        case  'category_name':
                            $query->where('c.cat_name_en','LIKE','%'.$params['key'].'%');
                            break;
                        case  'brand_name':
                            $query->where('b.brand_name_en','LIKE','%'.$params['key'].'%');
                            break;
                        case  'supp_name':
                            $query->where('s.supp_name_en','LIKE','%'.$params['key'].'%');
                            break;
                    }
                }
            });
            $result->orderBy("p.update_time","DESC");
            if($count){
                $result = $result->limit(1)->count();
            }else{
                $result = $result->offset($params['start'])->limit($params['length'])->get($fields);
            }
            return $result;

    }


}
