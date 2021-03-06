<?php
/**
 * @author shiayo.niu@wenba100.com
 * @Date: 2019-06-04
 */

namespace App\Libraries;

class Encryption
{
    private static $iv;
    private static $cipher;
    public function __construct()
    {
        self::$iv       = 'Wenba;EdU190606#daf%sCd';
        self::$cipher   = 'AES-256-CBC';
    }

    /**
     * 加密算法
     * @author shiyao.niu@wenba100.com
     * @date   2019-06-04
     */

    public static function encrypt($data,$key,$iv = '')
    {
        if (empty($key)) {
            throw new \Exception("key不能为空");
        }
        if (empty($data)) {
            throw new \Exception("加密数据不能为空");
        }
        $key = md5(strtoupper($key));
        $iv = empty($iv) ? self::$iv: $iv;
        $data=serialize($data);
        $encryptArr['iv']=base64_encode(substr($iv,0,16));
        $encryptArr['value']=openssl_encrypt($data, self::$cipher,$key,0,base64_decode($encryptArr['iv']));
        $encrypt=base64_encode(json_encode($encryptArr));
        return $encrypt;
    }

    /**
     * 解密算法
     * @author shiyao.niu@wenba100.com
     * @date   2019-06-04
     */
    public static function decrypt($encrypt,$key)
    {
        if (empty($key)) {
            throw new \Exception("key不能为空");
        }
        if (empty($encrypt)) {
            throw new \Exception("密文不能为空");
        }
        $key = md5(strtoupper($key));
        $encrypt = json_decode(base64_decode($encrypt), true);
        $iv = base64_decode($encrypt['iv']);
        $decrypt = openssl_decrypt($encrypt['value'], self::$cipher, $key, 0, $iv);
        $data = unserialize($decrypt);
        return empty($data) ? '' : $data;
    }

}