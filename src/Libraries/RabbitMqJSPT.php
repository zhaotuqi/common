<?php

namespace App\Libraries;

use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Bus\Queueable;

class RabbitMqJSPT implements ShouldQueue
{
    use Queueable;
    use InteractsWithQueue, SerializesModels;

    protected $exchange;
    protected $msg;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($exchange, $msg)
    {
        $this->exchange = $exchange;
        $this->msg      = $msg;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        app('amq')->sendQueueToJSPT($this->exchange, $this->msg);
    }
}
