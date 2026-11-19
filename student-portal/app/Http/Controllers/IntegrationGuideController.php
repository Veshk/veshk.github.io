<?php

namespace App\Http\Controllers;

use App\Models\CourseRegistrationEvent;
use App\Models\PortalMail;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IntegrationGuideController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless(config('course_integration.enabled') && ($request->user()->isSenior() || (config('course_integration.sandbox') && $request->user()->email === config('course_integration.allowed_email'))), 403);

        return view('integrations.guide', [
            'events' => CourseRegistrationEvent::latest('id')->limit(30)->get(),
            'mails' => PortalMail::latest('id')->limit(30)->get(),
        ]);
    }
}
