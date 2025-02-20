<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\ApiController as ApiController;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Messages;
use App\Models\Participants;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

//use MongoDB\BSON\ObjectId;

class MessageController extends ApiController
{
    protected $telegramController;

    public function __construct(TelegramController $telegramController)
    {
        $this->telegramController = $telegramController;
    }

    public function sendMessageSystemBot(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $user_sys = User::where('role', 5)->first();
        $id_system_bot = $user_sys['_id'];
        define('SYSTEM_BOT_ID', $id_system_bot);

        try {
            $users = User::where('role', '!=', 5)
                ->get();
            $telegram_messages = [];

            foreach ($users as $user) {
                // Kiểm tra conversation đã tồn tại
                $conversation = Conversation::where(function ($query) use ($user) {
                    $query->where('id_user1', SYSTEM_BOT_ID)
                        ->where('id_user2', $user->id);
                })->first();

                if ($user->id == SYSTEM_BOT_ID) {
                    continue;
                }
                // Tạo conversation mới nếu chưa tồn tại
                if (!$conversation) {
                    $conversation = Conversation::create([
                        'id_user1' => SYSTEM_BOT_ID,
                        'id_user2' => $user->id,
                        'last_mess' => $request->message,
                        'last_mess_id' => SYSTEM_BOT_ID
                    ]);
                }

                // Tạo tin nhắn mới
                Messages::create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => SYSTEM_BOT_ID,
                    'message' => $request->message,
                    'status' => 0
                ]);

                // Cập nhật conversation
                $conversation->update([
                    'last_mess' => $request->message,
                    'last_mess_id' => SYSTEM_BOT_ID,
                    'updated_at' => now()
                ]);
                if (!empty($user->telegram_chat_id)) {
                    $telegram_messages[] = [
                        'chat_id' => $user->telegram_chat_id,
                        'message' => "You have a new message from system_bot. Please check your message on the website."
                    ];
                }
            }
            foreach ($telegram_messages as $telegram_message) {
                $this->telegramController->sendTelegramMessage($telegram_message['chat_id'], $telegram_message['message']);
            }
            return response()->json([
                'status' => 'success',
                'message' => 'Message sent to all users successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function sendMessageCustomerService(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $user_sys = User::where('role', 4)->first();
        $customer_service_id = $user_sys['_id'];
        define('CUSTOMER_SERVICE_ID', $customer_service_id);

        try {
            $users = User::where('role', '!=', 4)
                ->where('role', '!=', 5)
                ->where('role', '!=', 7)
                ->where('role', '!=', 6)
                ->get();
            $telegram_messages = [];

            foreach ($users as $user) {
                // Kiểm tra conversation đã tồn tại
                $conversation = Conversation::where(function ($query) use ($user) {
                    $query->where('id_user1', CUSTOMER_SERVICE_ID)
                        ->where('id_user2', $user->id);
                })->first();

                if ($user->id == CUSTOMER_SERVICE_ID) {
                    continue;
                }
                // Tạo conversation mới nếu chưa tồn tại
                if (!$conversation) {
                    $conversation = Conversation::create([
                        'id_user1' => CUSTOMER_SERVICE_ID,
                        'id_user2' => $user->id,
                        'last_mess' => $request->message,
                        'last_mess_id' => CUSTOMER_SERVICE_ID
                    ]);
                }

                // Tạo tin nhắn mới
                Messages::create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => CUSTOMER_SERVICE_ID,
                    'message' => $request->message,
                    'status' => 0
                ]);

                // Cập nhật conversation
                $conversation->update([
                    'last_mess' => $request->message,
                    'last_mess_id' => CUSTOMER_SERVICE_ID,
                    'updated_at' => now()
                ]);

                if (!empty($user->telegram_chat_id)) {
                    $telegram_messages[] = [
                        'chat_id' => $user->telegram_chat_id,
                        'message' => "You have a new message from customer_service. Please check your message on the website."
                    ];
                }
            }

            foreach ($telegram_messages as $telegram_message) {
                $this->telegramController->sendTelegramMessage($telegram_message['chat_id'], $telegram_message['message']);
            }
            return response()->json([
                'status' => 'success',
                'message' => 'Message sent to all users successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function sendMessageByAdmin(Request $request)
    {
        try {
            // Validate đầu vào
            $request->validate([
                'message' => 'required|string',
                'type' => 'required',
                'name' => 'required_if:type,to-user'
            ]);

            $user = auth()->user();
            $admin_id = $user['_id'];
            $telegram_messages = [];

            // Kiểm tra quyền admin
            if ($user['role'] != 4) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to send messages'
                ], 403);
            }

            // Lấy danh sách user dựa theo type
            $users = [];
            switch ($request->type) {
                case 'all-user':
                    $users = User::where('role', 1)->get();
                    break;
                case 'all-shop':
                    $users = User::where('role', 2)->get();
                    break;
                case 'online-user':
                    $users = User::whereIn('role', [1, 2])
                        ->where('is_online', true)
                        ->get();
                    break;
                case 'active-shop':
                    $users = User::where('role', 2)
                        ->where('is_active', true)
                        ->get();
                    break;
                case 'to-user':
                    $users = User::where('name', $request->name)
                        ->get();
                    break;
                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid message type'
                    ], 400);
            }

