<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\balance;
use App\Models\balance_log;
use App\Models\CategorySlug;
use App\Models\kiosk_sub_product;
use App\Models\Kiot;
use App\Models\KiotSub;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\ReportOrder;
use App\Models\Reseller;
use App\Models\User;
use App\Models\OrderDetail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\RatingProduct;
use App\Http\Controllers\Api\ApiController as ApiController;
use function Carbon\int;

class OrderController extends ApiController
{

    public function OrderAll() {
        $order = Order::orderBy('created_at', 'desc')->get();
        return $order;
    }

    public function GetOrder()
    {
        $user = auth()->user();

        // Fetch paginated orders for the authenticated user
        $orders = Order::where('user_id', $user['_id'])->orderBy('created_at', 'desc')->paginate(5);
        $ordersWithRating = $orders->map(function ($order) {
            $rating = RatingProduct::where('order_id', $order['_id'])->first();
            $repory = ReportOrder::where('id_order', $order['_id'])->first();
            $order->is_rating = $rating ? 1 : 0;
            $order->is_report = $repory ? 1 : 0;
            return $order;
        });
        // Check if there are any orders for the user
        if ($orders->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No orders found for the user.',
                'data' => []
            ], 404);
        }

        // Return the paginated orders and pagination info
        return response()->json([
            'success' => true,
            'message' => 'Orders retrieved successfully.',
            'data' => $ordersWithRating,
            'pagination' => [
                'total' => $orders->total(),
                'per_page' => $orders->perPage(),
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'next_page_url' => $orders->nextPageUrl(),
                'prev_page_url' => $orders->previousPageUrl(),
            ]
        ], 200);
    }

    public function AddOrder(Request $request)
    {
        try {

            $user = auth()->user();
            $data = $request->all();
            $data['user_id'] = $user['_id'];

            $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';  // Chuỗi bao gồm cả chữ cái và số
            $maxAttempts = 1000; // Giới hạn số lần thử tạo mã (để tránh vòng lặp vô tận)
            $attempts = 0;  // Biến đếm số lần thử

            $sub_kiot = KiotSub::find($request['kiosk_sub_id']);
            $kiot = Kiot::find($request['kiosk_id']);
            if ($request['id_seller'] == $user['_id']) {
                return $this->sendError('You are not allowed to buy products from your own kiosk.', [], 400);
            }
            if ($kiot['status'] == 0) {
                return $this->sendError('The product is being approved and cannot be purchased', [], 400);
            }
            $category_sub = CategorySlug::find($kiot['category_sub_id']);

            $floor_dis = $category_sub['floor_dis'];
            // lấy phí sàn của seller
            $seller_info = User::find($request['id_seller']);
            $floor_dis_seller = $seller_info['penalty_tax'];
            if ($seller_info['is_banned'] == 1 && $floor_dis_seller > $floor_dis) {
                $floor_dis = $floor_dis_seller;
            }else{
                $floor_dis = $category_sub['floor_dis'];
            }
            // Kiểm tra xem sản phẩm có tồn tại không
            if (!$sub_kiot) {
                return $this->sendError('Kiosk product not found.', [], 404);
            }
            do {
                $randomString = '';
                $length = 7; // Chiều dài mã ban đầu là 9

                // Tạo mã ngẫu nhiên
                for ($i = 0; $i < $length; $i++) {
                    $randomString .= $characters[rand(0, strlen($characters) - 1)];
                }

                // Thêm 1 chữ số ngẫu nhiên vào cuối
                $randomString .= rand(0, 9);

                // Kiểm tra xem mã đã tồn tại trong cơ sở dữ liệu chưa
                $orderExists = Order::where('order_code', $randomString)->exists();

                // Nếu đã hết ký tự có thể tạo ra, tăng chiều dài của mã lên 2 ký tự nữa
                if ($orderExists) {
                    $length += 2; // Tăng chiều dài mã lên nếu trùng lặp
                }
                $attempts++;

            } while ($orderExists && $attempts < $maxAttempts); // Lặp cho đến khi tạo được mã duy nhất hoặc đạt giới hạn

            // Nếu đã thử quá số lần giới hạn mà vẫn trùng lặp, trả về lỗi
            if ($attempts >= $maxAttempts) {
                return $this->sendError('Could not generate unique order code. Please try again later.', [], 400);
            }
            $quality = (int)$request['quality'];
//            $qtyKiot = (int)$sub_kiot['quantity'];
            $qtyKiot = kiosk_sub_product::where('kiosk_sub_id', $request['kiosk_sub_id'])
                    ->where('status', 1)
                    ->count() ?? 0;
            if ($request['status_kiot'] == 'service') {
                $data['service_product'] = $sub_kiot['name'];
                $data['service_waitingdays'] = $request['service_waitingdays'];

            } else {
                $data['service_product'] = null;
                $data['service_waitingdays'] = null;
                if ((int)$qtyKiot < $quality) {
                    return $this->sendError('There is not enough quantity in the warehouse.', [], 400);
                }
            }
            // Sau khi đã tạo mã duy nhất, tiếp tục với các thao tác khác
            $order_code = $randomString;

            $total_price = (float)$sub_kiot['price'] * (int)$quality;

            //Tính phần trăm trên tổng số tiền
            $floor = ((float)$floor_dis / 100) * (float)$total_price;

            $data['order_code'] = $order_code; // Lưu mã order_code vào dữ liệu
            //Lấy giá sản phẩm
            //Giám giảm

            if ($request['code_vorcher'] != null || $request['code_vorcher'] != "null" || !empty($request['code_vorcher']) || $request['code_vorcher'] == "") {

                $data['code_vorcher'] = $request['code_vorcher'];
                $vorcher = Promotion::where('promotion_code', $request['code_vorcher'])->where('status', 1)->first();

                //Kiểm tra có tồn tại vorcher và trạng thái vorcher có còn hoạt động nữa không
                if (!empty($vorcher)) {
                    $startDay = $vorcher['start_date'];
                    $endtDay = $vorcher['end_date'];
                    // Chuyển đổi $startDay thành timestamp
                    $startDayTimestamp = strtotime($startDay);
                    $endtDayTimestamp = strtotime($endtDay);
                    // Lấy timestamp hiện tại
                    $currentTimestamp = time();
                    // check xem mã giảm giá đấy đã bắt đầu hay kết thúc chưa hoặc đã có tồn tại hay ko
                    $using = (int)$vorcher['total_for_using'];
                    //Kiểm tra vorcher của admin hay không
                    if ($vorcher->is_admin_created == 1 || $vorcher->is_admin_created != null) {

                        if ($startDayTimestamp <= $currentTimestamp
                            && $endtDayTimestamp >= $currentTimestamp
                            && $using > 0) {
                            if ($vorcher['type'] == "0") {
                                $percent = ($vorcher['percent'] / 100) * $total_price;;
                                // nếu số tiền giảm lớn hơn số tiền tối đa
                                if ($percent >= (int)$vorcher['max_amount']) {
                                    $data['sale_percent'] = $vorcher['percent'];
                                    $data['reduce_amount'] = $vorcher['max_amount'];
                                    $data['promotion_id'] = $vorcher['_id'];
                                    $data['code_vorcher'] = $vorcher['promotion_code'];
                                    $pricepo = $total_price - $percent;
                                    $floor = $floor - (float)$vorcher['max_amount'];
                                    $total_prices = $pricepo;

                                    $total_price = $total_prices;

                                } else {
                                    $reduce_amount = $percent;
                                    $data['sale_percent'] = $vorcher['percent'];
                                    $data['reduce_amount'] = $reduce_amount;
                                    $data['promotion_id'] = $vorcher['_id'];
                                    $data['code_vorcher'] = $vorcher['percent'];
                                    $pricepo = $total_price - $reduce_amount;
                                    $total_prices = $pricepo;
                                    $total_price = $total_prices;
                                    $floor = $floor - (float)$vorcher['max_amount'];
                                }
                            }
                            elseif ($vorcher['type'] == "1") {

                                if ($floor <= (int)$vorcher['amount']) {
                                    $percent = (70 / 100) * $total_price;
                                    $percentage = ($percent / $total_price) * 100;
                                    $reduce_amount = $percent;
                                    $pricepo = $total_price - $percent;
                                    $floor = 0;
                                    $total_prices = $pricepo;
                                    $total_price = $total_prices;
                                    $data['sale_percent'] = $percentage;
                                    $data['reduce_amount'] = $reduce_amount;
                                    $data['promotion_id'] = $vorcher['_id'];
                                    $data['code_vorcher'] = $vorcher['promotion_code'];
                                } else {
                                    $pricepo = $total_price - (int)$vorcher['amount'];
                                    $percent = ($total_price / 100) * $total_price;
                                    $percentage = ($percent / $total_price) * 100;
                                    $total_prices = $pricepo;
                                    $reduce_amount = $percent;
                                    $total_price = $total_prices;
                                    $floor = $floor - (int)$vorcher['max_amount'];
                                    $data['sale_percent'] = $percentage;
                                    $data['reduce_amount'] = $reduce_amount;
                                    $data['promotion_id'] = $vorcher['_id'];
                                    $data['code_vorcher'] = $vorcher['promotion_code'];
                                }
                            }
                        }else {
                            $data['code_vorcher'] = "";
                            $data['promotion_id'] = "";
                            $data['sale_percent'] = "";
                            $data['reduce_amount'] = "";
                        }
                    }
                    else {

                        if ($startDayTimestamp <= $currentTimestamp
                            && $endtDayTimestamp >= $currentTimestamp
                            && $vorcher['kiosk_id'] == $request['kiosk_id']
                            && $using > 0) {
                            // Thực hiện logic khi voucher hợp lệ
                            //giảm bằng phần trăm

                            if ($vorcher['type'] == "0") {
                                $percent = ($vorcher['percent'] / 100) * $total_price;;

                                if ($percent >= (int)$vorcher['max_amount']) {
                                    $data['sale_percent'] = $vorcher['percent'];
                                    $data['reduce_amount'] = $vorcher['max_amount'];
                                    $data['promotion_id'] = $vorcher['_id'];
                                    $data['code_vorcher'] = $vorcher['promotion_code'];
                                    $pricepo = $total_price - (float)$vorcher['max_amount'];
                                    $total_prices = $pricepo;
                                    $total_price = $total_prices;

                                } else {
                                    $reduce_amount = $percent;
                                    $data['sale_percent'] = $vorcher['percent'];
                                    $data['reduce_amount'] = $reduce_amount;
                                    $data['promotion_id'] = $vorcher['_id'];
                                    $data['code_vorcher'] = $vorcher['percent'];
                                    $pricepo = $total_price - $reduce_amount;
                                    $total_prices = $pricepo;
                                    $total_price = $total_prices;

                                }
                            }
                            elseif ($vorcher['type'] == "1") {

                                if ($total_price <= (int)$vorcher['amount']) {
                                    $percent = (70 / 100) * $total_price;
                                    $percentage = ($percent / $total_price) * 100;
                                    $reduce_amount = $percent;
                                    $pricepo = $total_price - $percent;

                                    $total_prices = $pricepo;
                                    $total_price = $total_prices;
                                    $data['sale_percent'] = $percentage;
                                    $data['reduce_amount'] = $reduce_amount;
                                    $data['promotion_id'] = $vorcher['_id'];
                                    $data['code_vorcher'] = $vorcher['promotion_code'];
                                } else {
                                    $pricepo = $total_price - (int)$vorcher['amount'];
                                    $percent = ($total_price / 100) * $total_price;
                                    $percentage = ($percent / $total_price) * 100;
                                    $total_prices = $pricepo;
                                    $reduce_amount = $percent;
                                    $total_price = $total_prices;
                                    $data['sale_percent'] = $percentage;
                                    $data['reduce_amount'] = $reduce_amount;
                                    $data['promotion_id'] = $vorcher['_id'];
                                    $data['code_vorcher'] = $vorcher['promotion_code'];
                                }
                            }
                        } else {
                            $data['code_vorcher'] = "";
                            $data['promotion_id'] = "";
                            $data['sale_percent'] = "";
                            $data['reduce_amount'] = "";
                        }
                    }

                }
                else {
                    $data['code_vorcher'] = "";
                    $data['sale_percent'] = "";
                    $data['promotion_id'] = "";
                    $data['reduce_amount'] = "";
                }
            } else {
                $data['code_vorcher'] = "";
                $data['promotion_id'] = "";
                $data['sale_percent'] = "";
                $data['reduce_amount'] = "";
            }
            //Kết thúc giảm giá
            $userSeller = User::find($request['id_seller']);

            $data['name_user_buy'] = $user['name'];
            $data['id_seller'] = $request['id_seller'];
            $data['name_seller'] = $userSeller['name'];
            $data['admin_amount'] = $floor;
//          $kiot_slug_price = KiotSub::find($request['kiosk_sub_id']);
            $data['price'] = (float)$sub_kiot['price'];
            $data['status'] = 0; // 0 đang chờ hoàn thành
            $data['kiosk_sub_name'] = $sub_kiot['name'];
            $data['quality'] = $quality;
            $data['total_price'] = (float)$total_price;
            $reseller_amounts = "";
            $ref_id = "";

            if (!empty($request['ref_user_id'])) {
                $resller = Reseller::find($request['ref_user_id']);

                if ($resller) {
                    // Kiểm tra logic mua hàng
                        // Kiểm tra nếu 'percent' và 'user_id' tồn tại
                        if (isset($resller['percent']) && isset($resller['user_id'])) {
                            $percent = (int)$resller['percent'];
                            $reseller_amounts = ($percent / 100) * (float)$total_price;
                            $ref_id = $resller['user_id'];

                            // Lấy thông tin người giới thiệu
                            $userBuy = User::find($resller['user_id']);
                            $data['name_ref'] = $userBuy['name'] ?? 'Unknown';
                        } else {
                            // Xử lý khi thiếu 'percent' hoặc 'user_id'
                            // Log lỗi hoặc phản hồi
//                echo 'Không tìm thấy giá trị percent hoặc user_id trong reseller.';
                        }

                } else {
                    // Không tìm thấy reseller với ref_user_id
//        echo 'Không tìm thấy reseller với ref_user_id: ' . $request['ref_user_id'];
                }
            }


            $data['reseller_amount'] = $reseller_amounts;
            $data['ref_user_id'] = $ref_id;

            $data['is_delete'] = 1; //1 Hiện thị ; 0 đã xóa
            //kiểm tra Thanh toán trừ tiền trong ví
            $balance = Balance::where('user_id', $user['_id'])->first();
            if (empty($balance) || $balance == null) {
                return $this->sendError('You have not linked a payment wallet', [], 400);
            }
            elseif ($balance['balance'] == 0 || (float)$balance['balance'] < (float)$total_price) {
                return $this->sendError('The balance in the wallet is not enough to make payment', [], 400);
            }
            else {
                // trừ tiền ng mua
                $balanceTop = Balance::where('user_id', $user['_id'])->first();
                $balanceTop['balance'] = (float)$balanceTop['balance'] - (float)$total_price;
                $balanceTop->save();

                // Log giao dịch trừ tiền người mua
                $data_request['id_balance'] = $balance['_id'];
                $data_request['user_id'] = $user['_id'];
                $data_request['action_user'] = "Buy product/service name " . $sub_kiot['name'];
                $data_request['transaction_status'] = "buy";
                $data_request['last_balance'] = $balance['balance'];
                $data_request['current_balance'] = $balanceTop['balance'];
                $data_request['balance'] = (float)$total_price;
                $data_request['status'] = '3';
                $balance_log = balance_log::create($data_request);
                // end log

                // chuyển tiền cho admin tạm giữ
                $admin = User::where('role', 4)->first();
                if ($admin) {
                    $adminBalance = Balance::where('user_id', $admin['_id'])->first();
                    if ($adminBalance) {
                        $lastAdminBalance = $adminBalance['balance'];
                        $adminBalance['balance'] = (float)$adminBalance['balance'] + (float)$total_price;
                        $adminBalance->save();

                        // Log giao dịch chuyển tiền cho admin
                        balance_log::create([
                            'id_balance' => $adminBalance['_id'],
                            'user_id' => $admin['_id'],
                            'action_user' => "Hold payment for order " . $order_code,
                            'transaction_status' => "hold",
                            'last_balance' => $lastAdminBalance,
                            'current_balance' => $adminBalance['balance'],
                                'balance' => (float)$total_price,
                            'status' => '3'
                        ]);
                        $userSeller = User::find($request['id_seller']);
                        $sellerBalance = Balance::where('user_id', $userSeller['_id'])->first();

                        if (!$sellerBalance) {
                            // Nếu không tìm thấy bản ghi, tạo mới
                            $sellerBalance = new Balance();
                            $sellerBalance->user_id = $userSeller['_id'];
                            $sellerBalance->hold_balance = 0; // Khởi tạo hold_balance
                        }

// Lấy giá trị hold_balance hiện tại
                        $hold_balance = (float)($sellerBalance->hold_balance ?? 0);

// Cập nhật hold_balance
                        $sellerBalance->hold_balance = $hold_balance + (float)$total_price - (float)$floor - (float)$reseller_amounts;

// Lưu lại thay đổi
                        $sellerBalance->save();

                    }
                }
                // end chuyển tiền cho admin tạm giữ
                $order = Order::create($data);
                // chuyển lại tiền cho seller
                dispatch(function () use ($order, $total_price, $admin, $floor) {
                    try {
                        // Tìm order để check status
                        $checkOrder = Order::find($order['_id']);
                        if ($checkOrder && $checkOrder['status'] == 0) {
                            $adminBalance = Balance::where('user_id', $admin['_id'])->first();

                            // Tính toán số tiền
                            $platformFee = (float)$floor; // Phí sàn
                            $sellerAmount = (float)$total_price - $platformFee; // Số tiền seller nhận được

                            // Trừ toàn bộ số tiền từ admin role 4
                            $lastAdminBalance = $adminBalance['balance'];
                            $adminBalance['balance'] = (float)$adminBalance['balance'] - (float)$total_price;
                            $adminBalance->save();

                            // Log trừ tiền admin
                            balance_log::create([
                                'id_balance' => $adminBalance['_id'],
                                'user_id' => $admin['_id'],
                                'action_user' => "Release hold payment for order " . $order['order_code'],
                                'transaction_status' => 'release',
                                'last_balance' => $lastAdminBalance,
                                'current_balance' => $adminBalance['balance'],
                                'balance' => (float)$total_price,
                                'status' => '3'
                            ]);

                            // Cộng lại phí sàn cho admin
                            $adminBalance['balance'] = (float)$adminBalance['balance'] + $platformFee;
                            $adminBalance->save();

                            // Log cộng phí sàn cho admin
                            balance_log::create([
                                'id_balance' => $adminBalance['_id'],
                                'user_id' => $admin['_id'],
                                'action_user' => "Platform fee for order " . $order['order_code'],
                                'transaction_status' => 'fee',
                                'last_balance' => (float)$adminBalance['balance'] - $platformFee,
                                'current_balance' => $adminBalance['balance'],
                                'balance' => $platformFee,
                                'status' => '3'
                            ]);

                            // Chuyển tiền cho seller (đã trừ phí sàn)
                            $sellerBalance = Balance::where('user_id', $order['id_seller'])->first();
                            $lastSellerBalance = $sellerBalance['balance'];
                            $sellerBalance['balance'] = (float)$sellerBalance['balance'] + $sellerAmount;
                            $sellerBalance->save();

                            // Log chuyển tiền cho seller
                            balance_log::create([
                                'id_balance' => $sellerBalance['_id'],
                                'user_id' => $order['id_seller'],
                                'action_user' => "Receive payment (after platform fee) for order " . $order['order_code'],
                                'transaction_status' => 'receive',
                                'last_balance' => $lastSellerBalance,
                                'current_balance' => $sellerBalance['balance'],
                                'balance' => $sellerAmount,
                                'status' => '3'
                            ]);
                        }
                    } catch (\Exception $e) {
                        \Log::error('Failed to process delayed payment: ' . $e->getMessage());
                    }
                })->delay(now()->addMinutes(1));
                // Kết thúc chuyển tiền cho seller
                //sau khi hoàn thành đơn hàng vorcher đc sẽ đc trừ
                if (!empty($request['code_vorcher'])) {
                    $vorcher = Promotion::where('promotion_code', $request['code_vorcher'])->where('status', 1)->first();
                    if (!empty($vorcher)) {
                        $using = (int)$vorcher['total_for_using'];

                        if ($startDayTimestamp <= $currentTimestamp
                            && $endtDayTimestamp >= $currentTimestamp
                            && $vorcher['kiosk_id'] == $request['kiosk_id']
                            && $using > 0) {
                            $vorchers = Promotion::where('promotion_code', $request['code_vorcher'])->where('status', 1)->first();

                            $usings = $vorchers['total_for_using'] - 1;
                            $vorchers['total_for_using'] = $usings;
                            $vorchers->save();
                        }

                    }
                }
                //Kết thúc
                $oderProduct = kiosk_sub_product::where('kiosk_sub_id', $order['kiosk_sub_id'])
                    ->where('status', 1)
                    ->take($quality)  // Lấy theo bản ghi
                    ->get();

                // Cập nhật status = 0 cho các bản ghi đó
                $value_order_product = [];
                foreach ($oderProduct as $key => $product) {
                    $product->status = 0;
                    $product->save();
                    $value_order_product[] = [
                        'value' => $product['value'],
                        'id' => $product['_id']
                    ];
                }
                $value_order_product = json_encode($value_order_product);
                $userSeller['checkPoint'] = (int)$userSeller['checkPoint'] + (float)$total_price;
                $userSeller->save();
                $user['checkPoint'] = (int)$user['checkPoint'] + (float)$total_price;
                $user->save();
                OrderDetail::create([
                    'order_code' => $order_code,
                    'kiosk_id' => $request['kiosk_id'],
                    'user_id' => $user['_id'],
                    'price' => (int)$total_price,
                    'value' => $value_order_product,
                ]);


//Update quality
                if ($request['status_kiot'] == 'service') {
                    $datakiot['quantity'] = null;
                } else {
                    $datakiot['quantity'] = $sub_kiot['quantity'] - $order['quality'];
                }

//            $datakiot['quantity'] = $sub_kiot['quantity'] - $order['quality'];

                $sub_kiot->update($datakiot);
                $success['expires_at'] = now()->addDays(3);
                $success['name'] = $order->order_code;
                return $this->sendResponse($order, 'Order successful.');
            }
//
        } catch (\Exception $e) {
            // Trả về phản hồi lỗi
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    public function guaranteeOrder(Request $request) {
        try {
            $user_seller = auth()->user();

            $order_old = Order::where('order_code', $request['order_code'])->where('id_seller', $user_seller['_id'])->first();

            if (empty($order_old)) {
                return $this->sendError('Order does not exist.', [], 400);
            }
            $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';  // Chuỗi bao gồm cả chữ cái và số
            $maxAttempts = 1000; // Giới hạn số lần thử tạo mã (để tránh vòng lặp vô tận)
            $attempts = 0;  // Biến đếm số lần thử

            $sub_kiot = KiotSub::find($order_old['kiosk_sub_id']);
            $kiot = Kiot::find($order_old['kiosk_id']);
            if ($order_old['user_id'] == $user_seller['_id']) {
                return $this->sendError('You are not allowed to buy products from your own kiosk.', [], 400);
            }
            if ($kiot['status'] == 0) {
                return $this->sendError('The product is being approved and cannot be purchased', [], 400);
            }
            $category_sub = CategorySlug::find($kiot['category_sub_id']);

            $floor_dis = $category_sub['floor_dis'];
            // lấy phí sàn của seller
            $seller_info = User::find($order_old['id_seller']);
            $floor_dis_seller = $seller_info['penalty_tax'];
            if ($seller_info['is_banned'] == 1 && $floor_dis_seller > $floor_dis) {
                $floor_dis = $floor_dis_seller;
            }else{
                $floor_dis = $category_sub['floor_dis'];
            }
            // Kiểm tra xem sản phẩm có tồn tại không
            if (!$sub_kiot) {
                return $this->sendError('Kiosk product not found.', [], 404);
            }
            do {
                $randomString = '';
                $length = 7; // Chiều dài mã ban đầu là 9

                // Tạo mã ngẫu nhiên
                for ($i = 0; $i < $length; $i++) {
                    $randomString .= $characters[rand(0, strlen($characters) - 1)];
                }

                // Thêm 1 chữ số ngẫu nhiên vào cuối
                $randomString .= rand(0, 9);

                // Kiểm tra xem mã đã tồn tại trong cơ sở dữ liệu chưa
                $orderExists = Order::where('order_code', $randomString)->exists();

                // Nếu đã hết ký tự có thể tạo ra, tăng chiều dài của mã lên 2 ký tự nữa
                if ($orderExists) {
                    $length += 2; // Tăng chiều dài mã lên nếu trùng lặp
                }
                $attempts++;

            } while ($orderExists && $attempts < $maxAttempts); // Lặp cho đến khi tạo được mã duy nhất hoặc đạt giới hạn

            // Nếu đã thử quá số lần giới hạn mà vẫn trùng lặp, trả về lỗi
            if ($attempts >= $maxAttempts) {
                return $this->sendError('Could not generate unique order code. Please try again later.', [], 400);
            }
            $quality = (int)$request['quality'];
//            $qtyKiot = (int)$sub_kiot['quantity'];
            $qtyKiot = kiosk_sub_product::where('kiosk_sub_id', $order_old['kiosk_sub_id'])
                    ->where('status', 1)
                    ->count() ?? 0;
            if ($order_old['status_kiot'] == 'service') {
                $data['service_product'] = $sub_kiot['name'];
                $data['service_waitingdays'] = $order_old['service_waitingdays'];

            } else {
                $data['service_product'] = null;
                $data['service_waitingdays'] = null;
                if ((int)$qtyKiot < $quality) {
                    return $this->sendError('There is not enough quantity in the warehouse.', [], 400);
                }
            }
            // Sau khi đã tạo mã duy nhất, tiếp tục với các thao tác khác
            $order_code = $randomString;
            $data['order_code'] = "BH_" . $order_code; // Lưu mã order_code vào dữ liệu
            $data['name_seller'] = $order_old['name_seller'];
            $data['name_user_buy'] = $order_old['name_user_buy'];
            $data['kiosk_id'] = $order_old['kiosk_id'];
            $data['id_seller'] = $order_old['id_seller'];
            $data['status_kiot'] = $order_old['status_kiot'];
            $data['user_id'] = $order_old['user_id'];
            $data['name_seller'] = $order_old['name_seller'];
            $data['kiosk_sub_name'] = $order_old['kiosk_sub_name'];
            $data['price'] = $order_old['price'];
            $data['total_price'] = 0;
            $data['ref_user_id'] = $order_old['ref_user_id'] ;
            $data['total_price'] = $order_old['code_vorcher'] ;
            $data['sale_percent'] = $order_old['sale_percent'] ;
            $data['promotion_id'] = $order_old['promotion_id'] ;
            $data['reduce_amount'] = $order_old['reduce_amount'] ;
            $data['code_vorcher'] = $order_old['code_vorcher'] ;
            $data['service_product'] = $order_old['service_product'] ;
            $data['service_waitingdays'] = $order_old['service_waitingdays'] ;
            $data['admin_amount'] = 0;
            $data['status'] = -1;
            $data['reseller_amount'] = 0;
            $data['quality'] = $quality;
            $order = Order::create($data);

            $oderProduct = kiosk_sub_product::where('kiosk_sub_id', $order_old['kiosk_sub_id'])
                ->where('status', 1)
                ->take($quality)  // Lấy theo bản ghi
                ->get();

            // Cập nhật status = 0 cho các bản ghi đó
            $value_order_product = [];
            foreach ($oderProduct as $key => $product) {
                $product->status = 0;
                $product->save();
                $value_order_product[] = [
                    'value' => $product['value'],
                    'id' => $product['_id']
                ];
            }
            $value_order_product = json_encode($value_order_product);
//            $userSeller['checkPoint'] = (int)$userSeller['checkPoint'] + (int)$total_price;
//            $userSeller->save();
//            $user['checkPoint'] = (int)$user['checkPoint'] + (int)$total_price;
//            $user->save();
            OrderDetail::create([
                'order_code' => $order_code,
                'kiosk_id' => $order_old['kiosk_id'],
                'user_id' => $order_old['user_id'],
                'price' => 0,
                'value' => $value_order_product,
            ]);
            if ($request['status_kiot'] == 'service') {
                $datakiot['quantity'] = null;
            } else {
                $datakiot['quantity'] = $sub_kiot['quantity'] - $order['quality'];
            }

//            $datakiot['quantity'] = $sub_kiot['quantity'] - $order['quality'];

            $sub_kiot->update($datakiot);
            $success['expires_at'] = now()->addDays(3);
            $success['name'] = $order->order_code;
            return $this->sendResponse($order, 'Order successful.');
        }catch (\Exception $e) {
            // Trả về phản hồi lỗi
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    public function AddPrevOrder(Request $request)
    {
        try {
            $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';  // Chuỗi bao gồm cả chữ cái và số
            $maxAttempts = 1000; // Giới hạn số lần thử tạo mã (để tránh vòng lặp vô tận)
            $attempts = 0;  // Biến đếm số lần thử

            $sub_kiot = KiotSub::find($request['kiosk_sub_id']);
            $kiot = Kiot::find($request['kiosk_id']);
            if ($kiot['status'] == 0) {
                return $this->sendError('The product is being approved and cannot be purchased', [], 400);
            }
            $category_sub = CategorySlug::find($kiot['category_sub_id']);
            $floor_dis = $category_sub['floor_dis'];
            // lấy phí sàn của seller
            $seller_info = User::find($request['id_seller']);
            $floor_dis_seller = $seller_info['penalty_tax'];
            if ($seller_info['is_banned'] == 1 && $floor_dis_seller > $floor_dis) {
                $floor_dis = $floor_dis_seller;
            }else{
                $floor_dis = $category_sub['floor_dis'];
            }
            // Kiểm tra xem sản phẩm có tồn tại không
            if (!$sub_kiot) {
                return $this->sendError('Kiosk product not found.', [], 404);
            }
            do {
                $randomString = '';
                $length = 7; // Chiều dài mã ban đầu là 9

                // Tạo mã ngẫu nhiên
                for ($i = 0; $i < $length; $i++) {
                    $randomString .= $characters[rand(0, strlen($characters) - 1)];
                }

                // Thêm 1 chữ số ngẫu nhiên vào cuối
                $randomString .= rand(0, 9);

                // Kiểm tra xem mã đã tồn tại trong cơ sở dữ liệu chưa
                $orderExists = Order::where('order_code', $randomString)->exists();

                // Nếu đã hết ký tự có thể tạo ra, tăng chiều dài của mã lên 2 ký tự nữa
                if ($orderExists) {
                    $length += 2; // Tăng chiều dài mã lên nếu trùng lặp
                }
                $attempts++;

            } while ($orderExists && $attempts < $maxAttempts); // Lặp cho đến khi tạo được mã duy nhất hoặc đạt giới hạn

            // Nếu đã thử quá số lần giới hạn mà vẫn trùng lặp, trả về lỗi
            if ($attempts >= $maxAttempts) {
                return $this->sendError('Could not generate unique order code. Please try again later.', [], 400);
            }
            $quality = (int)$request['quality'];
//            $qtyKiot = (int)$sub_kiot['quantity'];

//            if ($request['status_kiot'] == 'service') {
//                $data['service_product'] = $sub_kiot['name'];
//                $data['service_waitingdays'] = $request['service_waitingdays'];
//
//            } else {
//                $data['service_product'] = null;
//                $data['service_waitingdays'] = null;
//                if ($qtyKiot < $quality) {
//                    return $this->sendError('There is not enough quantity in the warehouse.', [], 400);
//                }
//            }
            // Sau khi đã tạo mã duy nhất, tiếp tục với các thao tác khác
            $order_code = $randomString;

            $total_price = (int)$sub_kiot['price'] * (int)$quality;

            //Tính phần trăm trên tổng số tiền
            $floor = ((int)$floor_dis / 100) * (int)$total_price;

            $user = auth()->user();
            $data = $request->all();
            $data['user_id'] = $user['_id'];
            $data['order_code'] = $order_code; // Lưu mã order_code vào dữ liệu
            //Lấy giá sản phẩm
            //Giá giảm
            if ($request['code_vorcher'] != null || $request['code_vorcher'] != "null" || !empty($request['code_vorcher']) || $request['code_vorcher'] == "") {
                $data['code_vorcher'] = $request['code_vorcher'];
                $data['sale_percent'] = $request['sale_percent'];
                $data['reduce_amount'] = $request['reduce_amount'];
            } else {
                $data['code_vorcher'] = "";
                $data['sale_percent'] = "";
                $data['reduce_amount'] = "";
            }
            $userSeller = User::find($request['id_seller']);
            $data['id_seller'] = $request['id_seller'];
            $data['name_seller'] = $userSeller['name'];
            $data['admin_amount'] = $floor;
            $data['price'] = (int)$sub_kiot['price'];
            $data['status'] = 7; // 0 đang chờ hoàn thành
            $data['kiosk_sub_name'] = $sub_kiot['name'];
            $data['quality'] = $quality;
            $data['total_price'] = (int)$total_price;
            $data['is_delete'] = 1; //1 Hiện thị ; 0 đã xóa

            //kiểm tra Thanh toán trừ tiền trong ví
            $balance = Balance::where('user_id', $user['_id'])->first();
            if (empty($balance) || $balance == null) {
                return $this->sendError('You have not linked a payment wallet', [], 400);
            } elseif ($balance['balance'] == 0 || (int)$balance['balance'] < (int)$total_price) {
                $status['errorStatus'] = 'scarcely';
                return $this->sendError('The balance in the wallet is not enough to make payment', $status, 400);
            } else {
                $balanceTop = Balance::where('user_id', $user['_id'])->first();
                $balanceTop['balance'] = (int)$balanceTop['balance'] - (int)$total_price;
                $balanceTop->save();
                $data_request['id_balance'] = $balance['_id'];
                $data_request['user_id'] = $user['_id'];
                $data_request['action_user'] = "Buy product/service name " . $sub_kiot['name'];
                $data_request['transaction_status'] = "buy";
                $data_request['last_balance'] = $balance['balance'];
                $data_request['current_balance'] = $balanceTop['balance'];
                $data_request['balance'] = (int)$total_price;
                $data_request['status'] = 3;

                $balance_log = balance_log::create($data_request);
                $order = Order::create($data);

                $success['expires_at'] = now()->addDays(3);
                $success['name'] = $order->order_code;
                return $this->sendResponse($order, 'Order successful.');
            }


        } catch (\Exception $e) {
            // Trả về phản hồi lỗi
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    public function GetOrderByID($id_user)
    {
        $kiots = Kiot::where('user_id', $id_user)->pluck('_id');

        if ($kiots->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No kiot found for this user.',
                'data' => []
            ], 404);
        }

        // Lấy giá trị status_kiot từ query parameters
        $status_kiot = request()->query('status_kiot', null);

        // Lấy giá trị page từ query parameters
        $page = request()->query('page', 1);

        // Tạo query cơ bản
        $orderQuery = Order::whereIn('kiosk_id', $kiots)
            ->where('status', '!=', 7)
            ->orderBy('created_at', 'desc');


        // Nếu có giá trị status_kiot, thêm điều kiện lọc vào query
        if ($status_kiot) {
            $orderQuery->where('status_kiot', $status_kiot);
        }

        // Phân trang
        $orders = $orderQuery->paginate(5, ['*'], 'page', $page);

        if ($orders->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No orders found for the kiosks.',
                'data' => []
            ], 404);
        }

        $arrorder = [];
        foreach ($orders as $od) {
            $user = User::find($od['user_id']);
            $kiots_oder = Kiot::find($od['kiosk_id']);
            $arrorder[] = [
                'order_code' => $od['order_code'],
                'kiosk_id' => $od['kiosk_id'],
                'quality' => $od['quality'],
                'user_id' => $od['user_id'],
                'name_user' => $user->name,
                'code_vorcher' => $od['code_vorcher'],
                'id_post' => $kiots_oder['id_post'],
                'reduce_amount' => $od['reduce_amount'],
                'id_seller' => $od['id_seller'],
                'name_seller' => $od['name'],
                'admin_amount' => $od['admin_amount'],
                'order_requirement' => $od['order_requirement'],
                'kiosk_sub_name' => $od['kiosk_sub_name'],
                'price' => $od['price'],
                'created_at' => $od['created_at'],
                'updated_at' => $od['updated_at'],
                'total_price' => $od['total_price'],
                'refund_money' => $od['refund_money'],
                'status_kiot' => $od['status_kiot'],
                'name_ref' => $od['name_ref'],
                'ref_user_id' => $od['ref_user_id'],
                'status' => $od['status']
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Orders retrieved successfully.',
            'data' => $arrorder,
            'pagination' => [
                'total' => $orders->total(),
                'per_page' => $orders->perPage(),
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'next_page_url' => $orders->nextPageUrl(),
                'prev_page_url' => $orders->previousPageUrl(),
            ]
        ], 200);
    }

    public function SearchOrderSeller($id_user)
    {
        $order_code = $_GET['order_code'];
        $name_order = $_GET['name_order'];
        $status_order = $_GET['status_order'];

        // Search order
        $kiots = Kiot::where('user_id', $id_user)->pluck('_id');

        if ($kiots->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No kiot found for this user.',
                'data' => []
            ], 404);
        }

        // Lấy giá trị status_kiot từ query parameters
        $status_kiot = request()->query('status_kiot', null);

        // Lấy giá trị page từ query parameters
        $page = request()->query('page', 1);

        // Tạo query cơ bản
        $orderQuery = Order::whereIn('kiosk_id', $kiots)->where('status', '!=', 7)->orderBy('created_at', 'desc');

        // Nếu có giá trị status_kiot, thêm điều kiện lọc vào query
        if ($status_kiot) {
            $orderQuery->where('status_kiot', $status_kiot);
        }

        // Thêm điều kiện lọc theo order_code nếu có
        if ($order_code) {
            $orderQuery->where('order_code', 'like', '%' . $order_code . '%');
        }

        // Thêm điều kiện lọc theo name_order nếu có
        if ($name_order) {
            $orderQuery->where('name_user_buy', 'like', '%' . $name_order . '%');
        }

        // Thêm điều kiện lọc theo status_order nếu có
        if ($status_order) {
            $orderQuery->where('status', $status_order);
        }

        // Phân trang
        $orders = $orderQuery->paginate(5, ['*'], 'page', $page);

        if ($orders->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No orders found for the kiosks.',
                'data' => []
            ], 404);
        }

        $arrorder = [];
        foreach ($orders as $od) {
            $user = User::find($od['user_id']);
            $kiots_oder = Kiot::find($od['kiosk_id']);
            $rating = $od['is_rating'] ? 1 : 0;
            $report = $od['is_report'] ? 1 : 0;
            $arrorder[] = [
                'order_code' => $od['order_code'],
                'kiosk_id' => $od['kiosk_id'],
                'quality' => $od['quality'],
                'user_id' => $od['user_id'],
                'name_user' => $user->name,
                'code_vorcher' => $od['code_vorcher'],
                'id_seller' => $od['id_seller'],
                'is_rating' => $rating,
                'is_report' => $report,
                'name_seller' => $od['name_seller'],
                'id_post' => $kiots_oder['id_post'],
                'reduce_amount' => $od['reduce_amount'],
                'admin_amount' => $od['admin_amount'],
                'order_requirement' => $od['order_requirement'],
                'kiosk_sub_name' => $od['kiosk_sub_name'],
                'price' => $od['price'],
                'created_at' => $od['created_at'],
                'updated_at' => $od['updated_at'],
                'total_price' => $od['total_price'],
                'status_kiot' => $od['status_kiot'],
                'status' => $od['status']
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Orders retrieved successfully.',
            'data' => $arrorder,
            'pagination' => [
                'total' => $orders->total(),
                'per_page' => $orders->perPage(),
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'next_page_url' => $orders->nextPageUrl(),
                'prev_page_url' => $orders->previousPageUrl(),
            ]
        ], 200);
    }

    public function SearchOrderPrev($id_user)
    {
        $order_code = $_GET['order_code'];

        // Search order
        $kiots = Kiot::where('user_id', $id_user)->pluck('_id');

        if ($kiots->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No kiot found for this user.',
                'data' => []
            ], 404);
        }

        // Lấy giá trị status_kiot từ query parameters
        $status_kiot = request()->query('status_kiot', null);

        // Lấy giá trị page từ query parameters
        $page = request()->query('page', 1);

        // Tạo query cơ bản
        $orderQuery = Order::whereIn('kiosk_id', $kiots)->where('status', 7)->orderBy('created_at', 'desc');

        // Nếu có giá trị status_kiot, thêm điều kiện lọc vào query
        if ($status_kiot) {
            $orderQuery->where('status_kiot', $status_kiot);
        }

        // Thêm điều kiện lọc theo order_code nếu có
        if ($order_code) {
            $orderQuery->where('order_code', 'like', '%' . $order_code . '%');
        }


        // Phân trang
        $orders = $orderQuery->paginate(5, ['*'], 'page', $page);

        if ($orders->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No orders found for the kiosks.',
                'data' => []
            ], 404);
        }

        $arrorder = [];
        foreach ($orders as $od) {
            $user = User::find($od['user_id']);
            $kiots_oder = Kiot::find($od['kiosk_id']);
            $arrorder[] = [
                'order_code' => $od['order_code'],
                'kiosk_id' => $od['kiosk_id'],
                'quality' => $od['quality'],
                'user_id' => $od['user_id'],
                'name_user' => $user->name,
                'code_vorcher' => $od['code_vorcher'],
                'id_seller' => $od['id_seller'],
                'name_seller' => $od['name_seller'],
                'id_post' => $kiots_oder['id_post'],
                'reduce_amount' => $od['reduce_amount'],
                'admin_amount' => $od['admin_amount'],
                'order_requirement' => $od['order_requirement'],
                'kiosk_sub_name' => $od['kiosk_sub_name'],
                'price' => $od['price'],
                'created_at' => $od['created_at'],
                'updated_at' => $od['updated_at'],
                'total_price' => $od['total_price'],
                'status_kiot' => $od['status_kiot'],
                'status' => $od['status']
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Orders retrieved successfully.',
            'data' => $arrorder,
            'pagination' => [
                'total' => $orders->total(),
                'per_page' => $orders->perPage(),
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'next_page_url' => $orders->nextPageUrl(),
                'prev_page_url' => $orders->previousPageUrl(),
            ]
        ], 200);
    }

    public function SearchOrder()
    {
        $user = auth()->user();

        // Lấy các tham số từ request
        $order_code = request()->get('order_code');
        $nameSeller = request()->get('name_seller');

        // Tạo query để tìm đơn hàng cho người dùng đã xác thực
        $orders = Order::where('user_id', $user['_id'])->orderBy('created_at', 'desc');

        // Kiểm tra điều kiện tìm kiếm: order_code hay name_seller
        if ($order_code) {
            // Nếu có order_code, tìm theo order_code
            $orders->where('order_code', 'like', '%' . $order_code . '%');
        } elseif ($nameSeller) {
            // Nếu không có order_code, tìm theo keyword trong name_seller
            $orders->where('name_seller', 'like', '%' . $nameSeller . '%');
        }

        // Paginate kết quả (ví dụ 10 đơn hàng trên mỗi trang)
        $orders = $orders->paginate(10);

        // Kiểm tra xem có đơn hàng nào không
        if ($orders->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No orders found for the user.',
                'data' => []
            ], 404);
        }

        // Trả về dữ liệu đơn hàng và thông tin phân trang
        return response()->json([
            'success' => true,
            'message' => 'Orders retrieved successfully.',
            'data' => $orders->items(),
            'pagination' => [
                'total' => $orders->total(),
                'per_page' => $orders->perPage(),
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'next_page_url' => $orders->nextPageUrl(),
                'prev_page_url' => $orders->previousPageUrl(),
            ]
        ], 200);
    }

    public function GetPreOrder($id_user)
    {
        $kiots = Kiot::where('user_id', $id_user)->pluck('_id');


        if ($kiots->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No kiot found for this user.',
                'data' => []
            ], 404);
        }

        // Lấy giá trị status_kiot từ query parameters
        $status_kiot = request()->query('status_kiot', null);

        // Lấy giá trị page từ query parameters
        $page = request()->query('page', 1);

        // Tạo query cơ bản với điều kiện chỉ lấy đơn hàng có status = 7
        $orderQuery = Order::whereIn('kiosk_id', $kiots)
            ->where('status', 7) // Thêm điều kiện status = 7
            ->orderBy('created_at', 'desc');

        // Nếu có giá trị status_kiot, thêm điều kiện lọc vào query
        if ($status_kiot) {
            $orderQuery->where('status_kiot', $status_kiot);
        }

        // Phân trang
        $orders = $orderQuery->paginate(5, ['*'], 'page', $page);

        if ($orders->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No orders found for the kiosks.',
                'data' => []
            ], 404);
        }

        $arrorder = [];
        foreach ($orders as $od) {
            $user = User::find($od['user_id']);
            $kiots_oder = Kiot::find($od['kiosk_id']);
            $arrorder[] = [
                'order_id' => $od['_id'],
                'order_code' => $od['order_code'],
                'kiosk_id' => $od['kiosk_id'],
                'quality' => $od['quality'],
                'user_id' => $od['user_id'],
                'name_user' => $user->name,
                'code_vorcher' => $od['code_vorcher'],
                'id_post' => $kiots_oder['id_post'],
                'reduce_amount' => $od['reduce_amount'],
                'id_seller' => $od['id_seller'],
                'name_seller' => $od['name'],
                'admin_amount' => $od['admin_amount'],
                'order_requirement' => $od['order_requirement'],
                'kiosk_sub_name' => $od['kiosk_sub_name'],
                'price' => $od['price'],
                'created_at' => $od['created_at'],
                'updated_at' => $od['updated_at'],
                'total_price' => $od['total_price'],
                'status_kiot' => $od['status_kiot'],
                'status' => $od['status']
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Orders retrieved successfully.',
            'data' => $arrorder,
            'pagination' => [
                'total' => $orders->total(),
                'per_page' => $orders->perPage(),
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'next_page_url' => $orders->nextPageUrl(),
                'prev_page_url' => $orders->previousPageUrl(),
            ]
        ], 200);
    }

    public function QuantityOrder($id_kiot)
    {
        // Tính tổng số lượng đã mua từ tất cả các đơn hàng có kiosk_id tương ứng
        $totalQuantity = Order::where('kiosk_id', $id_kiot)->where('status', '!=', 7)->sum('quality');

        // In ra kết quả
        return $totalQuantity;
    }

    function getTotalBuyAndSellByUser($userId)
    {
        $buyStatistics = Order::where('user_id', $userId)
            ->where('status', '!=', 7)
            ->sum('quality');

        $sellStatistics = Order::where('id_seller', $userId)
            ->where('status', '!=', 7)
            ->sum('quality');

        return response()->json([
            'total_buy' => $buyStatistics,
            'total_sell' => $sellStatistics,
        ]);
    }

    public function VerifyOrder(Request $request)
    {
        try {

            $order = Order::find($request['order_id']);
            $status = $request['status'];

            if ($status == "accept") {
//                $sub_kiot = KiotSub::find($order['kiosk_sub_id']);
                $curQty = kiosk_sub_product::where('kiosk_sub_id', $order['kiosk_sub_id'])
                        ->where('status', 1)
                        ->count() ?? 0;
//                $curQty = $sub_kiot['quantity'];
                $quality = $order->quality;
                if ($quality <= (int)$curQty) {
                    $sub_kiot = KiotSub::find($order['kiosk_sub_id']);
                    $order['status'] = 0;
                    $order->save();

                    $oderProduct = kiosk_sub_product::where('kiosk_sub_id', $order['kiosk_sub_id'])
                        ->where('status', 1)
                        ->take($quality)  // Lấy theo bản ghi
                        ->get();
                    // Cập nhật status = 0 cho các bản ghi đó

                    $value_order_product = [];
                    foreach ($oderProduct as $product) {
                        $product->status = 0;
                        $product->save();
                        $value_order_product[] = [
                            'value' => $product['value'],
                            'id' => $product['_id']
                        ];
                    }
                    $value_order_product = json_encode($value_order_product);
                    OrderDetail::create([
                        'order_code' => $order['order_code'],
                        'kiosk_id' => $order['kiosk_id'],
                        'user_id' => $order['user_id'],
                        'price' => $order['total_price'],
                        'value' => $value_order_product,
                    ]);
//Update quality
                    $datakiot['quantity'] = $sub_kiot['quantity'] - $order['quality'];

                    $sub_kiot->update($datakiot);

                    return $this->sendResponse($order, 'Order update successful.');
                } else {
                    return $this->sendError(400, 'Quantity is not enough');
                }

            } else {
                $order['status'] = 2;
                $order->save();
                return $this->sendResponse($order, 'Cancel order successfully.');

            }


        } catch (\Exception $e) {
            // Trả về phản hồi lỗi
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    public function GetOrderSeller()
    {
        $user = auth()->user();

        // Fetch paginated orders for the authenticated user
        $orders = Order::where('ref_user_id', $user['_id'])->orderBy('created_at', 'desc')->paginate(5);

        $ordersWithRating = $orders->map(function ($order) {
            $rating = RatingProduct::where('order_id', $order['_id'])->first();
            $repory = ReportOrder::where('id_order', $order['_id'])->first();
            $order->is_rating = $rating ? 1 : 0;
            $order->is_report = $repory ? 1 : 0;
            return $order;
        });
        // Check if there are any orders for the user
        if ($orders->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No orders found for the user.',
                'data' => []
            ], 404);
        }

        // Return the paginated orders and pagination info
        return response()->json([
            'success' => true,
            'message' => 'Orders retrieved successfully.',
            'data' => $ordersWithRating,
            'pagination' => [
                'total' => $orders->total(),
                'per_page' => $orders->perPage(),
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'next_page_url' => $orders->nextPageUrl(),
                'prev_page_url' => $orders->previousPageUrl(),
            ]
        ], 200);
    }

    public function VerifyOrdersSrvice(Request $request)
    {
        try {
            $order = Order::where('order_code', $request['order_id'])->first();

            $status = $request['status'];
            if ($status == "accept") {
                $order['status'] = 8;
                $order->save();
                return $this->sendResponse($order, 'Order update successful.');
            } else {
                try {
                    // 1. Tìm admin và tài khoản admin
                    $admin = User::where('role', 4)->first();
                    if (!$admin) {
                        return $this->sendError('Admin not found.', [], 404);
                    }

                    $adminBalance = Balance::where('user_id', $admin->_id)->first();
                    if (!$adminBalance) {
                        return $this->sendError('Admin balance not found.', [], 404);
                    }

                    // 2. Trừ tiền từ tài khoản admin
                    $lastAdminBalance = (int)$adminBalance->balance;
                    $adminBalance->balance = $lastAdminBalance - (int)$order['total_price'];
                    $adminBalance->save();

                    // Log giao dịch trừ tiền admin
                    balance_log::create([
                        'id_balance' => $adminBalance->_id,
                        'user_id' => $admin->_id,
                        'action_user' => "Release holding amount for refund - Order " . $order['order_code'],
                        'transaction_status' => 'release_refund',
                        'last_balance' => $lastAdminBalance,
                        'current_balance' => $adminBalance->balance,
                        'balance' => (int)$order['total_price'],
                        'status' => '3'
                    ]);

                    // 3. Hoàn tiền cho user
                    $userBalance = Balance::where('user_id', $order['user_id'])->first();
                    if (!$userBalance) {
                        return $this->sendError('User balance not found.', [], 404);
                    }

                    $lastUserBalance = (int)$userBalance->balance;
                    $userBalance->balance = $lastUserBalance + (int)$order['total_price'];
                    $userBalance->save();

                    // Log giao dịch hoàn tiền cho user
                    balance_log::create([
                        'id_balance' => $userBalance->_id,
                        'user_id' => $order['user_id'],
                        'action_user' => "Refund for service " . $order['kiosk_sub_name'] . " - Order " . $order['order_code'],
                        'transaction_status' => 'refund',
                        'last_balance' => $lastUserBalance,
                        'current_balance' => $userBalance->balance,
                        'balance' => (int)$order['total_price'],
                        'status' => '3'
                    ]);

                    // 4. Cập nhật trạng thái đơn hàng
                    $order->status = 10; // Trạng thái hủy/hoàn tiền
                    $order->save();

                    return $this->sendResponse($order, 'Order cancelled and refunded successfully.');

                } catch (\Exception $e) {
                    \Log::error('Refund processing failed', [
                        'order_id' => $order->_id,
                        'order_code' => $order->order_code,
                        'error' => $e->getMessage()
                    ]);
                    return $this->sendError('Failed to process refund. Please try again.', [], 400);
                }
            }
        } catch (\Exception $e) {
            // Trả về phản hồi lỗi
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    public function GetOrderDashboard()
    {
        $user = auth()->user();
        $currentMonthOrders = Order::where('id_seller', $user['_id'])
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->count();

        $lastMonthOrders = Order::where('id_seller', $user['_id'])
            ->whereMonth('created_at', Carbon::now()->subMonth()->month)
            ->whereYear('created_at', Carbon::now()->subMonth()->year)
            ->count();
            $success['expires_at'] = now()->addDays(3);
        $success['currentMonth'] = $currentMonthOrders;
        $success['lastMonth'] = $lastMonthOrders;
        return $this->sendResponse($success, 'OrderDashboard');
    }

    public function buyProductsAPI()
    {
        try {
            //Chuẩn bị data
            $kioskToken = $_GET['kioskToken'];
            $userToken = $_GET['userToken'];
            $quantity = $_GET['quantity'];
            $user = User::where('token_buy_Api', $userToken)->first();
            $sub_kiot = KiotSub::where('token_API', $kioskToken)->first();
            $kiot = Kiot::find($sub_kiot->kiosk_id);
            $user_seller = User::find($kiot->user_id);
            $kiosk_sub_id = $sub_kiot['_id'];
            $id_seller = $user_seller['_id'];


            $qtyKiot = kiosk_sub_product::where('kiosk_sub_id', $kiosk_sub_id)
                    ->where('status', 1)
                    ->count() ?? 0;
            //Hết data
            $data['user_id'] = $user['_id'];
            $data['is_api'] = 1;
            $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';  // Chuỗi bao gồm cả chữ cái và số
            $maxAttempts = 1000; // Giới hạn số lần thử tạo mã (để tránh vòng lặp vô tận)
            $attempts = 0;  // Biến đếm số lần thử

            if ($id_seller == $user['_id']) {
                $false['success'] = 'false';
                $false['description'] = 'You are not allowed to buy products from your own kiosk.';
                return $false;
            }
            if ($kiot['status'] == 0) {
                $false['success'] = 'false';
                $false['description'] = 'The product is being approved and cannot be purchased.';
                return $false;
            }
            $category_sub = CategorySlug::find($kiot['category_sub_id']);

            $floor_dis = $category_sub['floor_dis'];
            // lấy phí sàn của seller
            $seller_info = User::find($id_seller);
            $floor_dis_seller = $seller_info['penalty_tax'];
            if ($seller_info['is_banned'] == 1 && $floor_dis_seller > $floor_dis) {
                $floor_dis = $floor_dis_seller;
            }else{
                $floor_dis = $category_sub['floor_dis'];
            }
            // Kiểm tra xem sản phẩm có tồn tại không
            if (!$sub_kiot) {

                $false['success'] = 'false';
                $false['description'] = 'Kiosk product not found.';
                return $false;
            }
            do {
                $randomString = '';
                $length = 7; // Chiều dài mã ban đầu là 9

                // Tạo mã ngẫu nhiên
                for ($i = 0; $i < $length; $i++) {
                    $randomString .= $characters[rand(0, strlen($characters) - 1)];
                }

                // Thêm 1 chữ số ngẫu nhiên vào cuối
                $randomString .= rand(0, 9);

                // Kiểm tra xem mã đã tồn tại trong cơ sở dữ liệu chưa
                $orderExists = Order::where('order_code', $randomString)->exists();

                // Nếu đã hết ký tự có thể tạo ra, tăng chiều dài của mã lên 2 ký tự nữa
                if ($orderExists) {
                    $length += 2; // Tăng chiều dài mã lên nếu trùng lặp
                }
                $attempts++;

            } while ($orderExists && $attempts < $maxAttempts); // Lặp cho đến khi tạo được mã duy nhất hoặc đạt giới hạn

            // Nếu đã thử quá số lần giới hạn mà vẫn trùng lặp, trả về lỗi
            if ($attempts >= $maxAttempts) {
                $false['success'] = 'false';
                $false['description'] = 'Could not generate unique order code. Please try again later.';
                return $false;
            }
            $quality = (int)$quantity;
//            $qtyKiot = (int)$sub_kiot['quantity'];


                $data['service_product'] = null;
                $data['service_waitingdays'] = null;
                if ($qtyKiot < $quality) {
                    $false['success'] = 'false';
                    $false['description'] = 'There is not enough quantity in the warehouse.';
                    return $false;
                }
            // Sau khi đã tạo mã duy nhất, tiếp tục với các thao tác khác
            $order_code = $randomString;

            $total_price = (int)$sub_kiot['price'] * (int)$quality;

            //Tính phần trăm trên tổng số tiền
            $floor = ((int)$floor_dis / 100) * (int)$total_price;

            $data['order_code'] = $order_code; // Lưu mã order_code vào dữ liệu
            //Lấy giá sản phẩm
            $data['kiosk_id'] = $sub_kiot->kiosk_id;
            $userSeller = User::find($id_seller);
            $data['status_kiot'] = 'product';
            $data['name_user_buy'] = $user['name'];
            $data['id_seller'] = $id_seller;
            $data['name_seller'] = $userSeller['name'];
            $data['admin_amount'] = $floor;
//          $kiot_slug_price = KiotSub::find($request['kiosk_sub_id']);
            $data['price'] = (int)$sub_kiot['price'];
            $data['status'] = 0; // 0 đang chờ hoàn thành
            $data['kiosk_sub_name'] = $sub_kiot['name'];
            $data['quality'] = $quality;
            $data['kiosk_sub_id'] = $kiosk_sub_id;

            $data['total_price'] = (int)$total_price;
            $reseller_amounts = "";
            $ref_id = "";

            $data['reseller_amount'] = $reseller_amounts;
            $data['ref_user_id'] = $ref_id;

            $data['is_delete'] = 1; //1 Hiện thị ; 0 đã xóa
            //kiểm tra Thanh toán trừ tiền trong ví
            $balance = Balance::where('user_id', $user['_id'])->first();
            if (empty($balance) || $balance == null) {
                $false['success'] = 'false';
                $false['description'] = 'You have not linked a payment wallet.';
                return $false;
            } elseif ($balance['balance'] == 0 || (int)$balance['balance'] < (int)$total_price) {
                $false['success'] = 'false';
                $false['description'] = 'The balance in the wallet is not enough to make payment.';
                return $false;
            } else {
                $balanceTop = Balance::where('user_id', $user['_id'])->first();
                $balanceTop['balance'] = (int)$balanceTop['balance'] - (int)$total_price;
                $balanceTop->save();
                $data_request['id_balance'] = $balance['_id'];
                $data_request['user_id'] = $user['_id'];
                $data_request['action_user'] = "Buy product buy API" . $sub_kiot['name'];
                $data_request['transaction_status'] = "buy";
                $data_request['last_balance'] = $balance['balance'];
                $data_request['current_balance'] = $balanceTop['balance'];
                $data_request['balance'] = (int)$total_price;
                $data_request['status'] = 3;

                $balance_log = balance_log::create($data_request);
                $order = Order::create($data);
                $oderProduct = kiosk_sub_product::where('kiosk_sub_id', $order['kiosk_sub_id'])
                    ->where('status', 1)
                    ->take($quality)  // Lấy theo bản ghi
                    ->get();

                // Cập nhật status = 0 cho các bản ghi đó
                $value_order_product = [];
                foreach ($oderProduct as $key => $product) {
                    $product->status = 0;
                    $product->save();
                    $value_order_product[] = [
                        'value' => $product['value'],
                        'id' => $product['_id']
                    ];
                }
                $value_order_product = json_encode($value_order_product);
                $userSeller['checkPoint'] = (int)$userSeller['checkPoint'] + (int)$total_price;
                $userSeller->save();
                $user['checkPoint'] = (int)$user['checkPoint'] + (int)$total_price;
                $user->save();
                OrderDetail::create([
                    'order_code' => $order_code,
                    'kiosk_id' => $kiot->_id,
                    'user_id' => $user['_id'],
                    'price' => (int)$total_price,
                    'value' => $value_order_product,
                ]);

//Update quality
                    $datakiot['quantity'] = $sub_kiot['quantity'] - $order['quality'];

//            $datakiot['quantity'] = $sub_kiot['quantity'] - $order['quality'];

                $sub_kiot->update($datakiot);
                $success['success'] = 'true';
                $success['order_id'] = $order->order_code;
                return $success;
            }
        } catch (\Exception $e) {
            // Trả về phản hồi lỗi
            $false['success'] = 'false';
            $false['description'] = 'An error has occurred. Please try again later.';
            return $false;
        }
    }
    public function SearchOrderAdmin(Request $request)
    {
        // Lấy các tham số từ request
        $order_code = $request['order_code'];
        $nameSeller = $request['name_seller'];
        $nameBuy = $request['name_buy'];
        $status = $request['filterStatus'];

        // Tạo query để tìm đơn hàng
        $orders = Order::orderBy('created_at', 'desc');

        // Kiểm tra điều kiện tìm kiếm
        if ($order_code) {
            // Nếu có order_code, tìm theo order_code
            $orders->where('order_code', 'like', '%' . $order_code . '%');
        }
        if ($nameSeller) {
            // Nếu có name_seller, tìm theo keyword trong name_seller
            $orders->where('name_seller', 'like', '%' . $nameSeller . '%');
        }
        if ($nameBuy) {
            // Nếu có name_user_buy, tìm theo keyword trong name_user_buy
            $orders->where('name_user_buy', 'like', '%' . $nameBuy . '%');
        }
        if ($status) {
            // Nếu có status, tìm theo status
            $orders->where('status', (int)$status);
        }

        // Thực thi query để lấy kết quả
        $orders = $orders->get(); // Lấy tất cả đơn hàng mà không phân trang

        // Kiểm tra xem có đơn hàng nào không
        if ($orders->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No orders found for the user.',
                'data' => []
            ], 404);
        }

        // Trả về dữ liệu đơn hàng
        return response()->json([
            'success' => true,
            'message' => 'Orders retrieved successfully.',
            'data' => $orders,
        ], 200);
    }


}
