<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class StudentPreview
{
    public function handle(Request $request, Closure $next): Response
    {
        $requested = $request->boolean('preview') || $request->query->has('as_student');
        $student = null;
        if ($requested) {
            abort_unless($request->user()?->isStaff() && $request->isMethod('GET'), 403);
            if ($request->query->has('as_student')) {
                $id = $request->query('as_student');
                abort_unless(is_string($id) && ctype_digit($id), 404);
                $student = User::where('role', 'student')->findOrFail($id);
            }
        }
        $request->attributes->set('student_preview', $requested);
        $request->attributes->set('preview_student', $student);
        View::share('studentPreview', $requested);
        View::share('previewStudent', $student);
        View::share('portalViewer', $student ?? $request->user());

        $response = $next($request);
        if ($requested) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }

        return $response;
    }

    public static function active(): bool
    {
        return request()->attributes->get('student_preview', false);
    }

    public static function student(): ?User
    {
        return request()->attributes->get('preview_student');
    }

    public static function url(string $name, mixed $parameters = []): string
    {
        $parameters = is_array($parameters) ? $parameters : [$parameters];
        if (self::active()) {
            $parameters = array_merge($parameters, self::student() ? ['as_student' => self::student()->id] : ['preview' => 1]);
        }

        return route($name, $parameters);
    }
}
