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
}