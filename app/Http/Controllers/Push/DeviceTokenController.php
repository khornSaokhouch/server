<?php

namespace App\Http\Controllers\Push;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    /**
     * Register / update device token
     */
    public function register(Request $request)
    {
        $request->validate([
            'user_id'      => 'sometimes|exists:users,id',
            'device_token' => 'required|string',
            'platform'     => 'required|in:ios,android',
        ]);

        DeviceToken::updateOrCreate(
            ['token' => $request->device_token],
            [
                'user_id'  =>$request->user_id,
                'platform' => $request->platform,
            ]
        );

        return response()->json([
            'status'  => 'ok',
            'message' => 'Device token registered',
        ]);
    }
}
