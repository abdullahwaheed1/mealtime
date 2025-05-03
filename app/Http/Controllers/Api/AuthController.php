<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Otp;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Register a new user
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|string|email|max:255|unique:users',
            'user_type' => 'required|in:chef,customer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Create user without password
        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'user_type' => $request->user_type,
            'timestamp' => now(),
        ]);

        // Fixed OTP code for development
        $code = 123456;

        // Store the OTP
        Otp::create([
            'user_id' => $user->id,
            'code' => $code,
            'timestamp' => now(),
        ]);

        /* 
        // Generate random OTP code - Commented out until live
        // $code = rand(100000, 999999);

        // Send the OTP code via email - Commented out until live
        // $this->sendVerificationEmail($user->email, $code);
        */

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully. Please verify your email with the OTP.',
            'user_id' => $user->id
        ], 201);
    }

    /**
     * Send reset password code
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function sendResetCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if user exists
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User with this email not found'
            ], 404);
        }

        // Fixed OTP code for development
        $code = 123456;

        // Store the OTP
        Otp::create([
            'user_id' => $user->id,
            'code' => $code,
            'timestamp' => now(),
        ]);

        /* 
        // Generate random OTP code - Commented out until live
        // $code = rand(100000, 999999);

        // Send the OTP code via email - Commented out until live
        // $this->sendVerificationEmail($user->email, $code);
        */

        return response()->json([
            'success' => true,
            'message' => 'Password reset code sent successfully',
            'user_id' => $user->id
        ], 200);
    }

    /**
     * Verify OTP
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if OTP exists and is valid
        $otp = Otp::where('user_id', $request->user_id)
                  ->where('code', $request->code)
                  ->orderBy('id', 'desc')
                  ->first();

        if (!$otp) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP code'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully',
            'user_id' => $request->user_id
        ], 200);
    }

    /**
     * Set user password after OTP verification
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function setPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Update user password
        $user = User::find($request->user_id);
        $user->password = Hash::make($request->password);
        $user->save();

        // Set default values as needed
        if (empty($user->dob)) {
            $user->dob = '1989-12-02';
            $user->save();
        }

        // Generate JWT token instead of Sanctum token
        $token = auth('api')->login($user);

        return response()->json([
            'success' => true,
            'message' => 'Password set successfully',
            'user' => $user,
            'token' => $token,
        ], 200);
    }

    /**
     * User login
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Attempt to authenticate with JWT
        $credentials = $request->only('email', 'password');
        if (!$token = auth('api')->attempt($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid login credentials'
            ], 401);
        }

        $user = User::where('email', $request->email)->firstOrFail();

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token,
        ], 200);
    }
    /**
     * Send verification email with OTP
     * Commented out until project goes live
     *
     * @param string $email
     * @param string $code
     * @return void
     */
    private function sendVerificationEmail($email, $code)
    {
        $subject = "Your Verification Code";
        $message = "Your OTP verification code is: " . $code;
        
        Mail::raw($message, function ($mail) use ($email, $subject) {
            $mail->to($email)
                ->subject($subject);
        });
    }

    public function me()
    {
        return response()->json(auth('api')->user());
    }

    /**
     * Refresh a token.
     */
    public function refresh()
    {
        return $this->respondWithToken(auth('api')->refresh());
    }

    /**
     * Log the user out (Invalidate the token).
     */
    public function logout()
    {
        auth('api')->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

    /**
     * Get the token array structure.
     */
    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60
        ]);
    }

    /**
     * Handle social login (Google, Apple, etc.)
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function socialLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'social_token' => 'required|string',
            'user_type' => 'required|in:chef,customer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if user already exists with this email
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            // Create new user if not exists
            $user = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'user_type' => $request->user_type,
                'password' => Hash::make(uniqid()), // Generate a random password that won't be used
                'social_token' => $request->social_token
            ]);
        }

        // Generate token for the user
        $token = auth('api')->login($user);

        return response()->json([
            'success' => true,
            'message' => 'Social login successful',
            'user' => $user,
            'token' => $token,
        ], 200);
    }

    /**
     * Register device for push notifications
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function registerDevice(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'device_platform' => 'required|string|in:ios,android',
            'device_rid' => 'required|string', // Firebase token/registration ID
            'device_model' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Get the authenticated user
        $user = auth('api')->user();

        // Create or update device record
        $device = \DB::table('devices')
            ->updateOrInsert(
                [
                    'user_id' => $user->id,
                    'deviceRid' => $request->device_rid
                ],
                [
                    'user_id' => $user->id,
                    'rider_id' => 0,
                    'devicePlatform' => $request->device_platform,
                    'deviceRid' => $request->device_rid,
                    'deviceModel' => $request->device_model
                ]
            );

        return response()->json([
            'success' => true,
            'message' => 'Device registered successfully'
        ], 200);
    }
    
       /**
     * Update user profile
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function updateProfile(Request $request)
    {
        // Get authenticated user
        $user = auth('api')->user();
        
   

        try {
            // If password is being updated, verify old password
            if ($request->has('password') && $request->filled('password')) {
                if (!Hash::check($request->old_password, $user->password)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Current password is incorrect'
                    ], 400);
                }
                $user->password = Hash::make($request->password);
            }

            // Update basic profile fields
            if ($request->has('first_name')) {
                $user->first_name = $request->first_name;
            }
            
            if ($request->has('last_name')) {
                $user->last_name = $request->last_name;
            }
            
            if ($request->has('phone')) {
                $user->phone = $request->phone;
            }
            
            if ($request->has('dob')) {
                $user->dob = $request->dob;
            }
            
            if ($request->has('email')) {
                $user->email = $request->email;
            }
            
            if ($request->has('image')) {
                $user->image = $request->image;
            }
            
            if ($request->has('address')) {
                $user->address = $request->address;
            }
            
            if ($request->has('city')) {
                $user->city = $request->city;
            }
            
            if ($request->has('state')) {
                $user->state = $request->state;
            }
            
            if ($request->has('country')) {
                $user->country = $request->country;
            }
            
            if ($request->has('postal_code')) {
                $user->postal_code = $request->postal_code;
            }
            
            if ($request->has('gender')) {
                $user->gender = $request->gender;
            }
            
            if ($request->has('bio')) {
                $user->bio = $request->bio;
            }
            
            if ($request->has('notification_enabled')) {
                $user->notification_enabled = $request->notification_enabled;
            }
            
            if ($request->has('language')) {
                $user->language = $request->language;
            }

            // Update chef-specific fields if user is a chef
            if ($user->user_type === 'chef') {
                if ($request->has('about')) {
                    $user->about = $request->about;
                }
                
                if ($request->has('address_name')) {
                    $user->address_name = $request->address_name;
                }
                
                if ($request->has('address_detail')) {
                    $user->address_detail = $request->address_detail;
                }
                
                if ($request->has('note')) {
                    $user->note = $request->note;
                }
                
                if ($request->has('current_lat')) {
                    $user->current_lat = $request->current_lat;
                }
                
                if ($request->has('current_lng')) {
                    $user->current_lng = $request->current_lng;
                }
                
                if ($request->has('availability_pickup')) {
                    $user->availability_pickup = $request->availability_pickup;
                }
                
                if ($request->has('availability_delivery')) {
                    $user->availability_delivery = $request->availability_delivery;
                }
                
                if ($request->has('availability_dinein')) {
                    $user->availability_dinein = $request->availability_dinein;
                }
                
                if ($request->has('delivery_price')) {
                    $user->delivery_price = $request->delivery_price;
                }
                
                if ($request->has('dinein_price')) {
                    $user->dinein_price = $request->dinein_price;
                }
                
                if ($request->has('dinein_limit')) {
                    $user->dinein_limit = $request->dinein_limit;
                }
                
                if ($request->has('rest_status')) {
                    $user->rest_status = $request->rest_status;
                }
                
                // Handle bank details update
                if ($request->has('bank_details') && $request->has('payment_method')) {
                    $bankDetails = [
                        'payment_method' => $request->payment_method,
                        'details' => $request->bank_details
                    ];
                    $user->bank_details = json_encode($bankDetails);
                }
            }

            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'user' => $user
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}