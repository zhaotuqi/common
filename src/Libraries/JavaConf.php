<?php
/**
 * @author: 耿鸿飞<15911185633>
 * @date: 2019-03-19 18:02
 * @link: http://10.2.1.12:8090/pages/viewpage.action?pageId=10223764  接口文档地址
 */

namespace App\Libraries;


class JavaConf
{
    /**
     * 获取配置的服务器地址
     * @author: 耿鸿飞<15911185633>
     * @date: 2019-03-19 18:06
     * @link:
     * @return bool|string
     */
    private function getUrl(){
        static $request_url = false;
        if($request_url === false){
            if('pro' == $this->getEnv()){
                $request_url = 'http://10.19.71.110:8086/';
            }else{
                $request_url = 'http://10.2.1.154:8086/';
            }
        }
        return $request_url;
    }

    private function getEnv(){
        if(in_array(strtolower(env('REDIS_CMS_ENV','pro')),['pro','pre'])){
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
        try{

            $response = app('Common')->query($url,['config_id' => $configId]);
            $this->falconInc("JavaConf:Error:GetMap:Req,t=JavaConf");
            $jsonData = json_decode($response,true);
            $items = array_get($jsonData,'items',[]);
            $this->falconCos("JavaConf:ReqTime:GetMap,t=JavaConf",$startTime);
            if(empty($items)){
                $this->falconInc("JavaConf:Error:GetMap:DataEmpty,t=JavaConf");
                return [];
            }
            return array_column($items,'item_name','item_id');
        }catch (\Exception $e){
            $this->falconInc("JavaConf:Error:GetMap:Exception,t=JavaConf");
            $this->falconCos("JavaConf:ReqTime:GetMap,t=JavaConf",$startTime);
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
        try{
            $response = app('Common')->query($url,[
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
            return $items;
        }catch (\Exception $e){
            $this->falconInc('JavaConf:Error:GetConf:Exception,t=JavaConf');
            $this->falconCos("JavaConf:ReqTime:GetConf,t=JavaConf",$startTime);
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
        $configId = config("javaconf.".$this->getEnv().'.'.$configKey);
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
}