<?php
/**
 *
 * @author hongfei.geng<hongfei.geng@wenba100.com>
 * @Date: 2018-12-05
 */

namespace App\Libraries;

use Monolog\Logger;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMq
{
    private function getCon(){
        static $con = false;
        if($con === false || $con->isConnected() === false){
            $con = new AMQPStreamConnection(
                env('RABBITMQ_HOST','10.2.1.126'),
                env('RABBITMQ_PORT',5672),
                env('RABBITMQ_USER','guest'),
                env('RABBITMQ_PASSWORD','guest')
            );
        }

        return $con;
    }

    public function logger($exchange,$msg,$level = Logger::INFO)
    {
        $appName = config('app.app_name');
        $log    = new Logger($appName);
        $handle = new \App\Extension\LogRewrite('/data/logs/' . $appName . '/rabbitmq.log', config('app.log_max_files'));
        $log->pushHandler($handle);
        $log->log($level, sprintf('%s -> %s',$exchange,$msg));

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
        $this->logger($exchange,$msg);
        try{
            $con = $this->getCon();
            if($con) {
                $channel = $con->channel();
                $channel->confirm_select();
                $isSendOk = false;
                $channel->set_ack_handler(function($message)use(&$isSendOk){
                    $isSendOk = true;
                });
                $channel->set_nack_handler(function ($message)use ($exchange,$msg){
                    dispatch((new RabbitMqJob($exchange,$msg))->delay(60));
                });
                $channel->exchange_declare($exchange, 'fanout', false, true, false);
                $amqMsg = new AMQPMessage($msg);
                $ret = $channel->basic_publish($amqMsg, $exchange);
                $channel->wait_for_pending_acks(3);
                $channel->close();
                if(!$isSendOk){
                    $this->logger($exchange,$msg.' ： 发送失败',Logger::WARNING);
                    throw new \Exception("发送消息失败");
                }
            }else{
                $this->logger($exchange,$msg.' ： 链接断开',Logger::WARNING);
                throw new \Exception("链接RabbitMq失败");
            }
        }catch (\Exception $e){
            dispatch((new RabbitMqJob($exchange,$msg))->delay(60));
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
        try {
            $con = $this->getCon();
            if ($con) {

                $channel = $con->channel();
                $channel->queue_declare($queueName, false, true, false, false);
                $channel->basic_consume($queueName, '', false, false, false, false, $callBack);

                while (count($channel->callbacks)) {
                    $channel->wait();
                }
            }
        }catch (\Exception $e){
            echo sprintf("[%s] %s\n",date("Y-m-d H:i:s"),$e->getTraceAsString());
            sleep(15);
            return $this->consumeQueue($queueName,$callBack);
        }
    }

    public function __destruct()
    {
        $this->getCon()->close();
    }

    private function __checkConnection()
    {
        $checkArray = [
            'RABBITMQ_HOST'        => env('RABBITMQ_HOST',''),
            'RABBITMQ_PORT'        => env('RABBITMQ_PORT',''),
            'RABBITMQ_PORT'        => env('RABBITMQ_PORT',''),
            'RABBITMQ_PASSWORD'    => env('RABBITMQ_PASSWORD',''),
            'RABBITMQ_VHOST_JSPT'  => env('RABBITMQ_VHOST_JSPT','')
        ];

        foreach ($checkArray as $key => $value) {
            if (empty($value)) {
                throw new \Exception('connect java rabbitMq error : env config'.$key.'can not empty!');
            }
        }

        $con = new AMQPStreamConnection(
            env('RABBITMQ_HOST'),
            env('RABBITMQ_PORT'),
            env('RABBITMQ_USER'),
            env('RABBITMQ_PASSWORD'),
            env('RABBITMQ_VHOST_JSPT')
        );

        return $con;
    }

    /**
     * 结算平台rabbitMQ
     *
     * @param $exchange
     * @param $msg
     * @throws \Exception
     */
    public function sendQueueToJSPT($exchange, $msg)
    {
        $con = $this->__checkConnection();

        try {
            if (empty($con)) {
                throw new \Exception("链接RabbitMq失败");
            }
            // 创建MQ服务内部channel通信
            $channel = $con->channel();

            // 设置交换机名
            $channel->exchange_declare($exchange, 'fanout', false, true, false);

            // 创建MQ消息
            $amqMsg = new AMQPMessage($msg, ['delivery' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);

            // 发布MQ消息
            $channel->basic_publish($amqMsg, $exchange);

            // 关闭频道
            $channel->close();

        }catch (\Exception $e) {

            dispatch((new RabbitMqJSPTJob($exchange, $msg))->delay(60));
        }
    }
}