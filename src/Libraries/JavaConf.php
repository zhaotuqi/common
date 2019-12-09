<?php
/**
 * @author: 耿鸿飞<15911185633>
 * @date: 2019-03-19 18:02
 * @link: http://10.2.1.12:8090/pages/viewpage.action?pageId=10223764  接口文档地址
 */

namespace App\Libraries;


use Illuminate\Support\Facades\Cache;

class JavaConf
{
    /**
     * java配置平台连接地址
     * User: yaokun
     * Date: 2019-05-29 12:04
     * Email: <yaokun.xie@wenba100.com>
     * @return mixed
     * @throws \Exception
     */
    private function getUrl()
    {
        $javaConfigPlatformUrl = env("JAVA_CONFIG_CENTER");
        if (empty($javaConfigPlatformUrl )) {
            throw  new \Exception("library/common扩展需要java配置平台连接地址");
        }
        return $javaConfigPlatformUrl;
    }

    /**
     * 获取java配置平台环境
     * @param $configKey
     * @return string
     */
    private function getEnv($configKey){
        if('pre' == strtolower(env('REDIS_CMS_ENV','pro')))
        {
            $configId = config("javaconf.pre.".$configKey);
            if(!empty($configId)){
                return 'pre';
            }
            return 'pro';
        }elseif ('pro' == strtolower(env('REDIS_CMS_ENV','pro'))){
            return 'pro';
        }

        return 'dev';
    }

    /**
     * 获取配置的结构信息
     * @author: 耿鸿飞<15911185633>
     * @date: 2019-03-19 18:28
     * @link:
     * @param $configId
     * @return array
     */
    private function fetchTableInfo($configId){
        $url = $this->getUrl().'config/group/get';
        $startTime  = microtime(true);
        $fileCacheKey = 'file_cache_get_'.$configId;
        //是否开启文件缓存
        $isOpen = env('OPEN_CONFIG_PLATFORM_FILE_CACHE', false);
        try{
            if ($isOpen && Cache::has($fileCacheKey)) {
                $items = Cache::get($fileCacheKey);
            } else {
                $response = app('Common')->getConfigQuery($url,['config_id' => $configId]);
                $this->falconInc("JavaConf:Error:GetMap:Req,t=JavaConf");
                $jsonData = json_decode($response,true);
                $items = array_get($jsonData,'items',[]);
                $this->falconCos("JavaConf:ReqTime:GetMap,t=JavaConf",$startTime);
                if(empty($items)){
                    $this->falconInc("JavaConf:Error:GetMap:DataEmpty,t=JavaConf");
                    return [];
                }
                Cache::forever($fileCacheKey, $items);
            }
            return array_column($items,'item_name','item_id');
        }catch (\Exception $e){
            $this->falconInc("JavaConf:Error:GetMap:Exception,t=JavaConf");
            $this->falconCos("JavaConf:ReqTime:GetMap,t=JavaConf",$startTime);
            //增加异常捕获后，改从文件缓存读取
            $items = Cache::get($fileCacheKey);
            return $items ? array_column($items,'item_name','item_id') : [];
        }
        return [];
    }

    /**
     * 打点
     * @author: 耿鸿飞<15911185633>
     * @date: 2019-03-19 19:57
     * @link:
     * @param $key
     */
    private function falconInc($key){
        try{
            \Monitor\Client::inc($key);
        }catch (\Exception $e){
        }
    }

    /**
     * 打点
     * @author: 耿鸿飞<15911185633>
     * @date: 2019-03-19 19:56
     * @link:
     * @param $key
     * @param $startTime
     */
    private function falconCos($key,$startTime){
        try{
            \Monitor\Client::cost($key,(microtime(true) - $startTime)*1000);
        }catch (\Exception $e){
        }
    }

    /**
     * 获取配置信息
     * @author: 耿鸿飞<15911185633>
     * @date: 2019-03-19 18:33
     * @link:
     * @param $configId
     * @return array|mixed
     */
    private function fetchConfInfo($configId){
        $url = $this->getUrl().'config/data/page';
        $startTime  = microtime(true);
        $fileCacheKey = 'file_cache_page_'.$configId;
        //是否开启文件缓存
        $isOpen = env('OPEN_CONFIG_PLATFORM_FILE_CACHE', false);
        try{
            if ($isOpen && Cache::has($fileCacheKey)) {
                $items = Cache::get($fileCacheKey);
            } else {
                $response = app('Common')->getConfigQuery($url,[
                    'config_id' => $configId,
                    'page' => 1,
                    'page_size' => 999
                ]);
                $this->falconInc("JavaConf:Error:GetConf:Req,t=JavaConf");
                $this->falconCos("JavaConf:ReqTime:GetConf,t=JavaConf",$startTime);
                $jsonData = json_decode($response,true);
                $items = array_get($jsonData,'list',[]);
                if(empty($items)){
                    $this->falconInc("JavaConf:Error:GetConf:DataEmpty,t=JavaConf");
                    return [];
                }
                Cache::forever($fileCacheKey, $items);
            }
            return $items;
        }catch (\Exception $e){
            $this->falconInc('JavaConf:Error:GetConf:Exception,t=JavaConf');
            $this->falconCos("JavaConf:ReqTime:GetConf,t=JavaConf",$startTime);
            //增加异常捕获后，改从文件缓存读取
            $items = Cache::get($fileCacheKey);
            return $items ?? [];
        }
        return [];
    }

