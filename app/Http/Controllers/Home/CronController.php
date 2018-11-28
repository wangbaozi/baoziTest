<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;

use App\Models\Basic\Product;
use App\Models\Basic\Category;
use App\Models\Basic\Currency;
use App\Models\Basic\Brand;
use App\Models\Basic\Artist;
use App\Models\Basic\Title;
use App\Models\Basic\Supp;

use App\Models\FilesReader;
use App\Models\Link\Cron;
use App\Models\Link\Download;
use App\Models\Link\FileDb;
use App\Models\Link\Account;

use App\Models\Basic\ProductImg;

/*
 * 检查，登录，下载，解压，解析，入库
-1.1 检查有几个下载任务，设置不超过 DOWNLIMIT = 5下载任务
     可以继续执行下载 = DOWNLIMIT - 正在下载中的任务个数
-1.2 使用ftp/http 账号登录
-1.3 检查文件大小，标记下载任务开始
-1.4 下载任务结束，解压单个/多个文件
-1.5 检测文件格式，单个/多个数量，大小
-1.6 解析文件单个/多个
-1.7 每条数据记录一个hash值，与数据库比较，
     hash值相等表示数据不变，只更新时间
     hash值不相等表示为新数据，新增数据
*/

class CronController extends Controller
{
    //下载地址
    public $dirDown = 'download';
    //文件类型名称
    public $fileExt = '';
    //允许下载的文件类型
    public $allowExt = [];
    //需要解压的问文件类型
    public $unzipExt = [];
    //新增id
    public $insertGetId = 0;
    //文件大小
    public $fileSize = 0;
    //文件夹下所有文件
    public $dirFiles = [];
    //遍历相关表ids
    public $fields = [];
    //数据映射模版ID
    public $fd_id = 0;
    //任务cron_id
    public $cron_id = 0;
    //商家mcid
    public $mcid = 0;
    //商家supp_id
    public $supp_id = 0;
    //商家数据新增，全量0，1增量
    public $is_full = 1;
    //映射关系内容
    public $fileDBinfo = [];
    //设置一次可以下载几个cron任务
    const DOWNLIMIT = 3;
    //设置一次解析文件个数
    const PARSELIMIT = 5;
    //每次读取文件内容行数
    const PAGESIZE = 2000;
    //异常信息编号
    public $failNo = 0;
    //url关键字过滤
    public $special = '';
    //cron执行中 状态超时时间
    const OVERTIME = 3600;
    //CDN图片Host
    public  $cdnHost = '';





    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $rarFiles = new FilesReader\RarFiles();
        $this->allowExt = $rarFiles->allowExt;
        $this->unzipExt = $rarFiles->unzipExt;
        $this->cdnHost = config('alioss.PREFIXURLCDNIMG');

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
     * 检查可下载文件并执行下载，解压，解析，入库
     *
     * @param
     * @return void
     * @author lonn.chen
     */
    public function down()
    {
        //检查cron超过1小时的执行中任务
        $this->reCron();
        //取cron任务表'nextrun' <= time()
        $count = Cron::getIns()->getDataList(['cron_status' => 1, 'status' => 1], true);
        if(isset($count) && $count >= self::DOWNLIMIT ){
            Log::info('cronLog down 任务超过'.self::DOWNLIMIT.'个， count = ' . $count );
            return;
        }
        $limit = self::DOWNLIMIT - $count;
        $limit = ($limit > self::DOWNLIMIT || $limit < 0) ? self::DOWNLIMIT : $limit;
        //取可执行的数据，执行状态为0，符合执行时间 nextrun <= time
        $list = Cron::getIns()->getCronList($limit);
        $list = json_decode($list, true);
        Log::info('cronLog down 已有' . $count . '个任务 , list = ' . json_encode($list) . ' ');
        foreach ((array)$list as $k => $v) {
            //先全部标记为执行中
            //标记任务:执行中 cron_status = 1
            $update = ['cron_status' => 1, 'update_time' => date('Y-m-d H:i:s')];
            Cron::getIns()->where('cron_id', $v['cron_id'])->update($update);
            Log::info('cronLog down 更新执行时间 cron_status = 1 , cron_id = ' . $v['cron_id']);
        }
        foreach ((array)$list as $k => $v) {
            $v['username'] = '';
            $v['password'] = '';
            if ($v['account_id'] > 0) {
                $acc = Account::getIns()->where('account_id', $v['account_id'])->first();
                $acc = json_decode($acc, true);
                $v['username'] = $acc['username'];
                $v['password'] = $acc['password'];
            }
            $this->mcid = $v['mcid'];
            $this->is_full = $v['is_full'];
            $this->cron_id = $v['cron_id'];
            $this->checkPath($v);
        }
    }

    /**
     * 检查可下载文件并执行下载，解压，解析，入库
     *
     * @param
     * @return void
     * @author lonn.chen
     */
    public function parse()
    {
        ini_set('memory_limit', '10240M');
        //取download下载表
        $count = Download::getIns()->getDataList(['read_status' => 1, 'status' => 2], true);
        if(isset($count) && $count >= self::PARSELIMIT ){
            Log::info('cronLog down 任务超过'.self::PARSELIMIT.'个，count = ' . $count );
            return;
        }
        $limit = self::PARSELIMIT - $count;
        $limit = ($limit > self::PARSELIMIT || $limit < 0) ? self::PARSELIMIT : $limit;
        //取可执行的数据
        $list = Download::getIns()->getDownList(['status' => 2, 'read_status' => 0, 'start' => 0, 'length' => $limit]);
        $list = json_decode($list, true);
        Log::info('cronLog parse 已有' . $count . '个任务 , list = ' . json_encode($list) . ' ');
        foreach ((array)$list as $k => $v) {
            //先全部标记为：解析中
            $this->updateReadstatus($v['down_id'],1);
        }
        foreach ((array)$list as $k => $v) {
            //手动添加解析文档
            if($v['cron_id'] > 0  && empty($v['down_text'])){
                //取cron_id ,fd_id
                $cronInfo = Cron::getIns()->where('cron_id', $v['cron_id'])->first();
                $cronInfo = json_decode($cronInfo, true);
                $fd_id = $cronInfo['fd_id'];
                $this->cron_id = $cronInfo['cron_id'];
                $this->mcid = $cronInfo['mcid'];
                $this->supp_id = $cronInfo['supp_id'];
                $this->is_full = $cronInfo['is_full'];
                $file_path = $cronInfo['file_path'];
            }else{
                $down_text = json_decode($v['down_text'],true);
                $this->cron_id = 0;
                $this->mcid = $down_text['mcid'];
                $this->supp_id = $down_text['supp_id'];
                $this->is_full = 0;
                $fd_id = $v['fd_id'];
                $this->special = $down_text['special'];
                $file_path = '';
            }


            $getDBinfo = FileDb::getIns()->where('fd_id', $fd_id)->first();
            $getDBinfo = json_decode($getDBinfo, true);
            $this->fileDBinfo = $getDBinfo;
            $this->fileDBinfo['file_path'] = $file_path;

            $this->parseFile($v['file_url'], $v['down_id']);
        }

    }


