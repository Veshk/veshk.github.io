<?php

namespace App\Http\Controllers;

use App\Models\SelfStudyCourse;
use App\Models\SelfStudyEnrollment;
use App\Models\SelfStudyLesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SelfStudyController extends Controller
{
    public function show(Request $request, SelfStudyCourse $course, ?SelfStudyLesson $lesson = null): View
    {
        $enrollment = $this->access($request, $course);
        $course->load(['lessons' => fn ($query) => $query->where('is_published', true)]);
        if ($lesson) {
            abort_unless($lesson->self_study_course_id === $course->id && $lesson->is_published, 404);
        } else {
            $lesson = $course->lessons->first(fn (SelfStudyLesson $item): bool => ! in_array($item->id, $enrollment?->completed_lessons ?? [])) ?? $course->lessons->first();
        }

        return view('student.self-study', compact('course', 'lesson', 'enrollment'));
    }

    public function progress(Request $request, SelfStudyCourse $course, SelfStudyLesson $lesson): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['completed' => ['required', 'boolean']]);
        $enrollment = $this->access($request, $course, true);
        abort_unless($lesson->self_study_course_id === $course->id && $lesson->is_published, 404);
        DB::transaction(function () use ($enrollment, $lesson, $data): void {
            $enrollment = SelfStudyEnrollment::whereKey($enrollment->id)->lockForUpdate()->firstOrFail();
            abort_unless($enrollment->available(), 403);
            $ids = array_diff($enrollment->completed_lessons ?? [], [$lesson->id]);
            if ($data['completed']) {
                $ids[] = $lesson->id;
            }
            $enrollment->update(['completed_lessons' => array_values(array_unique($ids))]);
        });

        return $request->expectsJson() ? response()->json(['progress' => $enrollment->fresh()->progress(), 'completed' => (bool) $data['completed']]) : redirect()->route('student.lesson', [$course, $lesson]);
    }

    public function note(Request $request, SelfStudyCourse $course, SelfStudyLesson $lesson): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:5000']]);
        $enrollment = $this->access($request, $course, true);
        abort_unless($lesson->self_study_course_id === $course->id && $lesson->is_published, 404);
        DB::transaction(function () use ($enrollment, $lesson, $data): void {
            $enrollment = SelfStudyEnrollment::whereKey($enrollment->id)->lockForUpdate()->firstOrFail();
            abort_unless($enrollment->available(), 403);
            $notes = $enrollment->notes ?? [];
            $notes[$lesson->id] = $data['note'] ?? '';
            $enrollment->update(['notes' => $notes]);
        });

        return redirect()->route('student.lesson', [$course, $lesson])->with('notice', 'Заметка сохранена');
    }

    public function material(Request $request, SelfStudyCourse $course, SelfStudyLesson $lesson, int $index): StreamedResponse
    {
        $this->access($request, $course);
        abort_unless($lesson->self_study_course_id === $course->id && $lesson->is_published, 404);
        $material = $lesson->materials[$index] ?? [];
        $path = $material['path'] ?? '';
        abort_unless(str_starts_with($path, 'course-materials/') && ! str_contains($path, '..') && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path);
    }

    private function access(Request $request, SelfStudyCourse $course, bool $studentOnly = false): ?SelfStudyEnrollment
    {
        if (! $studentOnly && $course->canManage($request->user())) {
            return null;
        }
        $enrollment = $request->user()->selfStudyEnrollments()->where('self_study_course_id', $course->id)->with('course')->first();
        abort_unless($enrollment && $enrollment->available(), 403, 'Доступ к курсу пока не открыт или уже завершён.');

        return $enrollment;
    }
}
