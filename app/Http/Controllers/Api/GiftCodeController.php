<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\balance;
use App\Models\balance_log;
use App\Models\GiftCode;
use App\Models\User;
use Illuminate\Http\Request;

class   GiftCodeController extends ApiController
{
    /**
     * Mục đích: Admin tạo mã + hoặc - tiền cho cả user và shop
     * Shop, user nhập khi nạp tiền-> Thêm 1 phần nhập Gifcode
     * Check hệ thống chỉ cho dùng 1 lần/ 1 user, shop
     *
     * 3. Admin:
     *
     * Hiển thị danh sách tất cả gilf code quản trị đã tạo. Thông tin gilf code gồm: STT, Ngày tạo, Người tạo, Mã, Loại, Số tiền, Người dùng, Tài khoản, Trạng thái (Đã sử dụng, Chưa sử dụng), Ghi chú
     * Tìm kiếm theo mã
     * Tạo mã gilf code: Loại: Cộng tiền/ Trừ tiền, Số tiền, Ghi chú
 */

    public function getAll(Request $request)
    {
        try {
//            if (!in_array(auth()->user()->role, [4, 7])) {
//                return $this->sendError('Không có quyền truy cập', 403);
//            }

            $query = GiftCode::query();

            if ($request->filled('code')) {
                $query->where('code', 'like', '%' . $request->code . '%');
            }

            $giftcodes = $query->orderBy('created_at', 'desc')
                ->paginate($request->per_page ?? 10);

            $data = [];
            foreach ($giftcodes as $index => $giftcode) {
                $creator = User::find($giftcode->created_by);
                $creatorName = $creator ? $creator->name : 'Unknown';
                $userInfo = 'Chưa sử dụng';
                if ($giftcode->used_by) {
                    $user = User::find($giftcode->used_by);
                    $userInfo = $user ? $user->name : 'Unknown';
                }

                $stt = ($giftcodes->currentPage() - 1) * $giftcodes->perPage() + $index + 1;
                $data[] = [
                    'stt' => $stt,
                    'created_at' => $giftcode->created_at->format('Y-m-d H:i:s'),
                    'creator' => $creatorName,
                    'code' => $giftcode->code,
                    'type' => $giftcode->type == 1 ? 'Cộng tiền' : 'Trừ tiền',
                    'amount' => number_format($giftcode->amount),
                    'used_by' => $userInfo,
                    'account_type' => $giftcode->used_by_type == 1 ? 'Shop' : 'User',
                    'status' => $giftcode->status == 1 ? 'Chưa sử dụng' : 'Đã sử dụng',
                    'note' => $giftcode->note ?? ''
                ];
            }

            return response()->json([
                'data' => $data,
                'pagination' => [
                    'total' => $giftcodes->total(),
                    'per_page' => $giftcodes->perPage(),
                    'current_page' => $giftcodes->currentPage(),
                    'last_page' => $giftcodes->lastPage()
                ]
            ]);

        } catch (\Exception $e) {
            return $this->sendError('Lỗi lấy danh sách gift code: ' . $e->getMessage());
        }
    }

//    tạo mã
    public function createGiftCode(Request $request)
    {
        try {
            $user = auth()->user();
            if (!in_array($user->role, [4, 7])) {
                return $this->sendError('Không có quyền truy cập', 403);
            }

            $request->validate([
                'code' => 'required|string|unique:gift_codes,code|max:100',
                'type' => 'required|in:1,2',
                'amount' => 'required|numeric|min:1',
                'note' => 'nullable|string|max:255',
                'used_by_type' => 'required|in:1,2'
            ]);

            $giftcode = GiftCode::create([
                'code' => strtoupper($request->code),
                'type' => (int)$request->type,
                'amount' => (int)$request->amount,
                'status' => 1, // Chưa sử dụng
                'used_by_type' => (int)$request->used_by_type,
                'created_by' => $user['_id'],
                'note' => $request->note
            ]);

            // Log tạo gift code
            if ($user['role'] == 6 || $user['role'] == 7) {
                $type_text = $request->type == 1 ? 'Số tiền' : 'Phần trăm';
                $used_by_type_text = $request->used_by_type == 1 ? 'Chỉ sử dụng một lần' : 'Sử dụng nhiều lần';

                ActivityLog::create([
                    'supporter_id' => $user['_id'],
                    'action' => 'create_giftcode',
                    'description' => sprintf(
                        "Tạo gift code %s: %s %s, %s%s. %s",
                        $giftcode['code'],
                        $type_text,
                        number_format($giftcode['amount']),
                        $request->type == 2 ? '%' : '',
                        $used_by_type_text,
                        $request->note ? "(Ghi chú: {$request->note})" : ""
                    ),
                    'target_id' => $giftcode['_id'],
                    'is_success' => true
                ]);
            }

            return $this->sendResponse($giftcode, 'Tạo gift code thành công');

        } catch (\Exception $e) {
            if ($user['role'] == 6 || $user['role'] == 7) {
                ActivityLog::create([
                    'supporter_id' => $user['_id'],
                    'action' => 'create_giftcode_error',
                    'description' => "Lỗi tạo gift code: " . $e->getMessage(),
                    'target_id' => null,
                    'is_success' => false
                ]);
            }
            return $this->sendError('Lỗi tạo gift code: ' . $e->getMessage());
        }
    }
    public function useGiftCode(Request $request)
    {
        try {
            $request->validate([
                'code' => 'required'
            ]);
            $user = auth()->user();


            // Check gift code tồn tại và chưa sử dụng
            $giftcode = GiftCode::where('code', $request->code)
                ->where('status', 1)
                ->first();

            if (!$giftcode) {
                return $this->sendError('Gift code is invalid or has been used');
            }

            $userType = $user['role'] == 2 ? 1 : 2;

            if ($giftcode->used_by_type != $userType) {
                return $this->sendError('Gift codes are not for your account type');
            }

            $type = $giftcode->type;
            $amount = $giftcode->amount;
            if ($type == 2) {
                $amount_neg = $amount * -1;
            }else{
                $amount_neg = $amount;
            }

            // cộng t
            $user_balance = Balance::where('user_id', $user['_id'])->first();
            if (!$user_balance) {
                $data['id_wallet'] = '0xb32bj2342l3n';
                $data['topup'] = $giftcode->amount;
                $data['balance'] = $giftcode->amount;
                $data['user_id'] = $user['_id'];
                $balance = balance::create($data);
                $last_user_balance = (int)$balance->balance;
//                $balance->balance = $last_user_balance + $amount_neg;
//                $user_balance->save();

                balance_log::create([
                    'id_balance' => $balance->_id,
                    'user_id' => $user['_id'],
                    'action_user' => "Gift code " . $giftcode->code,
                    'transaction_status' => 'gift_code',
                    'last_balance' => $last_user_balance,
                    'current_balance' => $balance->balance,
                    'balance' => $giftcode->amount,
                    'status' => '3'
                ]);
            }else {
                $last_user_balance = (int)$user_balance->balance;
                $user_balance->balance = $last_user_balance + $amount_neg;
                $user_balance->save();

                balance_log::create([
                    'id_balance' => $user_balance->_id,
                    'user_id' => $user['_id'],
                    'action_user' => "Gift code " . $giftcode->code,
                    'transaction_status' => 'gift_code',
                    'last_balance' => $last_user_balance,
                    'current_balance' => $user_balance->balance,
                    'balance' => $giftcode->amount,
                    'status' => '3'
                ]);

            }

            // Cập nhật gift code
            $giftcode->update([
                'status' => 2,
                'used_by' => $user['_id'],
                'used_at' => now()
            ]);
            return $this->sendResponse($giftcode, 'Successful use of gift codes');

        } catch (\Exception $e) {
            return $this->sendError('Error using gift code: ' . $e->getMessage());
        }
    }

}
