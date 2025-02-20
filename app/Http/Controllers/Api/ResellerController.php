<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kiot;
use App\Models\Reseller;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\ApiController as ApiController;
use function PHPUnit\Framework\requires;

class ResellerController extends ApiController
{

    public function getResellerByKiotId($seller_id)
    {
        try {
            $reseller = Reseller::where('sellers_id', $seller_id)->orderBy('created_at', 'desc')->get();
            if ($reseller) {
                return $this->sendResponse($reseller, 'Get reseller successfully');
            } else {
                return $this->sendError('Reseller not found', [], 404);
            }
        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }


    public function getResellerByKiot($kiot_id){
        $reseller = Reseller::where('kiosk_id', $kiot_id)->orderBy('created_at', 'desc')->get();
        if ($reseller) {
            return $this->sendResponse($reseller, 'Get reseller successfully');
        } else {
            return $this->sendError('Reseller not found', [], 404);
        }
    }

    public function getResellerByUserId()
    {
        $user = auth()->user();
        $reseller = Reseller::where('user_id', $user['_id'])->get();
        if ($reseller) {
            return $this->sendResponse($reseller, 'Get reseller successfully');
        } else {
            return $this->sendError('Reseller not found', [], 404);
        }
    }

    public function AddReseller(Request $request)
    {
        try {
            $data = $request->all();
            $user = auth()->user();
            $data['kiosk_id'] = $request['kiosk_id'];
            $data['user_id'] = $user['_id'];

            $kiosk = Kiot::where('_id', $data['kiosk_id'])->first();
            if ($kiosk['user_id'] == $data['user_id']) {
                return $this->sendError('You are the owner of this kiosk, you cannot register as a reseller', [], 400);
            }

            // Kiểm tra xem cặp kiosk_id và user_id đã tồn tại chưa
            $existingReseller = Reseller::where('kiosk_id', $data['kiosk_id'])
                ->where('user_id', $data['user_id'])
                ->exists();
            $data['sellers_id'] = $kiosk['user_id'];
            if ($existingReseller) {
                return $this->sendError('You have already registered for this kiosk as a reseller.', [], 400);
            }

            // Nếu không tồn tại, tiếp tục tạo mới reseller
            $reseller = Reseller::create($data);
            $success['expires_at'] = now()->addDays(3);

            return $this->sendResponse($reseller, 'You have successfully submitted your request to become a reseller');
        } catch (\Exception $e) {
            // Trả về phản hồi lỗi
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    public function GetInfoReseller()
    {

        $user_id = request()->get('user_id');
        $kiot_id = request()->get('kiosk_id');

        // Kiểm tra nếu có sự khác biệt giữa kiot_id và kiosk_id, cần điều chỉnh lại đúng tên cột trong cơ sở dữ liệu
        $reseller = Reseller::where('user_id', $user_id)
            ->where('kiosk_id', $kiot_id) // Đảm bảo cột này là đúng trong DB
            ->where('status', 1)
            ->first();  // Dùng first() nếu chỉ cần lấy một kết quả

        if ($reseller) {
            return $this->sendResponse($reseller, 'Get reseller successfully');
        } else {
            return $this->sendError('Reseller not found', [], 404);
        }
    }


    public function updateStatusReseller(Request $request)
    {
        try {
            $data = $request->all();
            $reseller = Reseller::where('_id', $data['_id'])->first();
            if ($reseller) {
                $status = (int)$data['status'];
                $reseller->status = $status;
                if ($status == 1) {
                    $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';  // Chuỗi bao gồm cả chữ cái và số
                    $maxAttempts = 1000; // Giới hạn số lần thử tạo mã (để tránh vòng lặp vô tận)
                    $attempts = 0;  // Biến đếm số lần thử

                    do {
                        $randomString = '';
                        $length = 4; // Chiều dài mã ban đầu là 9

                        // Tạo mã ngẫu nhiên
                        for ($i = 0; $i < $length; $i++) {
                            $randomString .= $characters[rand(0, strlen($characters) - 1)];
                        }

                        // Thêm 1 chữ số ngẫu nhiên vào cuối
                        $randomString .= rand(0, 9);

                        // Kiểm tra xem mã đã tồn tại trong cơ sở dữ liệu chưa
                        $orderExists = Reseller::where('linkresller', $randomString)->exists();

                        // Nếu đã hết ký tự có thể tạo ra, tăng chiều dài của mã lên 2 ký tự nữa
                        if ($orderExists) {
                            $length += 1; // Tăng chiều dài mã lên nếu trùng lặp
                        }
                        $attempts++;

                    } while ($orderExists && $attempts < $maxAttempts); // Lặp cho đến khi tạo được mã duy nhất hoặc đạt giới hạn

                    // Nếu đã thử quá số lần giới hạn mà vẫn trùng lặp, trả về lỗi
                    if ($attempts >= $maxAttempts) {
                        return $this->sendError('Could not generate unique order code. Please try again later.', [], 400);
                    }
                    $reseller->shortened_code = $randomString;
                    $reseller->linkresller ="?id_resller=" . $data['_id'] . "&is_resller=1";
                } elseif ($status == 2) {
                    if (!empty($reseller->linkresller)) {
                        $reseller->linkresller = '';
                        $reseller->shortened_code = '';
                    }
                }
                $reseller->save();
                return $this->sendResponse($reseller, 'Update status reseller successfully');
            } else {
                return $this->sendError('Reseller not found', [], 404);
            }
        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    public function CheckShortCode()
    {
        $shortened_code = request()->get('shortened_code');

        $reseller = Reseller::where('shortened_code', $shortened_code)
            ->where('status', 1)
            ->first();  // Dùng first() nếu chỉ cần lấy một kết quả
        if ($reseller) {
            return $this->sendResponse($reseller, 'Get reseller successfully');
        } else {
            return $this->sendError('Reseller not found', [], 404);
        }
    }
    public function checkReseller($kiosk_id)
    {
        $user = auth()->user();
        $user_id = $user['_id'];
        $reseller = Reseller::where('user_id', $user_id)
            ->where('kiosk_id', $kiosk_id)
            ->first();
        if ($reseller) {
            return $this->sendResponse(true, 'Get reseller successfully');
        } else {
            return $this->sendError('Reseller not found', [], 404);
        }
    }
}
