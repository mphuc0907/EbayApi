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

            $kiot = Kiot::where('user_id', $promotion['created_user_id'])->get();

            if ($kiot == null || empty($kiot)) {
                return $this->sendError('This discount code cannot be used at this store', [], 400);
            }else {
                return $this->sendResponse($promotion, 'Promotion retrieved successfully', 200);
            }


        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }
}
