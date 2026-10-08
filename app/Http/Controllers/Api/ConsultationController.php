<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Consultation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsultationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => [
                'required',
                'string',
                'regex:/^09\d{9}$/',
            ],
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
                'regex:/^[\p{Arabic}A-Za-z\s\x{200C}]+$/u',
            ],
            'message' => [
                'required',
                'string',
                'min:5',
                'max:5000',
            ],
        ]);

        $consultation = Consultation::create([
            'phone' => $validated['phone'],
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'message' => $validated['message'],
            'status' => 'UNANSWERED',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'درخواست مشاوره با موفقیت ثبت شد.',
            'data' => [
                'consultation' => [
                    'id' => $consultation->id,
                    'status' => $consultation->status,
                    'created_at' => $consultation->created_at,
                ],
            ],
        ], 201);
    }
}