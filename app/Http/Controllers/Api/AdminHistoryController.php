<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AdminHistoryController extends Controller
{
    /**
     * سابقه پروژه‌ها
     */
    public function projects(): JsonResponse
    {
        $projects = Project::with([
            'user',
            'category',
            'tags',
        ])
            ->latest('updated_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'projects' => $projects,
            ],
        ]);
    }

    /**
     * سابقه کاربران
     */
    public function users(): JsonResponse
    {
        $users = User::where('role', 'USER')
            ->withCount([
                'projects',
                'conversations',
            ])
            ->latest('updated_at')
            ->get([
                'id',
                'phone',
                'first_name',
                'last_name',
                'email',
                'status',
                'is_active',
                'created_at',
                'updated_at',
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'users' => $users,
            ],
        ]);
    }
}