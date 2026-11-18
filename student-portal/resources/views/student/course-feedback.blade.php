<section class="aos-panel" aria-labelledby="aos-title">
    <p class="eyebrow">Завершение курса</p>
    <h2 id="aos-title">Анкета обратной связи</h2>
    <p class="aos-intro">Поделитесь впечатлениями об обучении. Ваши ответы помогут нам сделать курс полезнее.</p>
    @if($enrollment?->feedback_submitted_at)
        <div class="aos-success" role="status"><strong>Спасибо за обратную связь!</strong><p>Ваш ответ сохранён. При желании его можно изменить.</p></div>
    @endif
    <form method="post" action="{{ \App\Http\Middleware\StudentPreview::url('student.course.feedback.store', $course) }}">
        @csrf
        <fieldset class="aos-rating" @disabled(! $enrollment || ($studentPreview ?? false))>
            <legend>Как вы оцениваете курс? <span aria-hidden="true">*</span></legend>
            <div class="aos-rating-options">
                @foreach(range(1, 5) as $rating)
                    <label><input type="radio" name="rating" value="{{ $rating }}" required @checked((string) old('rating', $enrollment?->feedback_rating) === (string) $rating)><span>{{ $rating }}</span></label>
                @endforeach
            </div>
            <div class="aos-scale"><span>1 — совсем не понравился</span><span>5 — очень понравился</span></div>
        </fieldset>
        <label class="aos-field">Что понравилось?<textarea name="liked" rows="4" maxlength="5000" placeholder="Темы, примеры, подача материала — что было полезно для вас" @disabled(! $enrollment || ($studentPreview ?? false))>{{ old('liked', $enrollment?->feedback_liked) }}</textarea></label>
        <label class="aos-field">Что не понравилось или можно улучшить?<textarea name="improvements" rows="4" maxlength="5000" placeholder="Чего не хватило, что было сложно или хотелось бы изменить" @disabled(! $enrollment || ($studentPreview ?? false))>{{ old('improvements', $enrollment?->feedback_improvements) }}</textarea></label>
        <p class="aos-hint">Комментарии по желанию. Ответ увидит команда курса.</p>
        <button class="btn" type="submit" @disabled(! $enrollment || ($studentPreview ?? false))>{{ $enrollment?->feedback_submitted_at ? 'Сохранить изменения' : 'Отправить отзыв' }}</button>
        @if(! $enrollment || ($studentPreview ?? false))<p class="aos-hint">Форма доступна для заполнения в режиме слушателя</p>@endif
    </form>
    <div class="aos-navigation">
        @if($course->lessons->isNotEmpty())<a href="{{ \App\Http\Middleware\StudentPreview::url('student.lesson', [$course, $course->lessons->last()]) }}">← К последнему уроку</a>@endif
        <a href="{{ auth()->user()->isStaff() && ! ($studentPreview ?? false) ? \App\Filament\Pages\CourseCatalog::getUrl(['kind' => $course->kind]) : \App\Http\Middleware\StudentPreview::url('student.home', ['list' => 1]) }}">{{ auth()->user()->isStaff() && ! ($studentPreview ?? false) ? 'К управлению курсами' : 'К моему обучению' }} →</a>
    </div>
</section>
