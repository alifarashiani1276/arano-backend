<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $totalProjects = $user->projects()->count();

        $pendingProjects = $user->projects()
            ->where('status', 'PENDING')
            ->count();

        $activeProjects = $user->projects()
            ->where('status', 'IN_PROGRESS')
            ->count();

        $completedProjects = $user->projects()
            ->where('status', 'COMPLETED')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => [
                    'total_projects' => $totalProjects,
                    'pending_projects' => $pendingProjects,
                    'active_projects' => $activeProjects,
                    'completed_projects' => $completedProjects,
                ],
                'user' => [
                    'joined_at' => $user->created_at,
                ],
            ],
        ]);
    }
}