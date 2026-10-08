<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminProjectController extends Controller
{
    /**
     * لیست تمام پروژه‌ها
     */
    public function index(): JsonResponse
    {
        $projects = Project::with([
            'user',
            'category',
            'tags',
        ])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'projects' => $projects,
            ],
        ]);
    }

    /**
     * نمایش جزئیات یک پروژه
     */
    public function show(int $id): JsonResponse
    {
        $project = Project::with([
            'user',
            'category',
            'tags',
        ])->find($id);

        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => 'پروژه مورد نظر یافت نشد.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'project' => $project,
            ],
        ]);
    }

    /**
     * ویرایش پروژه توسط Admin
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $project = Project::find($id);

        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => 'پروژه مورد نظر یافت نشد.',
            ], 404);
        }

        $validated = $request->validate([
            'title' => [
                'sometimes',
                'required',
                'string',
                'min:3',
                'max:255',
            ],

            'description' => [
                'sometimes',
                'required',
                'string',
                'min:10',
                'max:5000',
            ],

            'budget' => [
                'sometimes',
                'required',
                'numeric',
                'min:0',
            ],

            'category_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('categories', 'id')
                    ->where('is_active', true),
            ],

            'deadline' => [
                'sometimes',
                'required',
                'date',
            ],

            'status' => [
                'sometimes',
                'required',
                'string',
                Rule::in([
                    'PENDING',
                    'IN_PROGRESS',
                    'COMPLETED',
                    'CANCELLED',
                ]),
            ],

            'tags' => [
                'sometimes',
                'nullable',
                'array',
                'max:10',
            ],

            'tags.*' => [
                'integer',
                Rule::exists('tags', 'id')
                    ->where('is_active', true),
            ],
        ]);

        $project->update(
            collect($validated)
                ->except('tags')
                ->toArray()
        );

        if (array_key_exists('tags', $validated)) {
            $project->tags()->sync($validated['tags'] ?? []);
        }

        $project->load([
            'user',
            'category',
            'tags',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'پروژه با موفقیت ویرایش شد.',
            'data' => [
                'project' => $project,
            ],
        ]);
    }

    /**
     * تغییر وضعیت پروژه
     */
    public function updateStatus(
        Request $request,
        int $id
    ): JsonResponse {
        $project = Project::find($id);

        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => 'پروژه مورد نظر یافت نشد.',
            ], 404);
        }

        $validated = $request->validate([
            'status' => [
                'required',
                'string',
                Rule::in([
                    'PENDING',
                    'IN_PROGRESS',
                    'COMPLETED',
                    'CANCELLED',
                ]),
            ],
        ]);

        $project->update([
            'status' => $validated['status'],
        ]);

        $project->load([
            'user',
            'category',
            'tags',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'وضعیت پروژه با موفقیت تغییر کرد.',
            'data' => [
                'project' => $project,
            ],
        ]);
    }

    /**
     * حذف پروژه توسط Admin
     */
    public function destroy(int $id): JsonResponse
    {
        $project = Project::find($id);

        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => 'پروژه مورد نظر یافت نشد.',
            ], 404);
        }

        $project->delete();

        return response()->json([
            'success' => true,
            'message' => 'پروژه با موفقیت حذف شد.',
        ]);
    }
}