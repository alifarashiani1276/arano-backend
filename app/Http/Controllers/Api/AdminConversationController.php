<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminConversationController extends Controller
{
    /**
     * نمایش تمام گفتگوها
     */
    public function index(): JsonResponse
    {
        $conversations = Conversation::with('user')
            ->withCount('messages')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'conversations' => $conversations,
            ],
        ]);
    }

    /**
     * نمایش یک گفتگوی خاص
     */
    public function show(int $id): JsonResponse
    {
        $conversation = Conversation::with([
            'user',
            'messages.sender',
        ])->find($id);

        if (!$conversation) {
            return response()->json([
                'success' => false,
                'message' => 'گفتگو مورد نظر یافت نشد.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'conversation' => $conversation,
            ],
        ]);
    }

    /**
     * پاسخ Admin به گفتگو
     */
    public function sendMessage(Request $request, int $id): JsonResponse
    {
        $conversation = Conversation::find($id);

        if (!$conversation) {
            return response()->json([
                'success' => false,
                'message' => 'گفتگو مورد نظر یافت نشد.',
            ], 404);
        }

        if ($conversation->status === 'CLOSED') {
            return response()->json([
                'success' => false,
                'message' => 'این گفتگو بسته شده است و امکان ارسال پیام جدید وجود ندارد.',
            ], 422);
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'min:1', 'max:5000'],
        ]);

        $message = $conversation->messages()->create([
            'sender_id' => $request->user()->id,
            'sender_role' => 'ADMIN',
            'message' => $validated['message'],
        ]);

        $message->load('sender');

        return response()->json([
            'success' => true,
            'message' => 'پاسخ با موفقیت ارسال شد.',
            'data' => [
                'message' => $message,
            ],
        ], 201);
    }

    /**
     * بستن گفتگو
     */
    public function close(int $id): JsonResponse
    {
        $conversation = Conversation::find($id);

        if (!$conversation) {
            return response()->json([
                'success' => false,
                'message' => 'گفتگو مورد نظر یافت نشد.',
            ], 404);
        }

        if ($conversation->status === 'CLOSED') {
            return response()->json([
                'success' => false,
                'message' => 'این گفتگو قبلاً بسته شده است.',
            ], 422);
        }

        $conversation->update([
            'status' => 'CLOSED',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'گفتگو با موفقیت بسته شد.',
            'data' => [
                'conversation' => $conversation,
            ],
        ]);
    }
}