    /**
     * 取文件信息，并生成新的文件名称
     *
     * @param $fileUrl
     * @return void
     * @author lonn.chen
     */
    protected function getFileinfo($fileUrl, $ext = '')
    {
        if (empty($fileUrl)) {
            return false;
        }
        //获取文件的扩展名
        if (empty($ext)) {
            $fileExt = strtolower(pathinfo($fileUrl, PATHINFO_EXTENSION));
        } else {
            #TODO
            $fileExt = $ext;
        }

        //检测文件类型是否允许下载
        if (!in_array($fileExt, $this->allowExt)) {
            Log::error('cronLog getFileinfo fileUrl = ' . $fileUrl . ' , fileExt = ' . $fileExt . ' 不支持该文件类型下载 ');
            return false;
        }
        //生成新文件名
        $newName = date('His') . rand(10, 99);

        $fileDir = $this->dirDown . '/' . date('Ymd') . '/' . strtolower($this->mcid) . '/' . $newName . '/';
        //创建保存目录
        if (!file_exists(storage_path() . '/' . $fileDir) && !mkdir(storage_path() . '/' . $fileDir, 0777, true)) {
            Log::error('cronLog getFileinfo ' . $fileDir . ' 创建文件夹失败 ');
            return false;
        }


        if ($fileExt == 'gz') {
            $getExt = strtolower(substr($fileUrl, -6));
            if ($getExt == 'xml.gz') $fileExt = 'xml.' . $fileExt;
            if ($getExt == 'txt.gz') $fileExt = 'txt.' . $fileExt;
        }else if ($fileExt == 'zip') {
            $getExt = strtolower(substr($fileUrl, -7));
            if ($getExt == 'csv.zip') $fileExt = 'csv.' . $fileExt;
        }

        $newFilename = $fileDir . $newName . '.' . $fileExt;

        Log::info('cronLog getFileinfo newFilename = ' . $newFilename . ' ');

        return [
            'fileExt' => $fileExt,
            'fileDir' => $fileDir,
            'newName' => $newName,
            'newFilename' => $newFilename,
        ];
    }


    /**
     * 分块解析单个文件内容，入库
     * rela 0:相对地址，1:绝对地址
     * @param $fileName
     * @return void
     * @author lonn.chen
     */
    protected function getFileContent($fileName, $rela = 0)
    {
        set_time_limit(0);
        $result = true;
        //取文件信息
        //$fileInfo = $this->getFileinfo($fileName);
        $fileInfo = pathinfo($fileName);
        switch ($fileInfo['extension']) {
            case 'xml':
                $result = $this->getXmlContent($fileName, $rela);
                break;

            default:
                $result = $this->getTxtContent($fileName, $rela);
                break;
        }

        return $result;

    }


    /**
     * csv分块解析单个文件内容，入库
     * rela 0:相对地址，1:绝对地址
     * @param $fileName
     * @return void
     * @author lonn.chen
     */
    protected function getTxtContent($fileName, $rela = 0)
    {
        Log::info('cronLog getTxtContent fileName = ' . $fileName . ', rela = ' . $rela . ', fileDBinfo = ' . json_encode($this->fileDBinfo));
        $txtFiles = new FilesReader\TxtFiles($fileName, $this->fileDBinfo['fd_prefix'], $rela);
        $line_number = $txtFiles->get_lines();
        $titles = [];
        //取第1行标题内容
        //有标题文档，可以使用键值
        if (isset($this->fileDBinfo['fd_type']) && $this->fileDBinfo['fd_type'] == 2) {
            //取第1行标题内容
            $txtFilesTitle = $txtFiles->get_data(1, 0);
            $titles = $txtFilesTitle[0];
            if (empty($titles) && empty(json_encode($titles))) {
                $this->failNo = 4;
                Log::error('cronLog getTxtContent 失败  titles 为空  , fileName = ' . $fileName);
                //标记解析失败
                return false;
            }
            Log::info('cronLog getTxtContent fileName = ' . $fileName . ' titles = ' . json_encode($titles));
        }
        $modSize = ceil($line_number / self::PAGESIZE);
        Log::info('cronLog getTxtContent line_number = ' . $line_number . ' , modSize = ' . $modSize);

        for ($i = 0; $i < $modSize; $i++) {
            $start = $i * self::PAGESIZE;
            $content = $txtFiles->get_data(self::PAGESIZE, $start);
            //Log::info('cronLog insertProductDB content = '.json_encode($content) );
            Log::info('cronLog getTxtContent start = ' . $start);
            //第1次删除第1行标题
            if ($start == 0) {
                array_shift($content);
            }
            $insert = $this->insertProductDB($titles, $content);
            if (isset($content)) {
                unset($content);
            }
            //写入失败
            if ($insert == false) {
                $this->failNo = 6;
                Log::error('cronLog getTxtContent 失败 insert = false, start = ' . $start . ', content= ' . json_encode($content) . '  ');
                return false;
                break;
            }
            Log::info('cronLog getTxtContent end = ' . ($start + self::PAGESIZE));
        }

        return true;

    }


    /**
     * xml分块解析单个文件内容，入库
     * rela 0:相对地址，1:绝对地址
     * @param $fileName
     * @return void
     * @author lonn.chen
     */
    protected function getXmlContent($fileName, $rela = 0)
    {
        #TODO
        //适配文件映射模版中的前缀符号cro
        $fd_prefix = 'product';
        if ($this->fileDBinfo['fd_prefix'] && $this->fileDBinfo['fd_type'] == 1) {
            $fd_prefix = $this->fileDBinfo['fd_prefix'];
        }
        $xmlFiels = new FilesReader\XmlFiles($fileName, $rela);
        $line_number = $xmlFiels->get_lines($fd_prefix);
        $modSize = ceil($line_number / self::PAGESIZE);
        Log::info('cronLog getTxtContent line_number = ' . $line_number . ' , modSize = ' . $modSize);
        for ($i = 0; $i < $modSize; $i++) {
            $start = $i * self::PAGESIZE;
            $content = $xmlFiels->get_data(self::PAGESIZE, $start);
            //Log::info('cronLog insertProductDB content = '.json_encode($content) );
            Log::info('cronLog getXmlContent start = ' . $start);

            //写入失败
            $inserts = $this->getXmlDataLines($content);
            if (isset($content)){
                unset($content);
            }
            //批量写入数据库
            $result = Product::getIns()->insert($inserts);

            if ($result == false) {
                $this->failNo = 6;
                Log::error('cronLog getXmlContent 失败 result = false, start = ' . $start . ', inserts = ' . json_encode($inserts) . '  ');
                return false;
                break;
            }
            Log::info('cronLog getXmlContent end = ' . ($start + self::PAGESIZE));
        }

        return true;

    }


