<?php

namespace App\Http\Controllers\Api\V1\Program;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\ProgramPageSection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProgramPageSectionController extends Controller
{
    public function index(Program $program)
    {
        return response()->json([
            'data' => $program->pageSections()
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

    public function store(Request $request, Program $program)
    {
        // Save the hero independently from other page sections.
        if ($request->input('section_key') === 'hero') {
            return $this->saveHero($request, $program);
        }

        $data = $this->validateSection($request, $program);

        $section = $program->pageSections()->firstOrNew([
            'section_key' => $data['section_key'],
        ]);

        $section->fill($data);
        $section->save();

        return response()->json([
            'message' => 'Section saved successfully',
            'data' => $section->fresh(),
        ], 200);
    }

    public function update(
        Request $request,
        Program $program,
        ProgramPageSection $section
    ) {
        $this->ensureSectionBelongsToProgram($program, $section);

        if ($section->section_key === 'hero') {
            return $this->saveHero($request, $program, $section);
        }

        $data = $this->validateSection($request, $program, $section);

        $section->fill($data);
        $section->save();

        return response()->json([
            'message' => 'Section updated successfully',
            'data' => $section->fresh(),
        ]);
    }

    public function toggle(
        Request $request,
        Program $program,
        ProgramPageSection $section
    ) {
        $this->ensureSectionBelongsToProgram($program, $section);

        $data = $request->validate([
            'is_enabled' => 'required|boolean',
        ]);

        $section->update($data);

        return response()->json([
            'message' => 'Section status updated successfully',
            'data' => $section->fresh(),
        ]);
    }

    public function destroy(
        Program $program,
        ProgramPageSection $section
    ) {
        $this->ensureSectionBelongsToProgram($program, $section);

        $this->deleteSectionFiles($section);
        $section->delete();

        return response()->json([
            'message' => 'Section deleted successfully',
        ]);
    }

    private function saveHero(
        Request $request,
        Program $program,
        ?ProgramPageSection $section = null
    ) {
        $data = $request->validate([
            'section_key' => [
                'sometimes',
                'required',
                'in:hero',
            ],

            'title_ar' => 'sometimes|nullable|string|max:255',
            'title_en' => 'sometimes|nullable|string|max:255',
            'subtitle_ar' => 'sometimes|nullable|string|max:255',
            'subtitle_en' => 'sometimes|nullable|string|max:255',

            'content_ar' => 'sometimes|nullable|array',
            'content_en' => 'sometimes|nullable|array',
            'settings' => 'sometimes|nullable|array',

            'settings.badge_ar' => 'sometimes|nullable|string|max:255',
            'settings.badge_en' => 'sometimes|nullable|string|max:255',
            'settings.image' => 'sometimes|nullable|string|max:1000',
            'settings.image_alt_ar' => 'sometimes|nullable|string|max:255',
            'settings.image_alt_en' => 'sometimes|nullable|string|max:255',
            'settings.primary_button_ar' => 'sometimes|nullable|string|max:255',
            'settings.primary_button_en' => 'sometimes|nullable|string|max:255',
            'settings.whatsapp_button_ar' => 'sometimes|nullable|string|max:255',
            'settings.whatsapp_button_en' => 'sometimes|nullable|string|max:255',
            'settings.whatsapp_url' => 'sometimes|nullable|url|max:1000',
            'settings.minimum_amount' => 'sometimes|nullable|numeric|min:0',
            'settings.currency_ar' => 'sometimes|nullable|string|max:20',
            'settings.currency_en' => 'sometimes|nullable|string|max:20',

            'is_enabled' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        $section = $section ?? $program->pageSections()->firstOrNew([
            'section_key' => 'hero',
        ]);

        $section->fill($data);
        $section->section_key = 'hero';
        $section->save();

        return response()->json([
            'message' => 'Hero section saved successfully',
            'data' => $section->fresh(),
        ]);
    }

    private function validateSection(
        Request $request,
        Program $program,
        ?ProgramPageSection $section = null
    ): array {
        $keyRules = [
            'string',
            'max:50',
            Rule::unique('program_page_sections', 'section_key')
                ->where('program_id', $program->id)
                ->ignore($section?->id),
        ];

        $data = $request->validate([
            'section_key' => array_merge(
                $section ? ['sometimes', 'required'] : ['required'],
                $keyRules
            ),

            'title_ar' => 'sometimes|nullable|string|max:255',
            'title_en' => 'sometimes|nullable|string|max:255',
            'subtitle_ar' => 'sometimes|nullable|string|max:255',
            'subtitle_en' => 'sometimes|nullable|string|max:255',

            'content_ar' => 'sometimes|nullable|array',
            'content_en' => 'sometimes|nullable|array',
            'settings' => 'sometimes|nullable|array',

            'is_enabled' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0',

            'images' => 'sometimes|array',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',

            'svg_files' => 'sometimes|array',
            'svg_files.*' => 'file|mimetypes:image/svg+xml|max:512',

            'remove_files' => 'sometimes|array',
            'remove_files.*' => 'string|max:500',
        ]);

        if ($request->hasFile('svg_files')) {
            foreach ($request->file('svg_files') as $file) {
                $contents = file_get_contents($file->getRealPath());

                if (
                    preg_match('/<script\b/i', $contents) ||
                    preg_match('/\bon\w+\s*=/i', $contents) ||
                    preg_match('/javascript\s*:/i', $contents)
                ) {
                    throw ValidationException::withMessages([
                        'svg_files' => 'SVG contains unsafe content.',
                    ]);
                }
            }
        }

        $removeFiles = $data['remove_files'] ?? [];
        unset($data['remove_files']);

        $contentUpdates = [];

        foreach (['content_ar', 'content_en'] as $field) {
            if (isset($data[$field])) {
                $contentUpdates[$field] = $data[$field];
            }
        }

        if ($section) {
            foreach ($contentUpdates as $field => $content) {
                $this->deleteReplacedContentFiles(
                    $section->{$field} ?? [],
                    $content
                );
            }
        }

        if ($request->hasFile('images')) {
            $paths = [];

            foreach ($request->file('images') as $file) {
                $paths[] = $file->store(
                    "programs/{$program->id}/sections",
                    'public'
                );
            }

            $data['settings'] = array_merge(
                $section?->settings ?? [],
                $data['settings'] ?? [],
                ['uploaded_images' => $paths]
            );
        }

        if ($request->hasFile('svg_files')) {
            $paths = [];

            foreach ($request->file('svg_files') as $file) {
                $paths[] = $file->store(
                    "programs/{$program->id}/sections",
                    'public'
                );
            }

            $settings = array_merge(
                $section?->settings ?? [],
                $data['settings'] ?? []
            );

            $settings['uploaded_svgs'] = $paths;
            $data['settings'] = $settings;
        }

        if ($section && $removeFiles) {
            $settings = $data['settings'] ?? $section->settings ?? [];

            foreach (['uploaded_images', 'uploaded_svgs'] as $key) {
                $settings[$key] = array_values(array_diff(
                    $settings[$key] ?? [],
                    $removeFiles
                ));
            }

            $data['settings'] = $settings;

            foreach ($removeFiles as $path) {
                if (
                    str_starts_with(
                        $path,
                        "programs/{$program->id}/sections/"
                    )
                ) {
                    Storage::disk('public')->delete($path);
                }
            }
        }

        return $data;
    }

    private function deleteReplacedContentFiles(
        array $oldContent,
        array $newContent
    ): void {
        $oldPaths = $this->extractStoragePaths($oldContent);
        $newPaths = $this->extractStoragePaths($newContent);

        foreach (array_diff($oldPaths, $newPaths) as $path) {
            if (str_starts_with($path, 'programs/')) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    private function extractStoragePaths(array $content): array
    {
        $paths = [];

        array_walk_recursive($content, function ($value, $key) use (&$paths) {
            if (
                is_string($value) &&
                in_array($key, [
                    'image',
                    'image_path',
                    'svg',
                    'svg_path',
                    'file',
                ], true) &&
                str_starts_with($value, 'programs/')
            ) {
                $paths[] = $value;
            }
        });

        return array_unique($paths);
    }

    private function deleteSectionFiles(
        ProgramPageSection $section
    ): void {
        $paths = [];

        foreach (['content_ar', 'content_en'] as $field) {
            $paths = array_merge(
                $paths,
                $this->extractStoragePaths($section->{$field} ?? [])
            );
        }

        $settings = $section->settings ?? [];

        foreach (['uploaded_images', 'uploaded_svgs'] as $key) {
            $paths = array_merge($paths, $settings[$key] ?? []);
        }

        foreach (array_unique($paths) as $path) {
            if (str_starts_with(
                $path,
                "programs/{$section->program_id}/sections/"
            )) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    private function ensureSectionBelongsToProgram(
        Program $program,
        ProgramPageSection $section
    ): void {
        abort_unless(
            $section->program_id === $program->id,
            404
        );
    }
}
