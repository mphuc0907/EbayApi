<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\ApiController as ApiController;
use App\Models\OrderDetail;
use Illuminate\Http\Request;

class OrderDetailController extends ApiController
{
    public function GetOrderDetailByOrderCode(Request $request)
    {
        try {
            $orderCode = $request->order_code;
            $orderDetail = OrderDetail::where('order_code', $orderCode)->get();
            return $this->sendResponse($orderDetail, 'Get order detail success');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }
}
