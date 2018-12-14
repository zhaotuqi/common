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
                case 'release':
                    $con = new AMQPStreamConnection('10.21.93.218', 5672, 'guest', 'guest');
                    break;
                case 'online':
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
     * @param $exchange
     * @param $msg
     */
    public function sendQueue($exchange,$msg){
        $ret = false;
        try{
            $con = $this->getCon();
            if($con) {
                $channel = $con->channel();
                $channel->exchange_declare($exchange, 'fanout', false, true, false);
                $amqMsg = new AMQPMessage($msg);
                $ret = $channel->basic_publish($amqMsg, $exchange);
                $channel->close();
            }
        }catch (\Exception $e){
            dispatch((new RabbitMqJob($exchange,$msg))->delay(15));
        }
        return $ret;
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