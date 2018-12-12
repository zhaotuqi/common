<?php
/**
 *
 * @author hongfei.geng<hongfei.geng@wenba100.com>
 * @Date: 2018-12-05
 */

namespace App\Libraries;


use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMq
{
    private function getCon(){
        static $con = false;
        if($con === false){

            switch(env('RABBITMQ_ENV')){
                case 'qa01':
                    $con = new AMQPStreamConnection('10.2.1.126', 5673, 'guest', 'guest');
                    break;
                case 'qa02':
                    $con = new AMQPStreamConnection('10.2.1.126', 5674, 'guest', 'guest');
                    break;
                case 'qa03':
                    $con = new AMQPStreamConnection('10.2.1.126', 5675, 'guest', 'guest');
                    break;
                case 'release':// @todo 预发环境的处理
                    $con = new AMQPStreamConnection('10.21.93.218', 5672, 'guest', 'guest');
                    break;
                case 'online':// @todo 正式环境的配置
                    $con = new AMQPStreamConnection('10.10.90.107', 5672, 'guest', 'guest');
                    break;
                default:
                    $con = new AMQPStreamConnection('10.2.1.126', 5672, 'guest', 'guest');
                    break;

            }

        }

        return $con;
    }

    /**
     * 发送消息
     * @author hongfei.geng<hongfei.geng@wenba100.com>
     * @Date: 2018-12-05
     * @param $topicName
     * @param $msg
     */
    public function sendQueue($queueName,$msg){
        try{
            $con = $this->getCon();
            if($con) {
                $channel = $con->channel();
                $channel->exchange_declare($queueName, 'fanout', false, true, false);
                $amqMsg = new AMQPMessage($msg);
                $channel->basic_publish($amqMsg, $queueName);
                $channel->close();
            }
        }catch (\Exception $e){
            var_dump($e->getTraceAsString()
            );
            dispatch((new RabbitMqJob($queueName,$msg))->delay(15));
        }

    }

    /**
     * 消费队列
     * @author hongfei.geng<hongfei.geng@wenba100.com>
     * @Date: 2018-12-05
     * @param $queueName
     * @param $callBack
     */
    public function consumeQueue($queueName,$callBack){
        $con = $this->getCon();
        if($con) {

            $channel = $con->channel();
            $channel->queue_declare($queueName, false, true, false, false);
            //$channel->basic_qos(null,10000,null);
            $channel->basic_consume($queueName, '', false, false, false, false, $callBack);

            while (count($channel->callbacks)) {
                $channel->wait();
            }
        }
    }

    public function __destruct()
    {
        $this->getCon()->close();
    }

}