<?php

namespace App\Http\Controllers\Api;


use App\Models\Link\Host;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Basic\ProductImg;

class ApiProductController extends Controller
{
    public $data;

    private static $success = '0200';
    //默认Aliyun保存路径
    public $filePath = 'data/upload/images/datafeed/';
    //允许下载的文件类型
    public $allowExt = ['jpg', 'png', 'jpeg', 'gif', 'tif'];


    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        #TODO 安全检查
//        $this->middleware('auth');
    }



    public function productList(Request $request){
        /*
        $result = [
            '641295'=> 'https://i5.walmartimages.com/asr/3f099741-3d7e-43f8-b135-5369156c212e_1.03dcce32a5b0ae3a28a5e545b85013c8.jpeg?odnHeight=450&odnWidth=450&odnBg=ffffff',
            '57015441'=> 'https://i5.walmartimages.com/asr/3f099741-3d7e-43f8-b135-5369156c212e_1.03dcce32a5b0ae3a28a5e545b85013c8.jpeg?odnHeight=450&odnWidth=450&odnBg=ffffff',
        ];
*/
        $reqs = '{"1":"https:\/\/i5.walmartimages.com\/asr\/3f099741-3d7e-43f8-b135-5369156c212e_1.03dcce32a5b0ae3a28a5e545b85013c8.jpeg?odnHeight=450&odnWidth=450&odnBg=ffffff","641293":"https:\/\/i5.walmartimages.com\/asr\/3f099741-3d7e-43f8-b135-5369156c212e_1.03dcce32a5b0ae3a28a5e545b85013c8.jpeg?odnHeight=450&odnWidth=450&odnBg=ffffff"}';

        $reqs = json_decode($reqs,true);

        //查询数据库，取全部key值
        $resultkeys = array_keys($reqs);
        #TODO 可以考虑redis
        $list = ProductImg::getIns()->whereIN("product_id",$resultkeys)->where('status',1)->get(['product_id','product_imageurl']);
        $arrlist = json_decode($list,true);
        //格式化
        foreach ((array)$arrlist as $value){
            $listids[$value['product_id']] = $value;
        }
        //需要替换的
        $result = array_intersect_key($reqs,$listids);
        if(empty($result)){
            return [];
        }
        foreach ((array)$result as $key=>$img){
            $result[$key] = $listids[$key]['product_imageurl'];
        }

        #TODO 需要优化异步操作
        //需要入库
        $diffs =  array_diff_key($reqs,$listids);
        $this->insertProductImg($diffs);

        //返回需要替换的数据
        return json_encode($result,true);
    }

    /**
     * 入库操作
     * @param $diffs
     * @return mixed
     *
    pathinfo
    Array
    (
        [dirname] => /s/files/1/1073/4692/products
        [basename] => Rosalia_mini_black_front_grande_fb048066-218a-4219-8e99-06ad3e100bd8.xx.jpg
        [extension] => jpg
        [filename] => Rosalia_mini_black_front_grande_fb048066-218a-4219-8e99-06ad3e100bd8.xx
    )
     */
    private function insertProductImg($diffs){
        $insert = [];
        foreach ($diffs as $key=>$imgurl){
            $parseInfo = parse_url($imgurl);
            $pathinfo = pathinfo($parseInfo['path']);
            if (!isset($pathinfo['extension']) || !in_array($pathinfo['extension'], $this->allowExt)) {
                //后缀名，跳过不需要入库的
                continue;
            }
            //做host判断是否需要入库
            $checked =  $this->checkHost($parseInfo['host']);
            if($checked == false){
                //跳过不需要入库的
                continue;
            }

            $filename = $pathinfo['basename'];
            $fileDir = $pathinfo['dirname']; //date('Y-m');
            //去掉开头斜杆/
            $product_img = $this->filePath.substr($fileDir . '/' . $filename, 1);
            //$insert[$diff]['product_img'] = $product_img;
            $insert[$key]['product_id'] = $key;
            $insert[$key]['product_imageurl'] = $imgurl;
            $insert[$key]['update_time'] = date('Y-m-d H:i:s');
            $insert[$key]['create_time'] = date('Y-m-d H:i:s');
        }

       return ProductImg::getIns()->insert($insert);
    }

    /**
     * 检查是否存在host配置
     * @param $host
     * @return bool
     */
    private function checkHost($host){
        if(empty($host))
            return false;

        $count =  Host::getIns()->where('host_name',$host)->where('status',1)->count();
        if( empty($count) ){
         //不需要入库的
            return false;
        }
        return true;

    }


}
