<?php
/**
 *
 * @author hongfei.geng<hongfei.geng@wenba100.com>
 * @Date: 2018-10-26
 */

namespace App\Libraries;


class CommonRedis
{
    /**
     * 代理redis的操作方法，对操作次数和平均时长打点
     * @author hongfei.geng<hongfei.geng@wenba100.com>
     * @Date: 2018-10-26
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        $startTime  = microtime(true);

        $ret = call_user_func_array([app('redis'),$name],$arguments);

        return $ret;
    }

}