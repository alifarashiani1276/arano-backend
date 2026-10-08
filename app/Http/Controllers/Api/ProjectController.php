<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Project;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    /**
     * لیست پروژه‌های کاربر
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $projects = Project::with(['category', 'tags'])
            ->where('user_id', $user->id)
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
     * ایجاد پروژه جدید
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'title' => [
                'required',
                'string',
                'min:3',
                'max:255',
            ],

            'description' => [
                'required',
                'string',
                'min:10',
                'max:5000',
            ],

            'budget' => [
                'required',
                'numeric',
                'min:0',
            ],

            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')
                    ->where('is_active', true),
            ],

            'deadline' => [
                'required',
                'date',
                'after_or_equal:today',
            ],

            'tags' => [
                'nullable',
                'array',
                'max:10',
            ],

            'tags.*' => [
                'integer',
                'distinct',
                Rule::exists('tags', 'id')
                    ->where('is_active', true),
            ],
        ]);

        $project = Project::create([
            'user_id' => $user->id,
            'category_id' => $validated['category_id'],
            'title' => $validated['title'],
            'description' => $validated['description'],
            'budget' => $validated['budget'],
            'deadline' => $validated['deadline'],
            'status' => 'PENDING',
        ]);

        if (!empty($validated['tags'])) {
            $project->tags()->sync($validated['tags']);
        }

        $project->load(['category', 'tags']);

        return response()->json([
            'success' => true,
            'message' => 'پروژه با موفقیت ایجاد شد.',
            'data' => [
                'project' => $project,
            ],
        ], 201);
    }

    /**
     * نمایش یک پروژه
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $project = Project::with(['category', 'tags'])
            ->where('user_id', $user->id)
            ->find($id);

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
     * ویرایش پروژه
     */
    public function update(
        Request $request,
        int $id
    ): JsonResponse {
        $user = $request->user();

        $project = Project::where('user_id', $user->id)
            ->find($id);

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
                'after_or_equal:today',
            ],

            'tags' => [
                'sometimes',
                'nullable',
                'array',
                'max:10',
            ],

            'tags.*' => [
                'integer',
                'distinct',
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

        $project->load(['category', 'tags']);

        return response()->json([
            'success' => true,
            'message' => 'پروژه با موفقیت ویرایش شد.',
            'data' => [
                'project' => $project,
            ],
        ]);
    }

    /**
     * حذف پروژه
     */
    public function destroy(
        Request $request,
        int $id
    ): JsonResponse {
        $user = $request->user();

        $project = Project::where('user_id', $user->id)
            ->find($id);

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