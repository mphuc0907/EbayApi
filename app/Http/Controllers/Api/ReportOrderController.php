<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\balance;
use App\Models\balance_log;
use App\Models\Conversation;
use App\Models\KiotSub;
use App\Models\Messages;
use App\Models\NoteReportOrder;
use App\Models\Order;
use App\Models\ReportOrder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\ApiController as ApiController;
use Illuminate\Support\Facades\Log;

class ReportOrderController extends ApiController
{
    public function Add(Request $request) {

        try {
            // Kiểm tra xem người dùng đã đăng nhập chưa
            if (!auth()->check()) {
                return $this->sendError('User is not authenticated.', [], 401);
            }

            $user = auth()->user();
            $data['user_create'] = $user['_id'];

//            // Kiểm tra xem mã đơn hàng đã có báo cáo chưa
//            $existingReport = ReportOrder::where('order_code', $request['order_code'])->first();
//            if ($existingReport) {
//                return $this->sendError('This order has already been reported.', [], 400);
//            }

            // Chuẩn bị dữ liệu cho báo cáo
            $data['id_user'] = $user['_id'];
            $data['name_user'] = $user['name'];
            $data['order_code'] = $request['order_code'];
            $data['id_order'] = $request['id_order'];
            $data['id_seller'] = $request['id_seller'];
            $data['info_report'] = $request['info_report'];
            $data['id_kiot'] = $request['id_kiot'];
            $data['reason'] = $request['reason'];
            $data['status'] = 1;
            $data['user_complain_time'] = Carbon::now()->format('Y-m-d H:i:s');


            // Kiểm tra xem đã tồn tại báo cáo với order_code chưa
            $reportCrunt = ReportOrder::where('order_code', $request['order_code'])->first();
            if (!empty($reportCrunt)) {
                // Cập nhật báo cáo đã tồn tại
                $reportCrunt->status = 1;
                $reportCrunt->info_report = $request['info_report'];
                $reportCrunt->reason = $request['reason'];
                $reportCrunt->save();

                // Cập nhật trạng thái đơn hàng
                $order = Order::find($reportCrunt['id_order']);
                if ($order) {
                    $order->is_report = 1;
                    $order->status = 1;
                    $order->save();
                }

                $reportOrder = $reportCrunt; // Gán giá trị cho $reportOrder để trả về

            } else {
                // Tạo báo cáo mới
                $reportOrder = ReportOrder::create($data);

                // Cập nhật trạng thái đơn hàng
                $order = Order::find($reportOrder['id_order']);
                if ($order) {
                    $order->is_report = 1;
                    $order->status = 1;
                    $order->save();
                }
            }
            $this->sendReportNotificationToSeller($reportOrder);
            // Trả về phản hồi thành công
            return $this->sendResponse($reportOrder, 'User report successfully.');

        } catch (\Exception $e) {
            // Ghi log lỗi nếu cần và trả về phản hồi lỗi
            return $this->sendError('An error has occurred. Please try again later.', ['error' => $e->getMessage()], 400);
        }
    }

