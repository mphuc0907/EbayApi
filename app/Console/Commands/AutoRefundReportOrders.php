<?php

namespace App\Console\Commands;

use App\Models\balance;
use App\Models\balance_log;
use App\Models\Conversation;
use App\Models\Messages;
use App\Models\Order;
use App\Models\ReportOrder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoRefundReportOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'report:auto-refund';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting auto refund process...');

        try {
            $reports = ReportOrder::where('status', 1)
                ->where('created_at', '<=', Carbon::now()->subMinutes(15))
                ->get();

            $this->info("Found {$reports->count()} reports to process");

            foreach ($reports as $report) {
                $this->processReport($report);
            }

            $this->info('Auto refund process completed');

        } catch (\Exception $e) {
            $this->error("Auto refund error: {$e->getMessage()}");
            Log::error("Auto refund error: {$e->getMessage()}");
        }
    }



    private function processReport($report): void
    {
        $this->line("Processing report for order: {$report->order_code}");

        try {
            $order = Order::find($report->id_order);

            if (!$order) {
                $this->error("Order not found for report: {$report->order_code}");
                return;
            }

            if ($order->status !== 3) {
                $this->processAutoRefund($report, $order);
            }

        } catch (\Exception $e) {
            $this->error("Error processing report {$report->order_code}: {$e->getMessage()}");
            Log::error("Report processing error: {$e->getMessage()}");
        }
    }

    private function processAutoRefund($report, $order): void
    {
        try {
            $admin = User::where('role', 4)->first();
            if (!$admin?->_id) {
                $this->error("Admin not found");
                return;
            }

            $adminBalance = Balance::where('user_id', $admin['_id'])->first();
            $userBalance = Balance::where('user_id', $order->user_id)->first();

            if (!$adminBalance || !$userBalance) {
                $this->error("Balance not found for order: {$order['order_code']}");
                return;
            }

            $total_price = (float)$order['total_price'];
            // Trừ tiền từ admin
            $lastAdminBalance = (float)$adminBalance['balance'];
            $adminBalance['balance'] = $lastAdminBalance - $total_price;
            $adminBalance->save();

            balance_log::create([
                'id_balance' => $adminBalance['_id'],
                'user_id' => $admin['_id'],
                'action_user' => "Auto refund release payment for order " . $order['order_code'],
                'last_balance' => $lastAdminBalance,
                'transaction_status' => 'refund',
                'current_balance' => $adminBalance['balance'],
                'balance' => $total_price,
                'status' => '3'
            ]);

            // Chuyển tiền cho user
            $lastUserBalance = (float)$userBalance['balance'];
            $userBalance['balance'] = $lastUserBalance + $total_price;
            $userBalance->save();

            balance_log::create([
                'id_balance' => $userBalance['_id'],
                'user_id' => $order['user_id'],
                'action_user' => "Auto refund receive payment for order " . $order['order_code'],
                'transaction_status' => 'refund',
                'last_balance' => $lastUserBalance,
                'current_balance' => $userBalance['balance'],
                'balance' => $total_price,
                'status' => '3'
            ]);

            // Cập nhật trạng thái
            $report['status'] = 4; // Đã hoàn tiền
            $report->save();

            $order['status'] = 3; // Đã hoàn tiền
            $order->save();

            // Gửi thông báo
            $this->sendAutoRefundNotification($order);

            $this->info("Successfully processed refund for order: {$order['order_code']}");

        } catch (\Exception $e) {
            $this->error("Error processing refund: {$e->getMessage()}");
            Log::error("Refund processing error: {$e->getMessage()}");
        }
    }
    private function sendAutoRefundNotification($order): void
    {
        try {
            $user_sys = User::where('role', 5)->first();
            if (!$user_sys['_id']) {
                $this->error("System bot not found");
                return;
            }

            // Gửi tin nhắn cho user
            $this->sendMessageToUser(
                $user_sys['_id'],
                $order->user_id,
                $this->formatAutoRefundMessage($order)
            );

            // Gửi tin nhắn cho seller
            $this->sendMessageToUser(
                $user_sys['_id'],
                $order->id_seller,
                $this->formatAutoRefundMessageSeller($order)
            );

            $this->info("Notifications sent for order: {$order->order_code}");

        } catch (\Exception $e) {
            $this->error("Error sending notifications: {$e->getMessage()}");
            Log::error("Notification error: {$e->getMessage()}");
        }
    }

    private function sendMessageToUser($fromId, $toId, $message): void
    {
        $conversation = Conversation::firstOrCreate(
            [
                'id_user1' => $fromId,
                'id_user2' => $toId
            ],
            [
                'last_mess' => $message,
                'last_mess_id' => $fromId
            ]
        );

        Messages::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $fromId,
            'message' => $message,
            'status' => 0
        ]);

        $conversation->update([
            'last_mess' => $message,
            'last_mess_id' => $fromId,
            'updated_at' => now()
        ]);
    }

    private function formatAutoRefundMessage($order): string
    {
        $time = Carbon::now()->format('H:i:s d/m/Y');
        $formattedAmount = number_format($order->total_price, 0, ',', '.') . 'USDT';

        return "⏰ AUTOMATIC REFUND NOTICE 💰\n\n" .
            ". Time: {$time}\n" .
            ". Order code: {$order->order_code}\n" .
            ". Refund amount: {$formattedAmount}\n" .
            ". Reason: Seller did not handle the complaint within the specified time\n" .
            ". Status: Automatically refunded\n\n" .
            ". The amount has been transferred to your wallet.\n" .
            ". Please check your balance and contact us if you have any questions!";
    }


    private function formatAutoRefundMessageSeller($order): string
    {
        $time = Carbon::now()->format('H:i:s d/m/Y');
        $formattedAmount = number_format($order->total_price, 0, ',', '.') . ' USDT';

        return "⚠️ AUTOMATIC REFUND NOTICE ⚠️\n\n" .
            ". Time: {$time}\n" .
            ". Order code: {$order->order_code}\n" .
            ". Refund amount: {$formattedAmount}\n" .
            ". Reason: Complaint not handled within the specified time\n" .
            ". Action: The system has automatically refunded the customer\n\n" .
            ". Please handle complaints within the specified time to avoid automatic refunds!";
    }

}
