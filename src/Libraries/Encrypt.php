<?php
/**
 * @author shiayo.niu@wenba100.com
 * @Date: 2019-06-04
 */

namespace App\Libraries;


class Encrypt
{
    private static $iv;
    public function __construct()
    {
        self::$iv = 'Wenba;EdU190606#daf%sCd';
    }

    /**
     * 加密算法
     * @author shiyao.niu@wenba100.com
     * @date   2019-06-04
     */

    public static function encryptsec($data,$key,$iv = '')
    {
        $key = md5($key);
        $iv = empty($iv) ? self::$iv: $iv;
        $data=serialize($data);
        $encryptArr['iv']=base64_encode(substr($iv,0,16));
        $encryptArr['value']=openssl_encrypt($data, 'AES-256-CBC',$key,0,base64_decode($encryptArr['iv']));
        $encrypt=base64_encode(json_encode($encryptArr));
        return $encrypt;
    }

    /**
     * 解密算法
     * @author shiyao.niu@wenba100.com
     * @date   2019-06-04
     */
    public static function decryptsec($encrypt,$key)
    {
        $key = md5($key);
        $encrypt = json_decode(base64_decode($encrypt), true);
        $iv = base64_decode($encrypt['iv']);
        $decrypt = openssl_decrypt($encrypt['value'], 'AES-256-CBC', $key, 0, $iv);
        $data = unserialize($decrypt);
        return empty($data) ? '' : $data;
    }

}