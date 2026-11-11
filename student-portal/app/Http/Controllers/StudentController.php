<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApplicationRequest;
use App\Models\MailPreview;
use App\Models\StudyGroup;
use App\Services\Admissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function home(Request $request): View|RedirectResponse
    {
        if ($request->user()->isStaff()) {
            return redirect('/admin');
        }
        $enrollments = $request->user()->enrollments()->with(['group.administrator', 'group.program'])->get();
        $applications = $request->user()->applications()->with('group')->latest()->get();
        $available = $enrollments->filter(fn ($enrollment) => $enrollment->group->availableTo($request->user()));
        if ($available->count() === 1 && ! $request->boolean('list') && ! $applications->contains('status', 'pending')) {
            return redirect()->route('student.group', $available->first()->group);
        }

        return view('student.home', compact('enrollments', 'applications'));
    }

    public function applicationForm(Request $request): View
    {
        abort_unless($request->user()->role === 'student', 403);

        return view('student.apply', ['groups' => StudyGroup::where('registration_open', true)->orderBy('starts_at')->get()]);
    }

    public function apply(ApplicationRequest $request, Admissions $admissions): RedirectResponse
    {
        $admissions->submit($request->user(), $request->filled('study_group_id') ? (int) $request->study_group_id : null, $request->message);

        return redirect()->route('student.home', ['list' => 1])->with('notice', 'Заявка отправлена. Ваши текущие курсы остаются доступны.');
    }

    public function group(Request $request, StudyGroup $group): View
    {
        abort_unless($request->user()->isStaff() || $group->availableTo($request->user()), 403, 'Доступ к этой группе пока не открыт или уже завершён.');
        $group->load('administrator');

        return view('student.group', compact('group'));
    }

    public function email(MailPreview $preview): Response
    {
        Gate::authorize('view', $preview->application);

        return response($preview->body)->header('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'self'");
    }
}
