<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $projects = $user->projects()
            ->with(['category', 'tags'])
            ->latest()
            ->get();

        $totalProjects = $projects->count();

        $pendingProjects = $projects
            ->where('status', 'PENDING')
            ->count();

        $activeProjects = $projects
            ->where('status', 'IN_PROGRESS')
            ->count();

        $completedProjects = $projects
            ->where('status', 'COMPLETED')
            ->count();

        $cancelledProjects = $projects
            ->where('status', 'CANCELLED')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_projects' => $totalProjects,
                    'pending_projects' => $pendingProjects,
                    'active_projects' => $activeProjects,
                    'completed_projects' => $completedProjects,
                    'cancelled_projects' => $cancelledProjects,
                ],
                'projects' => $projects,
            ],
        ]);
    }
}