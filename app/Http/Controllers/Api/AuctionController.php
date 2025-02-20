<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\auction;
use App\Models\auction_log;
use App\Models\auction_messages;
use App\Models\auction_sub;
use App\Models\balance;
use App\Models\Category;
use App\Models\CategoryParent;
use App\Models\Kiot;
use App\Models\log_user_auction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\ApiController as ApiController;
use function Symfony\Component\Clock\get;

class AuctionController extends ApiController
{
    public function createAuction()
    {
        try {
            // Chuyển tất cả các bản ghi có status = 0 thành 1
            auction::whereIn('status', [0, 1])->update(['status' => 2]);


            // Tạo dữ liệu cho mục đấu giá mới
            $data['time'] = time();
            $data['max_user'] = 0;
            $data['max_view'] = 0;
            $data['time_auction'] = now()->addDays(7)->timestamp; // Thời gian đấu giá cộng thêm 7 ngày từ hiện tại
            $data['status'] = 0; // Đánh dấu trạng thái là bắt đầu (0)

            // Tạo mới mục đấu giá
            $auction = auction::create($data);

            // Thiết lập phản hồi trả về
            $success['expires_at'] = now()->addDays(3);
            $success['data_value'] = $auction;

            return $this->sendResponse($success, 'Created a new auction successfully');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    public function updateAuction($id, Request $request)
    {
        try {
            $auction = auction::find($id);
            $auction->max_user = $request['max_user'];
            $auction->max_view = $request['max_view'];
            $auction->save();
            $success['expires_at'] = now()->addDays(3);
            $success['data_value'] = $auction;
            return $this->sendResponse($success, 'Created a new auction successfully');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    public function addAuction(Request $request)
    {
        try {
            $request->validate([
                'auction_id' => 'required|exists:auction,_id', // ID của người nhận
                'category_id' => 'required|exists:category,_id',
            ]);

            // Kiểm tra xem dữ liệu đã tồn tại chưa
            $existingAuction = auction_sub::where('id_auctions', $request['auction_id'])
                ->where('category_id', $request['category_id'])
                ->first();

            if ($existingAuction) {
                return $this->sendError('This auction and category combination already exists.');
            }

            $data['id_auctions'] = $request['auction_id'];
            $data['category_id'] = $request['category_id'];
            $data['time_end'] = time() + 85;
            $auction = auction_sub::create($data);
            $success['expires_at'] = now()->addDays(3);
            $success['data_value'] = $auction;
            return $this->sendResponse($success, 'Created a new auction successfully');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    public function updateAu($id, Request $request)
    {
        try {
            $auction = auction_sub::find($id);
            $auction->max_price = $request['max_price'];
            $auction->success_user_id = $request['success_user_id'];
            $auction->save();
            $success['expires_at'] = now()->addDays(3);
            $success['data_value'] = $auction;
            return $this->sendResponse($success, 'Created a new auction successfully');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    public function targetMoney(Request $request)
    {
        try {
            $user = auth()->user();
            $id_user = $user['_id'];
            $request->validate([
                'auction_price' => 'required', // ID của người nhận
                'auction_sub_id' => 'required|exists:auction_sub,_id',
            ]);
            //Kiểm tra trong có đủ tiền không
            $balance = balance::where('user_id', $id_user)->first();
            //Số dư còn lại
            $curr_balance = $balance['balance'];

            if ($curr_balance <= $request['auction_price']) {
                return $this->sendError('Số tiền trong ví bạn không đủ vui lòng nạp thêm tiền', [], 400);
            } else {
                $balance['balance'] = (int)$curr_balance - (int)$request['auction_price'];
                $balance->save();
            }
            $latestLogs = auction_log::where('auction_sub_id', $request['auction_sub_id'])
                ->orderBy('created_at', 'desc')
                ->first();
            //kiểm tra thời gian
            if (!empty($latestLogs)) {
                $user_bla = $latestLogs['user_id'];
                $amount_auction = $latestLogs['auction_price'];

                $balance_user = balance::where('user_id', $user_bla)->first();
                $curr_balance_user = $balance_user['balance'];
                $balance_user['balance'] = (int)$curr_balance_user + (int)$amount_auction;
                $balance_user->save();
            }

            $data['auction_price'] = $request['auction_price'];
            $data['auction_sub_id'] = $request['auction_sub_id'];
            $data['user_id'] = $id_user;
            $auction_log = auction_log::create($data);

            //Kiểm tra hoàn tiền


            $auction = auction_sub::find($request['auction_sub_id']);
            $auction->max_price = $auction_log['auction_price'];
            $auction->success_user_id = $id_user;
            $auction->time_end = time() + 85;
            $auction->save();
            $success['expires_at'] = now()->addDays(3);
            $success['data_value'] = $auction_log;
            return $this->sendResponse($success, 'Created a new auction successfully');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    public function sendMess(Request $request)
    {
        try {
            $user = auth()->user();
            $id_user = $user['_id'];
            $request->validate([
                'auction_id' => 'required|exists:auction,_id', // ID của người nhận
                'message' => 'required|string',
            ]);
            $data['id_auction'] = $request['auction_id'];
            $data['message'] = $request['message'];
            $data['user_id'] = $id_user;

            $auction_mess = auction_messages::create($data);
            $success['expires_at'] = now()->addDays(3);
            $success['data_value'] = $auction_mess;
            return $this->sendResponse($success, 'Created a new auction successfully');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    //Get auction

    public function getAuction()
    {
        $auctions = auction::orderBy('updated_at', 'desc')->get();

        return $auctions;
    }

    public function getAuctionDetail($id)
    {
        // Lấy thông tin từ bảng auction
        $auction = auction::find($id);

        if (!$auction) {
            return $this->sendError('Auction not found');
        }

        // Lấy thông tin từ bảng auction_sub liên quan đến auction này
        $auctionSub = auction_sub::where('id_auction', $id)->get();

        // Lấy thông tin từ bảng auction_log dựa trên auction_sub_id
        $auctionLog = auction_log::whereIn('auction_sub_id', $auctionSub->pluck('id'))->get();

        // Lấy thông tin từ bảng auction_messages liên quan đến auction này
        $auctionMessages = auction_messages::where('id_auction', $id)->get();

        // Tách dữ liệu theo từng loại thông tin của các bảng
        $data = [
            'auction' => [
                'id' => $auction->id,
                'time' => $auction->time,
                'max_user' => $auction->max_user,
                'max_view' => $auction->max_view,
                'time_auction' => $auction->time_auction,
                'created_at' => $auction->created_at,
            ],
            'auction_sub' => $auctionSub->map(function ($sub) {
                return [
                    'id' => $sub->id,
                    'id_auction' => $sub->id_auction,
                    'max_price' => $sub->max_price,
                    'category_id' => $sub->category_id,
                    'success_user_id' => $sub->success_user_id,
                    'created_at' => $sub->created_at,
                ];
            }),
            'auction_log' => $auctionLog->map(function ($log) {
                return [
                    'id' => $log->id,
                    'auction_sub_id' => $log->auction_sub_id,
                    'user_id' => $log->user_id,
                    'auction_price' => $log->auction_price,
                    'created_at' => $log->created_at,
                ];
            }),
            'auction_messages' => $auctionMessages->map(function ($message) {
                return [
                    'id' => $message->id,
                    'id_auction' => $message->id_auction,
                    'user_id' => $message->user_id,
                    'message' => $message->message,
                    'created_at' => $message->created_at,
                ];
            }),
        ];

        return $this->sendResponse($data, 'Auction details retrieved successfully');
    }

    //Check stats auction

    public function checkAndCreateAuction(Request $request)
    {
        try {
            // Validate auction_id
            $request->validate([
                'id_auction' => 'required|exists:auction,_id',
            ]);

            $id_auction = $request['id_auction'];
            $currentTime = time();

            // Get all categories from database
            $categories = Category::all();
            $allCategoriesUsed = true;

            foreach ($categories as $category) {
                // Check for existing auction with auction_id and category_id
                $existingAuction = auction_sub::where('id_auctions', $id_auction)
                    ->where('category_id', $category->id)
                    ->orderBy('created_at', 'desc')
                    ->first();

                if ($existingAuction) {
                    // If the most recent auction with this category_id is still active
                    if ($existingAuction->time_end > $currentTime) {
                        // Active auction exists, return the existing auction data
                        return response()->json([
                            'message' => 'Phiên đấu giá hiện tại vẫn đang hoạt động cho category này',
                            'existing_auction' => $existingAuction // Return the existing auction data
                        ]);
                    } else {
                        // The existing auction has ended
                        // We do not set $allCategoriesUsed to false, as it means the category is still in use
                        // We can skip to the next category
                        continue;
                    }
                }

                // If no existing auction found for this category, mark category as not fully used
                $allCategoriesUsed = false;

                // Check for duplicates just in case (even though we're in the previous condition)
                $duplicateCheck = auction_sub::where('id_auctions', $id_auction)
                    ->where('category_id', $category->id)
                    ->exists();

                // If no duplicates, create a new auction
                if (!$duplicateCheck) {
                    // Create a new auction with end_time set to current time + 85 seconds
                    $data = [
                        'id_auctions' => $id_auction,
                        'category_id' => $category->_id,
                        'max_price' => 0,
                        'success_user_id' => "",
                        'time_end' => time() + 85,
                    ];
                    $newAuction = auction_sub::create($data);
                    return response()->json(['message' => 'Tạo phiên đấu giá mới thành công', 'data' => $newAuction]);
                }
            }

            // If all categories are used, update the auction status
            if ($allCategoriesUsed) {
                $auction = auction::find($id_auction);
                $auction->status = 2;
                $auction->save();
                // Tạo dữ liệu cho mục đấu giá mới
                auction::whereIn('status', [0, 1])->update(['status' => 2]);

                $dataauction['time'] = time();
                $dataauction['max_user'] = 0;
                $dataauction['max_view'] = 0;
                $dataauction['time_auction'] = now()->addDays(7)->timestamp; // Thời gian đấu giá cộng thêm 7 ngày từ hiện tại
                $dataauction['status'] = 0; // Đánh dấu trạng thái là bắt đầu (0)

                // Tạo mới mục đấu giá
                $auction = auction::create($dataauction);
                return response()->json(['message' => 'Tất cả các category đã có phiên đấu giá cho auction_id này. Không thể tạo thêm. Trạng thái của auction đã được cập nhật.']);
            }

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function checkStatus()
    {
        try {
            $auction = auction::orderBy('created_at', 'desc')->first();
            return $this->sendResponse($auction, 'Auction details retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }
    public function checkStatusByID($id)
    {

        try {

            $auction = auction::find($id);

            return $this->sendResponse($auction, 'Auction details retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }
    public function getAuctionSub($id)
    {
        try {
            $auction = auction_sub::find($id);
            return $this->sendResponse($auction, 'Auction details retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    public function getLogAuction(Request $request)
    {
        try {
            $auction_sub_id = $request->input('auction_sub_id');

            // Lấy tất cả bản ghi đấu giá liên quan đến auction_sub_id
            $auction = auction_log::where('auction_sub_id', $auction_sub_id)->get();

            // Thêm tên người dùng vào mỗi bản ghi
            foreach ($auction as $key => $value) {
                $user = User::find($value->user_id);
                $auction_sub = auction_sub::find($value->auction_sub_id);
                $value->name_user = $user ? $user->name : 'Unknown';
                $value->id_user = $user ? $user->_id : 'Unknown';
                $value->time_end = $auction_sub ? $auction_sub->time_end : 'Unknown';
            }

            return $this->sendResponse($auction, 'Auction details retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    public function getAuctionUser($id)
    {

        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $id_user = $user['_id'];

        // Lấy auction dựa trên ID
        $auction = auction::find($id);
        if (!$auction) {
            return response()->json(['message' => 'Auction not found'], 404);
        }

        // Lấy danh sách auction_sub liên quan đến auction và người dùng
        $auction_sub = auction_sub::where('id_auctions', $auction['_id'])
            ->where('success_user_id', $id_user)
            ->get();

        if ($auction_sub->isEmpty()) {
            return response()->json(['message' => 'No auction_sub found for this user'], 404);
        }

        // Lấy danh sách category ID từ auction_sub
        $category_ids = $auction_sub->pluck('category_id')->toArray();

        // Lấy danh sách categories dựa trên ID
        $categories = Category::whereIn('_id', $category_ids)->get();

        // Trả về dữ liệu bao gồm auction_sub và categories
        return response()->json([
            'auction_sub' => $auction_sub,
            'categories' => $categories,
        ]);
    }


    public function getAuctionView($id)
    {
//        $user = auth()->user();
//        $id_user = $user['_id'];

        // Lấy auction thứ 2 từ cái mới nhất
        $auction = auction::find($id);

        // Kiểm tra nếu tồn tại auction thứ 2
        if (!$auction) {
            return response()->json(['message' => 'No second auction found'], 404);
        }

        // Lấy danh sách auction_sub với auction thứ 2
        $auction_sub = auction_sub::where('id_auctions', $auction['_id'])
//            ->where('success_user_id', $id_user)
            ->get();

        // Tạo mảng chứa thông tin categories
        $categories = [];
        foreach ($auction_sub as $value) {
            $category = Category::where('_id', $value['category_id'])->first();
            if ($category) {
                $categories[] = $category; // Thêm category vào mảng categories
            }
        }

        // Trả về dữ liệu bao gồm cả auction_sub và categories
        return response()->json([
            'auction_sub' => $auction_sub,
            'categories' => $categories
        ]);
    }

    public function UpViewAuction(Request $request)
    {
        $user = auth()->user();
        $id_user = $user['_id'];
        $name_user = $user['name'];
        $auction_id = $request['auction_id'];

        $auction = auction::find($auction_id);
        if ($auction) {
            // Tăng lượt xem của phiên đấu giá
            $curronView = $auction->max_view;
            $auction->max_view = (int)$curronView + 1;
            $auction->save();

            // Kiểm tra xem user đã vào phiên đấu giá chưa
            $log_user_auction = log_user_auction::where('id_action', $auction_id)
                ->where('id_user', $id_user)
                ->first();

            if (!$log_user_auction) {
                // Nếu chưa có log, tạo mới một log cho phiên đấu giá này
                $data['id_action'] = $auction_id;
                $data['id_user'] = $id_user;
                $data['name_user'] = $name_user;
                log_user_auction::create($data);
            }

            // Đếm số người đã tham gia
            $participantCount = log_user_auction::where('id_action', $auction_id)->count();
            $auction->max_user = $participantCount;
            $auction->save();
            // Trả về thông tin số view và số người đã tham gia
            return response()->json([
                'success' => true,
                'view_count' => $auction->max_view,
                'participant_count' => $participantCount,
            ]);
        } else {
            // Trường hợp không tìm thấy phiên đấu giá
            return response()->json(['error' => 'Phiên đấu giá không tồn tại'], 404);
        }
    }

    public function AuctionByKiot(Request $request)
    {
        try {
            $request->validate([
                'id_auction' => 'required|exists:auction,_id',
                'id_kiot' => 'required|exists:kiot,_id',
            ]);
            $id_auction = $request['id_auction'];
            $id_kiot = $request['id_kiot'];
            $kiot = Kiot::find($id_kiot);
            $kiot->is_sponsorship = $id_auction;
            $kiot->save();
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getSponsorship(Request $request) {
        $id_category = $request['category_id'];

        $id_auction = $request['id_auction'];
        $kiot = Kiot::where('category_parent_id', $id_category)->where('status','1')->where('is_sponsorship', $id_auction)->get();
        $categories = CategoryParent::find($id_category);
        $name_cate = $categories->name;
        return $kiot;
    }
    public function GetKiotByAuction($id){
        $user = auth()->user();
        $id_user = $user['_id'];
        $kiot = Kiot::where('is_sponsorship', $id)->where('status', '1')->where('user_id', $id_user)->get();
        return $kiot;
    }
    public function GetKiotAdu(Request $request)
    {
        try {
            // Xác thực các tham số đầu vào
            $request->validate([
                'id_auction' => 'required|exists:auction,_id',
                'category_id' => 'required|exists:category,_id',
            ]);

            // Lấy tham số từ request
            $id_auction = $request->input('id_auction');
            $category_id = $request->input('category_id');

            // Tìm các kiot theo điều kiện
            $kiot = Kiot::where('is_sponsorship', $id_auction)
                ->where('category_id', $category_id)
                ->where('status', '1')
                ->get();

            // Trả về dữ liệu dưới dạng JSON
            return response()->json(['data' => $kiot], 200);

        } catch (\Exception $e) {
            // Xử lý lỗi và trả về thông báo lỗi
            return response()->json(['error' => 'Đã xảy ra lỗi: ' . $e->getMessage()], 500);
        }
    }

    public function StartNow($id,Request $request){
        $auction = auction::find($id);
        $auction->status = $request['status'];
        if ($request['status'] == 1) {
            $auction->time_auction = time();
        }
        $auction->save();
        return $this->sendResponse($auction, 'Phiên đấu giá đã bắt đầu');

    }

}
