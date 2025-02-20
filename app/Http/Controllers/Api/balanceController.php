<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BalanceRequest;
use App\Http\Controllers\Api\ApiController as ApiController;
use App\Models\balance;
use App\Models\balance_log;
use App\Models\User;
use Illuminate\Http\Request;

class balanceController extends ApiController
{
    public function GetBalance($id)
    {
        try {
            $balance = balance::find($id);
            if (!$balance) {
                return $this->sendError('not found', [], 404);
            }
            return $this->sendResponse($balance, 'Get balance successfully');
        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    public function getBalanceByUserId()
    {
        $user = auth()->user();
        $balance = balance::where('user_id', $user['_id'])->get();
        if (!$balance) {
            return $this->sendError('not found', [], 404);
        }
        return $this->sendResponse($balance, 'Get balance successfully');
    }

    public function getBalanceByUser($user_id)
    {

        $balance = balance::where('user_id', $user_id)->get();
        if (!$balance) {
            return $this->sendError('not found', [], 404);
        }
        return $this->sendResponse($balance, 'Get balance successfully');
    }

    public function addBalance(BalanceRequest $request)
    {

        try {
            $user = auth()->user();
            $data['id_wallet'] = $request['id_wallet'];
            $data['topup'] = $request['topup'];
            $data['balance'] = $request['topup'];
            $data['user_id'] = $user['_id'];
            $balance = balance::create($data);

            //Lưu log
            $data_request['id_balance'] = $balance['_id'];
            $data_request['user_id'] = $user['_id'];
            $data_request['action_user'] = "The balance has been added" . $request['topup'];
            $data_request['last_balance'] = $balance['balance'];
            $data_request['topup'] = $request['topup'];
            $data_request['current_balance'] = $balance['balance'];
            $data_request['balance'] = $request['topup'];
            $data_request['transaction_status'] = "recharge";

            $balance_log = balance_log::create($data_request);
            // Chuẩn bị dữ liệu phản hồi
            $success['expires_at'] = now()->addDays(3);
            $success['data_value'] = $data_request;

            // Trả về phản hồi thành công
            return $this->sendResponse($success, 'Deposited money into wallet successfully');

        } catch (\Exception $e) {
            // Trả về phản hồi lỗi
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    public function rechargeBalance($id, Request $request)
    {
        try {
            $balance = balance::find($id);
            $data['balance'] = $balance['balance'] + $request['topup'];
            $data['topup'] = $balance['topup'] + $request['topup'];

            $balance->update($data);
            // Kiểm tra nếu không tìm thấy đối tượng

            //Lưu log
            $user = auth()->user();
            $data_request['id_balance'] = $balance['_id'];
            $data_request['user_id'] = $user['_id'];
            $data_request['action_user'] = "The balance has been added" . $request['topup'];
            $data_request['transaction_status'] = "recharge";
            $data_request['last_balance'] = $balance['balance'];
            $data_request['current_balance'] = $balance['balance'];
            $data_request['balance'] = $request['topup'];

            $balance_log = balance_log::create($data_request);
            if (!$balance) {
                return $this->sendError('not found', [], 404);
            }
            $success['expires_at'] = now()->addDays(3);
            $success['data_value'] = $data_request;
            // Trả về phản hồi thành công
            return $this->sendResponse($success, 'Deposited money into wallet successfully');
        }catch (\Exception $e) {
            // Trả về phản hồi lỗi
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    public function rechargeBalanceErr(Request $request)
    {
        try {
            $user = User::where('name', $request['name'])->first();
            $balance = balance::where('user_id', $user['_id'])->first();
            $data['balance'] = $balance['balance'] + $request['topup'];
            $data['topup'] = $balance['topup'] + $request['topup'];

            $balance->update($data);
            // Kiểm tra nếu không tìm thấy đối tượng

            //Lưu log
            $user = auth()->user();
            $data_request['id_balance'] = $balance['_id'];
            $data_request['user_id'] = $balance['user_id'];
            $data_request['action_user'] = "The balance has been added payment error" . $request['topup'];
            $data_request['transaction_status'] = "recharge";
            $data_request['last_balance'] = $balance['balance'];
            $data_request['current_balance'] = $balance['balance'] + $request['topup'];
            $data_request['balance'] = $request['topup'] ;
            $data_request['status'] = '3';

            $balance_log = balance_log::create($data_request);
            if (!$balance) {
                return $this->sendError('not found', [], 404);
            }
            $success['expires_at'] = now()->addDays(3);
            $success['data_value'] = $data_request;
            // Trả về phản hồi thành công
            return $this->sendResponse($success, 'Deposited money into wallet successfully');
        }catch (\Exception $e) {
            // Trả về phản hồi lỗi
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    public function withdrawmoneyBalance($id, Request $request)
    {
        try {
            $balance = balance::find($id);
            if ($request['topup'] > $balance['balance']) {
                return $this->sendError('Insufficient balance', [], 400);
            }
            $data['balance'] = $balance['balance'] - $request['topup'];
            $data['topup'] = $balance['topup'] - $request['topup'];

            $balance->update($data);
            // Kiểm tra nếu không tìm thấy đối tượng

            //Lưu log
            $user = auth()->user();
            $data_request['id_balance'] = $balance['_id'];
            $data_request['user_id'] = $user['_id'];
            $data_request['transaction_status'] = "withdraw";
            $data_request['action_user'] = "Additional deducted balance" . $request['topup'];
            $data_request['last_balance'] = $balance['balance'];
            $data_request['current_balance'] = $balance['balance'] - $request['topup'];
            $data_request['balance'] = $request['topup'];

            $balance_log = balance_log::create($data_request);
            if (!$balance) {
                return $this->sendError('not found', [], 404);
            }
            $success['expires_at'] = now()->addDays(3);
            $success['data_value'] = $data_request;
            // Trả về phản hồi thành công
            return $this->sendResponse($success, 'withdraw money into wallet successfully');
        }catch (\Exception $e) {
            // Trả về phản hồi lỗi
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }
}
