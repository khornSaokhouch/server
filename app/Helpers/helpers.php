<?php

use Illuminate\Support\Facades\Http;
use Twilio\Rest\Client;

if (!function_exists('sendTelegramMessage')) {
    function sendTelegramMessage($message, $chatId = null)
    {
        $botToken = env('TELEGRAM_BOT_TOKEN');
        $chatId = $chatId ?? env('TELEGRAM_CHAT_ID');

        Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $message,
        ]);
    }
}
// function sendTelegramMessage($message) {
//     $token = env('TELEGRAM_BOT_TOKEN');
//     $chat_id = env('TELEGRAM_CHAT_ID');
//     $url = "https://api.telegram.org/bot{$token}/sendMessage";

//     $data = [
//         'chat_id' => $chat_id,
//         'text' => $message,
//         'parse_mode' => 'HTML'
//     ];

//     $ch = curl_init();
//     curl_setopt($ch, CURLOPT_URL, $url);
//     curl_setopt($ch, CURLOPT_POST, true);
//     curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
//     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//     curl_exec($ch);
//     curl_close($ch);
// }

// if (!function_exists('sendSms')) {
//     function sendSms($to, $message)
//     {
//         $sid = env('TWILIO_SID');
//         $token = env('TWILIO_AUTH_TOKEN');
//         $from = env('TWILIO_PHONE_NUMBER');

//         // Normalize Cambodia or other numbers
//         $to = preg_replace('/[^0-9]/', '', $to); // remove non-numeric
//         if (!str_starts_with($to, '855')) {
//             // ensure starts with +855 for Cambodia
//             $to = '855' . ltrim($to, '0');
//         }
//         $to = '+' . $to;

//         try {
//             $client = new Client($sid, $token);
//             $client->messages->create($to, [
//                 'from' => $from,
//                 'body' => $message
//             ]);
//         } catch (\Exception $e) {
//             \Log::error('Twilio SMS Error: ' . $e->getMessage());
//         }
//     }
// }

// if (!function_exists('sendTelegramMessage')) {
//     function sendTelegramMessage($chatId, $message)
//     {
//         $botToken = env('TELEGRAM_BOT_TOKEN');
//         $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

//         $response = file_get_contents($url . "?chat_id={$chatId}&text=" . urlencode($message));

//         return $response;
//     }
// }
