<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\TwoFactorController;
use App\Http\Controllers\Api\KiotController;
use App\Http\Controllers\Api\KiotSubController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CategoryParentController;
use App\Http\Controllers\Api\CategorySlugController;
use App\Http\Controllers\Api\kiosk_sub_productController;
use App\Http\Controllers\Api\ResellerController;
use App\Http\Controllers\Api\balanceController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\report_userController;
use App\Http\Controllers\Api\RatingPostController;
use App\Http\Controllers\Api\RatingProductController;
use App\Http\Controllers\Api\PromotionController;
use App\Http\Controllers\Api\balance_logController;
use App\Http\Controllers\Api\OrderDetailController;
use App\Http\Controllers\Api\ReportOrderController;
use Illuminate\Auth\Middleware\Authenticate;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');
Route::group(['prefix' => 'auth'], function ($router) {
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
    Route::post('change-telegram', [UserController::class, 'changeInfoTelegram']);
    Route::post('uploadAvatar', [UserController::class, 'uploadAvatar']);
    // 2FA
    Route::get('2fa/setup', [TwoFactorController::class, 'setup']);
    Route::get('2fa/get', [TwoFactorController::class, 'get']);
    Route::post('2fa/active', [TwoFactorController::class, 'active']);
    Route::post('2fa/verify', [TwoFactorController::class, 'verify']);
    Route::post('2fa/disable', [TwoFactorController::class, 'disable']);
    // Đăng ký bán hàng
    Route::post('sale-register', [UserController::class, 'saleRegister']);
    // Gian hàng và sản phẩm
    Route::get('kiotUser/{user_id}', [KiotController::class, 'getKiotByUser']);
    Route::post('add-kiot', [KiotController::class, 'addKiot']);
    Route::get('edit-kiot/{id}', [KiotController::class, 'getkiotID']);
    Route::get('upadateIDPost/{id}', [KiotController::class, 'upadateIDPost']);
    Route::post('edit-kiot/{id}', [KiotController::class, 'editKiot']);
    Route::post('editKiotStatus/{id}', [KiotController::class, 'editKiotStatus']);

    //Sản phẩm
    Route::post('add-Subkiot', [KiotSubController::class, 'AddSubKiot']);
    Route::get('edit-SubKiotById/{id}', [KiotSubController::class, 'getSubKiotById']);
    Route::post('update-Subkiot/{id}', [KiotSubController::class, 'updateSubKiot']);
    Route::delete('delete-Subkiot/{id}', [KiotSubController::class, 'deleteSubKiot']);

    // Danh mục category
    Route::post('AddCategory', [CategoryController::class, 'addCategory']);
    Route::post('Addcategoryparent', [CategoryParentController::class, 'addCategoryParent']);
    Route::post('Addcategoryslug', [CategorySlugController::class, 'addCategorySlug']);
    Route::get('editCateSlugByParent/{id}', [CategorySlugController::class, 'getCategorySlugByParent']);

    //Kho product
//    Route::post('Addproduct', [kiosk_sub_productController::class, 'productAddFile']);
    Route::post('UploadFileTxt', [kiosk_sub_productController::class, 'UploadFileTxt']);
    Route::get('getProductByKioskId/{id}', [kiosk_sub_productController::class, 'getKiotSubProductByParentId']);
    Route::get('getProductUploadByKioskId/{id}', [kiosk_sub_productController::class, 'getKiotSubHistoryByParentId']);
    Route::get('getKiotSubHistoryById/{id}', [kiosk_sub_productController::class, 'getKiotSubHistoryById']);
    //Reseller
    Route::get('getResellerByKiotId/{id}', [ResellerController::class, 'getResellerByKiotId']);
    Route::get('GetInfoReseller', [ResellerController::class, 'GetInfoReseller']);
    Route::get('getResellerByUserId', [ResellerController::class, 'getResellerByUserId']);
    Route::post('AddReseller', [ResellerController::class, 'AddReseller']);
    Route::post('updateStatusReseller/{id}', [ResellerController::class, 'updateStatusReseller']);


    //Balance Ví tiền
    Route::get('GetBalance/{id}', [balanceController::class, 'GetBalance']);
    //Khởi tạo ví tiền và nạp lần đầu tiên
    Route::post('addBalance', [balanceController::class, 'addBalance']);
    //Api nạp tiền
    Route::post('recharge/{id}', [balanceController::class, 'rechargeBalance']);
    //Api rút tiền
    Route::post('withdrawmoney/{id}', [balanceController::class, 'withdrawmoneyBalance']);
    // get info balance by user id
    Route::get('getBalanceByUserId', [balanceController::class, 'getBalanceByUserId']);

    // balance_log
    Route::get('getBalanceLogByUserId', [balance_logController::class, 'getBalanceLogByUserId']);

    //wishlist
    Route::get('wishlist/{id_user}', [WishlistController::class, 'getWishlist']);
    Route::get('checkWishlist/{id_kiot}',[WishlistController::class, 'checkWishlist']);
    Route::post('Addwishlist', [WishlistController::class, 'Addwishlist']);
    Route::delete('Deletewishlist/{id}', [WishlistController::class, 'Deletewishlist']);

    //order
    Route::get('GetOrder', [OrderController::class, 'GetOrder']);
    Route::get('GetOrderbyID/{id_user}', [OrderController::class, 'GetOrderByID']);
    Route::get('GetPreOrder/{id_user}', [OrderController::class, 'GetPreOrder']);
    Route::get('SearchOrderSeller/{id_user}', [OrderController::class, 'SearchOrderSeller']);
    Route::get('SearchOrderPrev/{id_user}', [OrderController::class, 'SearchOrderPrev']);
    Route::get('SearchOrder', [OrderController::class, 'SearchOrder']);
    Route::post('OrderSuccess', [OrderController::class, 'AddOrder']);
    Route::post('AddPrevOrder', [OrderController::class, 'AddPrevOrder']);
    Route::post('VerifyOrder', [OrderController::class, 'VerifyOrder']);
    Route::get('GetOrderSeller', [OrderController::class, 'GetOrderSeller']);
    // order detail
    Route::get('GetOrderDetailByOrderCode/{order_code}', [OrderDetailController::class, 'GetOrderDetailByOrderCode']);

    // Rating Post
    Route::post('AddRatingPost', [RatingPostController::class, 'AddRatingPost']);
    Route::get('getRatingPostByUserId/{id}', [RatingPostController::class, 'getRatingPostByUserId']);
    Route::post('updateStatusRatingPost/{id}', [RatingPostController::class, 'updateStatusRatingPost']);

    //Rating product
    Route::post('AddRatingProduct', [RatingProductController::class, 'AddRatingProduct']);
    Route::get('getRatingProductUserSeller', [RatingProductController::class, 'getRatingProductUserSeller']);
    Route::get('getReplyRating/{id}', [RatingProductController::class, 'getReplyRating']);
    Route::post('replyRating', [RatingProductController::class, 'replyRating']);

    // Promotion
    Route::post('addPromotion', [PromotionController::class, 'addPromotion']);
    Route::get('getPromotion', [PromotionController::class, 'getPromotion']);
    Route::get('getPromotionByUserId', [PromotionController::class, 'getPromotionByUserId']);
    Route::post('updateStatusPromotion/{id}', [PromotionController::class, 'updateStatusPromotion']);
    Route::delete('removePromotion/{id}', [PromotionController::class, 'removePromotion']);

    //Report order
    Route::post('SubmitReportOrder', [ReportOrderController::class, 'Add']);
    Route::post('VerifyReport/{id_order}', [ReportOrderController::class, 'VerifyReport']);
    Route::get('GetOrderSeller/{id_seller}', [ReportOrderController::class, 'getSeller']);


    Route::get('SearchReportSeller/{id_seller}', [ReportOrderController::class, 'SearchReport']);
    Route::get('GetOrderReport/{order_code}', [ReportOrderController::class, 'GetOrderReport']);
});
Route::get('getkiotAdmin/{id}', [KiotController::class, 'getkiotAdmin']);
Route::get('CheckShortCode', [ResellerController::class, 'CheckShortCode']);

