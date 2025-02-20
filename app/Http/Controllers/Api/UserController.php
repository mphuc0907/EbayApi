<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ApiController as ApiController;
use App\Models\ActivityLog;
use App\Models\balance;
use App\Models\Conversation;
use App\Models\Kiot;
use App\Models\Messages;
use App\Models\Order;
use App\Models\User;
use App\Models\PasswordResetToken;
use App\Models\User_log;
use App\Models\UserPenaltyTaxLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Requests\RegisterFormRequest;
use App\Http\Requests\LoginFormRequest;
use App\Http\Requests\ForgotPasswordFormRequest;
use App\Http\Requests\ResetPasswordFormRequest;
use App\Http\Requests\ChangePasswordFormRequest;
use App\Http\Requests\ChangeInformationFormRequest;
use App\Http\Requests\SaleRegisterFormRequest;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends ApiController
{
    /**
     * Register api
     *
     * @return \Illuminate\Http\Response
     */
    public function register(RegisterFormRequest $request)
    {
        try {
            $data = $request->all();
            $data['password'] = bcrypt($data['password']);
            $data['role'] = config('base.role.normal');
            $data['level'] = '0';
            $data['checkPoint'] = '0';
            $user = User::create($data);
            //return $this->sendResponse($user, 'User register successfully.');
            $success['token'] = $user->createToken($user->email, ['*'], now()->addDays(3))->plainTextToken;
            $success['expires_at'] = now()->addDays(3);
            $success['name'] = $user->name;
            $email = $user->email;
            $name = $user->name;
            // Gửi mail
            Mail::send('auth.welcome', compact('name'), function ($e) use ($email) {
                $e->subject('Registered successfully');
                $e->to($email);
            });

            // tạo ví cho user
            // tạo id ví cho user chưa có
            $balance = balance::where('user_id', $user->_id)->first();

            if (!$balance) {
                $data_balance['id_wallet'] = $this->generateRandomCode();
                $data_balance['topup'] = 0;
                $data_balance['balance'] = 0;
                $data_balance['user_id'] = $user->_id;
                $balance = balance::create($data_balance);
            }
            return $this->sendResponse($success, 'User register successfully.');
        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    /**
     * Login api
     *
     * @return \Illuminate\Http\Response
     */
    public function login(LoginFormRequest $request)
    {
        // Kiểm tra thông tin đăng nhập
        if (Auth::guard('web')->attempt(['email' => $request->email, 'password' => $request->password])) {
            $user = Auth::user();

            if ($user->is_banned == true) {
                return $this->sendError('Your account has been banned. Please contact support for more information.', [], 403);
            }

            // Kiểm tra nếu tài khoản có bật xác thực 2 lớp (Google 2FA)
            if (!empty($user->google2fa_enabled)) {
                $success['google2fa_enabled'] = $user->google2fa_enabled;
            }

            // Tạo token truy cập có thời hạn 3 ngày
            $success['token'] = $user->createToken($user->email, ['*'], now()->addDays(3))->plainTextToken;
            $success['expires_at'] = now()->addDays(3);

            // tạo id ví cho user chưa có
            $balance = balance::where('user_id', $user->_id)->first();

            if (!$balance) {
                $data_balance['id_wallet'] = $this->generateRandomCode();
                $data_balance['topup'] = 0;
                $data_balance['balance'] = 0;
                $data_balance['user_id'] = $user->_id;
                $balance = balance::create($data_balance);
            }

            // Trả về phản hồi thành công
            $response = $this->sendResponse($success, 'User login successfully.');

            // Lưu log thành công
            $this->saveUserLog($user->_id, 'login_success', 'User Login Successful', $request['ipSever']);

            return $response;
        } else {
            // Lưu log khi đăng nhập thất bại
            $this->saveUserLog(null, 'login_failed', 'Login Attempt Failed', $request['ipSever']);

            // Trả về phản hồi lỗi
            return $this->sendError('The provided credentials are incorrect', [], 401);
        }
    }

    /**
     * Hàm lưu log người dùng
     * @param int|null $userId ID người dùng (null nếu chưa đăng nhập thành công)
     * @param string $status Trạng thái (login_success, login_failed)
     * @param string $description Mô tả trạng thái
     */
    protected function saveUserLog($userId, $status, $description, $ipSever)
    {
        // Lấy địa chỉ IP của người dùng
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        // Lấy thông tin User-Agent của người dùng
        $userAgent = $ipSever;

        // Tạo dữ liệu log
        $data_log = [
            'user_id' => $userId,
            'ip' => $ipSever,
            'status' => $status,
            'user_agent' => $userAgent,
            'description' => $description
        ];

        // Lưu log vào cơ sở dữ liệu
        User_log::create($data_log);
    }



    /**
     * Logout api
     *
     * @return \Illuminate\Http\Response
     */
    public function logout()
    {
        auth()->user()->tokens()->delete();
        return $this->sendResponse(null, 'User logout successfully.');
    }

    /**
     * Forgot password api
     *
     * @return \Illuminate\Http\Response
     */
    public function forgotPassword(ForgotPasswordFormRequest $request)
    {
        try {
            $email = $request['email'];
            $user = User::where('email', $email)->first();
            if (!$user) {
                return $this->sendError('Email not found', [], 400);
            }
            $token = Str::random(50);
            $url = config('base.siteurl') . '/reset-password?token=' . $token;
            // Lưu thông tin
            PasswordResetToken::create([
                'email' => $email,
                'token' => $token,
                'created_at' => time()
            ]);

            // Gửi mail với try-catch riêng để bắt lỗi mail
            try {
                Mail::send('auth.forgot_password', compact('url'), function ($e) use ($email) {
                    $e->subject('Forgot password Notification');
                    $e->to($email);
                });

                return $this->sendResponse(null, 'We have sent a reset email to your email. Please check your email and follow the instructions.');
            } catch (\Exception $mailException) {
                throw $mailException;
            }

        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), [], 400);
        }
    }

    /**
     * Reset password api
     *
     * @return \Illuminate\Http\Response
     */
    public function resetPassword(ResetPasswordFormRequest $request)
    {
        try {
            // Kiểm tra email và token có hợp lệ hay không
            $password_reset_token = new PasswordResetToken();
            if ($user_password_reset = $password_reset_token->checkEmailAndToken($request['token'], $request['email'])) {
                // Kiểm tra xem đã hết 5p chưa
                if ($user_password_reset['created_at']->timestamp + 300 < time()) {
                    return $this->sendError('Password change time has expired. Please resubmit request.', [], 400);
                }
                //
                $user = User::where('email', $request['email'])->first();
                $user->forceFill([
                    'password' => Hash::make($request['password'])
                ]);
                $user->save();
                return $this->sendResponse(null, 'Password change successful');
            }
            return $this->sendError('Incorrect information. Please check and try again.', [], 400);
        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    /**
     * Get user information
     *
     * @return \Illuminate\Http\Response
     */
    public function getUser()
    {
        return auth()->user();
    }

    /**
     * Change password
     *
     * @return \Illuminate\Http\Response
     */
    public function changePassword(ChangePasswordFormRequest $request)
    {
        try {
            $user = auth()->user();
            // Kiểm tra mật khẩu hiện tại có đúng hay không
            if (!Hash::check($request['old_password'], $user['password'])) {
                return $this->sendError('Current password is incorrect. Please check and try again.', [], 400);
            }
            $user->forceFill([
                'password' => Hash::make($request['new_password'])
            ]);
            $user->save();
            return $this->sendResponse(null, 'Password change successful');
        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    /**
     * Change information
     *
     * @return \Illuminate\Http\Response
     */
    public function changeInformation(ChangeInformationFormRequest $request)
    {
        try {
            $user = auth()->user();
            $user['fullname'] = $request['fullname'];
            $user->save();
            return $this->sendResponse(null, 'Change information successfully');
        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    public function changeInfoTelegram(Request $request)
    {
        try {
            $user = auth()->user();
            $user['telegram_chat_id'] = $request['telegram_chat_id'];
            $user['telegram_username'] = $request['telegram_username'];
            $user['telegram_fullname'] = $request['telegram_fullname'];
            $user->save();
            return $this->sendResponse(null, 'Change telegram successfully');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), [], 400);
        }
    }
    public function changeInfoBuyApi(Request $request)
    {
        try {
            $user = auth()->user();
            if ($request['buyApi'] == 'onAPI') {
                $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';  // Chuỗi bao gồm cả chữ cái và số
                $maxAttempts = 1000; // Giới hạn số lần thử tạo mã (để tránh vòng lặp vô tận)
                $attempts = 0;  // Biến đếm số lần thử

                do {
                    $randomString = '';
                    $length = 19; // Chiều dài mã ban đầu là 19

                    // Tạo mã ngẫu nhiên
                    for ($i = 0; $i < $length; $i++) {
                        $randomString .= $characters[rand(0, strlen($characters) - 1)];
                    }

                    // Thêm 1 chữ số ngẫu nhiên vào cuối
                    $randomString .= rand(0, 9);

                    // Kiểm tra xem mã đã tồn tại trong cơ sở dữ liệu chưa
                    $orderExists = User::where('token_buy_Api', $randomString)->exists();

                    // Nếu đã hết ký tự có thể tạo ra, tăng chiều dài của mã lên 2 ký tự nữa
                    if ($orderExists) {
                        $length += 2; // Tăng chiều dài mã lên nếu trùng lặp
                    }
                    $attempts++;

                } while ($orderExists && $attempts < $maxAttempts); // Lặp cho đến khi tạo được mã duy nhất hoặc đạt giới hạn

                $user['enabled_Api'] = 1;
                $user['token_buy_Api'] = $randomString;
            }else {
                $user['enabled_Api'] = 0;
                $user['token_buy_Api'] = "";
            }

            $user->save();
            //Lưu log
            if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
                $ip = $_SERVER['HTTP_CLIENT_IP'];
            } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
            } else {
                $ip = $_SERVER['REMOTE_ADDR'];
            }


            return $this->sendResponse(null, 'Change telegram successfully');
        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    /**
     * Đăng ký bán hàng
     *
     * @return \Illuminate\Http\Response
     */
    public function saleRegister(SaleRegisterFormRequest $request)
    {
        try {
            $user = auth()->user();
            $user['phone'] = $request['phone'];
            $user['bank_name'] = $request['bank_name'];
            $user['front_id_card'] = $request['front_id_card'];
            $user['back_id_card'] = $request['back_id_card'];
            $user['role'] = config('base.role.sale');
            $user->save();
            return $this->sendResponse(null, 'Registration successful');
        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    /**
     * Get user by id
     *
     * @return \Illuminate\Http\Response
     */

    public function getUserById($id)
    {
        try {
            $user = User::find($id);
            if ($user) {
                return $this->sendResponse($user, 'Get user successfully');
            }
            return $this->sendError('User not found', [], 404);
        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

// upload avatar
    public function uploadAvatar(Request $request)
    {
        try {
            $user = auth()->user();
            $user['back_id_card'] = $request['avatar'];
            $user->save();
            return $this->sendResponse(null, 'Upload avatar successfully');
        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    public function getUserByName($name)
    {
        try {
            $user = User::where('name', 'like', '%' . $name . '%')->get();
            if ($user) {
                return $this->sendResponse($user, 'Get user successfully');
            }
            return $this->sendError('User not found', [], 404);
        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    public function getSale() {
        $user = User::where('role', 2)->orderBy('created_at', 'desc')->get();

        $arrorder = [];
        foreach ($user as $value) {
            //Tổng số gian hàng
            $kiot = Kiot::where('user_id', $value['_id'])->count();
            $Totalorder = Order::where('id_seller', $value['_id'])->count();
            $Complaintsorder = Order::where('id_seller', $value['_id'])->whereIn('status', [3, 4])->count();
            $percentageComplaints = $Totalorder > 0 ? ($Complaintsorder / $Totalorder) * 100 : 0;

            $order = Order::where('id_seller', $value['_id'])->sum('total_price');
            $orderAdmin = Order::where('id_seller', $value['_id'])->sum('admin_amount');
            $arrorder[] = [
                'name_user' => $value['name'],
                'id_user' => $value['_id'],
                'email' => $value['email'],
                'total_order' => $Totalorder,
                'total_price' => $order,
                'complaints' => $percentageComplaints,
                'admin_amount' => $orderAdmin,
                'total_kiot' => $kiot,
                'created_at' => $value['created_at'],
            ];
        }

        return $arrorder;
    }

    public function updateDataUser(Request $request)
    {
        try {
            $admin = auth()->user();
            $user = User::find($request['id']);
            $oldData = $user->toArray();

            // Update user status (true = locked/banned, false = unlocked/unbanned)
            $user['is_lock_chat'] = $request['is_lock_chat'] ?? ($oldData['is_lock_chat'] ?? false);
            $user['is_banned'] = $request['is_banned'] ?? ($oldData['is_banned'] ?? false);
            $user['is_lock_balance'] = $request['is_lock_balance'] ?? ($oldData['is_lock_balance'] ?? false);
            $user['is_pending_all_kiosk'] = $request['is_pending_all_kiosk'] ?? ($oldData['is_pending_all_kiosk'] ?? false);
            $user['is_lock_all_kiosk'] = $request['is_lock_all_kiosk'] ?? ($oldData['is_lock_all_kiosk'] ?? false);

            // Track status changes for notifications (English)
            $statusChanges = [];

            // Track changes for admin log (Vietnamese)
            $adminLogChanges = [];

            // Check chat status change
            if (($oldData['is_lock_chat'] ?? false) != $user['is_lock_chat']) {
                $statusChanges[] = $user['is_lock_chat'] ?
                    'Your chat has been locked' :
                    'Your chat has been unlocked';
                $adminLogChanges[] = $user['is_lock_chat'] ? 'Khóa chat' : 'Mở chat';
            }

            // Check account ban status change
            if (($oldData['is_banned'] ?? false) != $user['is_banned']) {
                $statusChanges[] = $user['is_banned'] ?
                    'Your account has been banned' :
                    'Your account has been unbanned';
                $adminLogChanges[] = $user['is_banned'] ? 'Khóa tài khoản' : 'Mở tài khoản';
            }

            // Check transaction status change
            if (($oldData['is_lock_balance'] ?? false) != $user['is_lock_balance']) {
                $statusChanges[] = $user['is_lock_balance'] ?
                    'Your transactions have been locked' :
                    'Your transactions have been unlocked';
                $adminLogChanges[] = $user['is_lock_balance'] ? 'Khóa giao dịch' : 'Mở giao dịch';
            }

            // Check kiosk pending status change
            if (($oldData['is_pending_all_kiosk'] ?? false) != $user['is_pending_all_kiosk']) {
                $statusChanges[] = $user['is_pending_all_kiosk'] ?
                    'All your kiosks have been set to pending review' :
                    'All your kiosks have been activated';
            }

            // Check kiosk lock status change
            if (($oldData['is_lock_all_kiosk'] ?? false) != $user['is_lock_all_kiosk']) {
                $statusChanges[] = $user['is_lock_all_kiosk'] ?
                    'All your kiosks have been locked' :
                    'All your kiosks have been unlocked';
            }

            // Log status changes for admin (in Vietnamese)
            if ($admin['role'] == 6 || $admin['role'] == 7 && !empty($adminLogChanges)) {
                ActivityLog::create([
                    'supporter_id' => $admin['_id'],
                    'action' => 'update_user_status',
                    'description' => "Thay đổi trạng thái user {$user['name']}: " . implode(', ', $adminLogChanges),
                    'target_id' => $user['_id'],
                    'is_success' => true,
                ]);
            }

            // Handle pending status for all kiosks
            $old_pending_status = $oldData['is_pending_all_kiosk'] ?? false;
            if ($old_pending_status != $user['is_pending_all_kiosk']) {
                $kiot = Kiot::where('user_id', $request['id'])->get();
                foreach ($kiot as $value) {
                    $value['is_pending'] = $user['is_pending_all_kiosk'];
                    $value['is_active'] = !$user['is_pending_all_kiosk'];
                    $value->update();
                }

                if ($admin['role'] == 6 || $admin['role'] == 7) {
                    ActivityLog::create([
                        'supporter_id' => $admin['_id'],
                        'action' => 'update_kiosk_status',
                        'description' => ($user['is_pending_all_kiosk'] ?
                            "Chuyển tất cả gian hàng của {$user['name']} sang trạng thái chờ duyệt" :
                            "Kích hoạt tất cả gian hàng của {$user['name']}"),
                        'target_id' => $user['_id'],
                        'is_success' => true,
                    ]);
                }
            }

            // Handle lock status for all kiosks
            $old_lock_status = $oldData['is_lock_all_kiosk'] ?? false;
            if ($old_lock_status != $user['is_lock_all_kiosk']) {
                $kiot = Kiot::where('user_id', $request['id'])->get();
                foreach ($kiot as $value) {
                    $value['is_locked'] = $user['is_lock_all_kiosk'];
                    $value['is_active'] = !$user['is_lock_all_kiosk'];
                    $value->update();
                }

                if ($admin['role'] == 6 || $admin['role'] == 7) {
                    ActivityLog::create([
                        'supporter_id' => $admin['_id'],
                        'action' => 'update_kiosk_status',
                        'description' => ($user['is_lock_all_kiosk'] ?
                            "Khóa tất cả gian hàng của {$user['name']}" :
                            "Mở khóa tất cả gian hàng của {$user['name']}"),
                        'target_id' => $user['_id'],
                        'is_success' => true,
                    ]);
                }
            }

            // Send system notifications if there are any status changes
            if (!empty($statusChanges)) {
                try {
                    $user_sys = User::where('role', 5)->first();
                    $id_system_bot = $user_sys['_id'];
                    define('SYSTEM_BOT_ID', $id_system_bot);

                    // Check if conversation exists
                    $conversation = Conversation::where(function ($query) use ($user) {
                        $query->where('id_user1', SYSTEM_BOT_ID)
                            ->where('id_user2', $user->id);
                    })->first();

                    // Create new conversation if it doesn't exist
                    if (!$conversation) {
                        $conversation = Conversation::create([
                            'id_user1' => SYSTEM_BOT_ID,
                            'id_user2' => $user->id,
                            'last_mess' => 'System Notification: ' . implode("\n", $statusChanges),
                            'last_mess_id' => SYSTEM_BOT_ID
                        ]);
                    }

                    // Create notification message
                    $message = "System Notification:\n" . implode("\n", $statusChanges);

                    // Send new message
                    Messages::create([
                        'conversation_id' => $conversation->id,
                        'sender_id' => SYSTEM_BOT_ID,
                        'message' => $message,
                        'status' => 0
                    ]);

                    // Update conversation
                    $conversation->update([
                        'last_mess' => $message,
                        'last_mess_id' => SYSTEM_BOT_ID,
                        'updated_at' => now()
                    ]);

                } catch (\Exception $e) {
                    // Log error but continue with the update process
                    \Log::error('Error sending system messages: ' . $e->getMessage());
                }
            }

            $user->update();
            return $this->sendResponse(null, 'Update user successfully');

        } catch (\Exception $e) {
            if ($admin['role'] == 6 || $admin['role'] == 7) {
                ActivityLog::create([
                    'supporter_id' => $admin['_id'],
                    'action' => 'update_user_error',
                    'description' => "Lỗi cập nhật thông tin user " . ($user['name'] ?? 'không xác định'),
                    'target_id' => $request['id'],
                    'is_success' => false
                ]);
            }
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }
    public function adminGetAllUser(Request $request)
    {
        $perPage = $request->get('per_page', 20);
        $currentPage = $request->get('page', 1);

        $user = User::orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $currentPage);
        $response = [
            'data' => $user->items(),
            'meta' => [
                'total' => $user->total(),
                'per_page' => $user->perPage(),
                'current_page' => $user->currentPage(),
                'last_page' => $user->lastPage(),
                'next_page_url' => $user->nextPageUrl(),
                'prev_page_url' => $user->previousPageUrl(),
            ]
        ];

        return $this->sendResponse($response, 'Balance Log retrieved successfully.');
    }
    public function adminSearchUser(Request $request)
    {
        $perPage = $request->get('per_page', 20);
        $currentPage = $request->get('page', 1);
        $name = $request->get('name');
        $fullname = $request->get('fullname');
        $email = $request->get('email');
        $id_wallet = $request->get('id_wallet');
        $wallet = balance::where('id_wallet', $id_wallet)->first();
        if ($wallet && $id_wallet != null) {
            $id_user = $wallet['user_id'];
        }else{
            $id_user = null;
        }
        $user = User::query()
            ->when($name, function ($query, $name) {
                return $query->where('name', 'like', '%' . $name . '%');
            })
            ->when($fullname, function ($query, $fullname) {
                return $query->where('fullname', 'like', '%' . $fullname . '%');
            })
            ->when($email, function ($query, $email) {
                return $query->where('email', 'like', '%' . $email . '%');
            })
            ->when($id_user, function ($query, $id_user) {
                return $query->where('_id', $id_user);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $currentPage);

        $response = [
            'data' => $user->items(),
            'meta' => [
                'total' => $user->total(),
                'per_page' => $user->perPage(),
                'current_page' => $user->currentPage(),
                'last_page' => $user->lastPage(),
                'next_page_url' => $user->nextPageUrl(),
                'prev_page_url' => $user->previousPageUrl(),
            ]
        ];

        return $this->sendResponse($response, 'Balance Log retrieved successfully.');
    }


    // check online
    const OFFLINE_AFTER_SECONDS = 300;

    public function getUserStatus($userId)
    {
        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'error' => 'User not found'
            ], 404);
        }

        $lastActivity = Carbon::parse($user->last_activity);
        $timeDiff = $lastActivity->diffInSeconds(Carbon::now());

        if ($timeDiff > self::OFFLINE_AFTER_SECONDS) {
            $user->is_online = false;
            $user->save();
        }

        return response()->json([
            'user_id' => $user->_id,
            'is_online' => $user->is_online,
            'last_activity' => $user->last_activity,
            'offline_duration' => $user->is_online ? 0 : $this->formatOfflineDuration($timeDiff)
        ]);
    }
    private function formatOfflineDuration($seconds)
    {
        if ($seconds < 60) {
            return $seconds . ' seconds';
        } elseif ($seconds < 3600) {
            return floor($seconds / 60) . ' minutes';
        } elseif ($seconds < 86400) {
            return floor($seconds / 3600) . ' hours';
        } else {
            return floor($seconds / 86400) . ' days';
        }
    }


    // admin register

    public function adminRegister(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:6',
                'wp_role' => 'required|string|in:administrator,subscriber,editor'
            ]);

            $data = $request->all();

           $wpRoles = [
                'administrator' => 4,
                'subscriber' => 6,
                'editor' => 7
            ];

            // Set default values
            $data['password'] = bcrypt($data['password']);
            $data['role'] = $wpRoles[$data['wp_role']] ?? 0;
            $data['level'] = '0';
            $data['checkPoint'] = '0';

            $user = User::create($data);

            $success['token'] = $user->createToken($user->email, ['*'], now()->addDays(3))->plainTextToken;
            $success['expires_at'] = now()->addDays(3);
            $success['name'] = $user->name;

            // Send welcome email (if needed)
            $email = $user->email;
            $name = $user->name;
            Mail::send('auth.welcome', compact('name'), function ($e) use ($email) {
                $e->subject('Registered successfully');
                $e->to($email);
            });

            $balance = balance::where('user_id', $user->_id)->first();
            if (!$balance) {
                $data_balance['id_wallet'] = $this->generateRandomCode();
                $data_balance['topup'] = 0;
                $data_balance['balance'] = 0;
                $data_balance['user_id'] = $user->_id;
                $balance = balance::create($data_balance);
            }

            return $this->sendResponse($success, 'User register successfully.');

        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    public function adminDeleteUser(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|exists:users,name' // Validate username thay vì email
            ]);

            $user = User::where('name', $request->name)->first(); // Tìm theo username

            if (!$user) {
                return $this->sendError('User not found.', [], 404);
            }

            // Xóa token nếu có
            $user->tokens()->delete();

            // Xóa user
            $user->delete();

            return $this->sendResponse([], 'User deleted successfully.');

        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    public function adminUpdateUser(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255',
                'old_name' => 'required|string|exists:users,name', // Validate old username thay vì email
                'wp_role' => 'required|string|in:administrator,subscriber,editor',
                'password' => 'nullable|string|min:6'
            ]);

            // Tìm user bằng username cũ
            $user = User::where('name', $request->old_name)->first();

            if (!$user) {
                return $this->sendError('User not found.', [], 404);
            }

            // Kiểm tra username mới đã tồn tại chưa (nếu có thay đổi)
            if ($request->name !== $request->old_name) {
                if (User::where('name', $request->name)->exists()) {
                    return $this->sendError('Username already exists.', [], 422);
                }
            }

            // Kiểm tra email mới
            if ($request->email !== $user->email) {
                if (User::where('email', $request->email)->exists()) {
                    return $this->sendError('Email already exists.', [], 422);
                }
            }

            $wpRoles = [
                'administrator' => 4,
                'subscriber' => 6,
                'editor' => 7
            ];

            $updateData = [
                'name' => $request->name,
                'email' => $request->email,
                'role' => $wpRoles[$request->wp_role] ?? 0
            ];

            if ($request->filled('password')) {
                $updateData['password'] = bcrypt($request->password);
            }

            $user->update($updateData);

            $success['token'] = $user->createToken($user->email, ['*'], now()->addDays(3))->plainTextToken;
            $success['expires_at'] = now()->addDays(3);
            $success['name'] = $user->name;

            return $this->sendResponse($success, 'User updated successfully.');

        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }


    public function getAllSupporter()
    {
        $user = User::where('role', 7)->get();
        return $user;
    }

    public function AllUser() {
        $user = User::orderBy('created_at', 'desc')->get();
        return $this->sendResponse($user, 'Balance Log retrieved successfully.');

    }

    public function resetUser2FA($id){
        try {
            $user = User::find($id);
            // xoá cot google2fa_enabled và google2fa_secret
            $user['google2fa_enabled'] = null;
            $user['google2fa_secret'] = null;
            $user->save();
            return $this->sendResponse(null, 'Reset 2FA successfully');
        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }
    public function resetUserPenalty($id){
        try {
            $user = User::find($id);
            $user['penalty_tax'] = 0;
            $user->save();
            // xoá user_penalty_tax_log
            $user_penalty_tax_log = UserPenaltyTaxLog::where('user_id', $id)->delete();
            return $this->sendResponse(null, 'Reset Penalty successfully');
        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }


    public function googleLogin(Request $request)
    {
        try {
            // Hàm tạo tên ngẫu nhiên với tiền tố ebay
            function generateUniqueName($prefix = 'ebay') {
                do {
                    // Tạo chuỗi ngẫu nhiên gồm chữ và số
                    $length = 8; // Độ dài chuỗi ngẫu nhiên
                    $characters = '0123456789abcdefghijklmnopqrstuvwxyz';
                    $randomString = '';

                    for ($i = 0; $i < $length; $i++) {
                        $randomString .= $characters[random_int(0, strlen($characters) - 1)];
                    }

                    $name = $prefix . '-' . $randomString;

                    // Kiểm tra xem tên đã tồn tại chưa
                    $exists = User::where('name', $name)->exists();
                } while ($exists);

                return $name;
            }

            // Tạo tên ngẫu nhiên cho user mới
            $uniqueName = generateUniqueName();

            $user = User::firstOrCreate(
                ['email' => $request['email']],
                [
                    'name' => $uniqueName,
                    'password' => bcrypt(uniqid()),
                    'google_id' => $request['google_id']
                ]
            );

            if ($user->is_banned == true) {
                return $this->sendError('Your account has been banned. Please contact support for more information.', [], 403);
            }

            $success['token'] = $user->createToken($user->email, ['*'], now()->addDays(3))->plainTextToken;
            $success['expires_at'] = now()->addDays(3);
            $success['name'] = $user->name;
            $success['email'] = $user->email;
            $success['google2fa_enabled'] = $user->google2fa_enabled;

            // thêm trạng thái online
            $user->is_online = true;
            $user->last_activity = now();
            $user->save();

            // lưu log

            $this->saveUserLog($user->_id, 'login_success', 'User Login Successful', $request['ipSever']);

            // tạo id ví cho user chưa có
            $balance = balance::where('user_id', $user->_id)->first();

            if (!$balance) {
                $data['id_wallet'] = $this->generateRandomCode();
                $data['topup'] = 0;
                $data['balance'] = 0;
                $data['user_id'] = $user->_id;
                $balance = balance::create($data);
            }
            return $this->sendResponse($success, 'User login successfully!');
        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 500);
        }
    }


    private function generateRandomCode() {
        $hexChars = 'abcdef0123456789';
        $alphaChars = 'abcdefghijklmnopqrstuvwxyz';

        $getRandomChar = function($charSet) {
            return $charSet[rand(0, strlen($charSet) - 1)];
        };

        do {
            $id_wallet = '0x' .
                $getRandomChar($hexChars) .
                $getRandomChar($hexChars) .
                $getRandomChar($hexChars) .
                $getRandomChar($alphaChars) .
                $getRandomChar($alphaChars) .
                $getRandomChar($hexChars) .
                $getRandomChar($hexChars) .
                $getRandomChar($hexChars) .
                $getRandomChar($hexChars) .
                $getRandomChar($alphaChars) .
                $getRandomChar($hexChars) .
                $getRandomChar($alphaChars);
        } while (balance::where('id_wallet', $id_wallet)->exists()); // Kiểm tra trùng ID
        return $id_wallet;
    }
}
