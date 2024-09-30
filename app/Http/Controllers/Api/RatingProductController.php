<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\ApiController as ApiController;
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
            $data['name_user'] = $user['fullname'];
            $data['avatar_user'] = $user['back_id_card'];
            $data['email'] = $user['email'];
            $data['total_like'] = 0;
            $data['status'] = 0;

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
}
