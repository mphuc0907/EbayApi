<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\TwoFactorController;
use Illuminate\Auth\Middleware\Authenticate;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');
Route::group(['prefix' => 'auth'], function($router) {
    Route::post('register', [UserController::class, 'register']);
    Route::post('login', [UserController::class, 'login']);
    Route::post('forgot-password', [UserController::class, 'forgotPassword']);
    Route::post('reset-password', [UserController::class, 'resetPassword']);
//    Route::post('/reset-password/{token}', [UserController::class, 'resetPassword'])->name('password.reset');

});
Route::group(['middleware' => 'auth:sanctum'], function () {
    // Auth
    Route::post('logout', [UserController::class, 'logout']);
    Route::get('user', [UserController::class, 'getUser']);
    Route::post('change-password', [UserController::class, 'changePassword']);
    Route::post('change-information', [UserController::class, 'changeInformation']);
    // 2FA
    Route::get('2fa/setup', [TwoFactorController::class, 'setup']);
    Route::post('2fa/active', [TwoFactorController::class, 'active']);
    Route::post('2fa/verify', [TwoFactorController::class, 'verify']);
    Route::post('2fa/disable', [TwoFactorController::class, 'disable']);
    // Đăng ký bán hàng
    Route::post('sale-register', [UserController::class, 'saleRegister']);
});
// Láy mã OTP
Route::get('2fa/otp', [TwoFactorController::class, 'otp']);