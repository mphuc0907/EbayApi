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
use App\Http\Controllers\Api\UserLogController;
use App\Http\Controllers\Api\report_userController;
use App\Http\Controllers\Api\RatingPostController;
use App\Http\Controllers\Api\RatingProductController;
use App\Http\Controllers\Api\PromotionController;
use App\Http\Controllers\Api\balance_logController;
use App\Http\Controllers\Api\OrderDetailController;
use App\Http\Controllers\Api\ReportOrderController;
use App\Http\Controllers\Api\DashboardContetroller;
use App\Http\Controllers\Api\UserPenaltyTaxLogController;
use App\Http\Controllers\Api\AuctionController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\AuctionMessagesController;
use App\Http\Controllers\Api\AuctionLogController;
use App\Http\Controllers\Api\GiftCodeController;
use App\Http\Controllers\Api\ActivityLogController;

use Illuminate\Auth\Middleware\Authenticate;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');
Route::group(['prefix' => 'auth'], function ($router) {
    Route::post('register', [UserController::class, 'register']);
    Route::post('login', [UserController::class, 'login']);
    Route::post('google-login', [UserController::class, 'googleLogin']);
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
    Route::post('change-buyApi', [UserController::class, 'changeInfoBuyApi']);
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
    Route::post('getKiotByCate', [KiotController::class, 'getKiotByCate']);
    //Sản phẩm
    Route::post('add-Subkiot', [KiotSubController::class, 'AddSubKiot']);
    Route::get('edit-SubKiotById/{id}', [KiotSubController::class, 'getSubKiotById']);
    Route::post('update-Subkiot/{id}', [KiotSubController::class, 'updateSubKiot']);
    Route::get('countKiot', [KiotController::class, 'CountKiotByUser']);

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
    Route::get('getProductByFileName', [kiosk_sub_productController::class, 'getProductByFileName']);
    Route::get('getProductTxtNoSell', [kiosk_sub_productController::class, 'getProductTxtNoSell']);
    Route::post('deleteProductTxt', [kiosk_sub_productController::class, 'deleteProductTxt']);
    Route::post('reportProduct', [kiosk_sub_productController::class, 'reportProduct']);
    Route::get('checkReport/{id}', [kiosk_sub_productController::class, 'checkReport']);
    Route::get('checkFileName', [kiosk_sub_productController::class, 'checkFileName']);
    //Reseller
    Route::get('getResellerByKiotId/{id}', [ResellerController::class, 'getResellerByKiotId']);
    Route::get('GetInfoReseller', [ResellerController::class, 'GetInfoReseller']);
    Route::get('getResellerByUserId', [ResellerController::class, 'getResellerByUserId']);
    Route::post('AddReseller', [ResellerController::class, 'AddReseller']);
    Route::get('checkReseller/{id}', [ResellerController::class, 'checkReseller']);
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
    Route::get('GetKiotByAuction/{id}', [AuctionController::class, 'GetKiotByAuction']);
    Route::post('sendRequest', [balance_logController::class, 'send_request']);
    Route::post('send_withdrawmoney', [balance_logController::class, 'send_withdrawmoney']);


    // balance_log
    Route::get('getBalanceLogByUserId', [balance_logController::class, 'getBalanceLogByUserId']);
    Route::get('getBalanceWidthDraw', [balance_logController::class, 'getBalanceWidthDraw']);
    Route::get('custody_money', [balance_logController::class, 'custody_money']);
    Route::get('getRevenueByUser', [balance_logController::class, 'getRevenueByUser']);
    Route::get('getDailyTopupPercentage', [balance_logController::class, 'getDailyTopupPercentage']);
    Route::get('getTemporarily', [balance_logController::class, 'getTemporarily']);
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
    Route::post('guaranteeOrder', [OrderController::class, 'guaranteeOrder']);

    Route::post('VerifyOrder', [OrderController::class, 'VerifyOrder']);
    Route::post('VerifyOrdersSrvice', [OrderController::class, 'VerifyOrdersSrvice']);
    Route::get('GetOrderSeller', [OrderController::class, 'GetOrderSeller']);
    Route::get('GetOrderDashboard', [OrderController::class, 'GetOrderDashboard']);
//    Route::get('GetOrderDashboard', [OrderController::class, 'GetOrderDashboard']);
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
    Route::get('GetOrderBySeller/{id_seller}', [ReportOrderController::class, 'getSeller']);


    Route::get('SearchReportSeller/{id_seller}', [ReportOrderController::class, 'SearchReport']);
    Route::get('GetOrderReport/{order_code}', [ReportOrderController::class, 'GetOrderReport']);

    //USer log
    Route::get('GetLogUser', [UserLogController::class, 'GetLogUser']);

    //Message
    Route::post('createConversation', [MessageController::class, 'createConversation']);
    Route::post('sendMessage', [MessageController::class, 'sendMessage']);
    Route::get('getConversation', [MessageController::class, 'getConversation']);
    Route::post('getMessage/{id_conversation}', [MessageController::class, 'getMessage']);
    Route::post('getLastMessage', [MessageController::class, 'getLastMessage']);
    Route::get('getMessageByNameUser/{name}', [MessageController::class, 'getMessageByNameUser']);
    Route::get('getConversationByNameReceiver/{name}', [MessageController::class, 'getConversationByNameReceiver']);
    Route::post('seenMessage', [MessageController::class, 'SeenMessage']);
    Route::post('messageNoSeen/{id}', [MessageController::class, 'getMessageNoSeen']);
    Route::post('getNewConversation', [MessageController::class, 'getNewConversation']);
    Route::post('sendMessageByAdmin', [MessageController::class, 'sendMessageByAdmin']);
    Route::get('qtyMessage', [MessageController::class, 'qtyMessage']);

    //Đặt tiền đấu giá
    Route::post('targetMoney', [AuctionController::class, 'targetMoney']);
    Route::post('sendMessAuction', [AuctionController::class, 'sendMess']);
    Route::get('getAuctionUser/{id}', [AuctionController::class, 'getAuctionUser']);

    // tin nhắn đấu giá
    Route::post('sendMessageAuction', [AuctionMessagesController::class, 'sendMessageAuction']);
    Route::post('getAllMessage', [AuctionMessagesController::class, 'getAllMessage']);
    Route::post('getMessageAuction', [AuctionMessagesController::class, 'getMessageAuction']);
    Route::post('getNewMessageAuction', [AuctionMessagesController::class, 'getNewMessages']);
    Route::post('UpView', [AuctionController::class, 'UpViewAuction']);

    // upadate post
    Route::post('updateAuctionByKiot', [AuctionController::class, 'AuctionByKiot']);

    // gift code
    Route::get('getRecentBalanceLog', [balance_logController::class, 'getRecentBalanceLog']);
    Route::post('useGiftCode', [GiftCodeController::class, 'useGiftCode']);


    // admin 30 giao dịch gần nhất
    Route::get('getTransaction', [balance_logController::class, 'getTransaction']);

    // chuyển tiền cho support
    Route::post('transferMoneySupporter', [balance_logController::class, 'transferMoneySupporter']);
});

