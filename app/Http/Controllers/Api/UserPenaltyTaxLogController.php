<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\ApiController as ApiController;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Conversation;
use App\Models\Messages;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\UserPenaltyTaxLog;

class UserPenaltyTaxLogController extends ApiController
{
    public function getUserPenaltyTaxLogByUserId($id)
    {
        try {
            $userPenaltyTaxLog = UserPenaltyTaxLog::where('user_id', $id)->orderBy('created_at', 'desc')->get();
            return $this->sendResponse($userPenaltyTaxLog, 'User Penalty Tax Log register successfully.');

        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    public function getUserPenaltyTaxLogByUserIdCurrent($id)
    {
        try {
            $userPenaltyTaxLog = UserPenaltyTaxLog::where('user_id', $id)->orderBy('created_at', 'desc')->first();
            $level = $userPenaltyTaxLog->level;
            return $this->sendResponse($level, 'User Penalty Tax Log register successfully.');

        } catch (\Exception $e) {
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

    public function addUserPenaltyTaxLog(Request $request)
    {
        try {
            $admin = auth()->user();
            $data = $request->all();
            $user_id = $data['user_id'];
            $user = User::where('_id', $user_id)->first();

            if (!$user) {
                return $this->sendError('User not found', [], 400);
            }

            $level = $data['level'];
            $reason = $data['reason'];
            $old_penalty_tax = $user->penalty_tax;
            $old_is_banned = $user->is_banned;

            // Process penalty level
            if ($level >= 3 && $level <= 5) {
                $penalty = [
                    3 => 5,
                    4 => 10,
                    5 => 15
                ];
                $user->penalty_tax = $penalty[$level];
                $user->is_banned = false;
            } elseif ($level == 6) {
                $user->is_banned = true;
            } else { // level 1,2
                $user->penalty_tax = 0;
                $user->is_banned = false;
            }

            // Track changes for admin log (in Vietnamese)
            $changes = [];
            if ($old_penalty_tax != $user->penalty_tax) {
                if ($level >= 3 && $level <= 5) {
                    $changes[] = "Tăng phí phạt từ {$old_penalty_tax}% lên {$user->penalty_tax}%";
                } else {
                    $changes[] = "Đặt lại phí phạt về 0%";
                }
            }
            if ($old_is_banned != $user->is_banned) {
                $changes[] = $user->is_banned ? "Khóa tài khoản" : "Mở tài khoản";
            }

            // Track changes for user notification (in English)
            $notification_messages = [];
            if ($old_penalty_tax != $user->penalty_tax) {
                if ($level >= 3 && $level <= 5) {
                    $notification_messages[] = "Your account's penalty fee has been increased from {$old_penalty_tax}% to {$user->penalty_tax}%";
                } else {
                    $notification_messages[] = "Your account's penalty fee has been reset to 0%";
                }
            }
            if ($old_is_banned != $user->is_banned) {
                if ($user->is_banned) {
                    $notification_messages[] = "Your account has been banned";
                } else {
                    $notification_messages[] = "Your account has been unbanned";
                }
            }

            // Add reason to notification (in English)
            $notification_messages[] = "Reason: {$reason}";

            // Create activity log for admin (in Vietnamese)
            if ($admin['role'] == 6 || $admin['role'] == 7) {
                ActivityLog::create([
                    'supporter_id' => $admin['_id'],
                    'action' => 'penalty_user',
                    'description' => "Xử phạt user {$user->name} - Mức độ {$level}: " . implode(', ', $changes) . ". Lý do: {$reason}",
                    'target_id' => $user->_id,
                    'is_success' => true,
                ]);
            }

            // Send system notification to user (in English)
            try {
                $user_sys = User::where('role', 5)->first();
                $id_system_bot = $user_sys['_id'];
                define('SYSTEM_BOT_ID', $id_system_bot);

                // Check existing conversation
                $conversation = Conversation::where(function ($query) use ($user) {
                    $query->where('id_user1', SYSTEM_BOT_ID)
                        ->where('id_user2', $user->id);
                })->first();

                // Create new conversation if not exists
                if (!$conversation) {
                    $conversation = Conversation::create([
                        'id_user1' => SYSTEM_BOT_ID,
                        'id_user2' => $user->id,
                        'last_mess' => "System Penalty Notice\n" . implode("\n", $notification_messages),
                        'last_mess_id' => SYSTEM_BOT_ID
                    ]);
                }

                // Create notification message
                $message = "System Penalty Notice\n" . implode("\n", $notification_messages);

                // Create new message
                Messages::create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => SYSTEM_BOT_ID,
                    'message' => $message,
                    'status' => 0
                ]);

                // Update conversation
                $conversation->update([
                    'last_mess' => $message,
                    'last_mess_id' => SYSTEM_BOT_ID,
                    'updated_at' => now()
                ]);

            } catch (\Exception $e) {
                // Log error but continue with the process
                \Log::error('Error sending penalty notification: ' . $e->getMessage());
            }

            $user->update();

            // Create detailed penalty log
            $userPenaltyTaxLog = UserPenaltyTaxLog::create([
                'user_id' => $user_id,
                'level' => $level,
                'reason' => $reason,
            ]);

            return $this->sendResponse($userPenaltyTaxLog, 'User Penalty Tax Log register successfully.');

        } catch (\Exception $e) {
            if ($admin['role'] == 6 || $admin['role'] == 7) {
                ActivityLog::create([
                    'supporter_id' => $admin['_id'],
                    'action' => 'penalty_user_error',
                    'description' => "Lỗi xử phạt user " . ($user->name ?? 'không xác định'),
                    'target_id' => $user_id ?? null,
                    'is_success' => false,
                ]);
            }
            return $this->sendError('An error has occurred. Please try again later', [], 400);
        }
    }

}
