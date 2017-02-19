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

    /**
     * 发送异常邮件
     * @param $title
     * @param $param
     * @param $message
     */
    public function sendExceptionMail($title, $param, Exception $e, $requestUrl = null)
    {
        if (!config('mail.mail_sys_log')) return true;
        Mail::queueOn('warningEmail', 'emails.warning',
            [
                'messages'   => $e->getMessage(),
                'requestUrl' => $requestUrl,
                'param'      => json_encode($param, JSON_UNESCAPED_UNICODE),
                'trace'      => str_replace("\n", "<br/>", $e->getTraceAsString())
            ],
            function ($m) use ($title) {
                $m->to(config('mail.send_to_001.address'), config('mail.send_to_001.name'))
                    ->subject($title);
            });
    }

    /**
     * 发送异常邮件
     * @param $title
     * @param $param
     * @param $message
     */
    public function sendWarningMail($title, $param, $message, $requestUrl = null)
    {
        if (!config('mail.mail_sys_log')) return true;
        Mail::queueOn('warningEmail', 'emails.warning',
            [
                'messages'   => $message,
                'requestUrl' => $requestUrl,
                'param'      => json_encode($param, JSON_UNESCAPED_UNICODE),
                'trace'      => ''
            ],
            function ($m) use ($title) {
                $m->to(config('mail.send_to_001.address'), config('mail.send_to_001.name'))
                    ->subject($title);
            });
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
}