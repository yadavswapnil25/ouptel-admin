<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AddressController extends Controller
{
    /**
     * Get all addresses for authenticated user (mimics WoWonder address.php with type=get)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getAddresses(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '5',
                    'error_text' => 'No session sent.'
                ]
            ], 401);
        }
        
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '6',
                    'error_text' => 'Session id is wrong.'
                ]
            ], 401);
        }

        try {
            // Get pagination parameters
            $limit = $request->input('limit', 20);
            $offset = $request->input('offset', 0);

            if ($limit > 50) $limit = 50;

            // Get user's addresses
            $query = DB::table('Wo_UserAddress')
                ->where('user_id', $tokenUserId)
                ->orderBy('id', 'DESC');

            if ($offset > 0) {
                $query->where('id', '<', $offset);
            }

            $addresses = $query->limit($limit)->get();

            return response()->json([
                'api_status' => '200',
                'api_text' => 'success',
                'api_version' => '1.0',
                'data' => $addresses,
                'total' => count($addresses)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => '500',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '7',
                    'error_text' => 'Failed to get addresses: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Get specific address by ID (mimics WoWonder address.php with type=get_by_id)
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function getAddressById(Request $request, int $id): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '5',
                    'error_text' => 'No session sent.'
                ]
            ], 401);
        }
        
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '6',
                    'error_text' => 'Session id is wrong.'
                ]
            ], 401);
        }

        try {
            $address = DB::table('Wo_UserAddress')
                ->where('id', $id)
                ->where('user_id', $tokenUserId)
                ->first();

            if (!$address) {
                return response()->json([
                    'api_status' => '400',
                    'api_text' => 'failed',
                    'api_version' => '1.0',
                    'errors' => [
                        'error_id' => '6',
                        'error_text' => 'Address not found.'
                    ]
                ], 404);
            }

            return response()->json([
                'api_status' => '200',
                'api_text' => 'success',
                'api_version' => '1.0',
                'data' => $address
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => '500',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '7',
                    'error_text' => 'Failed to get address: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Add new address (mimics WoWonder address.php with type=add)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function addAddress(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '5',
                    'error_text' => 'No session sent.'
                ]
            ], 401);
        }
        
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '6',
                    'error_text' => 'Session id is wrong.'
                ]
            ], 401);
        }

        // Validate input
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'country' => 'required|string|max:100',
            'city' => 'required|string|max:100',
            'zip' => 'required|string|max:20',
            'address' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => $validator->errors()->all()
            ], 422);
        }

        try {
            // Create new address
            $addressId = DB::table('Wo_UserAddress')->insertGetId([
                'user_id' => $tokenUserId,
                'name' => $request->input('name'),
                'phone' => $request->input('phone'),
                'country' => $request->input('country'),
                'city' => $request->input('city'),
                'zip' => $request->input('zip'),
                'address' => $request->input('address'),
                'time' => time(),
            ]);

            // Get the created address
            $address = DB::table('Wo_UserAddress')->where('id', $addressId)->first();

            return response()->json([
                'api_status' => '200',
                'api_text' => 'success',
                'api_version' => '1.0',
                'message' => 'Address successfully added',
                'data' => $address
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => '500',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '5',
                    'error_text' => 'Failed to add address: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Update address (mimics WoWonder address.php with type=edit)
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateAddress(Request $request, int $id): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '5',
                    'error_text' => 'No session sent.'
                ]
            ], 401);
        }
        
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '6',
                    'error_text' => 'Session id is wrong.'
                ]
            ], 401);
        }

        // Validate input
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'country' => 'required|string|max:100',
            'city' => 'required|string|max:100',
            'zip' => 'required|string|max:20',
            'address' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => $validator->errors()->all()
            ], 422);
        }

        try {
            // Check if address exists and belongs to user
            $address = DB::table('Wo_UserAddress')
                ->where('id', $id)
                ->where('user_id', $tokenUserId)
                ->first();

            if (!$address) {
                return response()->json([
                    'api_status' => '400',
                    'api_text' => 'failed',
                    'api_version' => '1.0',
                    'errors' => [
                        'error_id' => '6',
                        'error_text' => 'Address not found.'
                    ]
                ], 404);
            }

            // Update address
            DB::table('Wo_UserAddress')
                ->where('id', $id)
                ->where('user_id', $tokenUserId)
                ->update([
                    'name' => $request->input('name'),
                    'phone' => $request->input('phone'),
                    'country' => $request->input('country'),
                    'city' => $request->input('city'),
                    'zip' => $request->input('zip'),
                    'address' => $request->input('address'),
                ]);

            // Get updated address
            $updatedAddress = DB::table('Wo_UserAddress')->where('id', $id)->first();

            return response()->json([
                'api_status' => '200',
                'api_text' => 'success',
                'api_version' => '1.0',
                'message' => 'Address successfully edited',
                'data' => $updatedAddress
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => '500',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '5',
                    'error_text' => 'Failed to update address: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Delete address (mimics WoWonder address.php with type=delete)
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function deleteAddress(Request $request, int $id): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '5',
                    'error_text' => 'No session sent.'
                ]
            ], 401);
        }
        
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '6',
                    'error_text' => 'Session id is wrong.'
                ]
            ], 401);
        }

        try {
            // Check if address exists and belongs to user
            $address = DB::table('Wo_UserAddress')
                ->where('id', $id)
                ->where('user_id', $tokenUserId)
                ->first();

            if (!$address) {
                return response()->json([
                    'api_status' => '400',
                    'api_text' => 'failed',
                    'api_version' => '1.0',
                    'errors' => [
                        'error_id' => '6',
                        'error_text' => 'Address not found.'
                    ]
                ], 404);
            }

            // Delete address
            DB::table('Wo_UserAddress')
                ->where('id', $id)
                ->where('user_id', $tokenUserId)
                ->delete();

            return response()->json([
                'api_status' => '200',
                'api_text' => 'success',
                'api_version' => '1.0',
                'message' => 'Address successfully deleted'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => '500',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '5',
                    'error_text' => 'Failed to delete address: ' . $e->getMessage()
                ]
            ], 500);
        }
    }
}

