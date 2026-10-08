<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Consultation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminConsultationController extends Controller
{
    /**
     * لیست درخواست‌های مشاوره
     */
    public function index(): JsonResponse
    {
        $consultations = Consultation::latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'consultations' => $consultations,
            ],
        ]);
    }

    /**
     * مشاهده جزئیات یک درخواست
     */
    public function show(int $id): JsonResponse
    {
        $consultation = Consultation::find($id);

        if (!$consultation) {
            return response()->json([
                'success' => false,
                'message' => 'درخواست مشاوره مورد نظر یافت نشد.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'consultation' => $consultation,
            ],
        ]);
    }

    /**
     * تغییر وضعیت درخواست
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $consultation = Consultation::find($id);

        if (!$consultation) {
            return response()->json([
                'success' => false,
                'message' => 'درخواست مشاوره مورد نظر یافت نشد.',
            ], 404);
        }

        $validated = $request->validate([
            'status' => [
                'required',
                'string',
                Rule::in([
                    'UNANSWERED',
                    'ANSWERED',
                ]),
            ],
        ]);

        $consultation->update([
            'status' => $validated['status'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'وضعیت درخواست مشاوره با موفقیت تغییر کرد.',
            'data' => [
                'consultation' => $consultation,
            ],
        ]);
    }
}