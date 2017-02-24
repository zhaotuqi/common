<?php
/**
 * Created by PhpStorm.
 * User: jesse
 * Date: 16/5/20
 * Time: 00:17
 */

namespace App\Libraries;

use Illuminate\Support\Facades\Mail;
use Monolog\Logger;
use Exception;
use RuntimeException;

class Common
{
    const EACH_PAGE_NUM = 10000;

    public function convertString(array $param)
    {
        return array_map(function ($v) {
            if (!is_array($v)) {
                return (string)$v;
            } else {
                return $v;
            }
        }, $param);
    }

    public function convertUtf8($data)
    {
        $data = array_map(function ($v) {
            return iconv('gbk', 'utf-8', $v);
        }, $data);

        return $data;
    }

    public function convertGBK($data)
    {
        $data = array_map(function ($v) {
            return iconv('utf-8', 'GBK', $v);
        }, $data);

        return $data;
    }

    /**
     * 发送异常邮件
     * @param $title
     * @param $param
     * @param $message
     */
    public function sendExceptionMail($title, $param, Exception $e, $requestUrl = null)
    {
        @$this->request(config('common_config.warning_email_url'), [
            'messages'   => $e->getMessage(),
            'requestUrl' => $requestUrl,
            'param'      => json_encode($param, JSON_UNESCAPED_UNICODE),
            'trace'      => str_replace("\n", "<br/>", $e->getTraceAsString()),
            'title'      => $title
        ]);
    }

    /**
     * 发送异常邮件
     * @param $title
     * @param $param
     * @param $message
     */
    public function sendWarningMail($title, $param, $message, $requestUrl = null)
    {
        @$this->request(config('common_config.warning_email_url'), [
            'messages'   => $message,
            'requestUrl' => $requestUrl,
            'param'      => json_encode($param, JSON_UNESCAPED_UNICODE),
            'trace'      => json_encode($param, JSON_UNESCAPED_UNICODE),
            'title'      => $title
        ]);
    }


    public function logger($name, $path, $message, $level)
    {
        $log = new Logger($name);
        $handle = new \App\Extension\LogRewrite('/data/logs/' . config('app.app_name') . '/' . $path, config('app.log_max_files'));
        $log->pushHandler($handle);
        $log->log($level, $message);
    }

    /**
     * 判断日期格式
     * @param $date
     * @return bool
     */
    public function is_date($date)
    {
        if ($date == date('Y-m-d H:i:s', strtotime($date))) {
            return true;
        } else {
            return false;
        }
    }

    public function isPhoneNo($phoneNo)
    {
        return preg_match('/^1[3|4|5|7|8]\d{9}$/', $phoneNo) ? true : false;
    }

    /**
     * request GET Query String
     * @param $requestUrl
     * @param $param
     * @return mixed
     */
    public function query($requestUrl, $param, $headers = [])
    {
        $httpClient = app('HttpClient');

        try {
            $i = 0;
            query:
            $result = $httpClient->request('GET', $requestUrl, ['query' => $param, 'headers' => $headers, 'timeout' => 3, 'connect_timeout' => 3])->getBody()->getContents();
        } catch (RuntimeException $e) {
            if ($i < 5) {
                $i++;
                //file_put_contents('/tmp/query.log', $i . '__' . $requestUrl . PHP_EOL, FILE_APPEND);
                goto query;
            } else {
                throw $e;
            }
        }

        $message = [
            'request_uri'    => $requestUrl,
            'request_header' => $headers,
            'request_body'   => $param,
            'response_body'  => @json_decode($result, true) ?: $result
        ];

        //记录log
        $this->logger(
            config('app.app_name'),
            'servicelog.log',
            json_encode($message, JSON_UNESCAPED_UNICODE),
            Logger::INFO
        );

        return $result;
    }

    /**
     * request POST FORM DATA
     * @param $requestUrl
     * @param $param
     * @return mixed
     */
    public function request($requestUrl, $param, $headers = [])
    {
        $httpClient = app('HttpClient');
        try {
            $i = 0;
            request:
            $result = $httpClient->request('POST', $requestUrl, ['form_params' => $param, 'headers' => $headers, 'timeout' => 3, 'connect_timeout' => 3])->getBody()->getContents();
        } catch (RuntimeException $e) {
            if ($i < 5) {
                $i++;
                goto request;
            } else {
                throw $e;
            }
        }

        $message = [
            'request_uri'    => $requestUrl,
            'request_header' => $headers,
            'request_body'   => $param,
            'response_body'  => @json_decode($result, true) ?: $result
        ];

        //记录log
        $this->logger(
            config('app.app_name'),
            'servicelog.log',
            json_encode($message, JSON_UNESCAPED_UNICODE),
            Logger::INFO
        );

        return $result;
    }

