<?php
/**
 *
 * @author hongfei.geng<hongfei.geng@wenba100.com>
 * @Date: 2018-12-05
 */

namespace App\Libraries;

use Illuminate\Support\Facades\Log;
use Monolog\Logger;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMq
{
    public function getCon()
    {
        static $con = false;
        if ($con === false || $con->isConnected() === false) {

            $rabbitmqConfig = [
                "RABBITMQ_HOST" => env('RABBITMQ_HOST'),
                "RABBITMQ_PORT" => env('RABBITMQ_PORT'),
                "RABBITMQ_USER" => env('RABBITMQ_USER'),
                "RABBITMQ_PASSWORD" => env('RABBITMQ_PASSWORD'),
                'RABBITMQ_VHOST' => '/'
            ];
            //检查rabbitmq连接配置项
            $checkConfigMsg = "";
            $checkConfigMsg .= empty($rabbitmqConfig["RABBITMQ_HOST"]) ? ".env文件： RABBITMQ_HOST 未配置" . PHP_EOL : "";
            $checkConfigMsg .= empty($rabbitmqConfig["RABBITMQ_PORT"]) ? ".env文件： RABBITMQ_PORT 未配置" . PHP_EOL : "";
            $checkConfigMsg .= empty($rabbitmqConfig["RABBITMQ_USER"]) ? ".env文件： RABBITMQ_USER 未配置" . PHP_EOL : "";
            $checkConfigMsg .= empty($rabbitmqConfig["RABBITMQ_PASSWORD"]) ? ".env文件： RABBITMQ_PASSWORD 未配置" . PHP_EOL : "";

            if (!empty($checkConfigMsg)) {
                throw new \Exception(PHP_EOL . $checkConfigMsg);
            }

            $con = new AMQPStreamConnection(
                $rabbitmqConfig['RABBITMQ_HOST'],
                $rabbitmqConfig['RABBITMQ_PORT'],
                $rabbitmqConfig['RABBITMQ_USER'],
                $rabbitmqConfig['RABBITMQ_PASSWORD'],
                $rabbitmqConfig['RABBITMQ_VHOST']
            );
        }

        return $con;
    }

    public function logger($exchange, $msg, $level = Logger::INFO)
    {
        $appName = config('app.app_name');
        $log = new Logger($appName);
        $handle = new \App\Extension\LogRewrite('/data/logs/' . $appName . '/rabbitmqbody.log',
            config('app.log_max_files'));
        $log->pushHandler($handle);
        $log->log($level, sprintf('%s -> %s', $exchange, $msg));
    }

    /**
     * @param $time
     * @param $exchange
     * @param int $level
     * 记录RabbitMQ连接&发送时间
     */
    private function logger_time($time, $exchange, $level = Logger::INFO)
    {
        $appName = config('app.app_name');
        $log = new Logger($appName);
        $handle = new \App\Extension\LogRewrite('/data/logs/' . $appName . '/rabbitmqtime.log',
            config('app.log_max_files'));
        $log->pushHandler($handle);
        $log->log($level, sprintf('%s -> %s', $time, $exchange));
    }

    /**
     * 发送消息
     * @param $exchange
     * @param $msg
     * @author hongfei.geng<hongfei.geng@wenba100.com>
     * @Date: 2018-12-05
     */
    public function sendQueue($exchange, $msg)
    {
        $ret = false;
        $this->logger($exchange, $msg);
        $i = 0;
        B:
        try {
            $startTime = microtime(true);
            $con = $this->getCon();
            if ($con) {
                $channel = $con->channel();
                $channel->confirm_select();
                $isSendOk = false;
                $channel->set_ack_handler(function ($message) use (&$isSendOk) {
                    $isSendOk = true;
                });
                $channel->set_nack_handler(function ($message) use ($exchange, $msg) {
                });
                $channel->exchange_declare($exchange, 'fanout', false, true, false);
                $amqMsg = new AMQPMessage($msg, ['delivery' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);
                $ret = $channel->basic_publish($amqMsg, $exchange);
                $channel->wait_for_pending_acks(3);
                $channel->close();
                if (!$isSendOk) {
                    $this->logger($exchange, $msg . ' ： 发送失败', Logger::WARNING);
                    throw new \Exception("发送消息失败");
                }
            } else {
                $this->logger($exchange, $msg . ' ： 链接断开', Logger::WARNING);
                throw new \Exception("链接RabbitMq失败");
            }

            $endTime = microtime(true);
            $time = $endTime - $startTime;
            $this->logger_time($time . "=" . $endTime . "-" . $startTime, $exchange);
        } catch (\Exception $e) {
            $i++;
            if ($i >= 3) {
                $msg = 'rabbitmq生产数据失败告警，等待10秒重试。已经三次仍然没有成功。参数如下，exhange:' . $exchange . 'param：' . $msg;
                $this->logger($msg, Logger::WARNING);
                throw new \Exception($msg);
            }
            sleep(10);
            goto B;
        }
        return $ret;
    }

    /**
     * 消费队列
     * @param $queueName
     * @param $callBack
     * @param null $prefetchSize
     * @param int $prefetchCount
     * @return mixed
     * @author hongfei.geng<hongfei.geng@wenba100.com>
     * @Date: 2018-12-05
     */
    public function consumeQueue($queueName, $callBack, $prefetchSize = null, $prefetchCount = 1)
    {
        try {
            $con = $this->getCon();
            if ($con) {

                $channel = $con->channel();
                $channel->queue_declare($queueName, false, true, false, false);
                $channel->basic_qos($prefetchSize, $prefetchCount, false);
                $channel->basic_consume($queueName, '', false, false, false, false, $callBack);

                while (count($channel->callbacks)) {
                    $channel->wait();
                }
            }
        } catch (\Exception $e) {
            $message = sprintf("[异常原因: %s]\n[主机名称: %s]\n[时间：%s]\n[异常状态码: %s]\n异常所在文件行：[%s:%s]\n异常详情：\n%s",
                $e->getMessage(),
                gethostname(),
                date("Y-m-d H:i:s"),
                $e->getCode(),
                $e->getFile(),
                $e->getLine(),
                $e->getTraceAsString());
            Log::error('rabbitmq消费异常,队列名称：' . $queueName . '请手动重启脚本，或者存在脚本守护进程,守护进程会自动重启异常脚本' . $message);
            exit(1); //异常了退出即可，守护进程会让他自动重启，由于没有返回ACK 消息会再次派发
            // sleep(15);
            //return $this->consumeQueue($queueName,$callBack);
        }
    }

    public function __destruct()
    {
        $this->getCon()->close();
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
        $startTime = microtime(true);
        $this->logger($exchange, $msg);
        $i = 0;
        A:
        $con = $this->getCon();

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

            $endTime = microtime(true);
            $time = $endTime - $startTime;
            $this->logger_time($time . "=" . $endTime . "-" . $startTime, $exchange);
        } catch (\Exception $e) {
            $i++;
            if ($i >= 3) {
                $msg = 'rabbitmq生产数据失败告警，等待10秒重试。已经三次仍然没有成功。参数如下，exhange:' . $exchange . 'param：' . $msg;
                $this->logger($msg, Logger::WARNING);
                throw new \Exception($msg);
            }
            sleep(10);
            goto A;
        }
    }
}
