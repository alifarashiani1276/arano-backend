<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AdminProfileController extends Controller
{
    /**
     * نمایش پروفایل مدیر
     */
    public function show(Request $request): JsonResponse
    {
        $admin = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'admin' => [
                    'id' => $admin->id,
                    'phone' => $admin->phone,
                    'first_name' => $admin->first_name,
                    'last_name' => $admin->last_name,
                    'email' => $admin->email,
                    'bio' => $admin->bio,
                    'role' => $admin->role,
                    'status' => $admin->status,
                    'is_active' => $admin->is_active,
                    'created_at' => $admin->created_at,
                ],
            ],
        ]);
    }

    /**
     * ویرایش پروفایل مدیر
     */
    public function update(Request $request): JsonResponse
    {
        $admin = $request->user();

        $validator = Validator::make(
            $request->all(),
            [
                'first_name' => [
                    'required',
                    'string',
                    'min:2',
                    'max:100',
                    'regex:/^[\p{Arabic}A-Za-z\s\x{200C}]+$/u',

                ],

                'last_name' => [
                    'required',
                    'string',
                    'min:2',
                    'max:100',
                    'regex:/^[\p{Arabic}A-Za-z\s\x{200C}]+$/u'
                ],

                'email' => [
                    'required',
                    'email',
                    'max:255',
                    Rule::unique('users', 'email')->ignore($admin->id),
                ],

                'bio' => [
                    'nullable',
                    'string',
                    'max:1000',
                ],
            ],
            [
                'first_name.required' => 'نام الزامی است.',
                'first_name.min' => 'نام باید حداقل ۲ کاراکتر باشد.',

                'last_name.required' => 'نام خانوادگی الزامی است.',
                'last_name.min' => 'نام خانوادگی باید حداقل ۲ کاراکتر باشد.',

                'email.required' => 'ایمیل الزامی است.',
                'email.email' => 'فرمت ایمیل صحیح نیست.',
                'email.unique' => 'این ایمیل قبلاً استفاده شده است.',

                'bio.max' => 'بیوگرافی نمی‌تواند بیشتر از ۱۰۰۰ کاراکتر باشد.',
                'first_name.regex' => 'نام فقط می‌تواند شامل حروف فارسی یا انگلیسی باشد.',
                'last_name.regex' => 'نام خانوادگی فقط می‌تواند شامل حروف فارسی یا انگلیسی باشد.',
            ]   
        );

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'اطلاعات وارد شده معتبر نیست.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $admin->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'پروفایل مدیر با موفقیت به‌روزرسانی شد.',
            'data' => [
                'admin' => [
                    'id' => $admin->id,
                    'phone' => $admin->phone,
                    'first_name' => $admin->first_name,
                    'last_name' => $admin->last_name,
                    'email' => $admin->email,
                    'bio' => $admin->bio,
                    'role' => $admin->role,
                    'status' => $admin->status,
                    'is_active' => $admin->is_active,
                ],
            ],
        ]);
    }
}