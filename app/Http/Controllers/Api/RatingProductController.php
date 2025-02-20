<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\ApiController as ApiController;
use App\Models\balance;
use App\Models\balance_log;
use App\Models\Kiot;
use App\Models\Order;
use App\Models\RatingProduct;
use Illuminate\Http\Request;

class RatingProductController extends ApiController
{
    public function getRatingProductUserSeller()
    {
        try {
            $user = auth()->user();
            $kiot_seller = Kiot::where('user_id', $user['_id'])->get();
            $rating = RatingProduct::whereIn('kiosk_id', $kiot_seller->pluck('_id'))
                ->where('id_parent', null)
                ->orderBy('created_at', 'desc')
                ->paginate(5);

            $order = Order::whereIn('_id', $rating->pluck('order_id'))->get();
            $kiot_id_post = Kiot::whereIn('_id', $rating->pluck('kiosk_id'))->get();

            $customData = $rating->map(function ($item) use ($order, $kiot_id_post) {
                $order = $order->where('_id', $item['order_id'])->first();
                $kiot = $kiot_id_post->where('_id', $item['kiosk_id'])->first();
                return [
                    'id' => $item['_id'],
                    'order_code' => $order['order_code'] ?? null,
                    'id_post' => $kiot['id_post'] ?? null,
                    'created_at' => $item['created_at'],
                    'user_id' => $item['user_id'],
                    'name_user' => $item['name_user'],
                    'avatar_user' => $item['avatar_user'],
                    'comment' => $item['comment'],
                    'star' => $item['star'],
                ];
            });
            // custom data
            return response()->json([
                'success' => true,
                'message' => 'Ratings retrieved successfully.',
                'data' => $customData,
                'pagination' => [
                    'total' => $rating->total(),
                    'per_page' => $rating->perPage(),
                    'current_page' => $rating->currentPage(),
                    'last_page' => $rating->lastPage(),
                    'next_page_url' => $rating->nextPageUrl(),
                    'prev_page_url' => $rating->previousPageUrl(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error has occurred. Please try again later.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function AddRatingProduct(Request $request)
    {
        try {
            $user = auth()->user();
            $data = $request->all();
            $image = $request['image'];
            $gallery = json_encode(["value" => $image]);
            $data['user_id'] = $user['_id'];
            $data['id_parent'] = $request['id_parent'];
            $data['imageGallery'] = $gallery;
            $data['name_user'] = $user['name'];
            $data['avatar_user'] = $user['back_id_card'];
            $data['email'] = $user['email'];
            $data['total_like'] = 0;
            $data['status'] = 0;

            $order = Order::where('_id', $data['order_id'])->first();
            $kiot = Kiot::where('_id', $order['kiosk_id'])->first();
            if ($data['id_parent'] == null) {
                $refund_money = $kiot['refund_person'];
                $total_price = $order['total_price'];
                $refund = $total_price * $refund_money / 100;
               $status_order = $order['status'];
               if($status_order == -1){
                   $balance = balance::where('user_id', $user['_id'])->first();
                   $current_balance = $balance['balance'];
                   $balance['balance'] = $current_balance + $refund;
                   $balance->save();
                   //
                   $id_balance = $balance['_id'];
                   $action_user = 'Refund money rating from order ' . $order['order_code'];
                   $transaction_status = 'refund';
                   $top_up = $refund;
                   $last_balance = $current_balance;
                   $balance_log_current = $current_balance + $refund;
                   $balance_log = balance_log::create([
                       'user_id' => $user['_id'],
                       'id_balance' => $id_balance,
                       'action_user' => $action_user,
                       'last_balance' => $last_balance,
                       'transaction_status' => $transaction_status,
                       'status' => 3,
                       'current_balance' => $balance_log_current,
                       'balance' => $top_up,
                   ]);

                   // trừ tiền của seller
                   $seller = balance::where('user_id', $order['id_seller'])->first();
                   $current_balance_seller = $seller['balance'];
                   $seller['balance'] = $current_balance_seller - $refund;
                   $seller->save();

                   // lưu log trừ tiền của seller
                   $id_balance_seller = $seller['_id'];
                   $action_user_seller = 'Refund money rating from order for seller ' . $order['order_code'];
                   $transaction_status_seller = 'refund';
                   $top_up_seller = $refund;
                   $last_balance_seller = $current_balance_seller;
                   $balance_log_current_seller = $current_balance_seller - $refund;
                   $balance_log_seller = balance_log::create([
                       'user_id' => $order['id_seller'],
                       'id_balance' => $id_balance_seller,
                       'action_user' => $action_user_seller,
                       'last_balance' => $last_balance_seller,
                       'transaction_status' => $transaction_status_seller,
                       'status' => 3,
                       'current_balance' => $balance_log_current_seller,
                       'balance' => $top_up_seller,
                   ]);
               }
                // thêm trường refund vào order
                $order['refund_money'] = (int)$refund;
                $order->save();

            }


            $rating = RatingProduct::where('user_id', $user['_id'])->where('order_id', $data['order_id'])->first();
            if ($rating) {
                return $this->sendError('You have already rated this product', [], 400);
            }

            $rating = RatingProduct::create($data);
            return $this->sendResponse($rating, 'Evaluation of success');

        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    public function GetRatingProductByKiotId($id)
    {
        try {
            $rating = RatingProduct::where('kiosk_id', $id)
                ->orderBy('created_at', 'desc')
                ->paginate(4);

            if ($rating->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No ratings found for this kiosk.',
                    'data' => []
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Ratings retrieved successfully.',
                'data' => $rating->items(),
                'pagination' => [
                    'total' => $rating->total(),
                    'per_page' => $rating->perPage(),
                    'current_page' => $rating->currentPage(),
                    'last_page' => $rating->lastPage(),
                    'next_page_url' => $rating->nextPageUrl(),
                    'prev_page_url' => $rating->previousPageUrl(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error has occurred. Please try again later.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function replyRating(Request $request)
    {
        try {
            $user = auth()->user();
            $data = $request->all();
            $rating = RatingProduct::where('_id', $data['id_parent'])->first();
            if (!$rating) {
                return $this->sendError('Rating not found', [], 400);
            }
            $kiosk = Kiot::where('_id', $rating['kiosk_id'])->first();
            if ($kiosk) {
                $userKiosk = $kiosk->user_id;
            } else {
                return $this->sendError('Kiosk not found', [], 400);
            }
            if ($user['_id'] != $userKiosk) {
                return $this->sendError('You are not allowed to reply to this rating', [], 400);
            }
            $ratingReply = RatingProduct::where('id_parent', $data['id_parent'])->first();
            if ($ratingReply) {
                return $this->sendError('You have already replied to this rating', [], 400);
            }
            $rating->comment = $data['comment'];
            $rating->id_parent = $data['id_parent'];
            $rating->user_id = $user['_id'];
            $rating->name_user = $user['fullname'];
            $rating->avatar_user = $user['back_id_card'];
            $rating->email = $user['email'];
            $rating->imageGallery = [];
            // tạo object mới
            $rating = RatingProduct::create($rating->toArray());
            return $this->sendResponse($rating, 'Reply rating success');
        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    function getRatingProductById($id)
    {
        try {
            $rating = RatingProduct::where('_id', $id)->get();
            return $this->sendResponse($rating, 'Get rating product success');
        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    function getReplyRating($id)
    {
        try {
            $rating = RatingProduct::where('id_parent', $id)->get();
            return $this->sendResponse($rating, 'Get reply rating success');
        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }
    public function getQuantityRating($id_kiot) {
         $comment = RatingProduct::where('kiosk_id' , $id_kiot)->get();
        $count = $comment->count();
        // Tính tổng của trường `star`
        $totalStars = $comment->sum('star');

        // Tính trung bình (kiểm tra để tránh chia cho 0)
        $averageStars = $count > 0 ? $totalStars / $count : 0;
        $susscer = [
            'total_stars' => $totalStars,
            'average_stars' => $averageStars,
            'total_comments' => $count
        ];
        // Debug kết quả
        return $this->sendResponse($susscer, 'Reply rating success');
    }

    public function getRatingByUserId($id)
    {
        $kiot = Kiot::where('user_id', $id)->get();

        $ratings = RatingProduct::whereIn('kiosk_id', $kiot->pluck('_id'))
            ->where('id_parent', null)
            ->get();

        $count = $ratings->count();

        $averageStars = $ratings->avg(function($rating) {
            return floatval($rating->star);
        });
        $susscer = [
            'average_stars' => $averageStars ? round($averageStars, 1) : 0,
            'total_comments' => $count
        ];

        return $this->sendResponse($susscer, 'Reply rating success');
    }
}
