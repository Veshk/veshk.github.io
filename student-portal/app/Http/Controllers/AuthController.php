<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegistrationRequest;
use App\Models\StudyGroup;
use App\Models\User;
use App\Services\Admissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function registration(): View
    {
        return view('auth.register', ['groups' => StudyGroup::where('registration_open', true)->orderBy('starts_at')->get()]);
    }

    public function register(RegistrationRequest $request, Admissions $admissions): RedirectResponse
    {
        $user = DB::transaction(function () use ($request, $admissions): User {
            $user = User::create(['name' => trim($request->last_name.' '.$request->first_name.' '.$request->patronymic), 'email' => $request->email, 'password' => $request->password]);
            $admissions->submit($user, $request->filled('study_group_id') ? (int) $request->study_group_id : null, $request->message);

            return $user;
        });
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('student.home')->with('notice', 'Заявка отправлена. Администратор проверит её и откроет доступ.');
    }

    public function loginForm(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $credentials['email'] = mb_strtolower(trim($credentials['email']));
        $key = 'login:'.hash('sha256', $credentials['email'].'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['email' => 'Слишком много попыток. Попробуйте через минуту.'])->onlyInput('email');
        }
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);

            return back()->withErrors(['email' => 'Не удалось войти. Проверьте почту и пароль.'])->onlyInput('email');
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->intended($request->user()->isStaff() ? '/admin' : route('student.home'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
