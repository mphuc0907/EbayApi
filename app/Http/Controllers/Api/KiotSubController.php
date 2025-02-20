<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\ApiController as ApiController;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryParent;
use App\Models\kiosk_sub_product;
use App\Models\Kiot;
use App\Models\KiotSub;
use App\Models\User;
use Illuminate\Http\Request;

class KiotSubController extends ApiController
{

  public function GetSubKiotByKioskId($id)
    {
        try {
            // Lấy dữ liệu từ bảng KiotSub
            $subkiot = KiotSub::where('kiosk_id', $id)->get();

            // Lấy dữ liệu Kiot theo id
            $kiot = Kiot::find($id);

            // Kiểm tra category của Kiot
            $category = CategoryParent::find($kiot['category_parent_id']);
            $status_kiot = '';

            // Xác định giá trị status_kiot dựa trên tên của category
            if ($category) {
                if ($category->name === 'Product') {
                    $status_kiot = 'product';
                } elseif ($category->name === 'Service') {
                    $status_kiot = 'service';
                }
            }

            // Debug giá trị oderProduct


            // Thêm status_kiot vào từng mục trong danh sách subkiot
            foreach ($subkiot as $item) {

                // Kiểm tra số lượng sản phẩm
                $oderProduct = kiosk_sub_product::where('kiosk_sub_id', $item->_id)
                        ->where('status', 1)
                        ->count() ?? 0;

                $item->quantity = (int)$oderProduct;
                $item->status_kiot = $status_kiot;
            }

            // Trả về kết quả với status_kiot được thêm vào
            return $this->sendResponse($subkiot, 'Get Subkiot successfully.');

        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

  public function getSubKiotById($id)
  {
    try {
      $subkiot = KiotSub::where('_id', $id)->first();
      return $this->sendResponse($subkiot, 'User register successfully.');

    } catch (\Exception $e) {
      return $this->sendError('An error has occurred. Please try again later', [], 400);
    }
  }

  public function AddSubKiot(Request $request)
  {
    try {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';  // Chuỗi bao gồm cả chữ cái và số
        $maxAttempts = 1000; // Giới hạn số lần thử tạo mã (để tránh vòng lặp vô tận)
        $attempts = 0;  // Biến đếm số lần thử

        do {
            $randomString = '';
            $length = 20; // Chiều dài mã ban đầu là 9

            // Tạo mã ngẫu nhiên
            for ($i = 0; $i < $length; $i++) {
                $randomString .= $characters[rand(0, strlen($characters) - 1)];
            }

            // Thêm 1 chữ số ngẫu nhiên vào cuối
            $randomString .= rand(0, 9);

            // Kiểm tra xem mã đã tồn tại trong cơ sở dữ liệu chưa
            $orderExists = KiotSub::where('token_API', $randomString)->exists();

            // Nếu đã hết ký tự có thể tạo ra, tăng chiều dài của mã lên 2 ký tự nữa
            if ($orderExists) {
                $length += 2; // Tăng chiều dài mã lên nếu trùng lặp
            }
            $attempts++;

        } while ($orderExists && $attempts < $maxAttempts); // Lặp cho đến khi tạo được mã duy nhất hoặc đạt giới hạn

//            $data = $request->all();
      $count = KiotSub::where('kiosk_id', $request['kiosk_id'])->count();
      if ($count >= 5) {
        return $this->sendError('Bạn chỉ có thể thêm tối đa 5 SubKiot cho một kiosk.', ['status' => 'error'], 400);
      }
      $data['kiosk_id'] = $request['kiosk_id'];
      $data['name'] = $request['name'];
      $data['token_API'] = $randomString;
      $data['price'] = $request['price'];
      $data['status'] = $request['status'];

      $subkiot = KiotSub::create($data);

      $success['expires_at'] = now()->addDays(3);
      $success['name'] = $subkiot->name;
      return $this->sendResponse($success, 'User register successfully.');

    } catch (\Exception $e) {
      return $this->sendError('An error has occurred. Please try again later', [], 400);
    }
  }

  public function updateSubKiot(Request $request)
  {
    try {
      $subkiot = KiotSub::where('_id', $request['_id'])->first();
      if (!$subkiot) {
        return $this->sendError('Subkiot not found', [], 404);
      }
      if (!is_null($request->input('price'))) {
        $subkiot->price = $request->input('price');
      }
      if (!is_null($request->input('name'))) {
        $subkiot->name = $request->input('name');
      }
      $subkiot->status = $request->input('status');
      $subkiot->_id = $request->input('_id');
      $subkiot->save();
      return $this->sendResponse($subkiot, 'Update successfully.');
    } catch (\Exception $e) {
      return $this->sendError('An error has occurred. Please try again later', [], 400);
    }
  }
  function deleteSubKiot(Request $request)
  {
    try {
      $subkiot = KiotSub::where('_id', $request['_id'])->first();
      if (!$subkiot) {
        return $this->sendError('Subkiot not found', [], 404);
      }
      $subkiot->delete();
      return $this->sendResponse([], 'Delete successfully.');
    } catch (\Exception $e) {
      return $this->sendError('An error has occurred. Please try again later', [], 400);
    }
  }

  public function getStockAPI() {
      try {
          $token_kiot = $_GET['kioskToken'];
          $user = $_GET['userToken'];
          $user_test = User::where('token_buy_Api', $user)->first();
          if (empty($user_test)) {
              $false['success'] = 'false';
              $false['description'] = 'User does not exist';
              return $false;
          }
          $subkiot = KiotSub::where('token_API', $token_kiot)->first();
          $data = kiosk_sub_product::where('kiosk_sub_id', $subkiot['_id'])->where('status', 1)->get();

//          if (count($data) < 0) {
//              $false['success'] = 'false';
//              $false['description'] = 'File name is exist.';
//              return $false;
//          } else {
//              $false['success'] = 'false';
//              $false['description'] = 'File name is not exist.';
//              return $false;
//          }
          $success['success'] = 'true';
          $success['price'] = $subkiot->price;
          $success['name'] = $subkiot->name;
          $success['stock'] = count($data);
          return $success;

      } catch (\Exception $e) {
          $false['success'] = 'false';
          $false['description'] = 'An error has occurred. Please try again later.';
          return $false;
      }
  }
}
