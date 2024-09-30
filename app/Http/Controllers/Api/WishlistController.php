<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\ApiController as ApiController;

class WishlistController extends ApiController
{
    public function getWishlist($id){
        $wishlist = Wishlist::where('user_id', $id)->get();
        return $wishlist;
    }

    public function checkWishlist($kiosk_id){
        $user = auth()->user();
        $wishlist = Wishlist::where('kiosk_id', $kiosk_id)->where('user_id', $user['_id'])->first();
        if ($wishlist) {
            return $this->sendResponse($wishlist, 'This product is already in the favorites list.');
        } else {
            return $this->sendError('This product is not in the favorites list.', ['error' => 'This product is not in the favorites list.'], 400);
        }
    }

    public function Addwishlist(Request $request){
        try {
            $user = auth()->user();
            $data['kiosk_id'] = $request['kiosk_id'];
            $data['user_id'] = $user['_id'];
            // check
            $wishlist = Wishlist::where('kiosk_id', $data['kiosk_id'])->where('user_id', $data['user_id'])->first();
            if ($wishlist) {
                return $this->sendError('This product is already in the favorites list.', ['error' => 'This product is already in the favorites list.'], 400);
            }
            $wishlist = Wishlist::create($data);
            return $this->sendResponse(null, 'Successfully added to the favorites list.');
        } catch (\Exception $e) {
            // Trả về phản hồi lỗi
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }
    public function Deletewishlist($id) {
        $wishlist = Wishlist::find($id);
        $wishlist->delete();
        return "Successfully removed from the favorites list.";
    }
}
