<?php

namespace App\Console\Commands;

use App\Models\balance;
use App\Models\balance_log;
use App\Models\Order;
use App\Models\User;
use Illuminate\Console\Command;
use Carbon\Carbon;

class ProcessTransferOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:transfer-money {--minutes=10 : Minutes to wait before processing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Transfer money from admin to seller and process reseller commission after specified minutes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $minutes = $this->option('minutes');
        $timeAgo = Carbon::now()->subMinutes(2);

        $this->info("Starting to process orders created before: " . $timeAgo);

        // Lấy đơn hàng thường và dịch vụ
        $normalPendingOrders = Order::where('status', 0)
            ->where('status_kiot', '!=' , 'service')
            ->where('created_at', '<=', $timeAgo)
            ->get();

        $servicePendingOrders = Order::where('status', 8)
            ->where('status_kiot', 'service')
            ->where('created_at', '<=', $timeAgo)
            ->get();

        $pendingOrders = $normalPendingOrders->concat($servicePendingOrders);

        $totalOrders = $pendingOrders->count();
        $this->info("Found {$totalOrders} total orders to process");
        $this->info("- Normal orders: " . $normalPendingOrders->count());
        $this->info("- Service orders: " . $servicePendingOrders->count());

        if ($totalOrders === 0) {
            $this->info("No orders to process");
            return;
        }

        $bar = $this->output->createProgressBar($totalOrders);
        $bar->start();

        $successCount = 0;
        $failCount = 0;

