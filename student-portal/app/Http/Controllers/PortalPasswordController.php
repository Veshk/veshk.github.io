<?php

namespace App\Http\Controllers;

use App\Models\PortalMail;
use App\Models\User;
use App\Services\CourseRegistration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PortalPasswordController extends Controller
{
    public function forgot(): View
    {
        abort_unless(config('course_integration.enabled'), 404);

        return view('auth.portal-password', ['mode' => 'request']);
    }

    public function requestLink(Request $request, CourseRegistration $service): RedirectResponse
    {
        abort_unless(config('course_integration.enabled'), 404);
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:254']]);
        $email = mb_strtolower(trim($data['email']));
        $rateKey = 'portal-password:'.hash('sha256', $email);
        if ((! config('course_integration.sandbox') || $email === config('course_integration.allowed_email')) && ! RateLimiter::tooManyAttempts($rateKey, 1)) {
            RateLimiter::hit($rateKey, 300);
            DB::transaction(function () use ($email, $service): void {
                $user = User::where('email', $email)->where('role', 'student')->lockForUpdate()->first();
                if (! $user) {
                    return;
                }
                $purpose = $user->password_setup_pending ? 'setup' : 'reset';
                PortalMail::create([
                    'deduplication_key' => 'password:'.Str::uuid(), 'user_id' => $user->id, 'kind' => $purpose,
                    'payload' => ['email' => $email, 'name' => $user->name, 'url' => $service->passwordLink($user, $purpose)],
                ]);
            });
        }

        return back()->with('status', 'Если адрес зарегистрирован, мы отправим письмо со ссылкой. Проверьте почту и папку «Спам».');
    }

    public function form(string $token): View
    {
        abort_unless(config('course_integration.enabled'), 404);
        $row = DB::table('portal_password_tokens')->where('token_hash', hash('sha256', $token))->whereNull('used_at')->where('expires_at', '>', now())->first();

        return view('auth.portal-password', ['mode' => $row ? $row->purpose : 'expired', 'token' => $token]);
    }

    public function save(Request $request, string $token): RedirectResponse
    {
        abort_unless(config('course_integration.enabled'), 404);
        $data = $request->validate(['password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()]]);
        $user = DB::transaction(function () use ($token, $data): User {
            $row = DB::table('portal_password_tokens')->where('token_hash', hash('sha256', $token))->first();
            abort_unless($row, 422, 'Ссылка недействительна');
            $user = User::whereKey($row->user_id)->lockForUpdate()->firstOrFail();
            $row = DB::table('portal_password_tokens')->where('id', $row->id)->lockForUpdate()->first();
            abort_unless($row->used_at === null && now()->lt($row->expires_at), 422, 'Срок ссылки истёк');
            abort_unless($user->role === 'student' && (! config('course_integration.sandbox') || $user->email === config('course_integration.allowed_email')), 403);
            abort_if($row->purpose === 'setup' && ! $user->password_setup_pending, 422, 'Пароль уже установлен');
            $user->forceFill(['password' => $data['password'], 'password_setup_pending' => false, 'email_verified_at' => $user->email_verified_at ?? now(), 'remember_token' => Str::random(60)])->save();
            DB::table('portal_password_tokens')->where('user_id', $user->id)->whereNull('used_at')->update(['used_at' => now(), 'updated_at' => now()]);
            DB::table('sessions')->where('user_id', $user->id)->delete();

            return $user;
        });
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('student.home');
    }
}
