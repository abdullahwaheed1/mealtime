<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class UserAddressController extends Controller
{
    /**
     * List all addresses for authenticated user
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user = auth('api')->user();
        
        $addresses = UserAddress::where('user_id', $user->id)
                               ->orderBy('timestamp', 'desc')
                               ->get();
        
        return response()->json([
            'success' => true,
            'data' => $addresses
        ], 200);
    }

    /**
     * Add a new address
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'address' => 'required|string|max:100',
            'city' => 'required|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:150',
            'address_type' => 'required|string|max:50',
            'lat' => 'required|string|max:20',
            'lng' => 'required|string|max:20',
            'appartment' => 'nullable|string|max:50',
            'note' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = auth('api')->user();
        $now = Carbon::now();

        $address = UserAddress::create([
            'user_id' => $user->id,
            'address' => $request->address,
            'city' => $request->city,
            'state' => $request->state ?? '',
            'postal' => $request->postal ?? '',
            'country' => $request->country ?? '',
            'address_type' => $request->address_type,
            'lat' => $request->lat,
            'lng' => $request->lng,
            'appartment' => $request->appartment ?? '',
            'note' => $request->note ?? '',
            'timestamp' => $now,
            'update_timestamp' => $now
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Address created successfully',
            'data' => $address
        ], 201);
    }

    /**
     * Update address
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'address' => 'sometimes|string|max:100',
            'city' => 'sometimes|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:150',
            'address_type' => 'sometimes|string|max:50',
            'lat' => 'sometimes|string|max:20',
            'lng' => 'sometimes|string|max:20',
            'appartment' => 'nullable|string|max:50',
            'note' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = auth('api')->user();
        
        $address = UserAddress::where('user_id', $user->id)
                              ->where('id', $id)
                              ->first();
        
        if (!$address) {
            return response()->json([
                'success' => false,
                'message' => 'Address not found'
            ], 404);
        }

        // Update only the fields that are provided
        $address->fill($request->only([
            'address', 'city', 'state', 'postal', 'country', 
            'address_type', 'lat', 'lng', 'appartment', 'note'
        ]));

        $address->update_timestamp = Carbon::now();
        $address->save();

        return response()->json([
            'success' => true,
            'message' => 'Address updated successfully',
            'data' => $address
        ], 200);
    }

    /**
     * Delete address
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $user = auth('api')->user();
        
        $address = UserAddress::where('user_id', $user->id)
                              ->where('id', $id)
                              ->first();
        
        if (!$address) {
            return response()->json([
                'success' => false,
                'message' => 'Address not found'
            ], 404);
        }

        $address->delete();

        return response()->json([
            'success' => true,
            'message' => 'Address deleted successfully'
        ], 200);
    }
}