    public function addNoteReportOrder(Request $request)
    {
        try {
            $params = $request->all();
            $reportOrder = ReportOrder::where('order_code', $params['order_code'])->first();

            if (!$reportOrder) {
                return $this->sendError('Report Order not found.');
            }
            $note =  $params['note'];
            $noteReportOrder = NoteReportOrder::create([
                'id_report_order' => $reportOrder['_id'],
                'order_code' => $reportOrder['order_code'],
                'note' => $note
            ]);

            return $this->sendResponse($noteReportOrder, 'Note report created successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error updating note: ' . $e->getMessage());
        }
    }
    function getNoteReportOrder($order_code){
        $note = NoteReportOrder::where('order_code', $order_code)->get();
        return response($note, 200)->header('Content-Type', 'text/plain');
    }
    public function getSeller($id){
        $reportOrder = ReportOrder::where('id_seller',  $id)->orderBy('created_at', 'desc')->paginate(5);
        // nếu là null thì trả về thông báo không tìm thấy
        if ($reportOrder->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No report found for this user.',
                'data' => []
            ], 404);
        }else{
            $ReportWithOrder = $reportOrder->map(function($reportOrder) {
                $order = Order::where('_id', $reportOrder['id_order'])->first();
                if ($order) {
                    $reportOrder->quantity = $order['quality'];
                    $reportOrder->total = $order['total_price'];
                } else {
                    $reportOrder->quantity = 1;
                    $reportOrder->total = 1;
                }

                return $reportOrder;
            });
        }

        if ($reportOrder->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No orders found for the user.',
                'data' => []
            ], 404);
        }

        // Return the paginated orders and pagination info
        return response()->json([
            'success' => true,
            'message' => 'Report retrieved successfully.',
            'data' => $ReportWithOrder,  // The actual order data
            'pagination' => [
                'total' => $reportOrder->total(),
                'per_page' => $reportOrder->perPage(),
                'current_page' => $reportOrder->currentPage(),
                'last_page' => $reportOrder->lastPage(),
                'next_page_url' => $reportOrder->nextPageUrl(),
                'prev_page_url' => $reportOrder->previousPageUrl(),
            ]
        ], 200);
    }

    public function getReportByOrderCode($order_code){
        $reportOrder = ReportOrder::where('order_code', $order_code)->first();
        return $reportOrder;
    }
    public function searchReport(Request $request, $id)
    {
        $query = ReportOrder::query()
            ->where('id_seller', $id);

// Thêm điều kiện `order_code` nếu tồn tại
        if (!is_null($request->order_code)) {
            $query->where('order_code', 'like', '%' . $request->order_code . '%');
        }

//
// Thêm điều kiện `name_order` nếu tồn tại
        if (!is_null($request->name_order)) {
            $query->where('name_user', 'like', '%' . $request->name_order . '%');
        }
//
//        var_dump($request->status);
//        if (!is_null($request->status)) {
//            $query->where('status', $request->status);
//        }

        // Get paginated results
        $reports = $query->orderBy('created_at', 'desc')
            ->paginate($request->page ?? 5);

        if ($reports->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No report found for this user.',
                'data' => []
            ], 404);
        }

        // Add order details to each report
        $reportsWithOrders = $reports->through(function($report) {
            $order = Order::find($report->id_order);
            if ($order) {
                $report->quantity = $order->quality;
                $report->total = $order->total_price;
            }
            return $report;
        });

        return response()->json([
            'success' => true,
            'message' => 'Reports retrieved successfully.',
            'data' => $reportsWithOrders,
            'pagination' => [
                'total' => $reports->total(),
                'per_page' => $reports->perPage(),
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'next_page_url' => $reports->nextPageUrl(),
                'prev_page_url' => $reports->previousPageUrl(),
            ]
        ]);
    }
    //    public function VerifyReport($id,Request $request) {
