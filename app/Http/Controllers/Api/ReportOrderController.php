<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ReportOrder;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\ApiController as ApiController;

class ReportOrderController extends ApiController
{
    public function Add(Request $request) {

        try {
            if (!empty(auth()->user())) {
                $user = auth()->user();
                $data['user_create'] = $user['_id'];
            }

            // Check if the order_code already has a report
            $existingReport = ReportOrder::where('order_code', $request['order_code'])->first();
            if ($existingReport) {
                return $this->sendError('This order has already been reported.', [], 400);
            }

            $data['id_user'] = $user['_id'];
            $data['name_user'] = $user['name'];
            $data['order_code'] = $request['order_code'];
            $data['id_order'] = $request['id_order'];
            $data['id_seller'] = $request['id_seller'];
            $data['info_report'] = $request['info_report'];
            $data['id_kiot'] = $request['id_kiot'];
            $data['reason'] = $request['reason'];
            $data['status'] = 0;

            $reportOrder = ReportOrder::create($data);

            $order = Order::find($reportOrder['id_order']);
            $order['is_report'] = 1;
            $order['status'] = 0;
            $order->save();

            return $this->sendResponse($reportOrder, 'User report successfully.');

        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    public function getSeller($id){
        $reportOrder = ReportOrder::where('id_seller',  $id)->where('status', '!=', 3)->orderBy('created_at', 'desc')->paginate(5);

        $ReportWithOrder = $reportOrder->map(function($reportOrder) {
            $order = Order::where('_id', $reportOrder['id_order'])->first();

            $reportOrder->quantity = $order['quality'];
            $reportOrder->total = $order['total_price'];
            return $reportOrder;
        });
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

    public function SearchReport($id)
    {
        // Lấy các giá trị từ query parameters
        $order_code = $_GET['order_code'] ?? null;
        $name_order = $_GET['name_user'] ?? null;
        $status = $_GET['status'] ?? null;

        // Lấy giá trị page từ query parameters
        $page = request()->query('page', 1);

        // Tạo query cơ bản
        $reportOrderQuery = ReportOrder::where('id_seller', $id)->where('status', 0);

        // Thêm điều kiện lọc theo order_code nếu có
        if ($order_code) {
            $reportOrderQuery->where('order_code', 'like', '%' . $order_code . '%');
        }

        // Thêm điều kiện lọc theo name_order nếu có
        if ($name_order) {
            $reportOrderQuery->where('name_user', 'like', '%' . $name_order . '%');
        }

        // Thêm điều kiện lọc theo status nếu có
        if ($status) {
            $reportOrderQuery->where('status', $status);
        }

        // Phân trang
        $reportOrders = $reportOrderQuery->orderBy('created_at', 'desc')->paginate(5, ['*'], 'page', $page);

        // Kiểm tra nếu không có báo cáo nào được tìm thấy
        if ($reportOrders->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No report found for this user.',
                'data' => []
            ], 404);
        }

        // Lấy dữ liệu đơn hàng và thêm thông tin chi tiết vào từng báo cáo
        $ReportWithOrder = $reportOrders->map(function($reportOrder) {
            $order = Order::where('_id', $reportOrder['id_order'])->first();
            if ($order) {
                $reportOrder->quantity = $order['quality'];
                $reportOrder->total = $order['total_price'];
            }
            return $reportOrder;
        });

        // Trả về kết quả JSON với dữ liệu báo cáo và thông tin phân trang
        return response()->json([
            'success' => true,
            'message' => 'Reports retrieved successfully.',
            'data' => $ReportWithOrder,
            'pagination' => [
                'total' => $reportOrders->total(),
                'per_page' => $reportOrders->perPage(),
                'current_page' => $reportOrders->currentPage(),
                'last_page' => $reportOrders->lastPage(),
                'next_page_url' => $reportOrders->nextPageUrl(),
                'prev_page_url' => $reportOrders->previousPageUrl(),
            ]
        ], 200);
    }

    public function VerifyReport($id,Request $request) {
        try {
            $report = ReportOrder::where('order_code' ,$id)->first();
            $status = $request['status'];

            if ($status == "accept") {
                $report['status'] = 1; //Đã xử lý report
                $report->save();
                return $this->sendResponse($report, 'Report update successful.');
            }elseif ($status == "notAccept") {
                $order = Order::find($report['id_order']);
                $report['status'] = 2; //Hủy bỏ report chỉ có user được hủy
                $report->save();
                $order['is_report'] = 0;
                $order->save();
                return $this->sendResponse($report, 'Report update successful.');
            }elseif ($status == "dispute") {
                $report['status'] = 3; //Tranh chấp chuyển sang cho
                $report->save();
                return $this->sendResponse($report, 'Report update successful.');
            }elseif ($status == "Refund") {
                $report['status'] = 4; // Hoàn tiền
                $report->save();
                return $this->sendResponse($report, 'Report update successful.');
            }
        }catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    public function GetOrderReport($code) {
        $reportOrder = ReportOrder::where('order_code', $code)->first();
        return $reportOrder;
    }
    public function getReportAdmin(){
        $reportOrder = ReportOrder::where('status', 3)->orderBy('created_at', 'desc')->get();

        $ReportWithOrder = $reportOrder->map(function($reportOrder) {
            $order = Order::where('_id', $reportOrder['id_order'])->first();

            $reportOrder->quantity = $order['quality'];
            $reportOrder->total = $order['total_price'];
            return $reportOrder;
        });
        if ($reportOrder->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No orders found for the user.',
                'data' => []
            ], 404);
        }

        // Return the paginated orders and pagination info
       return $ReportWithOrder;
    }
}
