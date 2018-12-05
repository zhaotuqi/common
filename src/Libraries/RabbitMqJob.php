<?php
/**
 *
 * @author hongfei.geng<hongfei.geng@wenba100.com>
 * @Date: 2018-12-06
 */

namespace App\Libraries;

use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Bus\Queueable;

class RabbitMqJob implements ShouldQueue
{
    use Queueable;
    use InteractsWithQueue, SerializesModels;

    protected $queueName;
    protected $msg;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($queueName,$msg)
    {
        $this->queueName = $queueName;
        $this->msg = $msg;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        app('amq')->sendQueue($this->queueName,$this->msg);
    }
}