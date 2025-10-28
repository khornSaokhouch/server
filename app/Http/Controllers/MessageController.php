<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use Illuminate\Http\Request;
use App\Events\NewNotification;

class MessageController extends Controller
{
    public function sendMessage(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:255'
        ]);

        $message = $request->input('message');

        // Broadcast the event
        broadcast(new MessageSent($message));

        return response()->json([
            'status' => 'success', 
            'message' => 'Message broadcasted!'
        ]);
    }
}