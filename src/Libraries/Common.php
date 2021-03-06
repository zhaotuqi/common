<?php
/**
 * Created by PhpStorm.
 * User: jesse
 * Date: 16/5/20
 * Time: 00:17
 */

namespace App\Libraries;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Monolog\Logger;
use Exception;
use RuntimeException;
use Request;

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
        //过滤开发测试环境
//        if(self::checkAllowWarningEnv() == false) {
//            return false;
//        }

//        $message = sprintf("主机名称: %s\n运行方式: %s\n异常时间: %s\n异常原因: %s\n异常状态码: %s\n%s() 入参: %s\n异常所在文件行: [%s:%s]\n",
        $message = sprintf("主机名称: %s\n运行方式: %s\n异常时间: %s\n异常原因: %s\n异常状态码: %s\n异常所在文件行: [%s:%s]\n",
            trim(`hostname`),
            php_sapi_name(),
            date("Y-m-d H:i:s"),
            $e->getMessage(),
            $e->getCode(),
//            array_get($e->getTrace(), '1.function', '未知function'),
//            json_encode(array_get($e->getTrace(), '1.args', '未知param'), JSON_UNESCAPED_UNICODE),
            $e->getFile(),
            $e->getLine());

        $param = [
            'messages' => $message,
            'request_url' => $requestUrl,
            'param' => json_encode($param, JSON_UNESCAPED_UNICODE),
            'trace' => str_replace("\n", "<br/>", $e->getTraceAsString()),
            'title' => $title
        ];

        $client = app('HttpClient');
        try {
            $promise = $client->requestAsync('POST', config('common_config.warning_email_url'), ['json' => $param, 'timeout' => 3, 'connect_timeout' => 3]);
            $promise->wait();
        } catch (Exception $e) {
            Log::info('写日志超时' . $e->getMessage());
        }
    }

    public function getXuebaTokenByUid($uid, $phone)
    {
        $key = config('global.XBJ_USER_TOKEN_KEY');
        $td  = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_ECB, '');
        $iv  = mcrypt_create_iv(mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
        mcrypt_generic_init($td, $key, $iv);
        $data = mcrypt_generic($td, $uid . '_' . $phone);
        mcrypt_generic_deinit($td);
        mcrypt_module_close($td);
        $token    = base64_encode($data);
        $token    = base64_encode($token);
        $token    = str_replace('+', '-', $token);
        $token    = str_replace('/', '_', $token);
        $len      = strlen($token) - 1;
        $numEqual = 0;
        for ($i = $len; $i >= 0; $i--) {
            if (substr($token, $i, 1) == '=') {
                $numEqual = $numEqual + 1;
            } else {
                break;
            }
        }
        if ($numEqual > 0) {
            $token = substr($token, 0, $len - $numEqual + 1);
        }
        $token .= $numEqual;

        return $token;
    }

    public function encryptContent($content, $key = null)
    {
        $key      = $key ?: "235e2a084899bf7f58f432b5037a5bb9";
        $token    = self::encrypt($content, $key);
        $token    = base64_encode($token);
        $token    = str_replace('+', '-', $token);
        $token    = str_replace('/', '_', $token);
        $len      = strlen($token) - 1;
        $numEqual = 0;
        for ($i = $len; $i >= 0; $i--) {
            if (substr($token, $i, 1) == '=') {
                $numEqual = $numEqual + 1;
            } else {
                break;
            }
        }
        if ($numEqual > 0) {
            $token = substr($token, 0, $len - $numEqual + 1);
        }
        $token .= $numEqual;

        return $token;
    }

    public static function encrypt($input, $key)
    {
        $size = mcrypt_get_block_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_ECB);
        //$input = self::pkcs5_pad($input, $size);
        $td = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_ECB, '');
        $iv = mcrypt_create_iv(mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
        mcrypt_generic_init($td, $key, $iv);
        $data = mcrypt_generic($td, $input);
        mcrypt_generic_deinit($td);
        mcrypt_module_close($td);
        $data = base64_encode($data);

        return $data;
    }

    public function decryptToken($token, $key = null)
    {
        $globConfig = config('global');
        $key        = $globConfig['XBJ_USER_TOKEN_KEY'];
        if (!empty($token)) {
            $token    = str_replace('-', '+', $token);
            $token    = str_replace('_', '/', $token);
            $len      = strlen($token);
            $numEqual = substr($token, $len - 1, 1);
            if ($numEqual === '0') {
                $token = substr($token, 0, $len - 1);
            } else {
                $token = substr($token, 0, $len - 1);
                $token .= str_pad('', 0 + $numEqual, '=');
            }
            $token = base64_decode($token);
            $ret   = $this->decrypt($token, $key);

            return $ret;
        }

        return false;
    }

    public function decrypt($sStr, $sKey)
    {
        $decrypted = mcrypt_decrypt(
            MCRYPT_RIJNDAEL_128,
            $sKey,
            base64_decode($sStr),
            MCRYPT_MODE_ECB
        );

        return $decrypted;
    }

    /**
     * 发送异常邮件
     * @param $title
     * @param $param
     * @param $message
     */
    public function sendWarningMail($title, $param, $message, $requestUrl = null)
    {
        //过滤开发测试环境
//        if(self::checkAllowWarningEnv() == false) {
//            return false;
//        }
        $message = sprintf("主机名称: %s\n运行方式: %s\n异常时间: %s\n异常原因: %s\n",
            trim(`hostname`),
            php_sapi_name(),
            date("Y-m-d H:i:s"),
            $message);

        $param = [
            'messages'    => $message,
            'request_url' => $requestUrl,
            'param'       => json_encode($param, JSON_UNESCAPED_UNICODE),
            'trace'       => json_encode($param, JSON_UNESCAPED_UNICODE),
            'title'       => $title
        ];

        $client = app('HttpClient');

        try {
            $promise = $client->requestAsync('POST', config('common_config.warning_email_url'), ['json' => $param, 'timeout' => 3, 'connect_timeout' => 3]);
            $promise->wait();
        } catch (Exception $e) {
            Log::info('写日志超时' . $e->getMessage());
        }
    }


    public function logger($name, $path, $message, $level)
    {
        $isNoLog = false;
        if (is_array($message)) {
            if (isset($message['request_uri'])) {
                $isNoLog = $this->noLog($message['request_uri']);
                if (!$isNoLog && $this->blackList($message['request_uri'])) {
                    $message['request_body']  = 'This request has been filtered ...';
                    $message['response_body'] = 'This response has been filtered ...';
                }
            }
            $message = json_encode($message, JSON_UNESCAPED_UNICODE);
        }

        if (!$isNoLog) {
            $log    = new Logger($name);
            $handle = new \App\Extension\LogRewrite('/data/logs/' . config('app.app_name') . '/' . $path, config('app.log_max_files'));
            $log->pushHandler($handle);
            $log->log($level, $message);
        }
    }

    /**
     * 接口请求打点
     * @author hongfei.geng<hongfei.geng@wenba100.com>
     * @Date: 2018-10-26
     * @param $url
     * @param $costMs
     * @param int $httpCode
     */
    private static function requestToFalcon($url,$costMs,$httpCode = 200){
        $url = trim($url);
        if(!env("REQUEST_TO_FALCON") || !class_exists('\Monitor\Client') || strlen($url) == 0 || !$costMs){
            return ;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if(!$host){
            return ;
        }

        try{
            //打点次数
            \Monitor\Client::inc(sprintf("%s,h=%s,t=api_count",$url,$host));
            if(200 != $httpCode){
                //打点服务器异常的次数
                \Monitor\Client::inc(sprintf("%s,h=%s,t=api_error,c=%d",$url,$host,$httpCode));
            }
            //打点请求时长
            \Monitor\Client::cost(sprintf("%s,h=%s,t=api_cost",$url,$host),$costMs);

        }catch (Exception $e){
            Log::info('记录Falcon失败',$e->getMessage());
        }

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
        return preg_match('/^1[3|4|5|6|7|8|9]\d{9}$/', $phoneNo) ? true : false;
    }

    /**
     * 填充默认的header信息
     *
     **/
    private function defaultHeader($header)
    {
        if (app()->runningInConsole()) {
            return array_merge($header, ['logid' => uniqid() . rand(1, 1000), 'trace' => 0]);
        } else {
            parse_str($_SERVER['QUERY_STRING'], $arrQuery);
            $header['logid'] = isset($_SERVER['HTTP_LOGID']) ? $_SERVER['HTTP_LOGID'] : (isset($arrQuery['logid']) ? $arrQuery['logid'] : uniqid() . rand(1, 1000));
            $header['trace'] = isset($_SERVER['HTTP_TRACE']) ? intval($_SERVER['HTTP_TRACE']) + 1 : (isset($arrQuery['trace']) ? intval($arrQuery['trace']) + 1 : 0);

            return $header;
        }
    }

    /**
     * request GET Query String
     * @param $requestUrl
     * @param $param
     * @return mixed
     */
    public function query($requestUrl, $param, $headers = [])
    {
        $headers    = $this->defaultHeader($headers);
        $httpClient = app('HttpClient');
        $startTime  = microtime(true);
	    $requestUrl = $this->addXdebugParams($requestUrl);
	    try {
            $i = 0;
            query:
            $req = $httpClient->request('GET', $requestUrl, ['query' => $param, 'headers' => $headers, 'timeout' => 30, 'connect_timeout' => 30]);

            //打点falcon中的次数，请求时长，错误
            self::requestToFalcon($requestUrl,(microtime(true) - $startTime)*1000,$req->getStatusCode());

            $result = $req->getBody()->getContents();
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
            'response_time'  => microtime(true) - $startTime,
            'request_uri'    => $requestUrl,
            'request_header' => $headers,
            'request_body'   => $this->logReduce($param),
            'response_body'  => $this->logReduce($result)
        ];


        //记录log
        $this->logger(
            config('app.app_name'),
            'servicelog.log',
            $message,
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
        $headers    = $this->defaultHeader($headers);
        $httpClient = app('HttpClient');
        $startTime  = microtime(true);
	    $requestUrl = $this->addXdebugParams($requestUrl);
	    try {
            $i = 0;
            request:
            $req = $httpClient->request('POST', $requestUrl, ['form_params' => $param, 'headers' => $headers, 'timeout' => 30, 'connect_timeout' => 30]);

            //打点falcon中的次数，请求时长，错误
            self::requestToFalcon($requestUrl,(microtime(true) - $startTime)*1000,$req->getStatusCode());

            $result = $req->getBody()->getContents();
        } catch (RuntimeException $e) {
            if ($i < 5) {
                $i++;
                goto request;
            } else {
                throw $e;
            }
        }

        $message = [
            'response_time'  => microtime(true) - $startTime,
            'request_uri'    => $requestUrl,
            'request_header' => $headers,
            'request_body'   => $this->logReduce($param),
            'response_body'  => $this->logReduce($result)
        ];

        //记录log
        $this->logger(
            config('app.app_name'),
            'servicelog.log',
            $message,
            Logger::INFO
        );

        return $result;
    }

    public function postRequest($requestUrl, $param)
    {
        $headers    = ['Content-Type' => 'application/x-www-form-urlencoded'];
        $headers    = $this->defaultHeader($headers);
        $httpClient = app('HttpClient');
        $startTime  = microtime(true);
        $requestUrl = $this->addXdebugParams($requestUrl);
        try {
            $i = 0;
            postRequest:
            $req = $httpClient->request('POST', $requestUrl, ['body' => $param, 'verify' => false, 'headers' => $headers]);

            //打点falcon中的次数，请求时长，错误
            self::requestToFalcon($requestUrl,(microtime(true) - $startTime)*1000,$req->getStatusCode());

            $result = $req->getBody()->getContents();
        } catch (RuntimeException $e) {
            if ($i < 5) {
                $i++;
                goto postRequest;
            } else {
                throw $e;
            }
        }

        $message = [
            'response_time'  => microtime(true) - $startTime,
            'request_uri'    => $requestUrl,
            'request_header' => $headers,
            'request_body'   => $this->logReduce($param),
            'response_body'  => $this->logReduce($result)
        ];

        //记录log
        $this->logger(
            config('app.app_name'),
            'servicelog.log',
            $message,
            Logger::INFO
        );

        return $result;
    }

    /**
     * 并发请求
     * @param  [type] $arrRequestUrl [description]
     * @return [type] $arrReponseData               [description]
     */
    public function requestAsync($arrRequestData)
    {
        $objHttpClient = app('HttpClient');
        foreach ($arrRequestData as $url => $data) {
	        $url = $this->addXdebugParams($url);
	        $tmp            = [
                'form_params' => $data,
                'headers'     => $this->defaultHeader([]),
            ];
            $postData[$url] = $objHttpClient->postAsync($url, $tmp);
        }
        $arrResult = \GuzzleHttp\Promise\unwrap($postData);
        foreach ($arrResult as $key => $value) {
            $arrData              = json_decode($value->getBody()->getContents(), true);
            $arrReponseData[$key] = $arrData;
        }

        return $arrReponseData;
    }

    /**
     * request GET Query String
     * @param $requestUrl
     * @param $param
     * @return mixed
     */
    public function queryCatchException($requestUrl, $param, $headers = [])
    {
        $headers    = $this->defaultHeader($headers);
        $httpClient = app('HttpClient');
        $startTime  = microtime(true);
        try {
            $i = 0;
            query:
            $req = $httpClient->request('GET', $requestUrl, ['query' => $param, 'headers' => $headers, 'timeout' => 30, 'connect_timeout' => 30]);

            //打点falcon中的次数，请求时长，错误
            self::requestToFalcon($requestUrl,(microtime(true) - $startTime)*1000,$req->getStatusCode());

            $result = $req->getBody()->getContents();
        } catch (RuntimeException $e) {
            if ($i < 2) {
                $i++;
                //file_put_contents('/tmp/query.log', $i . '__' . $requestUrl . PHP_EOL, FILE_APPEND);
                goto query;
            } else {
                return false;
            }
        }

        $message = [
            'response_time'  => microtime(true) - $startTime,
            'request_uri'    => $requestUrl,
            'request_header' => $headers,
            'request_body'   => $this->logReduce($param),
            'response_body'  => $this->logReduce($result)
        ];

        //记录log
        $this->logger(
            config('app.app_name'),
            'servicelog.log',
            $message,
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
        $headers    = $this->defaultHeader($headers);
        $httpClient = app('HttpClient');
        $startTime  = microtime(true);
	    $requestUrl = $this->addXdebugParams($requestUrl);
	    try {
            $i = 0;
            request:
            $req = $httpClient->request('POST', $requestUrl,
                [
                    'multipart'       => $multipart,
                    'headers'         => $headers,
                    'timeout'         => 30,
                    'connect_timeout' => 10
                ]);

            //打点falcon中的次数，请求时长，错误
            self::requestToFalcon($requestUrl,(microtime(true) - $startTime)*1000,$req->getStatusCode());

            $result = $req->getBody()->getContents();
        } catch (RuntimeException $e) {
            if ($i < 5) {
                $i++;
                goto request;
            } else {
                throw $e;
            }
        }

		$arrRequestBody = [];
		foreach ($multipart as $single) {
			if('userfile' != trim($single['name'])) {
				$arrRequestBody[] = $single;
			}
		}
        $message = [
            'response_time'  => microtime(true) - $startTime,
            'request_uri'    => $requestUrl,
            'request_header' => $headers,
            'request_body'   => $arrRequestBody,
            'response_body'  => json_decode($result, true) ?: $result
        ];

        //记录log
        $this->logger(
            config('app.app_name'),
            'servicelog.log',
            $message,
            Logger::INFO
        );

        return $result;
    }

    /**
     * 发送json raw data 请求集群
     * @param $arrRequestUrl
     * @param $param
     * @return mixed
     */
    public function requestJsonCluster($arrRequestUrl, $param, $headers = [])
    {
		$headers = $this->defaultHeader($headers);
        $httpClient = app('HttpClient');
        $startTime  = microtime(true);
        try {
            $i = 0;
            requestJson:
			if ($i >= 2) {//请求两次master，第三次开始请求slave
				$requestUrl = $arrRequestUrl['slave'];
			}
			else {
				$requestUrl = $arrRequestUrl['master'];
			}
            $req = $httpClient->request('POST', $requestUrl, ['json' => $param, 'headers' => $headers, 'timeout' => 3, 'connect_timeout' => 3]);

            //打点falcon中的次数，请求时长，错误
            self::requestToFalcon($requestUrl,(microtime(true) - $startTime)*1000,$req->getStatusCode());

            $result = $req->getBody()->getContents();
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
    /**
     * 发送json raw data 请求
     * @param $requestUrl
     * @param $param
     * @return mixed
     */
    public function requestJson($requestUrl, $param, $headers = [])
    {
        $headers    = $this->defaultHeader($headers);
        $httpClient = app('HttpClient');
        $startTime  = microtime(true);
	    $requestUrl = $this->addXdebugParams($requestUrl);
	    try {
            $i = 0;
            requestJson:
            $req = $httpClient->request('POST', $requestUrl, ['json' => $param, 'headers' => $headers, 'timeout' => 30, 'connect_timeout' => 30]);

            //打点falcon中的次数，请求时长，错误
            self::requestToFalcon($requestUrl,(microtime(true) - $startTime)*1000,$req->getStatusCode());

            $result = $req->getBody()->getContents();
        } catch (RuntimeException $e) {
            if ($i < 5) {
                $i++;
                goto requestJson;
            } else {
                throw $e;
            }
        }

        $message = [
            'response_time'  => microtime(true) - $startTime,
            'request_uri'    => $requestUrl,
            'request_header' => $headers,
            'request_body'   => $this->logReduce($param),
            'response_body'  => $this->logReduce($result)
        ];

        //记录log
        $this->logger(
            config('app.app_name'),
            'servicelog.log',
            $message,
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
        $list  = [
            'list' => $data
        ];

        return array_merge($array, $param, $list);
    }

    /**
     * 发送body raw data 请求
     * @param $requestUrl
     * @param $param
     * @return mixed
     */
    public function requestBody($requestUrl, $param, $headers = [])
    {
        $headers    = $this->defaultHeader($headers);
        $httpClient = app('HttpClient');
        $startTime  = microtime(true);
        $requestUrl = $this->addXdebugParams($requestUrl);
        try {
            $i = 0;
            requestJson:
            $req = $httpClient->request('POST', $requestUrl, ['body' => $param, 'headers' => $headers, 'timeout' => 30, 'connect_timeout' => 30]);
            //打点falcon中的次数，请求时长，错误
            self::requestToFalcon($requestUrl,(microtime(true) - $startTime)*1000,$req->getStatusCode());
            $result = $req->getBody()->getContents();
        } catch (RuntimeException $e) {
            if ($i < 5) {
                $i++;
                goto requestJson;
            } else {
                throw $e;
            }
        }

        $message = [
            'response_time'  => microtime(true) - $startTime,
            'request_uri'    => $requestUrl,
            'request_header' => $headers,
            'request_body'   => $this->logReduce($param),
            'response_body'  => $this->logReduce($result)
        ];

        //记录log
        $this->logger(
            config('app.app_name'),
            'servicelog.log',
            $message,
            Logger::INFO
        );

        return $result;
    }

    public function getOrderId($prefix = null)
    {
        return $prefix . date('YmdHis') . substr(base_convert(uniqid(), 16, 10), -4) . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    public function daysBetweenDates($date1, $date2)
    {
        $date1 = strtotime($date1);
        $date2 = strtotime($date2);
        $days  = ceil(abs($date1 - $date2) / 86400);

        return $days;
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
        \Maatwebsite\Excel\Facades\Excel::create($strFileNamePre . time(), function ($excel) use ($arrListData, $arrCellName, $strFileNamePre) {
            $intSheetNum = ceil(count($arrListData) / self::EACH_PAGE_NUM);
            for ($index = 0; $index < $intSheetNum; $index++) {
                $arrCellDataTmp = $arrCellName;
                $arrTmpInfo     = array_slice($arrListData, $index * self::EACH_PAGE_NUM, self::EACH_PAGE_NUM);
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

    /**
     * 存储excel
     * @param  {[type]} $arrCellName    [description]
     * @param  {[type]} $arrListData    [description]
     * @param  {[type]} $strFileNamePre [description]
     * @return {[type]}                 [description]
     */
    public function storeAsExcel($arrCellName, $arrListData, $strFileNamePre)
    {
        \Maatwebsite\Excel\Facades\Excel::create($strFileNamePre, function ($excel) use ($arrListData, $arrCellName, $strFileNamePre) {
            $intSheetNum = ceil(count($arrListData) / self::EACH_PAGE_NUM);
            for ($index = 0; $index < $intSheetNum; $index++) {
                $arrCellDataTmp = $arrCellName;
                $arrTmpInfo     = array_slice($arrListData, $index * self::EACH_PAGE_NUM, self::EACH_PAGE_NUM);
                foreach ($arrTmpInfo as $singleTmpInfo) {
                    $arrCellDataTmp[] = $singleTmpInfo;
                }
                $strSheetName = sprintf("%s_%d", $strFileNamePre, $index);
                $excel->sheet($strSheetName, function ($sheet) use ($arrCellDataTmp) {
                    $sheet->rows($arrCellDataTmp);
                });
            }
        })->store('xls');
    }

    //获取随机数
    public function strRand($length = 10, $char = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ')
    {
        if (!is_int($length) || $length < 0) return false;
        $rand_str = '';
        $char_len = strlen($char);
        for ($i = $length; $i > 0; $i--) {
            $rand_str .= $char[mt_rand(0, $char_len - 1)];
        }

        return $rand_str;
    }

    /**
     * @param string $time
     * @return int
     */
    public function getSecByTimes($time = '12:00')
    {
        $sec = strtotime($time) - strtotime('00:00');

        if ($sec > 0 && $sec < 86400) {
            return $sec;
        }

        return 0;
    }

    public function getVersionNum($version)
    {

        $version_array = explode('.', $version);

        $version_num = $version_array[0] * 1000 + $version_array[1] * 100 + $version_array[2] * 10 + $version_array[3];

        return $version_num;
    }

    public function getClientIp()
    {
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        if (array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER)) {
            $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }

        return $ipAddress;
    }

    /**
     * 日志精简 支持数组or字符串
     * @param $logBody
     */
    public function logReduce($logBody)
    {
        if (!is_array($logBody)) {
            $logBody = json_decode($logBody, true);
            if (!is_array($logBody)) {
                return $logBody;
            }
        }

        return collect($logBody)->transform(function ($item, $key) {
            if ($key == 'cpList') {
                $item = 'Has been filtered ...';
            }

            if (is_array($item) && key_exists('cpList', $item)) {
                $item['cpList'] = 'Has been filtered ...';
            }

            return $item;
        })->toArray();
    }

    /**
     * 判断是否在路由黑名单
     * @param $pathInfo
     * @return bool
     */
    public function blackList($pathInfo)
    {
        $pathData     = parse_url($pathInfo);
        $blacklistUrl = [
            '/homework/homeworkData',
            '/android/student/getCourseFile',
            '/ios/student/getCourseFile',
            '/studentPc/getCourseFile',
            '/manageCourse/getOverCourseList',
            '/courseware/getQ',
            '/courseware/getTree',
        ];

        // 此处增加可动态配置的路由黑名单
        config('common_config.no_log_body_routes') && $blacklistUrl = array_merge($blacklistUrl, config('common_config.no_log_body_routes'));

        return in_array($pathData['path'], $blacklistUrl);
    }

    /**
     * 判断是否记录日志
     * @param $pathInfo
     * @return bool
     */
    private function noLog($pathInfo)
    {
        return config('common_config.no_log_routes') && in_array(parse_url($pathInfo)['path'], config('common_config.no_log_routes'));
    }

	/**
	 * 添加 xdebug 调试参数
	 *
	 * @param $url
	 *
	 * @return string
	 */
	private function addXdebugParams($url)
	{
		if (extension_loaded('xdebug')) {
			$parsed = parse_url($url);
			$xdebugParams = 'XDEBUG_SESSION_START=' . rand(10000, 20000);
			if (empty($parsed['query'])) {
				$url .= '?' . $xdebugParams;
			} else {
				$url .= '&' . $xdebugParams;
			}
		}

		return $url;
	}

    /**
     * 记录业务日志
     * @param $code 业务码
     * @param $message 消息体
     */
    public function busLogger($code, $message)
    {
        if(!isset($_SERVER['HTTP_LOGID'])){
            $_SERVER['HTTP_LOGID'] = uniqid() . rand(1, 1000);
        }
        $message_body = [
            'msg_code'    => $code,
            'http_logid' => $_SERVER['HTTP_LOGID'],
            'message'   => $message,
        ];
        Common::logger(config('app.app_name'),
            'requestlog.log',
            json_encode($message_body, JSON_UNESCAPED_UNICODE),
            Logger::INFO
        );
    }

    //检查env环境是否允许报警
    private static function checkAllowWarningEnv()
    {
        $allowWarningEnv = ['pro', 'production', 'preview'];

        if(in_array(strtolower(env('APP_ENV')), $allowWarningEnv)) {

            return true;
        }
        return false;
    }
    /* *
     * 校验身份证号码
     * @param $IDNumber
     *
     * @reutrn bool
     */
    public static function verifyIdentifyNO($IDNumber)
    {
        $len = strlen($IDNumber);
        if ($len != 15 && $len != 18) {
            return false;
        }
        if ($len == 15) {
            //一代身份证是20世纪19XX年
            $birthdateStr = "19" . substr($IDNumber, 6, 6);
            $mon = intval(substr($birthdateStr, 4, 2));
            $day = intval(substr($birthdateStr, 6, 2));
            if ($mon < 1 || $mon > 12) {
                return false;
            }
            if ($day < 1 || $day > 31) {
                return false;
            }
            return is_numeric($IDNumber);
        }
        if ($len == 18) {
            //二代身份证校验
            $birthdateStr = substr($IDNumber, 6, 8);
            $mon = intval(substr($birthdateStr, 4, 2));
            $day = intval(substr($birthdateStr, 6, 2));
            if ($mon < 1 || $mon > 12) {
                return false;
            }
            if ($day < 1 || $day > 31) {
                return false;
            }
            $weight = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];
            $verify = ['1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2'];
            $IDNumber = strtoupper($IDNumber);//同时会转成字符串
            $verifyNumber = 0;
            for ($index = 0; $index < 17; $index++) {
                $verifyNumber += $IDNumber[$index] * $weight[$index];
            }
            $verifyNumber = $verifyNumber % 11;//获得最终校验码
            return $verify[$verifyNumber] == $IDNumber[17];
        }
    }

    /**
     * 过滤特殊字符，只保留中文、英文、数字、下划线
     * @param $chars
     * @param string $encoding
     * @return string
     * @author shiyao.niu@wenba100.com
     * @date   2018-11-27
     */
    public static function filterSpecialChars($chars,$encoding='utf8')
    {
        if(empty($chars)) return '';

        $pattern =($encoding=='utf8')?'/[\x{4e00}-\x{9fa5}a-zA-Z0-9_]/u':'/[\x80-\xFF]/';
        preg_match_all($pattern,$chars,$result);
        $temp =join('',$result[0]);
        return $temp;
    }

    /**
     * 获取毫秒数的时间戳
     *
     * @return string
     */
    public static function getMicrotime()
    {
        list($msec, $sec) = explode(' ',microtime());

        return (string)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
    }

    /**
     * 获取服务环境信息
     * @return array
     * @author shiyao.niu@wenba100.com
     */
    public function getServiceInfo()
    {
        $data = [];
        $data['name'] = env("APP_NAME") ?? '';
        $data['ip'] = \request()->server('SERVER_ADDR') ?? '';
        $data['port'] = \request()->getPort() ?? '';
        $data['env'] = env("APP_ENV") ?? '';
        return $data;
    }

    /**
     * @param $requestUrl
     * @param string $method POST,PUT,DELETE
     * @param $param
     * @return mixed
     * @todo
     */
    public function requestMethodByJson($requestUrl, $param, $method = 'POST', $headers = [])
    {
        $headers = $this->defaultHeader($headers);
        $httpClient = app('HttpClient');
        $startTime = microtime(true);
        $requestUrl = $this->addXdebugParams($requestUrl);
        try {
            $i = 0;
            requestMethodByJson:
            $req = $httpClient->request($method, $requestUrl, ['json' => $param, 'verify' => false, 'headers' => $headers, 'timeout' => 30, 'connect_timeout' => 30]);

            //打点falcon中的次数，请求时长，错误
            self::requestToFalcon($requestUrl, (microtime(true) - $startTime) * 1000, $req->getStatusCode());

            $result = $req->getBody()->getContents();
        } catch (RuntimeException $e) {
            if ($i < 5) {
                $i++;
                goto requestMethodByJson;
            } else {
                throw $e;
            }
        }

        $message = [
            'response_time' => microtime(true) - $startTime,
            'request_uri' => $requestUrl,
            'request_type' => $method,
            'request_header' => $headers,
            'request_body' => $this->logReduce($param),
            'response_body' => $this->logReduce($result)
        ];

        //记录log
        $this->logger(
            config('app.app_name'),
            'servicelog.log',
            $message,
            Logger::INFO
        );

        return $result;
    }

    /**
     * @param $requestUrl
     * @param $param
     * @param array $headers
     * @return mixed
     * 去掉补偿机制，只请求一次
     */
    public function noRepeatRequest($requestUrl, $param, $headers = [])
    {
        $headers    = $this->defaultHeader($headers);
        $httpClient = app('HttpClient');
        $startTime  = microtime(true);
        $requestUrl = $this->addXdebugParams($requestUrl);
        $errormsg   = '';
        try {
            $req = $httpClient->request('POST', $requestUrl, ['form_params' => $param, 'headers' => $headers, 'timeout' => 30, 'connect_timeout' => 30]);

            //打点falcon中的次数，请求时长，错误
            self::requestToFalcon($requestUrl, (microtime(true) - $startTime) * 1000, $req->getStatusCode());

            $result = $req->getBody()->getContents();
        } catch (RuntimeException $e) {
            $errormsg = $e->getMessage();
        }

        $message = [
            'response_time'  => microtime(true) - $startTime,
            'request_uri'    => $requestUrl,
            'request_header' => $headers,
            'request_body'   => $this->logReduce($param),
            'response_body'  => $this->logReduce(!empty($errormsg) ? $errormsg : $result)
        ];

        //记录log
        $this->logger(
            config('app.app_name'),
            'servicelog.log',
            $message,
            Logger::INFO
        );

        return $result;
    }

    /**
     * request GET Query String
     * @param $requestUrl
     * @param $param
     * @return mixed
     */
    public function getConfigQuery($requestUrl, $param, $headers = [])
    {
        $headers    = $this->defaultHeader($headers);
        $httpClient = app('HttpClient');
        $startTime  = microtime(true);
        $requestUrl = $this->addXdebugParams($requestUrl);
        try {
            $i = 0;
            query:
            $req = $httpClient->request('GET', $requestUrl, ['query' => $param, 'headers' => $headers, 'timeout' => 3, 'connect_timeout' => 3]);

            //打点falcon中的次数，请求时长，错误
            self::requestToFalcon($requestUrl,(microtime(true) - $startTime)*1000,$req->getStatusCode());

            $result = $req->getBody()->getContents();
        } catch (RuntimeException $e) {
            if ($i < 1) {
                $i++;
                goto query;
            } else {
                throw $e;
            }
        }

        $message = [
            'response_time'  => microtime(true) - $startTime,
            'request_uri'    => $requestUrl,
            'request_header' => $headers,
            'request_body'   => $this->logReduce($param),
            'response_body'  => $this->logReduce($result)
        ];


        //记录log
        $this->logger(
            config('app.app_name'),
            'servicelog.log',
            $message,
            Logger::INFO
        );

        return $result;
    }
}