Route::post('rechargeBalanceErr', [balanceController::class, 'rechargeBalanceErr']);


Route::post('sendMessageSystemBot', [MessageController::class, 'sendMessageSystemBot']);
Route::post('get-new-message/{id}', [MessageController::class, 'getNewMessages']);
Route::post('sendMessageCustomerService', [MessageController::class, 'sendMessageCustomerService']);
Route::get('getAllConversations', [MessageController::class, 'getAllConversations']);
Route::get('adminGetMessage/{id}', [MessageController::class, 'adminGetMessage']);
Route::post('markConversationAsRead', [MessageController::class, 'markConversationAsRead']);

Route::get('userStatus/{userId}', [UserController::class, 'getUserStatus'])
    ->middleware(\App\Http\Middleware\TrackUserActivity::class);

Route::get('OrderAll', [OrderController::class, 'OrderAll']);
Route::post('SearchOrderAdmin', [OrderController::class, 'SearchOrderAdmin']);

Route::get('buyProducts', [OrderController::class, 'buyProductsAPI']);
Route::get('getProducts', [OrderDetailController::class, 'GetProductAPI']);
Route::get('getStock', [KiotSubController::class, 'getStockAPI']);

Route::get('getkiotAdmin/{id}', [KiotController::class, 'getkiotAdmin']);
Route::get('getResellerAdminByKiotId/{id}', [ResellerController::class, 'getResellerByKiotId']);
Route::get('CheckShortCode', [ResellerController::class, 'CheckShortCode']);
Route::post('VerifyStatus/{id}', [balance_logController::class, 'VerifyStatus']);
//Get log
// balance_log
Route::get('getBalanceLog', [balance_logController::class, 'getBalanceLog']);
Route::get('getBalanceLogErr', [balance_logController::class, 'getBalanceLogErr']);
Route::get('searchBalanceLogByUserName', [balance_logController::class, 'searchBalanceLogByUserName']);
Route::get('getBalanceById/{id}', [balanceController::class, 'GetBalance']);