    /**
     * 获取全部的配置信息
     * @author: 耿鸿飞<15911185633>
     * @date: 2019-03-19 18:45
     * @link:
     * @param $configId
     * @return array|bool
     */
    private function fetchConfigAll($configId){
        $map = $this->fetchTableInfo($configId);
        if(empty($map)){
            return false;
        }
        $data = $this->fetchConfInfo($configId);
        if(empty($data)){
            return false;
        }

        $retArr = [];
        foreach ($data AS $items){
            $line = [];
            foreach (array_get($items,'items',[]) AS $item){
                $val = array_get($item,'item_value');
                $tmp = json_decode($val,true);
                $val = empty($tmp) ? $val : $tmp;
                //var_dump($val);
                $line[array_get($map,array_get($item,'item_id'))] = $val;
            }
            $retArr[] = $line;
        }
        return $retArr;
    }

    /**
     * 获取configId下的所有信息
     * @param $configId integer 配置id
     * @return array|bool 列表信息
     */
    private function fetchItemList($configId){
        $map = $this->fetchTableInfo($configId);
        if(empty($map)){
            return false;
        }
        $data = $this->fetchConfInfo($configId);
        if(empty($data)){
            return false;
        }

        $retArr = [];
        foreach ($data AS $items){
            $line = [];
            $list = array_column(array_get($items,'items',[]), null, 'item_id');
            foreach ($map as $k => $v) {
                $line[$v] = isset($list[$k]) ? array_get($list[$k], 'item_value', '') : '';
            }
            $retArr[$items['record_id']] = $line;
        }
        return $retArr;
    }

    /**
     * 获取单条record信息
     * @param $configId
     * @param $recordId
     * @return array
     */
    private function fetchRecordInfo($configId, $recordId){
        $url = $this->getUrl().'config/data/get';
        $startTime  = microtime(true);
        $fileCacheKey = 'file_cache_'.$configId.'_'.$recordId;
        //是否开启文件缓存
        $isOpen = env('OPEN_CONFIG_PLATFORM_FILE_CACHE', false);
        try{
            if ($isOpen && Cache::has($fileCacheKey)) {
                $items = Cache::get($fileCacheKey);
            } else {
                $response = app('Common')->getConfigQuery($url,[
                    'config_id'     => $configId,
                    'record_ids'    => implode(',', $recordId),
                ]);
                $this->falconInc("JavaConf:Error:fetchRecordInfo:Req,t=JavaConf");
                $this->falconCos("JavaConf:ReqTime:fetchRecordInfo,t=JavaConf",$startTime);
                $jsonData = json_decode($response,true);
                $items = array_get($jsonData,'list',[]);
                if(empty($items)){
                    $this->falconInc("JavaConf:Error:fetchRecordInfo:DataEmpty,t=JavaConf");
                    return [];
                }
                Cache::forever($fileCacheKey, $items);
            }
            return array_column($items, null, 'record_id');
        }catch (\Exception $e){
            $this->falconInc('JavaConf:Error:fetchRecordInfo:Exception,t=JavaConf');
            $this->falconCos("JavaConf:ReqTime:fetchRecordInfo,t=JavaConf",$startTime);
            //增加异常捕获后，改从文件缓存读取
            $items = Cache::get($fileCacheKey);
            return $items ? array_column($items, null, 'record_id') : [];
        }
        return [];
    }

    /**
     * 获取单条格式化信息
     * @param $configId
     * @param $recordIds
     * @return array|bool
     */
    private function fetchItemInfo($configId, $recordIds){
        $map = $this->fetchTableInfo($configId);
        if(empty($map)){
            return false;
        }

        $data = $this->fetchRecordInfo($configId, $recordIds);
        if(empty($data)){
            return false;
        }

        $retArr = [];
        foreach ($data AS $items){
            $line = [];
            $list = array_column(array_get($items,'items',[]), null, 'item_id');
            foreach ($map as $k => $v) {
                $line[$v] = isset($list[$k]) ? array_get($list[$k], 'item_value', '') : '';
            }
            $retArr[$items['record_id']] = $line;
        }
        return $retArr;
    }