//        try {
//            $report = ReportOrder::where('order_code' ,$id)->first();
//            $status = $request['status'];
//            $reason_seller = $request['reason_seller'];
//            $send = $request['send'];
//            if ($status == "accept") {
//                $report['status'] = 1; //Đã xử lý report
//                $report->save();
//                return $this->sendResponse($report, 'Report update successful.');
//            }elseif ($status == "notAccept") {
//                $order = Order::find($report['id_order']);
//
//                $report['status'] = 0; //Hủy bỏ report chỉ có user được hủy
//                $report->save();
//                $order['status'] = 0;
//                $order['is_report'] = 1;
//                $order->save();
//
//                $this->sendCancelReportNotification($report);
//                return $this->sendResponse($report, 'Report update successful.');
//            }elseif ($status == "dispute") {
//                $order = Order::find($report['id_order']);
//                $order['status'] = 2;
//                $order->save();
//                $report['status'] = 3; //Tranh chấp chuyển sang cho
//                $report['reason_seller'] = $reason_seller;
//                $report->save();
//                if ($send == true) {
//                    $this->sendDisputeMessages($order, $report);
//                }
//                return $this->sendResponse($report, 'Report update successful.');
//            }elseif ($status == "Refund") {
//                $report['status'] = 4; // Hoàn tiền
//                $is_win = $request['is_win'];
//                $admin_reason = $request['admin_reason'];
//                $report['is_win'] = $is_win;
//                $report['admin_reason'] = $admin_reason;
//                $report->save();
//                $order = Order::find($report['id_order']);
//                $order['status'] = 3;
//                $order->save();
//
//                if ($this->processRefund($order)) {
//                    $this->sendRefundNotification($order);
//                }
//                if ($is_win != null) {
//                    if ($this->processDisputeRefund($order, $is_win)) {
//                        $this->sendDisputeResultNotification($order, $report);
//                    }
//                }
//                return $this->sendResponse($report, 'Report update successful.');
//            }
//        }catch (\Exception $e) {
//            return $this->sendError('An error has occurred. Please try again later', [], 400);
//        }
//    }

    public function VerifyReport($id, Request $request)
    {
        try {
            $admin = auth()->user();
            $report = ReportOrder::where('order_code', $id)->first();
            $status = $request['status'];
            $reason_seller = $request['reason_seller'];
            $send = $request['send'];

            if ($status == "accept") {
                $report['status'] = 1;
                $report->save();

                ActivityLog::create([
                    'supporter_id' => $admin['_id'],
                    'action' => 'verify_report',
                    'description' => sprintf(
                        "Xác nhận chấp nhận báo cáo đơn hàng #%s của user %s",
                        $report['order_code'],
                        $report->user->name ?? 'không xác định'
                    ),
                    'target_id' => $report['user_id'], // ID của user tạo báo cáo
                    'is_success' => true
                ]);

                return $this->sendResponse($report, 'Report update successful.');

            } elseif ($status == "notAccept") {
                $order = Order::find($report['id_order']);
                $report['status'] = 0; // Hủy bỏ report
                $report->save();
                $order['status'] = 0;
                $order['is_report'] = 1;
                $order->save();

                ActivityLog::create([
                    'supporter_id' => $admin['_id'],
                    'action' => 'verify_report',
                    'description' => sprintf(
                        "Từ chối báo cáo đơn hàng #%s của user %s",
                        $report['order_code'],
                        $report->user->name ?? 'không xác định'
                    ),
                    'target_id' => $report['user_id'], // ID của user tạo báo cáo
                    'is_success' => true
                ]);

                $this->sendCancelReportNotification($report);
                return $this->sendResponse($report, 'Report update successful.');

            } elseif ($status == "dispute") {
                $order = Order::find($report['id_order']);
                $order['status'] = 2;
                $order->save();
                $report['status'] = 3; // Tranh chấp
                $report['reason_seller'] = $reason_seller;
                // thời gian hiện tại
                $report['seller_dispute_time'] = Carbon::now()->format('Y-m-d H:i:s');
                $report->save();

                ActivityLog::create([
                    'supporter_id' => $admin['_id'],
                    'action' => 'verify_report',
                    'description' => sprintf(
                        "Chuyển báo cáo đơn hàng #%s sang trạng thái tranh chấp. Lý do người bán: %s",
                        $report['order_code'],
                        $reason_seller ?? 'không có'
                    ),
                    'target_id' => $report['user_id'], // ID của user tạo báo cáo
                    'is_success' => true
                ]);

                if ($send == true) {
                    $this->sendDisputeMessages($order, $report);
                }
                return $this->sendResponse($report, 'Report update successful.');

            } elseif ($status == "Refund") {
                $report['status'] = 4; // Hoàn tiền
                $is_win = $request['is_win'];
                $admin_reason = $request['admin_reason'];
                $report['is_win'] = $is_win;
                $report['admin_reason'] = $admin_reason;
                $report->save();
                $order = Order::find($report['id_order']);
                $order['status'] = 3;
                $order->save();

                ActivityLog::create([
                    'supporter_id' => $admin['_id'],
                    'action' => 'verify_report',
                    'description' => sprintf(
                        "Hoàn tiền đơn hàng #%s. %s%s",
                        $report['order_code'],
                        $is_win !== null ? ($is_win ? "Người thắng: Người mua. " : "Người thắng: Người bán. ") : "",
                        $admin_reason ? "Lý do: " . $admin_reason : ""
                    ),
                    'target_id' => $report['user_id'], // ID của user tạo báo cáo
                    'is_success' => true
                ]);

//                if ($this->processRefund($order) && $is_win === null) {
//                    $this->sendRefundNotification($order);
//                }
                if ($is_win != null) {
                    if ($this->processDisputeRefund($order, $is_win)) {
                        $this->sendDisputeResultNotification($order, $report);
                    }
                }else{
                    if ($this->processRefund($order)) {
                        $this->sendDisputeResultNotification($order, $report);
                    }
                }
                return $this->sendResponse($report, 'Report update successful.');
            }

        } catch (\Exception $e) {
            ActivityLog::create([
                'supporter_id' => $admin['_id'],
                'action' => 'verify_report_error',
                'description' => sprintf(
                    "Lỗi xử lý báo cáo đơn hàng #%s: %s",
                    $id,
                    $e->getMessage()
                ),
                'target_id' => $report['user_id'] ?? null, // ID của user tạo báo cáo nếu có
                'is_success' => false
            ]);
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }
    public function GetOrderReport($code) {
        $reportOrder = ReportOrder::where('order_code', $code)->first();
        return $reportOrder;
    }
    public function getReportAdmin(Request $request){
        $perPage = $request->get('per_page', 20);
        $currentPage = $request->get('page', 1);
        $reportOrder = ReportOrder::where('status', 3)->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $currentPage);

        $ReportWithOrder = $reportOrder->map(function($reportOrder) {
            // Tìm order dựa vào id_order
            $order = Order::where('_id', $reportOrder['id_order'])->first();

            // Kiểm tra nếu order tồn tại
            if ($order) {
                $reportOrder->quantity = $order['quality'];
                $reportOrder->total = $order['total_price'];
                $reportOrder->name_seller = $order['name_seller'];
                $kiot_sub_id = $order['kiosk_sub_id'];
                $kiot_sub = KiotSub::find($kiot_sub_id);
                $reportOrder->name_product = $kiot_sub['name'];
            } else {
                // Nếu order không tồn tại, thiết lập giá trị mặc định
                $reportOrder->quantity = null;
                $reportOrder->total = null;
            }
            return $reportOrder;
        });
        if ($reportOrder->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No orders found for the user.',
                'data' => []
            ], 404);
        }

        $response = [
            'data' => $ReportWithOrder,
            'meta' => [
                'total' => $reportOrder->total(),
                'per_page' => $reportOrder->perPage(),
                'current_page' => $reportOrder->currentPage(),
                'last_page' => $reportOrder->lastPage(),
                'next_page_url' => $reportOrder->nextPageUrl(),
                'prev_page_url' => $reportOrder->previousPageUrl(),
            ]
        ];
        return $this->sendResponse($response, 'Report retrieved successfully.');
    }

    public function getAllReportAdmin(Request $request){
        $perPage = $request->get('per_page', 20);
        $currentPage = $request->get('page', 1);

        $reportOrder = ReportOrder::orderBy('updated_at', 'desc')->paginate($perPage, ['*'], 'page', $currentPage);
        $ReportWithOrder = $reportOrder->map(function($reportOrder) {
            // Tìm order dựa vào id_order
            $order = Order::where('_id', $reportOrder['id_order'])->first();

            // Kiểm tra nếu order tồn tại
            if ($order) {
                $reportOrder->quantity = $order['quality'];
                $reportOrder->total = $order['total_price'];
                $reportOrder->name_seller = $order['name_seller'];
                $kiot_sub_id = $order['kiosk_sub_id'];
                $kiot_sub = KiotSub::find($kiot_sub_id);
                $reportOrder->name_product = $kiot_sub['name'];
            } else {
                // Nếu order không tồn tại, thiết lập giá trị mặc định
                $reportOrder->quantity = null;
                $reportOrder->total = null;
            }
            return $reportOrder;
        });
        if ($reportOrder->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No orders found for the user.',
                'data' => []
            ], 404);
        }

        $response = [
            'data' => $ReportWithOrder,
            'meta' => [
                'total' => $reportOrder->total(),
                'per_page' => $reportOrder->perPage(),
                'current_page' => $reportOrder->currentPage(),
                'last_page' => $reportOrder->lastPage(),
                'next_page_url' => $reportOrder->nextPageUrl(),
                'prev_page_url' => $reportOrder->previousPageUrl(),
            ]
        ];
        return $this->sendResponse($response, 'Report retrieved successfully.');
    }