Route::get('getReportAdmin', [ReportOrderController::class, 'getReportAdmin']);
Route::get('getAllReportAdmin', [ReportOrderController::class, 'getAllReportAdmin']);
Route::get('kiotUserNoLogin/{user_id}', [KiotController::class, 'getKiotByUserIdApproved']);

// admin note report order
Route::post('addNoteReportOrder', [ReportOrderController::class, 'addNoteReportOrder']);
Route::post('getNoteReportOrder/{order_code}', [ReportOrderController::class, 'getNoteReportOrder']);


//Số  lượng đánh giá
Route::get('getQuantityRating/{id_kiot}', [RatingProductController::class, 'getQuantityRating']);
Route::get('getRatingByUserId/{id}', [RatingProductController::class, 'getRatingByUserId']);

Route::get('edit-SubKiotByKioskId/{id}', [KiotSubController::class, 'GetSubKiotByKioskId']);
Route::post('getPromotionByCode', [PromotionController::class, 'getPromotionByPromotionCode']);
Route::get('GetRatingPostById/{id}', [RatingPostController::class, 'GetRatingPostById']);
Route::get('getRatingPostByParentId/{id}', [RatingPostController::class, 'getRatingPostByParentId']);
Route::get('checkRatingPostById/{id}', [RatingPostController::class, 'checkRatingPostById']);
//khiếu nại
Route::get('report_user', [report_userController::class, 'report_user']);
Route::post('AddReport', [report_userController::class, 'AddReport']);
Route::get('GetReports', [report_userController::class, 'GetReports']);

//Lấy số lượng đã bán của gian hàng
Route::get('QuantityOrder/{id_kiot}', [OrderController::class, 'QuantityOrder']);
// lấy tổng số sản phẩm có sẵn trong kho :
Route::get('count-all-product/{id_user}', [KiotController::class, 'CountProductInKiot']);


// Láy mã OTP
Route::get('2fa/otp', [TwoFactorController::class, 'otp']);
// Danh mục sản phẩm
Route::get('category', [CategoryController::class, 'GetCategory']);

Route::get('kiot', [KiotController::class, 'getKiot']);
Route::get('countWishlist/{id_kiot}', [WishlistController::class, 'countWishlist']);
//User admin
Route::get('GetUserAdmin', [UserLogController::class, 'GetUser']);
Route::get('GetUserAdminByID', [UserLogController::class, 'GetUserByID']);
Route::get('adminSearchUser', [UserController::class, 'adminSearchUser']);

Route::post('SearchUserLog', [UserLogController::class, 'SearchLog']);
// tìm kiếm kiot
Route::post('searchKiot', [KiotController::class, 'searchKiot']);

Route::get('editCategory/{id}', [CategoryController::class, 'getCategoryByID']);

Route::get('editCate/{id}', [CategoryParentController::class, 'getCategoryParentByID']);

Route::get('editCateSlug/{id}', [CategorySlugController::class, 'getCategorySlugByID']);

Route::get('categoryparent', [CategoryParentController::class, 'GetCategoryParent']);

Route::get('categoryslug', [CategorySlugController::class, 'GetCategorySlug']);

//dashboard
Route::get('dashboard', [DashboardContetroller::class, 'Statistic']);
Route::get('dashboardBySeller/{id}', [DashboardContetroller::class, 'StatisticBySellerId']);
Route::get('dashboardMonth', [DashboardContetroller::class, 'StatisticByDay']);
Route::get('dashboardMonthBySeller/{id}', [DashboardContetroller::class, 'StaticDayBySellerId']);

// no login
Route::get('getUser/{id}', [UserController::class, 'getUserById']);
Route::get('getUserByName/{name}', [UserController::class, 'getUserByName']);
Route::get('getKiotByUserId/{userId}', [KiotController::class, 'getKiotByUserId']);
Route::get('GetRatingPostByPostId/{id}', [RatingPostController::class, 'GetRatingPostByPostId']);
Route::get('GetRatingProductByKiotId/{id}', [RatingProductController::class, 'GetRatingProductByKiotId']);
Route::get('getRatingProductById/{id}', [RatingProductController::class, 'getRatingProductById']);
Route::get('getTotalBuyAndSellByUser/{id}', [OrderController::class, 'getTotalBuyAndSellByUser']);
Route::get('manageSale', [UserController::class, 'getSale']);
// category update
Route::post('updateCateParent/{id}', [CategoryParentController::class, 'updateCategoryParent']);
Route::post('updateCategory/{id}', [CategoryController::class, 'updateCategory']);
Route::post('updateCategorySlug/{id}', [CategorySlugController::class, 'updateCategorySlug']);
// category delete
Route::delete('deleteCategorySlug/{id}', [CategorySlugController::class, 'deleteCategorySlug']);
Route::delete('deleteCategory/{id}', [CategoryController::class, 'deleteCategory']);
Route::delete('deleteCategoryParent/{id}', [CategoryParentController::class, 'deleteCategoryParent']);