Route::get('getReportAdmin', [ReportOrderController::class, 'getReportAdmin']);
Route::get('kiotUserNoLogin/{user_id}', [KiotController::class, 'getKiotByUserId']);

//Số  lượng đánh giá
Route::get('getQuantityRating/{id_kiot}', [RatingProductController::class, 'getQuantityRating']);

Route::get('edit-SubKiotByKioskId/{id}', [KiotSubController::class, 'GetSubKiotByKioskId']);
Route::post('getPromotionByCode', [PromotionController::class, 'getPromotionByPromotionCode']);
Route::get('GetRatingPostById/{id}', [RatingPostController::class, 'GetRatingPostById']);
Route::get('getRatingPostByParentId/{id}', [RatingPostController::class, 'getRatingPostByParentId']);
Route::get('checkRatingPostById/{id}', [RatingPostController::class, 'checkRatingPostById']);
//khiếu nại
Route::get('report_user', [report_userController::class, 'report_user']);
Route::post('AddReport', [report_userController::class, 'AddReport']);

//Lấy số lượng đã bán của gian hàng
Route::get('QuantityOrder/{id_kiot}', [OrderController::class, 'QuantityOrder']);


// Láy mã OTP
Route::get('2fa/otp', [TwoFactorController::class, 'otp']);
// Danh mục sản phẩm
Route::get('category', [CategoryController::class, 'GetCategory']);

Route::get('kiot', [KiotController::class, 'getKiot']);

Route::get('editCategory/{id}', [CategoryController::class, 'getCategoryByID']);

Route::get('editCate/{id}', [CategoryParentController::class, 'getCategoryParentByID']);

Route::get('editCateSlug/{id}', [CategorySlugController::class, 'getCategorySlugByID']);

Route::get('categoryparent', [CategoryParentController::class, 'GetCategoryParent']);

Route::get('categoryslug', [CategorySlugController::class, 'GetCategorySlug']);

// no login
Route::get('getUser/{id}', [UserController::class, 'getUserById']);
Route::get('getKiotByUserId/{userId}', [KiotController::class, 'getKiotByUserId']);
Route::get('GetRatingPostByPostId/{id}', [RatingPostController::class, 'GetRatingPostByPostId']);
Route::get('GetRatingProductByKiotId/{id}', [RatingProductController::class, 'GetRatingProductByKiotId']);
Route::get('getRatingProductById/{id}', [RatingProductController::class, 'getRatingProductById']);
Route::get('getTotalBuyAndSellByUser/{id}', [OrderController::class, 'getTotalBuyAndSellByUser']);

// category update
Route::post('updateCateParent/{id}', [CategoryParentController::class, 'updateCategoryParent']);
Route::post('updateCategory/{id}', [CategoryController::class, 'updateCategory']);
Route::post('updateCategorySlug/{id}', [CategorySlugController::class, 'updateCategorySlug']);
// category delete
Route::delete('deleteCategorySlug/{id}', [CategorySlugController::class, 'deleteCategorySlug']);
Route::delete('deleteCategory/{id}', [CategoryController::class, 'deleteCategory']);
Route::delete('deleteCategoryParent/{id}', [CategoryParentController::class, 'deleteCategoryParent']);