//    gửi tin nhắn khiếu nại :

//      case 1: user gửi khiếu nại
    private function sendReportNotificationToSeller($reportOrder)
    {
        try {
            $user_sys = User::where('role', 5)->first();
            $id_system_bot = $user_sys['_id'];

            // Tạo nội dung tin nhắn
            $message = $this->formatReportMessage($reportOrder);

            // Tìm conversation với seller
            $conversation = Conversation::where(function($query) use ($id_system_bot, $reportOrder) {
                $query->where('id_user1', $id_system_bot)
                    ->where('id_user2', $reportOrder['id_seller']);
            })->first();

            // Tạo conversation mới nếu chưa có
            if (!$conversation) {
                $conversation = Conversation::create([
                    'id_user1' => $id_system_bot,
                    'id_user2' => $reportOrder['id_seller'],
                    'last_mess' => $message,
                    'last_mess_id' => $id_system_bot
                ]);
            }

            // Tạo tin nhắn mới
            Messages::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $id_system_bot,
                'message' => $message,
                'status' => 0
            ]);

            // Cập nhật conversation
            $conversation->update([
                'last_mess' => $message,
                'last_mess_id' => $id_system_bot,
                'updated_at' => now()
            ]);

        } catch (\Exception $e) {
            \Log::error('Error sending report notification: ' . $e->getMessage());
        }
    }
    private function formatReportMessage($reportOrder) {
        $time = Carbon::parse($reportOrder['created_at'])->format('H:i:s d/m/Y');

        return implode(' ', [
            "⚠️ COMPLAINT NOTICE ⚠️",
            "",
            "Time: {$time}",
            ". Order code: {$reportOrder['order_code']}",
            ". Complainant: {$reportOrder['name_user']}",
            ". Reason for complaint: {$reportOrder['reason']}",
            ". Contact information: {$reportOrder['info_report']}",
            "",
            ". Please check and address the complaint as soon as possible!"
        ]);
    }


    // case 2: user huỷ khiếu nại

    private function sendCancelReportNotification($report)
    {
        try {
            $user_sys = User::where('role', 5)->first();
            $id_system_bot = $user_sys['_id'];

            // Tạo nội dung tin nhắn
            $message = $this->formatCancelReportMessage($report);

            // Tìm conversation với seller
            $conversation = Conversation::where(function($query) use ($id_system_bot, $report) {
                $query->where('id_user1', $id_system_bot)
                    ->where('id_user2', $report['id_seller']);
            })->first();

            // Tạo conversation mới nếu chưa có
            if (!$conversation) {
                $conversation = Conversation::create([
                    'id_user1' => $id_system_bot,
                    'id_user2' => $report['id_seller'],
                    'last_mess' => $message,
                    'last_mess_id' => $id_system_bot
                ]);
            }

            // Tạo tin nhắn mới
            Messages::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $id_system_bot,
                'message' => $message,
                'status' => 0
            ]);

            // Cập nhật conversation
            $conversation->update([
                'last_mess' => $message,
                'last_mess_id' => $id_system_bot,
                'updated_at' => now()
            ]);

        } catch (\Exception $e) {
            \Log::error('Error sending cancel report notification: ' . $e->getMessage());
        }
    }
    private function formatCancelReportMessage($report) {
        $time = Carbon::now()->format('H:i:s d/m/Y');

        return implode(' ', [
            "📢 COMPLAINT CANCELLATION NOTICE 📢",
            "",
            ". Time: {$time}",
            ". Order code: {$report['order_code']}",
            ". Complainant: {$report['name_user']}",
            ". Status: Complaint has been canceled",
            "",
            ". The order has been restored to its normal status."
        ]);
    }


