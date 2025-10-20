<?php

namespace App\Http\Controllers\Socialite;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Http;

class TelegramController extends Controller
{
    public function webhook(Request $request)
    {
        $data = $request->all();

        if (isset($data['message']['text'])) {
            $text = preg_replace('/@.+$/', '', trim(strtolower($data['message']['text'])));
            $chat_id = $data['message']['chat']['id'];

            switch ($text) {
                case '/start':
                    $this->sendAllUsers($chat_id);
                    break;

                case '/help':
                    $this->sendMessage($chat_id, "Available commands:\n/start - List all users\n/help - Show this message");
                    break;

                default:
                    $this->sendMessage($chat_id, "Unknown command. Type /help for available commands.");
                    break;
            }
        }

        return response()->json(['ok' => true]);
    }

    private function sendAllUsers($chat_id)
    {
        $users = User::all();

        if ($users->isEmpty()) {
            $message = "No users found.";
        } else {
            $message = "🟢 List of all users:\n";
            foreach ($users as $index => $u) {
                $name = $u->name ?? 'N/A';
                $email = $u->email ?? 'N/A';
                $phone = $u->phone ?? 'N/A';
                $role = $u->role ?? 'N/A';
                $timestamp = now()->toDateTimeString();

                $message .= ($index + 1) . ". Name: {$name}, Email: {$email}, Phone: {$phone}, Role: {$role}, Date: {$timestamp}\n";
            }
        }

        $this->sendMessage($chat_id, $message);
    }

    private function sendMessage($chat_id, $message)
    {
        $token = env('TELEGRAM_BOT_TOKEN');
        $url = "https://api.telegram.org/bot{$token}/sendMessage";

        Http::post($url, [
            'chat_id' => $chat_id,
            'text' => $message,
            'parse_mode' => 'HTML',
        ]);
    }
}
