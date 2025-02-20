<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\ApiController as ApiController;
use App\Http\Controllers\Controller;
use App\Models\balance;
use App\Models\balance_log;
use App\Models\Kiot;
use App\Models\Order;
use App\Models\RatingPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class RatingPostController extends ApiController
{
    public function RatingPost()
    {
        $rating_post = DB::table('rating_post')->get();
        print $rating_post;
    }

    function GetRatingPostByPostId($id_post)
    {
        if (empty($id_post)) {
            return $this->sendError('Id post is required', [], 400);
        } else {
            $rating_post = RatingPost::where('id_post', $id_post)->get();
            // chỉ lấy những bình luận có status != 0
            return $this->sendResponse($rating_post, 'Rating post successfully.');
        }
    }

    function checkRatingPostById($id)
    {
        $rating_post = RatingPost::where('parent_id', $id)->get();
        if (count($rating_post) > 0) {
            return true;
        } else {
            return false;
        }
    }

    function getRatingPostByParentId($parent_id)
    {
        if (empty($parent_id)) {
            return $this->sendError('Parent id is required', [], 400);
        } else {
            $rating_post = RatingPost::where('parent_id', $parent_id)->get();
            return $this->sendResponse($rating_post, 'Rating post successfully.');
        }
    }

    function GetRatingPostById($id)
    {
        try {
            $rating_post = RatingPost::find($id);
            return $this->sendResponse($rating_post, 'Rating post successfully.');

        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    public function AddRatingPost(Request $request)
    {
        try {
            if (!empty(auth()->user())) {
                $user = auth()->user();
                $data['user_id'] = $user['_id'];
                $data['email'] = $user['email'];
            }
            $data['post_owner_id'] = $request['post_owner_id'];
            $data['comment'] = $request['comment'];
            $data['donate'] = $request['donate'];
            $data['id_post'] = $request['id_post'];
            $data['parent_id'] = $request['parent_id'];
            $data['status'] = 1;
            if ($data['parent_id'] == null) {
                $donate = $data['donate'];
                $balance = balance::where('user_id', $user['_id'])->first();
                $current_balance = $balance['balance'];
                $balance['balance'] = $current_balance - $donate;
                $balance->save();
                //
                $id_balance = $balance['_id'];
                $action_user = 'Donate  ' . $donate . ' to post ' . $data['id_post'];
                $transaction_status = 'donate';
                $top_up = $donate;
                $last_balance = $current_balance;
                $balance_log_current = $current_balance - $donate;
                $balance_log = balance_log::create([
                    'user_id' => $user['_id'],
                    'id_balance' => $id_balance,
                    'action_user' => $action_user,
                    'last_balance' => $last_balance,
                    'transaction_status' => $transaction_status,
                    'current_balance' => $balance_log_current,
                    'status' => 3,
                    'balance' => $top_up,
                ]);

                // cộng tiền cho người đăng bài
                $balance_post_owner = balance::where('user_id', $data['post_owner_id'])->first();
                $current_balance_post_owner = $balance_post_owner['balance'];
                $balance_post_owner['balance'] = $current_balance_post_owner + $donate;
                $balance_post_owner->save();
                //
                $id_balance_post_owner = $balance_post_owner['_id'];
                $action_user_post_owner = 'Receive  ' . $donate . ' from post ' . $data['id_post'];
                $transaction_status_post_owner = 'receive';
                $top_up_post_owner = $donate;
                $last_balance_post_owner = $current_balance_post_owner;
                $balance_log_current_post_owner = $current_balance_post_owner + $donate;
                $balance_log_post_owner = balance_log::create([
                    'user_id' => $data['post_owner_id'],
                    'id_balance' => $id_balance_post_owner,
                    'action_user' => $action_user_post_owner,
                    'last_balance' => $last_balance_post_owner,
                    'transaction_status' => $transaction_status_post_owner,
                    'current_balance' => $balance_log_current_post_owner,
                    'status' => 3,
                    'balance' => $top_up_post_owner,
                ]);


            }
            $rating_post = RatingPost::create($data);

            return $this->sendResponse(null, 'Rating post successfully.');

        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    public function getRatingPostByUserId($id_post)
    {
        try {
            if (!empty(auth()->user())) {
                $user = auth()->user();
                $rating_post = RatingPost::where('post_owner_id', $user['_id'])
                    ->where('id_post', $id_post)
                    ->whereNull('parent_id')
                    ->get();

                $data_response = $rating_post->map(function ($item) {
                    $user = DB::table('users')->where('_id', $item['user_id'])->first();
                    if ($user) {
                        $item['avatar'] = $user['back_id_card'];
                        $item['name'] = $user['name'];
                    } else {
                        $item['avatar'] = null;
                        $item['name'] = 'Unknown';
                    }
                    return $item;
                });

                return $this->sendResponse($data_response, 'Rating post successfully.');
            }
        } catch (\Exception $e) {
            return $this->sendError('An error has occurred: ' . $e->getMessage(), [], 400);
        }
    }

    public function updateStatusRatingPost(Request $request, $id_post)
    {
        try {
            if (!$request->has('status')) {
                return $this->sendError('Status is required.', [], 400);
            }
            if ($request->input('status') != 0 && $request->input('status') != 1) {
                return $this->sendError('Status is invalid.', [], 400);
            }
            $rating_post = RatingPost::find($id_post);

            if (!$rating_post) {
                return $this->sendError('Rating post not found.', [], 404);
            }
            $rating_post->status = $request->input('status');
            $rating_post_child = RatingPost::where('parent_id', $id_post)->get();
            foreach ($rating_post_child as $item) {
                $item->status = $request->input('status');
                $item->save();
            }
            $rating_post->save();
            return $this->sendResponse(null, 'Rating post status updated successfully.');
        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }



}
