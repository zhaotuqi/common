<?php
/**
 *
 * @author hongfei.geng<hongfei.geng@wenba100.com>
 * @Date: 2018-12-05
 */

namespace App\Libraries;

use Illuminate\Console\Command;
use phpDocumentor\Reflection\Types\Boolean;

abstract class RabbitMqCommand extends Command
{
    /**
     * 获取队列的名字
     * @author hongfei.geng<hongfei.geng@wenba100.com>
     * @Date: 2018-12-05
     * @return String
     */
    abstract function getQueueName();

    /**
     * 处理消息，成功处理返回True，处理失败返回False
     * @author hongfei.geng<hongfei.geng@wenba100.com>
     * @Date: 2018-12-05
     * @param $msg
     * @return Boolean
     */
    abstract function msg($msg);


    public function handle(){
        $amq = app('amq');
        $queueName = $this->getQueueName();

        $amq->consumeQueue($queueName,[$this,'handMsg']);

    }

    public function handMsg($msg){
        if($this->msg($msg->body) === true){
            //确认消息消费
            $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
        }
    }


}