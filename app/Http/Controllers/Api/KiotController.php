<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\KiotRequest;
use App\Models\Kiot;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\ApiController as ApiController;
use App\Models\User;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Auth;
use function Symfony\Component\HttpFoundation\getUser;


class KiotController extends ApiController
{
     public function getKiot() {
         $user = auth()->user();
         $kiot = Kiot::all();
          $kiot = Kiot::orderBy('created_at', 'desc')->get();
         return $kiot;
     }
    public function getKiotByUser() {
        $user = auth()->user();
        // Sử dụng điều kiện trong hàm `where` thay vì `find`
        $kiot = Kiot::where('user_id', $user['_id'])->get();
        return $kiot;
    }
     public function addKiot(Request $request){
         try {
           $count = Kiot::where('user_id', auth()->user()->_id)->count();

            if ($count >= 5) {
              return $this->sendError('You have reached the maximum number of stores',['status' => 'error'], 400);
            }
            $user = auth()->user();
            $data = $request->all();

            $data['user_id'] = $user['_id'];
            $kiot = Kiot::create($data);
             $success['expires_at'] = now()->addDays(3);
             $success['name'] =  $kiot->name;
             return $this->sendResponse($kiot, 'User register successfully.');

         } catch (\Exception $e) {
             return  $this->sendError('An error has occurred. Please try again later', [], 400);
         }
     }

    public function editKiot(Request $request, $id) {
        try {
            // Tìm đối tượng Kiot dựa trên $id
            $kiot = Kiot::find($id);

            // Kiểm tra nếu không tìm thấy đối tượng
            if (!$kiot) {
                return $this->sendError('Kiot not found', [], 404);
            }

            // Lấy tất cả dữ liệu từ request
            $data['name'] = $request['name'];
            $data['allow_reseller'] = $request['allow_reseller'];
            $data['short_des'] = $request['short_des'];
            $data['description'] = $request['description'];
//            $data['image'] = $request['image'];

            // Cập nhật đối tượng với dữ liệu từ request
            $kiot->update($data);

            // Chuẩn bị dữ liệu phản hồi
            $success['expires_at'] = now()->addDays(3);
            $success['name'] = $kiot->name;

            // Trả về phản hồi thành công
            return $this->sendResponse($success, 'Kiot updated successfully.');
        } catch (\Exception $e) {
            // Trả về phản hồi lỗi
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    public function upadateIDPost(Request $request, $id) {
        try {
            $kiot = Kiot::find($id);
            if (!$kiot) {
                return $this->sendError('Kiot not found', [], 404);
            }
            $data['id_post'] = $request['id_post'];
            $kiot->update($data);
            $success['expires_at'] = now()->addDays(3);
            $success['name'] = $kiot->name;
            $success['id_post'] = $kiot->id_post;

            // Trả về phản hồi thành công
            return $this->sendResponse($success, 'Kiot updated successfully.');

        }catch (\Exception $e) {
            return  $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    public function editKiotStatus(Request $request, $id) {
        try {
            $kiotStatus= Kiot::find($id);

            if (!$kiotStatus) {
                return $this->sendError('Kiot not found', [], 404);
            }
            if ($request['status'] > 3 || $request['status'] < 0) {
                return $this->sendError('Status not update', [], 401);
            }
            $kiotStatus['status'] = $request['status'];
            $kiotStatus['user_id'] = $request['user_id'];

            $kiotStatus->save();

//            Trạng thái gian hàng
            if ($kiotStatus['status'] == 0) {
                $status = "Chờ duyệt";
            }elseif($kiotStatus['status'] == 1) {
                $status = "Đang hoạt động";
            }elseif($kiotStatus['status'] == 2) {
                $status = "Tạm dừng";
            } elseif($kiotStatus['status'] == 3) {
                $status = "Khóa";
            }

                return $this->sendResponse($status, 'Kiot updated successfully.');

        }  catch (\Exception $e) {
            return  $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    //
   public function getKiotByUserId($id) {
        $kiot = Kiot::where('user_id', $id)->get();
        return $kiot;
    }

    public function getkiotID($id) {
        $kiot = Kiot::find($id);
        return $kiot;
    }
    public function getkiotAdmin($id) {
        $kiot = Kiot::find($id);
        return $kiot;
    }
}
