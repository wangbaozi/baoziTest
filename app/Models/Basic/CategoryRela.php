<?php

namespace App\Models\Basic;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class CategoryRela extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'sl_category_rela';

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

    /**
     * 获取的一级分类
     */
    public function getFirstCategory(){
        $firstCategory = CategoryRela::query()->where('cat_parent',0)->get();
        return $firstCategory;
    }

    /**
     * 获取的一级分类
     */
    public function getParentCategory($parentId){
        //$Category = CategoryRela::where('cat_id',$parentId)->get()->toArray();
        $Category = CategoryRela::find($parentId)->toArray();

        return $Category;
    }


    /**
     * @return array|mixed 缓存分类列表
     */
    public function getCategoryRelaList(){

        $cacheKey = 'categoryRelaList';
        $categoryRelaList = Cache::get($cacheKey);

        if(empty($categoryRelaList)){

            $CategoryRela = CategoryRela::query()->where('status',1)->get();
            foreach ($CategoryRela as $key => $value){

                $categoryRelaList[$value['cat_id']] = $value['cat_name_en'];
            }

            Cache::put($cacheKey,$categoryRelaList,'60');//缓存时间设置60分钟

        }

        return $categoryRelaList;
    }

}
