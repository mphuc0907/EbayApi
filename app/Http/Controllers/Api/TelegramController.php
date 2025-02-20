<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;

class TelegramController extends Controller
{
    public function sendTelegramMessage($chat_id, $message)
    {
        $token = env('TELEGRAM_BOT_TOKEN');
        $url = "https://api.telegram.org/bot{$token}/sendMessage";

        try {
            $response = Http::post($url, [
                'chat_id' => $chat_id,
                'text' => $message
            ]);

            $responseData = $response->json();

            // Kiểm tra nếu Telegram báo lỗi (ví dụ chat_id không tồn tại)
            if (!$response->successful() || !isset($responseData['ok']) || !$responseData['ok']) {
                \Log::error('Error sending Telegram message', [
                    'response' => $responseData
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Error sending Telegram message', [
                'exception' => $e
            ]);
        }
    }

}
