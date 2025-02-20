<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\ApiController as ApiController;
use App\Models\ActivityLog;
use App\Models\Conversation;
use App\Models\Kiot;
use App\Models\KiotSub;
use App\Models\Message;
use App\Models\Messages;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Http\Request;


class KiotController extends ApiController
{
    public function getKiot()
    {
        $user = auth()->user();
        $kiot = Kiot::all();
        $kiot = Kiot::orderBy('created_at', 'desc')->get();
        return $kiot;
    }

    public function getKiotByUser()
    {
        $user = auth()->user();
        $is_lock_all_kiosk = $user['is_lock_all_kiosk'];
        if ($is_lock_all_kiosk == true) {
            return $this->sendError('You do not have permission to view this page', ['status' => 'error'], 400);
        }
        // Sử dụng điều kiện trong hàm `where` thay vì `find`
        $kiot = Kiot::where('user_id', $user['_id'])->get();
        return $kiot;
    }

    public function addKiot(Request $request)
    {
        try {
            $count = Kiot::where('user_id', auth()->user()->_id)->count();

            if ($count >= 5) {
                return $this->sendError('You have reached the maximum number of stores', ['status' => 'error'], 400);
            }
            $user = auth()->user();
            $data = $request->all();

            $data['user_id'] = $user['_id'];
            $kiot = Kiot::create($data);
            $success['expires_at'] = now()->addDays(3);
            $success['name'] = $kiot->name;
            return $this->sendResponse($kiot, 'User register successfully.');

        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    public function editKiot(Request $request, $id)
    {
        try {
            // Tìm đối tượng Kiot dựa trên $id
            $kiot = Kiot::where('_id', $id)->first();

            // Kiểm tra nếu không tìm thấy đối tượng
            if (!$kiot) {
                return $this->sendError('Kiot not found', [], 404);
            }


            // Lấy tất cả dữ liệu từ request
            $data['name'] = $request['name'];
            $data['refund_person'] = $request['refund_person'];
            $data['allow_reseller'] = $request['allow_reseller'];
            $data['is_private'] = $request['is_private'];
            $data['is_duplicate'] = $request['is_duplicate'];
            $data['short_des'] = $request['short_des'];
            $data['image'] = $request['image'];
            $data['status'] = 0;

            if ($request['image'] == null) {
                unset($data['image']);
            }
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

    public function upadateIDPost(Request $request, $id)
    {
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

        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    public function editKiotStatus(Request $request, $id)
    {
        try {
            $user = auth()->user();
            $user_id = $user['_id'];
            $kiotStatus = Kiot::find($id);
            $oldStatus = $kiotStatus['status'];
            $name = $kiotStatus['name'];
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
            } elseif ($kiotStatus['status'] == 1) {
                $status = "Đang hoạt động";
            } elseif ($kiotStatus['status'] == 2) {
                $status = "Tạm dừng";
            } elseif ($kiotStatus['status'] == 3) {
                $status = "Khóa";
            }

            $statusMap = [
                0 => "Chờ duyệt",
                1 => "Đang hoạt động",
                2 => "Tạm dừng",
                3 => "Khóa"
            ];
            $statusText = $statusMap[$request['status']] ?? '';
            // lưu log
            if($user['role'] == 6 || $user['role'] == 7){
                $activity_log = ActivityLog::create([
                    'supporter_id' => $user_id,
                    'action' => 'update',
                    'description' => "Cập nhật trạng thái gian hàng {$name} từ {$statusMap[$oldStatus]} sang {$statusText}",
                    'target_id' => $request['user_id'],
                    'is_success' => true
                ]);
            }
            $status_convert_eng = [
                0 => "pending",
                1 => "active",
                2 => "pause",
                3 => "lock"
            ];
            $status_text_convert_eng = $status_convert_eng[$request['status']] ?? '';
            // gửi tin nhắn thông báo
            $message_content = "Your store {$name} has been switched to the status {$status_text_convert_eng}";
            if ($user['role'] != 6 && $user['role'] != 7) {
                $user_sys = User::where('role', 5)->first();
                $id_system_bot = $user_sys['_id'];
                $conversation = Conversation::where('id_user1', $id_system_bot)
                    ->where('id_user2', $request['user_id'])
                    ->first();
                if (!$conversation) {
                    $conversation = Conversation::create([
                        'id_user1' => $id_system_bot,
                        'id_user2' => $request['user_id'],
                        'status' => 1
                    ]);
                }
                $message = Messages::create([
                        'conversation_id' => $conversation->id,
                        'sender_id' => $id_system_bot,
                        'message' => $message_content,
                        'status' => 0
                    ]);

                if ($message) {
                    $conversation->update([
                        'last_mess' => $message_content,
                        'last_mess_id' => $id_system_bot,
                        'updated_at' => now()
                    ]);
                }
            }
            return $this->sendResponse($status, 'Kiot updated successfully.');

        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    //
    public function getKiotByUserId($id)
    {
        $kiot = Kiot::where('user_id', $id)->get();
        return $kiot;
    }
//    tim gian hàng đã được duyệt
    public function getKiotByUserIdApproved($id)
    {
        $kiot = Kiot::where('user_id', $id)
            ->whereIn('status', [1, "1"])
            ->where('is_active', '!=', false)
            ->get();
        return $kiot;
    }

    public function getkiotID($id)
    {
        $kiot = Kiot::find($id);
        return $kiot;
    }

    public function getkiotAdmin($id)
    {
        $kiot = Kiot::find($id);
        return $kiot;
    }

    public function getKiotWishtList() {
        // Lấy tất cả wishlist records
        $wishList = Wishlist::all();

        // Đếm và sắp xếp kiosk_id theo số lần xuất hiện
        $kiosk_counts = $wishList->groupBy('kiosk_id')
            ->map(function ($group) {
                return [
                    'kiosk_id' => $group->first()->kiosk_id,
                    'count' => $group->count()
                ];
            })
            ->sortByDesc('count')
            ->pluck('kiosk_id')
            ->toArray();

        // Lấy tất cả Kiot records
        $kiot = Kiot::whereIn('_id', $kiosk_counts)->get();

        // Sắp xếp lại collection theo thứ tự của $kiosk_counts
        $sorted_kiot = collect($kiosk_counts)->map(function($kiosk_id) use ($kiot) {
            return $kiot->where('_id', $kiosk_id)->first();
        })->filter();

        // Trả về danh sách id_post đã được sắp xếp
        return $sorted_kiot->pluck('id_post');
    }

    // lọc danh sách sản phẩm và trả ra id_post
    public function getKiotWithFilter()
    {
        // giá tăng dần
        $kiot = Kiot::all();
        $filter = request('filter');
        $kiot_id = $kiot->pluck('_id');
        if ($filter == 'price_asc') {
            $kiot_sub = KiotSub::whereIn('kiosk_id', $kiot_id)->orderBy('price', 'asc')->get();
            $id_kiot_in_kiot_sub = $kiot_sub->pluck('kiosk_id');
            $kiot = Kiot::whereIn('_id', $id_kiot_in_kiot_sub)->get();
        }
        // giá giảm dần
        if ($filter == 'price_desc') {
            $kiot_sub = KiotSub::whereIn('kiosk_id', $kiot_id)
                ->orderBy('price', 'desc')
                ->groupBy('kiosk_id')
                ->get();
            $id_kiot_in_kiot_sub = $kiot_sub->pluck('kiosk_id')->unique();
            $id_kiot_in_kiot_sub_array = $id_kiot_in_kiot_sub->toArray();
            $kiot = Kiot::whereIn('_id', $id_kiot_in_kiot_sub_array)->get();
        }
        // mới nhất
        if ($filter == 'newest') {
            $kiot = Kiot::whereIn('_id', $kiot_id)->orderBy('created_at', 'desc')->get();
        }
        // cũ nhất
        if ($filter == 'oldest') {
            $kiot = Kiot::whereIn('_id', $kiot_id)->orderBy('created_at', 'asc')->get();
        }
        // return $kiot;
        $kiot_id_post = $kiot->pluck('id_post');
        return $kiot;
    }

    public function getKiotByCate(Request $request)
    {
        try {
            $user = auth()->user();
            $id_cate = $request['cate_id'];

            $kiot = Kiot::where('category_id', $id_cate)->where('status', '1')->where('user_id', $user['_id'])->get();
            return $kiot;
        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }

    }

    public function CountKiotByUser(){
        $user = auth()->user();
        $id = $user['id'];
        $kiot = Kiot::where('user_id', $id)->where('status', '1')->count();

        return $kiot;
    }


    // tính tổng số sản phẩm có sẵn trong kho
    public function CountProductInKiot($user_id)
    {
        $kiot = Kiot::where('user_id', $user_id)->get();
        $count = 0;
        foreach ($kiot as $item) {
            $kiot_sub = KiotSub::where('kiosk_id', $item['_id'])->get();
            foreach ($kiot_sub as $item) {
                $count += $item->quantity;
            }
        }
        return $count;
    }
}