    /**
     * 入库
     *
     * @param array
     * @return void
     * @author lonn.chen
     */
    protected function insertProductDB($titles, $content)
    {

        foreach ($content as $item => $value) {
            for ($i = 0; $i < count($value); $i++) {
                $lines[$item][$i] = $value[$i];
            }
        }
        if (isset($content)){
            unset($content);
        }
        //Log::info('cronLog insertProductDB lines = '.json_encode($lines).'  ');

        if ($titles) {
            $titles = strtolower(json_encode($titles));//转小写
            $titles = json_decode($titles);
        }
        $result = false;
        $inserts = $this->getTxtDataLines($lines, $titles);
        //批量写入数据库
        $result = Product::getIns()->insert($inserts);
        //写入失败
        if ($result == false) {
            $this->failNo = 6;
            Log::error('cronLog insertProductDB 失败  Product->inserts  ');
        }
        return $result;
    }

    /**
     * 解析txt文档内容
     *
     * @param array
     * @return void
     * @author lonn.chen
     */
    protected function getTxtDataLines($lines, $titles = '')
    {
        $fdjson = '';
        if ($this->fileDBinfo['file_db']) {
            $fdjson = strtolower($this->fileDBinfo['file_db']);//转小写
            $fdjson = json_decode($fdjson, true);
            //$fdjson = array_flip($fdjson);//调换键值
            //Log::info('cronLog getTxtDataLines file_db = ' . json_encode($fdjson));
        }
        $inserts = [];
        $updateSku = [];
        //Log::info('cronLog getTxtDataLines lines = '.json_encode($lines));
        $l = 0;
        foreach ((array)$lines as $lk => $lv) {
            //Log::info('cronLog getTxtDataLines line = '.json_encode($lv));
            //是否空行，第一个字段不能为空
            if (empty($lv[0]) && (($lv[0] == 'HDR' || $lv[0] == 'TRL') || $lv[0] == null)) continue;
            $isSku = false;
            $sku = '';

            //映射键值
            foreach ((array)$fdjson as $jk => $jv) {

                //这边只能解析txt文档
                if ($this->fileDBinfo['fd_type'] == 1) {
                    $this->failNo = 7;
                   // $i = $jv;
                    Log::error('cronLog getTxtDataLines 模版选择错误 fd_type = 1 content= ' . json_encode($lines) . ' ');
                    return false;
                }
                //有标题文档，可以使用键值
                if ($this->fileDBinfo['fd_type'] == 2) {
                    if (strpos($jv, '>>') === false) {
                        $i = array_search(trim($jv), $titles);
                    }

                    if (is_array($lv)) {
                        if (isset($lv[$i]) && strpos($jv, '>>') === false) {
                            $inserts[$l][$jk] = $lv[$i];
                        } else {
                            $inserts[$l][$jk] = $this->getTxtNode($lv, $jv, null, $titles);
                        }
                    }
                }
                //无标题文档解析，只能用数字键值
                if ($this->fileDBinfo['fd_type'] == 3) {
                    $i = intval($jv) - 1;
                    if ($i < 0) $i = 0;
                    if (isset($lv[$i]) && ($lv[0] == 'TRL' || $lv[0] == 'HDR' || $lv[0] == null)) continue;

                    if (isset($lv[$i])) $inserts[$l][$jk] = $lv[$i];
                }
                //Log::info('cronLog getTxtDataLines jk = '.$jk.', lk = '.$l.', i = '.$i);


                if ($jk == 'product_buyurl' && isset($inserts[$l]['product_buyurl'])) {
                    $urlKey = '';
                    if(isset($this->fileDBinfo['fd_id']) && $this->fileDBinfo['fd_id'] == 9 ) $urlKey = 'murl=';
                    $inserts[$l]['product_buyurl'] = $this->specialUrl($inserts[$l]['product_buyurl'],$urlKey);
                }

                if (strpos($jv, '#') !== false) {
                    $val = substr($jv,1);
                    $inserts[$l][$jk] = $val;
                }

                //如果字段是product_category,product_currency等则需记录品类再返回ID
                if (in_array($jk, $this->getJKArray()) && isset($inserts[$l][$jk]) && $inserts[$l][$jk] != '') {
                    #TODO
                    $arr = ["hashVal" => strtolower(trim($inserts[$l][$jk])), "val" => $inserts[$l][$jk], "lk" => $l, 'jk' => $jk, "Model" => "", "key" => "", "id" => "",];
                    $this->getReplaceValue($arr);
                    if (isset($this->fields[$arr['jk']][$arr['hashVal']])) $inserts[$l][$jk] = $this->fields[$arr['jk']][$arr['hashVal']];
                    if ($arr['jk'] == 'product_sku') {
                        $isSku = true;
                        if (isset($this->fields['product_sku'][$arr['hashVal']])){
                            $sku = $this->fields['product_sku'][$arr['hashVal']];
                        }
                    }
                }

                //过滤字符串头尾的引号
                if(in_array($jk,array('product_name','product_keywords','product_description','product_title')) && isset($inserts[$l][$jk])){
                    $inserts[$l][$jk] = $this->replaceQuote($inserts[$l][$jk]);
                }

            }


            //写入商家supp_id
            if ($this->supp_id && isset($inserts[$l])) {
                $inserts[$l]['product_supp'] = $this->supp_id;
            }
            //设置创建时间，更新时间
            if (isset($inserts[$l])) {
                $inserts[$l]['create_time'] = date('Y-m-d H:i:s');
                $inserts[$l]['update_time'] = date('Y-m-d H:i:s');
            }
            //售价为空的时候=原价
            if(isset($inserts[$l]['product_saleprice']) && intval($inserts[$l]['product_saleprice']) <= 0){
                $inserts[$l]['product_saleprice'] = $inserts[$l]['product_price'];
            }
            //原价为空的时候=售价
            if(isset($inserts[$l]['product_price']) && intval($inserts[$l]['product_price']) <= 0){
                $inserts[$l]['product_price'] = $inserts[$l]['product_saleprice'];
            }

            if(isset($inserts[$l]['ext1'])){
                $inserts[$l]['ext1'] = $this->getFilterExt($inserts[$l]['ext1'],'<LSN_DELIMITER>');
            }

            if(isset($inserts[$l]['ext2'])){
                $inserts[$l]['ext2'] = $this->getFilterExt($inserts[$l]['ext2'],'<LSN_DELIMITER>');
            }

            if(isset($inserts[$l]['ext3'])){
                $inserts[$l]['ext3'] = $this->getFilterExt($inserts[$l]['ext3'],'<LSN_DELIMITER>');
            }


            //Log::info('cronLog getTxtDataLines  inserts = '.json_encode($inserts).'  ');
            if ($isSku == true && $sku) {
                if (isset($inserts[$l]['create_time'])){
                    unset($inserts[$l]['create_time']);//删除创建时间
                }
                $updateSku[] = $sku;
                //重复sku，更新product，并删除该行数据
                $this->updateProductBySku($sku, $inserts[$l]);
                if(isset($inserts[$l])){
                    unset($inserts[$l]);
                }
            }
            //Log::info('cronLog getTxtDataLines inserts = '.json_encode($inserts).'  ');
            $l++;
        }
        if(!empty($updateSku)){
            Log::info('cronLog getTxtDataLines updateSku = '.json_encode($updateSku).'  ');
        }

        return $inserts;
    }