//     case 3 : khi seller hoàn tiền
    private function processRefund($order)
    {
        try {
            $admin = User::where('role', 4)->first();
            if (!$admin || !$admin->_id) {
                \Log::error("Admin role 4 not found for order: {$order->order_code}");
                return false;
            }

            $adminBalance = Balance::where('user_id', $admin->_id)->first();
            $userBalance = Balance::where('user_id', $order->user_id)->first();

            if (!$adminBalance || !$userBalance) {
                \Log::error("Balance not found for order: {$order->order_code}");
                return false;
            }

            $total_price = (int)$order->total_price;

            // Trừ tiền từ admin
            $lastAdminBalance = (int)$adminBalance->balance;
            $adminBalance->balance = $lastAdminBalance - $total_price;
            $adminBalance->save();

            balance_log::create([
                'id_balance' => $adminBalance->_id,
                'user_id' => $admin->_id,
                'action_user' => "Release refund payment for order " . $order->order_code,
                'last_balance' => $lastAdminBalance,
                'transaction_status' => 'refund',
                'current_balance' => $adminBalance->balance,
                'balance' => $total_price,
                'status' => 3
            ]);

            // Chuyển tiền cho user
            $lastUserBalance = (int)$userBalance->balance;
            $userBalance->balance = $lastUserBalance + $total_price;
            $userBalance->save();

            balance_log::create([
                'id_balance' => $userBalance->_id,
                'user_id' => $order->user_id,
                'action_user' => "Receive refund payment for order " . $order->order_code,
                'transaction_status' => 'receive refund',
                'last_balance' => $lastUserBalance,
                'current_balance' => $userBalance->balance,
                'balance' => $total_price,
                'status' => 3
            ]);

            return true;

        } catch (\Exception $e) {
            \Log::error("Refund processing error: " . $e->getMessage());
            return false;
        }
    }

    private function sendRefundNotification($order)
    {
        try {
            $user_sys = User::where('role', 5)->first();
            $id_system_bot = $user_sys['_id'];

            // Tạo nội dung tin nhắn
            $message = $this->formatRefundMessage($order);

            // Tìm conversation với user
            $conversation = Conversation::where(function($query) use ($id_system_bot, $order) {
                $query->where('id_user1', $id_system_bot)
                    ->where('id_user2', $order->user_id);
            })->first();

            // Tạo conversation mới nếu chưa có
            if (!$conversation) {
                $conversation = Conversation::create([
                    'id_user1' => $id_system_bot,
                    'id_user2' => $order->user_id,
                    'last_mess' => $message,
                    'last_mess_id' => $id_system_bot
                ]);
            }

            // Tạo tin nhắn mới
            Messages::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $id_system_bot,
                'message' => $message,
                'status' => 0
            ]);

            // Cập nhật conversation
            $conversation->update([
                'last_mess' => $message,
                'last_mess_id' => $id_system_bot,
                'updated_at' => now()
            ]);

        } catch (\Exception $e) {
            \Log::error('Error sending refund notification: ' . $e->getMessage());
        }
    }

    private function formatRefundMessage($order) {
        $time = Carbon::now()->format('H:i:s d/m/Y');
        $formattedAmount = number_format($order->total_price, 0, ',', '.') . 'USDT';

        return implode(' ', [
            "💰 REFUND NOTICE 💰",
            "",
            ". Time: {$time}",
            ". Order code: {$order->order_code}",
            ". Refund amount: {$formattedAmount}",
            ". Status: Refund successful",
            "",
            ". The amount has been transferred to your wallet.",
            ". Please check your balance and contact us if you have any questions!"
        ]);
    }


    // case 4 : tranh chấp :
    private function sendDisputeMessages($order, $report): void
    {
        try {
            $seller = User::find($order['id_seller']);
            $admin = User::where('role', 4)->first();


            // Gửi tin nhắn cho user
            $this->sendMessageToUser(
                $order['id_seller'],
                $order['user_id'],
                $this->formatDisputeMessageToUser($order, $seller, $report)
            );

//            // Gửi tin nhắn cho admin
//            $this->sendMessageToUser(
//                $order['id_seller'],
//                $admin['_id'],
//                $this->formatDisputeMessageToAdmin($order, $report, $seller)
//            );

        } catch (\Exception $e) {
            Log::error('Error sending dispute messages: ' . $e->getMessage());
        }
    }

    private function formatDisputeMessageToUser($order, $seller, $report) {
        $time = Carbon::now()->format('H:i:s d/m/Y');

        return implode(' ', [
            "🔔 DISPUTE RESPONSE",
            "",
            "Time: {$time}",
            "Order code: {$order['order_code']}",
            "Seller: {$seller['name']}",
            "Seller's response: {$report['reason_seller']}",
            "",
            "Your dispute has been forwarded to the handling department.",
            "We will continue to monitor and respond as soon as possible!"
        ]);
    }

    private function formatDisputeMessageToAdmin($order, $report, $seller) {
        $time = Carbon::now()->format('H:i:s d/m/Y');
        $formattedAmount = number_format($order['total_price'], 0, ',', '.') . 'USDT';

        return implode(' ', [
            "⚠️ DISPUTE HANDLING REQUEST ⚠️",
            "",
            "Time: {$time}",
            "Order code: {$order['order_code']}",
            "DISPUTE INFORMATION:",
            "- Order value: {$formattedAmount}",
            "- Reason from customer: {$report['reason']}",
            "- Complaint details: {$report['info_report']}",
            "SELLER INFORMATION:",
            "- Name: {$seller['name']}",
            "- ID: {$seller['_id']}",
            "- Response: {$report['reason_seller']}",
            "",
            "Please check and handle this dispute!"
        ]);
    }

    private function sendMessageToUser($fromId, $toId, $message): void
    {
        $conversation = Conversation::where(function($query) use ($fromId, $toId) {
            $query->where(function($q) use ($fromId, $toId) {
                $q->where('id_user1', $fromId)
                    ->where('id_user2', $toId);
            })
                ->orWhere(function($q) use ($fromId, $toId) {
                    $q->where('id_user1', $toId)
                        ->where('id_user2', $fromId);
                });
        })->first();

        if (!$conversation) {
            $conversation = Conversation::create([
                'id_user1' => $fromId,
                'id_user2' => $toId,
                'last_mess' => $message,
                'last_mess_id' => $fromId
            ]);
        }

        Messages::create([
            'conversation_id' => $conversation['_id'],
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

    // case 4.2: admin xử lí khiếu nại

    private function processDisputeRefund($order, $winner): bool
    {
        try {
            $admin = User::where('role', 4)->first();
            if (!$admin?->_id) {
                Log::error("Admin not found for order: {$order['order_code']}");
                return false;
            }

            $adminBalance = Balance::where('user_id', $admin['_id'])->first();
            $userBalance = Balance::where('user_id', $order['user_id'])->first();
            $sellerBalance = Balance::where('user_id', $order['id_seller'])->first();

            if (!$adminBalance || !$userBalance || !$sellerBalance) {
                Log::error("Balance not found for order: {$order['order_code']}");
                return false;
            }

            $total_price = (int)$order['total_price'];
            $platformFee = (int)$order['admin_amount'];

            // Trừ tiền từ admin
            $lastAdminBalance = (int)$adminBalance['balance'];
            $adminBalance['balance'] = $lastAdminBalance - $total_price;
            $adminBalance->save();

            balance_log::create([
                'id_balance' => $adminBalance['_id'],
                'user_id' => $admin['_id'],
                'action_user' => "Dispute refund release for order " . $order['order_code'],
                'transaction_status' => 'dispute refund',
                'last_balance' => $lastAdminBalance,
                'current_balance' => $adminBalance['balance'],
                'balance' => $total_price,
                'status' => 3
            ]);

            // Chuyển tiền cho người thắng
            if ($winner === 'user') {
                $lastWinnerBalance = (int)$userBalance['balance'];
                $userBalance['balance'] = $lastWinnerBalance + $total_price;
                $userBalance->save();

                balance_log::create([
                    'id_balance' => $userBalance['_id'],
                    'user_id' => $order['user_id'],
                    'action_user' => "Dispute win refund for order " . $order['order_code'],
                    'transaction_status' => 'dispute win refund',
                    'last_balance' => $lastWinnerBalance,
                    'current_balance' => $userBalance['balance'],
                    'balance' => $total_price,
                    'status' => 3
                ]);
            } else {
                // Cộng phí sàn cho admin trước
                $lastAdminBalance = (int)$adminBalance['balance'];
                $adminBalance['balance'] = $lastAdminBalance + $platformFee;
                $adminBalance->save();

                balance_log::create([
                    'id_balance' => $adminBalance['_id'],
                    'user_id' => $admin['_id'],
                    'action_user' => "Platform fee for dispute win order " . $order['order_code'],
                    'transaction_status' => 'fee',
                    'last_balance' => $lastAdminBalance,
                    'current_balance' => $adminBalance['balance'],
                    'balance' => $platformFee,
                    'status' => 3
                ]);

                // Xử lý reseller nếu có và seller thắng
                $remainingAmount = $total_price - $platformFee;  // Số tiền sau khi trừ phí sàn

                if ($order['ref_user_id'] && $order['name_ref'] && $order['reseller_amount']) {
                    try {
                        $resellerBalance = Balance::where('user_id', $order['ref_user_id'])->first();
                        if ($resellerBalance) {
                            $resellerAmount = (int)$order['reseller_amount'];
                            // Đảm bảo reseller_amount không vượt quá remainingAmount
                            $resellerAmount = min($resellerAmount, $remainingAmount);

                            $lastResellerBalance = (int)$resellerBalance['balance'];
                            $resellerBalance['balance'] = $lastResellerBalance + $resellerAmount;
                            $resellerBalance->save();

                            balance_log::create([
                                'id_balance' => $resellerBalance['_id'],
                                'user_id' => $order['ref_user_id'],
                                'action_user' => "Dispute win reseller commission (after platform fee) for order " . $order['order_code'] . " (Reseller: " . $order['name_ref'] . ")",
                                'transaction_status' => 'reseller_commission',
                                'last_balance' => $lastResellerBalance,
                                'current_balance' => $resellerBalance['balance'],
                                'balance' => $resellerAmount,
                                'status' => 3
                            ]);

                            // Số tiền còn lại cho seller
                            $sellerAmount = $remainingAmount - $resellerAmount;
                        } else {
                            // Nếu không tìm thấy reseller balance, seller nhận toàn bộ
                            $sellerAmount = $remainingAmount;
                        }
                    } catch (\Exception $e) {
                        Log::error('Dispute win reseller commission processing failed', [
                            'order_code' => $order['order_code'],
                            'ref_user_id' => $order['ref_user_id'],
                            'name_ref' => $order['name_ref'],
                            'error' => $e->getMessage()
                        ]);
                        // Nếu có lỗi xử lý reseller, seller nhận toàn bộ
                        $sellerAmount = $remainingAmount;
                    }
                } else {
                    // Không có reseller, seller nhận toàn bộ
                    $sellerAmount = $remainingAmount;
                }

                // Chuyển tiền cho seller

                // nếu có refund_rating thì hoàn tiền đánh giá cho user
                if ($order['refund_money'] != null) {
                    $lastUserBalance = (int)$userBalance['balance'];
                    $userBalance['balance'] = $lastUserBalance + $order['refund_money'];
                    $userBalance->save();

                    balance_log::create([
                        'id_balance' => $userBalance['_id'],
                        'user_id' => $order['user_id'],
                        'action_user' => "Refund rating for order " . $order['order_code'],
                        'transaction_status' => 'refund rating',
                        'last_balance' => $lastUserBalance,
                        'current_balance' => $userBalance['balance'],
                        'balance' => $order['refund_money'],
                        'status' => 3
                    ]);
                }

                $lastWinnerBalance = (int)$sellerBalance['balance'];
                $sellerBalance['balance'] = $lastWinnerBalance + $sellerAmount;
                $sellerBalance->save();

                $actionDescription = $order['ref_user_id']
                    ? "Dispute win refund (after platform fee and reseller commission) for order "
                    : "Dispute win refund (after platform fee) for order ";

                balance_log::create([
                    'id_balance' => $sellerBalance['_id'],
                    'user_id' => $order['id_seller'],
                    'action_user' => $actionDescription . $order['order_code'],
                    'transaction_status' => 'dispute win refund',
                    'last_balance' => $lastWinnerBalance,
                    'current_balance' => $sellerBalance['balance'],
                    'balance' => $sellerAmount,
                    'status' => 3
                ]);
            }

            return true;
        } catch (\Exception $e) {
            Log::error("Refund error: " . $e->getMessage());
            return false;
        }
}
private function sendDisputeResultNotification($order, $report): void {
        try {
            $user_sys = User::where('role', 5)->first();
            if (!$user_sys?->_id) {
                Log::error("System bot not found");
                return;
            }

            $messages = [
                'user' => [
                    'win' => [
                        'status' => '✅ You won this dispute.',
                        'money' => '💰 The amount has been refunded to your wallet.'
                    ],
                    'lose' => [
                        'status' => '❌ Your dispute was not accepted.',
                        'money' => '💡 The amount will be transferred to the seller.'
                    ]
                ],
                'seller' => [
                    'win' => [
                        'status' => '✅ You won this dispute.',
                        'money' => '💰 The amount has been transferred to your wallet.'
                    ],
                    'lose' => [
                        'status' => '❌ The dispute was accepted.',
                        'money' => '💡 The amount will be refunded to the customer.'
                    ]
                ]
            ];

            $time = Carbon::now()->format('H:i:s d/m/Y');
            $formattedAmount = number_format($order['total_price'], 0, ',', '.') . ' đ';
            $winner = $report['is_win'];

            foreach (['user' => $order['user_id'], 'seller' => $order['id_seller']] as $type => $recipient) {
                $result = $messages[$type][$winner === $type ? 'win' : 'lose'];
                $message = implode("\n", [
                    "⚖️ DISPUTE RESOLUTION RESULT ⚖️",
                    "",
                    "Time: {$time}",
                    "Order code: {$order['order_code']}",
                    "Amount: {$formattedAmount}",
                    "RESULT:",
                    $result['status'],
                    $result['money'],
                    "REASON:",
                    $report['admin_reason'],
                    "",
                    "If you need further assistance, please contact us."
                ]);
                $this->sendMessageToUser($user_sys['_id'], $recipient, $message);
            }

        } catch (\Exception $e) {
            Log::error('Error sending dispute result notification: ' . $e->getMessage());
        }
    }
}
