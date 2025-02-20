<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\auction;
use App\Models\auction_log;
use App\Models\auction_sub;
use App\Models\Category;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use MongoDB\Laravel\Eloquent\Casts\ObjectId;

class AuctionLogController extends ApiController
{
    /**
     * Get detailed auction information
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAuctionDetail($id)
    {
        try {
            // Tìm auction
            $auction = auction::find($id);

            if (!$auction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy phiên đấu giá'
                ], 404);
            }

            // Tìm các auction_sub liên quan
            $auctionSubs = auction_sub::where('id_auctions', $id)->get();

            $detailedResponse = [];

            foreach ($auctionSubs as $auctionSub) {
                // Lấy thông tin category
                $category = $auctionSub->category_id ? Category::find($auctionSub->category_id) : null;

                // Lấy thông tin người thắng
                $winner = $auctionSub->success_user_id ? User::find($auctionSub->success_user_id) : null;

                // Lấy lịch sử đấu giá và thông tin user tương ứng
                $bidHistory = auction_log::where('auction_sub_id', (string)$auctionSub->_id)->get();
                $bidHistoryData = [];

                foreach ($bidHistory as $bid) {
                    $bidUser = User::find($bid->user_id);
                    $bidHistoryData[] = [
                        'user_name' => $bidUser ? $bidUser->name : 'Unknown User',
                        'user_fullname' => $bidUser ? $bidUser->fullname : '',
                        'bid_amount' => floatval($bid->auction_price),
                        'bid_time' => Carbon::parse($bid->created_at)->format('Y-m-d H:i:s')
                    ];
                }

                $detailedResponse[] = [
                    'auction_sub_id' => (string)$auctionSub->_id,
                    'category' => $category ? [
                        'id' => (string)$category->_id,
                        'name' => $category->name,
                        'detail' => $category->detaill
                    ] : null,
                    'auction_date' => [
                        'start' => Carbon::createFromTimestamp($auction->time)->format('Y-m-d H:i:s'),
                        'end' => Carbon::createFromTimestamp($auctionSub->time_end)->format('Y-m-d H:i:s')
                    ],
                    'winner' => $winner ? [
                        'id' => (string)$winner->_id,
                        'name' => $winner->name,
                        'fullname' => $winner->fullname,
                        'phone' => $winner->phone,
                        'winning_amount' => floatval($auctionSub->max_price)
                    ] : null,
                    'bid_history' => $bidHistoryData,
                    'current_price' => floatval($auctionSub->max_price),
                    'status' => $this->getAuctionStatus($auction->status, $auctionSub)
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'auction_id' => (string)$auction->_id,
                    'time' => Carbon::createFromTimestamp($auction->time)->format('Y-m-d H:i:s'),
                    'max_user' => $auction->max_user,
                    'max_view' => $auction->max_view,
                    'time_auction' => Carbon::createFromTimestamp($auction->time_auction)->format('Y-m-d H:i:s'),
                    'status' => $auction->status,
                    'created_at' => Carbon::parse($auction->created_at)->format('Y-m-d H:i:s'),
                    'updated_at' => Carbon::parse($auction->updated_at)->format('Y-m-d H:i:s'),
                    'sub_auctions' => $detailedResponse
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get auction status based on auction status and sub auction data
     *
     * @param int $auctionStatus
     * @param auction_sub $auctionSub
     * @return string
     */
    private function getAuctionStatus($auctionStatus, $auctionSub)
    {
        $now = now();
        $endTime = Carbon::createFromTimestamp($auctionSub->time_end);

        if ($auctionSub->success_user_id) {
            return 'COMPLETED';
        }

        if ($auctionStatus === 2) {
            return 'ENDED';
        }

        if ($now->isAfter($endTime)) {
            return 'ENDED';
        }

        return 'ONGOING';
    }
}