Route::get('getKiotWishtList', [KiotController::class, 'getKiotWishtList']);
Route::get('getKiotWithFilter', [KiotController::class, 'getKiotWithFilter']);

// user_penalty_tax_log
Route::get('getUserPenaltyTaxLogByUserId/{id}', [UserPenaltyTaxLogController::class, 'getUserPenaltyTaxLogByUserId']);
Route::get('getUserPenaltyTaxLogByUserIdCurrent/{id}', [UserPenaltyTaxLogController::class, 'getUserPenaltyTaxLogByUserIdCurrent']);
Route::post('addUserPenaltyTaxLog', [UserPenaltyTaxLogController::class, 'addUserPenaltyTaxLog']);

// balance
Route::get('getBalanceByUser/{id}', [balanceController::class, 'getBalanceByUser']);

// user
Route::post('updateDataUser/{id}', [UserController::class, 'updateDataUser']);
Route::get('adminGetAllUser', [UserController::class, 'adminGetAllUser']);
Route::get('AllUser', [UserController::class, 'AllUser']);
Route::get('getAllSupporter', [UserController::class, 'getAllSupporter']);

// voucher
Route::post('adminAddPromotion', [PromotionController::class, 'adminAddPromotion']);
Route::post('adminUpdateDataPromotion/{id}', [PromotionController::class, 'adminUpdateDataPromotion']);
Route::get('getAdminPromiton', [PromotionController::class, 'getAdminPromiton']);
Route::get('searchAdminPromiton', [PromotionController::class, 'searchAdminPromiton']);
Route::get('getAdminPromotionById/{id}', [PromotionController::class, 'getAdminPromotionById']);

//Đấu giá
Route::post('createAuction', [AuctionController::class, 'createAuction']);
Route::post('updateAuction/{id}', [AuctionController::class, 'updateAuction']);
Route::post('addAuction', [AuctionController::class, 'addAuction']);
Route::post('updateAuction_sub/{id}', [AuctionController::class, 'updateAu']);
Route::post('getAuction', [AuctionController::class, 'getAuction']);
Route::post('checkStatus', [AuctionController::class, 'checkStatus']);
Route::post('checkStatusByID/{id}', [AuctionController::class, 'checkStatusByID']);
Route::get('getAuctionDetail/{id}', [AuctionController::class, 'getAuctionDetail']);
Route::post('getAuctionSub/{id}', [AuctionController::class, 'getAuctionSub']);
Route::post('getLogAuction', [AuctionController::class, 'getLogAuction']);
Route::post('checkAuction', [AuctionController::class, 'checkAndCreateAuction']);

// log đấu giá
Route::post('getAuctionDetail/{id}', [AuctionLogController::class, 'getAuctionDetail']);
Route::post('UserViewAuction', [AuctionController::class, 'LogUserAuction']);

// admin wp register
Route::post('adminRegister', [UserController::class, 'adminRegister']);
Route::delete('adminDeleteUser', [UserController::class, 'adminDeleteUser']);
Route::post('adminUpdateUser', [UserController::class, 'adminUpdateUser']);

//Lấy  kiot theo danh mục cha
Route::post('getKiotSponsorship', [AuctionController::class, 'getSponsorship']);
Route::post('GetKiotAdu', [AuctionController::class, 'GetKiotAdu']);
Route::post('StartNow/{id}', [AuctionController::class, 'StartNow']);


// gift code
Route::get('getGiftCode', [GiftCodeController::class, 'getAll']);
Route::post('createGiftCode', [GiftCodeController::class, 'createGiftCode']);


// get log :
Route::get('adminGetAllActivityLog', [ActivityLogController::class, 'adminGetAllActivityLog']);

// ẩn tin nhắn
Route::post('hideConversation', [MessageController::class, 'hideConversation']);
Route::get('/message-analytics', [MessageController::class, 'getMessageAnalytics']);
Route::get('/message-details', [MessageController::class, 'getStaffMessageDetails']);

Route::get('getAuctionView/{id}', [AuctionController::class, 'getAuctionView']);


// reset user
Route::post('resetUser2FA/{id}', [UserController::class, 'resetUser2FA']);
Route::post('resetUserPenalty/{id}', [UserController::class, 'resetUserPenalty']);

