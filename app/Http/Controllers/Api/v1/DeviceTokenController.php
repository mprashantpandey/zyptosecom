<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\UserDeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DeviceTokenController extends Controller
{
    /**
     * Register/update device token
     * 
     * POST /api/v1/devices/register-token
     * Body: { token: string, platform: 'android'|'ios'|'web' }
     */
    public function registerToken(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string|max:500',
            'platform' => 'required|string|in:android,ios,web',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = $request->user();
            
            $deviceToken = UserDeviceToken::updateOrCreate(
                [
                    'token' => $request->token,
                ],
                [
                    'user_id' => $user->id,
                    'platform' => $request->platform,
                    'last_seen_at' => now(),
                    'is_active' => true,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Device token registered successfully',
                'data' => [
                    'id' => $deviceToken->id,
                    'platform' => $deviceToken->platform,
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to register device token',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Unregister device token
     * 
     * POST /api/v1/devices/unregister-token
     * Body: { token: string }
     */
    public function unregisterToken(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = $request->user();
            
            UserDeviceToken::where('token', $request->token)
                ->where('user_id', $user->id)
                ->update(['is_active' => false]);

            return response()->json([
                'success' => true,
                'message' => 'Device token unregistered successfully',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to unregister device token',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
