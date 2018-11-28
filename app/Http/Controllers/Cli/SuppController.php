<?php

namespace App\Http\Controllers\Cli;

use App\Services\Util;
use App\Models\Basic\Supp;
use App\Http\Controllers\Controller;

class SuppController extends Controller
{

    /*
     *  根据linkhaitao link_merchant表中商家状态（下架），下架对应的sl_basic_supp商家
     *
     *  30分钟更新一次
     *
     */
    public function updateStatus() {
        $supp_list = Supp::where('supp_mcid','!=', '')->get(['supp_id', 'supp_mcid', 'status'])->toArray();
        if (empty($supp_list)){
            exit();
        }

        $supp_mcids = array_column($supp_list, 'supp_mcid');
        $params = [
            'm_id'  => implode(',', array_unique($supp_mcids))
        ];

        $url      = env('SHOPLOOKS_MERCHANT_STATUS_URL').'?'.http_build_query($params);
        $response = Util::request($url, [],true);

        $response_data = json_decode($response, true);

        if ($response_data['code'] !== '0200' || empty($response_data['payload'])){
            exit();
        }
        $supp_status_list = $response_data['payload'];

        $downline_supps = $online_supps = [];
        foreach ($supp_mcids as $k => $v) {
            #不等于2=>下线处理
            if (!empty($supp_status_list[$v])){
                if($supp_status_list[$v] != 2){
                    $downline_supps[] = $v;
                } else {
                    $online_supps[] = $v;
                }
            }
        }

        //下架商家不为空，下架更新
        if(!empty($downline_supps)){
            $update_res = Supp::whereIN('supp_mcid', $downline_supps)->update([
                'update_time' => date('Y-m-d H:i:s',time()),
                'status'      => 0
            ]);
        }

    }


}
