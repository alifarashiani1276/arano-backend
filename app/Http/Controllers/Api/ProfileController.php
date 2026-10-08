<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'min:2', 'max:100' ,'regex:/^[\p{Arabic}A-Za-z\s\x{200C}]+$/u'],
            'last_name' => ['required', 'string', 'min:2', 'max:100' , 'regex:/^[\p{Arabic}A-Za-z\s\x{200C}]+$/u'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'bio' => ['nullable', 'string', 'max:1000'],
        ]);

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'اطلاعات حساب کاربری با موفقیت تکمیل شد.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'phone' => $user->phone,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'bio' => $user->bio,
                    'role' => $user->role,
                    'status' => $user->status,
                    'profile_completed' => true,
                ],
            ],
        ]);
    }
}