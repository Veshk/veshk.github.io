<?php

namespace App\Console\Commands;

use App\Models\SelfStudyCourse;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Throwable;

class ImportRecordedCourseContent extends Command
{
    protected $signature = 'courses:import-content {manifest : Private JSON package path} {--apply : Apply the verified package} {--publish : Publish imported courses and lessons}';

    protected $description = 'Import verified content without changing people, payments or access dates';

    private const FIELDS = ['title', 'module_title', 'duration_minutes', 'description', 'video_url', 'materials', 'is_published'];

    public function handle(): int
    {
        try {
            $manifest = realpath($this->argument('manifest'));
            if (! $manifest || ! is_file($manifest)) {
                throw new RuntimeException('Package not found.');
            }
            $package = json_decode(file_get_contents($manifest), true, flags: JSON_THROW_ON_ERROR);
            Validator::make($package, [
                'version' => ['required', 'integer', 'in:1'],
                'courses' => ['required', 'array', 'min:1'],
                'courses.*.slug' => ['required', 'string', 'distinct'],
                'courses.*.expected_published' => ['required', 'boolean'],
                'courses.*.lessons' => ['required', 'array', 'min:1'],
                'courses.*.lessons.*.position' => ['required', 'integer', 'min:1'],
                'courses.*.lessons.*.expected' => ['required', 'array'],
                'courses.*.lessons.*.content.title' => ['required', 'string', 'max:255'],
                'courses.*.lessons.*.content.module_title' => ['nullable', 'string', 'max:255'],
                'courses.*.lessons.*.content.duration_minutes' => ['required', 'integer', 'between:1,1440'],
                'courses.*.lessons.*.content.description' => ['nullable', 'string', 'max:10000'],
                'courses.*.lessons.*.content.video_url' => ['required', 'regex:~^https://kinescope\.io/[a-zA-Z0-9]+$~'],
                'courses.*.lessons.*.content.materials' => ['present', 'array'],
                'courses.*.lessons.*.content.materials.*.title' => ['required', 'string', 'max:255'],
                'courses.*.lessons.*.content.materials.*.filename' => ['required', 'string', 'max:255', 'regex:~^[^/\\\\\x00-\x1f]+$~'],
                'courses.*.lessons.*.content.materials.*.file' => ['required', 'regex:~^files/[a-f0-9]{64}\.(pdf|xlsx|xls|docx|pptx|zip)$~'],
            ])->validate();

            $files = [];
            foreach ($package['courses'] as &$data) {
                foreach ($data['lessons'] as &$lesson) {
                    if (array_diff(self::FIELDS, array_keys($lesson['expected']))) {
                        throw new RuntimeException('Expected lesson snapshot is incomplete.');
                    }
                    $lesson['content'] = array_intersect_key($lesson['content'], array_flip(self::FIELDS));
                    $lesson['content']['is_published'] = (bool) $this->option('publish');
                    foreach ($lesson['content']['materials'] as &$material) {
                        $source = realpath(dirname($manifest).'/'.$material['file']);
                        $hash = pathinfo($material['file'], PATHINFO_FILENAME);
                        if (! $source || ! str_starts_with($source, dirname($manifest).DIRECTORY_SEPARATOR.'files'.DIRECTORY_SEPARATOR) || ! is_file($source) || filesize($source) > 52428800 || ! hash_equals($hash, hash_file('sha256', $source))) {
                            throw new RuntimeException('Material file is missing, outside the package, too large, or has a different checksum.');
                        }
                        $path = 'course-materials/'.basename($source);
                        $files[$path] = $source;
                        $material = ['title' => $material['title'], 'filename' => $material['filename'], 'path' => $path];
                    }
                    unset($material);
                }
                unset($lesson);
            }
            unset($data);

            DB::transaction(function () use ($package, $files): void {
                $updates = [];
                $courses = [];
                foreach ($package['courses'] as $data) {
                    $course = SelfStudyCourse::where('slug', $data['slug'])->where('kind', 'recorded')->lockForUpdate()->firstOrFail();
                    $lessons = $course->lessons()->lockForUpdate()->get();
                    $positions = array_column($data['lessons'], 'position');
                    if ($lessons->count() !== count($positions) || count(array_unique($positions)) !== count($positions) || $lessons->pluck('position')->sort()->values()->all() !== collect($positions)->sort()->values()->all()) {
                        throw new RuntimeException('Lesson structure differs for '.$course->slug.'.');
                    }
                    if ($course->is_published !== (bool) $data['expected_published'] && $course->is_published !== (bool) $this->option('publish')) {
                        throw new RuntimeException('Course publication state changed: '.$course->slug.'.');
                    }
                    foreach ($data['lessons'] as $item) {
                        $lesson = $lessons->firstWhere('position', $item['position']);
                        foreach (self::FIELDS as $field) {
                            $current = $lesson->getAttribute($field);
                            if ($current !== ($item['expected'][$field] ?? null) && $current !== ($item['content'][$field] ?? null)) {
                                throw new RuntimeException('Editorial change detected: '.$course->slug.', lesson '.$item['position'].', '.$field.'.');
                            }
                        }
                        $updates[] = [$lesson, $item['content']];
                    }
                    $courses[] = $course;
                    $this->line($course->slug.': '.$lessons->count().' lessons verified.');
                }
                if (! $this->option('apply')) {
                    $this->info('Dry run: '.count($files).' files verified. Nothing changed.');

                    return;
                }
                foreach ($files as $path => $source) {
                    if (Storage::disk('local')->exists($path)) {
                        if (! hash_equals(hash_file('sha256', $source), hash_file('sha256', Storage::disk('local')->path($path)))) {
                            throw new RuntimeException('Stored material checksum mismatch.');
                        }
                    } else {
                        $stream = fopen($source, 'rb');
                        try {
                            if (! Storage::disk('local')->put($path, $stream, ['visibility' => 'private'])) {
                                throw new RuntimeException('Could not store private material.');
                            }
                        } finally {
                            fclose($stream);
                        }
                    }
                }
                foreach ($updates as [$lesson, $content]) {
                    $lesson->fill($content);
                    if ($lesson->isDirty()) {
                        $lesson->save();
                    }
                }
                foreach ($courses as $course) {
                    $course->is_published = (bool) $this->option('publish');
                    if ($course->isDirty()) {
                        $course->save();
                    }
                }
                $this->info('Imported '.count($updates).' lessons and '.count($files).' private files. People, orders and access dates unchanged.');
            });

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