    /**
     * 处理url
     *
     * @param array
     * @return void
     * @author lonn.chen
     */
    protected function specialUrl($value, $key = '')
    {
        if (empty($value)){
            return ;
        }

        $resultUrl = $value;
        $file_path = json_decode($this->fileDBinfo['file_path'], true);
        if(empty($key) && isset($file_path['special'])){
            $key = $file_path['special'];
        }
        if(empty($key) && !empty($this->special)){
            $key = $this->special;
        }
        if (!empty($key)) {
            if (strpos($value, $key) !== false) {
                $query = parse_url($value, PHP_URL_QUERY);
                parse_str($query, $result);
                $key = substr($key,0,-1);
                //Log::info('cronLog specialUrl FullUrl = '.$value.', key = '.$key);
                if(isset($result[$key])) $resultUrl = $result[$key];
            }
        }

        return $resultUrl;
    }

    /**
     * 解析xml文档内容
     *
     * @param array
     * @return void
     * @author lonn.chen
     */
    protected function getXmlDataLines($lines)
    {

        $fdjson = '';
        if ($this->fileDBinfo['file_db']) {
            $fdjson = strtolower($this->fileDBinfo['file_db']);//转小写
            $fdjson = json_decode($fdjson, true);
            //$fdjson = array_flip($fdjson);//调换键值
            //Log::info('cronLog getXmlDataLines file_db = '.json_encode($fdjson));
        }
        $lines = json_encode($lines);
        $lines = json_decode($lines, true);

        $inserts = [];
        $l = 0;
        foreach ($lines as $lk => $lv) {
            $isSku = false;
            $sku = '';
            $lv = json_encode($lv);
            $lv = json_decode($lv, true);
            //映射键值
            foreach ((array)$fdjson as $jk => $jv) {

                if (is_array($lv)) {
                    if (strpos($jv, '>>') === false) {
                        $inserts[$l][$jk] = $this->getXmlVal($lv, $jv);
                    } else {
                        $inserts[$l][$jk] = $this->getXmlNode($lv, $jv);
                    }
                }
                //Log::info('cronLog getXmlDataLines inserts = '.json_encode($inserts).'  ');

                if (strpos($jv, '#') !== false) {
                    $val = substr($jv,1);
                    $inserts[$l][$jk] = $val;
                }

                if ($jk == 'product_buyurl' && isset($inserts[$l]['product_buyurl'])) {
                    $urlKey = '';
                    if(isset($this->fileDBinfo['fd_id']) && $this->fileDBinfo['fd_id'] == 9 ) $urlKey = 'murl=';
                    $inserts[$l]['product_buyurl'] = $this->specialUrl($inserts[$l]['product_buyurl'],$urlKey);
                }

                #TODO
                //如果字段是product_category,product_currency等则需记录品类再返回ID
                if (in_array($jk, $this->getJKArray()) && isset($inserts[$l][$jk]) && $inserts[$l][$jk] != '') {
                    $arr = ["hashVal" => strtolower(trim($inserts[$l][$jk])), "val" => $inserts[$l][$jk], "lk" => $l, 'jk' => $jk, "Model" => "", "key" => "", "id" => "",];
                    //Log::info('cronLog getXmlDataLines arr = '.json_encode($arr).'  ');
                    $this->getReplaceValue($arr);
                    if (isset($this->fields[$arr['jk']][$arr['hashVal']])) $inserts[$l][$arr['jk']] = $this->fields[$arr['jk']][$arr['hashVal']];
                    if ($arr['jk'] == 'product_sku') {
                        $isSku = true;
                        if (isset($this->fields['product_sku'][$arr['hashVal']])) $sku = $this->fields['product_sku'][$arr['hashVal']];
                    }
                }

            }
            //写入商家supp_id
            if ($this->supp_id && isset($inserts[$lk])) {
                $inserts[$l]['product_supp'] = $this->supp_id;
            }
            //设置创建时间，更新时间
            if (isset($inserts[$l])) {
                $inserts[$l]['create_time'] = date('Y-m-d H:i:s');
                $inserts[$l]['update_time'] = date('Y-m-d H:i:s');
            }
            //售价为空的时候=原价
            if(isset($inserts[$l]['product_saleprice']) && intval($inserts[$l]['product_saleprice']) <= 0){
                $inserts[$l]['product_saleprice'] = $inserts[$l]['product_price'];
            }
            //原价为空的时候=售价
            if(isset($inserts[$l]['product_price']) && intval($inserts[$l]['product_price']) <= 0){
                $inserts[$l]['product_price'] = $inserts[$l]['product_saleprice'];
            }
            if ($isSku == true && $sku) {
                if (isset($inserts[$l]['create_time'])) unset($inserts[$l]['create_time']);//删除创建时间
                //重复sku，更新product，并删除该行数据
                $this->updateProductBySku($sku, $inserts[$l]);
                if (isset($inserts[$l])){
                    unset($inserts[$l]);
                }
            }
            $l++;
        }
        if (isset($lines)){
            unset($lines);
        }
        //Log::info('cronLog getXmlDataLines inserts = '.json_encode($inserts).'  ');

        /*
               //取各个关联字段组合成数组
               $relaFields = $this->getRelaFields($inserts);

               $fields = [ 'field'=>'brand_id,brand_name_en','table'=>'sl_basic_brand','key'=>'brand_id', 'value'=>'brand_name_en'];
               $r = Cron::getIns()->getArrayQuery('brand_name_en',$fields,$selects['product_brand']);
       */

        return $inserts;
    }