    /**
     * @param $table
     * @param $data
     * @return array
     * 格式化
     */
    private function _formatTable($table, $data)
    {
        $items = [];
        foreach ($table as $key => $value) {
            $items[] = [
                'item_id'       => $key,
                'item_value'    => !is_null(array_get($data, $value, '')) ? array_get($data, $value, '') : '',
            ];
        }
        return $items;
    }

    /**
     * 获取配置信息
     * app('JavaConf')->fetchConfig(27,'payment_type.10.white_list','');
     * @author: 耿鸿飞<15911185633>
     * @date: 2019-03-19 19:08
     * @link:
     * @param $configId
     * @param $keys
     * @return mixed
     */
    public function fetchConfig($configKey,$keys,$defVal = false){
        $configId = config("javaconf.".$this->getEnv($configKey).'.'.$configKey);
        static $configIdList = [];
        list($pre,$k,$k2) = explode('.',$keys);
        if(!isset($configIdList[$configId])){
            $data = $this->fetchConfigAll($configId);
            if($data === false){
                $data = json_decode(app('wredis')->get(sprintf("javaconf:%d",$configId)),true);
                if(!$data){
                    return $defVal;
                }
                return array_get($data,$k.'.'.$k2,$defVal);
            }
            $data = array_column($data,null,$pre);
            $configIdList[$configId] = $data;
            app('wredis')->set(sprintf("javaconf:%d",$configId),json_encode($data));
            return array_get($data,$k.'.'.$k2,$defVal);
        }
        return array_get(array_get($configIdList,$configId,[]),$k.'.'.$k2,$defVal);
    }

    /**
     * 根据configKey添加字段
     * @param $configKey string 配置key
     * @param $itemName string 字段名称
     * @param $itemDesc string 字段描述
     * @return array|mixed
     */
    public function addItem($configKey, $itemName, $itemDesc)
    {
        $url        = $this->getUrl() . 'config/item/add';
        $configId   = config("javaconf." . $this->getEnv($configKey) . '.' . $configKey);
        $tableList  = $this->fetchTableInfo($configId);
        $startTime  = microtime(true);

        //判断是否存在该字段
        if(in_array($itemName, array_values($tableList))){
            return "已存在该名称";
        }
        try{
            $response = app('Common')->requestJson($url, [
                'config_id' => $configId,
                'items'     => [
                    [
                        'item_name' => $itemName,
                        'item_desc' => $itemDesc,
                    ],
                ],
            ]);
            $this->falconInc("JavaConf:Error:addItem:Req,t=JavaConf");
            $this->falconCos("JavaConf:ReqTime:addItem,t=JavaConf",$startTime);
            $jsonData = json_decode($response,true);
            $items = array_get($jsonData,'data.items',[]);
            if(empty($items)){
                $this->falconInc("JavaConf:Error:addItem:DataEmpty,t=JavaConf");
                return [];
            }
            return $items;
        }catch (\Exception $e){
            $this->falconInc('JavaConf:Error:addItem:Exception,t=JavaConf');
            $this->falconCos("JavaConf:ReqTime:addItem,t=JavaConf",$startTime);
        }
        return [];
    }

    /**
     * 根据configKey获取字段列表
     * @param $configKey string 配置key
     * @return array 字段列表
     */
    public function getItemList($configKey)
    {
        $configId   = config("javaconf." . $this->getEnv($configKey) . '.' . $configKey);
        $tableList  = $this->fetchTableInfo($configId);
        return $tableList;
    }

    /**
     * 添加内容
     * @param $configKey string 配置key
     * @param $info array 字段对应参数
     * @return array|bool|string
     */
    public function addContent($configKey, $info)
    {
        $url        = $this->getUrl() . 'config/data/add';
        $configId   = config("javaconf." . $this->getEnv($configKey) . '.' . $configKey);
        $tableList  = $this->fetchTableInfo($configId);
        $startTime  = microtime(true);

        if (!is_array($info)) {
            return "参数格式错误";
        }

        $items = $this->_formatTable($tableList, $info);

        try{
            $response = app('Common')->requestJson($url, [
                'config_id' => $configId,
                'items'     => $items,
            ]);
            $this->falconInc("JavaConf:Error:addContent:Req,t=JavaConf");
            $this->falconCos("JavaConf:ReqTime:addContent,t=JavaConf",$startTime);
            $jsonData = json_decode($response,true);
            $recordIds = array_get($jsonData,'data.record_ids',[]);
            $result = $this->fetchItemInfo($configId, $recordIds);
            if(empty($result)){
                $this->falconInc("JavaConf:Error:addContent:DataEmpty,t=JavaConf");
                return [];
            }
            return $result;
        }catch (\Exception $e){
            $this->falconInc('JavaConf:Error:addContent:Exception,t=JavaConf');
            $this->falconCos("JavaConf:ReqTime:addContent,t=JavaConf",$startTime);
        }
        return [];
    }

