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
use App\Models\ReportOrder;
use App\Models\Reseller;
use App\Models\User;
use App\Models\OrderDetail;
use Illuminate\Http\Request;
use App\Models\RatingProduct;
use App\Http\Controllers\Api\ApiController as ApiController;
use function Carbon\int;

class OrderController extends ApiController
{

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
//        try {

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

        $category_sub = CategorySlug::find($kiot['category_sub_id']);

        $floor_dis = $category_sub['floor_dis'];
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
        $qtyKiot = (int)$sub_kiot['quantity'];

        if ($request['status_kiot'] == 'dich-vu') {
            $data['service_product'] = $sub_kiot['name'];
            $data['service_waitingdays'] = $request['service_waitingdays'];

        }
        else {
            $data['service_product'] = null;
            $data['service_waitingdays'] = null;
            if ($qtyKiot < $quality) {
                return $this->sendError('There is not enough quantity in the warehouse.', [], 400);
            }
        }
        // Sau khi đã tạo mã duy nhất, tiếp tục với các thao tác khác
        $order_code = $randomString;

        $total_price = (int)$sub_kiot['price'] * (int)$quality;

        //Tính phần trăm trên tổng số tiền
        $floor = ((int)$floor_dis / 100) * (int)$total_price;

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
//          $kiot_slug_price = KiotSub::find($request['kiosk_sub_id']);
        $data['price'] = (int)$sub_kiot['price'];
        $data['status'] = 0; // 0 đang chờ hoàn thành
        $data['kiosk_sub_name'] = $sub_kiot['name'];
        $data['quality'] = $quality;
        $data['total_price'] = (int)$total_price;
        $reseller_amounts = "";
        $ref_id = "";
        if ($request['ref_user_id'] != "") {
            $resller = Reseller::find($request['ref_user_id']);

            // Kiểm tra xem $resller có tồn tại không
            if ($resller) {
                // Kiểm tra nếu 'percent' và 'user_id' có tồn tại trong $resller
                if (isset($resller['percent']) && isset($resller['user_id'])) {
                    $percent = $resller['percent'];
                    $reseller_amounts = ((int)$percent / 100) * (int)$total_price;
                    $ref_id = $resller['user_id'];
                } else {
                    // Xử lý khi không tìm thấy 'percent' hoặc 'user_id'
//                    echo 'Không tìm thấy giá trị percent hoặc user_id trong reseller.';
                }
            } else {
                // Xử lý khi không tìm thấy reseller với ref_user_id
//                echo 'Không tìm thấy reseller với ref_user_id: ' . $request['ref_user_id'];
            }
        }

        $data['reseller_amount'] = $reseller_amounts;
        $data['ref_user_id'] = $ref_id;
        $data['is_delete'] = 1; //1 Hiện thị ; 0 đã xóa
        //kiểm tra Thanh toán trừ tiền trong ví
        $balance = Balance::where('user_id', $user['_id'])->first();
        if (empty($balance) || $balance == null) {
            return $this->sendError('You have not linked a payment wallet', [], 400);
        } elseif ($balance['balance'] == 0 || (int)$balance['balance'] < (int)$total_price) {
            return $this->sendError('The balance in the wallet is not enough to make payment', [], 400);
        } else {
            $balanceTop = Balance::where('user_id', $user['_id'])->first();
            $balanceTop['balance'] = (int)$balanceTop['balance'] - (int)$total_price;
            $balanceTop->save();
            $data_request['id_balance'] = $balance['_id'];
            $data_request['user_id'] = $user['_id'];
            $data_request['action_user'] = "Buy product/service name " . $sub_kiot['name'];
            $data_request['last_balance'] = $balance['balance'];
            $data_request['current_balance'] = $balanceTop['balance'];
            $data_request['balance'] = (int)$total_price;

            $balance_log = balance_log::create($data_request);
            $order = Order::create($data);
            $oderProduct = kiosk_sub_product::where('kiosk_sub_id', $order['kiosk_sub_id'])
                ->where('status', 1)
                ->take($quality)  // Lấy theo bản ghi
                ->get();

            // Cập nhật status = 0 cho các bản ghi đó
            $value_order_product = [];
            foreach ($oderProduct as $product) {
                $product->status = 0;
                $product->save();
                $value_order_product[] = $product['value'];
            }
            $userSeller['checkPoint'] = (int)$userSeller['checkPoint'] + (int)$total_price;
            $userSeller->save();
            $user['checkPoint'] = (int)$user['checkPoint'] + (int)$total_price;
            $user->save();
            OrderDetail::create([
                'order_code' => $order_code,
                'kiosk_id' => $request['kiosk_id'],
                'user_id' => $user['_id'],
                'price' => (int)$total_price,
                'value' => $value_order_product,
            ]);


//Update quality
            if ($request['status_kiot'] == 'dich-vu') {
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
//        } catch (\Exception $e) {
//            // Trả về phản hồi lỗi
//            return $this->sendError('An error has occurred. Please try again later', [], 400);
//        }
    }

    public function AddPrevOrder(Request $request)
    {
        try {
            $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';  // Chuỗi bao gồm cả chữ cái và số
            $maxAttempts = 1000; // Giới hạn số lần thử tạo mã (để tránh vòng lặp vô tận)
            $attempts = 0;  // Biến đếm số lần thử

            $sub_kiot = KiotSub::find($request['kiosk_sub_id']);
            $kiot = Kiot::find($request['kiosk_id']);

            $category_sub = CategorySlug::find($kiot['category_sub_id']);
            $floor_dis = $category_sub['floor_dis'];
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

//            if ($request['status_kiot'] == 'dich-vu') {
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
                return $this->sendError('The balance in the wallet is not enough to make payment', [], 400);
            } else {
                $balanceTop = Balance::where('user_id', $user['_id'])->first();
                $balanceTop['balance'] = (int)$balanceTop['balance'] - (int)$total_price;
                $balanceTop->save();
                $data_request['id_balance'] = $balance['_id'];
                $data_request['user_id'] = $user['_id'];
                $data_request['action_user'] = "Buy product/service name " . $sub_kiot['name'];
                $data_request['last_balance'] = $balance['balance'];
                $data_request['current_balance'] = $balanceTop['balance'];
                $data_request['balance'] = (int)$total_price;

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
            $orderQuery->where('name_order', 'like', '%' . $name_order . '%');
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
                $sub_kiot = KiotSub::find($order['kiosk_sub_id']);
                $curQty = $sub_kiot['quantity'];
                $quality = $order->quality;
                if ($quality <= $curQty) {
                    $sub_kiot = KiotSub::find($order['kiosk_sub_id']);
                    $order['status'] = 0;
                    $order->save();
                    $oderProduct = kiosk_sub_product::where('kiosk_sub_id', $order['kiosk_sub_id'])
                        ->where('status', 1)
                        ->take($quality)  // Lấy theo bản ghi
                        ->get();
                    // Cập nhật status = 0 cho các bản ghi đó
                    foreach ($oderProduct as $product) {
                        $product->status = 0;
                        $product->save();
                    }
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
}
