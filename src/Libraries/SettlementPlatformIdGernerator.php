<?php
/**
 * Created by PhpStorm.
 * User: yaokun.xie
 * Date: 2019-02-27
 * Time: 16:13
 */

namespace App\Libraries;

/**
 * 生成规则仅用于结算平台
 *
 */

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
     *
     *  PROD ENV  正式环境ID生成器配置
     *  RTC_BASE_UUID_SERVICE_URI='http://10.10.31.38/uuid/settlement'
     *  RTC_BASE_UUID_APP_ID='butian'
     *  RTC_BASE_UUID_APP_KEY='d3c61cacab140b3bd'
     *
     *
     *  TEST  ENV 测试环境ID生成器配置
     *  RTC_BASE_UUID_SERVICE_URI='http://10.2.1.107:8079/uuid/settlement'
     *  RTC_BASE_UUID_APP_ID='test2'
     *  RTC_BASE_UUID_APP_KEY='keytest2'
     *
     *
     */
    public function IdGenerator()
    {
        $id = null;

        $config = [
            'uri' => env('RTC_BASE_UUID_SERVICE_URI', ""),
            'app_id' => env('RTC_BASE_UUID_APP_ID', ""),
            'app_key' => env('RTC_BASE_UUID_APP_KEY', "")
        ];
        //检查ID生成器配置
        $check_config_msg = "";
        $check_config_msg .= empty($config["uri"]) ? ".env文件： RTC_BASE_UUID_SERVICE_URI 未配置" . PHP_EOL : "";
        $check_config_msg .= empty($config["app_id"]) ? ".env文件：  RTC_BASE_UUID_APP_ID 未配置" . PHP_EOL : "";
        $check_config_msg .= empty($config["app_key"]) ? ".env文件： RTC_BASE_UUID_APP_KEY 未配置" . PHP_EOL : "";
        if (!empty($check_config_msg)) {
            throw new \Exception(PHP_EOL . $check_config_msg);
        } else {
            //ID 生成器规则   //时间段+自增序列 201903011500+incr_id
            $date = date("YmdHis", time());
            try {
                $param = [
                    "app_id" => $config["app_id"],
                    'app_key' => $config["app_key"]
                ];
                $response = app('Common')->requestJson($config['uri'], $param);
                $result = json_decode($response, true);
                if ($result["statusCode"] == 0) {
                    //生成正确的ID
                    $id = $date . $result["data"]['seq'];
                    return $id;
                } else {
                    throw new \Exception($response);
                }
            } catch (\Exception $e) {
                Log::info('ID 创建异常' . $e->getMessage());
                throw new \Exception('ID 创建异常' . $e->getMessage());
            }
        }
    }
}