    /**
     * 根据configKey和recordId修改数据
     * @param $configKey string 配置key
     * @param $recordId string 行id
     * @param $info
     * @return array|bool|string
     */
    public function editContent($configKey, $recordId, $info)
    {
        $url        = $this->getUrl() . 'config/data/change';
        $configId   = config("javaconf." . $this->getEnv($configKey) . '.' . $configKey);
        $tableList  = $this->fetchTableInfo($configId);
        $startTime  = microtime(true);

        if (!is_array($info)) {
            return "参数格式错误";
        }

        $items  = $this->_formatTable($tableList, $info);

        try{
            $response = app('Common')->requestJson($url, [
                [
                    'config_id' => $configId,
                    'record_id' => $recordId,
                    'items'     => $items,
                ]
            ]);
            $this->falconInc("JavaConf:Error:editContent:Req,t=JavaConf");
            $this->falconCos("JavaConf:ReqTime:editContent,t=JavaConf",$startTime);
            $jsonData = json_decode($response,true);
            $recordIds = array_get($jsonData,'data.record_ids',[]);
            $result = $this->fetchItemInfo($configId, $recordIds);
            if(empty($result)){
                $this->falconInc("JavaConf:Error:editContent:DataEmpty,t=JavaConf");
                return [];
            }
            return $result;
        }catch (\Exception $e){
            $this->falconInc('JavaConf:Error:editContent:Exception,t=JavaConf');
            $this->falconCos("JavaConf:ReqTime:editContent,t=JavaConf",$startTime);
        }
        return [];
    }

    /**
     * @param $configKey
     * @param $recordId
     * @return array|mixed
     * 删除一条内容
     */
    public function delContent($configKey, $recordId)
    {
        $url        = $this->getUrl() . 'config/data/del';
        $configId   = config("javaconf." . $this->getEnv($configKey) . '.' . $configKey);
        $startTime  = microtime(true);

        try{
            $response = app('Common')->request($url, [
                'confId'    => $configId,
                'recordId'  => $recordId,
            ]);
            $this->falconInc("JavaConf:Error:delContent:Req,t=JavaConf");
            $this->falconCos("JavaConf:ReqTime:delContent,t=JavaConf",$startTime);
            $jsonData = json_decode($response,true);
            if($jsonData['code'] != '200'){
                $this->falconInc("JavaConf:Error:delContent:DataEmpty,t=JavaConf");
                return [];
            }
            $list = json_decode(app('wredis')->get(sprintf("javaconf:%d:list", $configId)), true);
            if(isset($list[$recordId])){
                unset($list[$recordId]);
                app('wredis')->set(sprintf("javaconf:%d:list", $configId), json_encode($list));
            }
            return $jsonData;
        }catch (\Exception $e){
            $this->falconInc('JavaConf:Error:delContent:Exception,t=JavaConf');
            $this->falconCos("JavaConf:ReqTime:delContent,t=JavaConf",$startTime);
        }
    }

    /**
     * 获取列表及内容
     * @param $configKey
     * @return array|bool
     */
    public function getContentList($configKey)
    {
        $configId   = config("javaconf." . $this->getEnv($configKey) . '.' . $configKey);
        $list = $this->fetchItemList($configId);
        if ($list === false) {
            $list = json_decode(app('wredis')->get(sprintf("javaconf:%d:list", $configId)), true);
            if (!$list) {
                return [];
            }
            return $list;
        }
        app('wredis')->set(sprintf("javaconf:%d:list", $configId), json_encode($list));
        return $list;
    }

    /**
     * 获取单条内容
     * @param $configKey
     * @param $recordId
     * @return array|bool
     */
    public function getContent($configKey, $recordId)
    {
        $configId   = config("javaconf." . $this->getEnv($configKey) . '.' . $configKey);
        $info       = $this->fetchItemInfo($configId, [$recordId]);
        if ($info === false) {
            $info = json_decode(app('wredis')->get(sprintf("javaconf:%d:list", $configId)), true);
            if (!array_get($info, $recordId, '')) {
                return [];
            }
            return [$recordId => $info[$recordId]];
        }
        return $info;
    }
}