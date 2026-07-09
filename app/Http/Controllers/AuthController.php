<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Mail\ResetPasswordMail;

class AuthController extends Controller
{
    protected JwtService $jwtService;

    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    /**
     * Register a new user.
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users',
                function ($attribute, $value, $fail) {
                    if (!preg_match('/^.+@(students\.)?amikom\.ac\.id$/i', $value)) {
                        $fail('The email must be a valid Amikom email address (@students.amikom.ac.id or @amikom.ac.id).');
                    }
                },
            ],
            'password' => 'required|string|min:6',
            'contact_info' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Determine role: if first user, assign ADMIN. Otherwise, USER.
        $role = User::count() === 0 ? 'ADMIN' : 'USER';

        $user = User::create([
            'name' => $request->name,
            'email' => strtolower($request->email),
            'password' => Hash::make($request->password),
            'contact_info' => $request->contact_info,
            'role' => $role,
        ]);

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user
        ], 201);
    }

    /**
     * Login user and issue tokens.
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', strtolower($request->email))->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid email or password'
            ], 401);
        }

        $accessToken = $this->jwtService->generateAccessToken($user);
        $refreshToken = $this->jwtService->generateRefreshToken($user);

        // Set refresh token in secure HttpOnly cookie (expires in 7 days = 10080 minutes)
        $cookie = cookie(
            'refresh_token',
            $refreshToken,
            10080,
            '/',
            null,
            false, // set to true in production with HTTPS
            true // HttpOnly
        );

        return response()->json([
            'message' => 'Login successful',
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken, // also returning in body for frontend convenience
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'trust_score' => $user->trust_score,
                'contact_info' => $user->contact_info,
                'avatar_url' => $user->avatar_url,
            ]
        ], 200)->withCookie($cookie);
    }

    /**
     * Refresh access token.
     */
    public function refresh(Request $request)
    {
        // Try getting refresh token from cookie, request body, or Authorization header
        $refreshToken = $request->cookie('refresh_token') 
            ?? $request->input('refresh_token') 
            ?? $request->bearerToken();

        if (!$refreshToken) {
            return response()->json([
                'message' => 'Refresh token missing'
            ], 400);
        }

        $payload = $this->jwtService->decodeToken($refreshToken);

        if (!$payload || !isset($payload->sub) || !isset($payload->type) || $payload->type !== 'refresh') {
            return response()->json([
                'message' => 'Invalid or expired refresh token'
            ], 401);
        }

        $user = User::find($payload->sub);

        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ], 401);
        }

        $newAccessToken = $this->jwtService->generateAccessToken($user);

        return response()->json([
            'access_token' => $newAccessToken
        ], 200);
    }

    /**
     * Get authenticated user profile.
     */
    public function me(Request $request)
    {
        return response()->json([
            'user' => auth()->user()
        ], 200);
    }

    /**
     * Update user profile.
     */
    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'contact_info' => 'nullable|string|max:20',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        if ($request->has('name')) {
            $user->name = $request->name;
        }

        if ($request->has('contact_info')) {
            $user->contact_info = $request->contact_info;
        }

        if ($request->hasFile('avatar')) {
            // Store file locally inside public/uploads/avatars
            $file = $request->file('avatar');
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads/avatars'), $filename);
            
            // Set URL
            $user->avatar_url = asset('uploads/avatars/' . $filename);
        }

        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user
        ], 200);
    }

    /**
     * Send password reset token to user email.
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $token = Str::random(60);
        $email = strtolower($request->email);

        // Store or update the token
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => $token,
                'created_at' => Carbon::now()
            ]
        );

        // Send Email
        try {
            Mail::to($email)->send(new ResetPasswordMail($token, $email));
        } catch (\Exception $e) {
            // Fail silently or return error if mail config blocks it
            return response()->json([
                'message' => 'Gagal mengirim email reset password: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'message' => 'Tautan atur ulang kata sandi berhasil dikirim ke email Anda.'
        ], 200);
    }

    /**
     * Reset user password using the verification token.
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'token' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $email = strtolower($request->email);

        // Retrieve token record
        $record = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->where('token', $request->token)
            ->first();

        if (!$record) {
            return response()->json([
                'message' => 'Token reset password tidak valid atau tidak cocok.'
            ], 400);
        }

        // Check if token expired (expires after 60 minutes)
        if (Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            // Delete expired token
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            return response()->json([
                'message' => 'Token reset password sudah kedaluwarsa.'
            ], 400);
        }

        // Update password
        $user = User::where('email', $email)->first();
        $user->password = Hash::make($request->password);
        $user->save();

        // Delete token after successful reset
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        return response()->json([
            'message' => 'Kata sandi berhasil diubah! Silakan masuk kembali.'
        ], 200);
    }
}
