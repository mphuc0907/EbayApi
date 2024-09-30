<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\ApiController as ApiController;
use App\Http\Requests\Verify2FARequest;
use App\Http\Requests\Disable2FARequest;
use App\Http\Requests\OTP2FARequest;
use Illuminate\Support\Facades\Auth;
use PragmaRX\Google2FAQRCode\Google2FA;
use Carbon\Carbon;

class TwoFactorController extends ApiController
{
    // Lấy mã 2fa secret và QR code
    public function setup()
    {
        try {
            $user = Auth::user();

            $google2fa = new Google2FA();

            $secret = $google2fa->generateSecretKey();
            $QR_Image = $google2fa->getQRCodeInline(
                config('app.name'),
                $user->email,
                $secret
            );
            return $this->sendResponse([
                'secret' => $secret,
                'QR_Image' => $QR_Image,
            ], 'OK');
        } catch (\Exception $e) {
            return  $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    // Kích hoạt bảo mật 2 lớp
    public function active(Verify2FARequest $request)
    {
        try {
            $user = Auth::user();
            $google2fa = new Google2FA();

            $valid = $google2fa->verifyKey($request['2fa_secret'], $request['2fa_otp']);

            if ($valid) {
                $user->google2fa_secret = $request['2fa_secret'];
                $user->google2fa_enabled = 1;
                $user->save();

                return $this->sendResponse(null, 'Two-factor authentication enabled successfully');
            }

            return $this->sendError('The information is incorrect. Please check and try again', [], 400);
        } catch (\Exception $e) {
            return  $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }
    //Lấy data user đã đăng nhập
    public function get() {
        try {
            $user = Auth::user();
            $google2fa = new Google2FA();

            // Lấy secret key từ cơ sở dữ liệu
            $secret = $user->google2fa_secret; // Ví dụ, giả sử bạn lưu secret key trong cột google2fa_secret của bảng users

            if (!$secret) {
                return $this->sendError('2FA is not enabled for this user.', [], 400);
            }

            // Tạo mã QR với secret key hiện tại
            $QR_Image = $google2fa->getQRCodeInline(
                config('app.name'),
                $user->email,
                $secret
            );

            return response()->json([
                'qr_image' => $QR_Image,
                'secret' => $secret,
            ]);

        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    // Kiểm tra đăng nhập
    public function verify(Disable2FARequest $request)
    {
        try {
            $user = Auth::user();
            $google2fa = new Google2FA();

            $valid = $google2fa->verifyKey($user->google2fa_secret, $request['2fa_otp']);
            if($valid) {
                return $this->sendResponse(null, 'OK');
            }

            return $this->sendError('The information is incorrect. Please check and try again', [], 400);
        } catch (\Exception $e) {
            return  $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }
    // Hủy kích hoạt bảo mật 2 lớp
    public function disable(Disable2FARequest $request)
    {
        try {
            $user = Auth::user();
            $google2fa = new Google2FA();

            $valid = $google2fa->verifyKey($user->google2fa_secret, $request['2fa_otp']);

            if ($valid) {
                $user->google2fa_secret = '';
                $user->google2fa_enabled = 0;
                $user->save();

                return $this->sendResponse(null, 'OK');
            }

            return $this->sendError('The login code is incorrect', [], 400);
        } catch (\Exception $e) {
            return  $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }
    // Lấy mã otp
    public function otp(OTP2FARequest $request)
    {
        try {
            $google2fa = new Google2FA();

            $otp = $google2fa->getCurrentOtp($request['2fa_secret']);
            // Thời gian hiện tại
            $currentTimestamp = Carbon::now()->timestamp;

            // Thời gian tồn tại của mã OTP (thường là 30 giây)
            $otpLifetime = 30;

            // Thời gian đã trôi qua kể từ khi mã OTP được tạo
            $timeElapsed = $currentTimestamp % $otpLifetime;

            // Thời gian còn lại của mã OTP
            $timeRemaining = $otpLifetime - $timeElapsed;

            return $this->sendResponse(['otp' => $otp, 'otp_life_time' => $timeRemaining], 'OK');
        } catch (\Exception $e) {
            return  $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }
}
