<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Notifications\NewUserNotification;
use App\Notifications\WelcomeNotification;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => __('messages.invalid_credentials'),
            ], 401);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'message' => __('messages.account_inactive'),
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        $user->update(['last_login_at' => now()]);

        return response()->json([
            'message' => __('messages.login_success'),
            'token' => $token,
            'user' => $user->load('roles'),
        ]);
    }

public function register(RegisterRequest $request)
{
    $requestedRole = $request->input('role', 'donor');

    $role = Role::where('slug', $requestedRole)->first();

    if (!$role) {
        return response()->json([
            'message' => 'Registration role is not configured.',
        ], 500);
    }

    $isEditor = $requestedRole === 'editor';

    $user = DB::transaction(function () use (
        $request,
        $role,
        $isEditor
    ) {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),

            'status' => $isEditor ? 'inactive' : 'active',
        ]);

        $user->roles()->sync([$role->id]);

        if ($isEditor) {
            \App\Models\ContentChangeRequest::create([
                'user_id' => $user->id,
                'module' => 'editor-registration',
                'action' => 'register',
                'method' => 'REGISTER',
                'route_uri' => 'editor-registration',
                'controller' => self::class,
                'controller_method' => 'register',
                'record_id' => $user->id,
                'payload' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => 'editor',
                ],
                'route_parameters' => [
                    'user_id' => $user->id,
                ],
                'status' => 'pending',
            ]);
        }

        return $user;
    });

    try {
        $admins = User::whereHas('roles', function ($query) {
            $query->whereIn('slug', ['super-admin', 'admin']);
        })->get();

        foreach ($admins as $admin) {
            try {
                $admin->notify(
                    new NewUserNotification($user->id, $user->name)
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if (!$isEditor) {
            $user->notify(new WelcomeNotification($user->name));
        }
    } catch (\Throwable $e) {
        report($e);
    }

    if ($isEditor) {
        return response()->json([
            'message' => 'Your editor registration is awaiting admin approval.',
            'status' => 'pending',
            'user' => $user->load('roles'),
        ], 202);
    }

    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'message' => __('messages.register_success'),
        'token' => $token,
        'user' => $user->load('roles'),
    ], 201);
}


    public function profile(Request $request)
    {
        return response()->json([
            'message' => __('messages.profile'),
            'user' => $request->user()->load('roles'),
        ]);
    }

    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        return response()->json([
            'message' => __('messages.logout_success'),
        ]);
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => __('messages.change_password_incorrect'),
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);
                $user->tokens()
            ->where('id', '!=', $user->currentAccessToken()->id ?? 0)
            ->delete();

        return response()->json([
            'message' => __('messages.change_password_success'),
        ]);
    }
}
