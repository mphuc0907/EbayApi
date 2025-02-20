<?php

namespace App\Console\Commands;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Console\Command;

class UpdateOrder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'updateorder:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cập nhật trạng thái đơn hàng sau 3 ngày nếu đơn hàng vẫn đang có status là 0';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        $orders = Order::where('status', 0)
            ->where('created_at', '<=', Carbon::now()->subDays(3))
            ->get();

        foreach ($orders as $order) {
            // Cập nhật trạng thái đơn hàng
            $order->status = 1;
            $order->save();
        }

        $this->info('Đã cập nhật trạng thái cho các đơn hàng đủ điều kiện.');
    }
}
