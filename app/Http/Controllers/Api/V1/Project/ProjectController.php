<?php

namespace App\Http\Controllers\Api\V1\Project;

use App\Models\Project;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Services\ActivityLogService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProjectController extends Controller
{
    /**
     * عرض جميع المشاريع
     */
    public function index()
    {
        return ProjectResource::collection(
            Project::with(['donationPackages', 'cases'])
                ->withSum([
                    'donations as collected_amount_sum' => function ($query) {
                        $query->where('status', 'paid');
                    },
                ], 'amount')
                ->latest()
                ->paginate(\App\Support\PerPage::from(request(), 10))
        );
    }

    /**
     * إنشاء مشروع جديد
     */
    public function store(StoreProjectRequest $request)
    {
        $data = $request->validated();

        Validator::make($data, [
            'target_amount' => [
                'required',
                'numeric',
                'min:1',
            ],
        ])->validate();

        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')
                ->store('projects', 'public');
        }

        $project = Project::create($data);

        app(ActivityLogService::class)->created(
            $project,
            'إنشاء مشروع جديد: ' . ($project->name ?? 'Project'),
            [
                'attributes' => $data,
            ]
        );

        // تحميل إجمالي التبرعات قبل إرجاع المشروع
        $project = Project::withSum([
            'donations as collected_amount_sum' => function ($query) {
                $query->where('status', 'paid');
            },
        ], 'amount')->findOrFail($project->id);

        return new ProjectResource($project);
    }

    /**
     * عرض تفاصيل مشروع معين
     */
    public function show(Project $project)
    {
        // تحميل العلاقات وإجمالي التبرعات المدفوعة
        $project = Project::with([
            'donationPackages',
            'cases',
        ])->withSum([
            'donations as collected_amount_sum' => function ($query) {
                $query->where('status', 'paid');
            },
        ], 'amount')->findOrFail($project->id);

        return new ProjectResource($project);
    }

    /**
     * تعديل مشروع موجود
     */
    public function update(
        UpdateProjectRequest $request,
        Project $project
    ) {
        $oldData = $project->toArray();

        $data = $request->validated();

        Validator::make($data, [
            'target_amount' => [
                'sometimes',
                'required',
                'numeric',
                'min:1',
            ],
        ])->validate();

        $oldImage = $project->cover_image;

        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')
                ->store('projects', 'public');
        }

        $project->update($data);

        if (
            $oldImage &&
            $project->cover_image !== $oldImage &&
            str_starts_with(ltrim($oldImage, '/'), 'projects/')
        ) {
            Storage::disk('public')->delete($oldImage);
        }

        app(ActivityLogService::class)->updated(
            $project,
            'تعديل المشروع: ' . ($project->name ?? 'Project'),
            [
                'old' => $oldData,
                'new' => $project->fresh()->toArray(),
            ]
        );

        // إعادة تحميل المشروع مع إجمالي التبرعات المدفوعة
        $project = Project::with([
            'donationPackages',
            'cases',
        ])->withSum([
            'donations as collected_amount_sum' => function ($query) {
                $query->where('status', 'paid');
            },
        ], 'amount')->findOrFail($project->id);

        return new ProjectResource($project);
    }

    /**
     * حذف مشروع
     */
    public function destroy(Project $project)
    {
        $projectName = $project->name ?? 'Project';
        $projectId = $project->id;

        app(ActivityLogService::class)->deleted(
            $project,
            'حذف المشروع: ' . $projectName,
            [
                'deleted_project_id' => $projectId,
                'deleted_project_name' => $projectName,
            ]
        );

        $project->delete();

        return response()->json([
            'message' => 'Project deleted successfully',
        ]);
    }
}