            // nếu type là to-user thì kiểm tra xem người dùng có tồn tại không
            if ($request->type == 'to-user' && count($users) == 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }
            // Gửi tin nhắn cho từng user
            foreach ($users as $recipient) {
                // Bỏ qua nếu là chính admin
                if ($recipient['_id'] == $admin_id) {
                    continue;
                }

                // Kiểm tra conversation
                $conversation = Conversation::where(function ($query) use ($admin_id, $recipient) {
                    $query->where('id_user1', $admin_id)
                        ->where('id_user2', $recipient['_id']);
                })->orWhere(function ($query) use ($admin_id, $recipient) {
                    $query->where('id_user1', $recipient['_id'])
                        ->where('id_user2', $admin_id);
                })->first();

                // Tạo conversation mới nếu chưa có
                if (!$conversation) {
                    $conversation = Conversation::create([
                        'id_user1' => $admin_id,
                        'id_user2' => $recipient['_id'],
                        'last_mess' => $request->message,
                        'last_mess_id' => $admin_id
                    ]);
                }

                // Tạo tin nhắn mới
                Messages::create([
                    'conversation_id' => $conversation['_id'],
                    'sender_id' => $admin_id,
                    'message' => $request->message,
                    'status' => 0
                ]);

                // Cập nhật conversation
                $conversation->update([
                    'last_mess' => $request->message,
                    'last_mess_id' => $admin_id,
                    'updated_at' => now()
                ]);

                // send telegram message
                if (!empty($recipient->telegram_chat_id)) {
                    $telegram_messages[] = [
                        'chat_id' => $recipient->telegram_chat_id,
                        'message' => "You have a new message from admin. Please check your message on the website."
                    ];
                }
            }

            foreach ($telegram_messages as $telegram_message) {
                $this->telegramController->sendTelegramMessage($telegram_message['chat_id'], $telegram_message['message']);
            }