    /**
     * 取各个关联字段组合成数组
     *
     * @param array
     * @return void
     * @author lonn.chen
     */
    protected function getDBfieldsVal($relaFields)
    {
        foreach ($relaFields as $key => $val) {
            switch ($key) {
                case 'product_supp';
                    $fields = ['field' => 'supp_id,supp_name_en', 'table' => 'sl_basic_supp', 'key' => 'supp_id', 'value' => 'supp_name_en'];
                    break;
                case 'product_category';
                    $fields = ['field' => 'cat_id,cat_name_en', 'table' => 'sl_basic_cat', 'key' => 'cat_id', 'value' => 'cat_name_en'];
                    break;
                case 'product_currency';
                    $fields = ['field' => 'cur_id,cur_name_en', 'table' => 'sl_basic_currency', 'key' => 'cur_id', 'value' => 'cur_name_en'];
                    break;
                case 'product_brand';
                    $fields = ['field' => 'brand_id,brand_name_en', 'table' => 'sl_basic_brand', 'key' => 'brand_id', 'value' => 'brand_name_en'];
                    break;
                case 'product_artist';
                    $fields = ['field' => 'artist_id,artist_name_en', 'table' => 'sl_basic_artist', 'key' => 'artist_id', 'value' => 'artist_name_en'];
                    break;
                case 'product_title';
                    $fields = ['field' => 'tit_id,tit_name_en', 'table' => 'sl_basic_title', 'key' => 'tit_id', 'value' => 'tit_name_en'];
                    break;
                case 'product_sku';
                    $fields = ['field' => 'product_id,product_name_en', 'table' => 'sl_basic_product', 'key' => 'product_id', 'value' => 'product_name_en'];
                    break;

            }
            $result[$key] = Cron::getIns()->getArrayQuery($fields['value'], $fields, $val);
        }

        return $result;

    }

    /**
     * 取各个关联字段组合成数组
     *
     * @param array
     * @return void
     * @author lonn.chen
     */
    protected function getRelaFields($inserts)
    {
        //取各个关联字段组合成数组
        $fields = [];
        foreach ((array)$inserts as $ikey => $ival) {
            foreach ($ival as $ivkey => $ivval) {
                if (in_array($ivkey, $this->getJKArray())) {
                    $fields[$ivkey][] = $ivval;
                }
            }
        }

        return $fields;
    }


    /**
     * 获取多维数组指定节点值,针对大小分类，节点有双箭头>>标记
     * @param array & $arr 引用被操作数组的内存地址
     * @param string $node 节点路径, 如: a.b.c => $arr['a']['b']['c'];
     * @param null|mixed $default 若节点不存在时返回该默认值
     * @return mixed
     */
    protected function getTxtNode(array & $arr, $node, $default = null, $title = [])
    {
        if (empty($arr)){
            return $default;
        }
        array_change_key_case($arr);
        if (strpos($node, '>>') !== false) {
            $keys = explode('>>', $node);
            $val = '';
            foreach ($keys as $key) {
                //取key对应title的数字键值
                $key = array_search($key, $title);
                $r = $this->getTxtVal($arr, $key);
                if ($r) $val .= ' > ' . $r;
            }
            $val = substr($val, 2);
            //Log::info('cronLog getTxtNode val = '.$val );
            //只有以及分类
            if (isset($arr['category']) && count($arr['category']) == 1) $val = substr($val, 0, -2);
            return $val;

        } else {
            $arr = $this->getTxtVal($arr, $node);
        }
        return $arr;
    }

    /**
     * 获取多维数组指定节点值
     * @param array & $arr 引用被操作数组的内存地址
     * @param string $node 节点路径, 如: a.b.c => $arr['a']['b']['c'];
     * @param null|mixed $default 若节点不存在时返回该默认值
     * @return mixed
     */
    protected function getTxtVal(array & $arr, $keys, $default = null)
    {
        if (empty($arr)){
            return $default;
        }
        //Log::info('cronLog getTxtVal arr = '.json_encode($arr).', key = '.$keys );
        array_change_key_case($arr);
        if (isset($arr[$keys]) && !is_array($keys)) {
            return $arr = &$arr[$keys];
        }

        //$keys = explode('->',$node);
        foreach ((array)$keys as $key) {
            //if($key == '@') $key = '@attributes';
            $key = strval($key);
            if (isset($arr[$key])) {
                $arr = &$arr[$key];
            } else {
                return $default;
            }
        }

        return $arr;
    }

    /**
     * 获取多维数组指定节点值,针对大小分类，节点有双箭头>>标记
     * @param array & $arr 引用被操作数组的内存地址
     * @param string $node 节点路径, 如: a.b.c => $arr['a']['b']['c'];
     * @param null|mixed $default 若节点不存在时返回该默认值
     * @return mixed
     */
    protected function getXmlNode(array & $arr, $node, $default = null)
    {
        if (empty($arr)){
            return $default;
        }

        array_change_key_case($arr);
        if (strpos($node, '>>') !== false) {
            $keys = explode('>>', $node);
            $r = '';
            foreach ($keys as $key) {
                $r .= ' > ' . $this->getXmlVal($arr, $key);
            }
            $r = substr($r, 2);
            //只有以及分类
            if (count($arr['category']) == 1){
                $r = substr($r, 0, -2);
            }
            return $r;

        }
        return $arr;
    }

    /**
     * 获取多维数组指定节点值
     * @param array & $arr 引用被操作数组的内存地址
     * @param string $node 节点路径, 如: a.b.c => $arr['a']['b']['c'];
     * @param null|mixed $default 若节点不存在时返回该默认值
     * @return mixed
     */
    protected function getXmlVal(array & $arr, $node, $default = null)
    {
        if (empty($arr)){
            return $default;
        }

        array_change_key_case($arr);
        $keys = explode('->', $node);
        foreach ($keys as $key) {
            if ($key == '@') $key = '@attributes';
            $key = strval($key);
            if (isset($arr[$key])) {
                $arr = &$arr[$key];
            } else {
                return $default;
            }
        }

        return $arr;
    }

    /**
     * 检查是否为参数配置路径
     *
     * @param array
     * @return void
     * @author lonn.chen
     */
    protected function checkPath($v)
    {
        if ($v['link_type'] == 'ftp') {
            $host = $this->getFtpHost($v['link_host']);
        } else {
            $host = $this->getHttpHost($v['link_host']);
        }
        $ext = '';

        $host = $this->getHostUrl($host, $v['link_type']);

        $isjson = $this->is_json($v['file_path']);
        $result = false;
        if ($isjson == true) {
            $file_path = json_decode($v['file_path'], true);
            Log::info('cronLog checkPath file_path = ' . json_encode($file_path));
            if ($v['link_type'] == 'http' || $v['link_type'] == 'https') {
                //有后缀名,允许下载的文件
                if (isset($file_path['ext']) && in_array($file_path['ext'],$this->allowExt)){
                    $ext = $file_path['ext'];
                }
            }
            if (isset($file_path['type']) && $file_path['type'] >= 1 && $v['link_type'] == 'ftp') {
                $result = $this->getFtpFiels($v, $file_path);
            }elseif ((!isset($file_path['type']) || $file_path['type'] == 0) && isset($file_path['path'])) {
                $fileUrl = $host . '/' . $file_path['path'];
                $result = $this->downFile($v, $fileUrl, $ext);
            }
            

        } else {
            $fileUrl = $host . $v['file_path'];
            Log::info('cronLog checkPath fileUrl = '.$fileUrl);
            $result = $this->downFile($v, $fileUrl, $ext);
        }
        if(empty($result)){
            //不存在文件夹的时候直接标记执行失败
            //标记任务:执行失败 cron_status = 3
            //$this->failNo = 3;
            $update = ['cron_status' => 3, 'update_time' => date('Y-m-d H:i:s'),'fail_no'=>$this->failNo];
            Cron::getIns()->where('cron_id', $v['cron_id'])->update($update);
            Log::error('cronLog checkPath 执行失败 cron_status = 3 , cron_id = ' . $v['cron_id']);

            return false;
        }

        return true;

    }

