<?php

namespace Dx\Payroll\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Dx\Payroll\Http\Controllers\MonthlyController;

class MonthlyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $data;
    public function __construct($data = [])
    {
        if(empty($data)){
            return 1;
        }
        $this->data = json_decode(base64_decode($data), true);
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws
     */
    public function handle()
    {
        app(MonthlyController::class)->processMonthly($this->data);
    }

    /**
     * Custom info the job.
     *
     * @return void
     * @throws
     */
    public function tags()
    {
        $employeeId = $this->data['code'] ?? '';
        $month = $this->data['month'] ?? '';
        $str = 'Employee : '. $employeeId.' - Monthly : '.$month;
        return [$str];
    }
}
