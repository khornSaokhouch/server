<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Events\MessageSent;

class BroadcastController extends Controller
{
    public function sendMessage(Request $request)
    {
        $message = $request->input('message', 'Hello from Laravel!');

        // Broadcast the event
        broadcast(new MessageSent($message));

        return response()->json([
            'status' => 'success',
            'message' => 'Message broadcasted!',
        ]);
    }
}
