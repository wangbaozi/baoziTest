<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;


use Auth;
use Excel;
use App\Models\Basic\BrandRela;
use App\Models\Basic\Supp;
use App\Models\Link\Cron;
class ExcelController extends Controller
{
    private $limit = 3000;

    private $data = [];

    public function __construct()
    {
        $this->middleware('auth');
    }

    //批量导入商家csv数据
    public function importSupp(Request $request)
    {
        //setlocale(LC_ALL, 'zh_CN');  //设置地区信息（地域信息）
        //setlocale(LC_ALL,array('zh_CN.gbk','zh_CN.gb2312','zh_CN.gb18030'));

        header ( 'Content-Type: application/vnd.ms-excel' );
        ini_set('memory_limit', '2048M');
        set_time_limit(0);

        $file_csv  = $request->file('supp_csv_file');

        $extension = $file_csv->getClientOriginalExtension();

        $filePath = $file_csv->storeAs('exports/supp/'.date('Ymd'),
            sprintf(
                '%s_%d.%s',
                date('YmdHis'),
                rand(100000, 999999),
                $extension)
        );
        $filePath = storage_path().'/app/'.$filePath;
        Log::info($filePath);
        $this->data['filePath'] = $filePath;

        Excel::load($filePath, function($reader) {
            $data = $reader->all();
            $this->changeSupp($data);
        });
    }

    public function changeSupp($data=[])
    {
        if (empty($data)) {
            unlink($this->data['filePath']);
            msg('0300','无数据');

        }

        $user = Auth::user();

        $data_array = json_decode($data, true);
        if (!is_array($data_array)) {
            unlink($this->data['filePath']);
            msg('0310','数据格式错误');
        }

        //检测xlsx文件标题
        if (count($data_array) > 3000) {
            unlink($this->data['filePath']);
            msg('0320','最多导入3000条数据');
        }

        //判断数据格式
        $data_title = array_keys($data_array[0]);
        foreach ($data_title as $k => $v) {
            $data_title[$k] = (string)$v;
        }

        if (!in_array("supp_name_cn", $data_title) || !in_array("supp_name_en", $data_title) ||
            !in_array("supp_desc_cn", $data_title) || !in_array("supp_desc_en", $data_title) ||
            !in_array("supp_region", $data_title) || !in_array("supp_logo", $data_title)
        )
        {
            unlink($this->data['filePath']);
            msg('0330','标题依次：supp_name_cn、supp_name_en、supp_desc_cn、supp_desc_en、supp_region、supp_logo');
        }


        $add_data_list = $update_data_list = [];
        $now = date('Y-m-d H:i:s', time());
        foreach ($data_array as $n_k => $n_v) {
            if(empty($n_v['supp_name_en'])){
                continue;
            }

            $add_update_data = [
                'supp_name'    => $n_v['supp_name_en'],
                'supp_name_en' => $n_v['supp_name_en'],
                'supp_name_cn' => empty($n_v['supp_name_cn']) ? '' : $n_v['supp_name_cn'],
                'supp_desc_en' => empty($n_v['supp_desc_en']) ? '' : $n_v['supp_desc_en'],
                'supp_desc_cn' => empty($n_v['supp_desc_cn']) ? '' : $n_v['supp_desc_cn'],
                'supp_region'  => empty($n_v['supp_region'])  ? '' : $n_v['supp_region'],
                'supp_logo'    => empty($n_v['supp_logo'])    ? '' : $n_v['supp_logo'],
                'update_time'  => $now,
                'updator'      => $user['id']
            ];

            $is_exist = Supp::where('supp_name',$n_v['supp_name_en'])->first();

            if (empty($is_exist)) {
                $add_update_data['creator']     = $user['id'];
                $add_update_data['supp_mcid']   = $n_v['supp_name_en'];
                $add_update_data['create_time'] = $now;
                $add_data_list[] = $add_update_data;
            } else {
                $update_data_list[] = $add_update_data;
            }
        }

        $add_res = $update_res = 1;

        if (!empty($add_data_list)) {
            $add_res = Supp::insert($add_data_list);
        }

        if ($add_res ===false ){
            unlink($this->data['filePath']);
            msg('0200','商家数据导入失败');
        }

        if (!empty($update_data_list)) {
            $update_res = Supp::getIns()->updateBatch($update_data_list);
        }

        if ($update_res===false){
            unlink($this->data['filePath']);
            msg('0200','旧商家数据导入更新失败');
        }

        msg('0200','导入成功');

    }


    public function importBrandRela(Request $request)
    {
        header ( 'Content-Type: application/vnd.ms-excel' );
        ini_set('memory_limit', '2048M');
        set_time_limit(0);

        $type = 'cron_pj';

        //$filePath = 'storage/exports/'.iconv('UTF-8', 'GBK', 'brand_rela0914').'.csv';
        $filePath = 'storage/exports/'.iconv('UTF-8', 'GBK', $type).'.csv';

        Excel::load($filePath, function($reader) {
            $data = $reader->all();
            $this->changeCron($data, 'cron_pj');
        });
    }


