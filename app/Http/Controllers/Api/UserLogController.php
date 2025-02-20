<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\User_log;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\ApiController as ApiController;

class UserLogController extends ApiController
{
    public function GetLogUser(){
        $user = auth()->user();
        $orders = User_log::where('user_id', $user['_id'])->orderBy('created_at', 'desc')->get();
        return $orders;
    }
    public function GetUser() {
        $userlog = User_log::orderBy('updated_at', 'desc')->get();
        $arruser = [];
        foreach ($userlog as $u) {
            $user = User::find($u['user_id']);

            // Kiểm tra nếu $user không phải là null
            if ($user) {
                $arruser[] = [
                    'ip' => $u['ip'],
                    'id_user' => $u['user_id'],
                    'created_at' => $u['created_at'],
                    'account' => $user['name'],
                    'telegram' => $user['telegram_username'],
                    'phone' => $user['phone'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'bank_name' => $user['bank_name'],
                    'facebook' => $user['facebook'],
                    'penalty_tax' => $user['penalty_tax'],
                ];
            }
        }
        return $arruser;
    }

    public function SearchLog(Request $request) {
        $ip = $request['ipuser'];
        $acc = $request['account'];

        // Tạo query cho User_log
        $query = User_log::query();

        // Nếu có IP, thêm điều kiện tìm kiếm theo IP từ User_log
        if ($ip) {
            $query->where('ip', 'like', "%{$ip}%");
        }

        // Nếu có account, thực hiện join với bảng User để tìm kiếm theo account (name)
        if ($acc) {
            $query->whereHas('user', function ($q) use ($acc) {
                $q->where('name', 'like', "%{$acc}%");
            });
        }

        // Lấy dữ liệu user_log đã lọc theo IP và account, sắp xếp theo updated_at
        $userlog = $query->orderBy('updated_at', 'desc')->get();

        $arruser = [];
        foreach ($userlog as $u) {
            // Sử dụng mối quan hệ user() để lấy thông tin người dùng
            $user = $u->user;

            if ($user) {
                $arruser[] = [
                    'ip' => $u['ip'],
                    'id_user' => $u['user_id'],
                    'created_at' => $u['created_at'],
                    'account' => $user['name'],
                    'telegram' => $user['telegram_username'],
                    'phone' => $user['phone'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'bank_name' => $user['bank_name'],
                    'facebook' => $user['facebook'],
                    'penalty_tax' => $user['penalty_tax'],
                ];
            }
        }

        return response()->json([
            'success' => 1,
            'message' => 'Orders retrieved successfully.',
            'data' => $arruser,
        ], 200);
    }

    public function GetUserByID() {
        $ip = $_GET['ipuser'];
        $acc = $_GET['iduser'];

        // Tạo query cho User_log
        $query = User_log::query();

        // Nếu có IP, thêm điều kiện tìm kiếm theo IP từ User_log
        if ($ip) {
            $query->where('ip', 'like', "%{$ip}%");
        }

        // Nếu có account, thực hiện join với bảng User để tìm kiếm theo account (name)
        if ($acc) {
            $query->where('user_id', 'like', "%{$acc}%");
        }

        // Lấy dữ liệu user_log đã lọc theo IP và account, sắp xếp theo updated_at
        $userlog = $query->orderBy('updated_at', 'desc')->get();

        $arruser = [];
        foreach ($userlog as $u) {
            // Sử dụng mối quan hệ user() để lấy thông tin người dùng
            $user = $u->user;

            if ($user) {
                $arruser[] = [
                    'ip' => $u['ip'],
                    'id_user' => $u['user_id'],
                    'created_at' => $u['created_at'],
                    'account' => $user['name'],
                    'telegram' => $user['telegram_username'],
                    'phone' => $user['phone'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'bank_name' => $user['bank_name'],
                    'facebook' => $user['facebook'],
                    'penalty_tax' => $user['penalty_tax'],
                ];
            }
        }

        return response()->json([
            'success' => 1,
            'message' => 'Orders retrieved successfully.',
            'data' => $arruser,
        ], 200);
    }

}