    /**
     * 检查ftp Host
     *
     * @param array
     * @return void
     * @author lonn.chen
     */
    protected function getFtpHost($host)
    {
        if (empty($host)){
            return false;
        }

        if (strpos($host, 'ftp://') === false) {
            $result = $host;
        } else {
            //host = 'ftp://127.0.0.1'
            $result = substr($host, 6);
        }
        //线上环境ftpHost替换
        $ftp_hosts = env('SP_ONLINE_FTP_HOSTS');
        $ftp_hosts = explode('-',$ftp_hosts);
        if( env('APP_ENV') == 'production' && $ftp_hosts[0] == $result){
           $result = $ftp_hosts[1];
        }

        return $result;
    }

    /**
     * 检查ftp,http 取Host
     *
     * @param array
     * @return void
     * @author lonn.chen
     */
    protected function getHttpHost($host)
    {
        if (empty($host)){
            return false;
        }

        if (strpos($host, 'http://') === false && strpos($host, 'https://') === false) {
            $result = $host;
        } else {
            //host = 'http(s)://://127.0.0.1'
            if (strpos($host, 'http://') !== false) {
                $result = substr($host, 7);
            }
            if (strpos($host, 'https://') !== false) {
                $result = substr($host, 8);
            }

        }
        return $result;
    }

    /**
     * 检查ftp,http 拼接Host Url
     *
     * @param array
     * @return void
     * @author lonn.chen
     */
    protected function getHostUrl($host, $type = '')
    {
        if (empty($type)){
            return $host;
        }

        switch ($type) {
            case "ftp":
                $host = 'ftp://' . $host;
                break;
            case "http":
                $host = 'http://' . $host;
                break;
            case "https":
                $host = 'https://' . $host;
                break;
        }

        return $host;

    }

    /**
     * 下载
     *
     * @param array
     * @return void
     * @author lonn.chen
     */
    protected function downFile($v, $fileUrl = '', $ext = '',$isDir = 0)
    {
        //取文件信息
        $fileInfo = $this->getFileinfo($fileUrl, $ext);

        if (empty($fileInfo)){
            return false;
        }

        $fileSavePath = storage_path() . '/' . $fileInfo['newFilename'];
        //下载文件记录保存，下载中
        $insertGetId = $this->insertDownID($v, $fileUrl, $fileInfo);
        //标记任务:执行中 cron_status = 1
        $update = ['cron_status' => 1, 'update_time' => date('Y-m-d H:i:s')];
        Cron::getIns()->where('cron_id', $v['cron_id'])->update($update);
        Log::info('cronLog downFile 更新执行时间 cron_status = 1 , cron_id = ' . $v['cron_id']);
        //http或ftp保存文件
        $rarFiles = new FilesReader\RarFiles();
        $result = $rarFiles->ftpDown($fileUrl, $fileInfo, $v['username'], $v['password']);
        if ($result == false && $insertGetId) {
            //下载失败
            $this->failNo = 2;
            $update = ['status' => 3, 'down_num' => 1,'update_time' => date('Y-m-d H:i:s'),'fail_no'=>$this->failNo];
            Download::getIns()->where('down_id', $insertGetId)->update($update);
            Log::error('cronLog downFile 下载失败 status = 3 , down_id = ' . $insertGetId);

            if($isDir == 0) {
                //只有单个文件下载,标记任务:执行失败 cron_status = 3
                $update = ['cron_status' => 3, 'update_time' => date('Y-m-d H:i:s'),'fail_no' => $this->failNo];
                Cron::getIns()->where('cron_id', $v['cron_id'])->update($update);
                Log::error('cronLog downFile 执行失败 cron_status = 3 , cron_id = ' . $v['cron_id']);
            }
        }
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
        if ($insertGetId && $result) {
            $update = ['status' => 2, 'update_time' => date('Y-m-d H:i:s'), 'down_time' => date('Y-m-d H:i:s'), 'file_size' => $fileSize, 'file_unit' => $fileUnit];
            Download::getIns()->where('down_id', $insertGetId)->update($update);
            Log::info('cronLog downFile 下载完成 status = 2 , down_id = ' . $insertGetId);
            if($isDir == 0){
                //只有单个文件下载，直接更新cron_status=2,并更新执行时间
                $this->updateCronstatus($v);
            }
        }

        return true;
    }

    /**
     * 下载完成标记cron_status=2,并更新下次执行时间
     *
     * @param array
     * @return void
     * @author lonn.chen
     */
    protected function updateCronstatus($v){
        //标记任务:执行完成 cron_status = 2 ,并更新下次执行时间
        $update = [
            'nextrun' => ($v['nextrun'] + $v['hour_rate'] * 60 * 60),
            'lastrun' => $v['nextrun'],
            'cron_status' => 2,
            'update_time' => date('Y-m-d H:i:s'),
            'cron_time' => date('Y-m-d H:i:s')
        ];
        if($this->is_full == 0){
            $update['is_full'] = 1;
        }
        $result = Cron::getIns()->where('cron_id', $v['cron_id'])->update($update);
        Log::info('cronLog downFile 更新执行时间 cron_status = 2 , cron_id = '.$v['cron_id'].', update = ' . json_encode($update));
        //下载完成后休息10秒
        //sleep(10);

        return $result;
    }