    /**
     * request POST FORM DATA
     * @param $requestUrl
     * @param $param
     * @return mixed
     */
    public function requestMultipart($requestUrl, $multipart, $headers = [])
    {
        $httpClient = app('HttpClient');

        try {
            $i = 0;
            request:
            $result = $httpClient->request('POST', $requestUrl,
                [
                    'multipart'       => $multipart,
                    'headers'         => $headers,
                    'timeout'         => 3,
                    'connect_timeout' => 3
                ])->getBody()->getContents();

        } catch (RuntimeException $e) {
            if ($i < 5) {
                $i++;
                goto request;
            } else {
                throw $e;
            }
        }

        $message = [
            'request_uri'    => $requestUrl,
            'request_header' => $headers,
            'request_body'   => $multipart,
            'response_body'  => @json_decode($result, true) ?: $result
        ];

        //记录log
        $this->logger(
            config('app.app_name'),
            'servicelog.log',
            json_encode($message, JSON_UNESCAPED_UNICODE),
            Logger::INFO
        );

        return $result;
    }

    /**
     * 发送json raw data 请求
     * @param $requestUrl
     * @param $param
     * @return mixed
     */
    public function requestJson($requestUrl, $param, $headers = [])
    {
        $httpClient = app('HttpClient');
        try {
            $i = 0;
            requestJson:
            $result = $httpClient->request('POST', $requestUrl, ['json' => $param, 'headers' => $headers, 'timeout' => 3, 'connect_timeout' => 3])->getBody()->getContents();
        } catch (RuntimeException $e) {
            if ($i < 5) {
                $i++;
                goto requestJson;
            } else {
                throw $e;
            }
        }

        $message = [
            'request_uri'    => $requestUrl,
            'request_header' => $headers,
            'request_body'   => $param,
            'response_body'  => @json_decode($result, true) ?: $result
        ];

        //记录log
        $this->logger(
            config('app.app_name'),
            'servicelog.log',
            json_encode($message, JSON_UNESCAPED_UNICODE),
            Logger::INFO
        );

        return $result;
    }

    public function pageData($cur_page, $limit, $totalSize, $data, $param = [])
    {
        $array = [
            'pageNum'   => (string)ceil($totalSize / $limit),
            'curPage'   => (string)$cur_page,
            'pageSize'  => (string)$limit,
            'totalSize' => (string)$totalSize,
        ];
        $list = [
            'list' => $data
        ];

        return array_merge($array, $param, $list);
    }

    public function getOrderId($prefix = null)
    {
        return $prefix . date('YmdHis') . substr(base_convert(uniqid(), 16, 10), -4) . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    //显示数据
    public function getClockByTimestamp($time)
    {
        $time = $time - 28800;//减去八小时的秒
        $date = date("H:i", $time);
        if ($time > 0 && $date == '00:00') {
            return '24:00';
        }

        return $date;
    }

    public function getWeekName($index)
    {
        $week = ['周日', '周一', '周二', '周三', '周四', '周五', '周六'];
        if ($index > count($week) || $index < 0) {
            return '周日';
        }

        return $week[$index];
    }

    /**
     * 输出为excel
     * @param  {[type]} $arrCellName    [description]
     * @param  {[type]} $arrListData    [description]
     * @param  {[type]} $strFileNamePre [description]
     * @return {[type]}                 [description]
     */
    public function ouputAsExcel($arrCellName, $arrListData, $strFileNamePre)
    {
        Excel::create($strFileNamePre . time(), function ($excel) use ($arrListData, $arrCellName, $strFileNamePre) {
            $intSheetNum = ceil(count($arrListData) / self::EACH_PAGE_NUM);
            for ($index = 0; $index < $intSheetNum; $index++) {
                $arrCellDataTmp = $arrCellName;
                $arrTmpInfo = array_slice($arrListData, $index * self::EACH_PAGE_NUM, self::EACH_PAGE_NUM);
                foreach ($arrTmpInfo as $singleTmpInfo) {
                    $arrCellDataTmp[] = $singleTmpInfo;
                }
                $strSheetName = sprintf("%s_%d", $strFileNamePre, $index);
                $excel->sheet($strSheetName, function ($sheet) use ($arrCellDataTmp) {
                    $sheet->rows($arrCellDataTmp);
                });
            }
        })->export('xls');
    }
}