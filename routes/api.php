<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
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
    Route::post('logout', [UserController::class, 'logout']);
    Route::post('user', [UserController::class, 'getUser']);
    Route::post('change-password', [UserController::class, 'changePassword']);
    Route::post('change-information', [UserController::class, 'changeInformation']);

});