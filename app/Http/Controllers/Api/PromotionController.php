<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\ApiController as ApiController;
use App\Models\Kiot;
use App\Models\Promotion;
use Illuminate\Http\Request;
use App\Http\Requests\PromotionRequest;

class PromotionController extends ApiController
{
    public function getPromotion()
    {
        $promotions = Promotion::all();
        return $this->sendResponse($promotions, 'Promotions retrieved successfully', 200);
    }

    function getPromotionByUserId()
    {
        $user = auth()->user();
        if (empty($user)) {
            return $this->sendError('User not found', [], 400);
        }
        $promotions = Promotion::where('created_user_id', $user['_id'])->get();
        return $this->sendResponse($promotions, 'Promotions retrieved successfully', 200);
    }

    public function addPromotion(PromotionRequest $request)
    {
        try {
            $data_request = $request->all();
            $promotion = Promotion::create($data_request);
            return $this->sendResponse($promotion, 'Promotion created successfully', 200);
        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }
    public function adminAddPromotion(Request $request)
    {
        try {
            $data_request = $request->all();
            $promotion = Promotion::create($data_request);
            return $this->sendResponse($promotion, 'Promotion created successfully', 200);
        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    public function updateStatusPromotion(Request $request)
    {
        try {
            $data_request['_id'] = $request['_id'];
            $data_request['status'] = $request['status'];
            $promotion = Promotion::find($data_request['_id']);
            if (empty($promotion)) {
                return $this->sendError('Promotion not found', [], 400);
            }
            $promotion->update($data_request);
            return $this->sendResponse($promotion, 'Promotion updated successfully', 200);
        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    public function removePromotion($id)
    {
        $promotion = Promotion::find($id);
        if (empty($promotion)) {
            return $this->sendError('Promotion not found', [], 400);
        }
        $promotion->delete();
        return $this->sendResponse([], 'Promotion deleted successfully', 200);
    }

    public function getPromotionByPromotionCode(Request $request)
    {
        try {
            $promotionCode = $request->promotion_code;
            $promotion = Promotion::where('promotion_code', $promotionCode)->first();

            if (empty($promotion)) {
                return $this->sendError('Promotion not found', [], 400);
            }

            $startDay = $promotion['start_date'];
            $endtDay = $promotion['end_date'];
            $startDayTimestamp = strtotime($startDay);
            $endtDayTimestamp = strtotime($endtDay);
            $currentTimestamp = time();
            $using = (int)($promotion['total_for_using'] ?? 0); // Đảm bảo `$using` có giá trị mặc định

            if ($startDayTimestamp > $currentTimestamp) {
                $status = 0; // Chưa bắt đầu
            } elseif ($endtDayTimestamp < $currentTimestamp) {
                $status = 2; // Đã kết thúc
            } elseif ($using <= 0) {
                $status = 4; // Hết lượt sử dụng
            } else {
                $status = 1; // Có thể sử dụng
            }


            if (empty($promotion)) {
                return $this->sendError('Promotion not found', [], 400);
            }

            $kiot = Kiot::where('user_id', $promotion['created_user_id'])->get();

            if ($kiot == null || empty($kiot)) {
                return $this->sendError('This discount code cannot be used at this store', [], 400);
            }else {
                $success['status'] = $status;
                $success['expires_at'] = now()->addDays(3);
                $success['data'] = $promotion;
                return $this->sendResponse($success, 'Promotion retrieved successfully', 200);
            }


        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }
    public function getAdminPromiton(Request $request)
    {
        $perPage = $request->get('per_page', 20);
        $currentPage = $request->get('page', 1);
        $promotions = Promotion::where('is_admin_created', 1)->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $currentPage);
        $response = [
            'data' => $promotions->items(),
            'meta' => [
                'total' => $promotions->total(),
                'per_page' => $promotions->perPage(),
                'current_page' => $promotions->currentPage(),
                'last_page' => $promotions->lastPage(),
                'next_page_url' => $promotions->nextPageUrl(),
                'prev_page_url' => $promotions->previousPageUrl(),
            ]
        ];

        return $this->sendResponse($response, 'Balance Log retrieved successfully.');
    }

    public function searchAdminPromiton(Request $request)
    {
        $promotion_code = $request->get('promotion_code');
        $perPage = $request->get('per_page', 20);
        $currentPage = $request->get('page', 1);
        $promotions = Promotion::where('is_admin_created', 1)->where('promotion_code', 'like', '%' . $promotion_code . '%')->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $currentPage);
        $response = [
            'data' => $promotions->items(),
            'meta' => [
                'total' => $promotions->total(),
                'per_page' => $promotions->perPage(),
                'current_page' => $promotions->currentPage(),
                'last_page' => $promotions->lastPage(),
                'next_page_url' => $promotions->nextPageUrl(),
                'prev_page_url' => $promotions->previousPageUrl(),
            ]
        ];

        return $this->sendResponse($response, 'Balance Log retrieved successfully.');
    }

    public function adminUpdateDataPromotion(Request $request)
    {
        try {
            $data_request['_id'] = $request['_id'];
            $data_request['description'] = $request['description'];
            $data_request['amount'] = $request['amount'];
            $data_request['max_amount'] = $request['max_amount'];
            $data_request['type'] = $request['type'];
            $data_request['total_for_using'] = $request['total_for_using'];
            $data_request['percent'] = $request['percent'];
            $data_request['start_date'] = $request['start_date'];
            $data_request['end_date'] = $request['end_date'];
            $promotion = Promotion::find($data_request['_id']);
            if (empty($promotion)) {
                return $this->sendError('Promotion not found', [], 400);
            }
            $promotion->update($data_request);
            return $this->sendResponse($promotion, 'Promotion updated successfully', 200);
        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    public function getAdminPromotionById($id)
    {
        $promotion = Promotion::where('_id', $id)->where('is_admin_created', 1)->get();
        if (empty($promotion)) {
            return $this->sendError('Promotion not found', [], 400);
        }
        return $this->sendResponse($promotion, 'Promotion retrieved successfully', 200);
    }
}