    /**
     * 判断是否为文件或文件夹,根据路径配置规则下载文件
     * 解析file_path规则
     * @param array
     * @return void
     * @author lonn.chen
     */
    protected function getFtpFiels($v, $file_path)
    {
        /*
        $v = ['link_host'=>'47.96.152.204','username'=>'linkhaitao_ftp','password'=>'linkhaitao_ftp@2018'];
        $v['file_path'] = '{"type":"1","path":"/42680","ext":"cmp_EN-AU_EUR.xml.gz"}';
         $file_path = json_decode($v['file_path'],true);
        */
        #TODO
        $result = false;

        $host = $this->getFtpHost($v['link_host']);
        Log::info('cronLog getFtpFiels cron_id = '.$this->cron_id.', host = ' . $host . ', cronInfo = ' . json_encode($v));

        $ftpFiles = new FilesReader\FtpFiles("ftp://{$v['username']}:{$v['password']}@{$host}:21");
//        $ftpFiles->connect($host, 21);
//        $ftpFiles->login($v['username'], $v['password']);
        $fileList = [];
        if (isset($file_path['path'])){
            $fileList = $ftpFiles->nlist($file_path['path']);
        }

        if(empty($fileList) || $fileList[0] == '/'){
            Log::error('cronLog getFtpFiels nlist 失败 cron_id = '.$this->cron_id.', fileList = '.json_encode($fileList));
            $this->failNo = 1;
            return false;
        }

        //取ftp文件夹列表按配置规则取最新的一个文件
        $Files = $this->getFtpDirFile($fileList, $file_path);

        if (is_array($Files)) {
            Log::info('cronLog getFtpFiels cron_id = '.$this->cron_id.', array count = '.count($Files).' Files = ' . json_encode($Files));
            $i = 1;
            foreach ($Files as $key => $value) {
                //拼接fileUrl=host+value
                if(isset($file_path['route']) && $file_path['route'] == 1){
                    $fileUrl = 'ftp://' . $host . '/' . $value;
                }else{
                    $fileUrl = 'ftp://' . $host . '/' . $file_path['path'] . '/' . $value;
                }
                $result = $this->downFile($v, $fileUrl,'',1);
                if($i / 20 == 0){
                    sleep(3);
                }
                $i++;

            }
            //批量下载完成之后再修改cron_status=2,并更新下次时间
            $this->updateCronstatus($v);
        } else {
            Log::info('cronLog getFtpFiels cron_id = '.$this->cron_id.',  Files = ' . $Files);
            //单个文件,//拼接fileUrl=host+path+value
            if ($file_path['type'] == 0) {
                $fileUrl = 'ftp://' . $host . '/' . $Files;
            } else {
                if(isset($file_path['route']) && $file_path['route'] == 1){
                    $fileUrl = 'ftp://' . $host . '/' . $Files;
                }else{
                    $fileUrl = 'ftp://' . $host . '/' . $file_path['path'] . '/' . $Files;
                }
            }

            $result = $this->downFile($v, $fileUrl);
        }

        return $result;

    }

    /**
     * 取文件夹下最新最大的txt文件
     *
     * @param array
     * @return void
     * @author lonn.chen
     */
    protected function getFtpDirFile($fileList, $file_path = [])
    {
        if (empty($fileList)){
            return false;
        }
        //ext 没有配置或配置ext=All 表示取路径下全部文件
        if (!isset($file_path['ext']) || (isset($file_path['ext']) && $file_path['ext'] == 'All')) {
            //Log::info('cronLog getFtpDirFile fileList = ' . json_encode($fileList));
            return $fileList;
        }
        $result = false;
        //按文件名称规则取文件
        if (isset($file_path['ext']) && !empty($file_path['ext']) && !empty($fileList)) {
            foreach ($fileList as $fkey => $fval) {
                if (strpos($fval, $file_path['ext']) !== false) {
                    $lists[] = $fval;
                }
            }

            //is_full 0:全量，1:增量
            if(isset($this->is_full) && $this->is_full == 0){
                $result = $lists;
            }else{
                //取最后一个最新文件
               if(is_array($lists))
                   $result = end($lists);
            }
            if (isset($lists)){
                unset($lists);
            }
        }

        return $result;

    }

    /**
     * 解压，解析，入库
     *
     * @param array
     * @return void
     * @author lonn.chen
     */
    protected function parseFile($fileUrl, $down_id)
    {
        //取文件信息
        $fileInfo = pathinfo($fileUrl);
        if (empty($fileInfo)){
            return false;
        }
        $result = false;
        Log::info('cronLog parseFile down_id = '.$down_id.', fileInfo = ' . json_encode($fileInfo));
        //标记解析文件状态，执行中 read_status = 1
        $this->updateReadstatus($down_id,1);
        $rarFiles = new FilesReader\RarFiles();
        //需要解压的文件
        if (in_array($fileInfo['extension'], $this->unzipExt)) {
            $fileExt = $fileInfo['extension'];
            if ($fileExt == 'gz') {
                $fileExt = strtolower(substr($fileUrl, -6));
            }
            $result = $rarFiles->unzipFiles($fileUrl, $fileInfo['dirname'], $fileInfo['filename'], $fileExt);
            //读取文件夹下所有文件
            $fullDirName = storage_path() . '/' . $fileInfo['dirname'];
            if (!file_exists($fullDirName) || $result == false) {
                $this->failNo = 4;
                Log::error('cronLog parseFile not exists ,down_id = '.$down_id.', fullDirName = ' . $fullDirName);
                //标记失败
                $this->updateReadstatus($down_id,3);
                return false;
            }

            $files = $rarFiles->getDirFiles($fullDirName);
            Log::info('cronLog parseFile down_id = '.$down_id.', fullDirName = ' . $fullDirName.', files = '.json_encode($files,true) );

            if(empty($files)){
                Log::error('cronLog parseFile getDirFiles 失败 ,down_id = '.$down_id.', files = ' . json_encode($files,true));
                //标记解析失败
                $this->updateReadstatus($down_id,3);
                return false;
            }

            foreach ($files as $file) {
                $result = $this->getFileContent($file, 1);
                if($result == false){
                    Log::error('cronLog parseFile getFileContent = fasle ,down_id = '.$down_id.', file = ' . $file);
                    //标记解析失败
                    $this->updateReadstatus($down_id,3);
                    return false;
                }
            }

        } else {
            //解析单个文件内容
            $result = $this->getFileContent($fileUrl);
            if($result == false){
                //标记解析失败
                Log::error('cronLog parseFile getFileContent = fasle ,down_id = '.$down_id.', fileUrl = ' . $fileUrl);
                $this->updateReadstatus($down_id,3);
                return false;
            }
        }

        if($result != false){
            //标记解析文件状态，解析完成 read_status = 2
            $this->updateReadstatus($down_id,2);
        }

    }

    /**
     * 标记解析状态
     * @param array
     * @return void
     * @author lonn.chen
     */
    protected function updateReadstatus($down_id,$read_status){
        $update = ['read_status' => $read_status, 'update_time' => date('Y-m-d H:i:s'), 'read_time' => date('Y-m-d H:i:s')];
        //写入错误编号
        if($read_status == 3){
            $update['fail_no'] = $this->failNo;
        }
        Download::getIns()->where('down_id', $down_id)->update($update);
        Log::info('cronLog updateReadstatus down_id = '.$down_id.', read_status = '.$read_status);
        return true;
    }

