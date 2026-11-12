<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\SelfStudyController;
use App\Http\Controllers\StudentController;
use App\Http\Middleware\RecordPortalVisit;
use App\Models\StudyGroup;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'student.home' : 'login'));
Route::middleware('guest')->group(function (): void {
    Route::get('/register', [AuthController::class, 'registration'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1')->name('register.store');
    Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1')->name('login.store');
});
Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/learning', [StudentController::class, 'home'])->middleware(RecordPortalVisit::class)->name('student.home');
    Route::get('/courses/{course}', [SelfStudyController::class, 'show'])->middleware(RecordPortalVisit::class)->name('student.course');
    Route::get('/courses/{course}/lessons/{lesson}', [SelfStudyController::class, 'show'])->middleware(RecordPortalVisit::class)->name('student.lesson');
    Route::post('/courses/{course}/lessons/{lesson}/progress', [SelfStudyController::class, 'progress'])->middleware('throttle:120,1')->name('student.lesson.progress');
    Route::post('/courses/{course}/lessons/{lesson}/note', [SelfStudyController::class, 'note'])->middleware('throttle:60,1')->name('student.lesson.note');
    Route::get('/courses/{course}/lessons/{lesson}/materials/{index}', [SelfStudyController::class, 'material'])->whereNumber('index')->name('student.lesson.material');
    Route::get('/applications/new', [StudentController::class, 'applicationForm'])->middleware(RecordPortalVisit::class)->name('student.apply');
    Route::post('/applications', [StudentController::class, 'apply'])->middleware('throttle:10,1')->name('student.apply.store');
    Route::get('/learning/{group}', [StudentController::class, 'group'])->middleware(RecordPortalVisit::class)->name('student.group');
    Route::get('/learning/{group}/materials/{index}', function (Request $request, StudyGroup $group, int $index) {
        abort_unless($request->user()->isStaff() || $group->availableTo($request->user()), 403);
        $material = $group->materials[$index] ?? null;
        abort_if(! $material, 404);
        $contents = $material['title']."\n".$group->title.' — '.$group->code."\n\nДемонстрационный материал. Здесь будет учебный файл вашей группы.\n";

        return response()->streamDownload(function () use ($contents): void {
            echo $contents;
        }, 'vesh-material.txt', ['Content-Type' => 'text/plain; charset=utf-8']);
    })->whereNumber('index')->name('student.material');
    Route::get('/mail-preview/{preview}', [StudentController::class, 'email'])->name('mail.preview');
});
$roles = [
    'student' => ['email' => 'student@vesh.test', 'name' => 'Анна Петрова', 'label' => 'Слушатель — доступ открыт', 'description' => 'Страница программы, расписание и защищённые материалы'],
    'waiting' => ['email' => 'waiting@vesh.test', 'name' => 'Илья Морозов', 'label' => 'Слушатель — ожидает одобрения', 'description' => 'Кабинет после регистрации, пока группа недоступна'],
    'admin' => ['email' => 'admin@vesh.test', 'name' => 'Дарья Маврина', 'label' => 'Администратор', 'description' => 'Свои группы, очередь заявок и обращения без группы'],
    'senior' => ['email' => 'senior@vesh.test', 'name' => 'Варвара Маврина', 'label' => 'Старший администратор', 'description' => 'Все группы и назначение ответственных сотрудников'],
];
Route::get('/demo', function () use ($roles) {
    abort_unless(app()->environment('local') && config('app.demo_mode'), 404);

    return view('demo', compact('roles'));
})->name('demo');
Route::post('/demo/{role}', function (Request $request, string $role) use ($roles) {
    abort_unless(app()->environment('local') && config('app.demo_mode'), 404);
    abort_unless(isset($roles[$role]), 404);
    Auth::login(User::where('email', $roles[$role]['email'])->firstOrFail());
    $request->session()->regenerate();

    return redirect($request->user()->isStaff() ? '/admin/enrollment-applications' : route('student.home'));
})->name('demo.login');
