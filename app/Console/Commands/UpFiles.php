<?php

namespace App\Console\Commands;

use App\Models\Link\Host;
use Illuminate\Console\Command;
use App\Services\OSS;
use App\Models\Basic\Product;
use App\Models\Basic\ProductImg;
use Illuminate\Support\Facades\Log;


class UpFiles extends Command
{
    //每次上传到Aliyun图片
    const ProductLimit = 20;
    //每轮次最多上传到Aliyun图片
//    const ProductMax = 100;
    //每次取域名数量
    const HostLimit = 10;
    //每次执行数量
    public $cronMax = 100;
    //默认CDN域名
    public $cdnHost = '';
    //允许下载的文件类型
    public $allowExt = ['jpg', 'png', 'jpeg', 'gif', 'tif'];
    //默认Aliyun保存路径
    public $filePath = 'data/upload/images/datafeed/';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cron:upimg';

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

        $this->cdnHost = config('alioss.PREFIXURLCDNIMG');
        ini_set('memory_limit', '10240M');
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->getListProductImg();
    }



    /**
     * 根据product_img表的数据，把图片保存到CDN
     * product_img.status 0默认新增,1有效,2失败,3上传中,4无效地址,5删除
     */
    private function getListProductImg()
    {

        //超过5分钟没有更新的status=3重置状态status=0
        $this->resetDoProductImgStatus();

        $total = ProductImg::getIns()->where('status',0)->count();
        $count = $total;
        $ProductLimit = self::ProductLimit;
        if ($total >= $this->cronMax) {
            $count = $this->cronMax;
        }

        $ModSize = ceil($count / self::ProductLimit);
        Log::info('ImgFiles getListProductImg total = ' . $total . ', count = ' . $count . ', ProductLimit = ' . $ProductLimit . ', cronMax = ' . $this->cronMax . ', ModSize = ' . $ModSize);
        if($this->cronMax <= 0){
            //已达到最大处理数量
            return;
        }
        for ($i = 0; $i < $ModSize; $i++) {
            $start = $i * self::ProductLimit - 1;
            if ($start < 0) $start = 0;
            if ($start > $count) $start = $count;

            if(self::ProductLimit >= $this->cronMax && $this->cronMax > 0 ){
                $ProductLimit = $this->cronMax;
            }
            Log::info('ImgFiles getListProductImg  start = '.$start.', limit = ' . $ProductLimit . ', cronMax = ' . $this->cronMax);

            if($this->cronMax <= 0){
                //已达到最大处理数量
                break;
            }
            $list = ProductImg::getIns()
                ->where('status',0)
                ->limit($ProductLimit)
                ->orderBy('update_time', 'ASC')
                ->offset($start)
                ->get(['product_id', 'product_imageurl', 'product_img_id']);
            $list = json_decode($list, true);
            if(empty($list)){
                Log::error('ImgFiles  getListProductImg list  is empty ');
                break;
                return false;
            }
            $error = false;
            $product_ids = $product_errids = $update_ids = [];

            foreach ($list as $value) {
                $update_ids[] = $value['product_img_id'];
            }
            //先全部标记为上传中product_img.status=3
            $this->updateProductImg($update_ids,3);

            foreach ($list as $value) {
                $r = $this->downProductImgFiles($value);
                if ($r == true) {
                    $product_ids[] = $value['product_id'];
                } else {
                    $error = true;
                    $product_errids[] = $value['product_id'];
                    $this->cronMax += 1;
                }
            }
            if ($error == true) {
                Log::error('ImgFiles  getListProductImg product_errids = ' . json_encode($product_errids, true));
            }
            Log::info('ImgFiles getListProductImg start = ' . $start . ', limit = '.$ProductLimit.', status = 1, product_ids = ' . json_encode($product_ids, true));

            $this->cronMax -=  $ProductLimit;

        }

    }

    /**
     * product_img.status 0默认新增,1有效,2失败,3上传中,4无效地址,5删除
     * @param $info
     * @return bool
     */
    private function downProductImgFiles($info)
    {

        if (empty($info['product_imageurl'])) {
            Log::error('ImgFiles downProductImgFiles product_imageurl is empty ');
            $this->updateProductImg($info['product_img_id'],4);
            return false;
        }
        $fileUrl = $info['product_imageurl'];
        $parseInfo = parse_url($fileUrl);
        $pathinfo = pathinfo($parseInfo['path']);
        $filename = $pathinfo['basename'];
        $fileDir = $pathinfo['dirname']; //date('Y-m');
        //去掉开头斜杆/
        $path = $this->filePath.substr($fileDir . '/' . $filename, 1);
        if (!isset($pathinfo['extension']) || !in_array($pathinfo['extension'], $this->allowExt)) {
            Log::error('downProductImgFiles error pathinfo = ' . json_encode($pathinfo,true));
            $this->updateProductImg($info['product_img_id'],4);
            //无效链接
            return false;
        }

        $status = $this->getUrlstatus($fileUrl);
        if ($status == false) {
            Log::error('ImgFiles downProductImgFiles status = false, product_id = ' . $info['product_id'] . ', fileUrl = ' . $fileUrl);
            //无效链接
            $this->updateProductImg($info['product_img_id'],4);
            return false;
        }
        $contents = file_get_contents($fileUrl);
        $result = OSS::getIns()->publicUploadContent($path, $contents);
        $result = json_encode($result, true);

//       echo  $url = OSS::getIns()->getPublicObjectURL($path);
        //Log::info('ImgFiles publicUploadContent product_id = ' . $info['product_id'] . ', result = ' . json_encode($result, true));
        //为空值才算成功，返回的是Object Head值
        if ($result != "{}") {
            Log::error('ImgFiles publicUploadContent false, product_id = ' . $info['product_id'] . ', fileUrl = ' . $fileUrl . ', result = ' . json_encode($result, true));
            //无效链接
            $this->updateProductImg($info['product_img_id'],2);
            return false;
        }

        $this->updateProductImg($info['product_img_id'],1,$path);

        return true;
    }

    /**
     * 更新product_img状态
     * product_img.status 0默认新增,1有效,2失败,3上传中,4无效地址,5删除
     * @param $product_img_id
     * @param int $status
     */
    private function updateProductImg($product_img_id, $status = 0,$path = ''){
        $update = [];
        $update['status'] = $status;
        $update['update_time'] = date('Y-m-d H:i:s');
        if(!empty($path)){
            $update['product_img'] = $path;
        }
        ProductImg::getIns()->whereIN('product_img_id', (array)$product_img_id)->update($update);
    }

    /**
     * 超过5分钟没有更新的status=3重置状态status=0
     */
    private function resetDoProductImgStatus(){
        $update['status'] = 0;
        $update['update_time'] = date('Y-m-d H:i:s');
        ProductImg::getIns()->where('status',3)
            ->where('update_time','<=',date('Y-m-d H:i:s',(time()-5*60)))->update($update);

    }

    /**
     * 检查Url是否有效
     * @param $url
     * @return bool
     */
    private function getUrlstatus($url)
    {
        $array = get_headers($url, 1);
        if (preg_match('/(200|301|304|302)/', $array[0])) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 取配置域名
     */
    protected function getHostList_bak()
    {
        $count = Host::getIns()->where('status', 1)->whereNotNull('host_name')->count();
        $ModSize = ceil($count / self::HostLimit);
        Log::info('ImgFiles getHostList count = ' . $count . ', ModSize = ' . $ModSize);
        for ($i = 0; $i < $ModSize; $i++) {
            if ($this->cronMax <= 0) {
                Log::info('ImgFiles getHostList  cronMax = ' . $this->cronMax);
                //每轮次执行完最大数据退出
                break;
            }
            $start = $i * self::HostLimit - 1;
            if ($start < 0) $start = 0;
            if ($start > $count) $start = $count;
            $list = Host::getIns()->where('status', 1)->whereNotNull('host_name')->orderBy('host_id','DESC')->limit(self::HostLimit)->offset($start)->get();
            $list = json_decode($list, true);
            foreach ($list as $hostInfo) {
                if ($this->cronMax <= 0) {
                    Log::info('ImgFiles getHostList host_id = '.$hostInfo['host_id'].' cronMax = ' . $this->cronMax);
                    //每轮次执行完最大数据退出
                    break;
                }
                $this->getListProduct($hostInfo);
            }

        }
    }

    /**
     * @param null $hostName
     * status_img状态有[0,1,2,3]
     *   0: 初始状态，需要上传的数据
     *   1: 已保存到CDN
     *   2: 上传中，中间状态
     *   3: url无法保存或保存失败
     */
    protected function getListProduct_bak($hostInfo = [])
    {

        $hostName = $hostInfo['host_name'];

        if (empty($hostName)) {
            Log::error('ImgFiles getListProduct hostName  is empty ', true);
            return;
        }
        $total = Product::getIns()->whereNotNull('product_imageurl')
//            ->whereBetween('update_time', [date('Y-m-d', strtotime("-3 day")), date('Y-m-d')])
            ->where('status_img', 0)
            ->where('product_imageurl', 'LIKE', '%' . $hostName . '%')
            ->count();
        $count = $total;
        $ProductLimit = self::ProductLimit;
        if ($total >= $this->cronMax) {
            $count = $this->cronMax;
        } else {
            $this->cronMax -= $total;
        }

        $ModSize = ceil($count / self::ProductLimit);
        Log::info('ImgFiles getListProduct host_id = '.$hostInfo['host_id'].', hostName = '.$hostName.', total = ' . $total . ', count = ' . $count . ', ProductLimit = ' . $ProductLimit . ', cronMax = ' . $this->cronMax . ', ModSize = ' . $ModSize);
        if($this->cronMax <= 0){
            //已达到最大处理数量
            return;
        }
        for ($i = 0; $i < $ModSize; $i++) {
            $start = $i * self::ProductLimit - 1;
            if ($start < 0) $start = 0;
            if ($start > $count) $start = $count;

            if(self::ProductLimit >= $this->cronMax && $this->cronMax > 0 ){
                $ProductLimit = $this->cronMax;
            }
            Log::info('ImgFiles getListProduct host_id = '.$hostInfo['host_id'].', start = '.$start.', limit = ' . $ProductLimit . ', cronMax = ' . $this->cronMax);

            if($this->cronMax <= 0){
                //已达到最大处理数量
                break;
            }

            //只取昨天更新的数据 product_img_id = 0 的
            $list = Product::getIns()->whereNotNull('product_imageurl')
//                ->whereBetween('update_time', [date('Y-m-d', strtotime("-3 day")), date('Y-m-d')])
                ->where('status_img', 0)
                ->where('product_imageurl', 'LIKE', '%' . $hostName . '%')
                ->limit($ProductLimit)
                ->offset($start)
                ->orderBy('product_id', 'DESC')
                ->get(['product_id', 'product_imageurl', 'product_img_id']);
            $list = json_decode($list, true);
            if(empty($list)){
                Log::error('ImgFiles  getListProduct list  is empty ');
                break;
                return false;
            }
            $error = false;
            $product_ids = $product_errids = [];
            foreach ($list as $value) {
                $r = $this->downImgFiles($value);
                if ($r == true) {
                    $product_ids[] = $value['product_id'];
                } else {
                    $error = true;
                    $product_errids[] = $value['product_id'];
                    $this->cronMax += 1;
                }
            }
            if ($error == true) {
                Log::error('ImgFiles  getListProduct status_img = 3, product_errids = ' . json_encode($product_errids, true));
                //标记status_img = 1 - 完成
                $update = ['update_time' => date('Y-m-d H:i:s'), 'status_img' => 3];
                Product::getIns()->whereIN('product_id', $product_errids)->update($update);
            }
            Log::info('ImgFiles getListProduct host_id = '.$hostInfo['host_id'].', start = ' . $start . ', limit = '.$ProductLimit.', status_img = 1, product_ids = ' . json_encode($product_ids, true));

            $this->cronMax -=  self::ProductLimit;

        }

    }

    /**
     * 根据product表的数据，把图片保存到CDN
     * @param $info
     * @return bool
     */
    protected function downImgFiles_bak($info)
    {
        if (empty($info['product_imageurl'])) {
            Log::error('ImgFiles downImgFiles product_imageurl is empty ');
            return false;
        }
        //标记status_img = 2 - 上传中
        $update = ['update_time' => date('Y-m-d H:i:s'), 'status_img' => 2];
        Product::getIns()->where('product_id', $info['product_id'])->update($update);
        $fileUrl = $info['product_imageurl'];
        $parseInfo = parse_url($fileUrl);
        $pathinfo = pathinfo($parseInfo['path']);
        $filename = $pathinfo['basename'];
        $fileDir = $pathinfo['dirname']; //date('Y-m');
        //去掉开头斜杆/
        $path = $this->filePath.substr($fileDir . '/' . $filename, 1);
        if (!isset($pathinfo['extension']) || !in_array($pathinfo['extension'], $this->allowExt)) {
            Log::error('downImgFiles error pathinfo = ' . json_encode($pathinfo,true));
            //无效链接
            return false;
        }

        $product_imageurl = $this->cdnHost . $path;//完整cdn地址
        $imgInfo = ProductImg::getIns()->where('product_img', $path)->first();
        $imgInfo = json_decode($imgInfo, true);
        if (!empty($imgInfo)) {
            //更新product_img_id
            $update = ['update_time' => date('Y-m-d H:i:s'), 'product_imageurl' => $product_imageurl, 'status_img' => 1, 'product_img_id' => $imgInfo['product_img_id']];
            Product::getIns()->where('product_id', $info['product_id'])->update($update);
            //更新时间
            ProductImg::getIns()->where('product_img', $path)->update(['update_time' => date('Y-m-d H:i:s')]);
            return true;
        }
//        $fileUrl = 'https://images-na.ssl-images-amazon.com/images/G/01/Shopbop/p/pcs/products/ramyb/ramyb3003412867/ramyb3003412867_q1_1-1.jpg';
        $status = $this->getUrlstatus($fileUrl);
        if ($status == false) {
            Log::error('ImgFiles downImgFiles status = false, product_id = ' . $info['product_id'] . ', fileUrl = ' . $fileUrl);
            //无效链接
            return false;
        }
        $contents = file_get_contents($fileUrl);
        $result = OSS::getIns()->publicUploadContent($path, $contents);
        $result = json_encode($result, true);
        //Log::info('ImgFiles publicUploadContent product_id = ' . $info['product_id'] . ', result = ' . json_encode($result, true));
        //为空值才算成功，返回的是Object Head值
        if ($result != "{}") {
            Log::error('ImgFiles publicUploadContent false, product_id = ' . $info['product_id'] . ', fileUrl = ' . $fileUrl . ', result = ' . json_encode($result, true));
            //无效链接
            return false;
        }
        //记录数据
        $insert = [
            'product_id' => $info['product_id'],
            'product_img' => $path,
            'product_imageurl' => $fileUrl,
            'update_time' => date('Y-m-d H:i:s'),
            'create_time' => date('Y-m-d H:i:s')
        ];
        $insertGetId = ProductImg::getIns()->insertGetId($insert);

        if (empty($insertGetId)) {
            Log::error('ImgFiles downImgFiles  insertGetId = false, product_id = ' . $info['product_id'] . ', fileUrl = ' . $fileUrl);
            return false;
        }

        //更新product_img_id 标记-已完成status_img = 1
        $update = ['product_img_id' => $insertGetId, 'status_img' => 1, 'update_time' => date('Y-m-d H:i:s'), 'product_imageurl' => $product_imageurl];
        Product::getIns()->where('product_id', $info['product_id'])->update($update);

        return true;
    }



}
