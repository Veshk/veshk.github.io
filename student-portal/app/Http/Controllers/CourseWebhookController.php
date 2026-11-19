<?php

namespace App\Http\Controllers;

use App\Models\CourseRegistrationEvent;
use App\Models\SelfStudyCourse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CourseWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless(config('course_integration.enabled'), 404);
        $key = (string) config('course_integration.webhook_key');
        abort_unless(strlen($key) >= 32 && hash_equals($key, (string) $request->header('X-Portal-Key')), 401);
        if ($request->input('test') === 'test') {
            return response()->json(['ok' => true, 'test' => true]);
        }
        $data = $request->validate([
            'tranid' => ['required', 'string', 'max:160'],
            'formid' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'name' => ['required', 'string', 'max:150'],
            'Checkbox' => ['required', 'accepted'],
        ]);
        $data['email'] = mb_strtolower(trim($data['email']));
        $data['name'] = trim($data['name']);
        abort_unless(in_array($data['formid'], config('course_integration.form_ids'), true), 422);
        if (config('course_integration.sandbox')) {
            abort_unless(config('course_integration.allowed_email') !== '' && $data['email'] === config('course_integration.allowed_email'), 403);
        }
        $course = SelfStudyCourse::where('slug', config('course_integration.course_slug'))->where('kind', 'free')->where('is_published', true)->firstOrFail();
        $payload = ['email' => $data['email'], 'name' => $data['name'], 'formid' => $data['formid'], 'course_id' => $course->id, 'consent' => true];
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        $source = 'tilda:'.config('course_integration.project_id').':'.config('course_integration.page_id');
        $event = CourseRegistrationEvent::firstOrCreate(
            ['source' => $source, 'transaction_id' => $data['tranid']],
            ['payload_hash' => $hash, 'payload' => $payload],
        );
        if (! hash_equals($event->payload_hash, $hash)) {
            DB::transaction(function () use ($event): void {
                $event = CourseRegistrationEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
                $event->update(['status' => 'conflict', 'error' => 'Повторный идентификатор с другими данными']);
            });

            return response()->json(['ok' => false, 'error' => 'transaction_conflict'], 409);
        }

        return response()->json(['ok' => true, 'event_id' => $event->id]);
    }
}