            return response()->json([
                'success' => true,
                'message' => 'Messages sent successfully',
                'total_recipients' => count($users)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send messages: ' . $e->getMessage()
            ], 500);
        }
    }

    public function createConversation(Request $request)
    {
        try {
            // Validate đầu vào
            $request->validate([
                'id_user1' => 'required|exists:users,_id',
                'id_user2' => 'required|exists:users,_id',
            ]);

            // Chuyển đổi id_user1 và id_user2 sang ObjectId nếu cần thiết
            $id_user1 = $request['id_user1'];
            $id_user2 = $request['id_user2'];

            // Kiểm tra xem cuộc trò chuyện đã tồn tại hay chưa
            $existingConversation = Conversation::where(function ($query) use ($id_user1, $id_user2) {
                $query->where('id_user1', $id_user1)
                    ->where('id_user2', $id_user2);
            })->orWhere(function ($query) use ($id_user1, $id_user2) {
                $query->where('id_user1', $id_user2)
                    ->where('id_user2', $id_user1);
            })->first();

            // Nếu đã tồn tại cuộc trò chuyện, trả về cuộc trò chuyện đó
            if ($existingConversation) {
                return response()->json(['conversation' => $existingConversation], 200);
            }

            // Nếu không tồn tại, tạo cuộc trò chuyện mới
            $conversation = Conversation::create([
                'id_user1' => $id_user1,
                'id_user2' => $id_user2,
            ]);

            // Thêm người tham gia vào bảng participants
            Participants::create(['conversation_id' => $conversation['_id'], 'user_id' => $id_user1]);
            Participants::create(['conversation_id' => $conversation['_id'], 'user_id' => $id_user2]);
            return $this->sendResponse($conversation, 'Conversation successful.');
        } catch (\Exception $e) {
            // Trả về phản hồi lỗi
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    public function sendMessage(Request $request)
    {
        try {
            $user = auth()->user();
            $is_lock_chat = $user['is_lock_chat'];
            $is_banned = $user['is_banned'];
            if ($is_banned == true && $is_banned != null) {
                return $this->sendError('Your account has been locked', [], 400);
            }
            if ($is_lock_chat == true && $is_lock_chat != null) {
                return $this->sendError('You are locked chat', [], 400);
            }

            $request->validate([
                'id_user_receiver' => 'required|exists:users,_id', // ID của người nhận
                'message' => 'required|string',
            ]);

            // Lấy ID người nhận từ request
            $id_user_receiver = $request['id_user_receiver'];
            $sender_id = $user['_id'];

            // Kiểm tra nếu cuộc trò chuyện giữa hai người đã tồn tại
            $conversation = Conversation::where(function ($query) use ($sender_id, $id_user_receiver) {
                $query->where('id_user1', $sender_id)
                    ->where('id_user2', $id_user_receiver);
            })->orWhere(function ($query) use ($sender_id, $id_user_receiver) {
                $query->where('id_user1', $id_user_receiver)
                    ->where('id_user2', $sender_id);
            })->first();

            // Nếu cuộc trò chuyện chưa tồn tại, tạo mới cuộc trò chuyện
            if (!$conversation) {
                $conversation = Conversation::create([
                    'id_user1' => $sender_id,
                    'id_user2' => $id_user_receiver,
                ]);

                // Thêm người tham gia vào bảng participants
                Participants::create(['conversation_id' => $conversation['_id'], 'user_id' => $sender_id]);
                Participants::create(['conversation_id' => $conversation['_id'], 'user_id' => $id_user_receiver]);
            }

            // Gửi tin nhắn
            $message = Messages::create([
                'conversation_id' => $conversation['_id'],
                'sender_id' => $sender_id,
                'message' => $request->message,
                'status' => 0,
            ]);
            $message->is_load_sender = true;
            $message->save();

            // Cập nhật last_mess_id trong conversation
            $conversation->last_mess_id = $message['sender_id'];
            $conversation->last_mess = $message['message'];
            $conversation->save();
            return $this->sendResponse($message, 'Message sent');
        } catch (\Exception $e) {
            // Trả về phản hồi lỗi
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    const OFFLINE_AFTER_SECONDS = 300;

    public function getAllConversations(Request $request = null)
    {
        if (!$request) {
            $request = request();
        }

        $page = $request->get('page', 1);
        $search = $request->get('search');
        $type = $request->get('type', 'admin-user');
        $perPage = 10;

        try {
            // Base query for conversations
            $query = Conversation::query()->orderBy('updated_at', 'desc');

            // Get system bot and admin IDs
            $systemBot = User::where('role', 5)->first();
            $adminUsers = User::where('role', 4)->pluck('_id')->toArray();

            $systemBotId = $systemBot?->_id;

            // Apply filters based on types
            switch ($type) {
                case 'admin-user':
                    $query->where(function ($q) use ($adminUsers, $systemBotId) {
                        // Conversation có admin là user1, normal user là user2
                        $q->where(function ($subQ) use ($adminUsers, $systemBotId) {
                            $subQ->whereIn('id_user1', $adminUsers)
                                ->where('id_user2', '!=', $systemBotId)
                                ->whereNotIn('id_user2', $adminUsers);
                        })
                            // Hoặc admin là user2, normal user là user1
                            ->orWhere(function ($subQ) use ($adminUsers, $systemBotId) {
                                $subQ->whereIn('id_user2', $adminUsers)
                                    ->where('id_user1', '!=', $systemBotId)
                                    ->whereNotIn('id_user1', $adminUsers);
                            });
                    });
                    break;

                case 'system':
                    $query->where(function ($q) use ($systemBotId) {
                        $q->where('id_user1', $systemBotId)
                            ->orWhere('id_user2', $systemBotId);
                    });
                    break;

                case 'user-user':
                    $normalUsers = User::whereNotIn('role', [4, 5])->pluck('_id')->toArray();
                    $query->whereIn('id_user1', $normalUsers)
                        ->whereIn('id_user2', $normalUsers);
                    break;
                case 'support-user':
                    // supporter role = 7
                    $supportUsers = User::where('role', 7)->pluck('_id')->toArray();
                    $query->where(function ($q) use ($supportUsers) {
                        $q->whereIn('id_user1', $supportUsers)
                            ->orWhereIn('id_user2', $supportUsers);
                    });
                    break;
            }

            // Apply search if provided
            if (!empty($search)) {
                $userIds = User::where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->pluck('_id')
                    ->toArray();

                if (!empty($userIds)) {
                    $query->where(function ($q) use ($userIds) {
                        $q->whereIn('id_user1', $userIds)
                            ->orWhereIn('id_user2', $userIds);
                    });
                }
            }

            // Get total count for pagination
            $total = $query->count();
            $lastPage = ceil($total / $perPage);

            // Get paginated results
            $conversations = $query->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();

            // Transform conversation data
            $conversationData = $conversations->map(function ($conversation) use ($adminUsers) {
                $user1 = User::find($conversation->id_user1);
                $user2 = User::find($conversation->id_user2);

                // Kiểm tra tin nhắn chưa đọc trong conversation
                // Nếu có ít nhất 1 tin chưa đọc (is_admin_read = null hoặc false) thì conversation đó chưa đọc
                $hasUnreadMessages = Messages::where('conversation_id', $conversation->_id)
                    ->where(function ($q) {
                        $q->whereNull('is_admin_read')
                            ->orWhere('is_admin_read', false);
                    })
                    ->exists();

                $this->updateOnlineStatus($user1);
                $this->updateOnlineStatus($user2);

                // Format user data
                $formatUser = function ($user) {
                    return [
                        'id' => $user->_id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                        'is_online' => $user->is_online ?? false,
                        'last_activity' => $user->last_activity,
                        'is_system_bot' => $user->role === 5
                    ];
                };

                // Sắp xếp user để đảm bảo admin luôn ở vị trí user1 trong tab admin-user
                if (in_array($user2->_id, $adminUsers) && $user1->role !== 5) {
                    // Swap users if admin is user2
                    $temp = $user1;
                    $user1 = $user2;
                    $user2 = $temp;
                }

                return [
                    'conversation_id' => $conversation->_id,
                    'last_message' => $conversation->last_mess,
                    'last_message_sender_id' => $conversation->last_mess_id,
                    'updated_at' => $conversation->updated_at,
                    'is_hidden' => $conversation->is_hidden ?? false,
                    'user1' => $formatUser($user1),
                    'user2' => $formatUser($user2),
                    'has_unread_messages' => $hasUnreadMessages
                ];
            })->toArray();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'conversations' => $conversationData,
                    'current_page' => (int)$page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => $lastPage,
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in messages query: ' . $e->getMessage(), [
                'exception' => $e,
                'type' => $type,
                'search' => $search ?? null,
                'page' => $page ?? 1
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Có lỗi xảy ra khi tải danh sách hội thoại'
            ], 500);
        }
    }

    public function getConversation()
    {
        $user = auth()->user();
        $userId = $user['_id']; // ObjectId cho MongoDB

        $conversations = Conversation::where(function ($query) use ($userId) {
            $query->where('id_user1', $userId)
                ->orWhere('id_user2', $userId);
        })
            ->where(function ($query) {
                $query->whereNull('is_hidden')
                    ->orWhere('is_hidden', false);
            })
            ->orderBy('updated_at', 'desc')
            ->get();

        $result = [];
        foreach ($conversations as $conversation) {
            // Xác định ID của người dùng khác
            $otherUserId = $conversation->id_user1 == $userId ? $conversation->id_user2 : $conversation->id_user1;
            $otherUser = User::find($otherUserId);

            // Nếu không tìm thấy người dùng, bỏ qua cuộc trò chuyện này
            if (!$otherUser) {
                \Log::error("Không tìm thấy user với ID: " . $otherUserId);
                continue;
            }

            // Xử lý last_activity
            if (empty($otherUser->last_activity)) {
                $lastActivity = Carbon::now();
            } else {
                $lastActivity = Carbon::parse($otherUser->last_activity);
            }

            // Xử lý avatar
            $avatar = $otherUser->back_id_card;

            // Kiểm tra trạng thái online
            $timeDiff = $lastActivity->diffInSeconds(Carbon::now());
            if ($timeDiff > self::OFFLINE_AFTER_SECONDS) {
                $otherUser->is_online = false;
                $otherUser->save();
            }

            // Xác định loại tin nhắn cuối
            $type = ($conversation->last_mess_id == $userId) ? 'send' : 'receive';

            $useronline = [
                'avatar' => $avatar,
                'type' => $type,
                'name_user' => $otherUser->name,
                'user_id' => $otherUser->_id,
                'is_online' => $otherUser->is_online,
                'last_activity' => $lastActivity,
                'offline_duration' => $otherUser->is_online ? 0 : $this->formatOfflineDuration($timeDiff)
            ];

            // Thêm vào kết quả
            $result[] = [
                'conversation' => $conversation,
                'useronline' => $useronline
            ];
        }

        return $this->sendResponse($result, 'Conversations retrieved successfully.');
    }


    public function getConversationByNameReceiver($name_receiver)
    {
        // lấy thông tin cuộc hội thoại giữa người dùng hiện tại và người dùng khác dựa trên tên người nhận( chỉ trả về 1 cuộc trò chuyện)
        $user = auth()->user();
        $userId = $user['_id'];
        $receiver = User::where('name', $name_receiver)->first();
        $receiverId = $receiver['_id'];

        $conversation = Conversation::where(function ($query) use ($userId, $receiverId) {
            $query->where('id_user1', $userId)
                ->where('id_user2', $receiverId);
        })->orWhere(function ($query) use ($userId, $receiverId) {
            $query->where('id_user1', $receiverId)
                ->where('id_user2', $userId);
        })->first();
        // lấy thông tin người nhận
        $receiver = User::find($receiverId);
        $lastActivity = Carbon::parse($receiver->last_activity);
        $timeDiff = $lastActivity->diffInSeconds(Carbon::now());

        if ($timeDiff > self::OFFLINE_AFTER_SECONDS) {
            $receiver->is_online = false;
            $receiver->save();
        }

        $useronline = [
            'avatar' => $receiver->back_id_card,
            'name_user' => $receiver->name,
            'user_id' => $receiver->_id,
            'is_online' => $receiver->is_online,
            'last_activity' => $receiver->last_activity,
            'offline_duration' => $receiver->is_online ? 0 : $this->formatOfflineDuration($timeDiff)
        ];

        $result = [
            'conversation' => $conversation,
            'receiver' => $useronline
        ];

        return response(json_encode($result), 200)
            ->header('Content-Type', 'text/plain');

    }


    public function SeenMessage(Request $request)
    {
        try {
            $user = auth()->user();
            $id_user = $user['_id'];
            $request->validate([
                'id_conversation' => 'required|exists:conversations,_id', // ID của conversation
            ]);
            $id_conversation = $request['id_conversation'];

            // Lấy tất cả tin nhắn chưa đọc
            $messages = Messages::where('conversation_id', $id_conversation)
                ->where('status', 0) // Tin nhắn chưa đọc
                ->where('sender_id', '!=', $id_user)
                ->get();

            foreach ($messages as $message) {
                // Cập nhật trạng thái tin nhắn thành đã đọc
                $message->status = 1;
                $message->updated_at = now();  // Cập nhật thời gian xem
                $message->save();
            }

            return $this->sendResponse('Messages marked as read successfully.', '');
        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    public function adminGetMessage($id)
    {
        // Tìm cuộc trò chuyện với ID tương ứng
        $conversation = Conversation::find($id);
        if (!$conversation) {
            return response()->json(['success' => false, 'message' => 'Conversation not found'], 404);
        }

        // Lấy thông tin của cả hai người dùng
        $user1 = User::find($conversation->id_user1);
        $user2 = User::find($conversation->id_user2);

        // Xử lý thông tin online của user 1
        $lastActivity1 = Carbon::parse($user1->last_activity);
        $timeDiff1 = $lastActivity1->diffInSeconds(Carbon::now());

        if ($timeDiff1 > self::OFFLINE_AFTER_SECONDS) {
            $user1->is_online = false;
            $user1->save();
        }

        $useronline1 = [
            'avatar' => $user1->back_id_card,
            'name_user' => $user1->name,
            'user_id' => $user1->_id,
            'is_online' => $user1->is_online,
            'last_activity' => $user1->last_activity,
            'offline_duration' => $user1->is_online ? 0 : $this->formatOfflineDuration($timeDiff1)
        ];

        // Xử lý thông tin online của user 2
        $lastActivity2 = Carbon::parse($user2->last_activity);
        $timeDiff2 = $lastActivity2->diffInSeconds(Carbon::now());

        if ($timeDiff2 > self::OFFLINE_AFTER_SECONDS) {
            $user2->is_online = false;
            $user2->save();
        }

        $useronline2 = [
            'avatar' => $user2->back_id_card,
            'name_user' => $user2->name,
            'user_id' => $user2->_id,
            'is_online' => $user2->is_online,
            'last_activity' => $user2->last_activity,
            'offline_duration' => $user2->is_online ? 0 : $this->formatOfflineDuration($timeDiff2)
        ];

        // Lấy tất cả tin nhắn trong cuộc trò chuyện
        $messages = Messages::where('conversation_id', $id)->get();

        // Chuẩn bị mảng dữ liệu với thông tin status dựa trên người gửi/nhận
        $messageData = [];
        foreach ($messages as $message) {
            // Xác định status dựa trên id_user1
            $status = ($message->sender_id == $conversation->id_user1) ? 'send' : 'receive';

            $sender = User::find($message->sender_id);
            $lastActivity = Carbon::parse($sender->last_activity);
            $timeDiff = $lastActivity->diffInSeconds(Carbon::now());

            if ($timeDiff > self::OFFLINE_AFTER_SECONDS) {
                $sender->is_online = false;
                $sender->save();
            }

            $messageData[] = [
                'message' => $message->message,
                'sender_id' => $message->sender_id,
                'name_user' => $sender->name,
                'status' => $status,
                'avatar' => $sender->back_id_card,
                'is_online' => $sender->is_online,
                'created_at' => $message->created_at
            ];
        }

        // Trả về dữ liệu tin nhắn
        $responseData = [
            'success' => true,
            'data' => $messageData,
            'user1' => $useronline1,
            'user2' => $useronline2,
            'message' => 'Messages retrieved successfully.'
        ];

        return response(json_encode($responseData), 200)
            ->header('Content-Type', 'text/plain');
    }

    function getMessageNoSeen($id)
    {
        $mess = Messages::where('conversation_id', $id)
            ->where('status', 0)
            ->get();
        return $this->sendResponse($mess, 'Message   retrieved successfully.');
    }

    function getMessageByNameUser($name_user)
    {
        $user_send = User::where('name', $name_user)->first();
        $id_user_sender = $user_send['_id'];
        $user = auth()->user();
        $id_user_receiver = $user['_id'];

        // Tìm cuộc trò chuyện giữa hai người dùng và kiểm tra nếu cuộc trò chuyện không bị ẩn
        $conversation = Conversation::where(function ($query) use ($id_user_sender, $id_user_receiver) {
            $query->where('id_user1', $id_user_sender)
                ->where('id_user2', $id_user_receiver);
        })
            ->orWhere(function ($query) use ($id_user_sender, $id_user_receiver) {
                $query->where('id_user1', $id_user_receiver)
                    ->where('id_user2', $id_user_sender);
            })
            ->where(function ($query) {
                $query->whereNull('is_hidden')
                    ->orWhere('is_hidden', false);
            })
            ->first();

        // Kiểm tra nếu cuộc trò chuyện không tồn tại hoặc bị ẩn
        if (!$conversation) {
            return response()->json(['success' => false, 'message' => 'Conversation is hidden or does not exist'], 404);
        }

        $mess = Messages::where('conversation_id', $conversation['_id'])->get();
        foreach ($mess as $message) {
            $message->is_load_receive = true;
            $message->save();
        }
        return $this->sendResponse($mess, 'Message retrieved successfully.');
    }

    public function qtyMessage()
    {
        $user = auth()->user();
        $userId = $user['_id'];

        // Lấy tất cả các cuộc hội thoại của user
        $conversations = Conversation::where(function ($query) use ($userId) {
            $query->where('id_user1', $userId)
                ->orWhere('id_user2', $userId);
        })->get();

        // Đếm tổng số tin nhắn chưa đọc mà user nhận được
        $unreadMessagesCount = 0;
        foreach ($conversations as $conversation) {
            // Đếm số tin nhắn chưa đọc (status = 0) mà user nhận được
            $mess = Messages::where('conversation_id', $conversation['_id'])
                ->where('status', 0)
                ->where('sender_id', '!=', $userId) // Chỉ đếm tin nhắn mà user không phải người gửi
                ->count();

            // Cộng dồn số tin nhắn chưa đọc
            $unreadMessagesCount += $mess;
        }

        return $unreadMessagesCount; // Trả về số lượng tin nhắn chưa đọc
    }

    private function formatOfflineDuration($seconds)
    {
        if ($seconds < 60) {
            return $seconds . ' seconds';
        } elseif ($seconds < 3600) {
            return floor($seconds / 60) . ' minutes';
        } elseif ($seconds < 86400) {
            return floor($seconds / 3600) . ' hours';
        } else {
            return floor($seconds / 86400) . ' days';
        }
    }

    private function updateOnlineStatus($user)
    {
        if (!$user) return;

        $lastActivity = Carbon::parse($user->last_activity);
        $timeDiff = $lastActivity->diffInSeconds(Carbon::now());

        if ($timeDiff > self::OFFLINE_AFTER_SECONDS) {
            $user->is_online = false;
            $user->save();
        }
    }


    // admin wp

//    1. xem tin nhắn
    public function markConversationAsRead(Request $request)
    {
        try {
            $request->validate([
                'conversation_id' => 'required',
            ]);

            $id_conversation = $request['conversation_id'];

            // Update tất cả tin nhắn chưa đọc bởi admin
            $messages = Messages::where('conversation_id', $id_conversation)
                ->where('is_admin_read', null)
                ->get();

            foreach ($messages as $message) {
                $message->is_admin_read = true;
                $message->save();
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Messages marked as read successfully.',
                'data' => [
                    'conversation_id' => $id_conversation,
                    'messages_updated' => count($messages)
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error marking messages as read: ' . $e->getMessage(), [
                'conversation_id' => $id_conversation ?? null,
                'exception' => $e
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while marking messages as read.'
            ], 500);
        }
    }


    // load tin nhắn:

    public function getMessage($id)
    {
        $user = auth()->user();
        $userId = $user['_id'];  // ID người dùng hiện tại

        // Tìm cuộc trò chuyện với ID tương ứng
        $conversation = Conversation::find($id);
        $is_hidden = $conversation->is_hidden;
        if ($is_hidden == true) {
            return response()->json(['success' => false, 'message' => 'Conversation is hidden'], 404);
        }
        if (!$conversation) {
            return response()->json(['success' => false, 'message' => 'Conversation not found'], 404);
        }
        $user1 = User::find($conversation->id_user1);
        $user2 = User::find($conversation->id_user2);

        //Lấy thông tin user 1
        $lastActivity1 = Carbon::parse($user1->last_activity);
        $timeDiff1 = $lastActivity1->diffInSeconds(Carbon::now());

        if ($timeDiff1 > self::OFFLINE_AFTER_SECONDS) {
            $user1->is_online = false;
            $user1->save();
        }

        $useronline1 = [
            'avatar' => $user1->back_id_card,
            'name_user' => $user1->name,
            'user_id' => $user1->_id,
            'is_online' => $user1->is_online,
            'last_activity' => $user1->last_activity,
            'offline_duration' => $user1->is_online ? 0 : $this->formatOfflineDuration($timeDiff1)
        ];

        //Lấy thông tin user 2
        $lastActivity2 = Carbon::parse($user1->last_activity);
        $timeDiff2 = $lastActivity2->diffInSeconds(Carbon::now());

        if ($timeDiff2 > self::OFFLINE_AFTER_SECONDS) {
            $user2->is_online = false;
            $user2->save();
        }

        $useronline2 = [
            'avatar' => $user2->back_id_card,
            'name_user' => $user2->name,
            'user_id' => $user2->_id,
            'is_online' => $user2->is_online,
            'last_activity' => $user2->last_activity,
            'offline_duration' => $user2->is_online ? 0 : $this->formatOfflineDuration($timeDiff2)
        ];

        // Lấy tất cả tin nhắn trong cuộc trò chuyện
        $messages = Messages::where('conversation_id', $id)->get();

        // Chuẩn bị mảng dữ liệu với thông tin status (gửi/nhận)
        $messageData = [];
        foreach ($messages as $message) {
            // Xác định loại tin nhắn: gửi hay nhận

            if ($message->sender_id == $userId) {
                $status = 'send';
            } elseif ($message->sender_id != $userId) {
                $status = 'receive';
            }
            $lastActivity1 = Carbon::parse($user1->last_activity);
            $timeDiff1 = $lastActivity1->diffInSeconds(Carbon::now());
            $user3 = User::find($message->sender_id);
            if ($timeDiff1 > self::OFFLINE_AFTER_SECONDS) {
                $user3->is_online = false;
                $user3->save();
            }
            $messageData[] = [
                'message' => $message->message,
                'sender_id' => $message->sender_id,
                'status' => $status,
                'avatar' => $user3->back_id_card,
                'is_online' => $user3->is_online,
                'created_at' => $message->created_at
            ];
        }

        // Trả về dữ liệu tin nhắn cùng status
        $responseData = [
            'success' => true,
            'data' => $messageData,
            'user1' => $useronline1,
            'user2' => $useronline2,
            'message' => 'Messages retrieved successfully.'
        ];

//        return response()->json($responseData, 200);
        return response(json_encode($responseData), 200)
            ->header('Content-Type', 'text/plain');
    }

    // test
    public function getNewMessages($id)
    {
        $user = auth()->user();
        $userId = $user['_id'];

        // Find conversation
        $conversation = Conversation::find($id);
        if (!$conversation) {
            return response()->json(['success' => false, 'message' => 'Conversation not found'], 404);
        }

        if ($conversation->is_hidden) {
            return response()->json(['success' => false, 'message' => 'Conversation is hidden'], 404);
        }

        // Get users info
        $user1 = User::find($conversation->id_user1);
        $user2 = User::find($conversation->id_user2);

        // Update user1 online status
        $lastActivity1 = Carbon::parse($user1->last_activity);
        $timeDiff1 = $lastActivity1->diffInSeconds(Carbon::now());
        if ($timeDiff1 > self::OFFLINE_AFTER_SECONDS) {
            $user1->is_online = false;
            $user1->save();
        }

        $lastActivity2 = Carbon::parse($user2->last_activity);
        $timeDiff2 = $lastActivity2->diffInSeconds(Carbon::now());
        if ($timeDiff2 > self::OFFLINE_AFTER_SECONDS) {
            $user2->is_online = false;
            $user2->save();
        }

        // Get messages that haven't been fully loaded
        $messages = Messages::where('conversation_id', $id)
            ->where(function ($query) {
                $query->where('is_load_sender', '!=', true)
                    ->orWhere('is_load_receive', '!=', true);
            })
            ->get();

        $messageData = [];
        foreach ($messages as $message) {
            if ($message->sender_id == $userId) {
                // Current user is sender
                if (!$message->is_load_sender) {
                    $status = 'send';
                    $message->is_load_sender = true;
                    $message->save();

                    $sender = User::find($message->sender_id);
                    $lastActivity = Carbon::parse($sender->last_activity);
                    $timeDiff = $lastActivity->diffInSeconds(Carbon::now());

                    if ($timeDiff > self::OFFLINE_AFTER_SECONDS) {
                        $sender->is_online = false;
                        $sender->save();
                    }

                    $messageData[] = [
                        'message' => $message->message,
                        'sender_id' => $message->sender_id,
                        'status' => $status,
                        'avatar' => $sender->back_id_card,
                        'is_online' => $sender->is_online,
                        'created_at' => $message->created_at
                    ];
                }
            } else {
                // Current user is receiver
                if (!$message->is_load_receive) {
                    $status = 'receive';
                    $message->is_load_receive = true;
                    $message->save();

                    $sender = User::find($message->sender_id);
                    $lastActivity = Carbon::parse($sender->last_activity);
                    $timeDiff = $lastActivity->diffInSeconds(Carbon::now());

                    if ($timeDiff > self::OFFLINE_AFTER_SECONDS) {
                        $sender->is_online = false;
                        $sender->save();
                    }

                    $messageData[] = [
                        'message' => $message->message,
                        'sender_id' => $message->sender_id,
                        'status' => $status,
                        'avatar' => $sender->back_id_card,
                        'is_online' => $sender->is_online,
                        'created_at' => $message->created_at
                    ];
                }
            }
        }

        $responseData = [
            'success' => true,
            'data' => $messageData,
        ];

        return response(json_encode($responseData), 200)
            ->header('Content-Type', 'text/plain');
    }

    public function getLastMessage()
    {
        // lấy tin nhắn cuối cùng trong cuộc trò chuyện
        $user = auth()->user();
        $userId = $user['_id'];
        // find all conversation có chứa $UserId
        $conversation = Conversation::where(function ($query) use ($userId) {
            $query->where('id_user1', $userId)
                ->orWhere('id_user2', $userId);
        })->get();
        $conversation = $conversation->toArray();
        $conversation = array_column($conversation, '_id');
        $conversation = array_values($conversation);
        $conversation = array_map(function ($value) {
            return (string)$value;
        }, $conversation);
        $lastMessage = [];
        foreach ($conversation as $key => $value) {
            $lastMessage[] = Messages::where('conversation_id', $value)->orderBy('created_at', 'desc')->first();
        }
        $responseData = array();
        foreach ($lastMessage as $key => $value) {
            if ($value->sender_id == $userId) {
                $type = 'send';
            } elseif ($value->sender_id != $userId) {
                $type = 'receive';
            }
            $responseData[] = [
                'message' => $value->message,
                'type' => $type,
                'status' => $value->status,
                'id_conversation' => $value->conversation_id,
            ];
        }

        $responseData = [
            'success' => true,
            'data' => $responseData,
            'message' => 'Messages retrieved successfully.'
        ];
        return response(json_encode($responseData), 200)
            ->header('Content-Type', 'text/plain');
    }

    public function hideConversation(Request $request)
    {
        try {
            $id_conversation = $request['id_conversation'];

            // Ẩn cuộc trò chuyện is_hidden = true
            $conversation = Conversation::find($id_conversation);

            // nếu cuộc trò chuyện  đã bị ẩn thì đổi trạng thái thành false
            if ($conversation->is_hidden == true) {
                $conversation->is_hidden = false;
                $conversation->save();
                return response()->json([
                    'status' => 'success',
                    'message' => 'Conversation unhidden successfully.',
                    'data' => [
                        'conversation_id' => $id_conversation,
                    ]
                ]);
            }
            $conversation->is_hidden = true;
            $conversation->save();

            // ẩn tin nhắn trong cuộc trò chuyện
            $messages = Messages::where('conversation_id', $id_conversation)->get();
            foreach ($messages as $message) {
                $message->is_hidden = true;
                $message->save();
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Conversation hidden successfully.',
                'data' => [
                    'conversation_id' => $id_conversation,
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Error hiding conversation: ' . $e->getMessage(), [
                'conversation_id' => $id_conversation ?? null,
                'exception' => $e
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while hiding conversation.'
            ], 500);
        }
    }

    public function getNewConversation()
    {
        $user = auth()->user();
        $userId = $user['_id'];

        $conversation = Conversation::where(function ($query) use ($userId) {
            $query->where('id_user1', $userId)
                ->orWhere('id_user2', $userId);
        })
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$conversation) {
            return response()->json(['success' => false, 'message' => 'No conversation found'], 404);
        }

        $firstMessage = Messages::where('conversation_id', $conversation['_id'])
            ->orderBy('created_at', 'asc')
            ->first();

        if (!$firstMessage) {
            return response()->json(['success' => false, 'message' => 'No messages found in the conversation'], 404);
        }

        if ($firstMessage->sender_id == $userId) {
            return response()->json(['success' => false, 'message' => 'The first message was not sent by the user'], 403);
        }

        return response()->json([
            'success' => true,
            'conversation' => $conversation,
            'first_message' => $firstMessage
        ], 200);
    }


    // thống kê tin nhắn
    public function getMessageAnalytics(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 20);
        $offset = ($page - 1) * $perPage;

        $supportStaffQuery = User::where('role', 6)
            ->orWhere('role', 7);

        $totalStaff = $supportStaffQuery->count();

        $supportStaff = $supportStaffQuery->skip($offset)
            ->take($perPage)
            ->get();

        $analytics = [];
        $counter = $offset + 1;

        foreach ($supportStaff as $staff) {
            $conversations = Conversation::where('id_user1', $staff->_id)
                ->orWhere('id_user2', $staff->_id)
                ->get();

            $conversationIds = $conversations->pluck('_id');

            // Lấy tất cả tin nhắn trong các cuộc hội thoại, sắp xếp theo thời gian
            $messagesQuery = Messages::whereIn('conversation_id', $conversationIds)
                ->orderBy('created_at', 'asc');

            if ($request->filled('start_date')) {
                $startDate = Carbon::parse($request->start_date)->startOfDay();
                $messagesQuery->where('created_at', '>=', $startDate);
            }

            if ($request->filled('end_date')) {
                $endDate = Carbon::parse($request->end_date)->endOfDay();
                $messagesQuery->where('created_at', '<=', $endDate);
            }

            $messages = $messagesQuery->get();

            if ($messages->count() > 0) {
                $totalResponseTime = 0;
                $responseCount = 0;

                foreach ($conversations as $conversation) {
                    $conversationMessages = $messages->where('conversation_id', $conversation->_id)->values();

                    for ($i = 0; $i < $conversationMessages->count() - 1; $i++) {
                        $currentMessage = $conversationMessages[$i];
                        $nextMessage = $conversationMessages[$i + 1];

                        // Nếu tin nhắn hiện tại không phải từ staff và tin nhắn tiếp theo là từ staff
                        if ($currentMessage->sender_id != $staff->_id && $nextMessage->sender_id == $staff->_id) {
                            $responseTime = Carbon::parse($currentMessage->created_at)
                                ->diffInSeconds(Carbon::parse($nextMessage->created_at));
                            $totalResponseTime += $responseTime;
                            $responseCount++;
                        }
                    }
                }

                $avgResponseTime = $responseCount > 0 ? $totalResponseTime / $responseCount : 0;

                $analytics[] = [
                    'stt' => $counter++,
                    'staff_id' => (string)$staff->_id,
                    'responder_name' => $staff->name,
                    'role' => $staff->role == 7 ? 'Support' : 'Support Học Việc',
                    'total_messages' => $messages->where('sender_id', $staff->_id)->count(),
                    'total_responses' => $responseCount,
                    'total_response_time' => $this->formatDuration($totalResponseTime),
                    'avg_response_time' => $this->formatDuration($avgResponseTime)
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $analytics,
            'pagination' => [
                'total' => $totalStaff,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => ceil($totalStaff / $perPage)
            ]
        ]);
    }

    private function formatDuration($seconds)
    {
        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;

        if ($minutes < 60) {
            return sprintf('%d phút %d giây', $minutes, $remainingSeconds);
        } else {
            $hours = floor($minutes / 60);
            $remainingMinutes = $minutes % 60;
            return sprintf('%d giờ %d phút', $hours, $remainingMinutes);
        }
    }

    public function getStaffMessageDetails(Request $request)
    {
        $request->validate([
            'staff_id' => 'required',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        // Lấy tham số phân trang
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 20);

        // Tìm conversations của staff
        $conversationIds = Conversation::where('id_user1', $request->staff_id)
            ->orWhere('id_user2', $request->staff_id)
            ->get()
            ->pluck('_id');

        // Query messages
        $messagesQuery = Messages::whereIn('conversation_id', $conversationIds)
            ->where('sender_id', $request->staff_id);

        // Áp dụng filter date nếu có
        if ($request->filled('start_date')) {
            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $messagesQuery->where('created_at', '>=', $startDate);
        }

        if ($request->filled('end_date')) {
            $endDate = Carbon::parse($request->end_date)->endOfDay();
            $messagesQuery->where('created_at', '<=', $endDate);
        }

        // Thực hiện phân trang
        $messages = $messagesQuery->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        $messageDetails = $messages->map(function ($message) {
            return [
                'conversation_id' => (string)$message->conversation_id,
                'message' => $message->message,
                'created_at' => Carbon::parse($message->created_at)->format('Y-m-d H:i:s'),
                'status' => $message->status
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $messageDetails,
            'pagination' => [
                'total' => $messages->total(),
                'per_page' => $messages->perPage(),
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage()
            ]
        ]);
    }

}
