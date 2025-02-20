<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\ApiController as ApiController;
use App\Models\ActivityLog;
use App\Models\balance;
use App\Models\balance_log;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class balance_logController extends ApiController
{
    public function getBalanceLogByUserId()
    {
        try {
            $user = auth()->user();
            $page = request()->get('page', 1);
            $balance_log = balance_log::where('user_id', $user['_id'])->orderBy('created_at', 'desc')
                ->paginate(20, ['*'], 'page', $page);
            if ($balance_log->isEmpty()) {
                return $this->sendError('No data found', [], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Balance Log retrieved successfully.',
                'data' => $balance_log->items(),
                'pagination' => [
                    'total' => $balance_log->total(),
                    'per_page' => $balance_log->perPage(),
                    'current_page' => $balance_log->currentPage(),
                    'last_page' => $balance_log->lastPage(),
                    'next_page_url' => $balance_log->nextPageUrl(),
                    'prev_page_url' => $balance_log->previousPageUrl(),
                ]
            ], 200);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    public function getBalanceWidthDraw()
    {
        try {
            $user = auth()->user();
            $balance_log = balance_log::where('user_id', $user['_id'])
                ->where('transaction_status', 'withdraw')
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            if ($balance_log->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No data found',
                    'data' => []
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Balance Log retrieved successfully.',
                'data' => $balance_log->items(),
                'pagination' => [
                    'total' => $balance_log->total(),
                    'per_page' => $balance_log->perPage(),
                    'current_page' => $balance_log->currentPage(),
                    'last_page' => $balance_log->lastPage(),
                    'next_page_url' => $balance_log->nextPageUrl(),
                    'prev_page_url' => $balance_log->previousPageUrl(),
                ]
            ], 200);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    public function getBalanceLog(Request $request)
    {
        $perPage = $request->get('per_page', 20);
        $currentPage = $request->get('page', 1);

        $balance_log = balance_log::orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $currentPage);

        $response = [
            'data' => $balance_log->items(),
            'meta' => [
                'total' => $balance_log->total(),
                'per_page' => $balance_log->perPage(),
                'current_page' => $balance_log->currentPage(),
                'last_page' => $balance_log->lastPage(),
                'next_page_url' => $balance_log->nextPageUrl(),
                'prev_page_url' => $balance_log->previousPageUrl(),
            ]
        ];

        return $this->sendResponse($response, 'Balance Log retrieved successfully.');
    }

    public function searchBalanceLogByUserName(Request $request)
    {
        $perPage = $request->get('per_page', 20);
        $currentPage = $request->get('page', 1);
        $search = $request->get('username');
        $role = $request->get('role');
        $type_transaction = $request->get('type_transaction');
        $start_date = $request->get('start_date');
        $end_date = $request->get('end_date');
        $status = $request->get('status');

        // Xử lý status
        if($status === null) {
            $status = 0;
        } else {
            $status = intval($status);
        }

        $balanceLogs = balance_log::query()
            ->when($search, function ($query) use ($search) {
                $user = User::where('name', 'like', '%' . $search . '%')->first();
                if (!$user) {
                    return $query;
                }
                return $query->where('user_id', $user['_id']);
            })
            ->when($role, function ($query) use ($role) {
                $userIds = User::where('role', intval($role))->pluck('_id')->toArray();
                return $query->whereIn('user_id', $userIds);
            })
            ->when($type_transaction, function ($query) use ($type_transaction) {
                return $query->where('transaction_status', $type_transaction);
            })
            ->when($start_date && $end_date, function ($query) use ($start_date, $end_date) {
                $start_date = Carbon::parse($start_date)->startOfDay();
                $end_date = Carbon::parse($end_date)->endOfDay();
                return $query->whereBetween('created_at', [$start_date, $end_date]);
            })
            ->where('status', $status)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $currentPage);

        $response = [
            'data' => $balanceLogs->items(),
            'meta' => [
                'total' => $balanceLogs->total(),
                'per_page' => $balanceLogs->perPage(),
                'current_page' => $balanceLogs->currentPage(),
                'last_page' => $balanceLogs->lastPage(),
                'next_page_url' => $balanceLogs->nextPageUrl(),
                'prev_page_url' => $balanceLogs->previousPageUrl(),
            ]
        ];

        return $this->sendResponse($response, 'Balance Log retrieved successfully.');
    }
    public function send_request(Request $request)
    {
        try {
            $user = auth()->user();
            $balance = balance::where('user_id', $user['_id'])->first();
            //Chuyển tiền qua ví

            //Kết thúc
            $data['id_balance'] = $balance['_id'];
            $data['action_user'] = "The balance has been added" . " " . $request['topup'];
            $data['id_wallet'] = $balance['id_wallet'];
            $data['transaction_status'] = "recharge";
            $data['last_balance'] = $balance['balance'];
            $data['current_balance'] = $balance['balance'] + $request['topup'];
            $data['balance'] = $request['topup'];
            $data['user_id'] = $user['_id'];
            $data['status'] = 0;
            $balance_log = balance_log::create($data);

            $success['expires_at'] = now()->addDays(3);
            $success['data_value'] = $balance_log;

            // Trả về phản hồi thành công
            return $this->sendResponse($success, 'Deposited money into wallet successfully');

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    public function RequestErrorPayment(Request $request) {
        try {
            $user = auth()->user();
            $userRequest = User::where('name', $request['name'])->first();

            $balance =  balance::where('user_id', $userRequest['_id'])->first();
            $data['id_balance'] = $balance['_id'];
            $data['action_user'] =  $user['name'] . " is creating an error deposit request for " . $userRequest['name'] . " ​​with an amount of" . " " . $request['topup'];
            $data['id_wallet'] = $balance['id_wallet'];
            $data['transaction_status'] = "paymentErr";
            $data['balance'] = $request['topup'];
            $data['last_balance'] = $balance['balance'];
            $data['current_balance'] = $balance['balance'] + $request['topup'];
            $data['user_id'] = $userRequest['_id'];
            $data['status'] = 0;
            $balance_log = balance_log::create($data);
            $success['expires_at'] = now()->addDays(3);
            $success['data_value'] = $balance_log;

            // Trả về phản hồi thành công
            return $this->sendResponse($success, 'Deposited money into wallet successfully');
        }catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    public function send_withdrawmoney(Request $request)
    {
        try {
            $user = auth()->user();
            $balance = balance::where('user_id', $user['_id'])->first();

            $data['id_balance'] = $balance['_id'];
            $data['action_user'] = "Additional deducted balance" . " " . $request['topup'];
            $data['id_wallet'] = $balance['id_wallet'];
            $data['transaction_status'] = "withdraw";
            $data['balance'] = $request['topup'];
            $data['last_balance'] = $balance['balance'];
            $data['current_balance'] = $balance['balance'] - $request['topup'];
            $data['user_id'] = $user['_id'];
            $data['status'] = 0;
            $balance_log = balance_log::create($data);

            if ($request['topup'] > $balance['balance']) {
                return $this->sendError('Insufficient balance', [], 400);
            }
            $databalance['balance'] = $balance['balance'] - $request['topup'];
            $databalance['topup'] = $balance['topup'] - $request['topup'];

            $balance->update($databalance);
            $success['expires_at'] = now()->addDays(3);
            $success['data_value'] = $balance_log;

            // Trả về phản hồi thành công
            return $this->sendResponse($success, 'Deposited money into wallet successfully');

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    public function VerifyStatus($id, Request $request)
    {
        try {
            $user = auth()->user();
            $userRole = $user['role'];

            if ($userRole != 4 && $userRole != 7) {
                return $this->sendError('Unauthorized', [], 403);
            }

            $admin_balance = balance::where('user_id', $user['_id'])->first();
            $balance_log = balance_log::find($id);
            $balance_log['status'] = 1;
            $balance_log['admin_verify'] = $user['name'];
            $balance_log['role_verify'] = $user['role'];
            $balance_log['approval_reason'] = $request['approval_reason'];
            $balance_log->save();

            $balance = balance::find($balance_log['id_balance']);
            $user_target_id = $balance_log['user_id'];
            $user_target = User::find($user_target_id);
            $name_user_target = $user_target['name'];

            if ($request['transaction'] == 'recharge') {
                $balance['balance'] = $balance['balance'] + $balance_log['balance'];
                $balance['topup'] = $balance['topup'] + $balance_log['balance'];
                $balance->save();

                if (!$balance) {
                    return $this->sendError('not found', [], 404);
                }

                if ($admin_balance['balance'] < $balance_log['balance']) {
                    return $this->sendError('Insufficient balance in admin wallet', [], 400);
                }
                $admin_balance['balance'] = $admin_balance['balance'] - $balance_log['balance'];
                $admin_balance->save();

                $data['id_balance'] = $admin_balance['_id'];
                $data['action_user'] = "Approved recharge for user " . $name_user_target;
                $data['id_wallet'] = $admin_balance['id_wallet'];
                $data['transaction_status'] = "approved recharge";
                $data['last_balance'] = $admin_balance['balance'];
                $data['current_balance'] = $admin_balance['balance'] - $balance_log['balance'];
                $data['balance'] = $balance_log['balance'];
                $data['user_id'] = $user['_id'];
                $data['status'] = 3;
                $balance_log = balance_log::create($data);

                ActivityLog::create([
                    'supporter_id' => $user['_id'],
                    'action' => 'verify_recharge',
                    'description' => "Xác nhận nạp tiền cho user {$name_user_target}: " .
                        ($request['approval_reason'] ? "(Ghi chú: {$request['approval_reason']})" : ""),
                    'target_id' => $balance['_id'],
                    'is_success' => true
                ]);

                $success['expires_at'] = now()->addDays(3);
                $success['data_value'] = $balance_log;

                return $this->sendResponse($success, 'Deposited money into wallet successfully');

            } elseif ($request['transaction'] == 'withdraw') {
                $balance['balance'] = $balance['balance'] - $balance_log['topup'];
                $balance->save();

                if (!$balance) {
                    return $this->sendError('not found', [], 404);
                }

                ActivityLog::create([
                    'supporter_id' => $user['_id'],
                    'action' => 'verify_withdraw',
                    'description' => "Xác nhận rút tiền cho user {$name_user_target}: " .
                        ($request['approval_reason'] ? "(Ghi chú: {$request['approval_reason']})" : ""),
                    'target_id' => $balance['_id'],
                    'is_success' => true
                ]);

                $success['expires_at'] = now()->addDays(3);
                $success['data_value'] = $balance_log;

                return $this->sendResponse($success, 'Withdraw money into wallet successfully');
            }

        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }


    public function custody_money()
    {
        $user = auth()->user();
        $balance_log = balance_log::where('user_id', $user['_id'])->where('status', 0)->get();

        // Tính tổng của trường 'topup'
        $total_topup = $balance_log->sum('topup');

        // Trả về cả balance_log và tổng của topup
        $data = [
            'balance_log' => $balance_log,
            'total_topup' => $total_topup,
        ];

        return $this->sendResponse($data, 'Balance Log and total topup retrieved successfully.');
    }

    public function getRevenueByUser()
    {
        $user = auth()->user();

        // Tính tổng topup của tháng hiện tại với điều kiện transaction_status là recharge
        $currentMonthTopup = balance_log::where('user_id', $user['_id'])
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->where('transaction_status', 'recharge')
            ->sum('topup') ?? 0;  // Nếu null thì trả về 0

        // Tính tổng topup của tháng trước với điều kiện transaction_status là recharge
        $lastMonthTopup = balance_log::where('user_id', $user['_id'])
            ->whereMonth('created_at', Carbon::now()->subMonth()->month)
            ->whereYear('created_at', Carbon::now()->subMonth()->year)
            ->where('transaction_status', 'recharge')
            ->sum('topup') ?? 0;  // Nếu null thì trả về 0

        // Tạo dữ liệu trả về
        $success['expires_at'] = now()->addDays(3);
        $success['currentMonthTopup'] = $currentMonthTopup; // Tổng topup của tháng hiện tại
        $success['lastMonthTopup'] = $lastMonthTopup;       // Tổng topup của tháng trước

        // Trả về kết quả
        return $this->sendResponse($success, 'Revenue Dashboard');
    }


    public function getDailyTopupPercentage()
    {
        $user = auth()->user();

        // Lấy dữ liệu topup từ 30 ngày trước đến hiện tại, với điều kiện transaction_status là recharge
        $dailyTopups = balance_log::where('user_id', $user['_id'])
            ->where('transaction_status', 'recharge')
            ->where('created_at', '>=', Carbon::now()->subDays(30)) // Lấy dữ liệu từ 30 ngày trước
            ->get(['created_at', 'topup']); // Lấy giá trị topup để xử lý bên ngoài

        // Tính tổng topup cho mỗi ngày
        $groupedTopups = [];
        foreach ($dailyTopups as $topup) {
            $date = Carbon::parse($topup->created_at)->toDateString(); // Chuyển đổi sang định dạng ngày
            if (!isset($groupedTopups[$date])) {
                $groupedTopups[$date] = 0; // Khởi tạo giá trị cho ngày mới
            }
            $groupedTopups[$date] += $topup->topup ?? 0; // Nếu topup null thì cộng 0
        }

        // Tính tổng topup cho toàn bộ khoảng thời gian
        $totalTopupForPeriod = array_sum($groupedTopups);

        // Tạo mảng chứa dữ liệu tỷ lệ phần trăm cho mỗi ngày
        $dailyTopupPercentages = [];
        foreach ($groupedTopups as $date => $total_topup) {
            $dailyTopupPercentages[] = [
                'date' => $date,
                'topup' => $total_topup,
                'percentage' => $totalTopupForPeriod > 0 ? round(($total_topup / $totalTopupForPeriod) * 100, 2) : 0
            ];
        }

        // Trả về kết quả
        $success['expires_at'] = now()->addDays(3);
        $success['dailyTopupPercentages'] = $dailyTopupPercentages;

        return $this->sendResponse($success, 'Daily Topup Percentages for the Last 30 Days');
    }


    // 30 giao dịch gần nhất (nạp tiền và rút tiền)


    public function getRecentBalanceLog(Request $request)
    {
        try {
            $user = auth()->user();
            $userRole = $user['role'];

            if ($userRole != 4 && $userRole != 7) {
                return $this->sendError('Unauthorized', [], 403);
            }
            //withdraw
            //recharge
            $transactionType = $request->get('transaction_status', 'recharge');
            // nếu type = admin_transfer
            if ($transactionType == 'admin_transfer') {
               // tìm giao dịch có admin_transfer == true
                $balanceLog = balance_log::orderBy('created_at', 'desc')
                    ->where('transaction_status', 'admịn transfer support')
                    ->where('status', 3)
                    ->get();
            }else{
                $balanceLog = balance_log::orderBy('created_at', 'desc')
                    ->where('transaction_status', $transactionType)
                    ->where('status', 1)
                    ->where('role_verify', 7)
                    ->limit(30)
                    ->get();
            }
            if ($balanceLog->isEmpty()) {
                return $this->sendError('No data found', [], 404);
            }
            $response = [
                'data' => $balanceLog
            ];

            return $this->sendResponse($response, 'Recent Balance Log retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }
    public function transferMoneySupporter(Request $request)
    {
        try {
            $user = auth()->user();
            if ($user['role'] != 4) {
                return $this->sendError('Unauthorized', [], 403);
            }

            $receiver = User::find($request['receiver_id']);
            if (!$receiver || $receiver['role'] != 7) {
                return $this->sendError('Invalid receiver. Must be a user with role 7.', [], 400);
            }

            // Lấy số dư người gửi
            $sender_balance = balance::where('user_id', $user['_id'])->first();
            if (!$sender_balance) {
                return $this->sendError('Sender balance not found', [], 404);
            }

            // Lấy số dư người nhận
            $receiver_balance = balance::where('user_id', $receiver['_id'])->first();
            if (!$receiver_balance) {
                return $this->sendError('Receiver balance not found', [], 404);
            }

            // Kiểm tra số dư
            if ($request['amount'] > $sender_balance['balance']) {
                return $this->sendError('Insufficient balance', [], 400);
            }

            // Tạo log cho người gửi
            $sender_log_data = [
                'id_balance' => $sender_balance['_id'],
                'action_user' => "Transfer to " . $receiver['name'],
                'id_wallet' => $sender_balance['id_wallet'],
                'transaction_status' => "admịn transfer support",
                'last_balance' => $sender_balance['balance'],
                'current_balance' => $sender_balance['balance'] - $request['amount'],
                'balance' => $request['amount'],
                'user_id' => $user['_id'],
                'admin_transfer' => 'true',
                'status' => 3
            ];
            // update name người nhận vào log của người gửi
            $sender_log_data['receiver_name'] = $receiver['name'];

            // Tạo log cho người nhận
            $receiver_log_data = [
                'id_balance' => $receiver_balance['_id'],
                'action_user' => "Received from " . $user['name'],
                'id_wallet' => $receiver_balance['id_wallet'],
                'transaction_status' => "receive",
                'last_balance' => $receiver_balance['balance'],
                'current_balance' => $receiver_balance['balance'] + $request['amount'],
                'balance' => $request['amount'],
                'user_id' => $receiver['_id'],
                'status' => 3
            ];

            // Cập nhật số dư người gửi
            $sender_balance_data['balance'] = $sender_balance['balance'] - $request['amount'];
            $sender_balance->update($sender_balance_data);

            // Cập nhật số dư người nhận
            $receiver_balance_data['balance'] = $receiver_balance['balance'] + $request['amount'];
            $receiver_balance->update($receiver_balance_data);

            // Tạo log giao dịch
            $sender_log = balance_log::create($sender_log_data);
            // update tên người nhân vào log của người gửi
            $sender_log->update(['receiver_name' => $receiver['name']]);

            $receiver_log = balance_log::create($receiver_log_data);

            $success['expires_at'] = now()->addDays(3);
            $success['data_value'] = [
                'sender_log' => $sender_log,
                'receiver_log' => $receiver_log
            ];

            return $this->sendResponse($success, 'Money transferred successfully');

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

}