        foreach ($pendingOrders as $order) {
            try {
                $isServiceOrder = $order->status_kiot == 'service' && $order->status == 8;

//                // Kiểm tra thời gian chờ cho đơn dịch vụ
//                if ($isServiceOrder) {
//                    $waitingDays = $order->service_waitingdays;
//                    $orderDate = Carbon::parse($order->created_at);
//                    $dueDate = $orderDate->addDays($waitingDays);
//
//                    if (Carbon::now()->lessThan($dueDate)) {
//                        $this->info("Service order {$order->order_code} still in waiting period");
//                        $successCount++;
//                        $bar->advance();
//                        continue;
//                    }
//                }

                // Xử lý thanh toán cho cả 2 loại đơn
                $admin = User::where('role', 4)->first();
                if (!$admin || !$admin->_id) {
                    $this->error("Admin role 4 not found for order: {$order->order_code}");
                    $failCount++;
                    continue;
                }

                $adminBalance = Balance::where('user_id', $admin->_id)->first();
                $sellerBalance = Balance::where('user_id', $order->id_seller)->first();

                if (!$adminBalance || !$sellerBalance) {
                    $this->error("Balance not found for order: {$order->order_code}");
                    $failCount++;
                    continue;
                }

                $total_price = (float)$order->total_price;
                $platformFee = (float)$order->admin_amount;
                $remainingAmount = $total_price - $platformFee;

                // 1. Trừ tiền từ admin
                $lastAdminBalance = (float)$adminBalance->balance;
                $adminBalance->balance = $lastAdminBalance - $total_price;
                $adminBalance->save();

                balance_log::create([
                    'id_balance' => $adminBalance->_id,
                    'user_id' => $admin->_id,
                    'action_user' => "Release hold payment for order " . $order->order_code,
                    'transaction_status' => 'release',
                    'last_balance' => $lastAdminBalance,
                    'current_balance' => $adminBalance->balance,
                    'balance' => $total_price,
                    'status' => '3'
                ]);

                // 2. Cộng phí sàn cho admin
                $lastAdminBalance = (float)$adminBalance->balance;
                $adminBalance->balance = $lastAdminBalance + $platformFee;
                $adminBalance->save();

                balance_log::create([
                    'id_balance' => $adminBalance->_id,
                    'user_id' => $admin->_id,
                    'action_user' => "Platform fee for order " . $order->order_code,
                    'transaction_status' => 'fee',
                    'last_balance' => $lastAdminBalance,
                    'current_balance' => $adminBalance->balance,
                    'balance' => $platformFee,
                    'status' => '3'
                ]);

                // 3. Xử lý commission cho reseller nếu có
                $resellerAmount = 0;
                $hasReseller = false;

                if ($order->ref_user_id && $order->name_ref && $order->reseller_amount) {
                    $resellerBalance = Balance::where('user_id', $order->ref_user_id)->first();

                    if ($resellerBalance) {
                        $hasReseller = true;
                        $resellerAmount = (float)$order->reseller_amount;

                        $lastResellerBalance = (float)$resellerBalance->balance;
                        $resellerBalance->balance = $lastResellerBalance + $resellerAmount;
                        $resellerBalance->save();

                        balance_log::create([
                            'id_balance' => $resellerBalance->_id,
                            'user_id' => $order->ref_user_id,
                            'action_user' => "Receive reseller commission for order " . $order->order_code . " (Reseller: " . $order->name_ref . ")",
                            'transaction_status' => 'reseller_commission',
                            'last_balance' => $lastResellerBalance,
                            'current_balance' => $resellerBalance->balance,
                            'balance' => $resellerAmount,
                            'status' => '3'
                        ]);

                        $this->info("Processed reseller commission for order: {$order->order_code}");
                    }
                }

                $lastSellerBalance = (float)$sellerBalance->hold_balance;
                $total_hold = $total_price - $platformFee - $resellerAmount;
                $sellerBalance->hold_balance = $lastSellerBalance - $total_hold;
                $sellerBalance->save();

                // 4. Chuyển tiền còn lại cho seller
                $refund_rating = (float)$order->refund_money;
                // chuyển tiền rating cho user nếu có
                if ($refund_rating != null){
                    $user_balance = Balance::where('user_id', $order->user_id)->first();
                    $lastUserBalance = (float)$user_balance->balance;
                    $user_balance->balance = $lastUserBalance + $refund_rating;
                    $user_balance->save();

                    balance_log::create([
                        'id_balance' => $sellerBalance->_id,
                        'user_id' => $order->user_id,
                        'action_user' => "Receive payment (after platform fee and refund rating) for order " . $order->order_code,
                        'transaction_status' => 'rating',
                        'last_balance' => $lastSellerBalance,
                        'current_balance' => $sellerBalance->balance,
                        'balance' => $refund_rating,
                        'status' => '3'
                    ]);
                }
                $sellerAmount = $remainingAmount - $resellerAmount;
                $lastSellerBalance = (float)$sellerBalance->balance;
                $sellerBalance->balance = $lastSellerBalance + $sellerAmount - (float)$refund_rating;
                $sellerBalance->save();

                $actionDescription = $hasReseller
                    ? "Receive payment (after platform fee and reseller commission) for order "
                    : "Receive payment (after platform fee) for order ";

                balance_log::create([
                    'id_balance' => $sellerBalance->_id,
                    'user_id' => $order->id_seller,
                    'action_user' => $actionDescription . $order->order_code,
                    'transaction_status' => 'receive',
                    'last_balance' => $lastSellerBalance,
                    'current_balance' => $sellerBalance->balance,
                    'balance' => $sellerAmount - $refund_rating,
                    'status' => '3'
                ]);

                // 5. Cập nhật trạng thái đơn hàng
                $order->status = $isServiceOrder ? 9 : -1; // 9 cho dịch vụ hoàn thành, -1 cho đơn thường
                $order->save();

                $successCount++;

            } catch (\Exception $e) {
                $this->error("Error processing order {$order->order_code}: " . $e->getMessage());
                \Log::error('Order processing failed', [
                    'order_id' => $order->_id,
                    'order_code' => $order->order_code,
                    'error' => $e->getMessage()
                ]);
                $failCount++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info("Processing completed:");
        $this->info("- Successfully processed: {$successCount} orders");
        if ($failCount > 0) {
            $this->error("- Failed to process: {$failCount} orders");
        }
    }
}
// note
/*

Admin bị trừ toàn bộ đơn hàng (total_price)
Admin nhận phí sàn (platformFee)
Số tiền còn lại (remainingAmount = total_price - platformFee):

Nếu có reseller:

Reseller nhận % theo cấu hình ($resellerAmount)
Seller nhận phần còn lại ($remainingAmount - $resellerAmount)


Nếu không có reseller:

Seller nhận toàn bộ ($remainingAmount)
*/
