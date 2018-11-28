<?php

namespace App\Http\Controllers\Home;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

//use App\Models\Basic\Product;
//use App\Models\Basic\Category;
//use App\Models\Basic\Currency;
//use App\Models\Basic\Brand;
//use App\Models\Basic\Artist;
//use App\Models\Basic\Title;
//use App\Models\Basic\Supp;

use App\Models\FilesReader;
use App\Models\Link\Cron;
use App\Models\Link\Download;
use App\Models\Link\FileDb;
use App\Models\Link\Account;

class RedownController extends Controller
{
    //失败文件下载任务单次限制
    const DOWNLIMIT = 2;
    //cron执行中 状态超时时间
    const OVERTIME = 3600;
    //异常信息编号
    public $failNo = 0;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
//        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return;
    }


    /**
     * 手动重试下载失败的文件
     *
     * @param
     * @return void
     * @author lonn.chen
     */
    public function down(Request $request)
    {
        //取下载失败的文件列表
        ini_set('memory_limit', '10240M');
        $reqs = $request->all();
        $is_down = false;
        if ((isset($reqs['down_id']) && !empty($reqs['down_id'])) || !empty($reqs['down_id_list'])) {
            $is_down = true;
        }
        //取download下载表
        $count = Download::getIns()->getDataList(['status' => 1, 'is_down'=>false], true);
        if ((isset($count) && $count >= self::DOWNLIMIT) && $is_down == false) {
            Log::info('cronRedownLog downFile 任务超过' . self::DOWNLIMIT . '个，count = ' . $count);
            return false;
        }

        $limit = self::DOWNLIMIT - $count;
        $limit = ($limit > self::DOWNLIMIT || $limit < 0) ? self::DOWNLIMIT : $limit;
        //取可执行的数据
//        $list = Download::getIns()->getDownList(['read_status' => 0, 'status' => 3, 'start' => 0, 'length' => $limit]);
        $params = ['read_status' => 0, 'status' => 3, 'start' => 0, 'length' => $limit];
        $params['is_down'] = $is_down;
        if ($is_down) {
            if (!empty($reqs['down_id_list'])) {
                $down_ids = $reqs['down_id_list'];
            } else {
                $down_ids = explode(',', $reqs['down_id']);
            }
            $params['down_id'] = $down_ids;
        }
        $list = Download::getIns()->getDataList($params);

        if (empty($list)) {
            return false;
        }

        $list = json_decode($list, true);

        Log::info('cronRedownLog downFile 已有几个任务 ' . $count . ', list = ' . json_encode($list) . ' ');
        foreach ((array)$list as $k => $v) {
            //先全部标记为：下载中
            $this->updateDownstatus($v['down_id'], 1);
        }

        foreach ((array)$list as $k => $v) {
            $this->downFile($v);
        }

    }

    /**
     * 自动重试下载失败的文件
     *
     * @param
     * @return void
     * @author lonn.chen
     */
    public function cron()
    {
        //取下载失败的文件列表
        ini_set('memory_limit', '10240M');

        //取download下载表
        $count = Download::getIns()->getDataList(['status' => 1, 'is_down'=>false], true);
        if ((isset($count) && $count >= self::DOWNLIMIT) ) {
            Log::info('cronRedownLog downFile 任务超过' . self::DOWNLIMIT . '个，count = ' . $count);
            return false;
        }

        $limit = self::DOWNLIMIT - $count;
        $limit = ($limit > self::DOWNLIMIT || $limit < 0) ? self::DOWNLIMIT : $limit;
        //取可执行的数据
        //$list = Download::getIns()->getDownList(['read_status' => 0, 'status' => 3, 'start' => 0, 'length' => $limit]);
        $params = ['read_status' => 0, 'status' => 3, 'start' => 0, 'length' => $limit, 'is_down'=>false];
        $list = Download::getIns()->getDataList($params);

        if (empty($list)) {
            return false;
        }

        $list = json_decode($list, true);

        Log::info('cronRedownLog downFile 已有几个任务 ' . $count . ', list = ' . json_encode($list) . ' ');
        foreach ((array)$list as $k => $v) {
            //先全部标记为：下载中
            $this->updateDownstatus($v['down_id'], 1);
        }

        foreach ((array)$list as $k => $v) {
            $this->downFile($v);
        }

    }



    /**
     * 标记解析状态
     * @param array
     * @return void
     * @author lonn.chen
     */
    protected function updateDownstatus($down_id, $status, $data = [])
    {
        $update = ['status' => $status, 'read_status' => 0, 'update_time' => date('Y-m-d H:i:s'), 'read_time' => date('Y-m-d H:i:s')];
        if (!empty($data)) {
            foreach ($data as $key => $value) {
                $update[$key] = $value;
            }
        }
        if ($status == 3) {
            $update['down_num'] = DB::raw('down_num + 1');
            $update['fail_no'] = $this->failNo;
        }
        Download::getIns()->where('down_id', $down_id)->update($update);
        Log::info('cronRedownLog updateDownstatus down_id = ' . $down_id . ', status = ' . $status);
        return true;
    }


    /**
     * 下载
     *
     * @param array
     * @return void
     * @author lonn.chen
     */
    protected function downFile($downInfo)
    {

        $fileUrl = $downInfo['file_url'];
        $downUrl = $downInfo['down_url'];
        //取文件信息
        $pathinfo = pathinfo($fileUrl);
        $fileExt = $pathinfo['extension'];
        if ($fileExt == 'gz') {
            $getExt = strtolower(substr($fileUrl, -6));
            if ($getExt == 'xml.gz') $fileExt = 'xml.' . $fileExt;
            if ($getExt == 'txt.gz') $fileExt = 'txt.' . $fileExt;
        } else if ($fileExt == 'zip') {
            $getExt = strtolower(substr($fileUrl, -7));
            if ($getExt == 'csv.zip') $fileExt = 'csv.' . $fileExt;
        }

        $cronInfo = Cron::getIns()->where('cron_id', $downInfo['cron_id'])->first();
        $cronInfo = json_decode($cronInfo, true);
        //初始化
        $accInfo = [];
        $accInfo['username'] = '';
        $accInfo['password'] = '';
        if (isset($cronInfo['account_id']) && !empty($cronInfo['account_id'])) {
            $accInfo = Account::getIns()->where('account_id', $cronInfo['account_id'])->first();
            $accInfo = json_decode($accInfo, true);
        }
        //删除旧的文件
        if(!empty($downInfo['file_url']) && file_exists(storage_path().'/'.$downInfo['file_url'])){
            unlink(storage_path().'/'.$downInfo['file_url']);
        }
        //生成新文件名
        $newName = date('His') . rand(10, 99);
        $newFilename = $pathinfo['dirname'] . '/' . $newName . '.' . $fileExt;

        //创建保存目录
        if (!file_exists(storage_path() . '/' . $pathinfo['dirname']) && !mkdir(storage_path() . '/' . $pathinfo['dirname'], 0777, true)) {
            $this->failNo = 4;
            Log::error('cronRedownLog downFile dirname = ' . $pathinfo['dirname'] . ' 创建文件夹失败 ');
            return false;
        }

        $fileInfo = [
            'fileExt' => $fileExt,
            'fileDir' => $pathinfo['dirname'],
            'newName' => $newName,
            'newFilename' => $newFilename,
        ];

        //http或ftp保存文件
        $rarFiles = new FilesReader\RarFiles();
        $result = $rarFiles->ftpDown($downUrl, $fileInfo, $accInfo['username'], $accInfo['password']);

        if ($result == false) {
            //下载失败
            $this->failNo = 2;
            $this->updateDownstatus($downInfo['down_id'], 3);
            return false;
        }
        $fileSavePath = storage_path() . '/' . $newFilename;
        $fileSize = 0;
        $fileUnit = 'KB';
        if (file_exists($fileSavePath)) {
            $fileSize = round(filesize($fileSavePath) / 1024, 2);
        }

        if ($fileSize > 1024) {
            $fileUnit = 'MB';
            $fileSize = round(filesize($fileSavePath) / 1024 / 1024, 2);
        }

        //标记下载完成
        if ($result) {
            //下载完成
            $update = ['file_unit' => $fileUnit, 'file_size' => $fileSize, 'file_url' => $newFilename];
            $this->updateDownstatus($downInfo['down_id'], 2, $update);
        }

        return true;

    }

    /**
     *  超时1小时的重置状态
     *
     */
    public function reCron(){

        $update = ['cron_status' => 0, 'update_time' => date('Y-m-d H:i:s')];
        Cron::getIns()
            ->where('cron_status',1)
            ->where('status',1)
            ->where('update_time','<=',date('Y-m-d H:i:s',(time() - self::OVERTIME)))
            ->update($update);

        return true;

    }

}
