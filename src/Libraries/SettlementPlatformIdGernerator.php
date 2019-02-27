<?php
/**
 * Created by PhpStorm.
 * User: yaokun.xie
 * Date: 2019-02-27
 * Time: 16:13
 */

namespace App\Libraries;

define("RTC_BASE_UUID_SERVICE_CONFIG",
    [
        'qa' => [
            'uri'      => env('RTC_BASE_UUID_SERVICE_URI', 'http://10.2.1.107:8079/uuid/settlement'),
            'app_id'   => env('RTC_BASE_UUID_APP_ID', 'test2'),
            'app_key'  => env('RTC_BASE_UUID_APP_KEY', 'keytest2')
        ],
        'pre' => [
            'uri'      => env('RTC_BASE_UUID_SERVICE_URI', 'http://10.2.1.107:8079/uuid/settlement'),
            'app_id'   => env('RTC_BASE_UUID_APP_ID', 'test2'),
            'app_key'  => env('RTC_BASE_UUID_APP_KEY', 'keytest2')
        ],
        'pro' => [
            'uri'      => env('RTC_BASE_UUID_SERVICE_URI', ''),
            'app_id'   => env('RTC_BASE_UUID_APP_ID', ''),
            'app_key'  => env('RTC_BASE_UUID_APP_KEY', '')
        ]
    ]
);

use Log;

class SettlementPlatformIdGernerator
{


    /**
     * @param int $step
     * @return string
     * @throws \Exception
     *
     *  ID序列生成器 默认使用RTC基础服务 UUID生成器
     *
     *  默认使用教学研发中心ID生成器（mongodb object_id）
     *  RTC 基础服务uuid生成器
     *  联系： 教学研发中心-媒体数字中心-基础服务组  吴非
     *  http://10.2.1.12:8090/pages/viewpage.action?pageId=5906913
     *
     */
    public function IdGenerator($step = 3)
    {
        $env = env("APP_ENV");
        if($env=="production" || $env=="pro"){
            $env="pro";
        }elseif($env=="pre"){
            $env="pre";
        }else{
            $env="qa";
        }
        $config = RTC_BASE_UUID_SERVICE_CONFIG[$env];

        $id = null;
        $date = date("YmdHis", time());
        try {
            $param = [
                "app_id"  => $config["app_id"],
                'app_key' => $config["app_key"]
            ];
            $response = app('Common')->requestJson($config['uri'], $param);
            $result = json_decode($response, true);
            if ($result["statusCode"] == 0) {
                $id = $result["data"]['seq'];
                return $date.$id;
            } else {
                throw new \Exception($response);
            }
        } catch (\Exception $e) {
            throw new \Exception('ID 创建异常'.$e->getMessage());
            Log::info('ID 创建异常' . $e->getMessage());
        }
    }
}