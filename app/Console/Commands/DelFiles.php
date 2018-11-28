<?php

namespace App\Console\Commands;

use App\Models\Basic\ProductImg;
use Illuminate\Console\Command;
use App\Services\OSS;
use Illuminate\Support\Facades\Log;

class DelFiles extends Command
{
    //一次取多少商品数量
    const LIMIT = 100;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cron:delimg';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

        $this->delProductImg();

    }

    /**
     * 删除超过40天没有更新图片
     */
    protected function delProductImg(){
        $list = ProductImg::getIns()
            ->where('update_time','<=',date('Y-m-d H:i:s',strtotime('-40 day')))
            ->where('status',1)
            ->limit(self::LIMIT)
            ->get();
        if(empty($list)){
            return;
        }


        $proErrids = $proEmptyids =  $proIds =  [];
        foreach ($list as $key=>$value){
            $path = $value['product_img'];
            if(!empty($path)){
                $result = OSS::getIns()->publicDeleteObject($path);
                $result = json_encode($result, true);
                if ($result == "{}") {
                    //删除成功，标记 status = 5
                    $proIds[] = $value['product_img_id'];
                    $this->upProductImg($value['product_img_id'], 5);
                }else{
                    Log::error('delFiles delProductImg false result = ' . json_encode($result,true));
                    $proErrids[] = $value['product_img_id'];
                    //删除失败，标记 status = 4
                    $this->upProductImg($value['product_img_id'], 4);
                }
            }else{
                //无效
                $proEmptyids[] = $value['product_img_id'];
            }
        }
        //无效，标记 status = 4
        if(!empty($proEmptyids)){
            $this->upProductImg($proEmptyids, 4);
        }

        Log::info('delFiles delProductImg proIds = '.json_encode($proIds,true).', proErrids = '.json_encode($proErrids,true).', proEmptyids = '.json_encode($proEmptyids,true) );

    }


    /**
     * 更新product_img状态
     * product_img.status 0默认新增,1有效,2失败,3上传中,4无效地址,5删除
     * @param $product_img_id
     * @param int $status
     */
    protected function upProductImg($product_img_id, $status = 0){
        $update = [];
        $update['status'] = $status;
        $update['update_time'] = date('Y-m-d H:i:s');
        ProductImg::getIns()->whereIN('product_img_id', (array)$product_img_id)->update($update);
    }

}
