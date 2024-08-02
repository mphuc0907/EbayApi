<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ApiController as ApiController;
use App\Models\User;
use App\Models\PasswordResetToken;
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
            $user = User::create($data);
            //return $this->sendResponse($user, 'User register successfully.');
            $success['token'] =  $user->createToken($user->email, ['*'], now()->addDays(3))->plainTextToken;
            $success['expires_at'] = now()->addDays(3);
            $success['name'] =  $user->name;

            return $this->sendResponse($success, 'User register successfully.');
        } catch (\Exception $e) {
            return  $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }
    /**
     * Login api
     *
     * @return \Illuminate\Http\Response
     */
    public function login(LoginFormRequest $request)
    {
        if(Auth::guard('web')->attempt(['email' => $request->email, 'password' => $request->password])){
            $user = Auth::user();
            $success['token'] =  $user->createToken($user->email, ['*'], now()->addDays(3))->plainTextToken;
            $success['expires_at'] = now()->addDays(3);

            return $this->sendResponse($success, 'User login successfully.');
        }
        else{
            return $this->sendError('The provided credentials are incorrect', [], 401);
        }
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
            $token = Str::random(50);
            $url = config('base.siteurl') . '/reset-password?token=' . $token;
            // Lưu thông tin
            PasswordResetToken::create([
                'email' => $email,
                'token' => $token,
                'created_at' => time()
            ]);
            // Gửi mail
            Mail::send('auth.forgot_password', compact('url'), function ($e) use($email) {
                $e->subject('Forgot password Notification');
                $e->to($email);
            });
            return $this->sendResponse(null, 'We have sent a reset email to your email. Please check your email and follow the instructions.');
        } catch (\Exception $e) {
            return  $this->sendError('An error has occurred. Please try again later', [], 400);
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
            if($user_password_reset = $password_reset_token->checkEmailAndToken($request['token'], $request['email'])){
                // Kiểm tra xem đã hết 5p chưa
                if($user_password_reset['created_at']->timestamp + 300 < time()) {
                    return  $this->sendError('Password change time has expired. Please resubmit request.', [], 400);
                }
                //
                $user = User::where('email', $request['email'])->first();
                $user->forceFill([
                    'password' => Hash::make($request['password'])
                ]);
                $user->save();
                return $this->sendResponse(null, 'Password change successful');
            }
            return  $this->sendError('Incorrect information. Please check and try again.', [], 400);
        } catch (\Exception $e) {
            return  $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }
    /**
     * Get user information
     *
     * @return \Illuminate\Http\Response
     */
    public function getUser() {
        return auth()->user();
    }
    /**
     * Change password
     *
     * @return \Illuminate\Http\Response
     */
    public function changePassword(ChangePasswordFormRequest $request) {
        try {
            $user = auth()->user();
            // Kiểm tra mật khẩu hiện tại có đúng hay không
            if(!Hash::check($request['old_password'], $user['password'])) {
                return  $this->sendError('Current password is incorrect. Please check and try again.', [], 400);
            }
            $user->forceFill([
                'password' => Hash::make($request['new_password'])
            ]);
            $user->save();
            return $this->sendResponse(null, 'Password change successful');
        } catch (\Exception $e) {
            return  $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }
    /**
     * Change information
     *
     * @return \Illuminate\Http\Response
     */
    public function changeInformation(ChangeInformationFormRequest $request) {
        try {
            $user = auth()->user();
            $user['fullname'] = $request['fullname'];
            $user->save();
            return $this->sendResponse(null, 'Change information successfully');
        } catch (\Exception $e) {
            return  $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }
    /**
     * Đăng ký bán hàng
     *
     * @return \Illuminate\Http\Response
     */
    public function saleRegister(SaleRegisterFormRequest $request) {
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
            return  $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }
}