    /**
     * 下载文件记录保存，下载中
     * @param array
     * @return void
     * @author lonn.chen
     */
    protected function insertDownID($cronInfo, $fileUrl, $fileInfo)
    {
        //下载文件记录保存，下载中

        $insert = [
            'cron_id' => $cronInfo['cron_id'],
            'fd_id' => $cronInfo['fd_id'],
            'down_url' => $fileUrl,
            'file_url' => $fileInfo['newFilename'],
            'status' => 1,//下载中
            'create_time' => date('Y-m-d H:i:s'),
            'update_time' => date('Y-m-d H:i:s'),
            'creator' => 0,
            'updator' => 0,
        ];

        $insertGetId = Download::getIns()->insertGetId($insert);
        Log::info('cronLog insertDownID cron_id = '.$cronInfo['cron_id'].', down_id = '.$insertGetId.', status = 1 ' );
        return $insertGetId;
    }

    /**
     * 需要检测关联的字段
     * @param array
     * @return void
     * @author lonn.chen
     */
    protected function getJKArray()
    {
        //product_supp 不做关联
        return [
            'product_category', 'product_currency', 'product_brand', 'product_artist', 'product_title', 'product_sku'
        ];
    }

    /**
     * 替换数据
     * @param array
     * @return void
     * @author lonn.chen
     */
    protected function getReplaceValue($arr)
    {
        /*
         $arr = ["hashVal"=>strtolower(trim($lv[$i])),"Model"=>"Artist","key"=>"supp_name_en","val"=>$lv[$i],"id"=>"supp_id","lk"=>$lk,'jk'=>$jk];
         */
        switch ($arr['jk']) {
            case 'product_supp';
                $arr['Model'] = Supp::getIns();
                $arr['key'] = 'supp_name_en';
                $arr['id'] = 'supp_id';
                break;
            case 'product_category';
                $arr['Model'] = Category::getIns();
                $arr['key'] = 'cat_name_en';
                $arr['id'] = 'cat_id';
                break;
            case 'product_currency';
                $arr['Model'] = Currency::getIns();
                $arr['key'] = 'cur_name_en';
                $arr['id'] = 'cur_id';
                break;
            case 'product_brand';
                $arr['Model'] = Brand::getIns();
                $arr['key'] = 'brand_name_en';
                $arr['id'] = 'brand_id';
                break;
            case 'product_artist';
                $arr['Model'] = Artist::getIns();
                $arr['key'] = 'artist_name_en';
                $arr['id'] = 'artist_id';
                break;
            case 'product_title';
                $arr['Model'] = Title::getIns();
                $arr['key'] = 'tit_name_en';
                $arr['id'] = 'tit_id';
                break;
            case 'product_sku';
                $arr['Model'] = Product::getIns();
                $arr['key'] = 'product_sku';
                $arr['id'] = 'product_sku';
                break;

        }
        $result = [];
        if (isset($this->fields[$arr['jk']][$arr['hashVal']])) {
            $result[$arr['hashVal']] = $this->fields[$arr['jk']][$arr['hashVal']];
        } else {

            if ($arr['jk'] == 'product_sku' && isset($this->mcid)) {
                //商品produc 以 product.product_sku+basic_supp.supp_mcid 为唯一记录数据标准
                $data = $arr['Model']
                    ->leftJoin('sl_basic_supp as supp', 'supp.supp_id', '=', 'sl_product.product_supp')
                    ->where('' . $arr['key'] . '', '=', ''.$arr['val'].'')
                    ->where('sl_product.status', 1)
                    ->where('supp.supp_mcid', ''.$this->mcid.'')
                    ->orderBy('' . $arr['id'] . '', 'DESC')
                    ->first(['' . $arr['id'] . '']);
            } else {
                //其他
                $data = $arr['Model']
                    ->where('' . $arr['key'] . '', '=', ''.$arr['val'].'')
                    ->where('status', 1)
                    ->orderBy('' . $arr['id'] . '', 'DESC')
                    ->first(['' . $arr['id'] . '']);
            }

            if (isset($data[$arr['id']])) {
                $result[$arr['hashVal']] = $data[$arr['id']];
            } else {
                if ($arr['jk'] == 'product_sku') {
                    if (isset($result[$arr['hashVal']])) unset($result[$arr['hashVal']]);
                } else {
                    $insert = ['' . $arr['key'] . '' => $arr['val']];
                    if ($arr['jk'] == 'product_supp') {
                        //记录supp_mcid
                        $insert['supp_mcid'] = $this->mcid;
                    }
                    $insert['create_time'] = date('Y-m-d H:i:s');
                    $insert['update_time'] = date('Y-m-d H:i:s');
                    $result[$arr['hashVal']] = $arr['Model']->insertGetId($insert);
                }
            }
            if (isset($result[$arr['hashVal']]) && $result[$arr['hashVal']] != ''){
                $this->fields[$arr['jk']][$arr['hashVal']] = $result[$arr['hashVal']];
            }
        }
        return $result;
    }

    /**
     * 重复sku，更新product
     * @param array
     * @return void
     * @author lonn.chen
     */
    protected function updateProductBySku($sku, $update)
    {
        //Log::info('cronLog updateProductBySku 重复 product_sku = ' . $sku);
        //检查CDN图片
        /* edit 2019-11-21 不需要
        if(isset($update['product_imageurl']) && !empty($update['product_imageurl'])){
            $info = $this->checkImgUrl($update['product_imageurl']);
            $update['product_imageurl'] = $info['product_img'];
            $update['status_img'] = $info['status_img'];
            $update['product_img_id'] = $info['product_img_id'];
        }
        */
        $result = Product::getIns()->where('product_sku', $sku)->where('product_supp',$this->supp_id)->update($update);
        return $result;

    }

    protected function checkImgUrl($imgUrl){
        if(empty($imgUrl)){
            return ;
        }

        $result['status_img'] = 0;
        $result['product_img_id'] = 0;
        $productImg = ProductImg::getIns()->where('product_imageurl',$imgUrl)->first(['product_img_id','product_img']);

        if(!empty($productImg)){
            $productImg = json_decode($productImg,true);
            $result['product_img'] = $this->cdnHost.$productImg['product_img'];
            $result['product_img_id'] = $productImg['product_img_id'];
            $result['status_img'] = 1;
        }else{
            $result['product_img'] = $imgUrl;
        }

        return $result;
    }


    protected function is_json($string)
    {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }

    /**
     * 过滤扩展字段ext内容
     * @param array
     * @return void
     * @author lonn.chen
     */
    protected function getFilterExt($string,$separator=''){

        if(empty($string)){
            return ;
        }
        $result = '';
        if(!empty($separator) && strpos($string, $separator) !== false){
            $str =  explode($separator,$string);
            if(isset($str[1])){
                $result = $str[1];
            }
        }else{
            $result = $string;
        }
        return $result;

    }

    /**
     * 过滤字符串头尾的引号
     * @param array
     * @return void
     * @author lonn.chen
     */
    protected function replaceQuote($str){
        if(empty($str)){
            return ;
        }
        if( substr($str,0,1) == '"' ){
            $str = substr($str,1,-1);
        }
        //过滤字符串中间包含单引号
        //$str = str_replace("'","\'",$str );
        $str = addslashes($str);
        return $str;
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
