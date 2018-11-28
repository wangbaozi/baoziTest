<?php
/**
 * Created by PhpStorm.
 * User: 55haitao
 * Date: 2018/9/3
 * Time: 上午9:56
 */

/**
 * 商家状态转换
 * @param $status 状态
 */
if (! function_exists('getSuppStatusInfo')) {
    function getSuppStatusInfo($status)
    {
        $status_info = [
            '1' => '已上架',
            '0' => '已下架',
        ];
        return empty($status_info[$status]) ? '-' : $status_info[$status];
    }
}


/**
 * 文件下载状态转换
 * @param $status 状态
 */
if (! function_exists('getDownStatusInfo')) {
    function getDownStatusInfo($status)
    {
        $status_info = [
            '1' => '下载中',
            '2' => '已下载',
            '3' => '下载失败',
        ];
        return empty($status_info[$status]) ? '-' : $status_info[$status];
    }
}

/**
 * 文件下载解析状态转换
 * @param $status 状态
 */
if (! function_exists('getDownParseStatusInfo')) {
    function getDownParseStatusInfo($status)
    {
        $status_info = [
            '1' => '解析中',
            '2' => '已解析',
            '3' => '解析失败',
        ];
        return empty($status_info[$status]) ? '-' : $status_info[$status];
    }
}

/*
 *  映射模板 -- 数据表字段列表
 */
if (! function_exists('getTableFields')) {
    function getTableFields($type)
    {
        $field_list = [
            'product' => [
                '商品名称(product_name)' => 'product_name',
                //'商家名称(product_supp)' => 'product_supp',
                '商品分类(product_category)' => 'product_category',
                '商品关键词(product_keywords)' => 'product_keywords',
                '商品描述(product_description)' => 'product_description',
                '商品sku(product_sku)' => 'product_sku',
                '商品品牌(product_brand)' => 'product_brand',
                '商品币种(product_currency)' => 'product_currency',

                '商品售价(product_saleprice)'   => 'product_saleprice',
                '商品原价(product_price)'       => 'product_price',
                '购买链接(product_buyurl)'      => 'product_buyurl',
                '商品图片(product_imageurl)'    => 'product_imageurl',
                '-(product_artist)'            => 'product_artist',
                '商品标题(product_title)'       => 'product_title',
                '-(product_format)'      => 'product_format',
                '-(product_gift)'        => 'product_gift',
                '-(product_startdate)'   => 'product_startdate',
                '-(product_enddate)'     => 'product_enddate',
                '-(product_instock)'     => 'product_instock',
                '-(product_condition)'   => 'product_condition',
                '扩展字段1-(ext1)'                => 'ext1',
                '扩展字段2-(ext2)'                => 'ext2',
                '扩展字段3-(ext3)'                => 'ext3',
                '-(product_index)'       => 'product_index',
                '-(format_supp)'         => 'format_supp',
                '-(product_spuid)'       => 'product_spuid',
                '-(product_skuid)'       => 'product_skuid',
            ],
        ];

        return empty($field_list[$type]) ? '-' : $field_list[$type];
    }
}

if (! function_exists('getTableMustFields')) {
    function getTableMustFields($type)
    {
        $field_list = [
            'product' => [
                'product_name',
                //'product_supp',
                'product_category',
                'product_keywords',
                'product_description',
                'product_sku',
                'product_brand',
                'product_currency',
                'product_saleprice',
                'product_price',
                'product_buyurl',
                'product_imageurl',
//            'product_artist',
//            'product_title',
//            'product_format',
//            'product_gift',
//            'product_startdate',
//            'product_enddate',
//            'product_instock',
//            'product_condition',
//            'ext1',
//            'ext2',
//            'ext3',
//            'product_index',
//            'format_supp',
//            'product_spuid',
//            'product_skuid',
            ],
        ];

        return empty($field_list[$type]) ? '-' : $field_list[$type];
    }
}