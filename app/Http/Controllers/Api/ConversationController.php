<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    /**
     * نمایش لیست گفتگوهای کاربر
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversations = $user->conversations()
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
     * ایجاد گفتگوی جدید
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'subject' => ['required', 'string', 'min:3', 'max:255'],
            'message' => ['required', 'string', 'min:1', 'max:5000'],
        ]);

        $conversation = $user->conversations()->create([
            'subject' => $validated['subject'],
            'status' => 'OPEN',
        ]);

        $conversation->messages()->create([
            'sender_id' => $user->id,
            'sender_role' => 'USER',
            'message' => $validated['message'],
        ]);

        $conversation->load('messages');

        return response()->json([
            'success' => true,
            'message' => 'گفتگو با موفقیت ایجاد شد.',
            'data' => [
                'conversation' => $conversation,
            ],
        ], 201);
    }

    /**
     * نمایش یک گفتگوی خاص
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $conversation = $user->conversations()
            ->with('messages.sender')
            ->find($id);

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
     * ارسال پیام جدید در یک گفتگو
     */
    public function sendMessage(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $conversation = $user->conversations()->find($id);

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
            'sender_id' => $user->id,
            'sender_role' => 'USER',
            'message' => $validated['message'],
        ]);

        $message->load('sender');

        return response()->json([
            'success' => true,
            'message' => 'پیام با موفقیت ارسال شد.',
            'data' => [
                'message' => $message,
            ],
        ], 201);
    }
}