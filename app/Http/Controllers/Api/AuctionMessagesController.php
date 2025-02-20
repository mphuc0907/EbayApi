<?php

namespace App\Http\Controllers\Api;

use App\Models\auction_messages;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
class AuctionMessagesController extends ApiController
{
    // Lấy danh sách tin nhắn (phân trang)
    private function formatMessage($message)
    {
        return [
            'id' => $message->_id,
            'user_id' => $message->user_id,
            'user_name' => $message->name,
            'message' => $message->message,
            'created_at' => $message->created_at->format('Y-m-d H:i:s'),
            'is_system' => $message->role === 4,
            'avatar' => $message->avatar
        ];
    }
    public function getMessageAuction(Request $request)
    {
        $request->validate([
            'auction_id' => 'required',
            'page' => 'integer|min:1',
        ]);

        $perPage = 20;

        $messages = auction_messages::where('auction_id', $request->auction_id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $formattedMessages = collect($messages->items())->map(function($message) {
            return $this->formatMessage($message);
        });

        return response()->json([
            'status' => true,
            'data' => $formattedMessages,
            'meta' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'total' => $messages->total()
            ]
        ]);
    }

    // Lấy tin nhắn mới
    public function getNewMessages(Request $request)
    {
        $request->validate([
            'auction_id' => 'required',
            'last_id' => 'required|string'
        ]);

        // Lấy tin nhắn mới hơn last_id và sắp xếp theo thời gian tăng dần
        $lastMessage = auction_messages::find($request->last_id);
        if (!$lastMessage) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid last message ID'
            ], 400);
        }

        $messages = auction_messages::where('auction_id', $request->auction_id)
            ->where('created_at', '>', $lastMessage->created_at)
            ->orderBy('created_at', 'asc')
            ->get();

        $formattedMessages = $messages->map(function($message) {
            return $this->formatMessage($message);
        });

        return response()->json([
            'status' => true,
            'data' => $formattedMessages
        ]);
    }

    // Gửi tin nhắn mới
    public function sendMessageAuction(Request $request)
    {
        $request->validate([
            'auction_id' => 'required',
            'message' => 'required|string|max:500'
        ]);

        try {
            $user = auth()->user();
            $message = new auction_messages();
            $message->auction_id = $request->auction_id;
            $message->user_id = $user['_id'];
            $message->name = $user['name'];
            $message->message = $request->message;
            $message->role = $user['role'];
            $message->avatar = $user['back_id_card'];
            $message->save();

            return response()->json([
                'status' => true,
                'message' => 'Message sent successfully',
                'data' => $this->formatMessage($message)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to send message'
            ], 500);
        }
    }

        public function getAllMessage(Request $request)
    {
        $request->validate([
            'auction_id' => 'required',
        ]);

        $messages = auction_messages::where('auction_id', $request->auction_id)
            ->orderBy('created_at', 'desc')
            ->get();

        $formattedMessages = collect($messages)->map(function($message) {
            return $this->formatMessage($message);
        });

        return response()->json([
            'status' => true,
            'data' => $formattedMessages,
        ]);
    }
}
