<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordPortalVisit
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($request->isMethod('GET') && $response->getStatusCode() === 200 && $request->user()?->role === 'student') {
            $page = match ($request->route()->getName()) {
                'student.group' => 'Группа: '.$request->route('group')->title,
                'student.apply' => 'Новая заявка на обучение',
                'student.course', 'student.lesson' => 'Курс: '.$request->route('course')->title,
                default => 'Моё обучение',
            };
            $request->user()->portalVisits()->create(['visited_at' => now(), 'page' => $page]);
        }

        return $response;
    }
}
