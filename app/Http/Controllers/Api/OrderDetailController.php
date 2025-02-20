<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\ApiController as ApiController;
use App\Models\OrderDetail;
use App\Models\User;
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
    public function GetProductAPI() {
        $orderId = $_GET['orderId'];
        $userToken = $_GET['userToken'];
        $user = User::where('token_buy_Api', $userToken)->first();
        if (empty($user)) {
            $false['success'] = 'false';
            $false['description'] = 'User does not exist';
            return $false;
        }
        $orderDetail = OrderDetail::where('order_code', $orderId)->where('user_id', $user['_id'])->first();
        $data = json_decode($orderDetail['value'], true);
        $newData = array();

// Loop through the original data
        foreach ($data as $item) {
            // Push the 'value' into the new array with key 'product'
            $newData[] = array('product' => $item['value']);
        }
// Encode the new array back into JSON format
        $newJson = json_encode($newData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        if (!empty($orderDetail)) {
            $success['success'] = 'true';
            $success['data'] = $newJson;
            return $success;
        }else {
            $false['success'] = 'false';
            $false['description'] = 'Order does not exist';
            return $false;
        }
    }
}
