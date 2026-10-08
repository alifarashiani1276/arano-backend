<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $users = [
            'total' => User::where('role', 'USER')->count(),
            'pending' => User::where('role', 'USER')
                ->where('status', 'PENDING')
                ->count(),
            'approved' => User::where('role', 'USER')
                ->where('status', 'APPROVED')
                ->count(),
            'rejected' => User::where('role', 'USER')
                ->where('status', 'REJECTED')
                ->count(),
        ];

        $projects = [
            'total' => Project::count(),
            'pending' => Project::where('status', 'PENDING')->count(),
            'active' => Project::where('status', 'IN_PROGRESS')->count(),
            'completed' => Project::where('status', 'COMPLETED')->count(),
            'cancelled' => Project::where('status', 'CANCELLED')->count(),
        ];

        $conversations = [
            'open' => Conversation::where('status', 'OPEN')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => [
                    'users' => $users,
                    'projects' => $projects,
                    'requests' => [
                        'pending' => $users['pending'],
                    ],
                    'conversations' => $conversations,
                ],
            ],
        ]);
    }
}