    public function changeCron($data=[], $type='')
    {
        if (empty($data)) {
            Log::info('----导入文件暂无数据---');
        }

        Log::info($type.'----导入文件数据---');

        $user = Auth::user();

        //csv文件按$limit分块
        $data_res = array_chunk(json_decode($data, true), $this->limit);

        foreach ($data_res as $k => $v) {
            $t1 = microtime(true);

            $add_data_list = $update_data_list = [];
            $now = date('Y-m-d H:i:s', time());
            foreach ($v as $n_k => $n_v) {
                if(empty($n_v['supp_mcid'])){
                    continue;
                }

                $supp_info = Supp::where('supp_mcid',$n_v['supp_mcid'])->first();
                if (empty($supp_info)) {
                    Log::info($n_v['supp_mcid'].'----商家不存在');
                    continue;
                }

                if ( ($n_v['is_valid'] == 0) || ($n_v['is_exist'] == 0) || ($n_v['file_path'] == 'check') ||  empty($n_v['file_path'])){
                    if ( ($n_v['is_exist'] == 0) || ($n_v['file_path'] == 'check') ) {
                        Log::info($n_v['supp_mcid'].'--商家需要校验++++');
                    }
                    continue;
                }


                $now = date('Y-m-d H:i:s', time());
                $add_update_data = [
                    'supp_id'      => $supp_info['supp_id'],
                    'mcid'         => $supp_info['supp_mcid'],
                    'link_host'    => $n_v['link_host'],
                    'file_path'    => $n_v['file_path'],
                    'hour_run'     => 2,
                    'hour_rate'    => 24,
                    'nextrun'      => 0,
                    'lastrun'      => 0,
                    'create_time'  => $now,
                    'update_time'  => $now,
                ];

                $add_data_list[] = $this->typeData($add_update_data, $type);
            }

            $t3 = microtime(true);
            Log::info($k . '----新增数据----' . count($add_data_list));
            if (!empty($add_data_list)) {
                $add_res = Cron::insert($add_data_list);
                if ($add_res !== false) {
                    Log::info($k . '！！！新增成功！！！' . count($add_data_list));
                }
            }
            $t4 = microtime(true);
            Log::info($k . '----333---' . '耗时' . round($t4 - $t3, 3) . '秒');


            unset($add_data_list);

            usleep(5000);
        }

    }

    public function typeData ($add_update_data, $type = 'cron_cj') {
        if (empty($add_update_data) ) {
            return [];
        }

        switch ($type) {
            case 'cron_ls':
                $add_update_data['link_type']  = 'ftp';
                $add_update_data['fd_id']      = 9;
                $add_update_data['account_id'] = 6;
                break;

            case 'cron_aw':
                $add_update_data['link_type']  = 'https';
                $add_update_data['fd_id']      = $this->getTemplateByMcid($add_update_data['mcid'], $type);
                break;

            case 'cron_ir':
                $add_update_data['link_type']  = 'https';
                $add_update_data['fd_id']      = 15;
                break;

            case 'cron_pj':
                $add_update_data['link_type']  = 'http';
                $add_update_data['fd_id']      = 14;
                break;

            default:
                $add_update_data['link_type']  = 'ftp';
                $add_update_data['fd_id']      = 5;
                $add_update_data['account_id'] = 5;
        }

        return $add_update_data;
    }

    private function getTemplateByMcid($mcid, $type){
        $template = [
            'cron_aw' => [
                'topmanuk'   => 21,
                'superdryuk' => 20,
                'zavvi'      => 18,
            ],

        ];

        return empty($template[$type][$mcid]) ? 16 : $template[$type][$mcid];

    }



    //cron原始文件准备（本地使用）
    public function cronCsv($data=[], $type='')
    {
        if (empty($data)) {
            Log::info('----cron原始文件准备--无数据---');
        }

        Log::info($type.'----cron原始文件准备---');

        //csv文件按$limit分块
        $data_res = json_decode($data, true);

        foreach ($data_res as $k => $v) {
            $data_res[$k]['aw'] = empty($v['aw']) ? '' : '{"type":"0","path":"'.$v['aw'].'","ext":"csv.zip"}';
            $data_res[$k]['ir'] = empty($v['ir']) ? '' : '{"type":"0","path":"'.$v['ir'].'","ext":"txt.gz","special":"u="}';
            $data_res[$k]['pj'] = empty($v['pj']) ? '' : '{"type":"0","path":"'.$v['pj'].'","ext":"csv","special":"url="}';
            $data_res[$k]['cj'] = empty($v['cj']) ? '' : '{"type":"0","path":"'.$v['cj'].'","ext":"txt.gz","special":"url="}';
        }


        //$supp_list_data = $this->index();
        $header = [['aw','ir','pj','cj']];

        $cellData = array_merge($header, $data_res);

        Excel::create('cron_csv_down',function($excel) use ($cellData){
            $excel->sheet('score', function($sheet) use ($cellData){
                $sheet->rows($cellData);
            });
        })->export('csv');
    }




}
