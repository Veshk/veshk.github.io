const dirtyModals = new Set();

for (const eventName of ['input', 'change']) {
    document.addEventListener(eventName, (event) => {
        const modal = event.target.closest('.fi-modal[aria-modal="true"]');
        if (modal?.id) dirtyModals.add(modal.id);
    }, true);
}

function confirmClose(event, id) {
    if (!dirtyModals.has(id)) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    const modal = document.getElementById(id);
    if (!modal || modal.querySelector('.portal-discard-dialog')) return;
    const previousFocus = document.activeElement;
    const dialog = document.createElement('dialog');
    dialog.className = 'portal-discard-dialog';
    dialog.setAttribute('aria-labelledby', `${id}-discard-heading`);
    const heading = document.createElement('h2');
    heading.id = `${id}-discard-heading`;
    heading.textContent = 'Изменения не сохранены';
    const text = document.createElement('p');
    text.textContent = 'Закрыть окно и потерять внесённые правки?';
    const keep = document.createElement('button');
    keep.type = 'button';
    keep.textContent = 'Продолжить редактирование';
    keep.autofocus = true;
    const discard = document.createElement('button');
    discard.type = 'button';
    discard.className = 'portal-discard-confirm';
    discard.textContent = 'Закрыть без сохранения';
    keep.addEventListener('click', () => dialog.close());
    discard.addEventListener('click', () => {
        dirtyModals.delete(id);
        dialog.close();
        window.dispatchEvent(new CustomEvent('close-modal', { detail: { id } }));
    });
    dialog.addEventListener('close', () => {
        dialog.remove();
        previousFocus?.focus();
    });
    dialog.append(heading, text, keep, discard);
    modal.append(dialog);
    dialog.showModal();
}

window.addEventListener('close-modal', (event) => confirmClose(event, event.detail?.id), true);
document.addEventListener('click', (event) => {
    const button = event.target.closest('button');
    if (['close()', 'close(true)'].includes(button?.getAttribute('x-on:click'))) {
        confirmClose(event, button.closest('.fi-modal')?.id);
    }
}, true);

for (const eventName of ['modal-closed', 'close-modal-quietly']) {
    window.addEventListener(eventName, (event) => dirtyModals.delete(event.detail?.id));
}
window.addEventListener('profile-saved', () => dirtyModals.delete('staff-profile'));
window.addEventListener('beforeunload', (event) => {
    if (![...dirtyModals].some((id) => document.getElementById(id)?.classList.contains('fi-modal-open'))) return;
    event.preventDefault();
    event.returnValue = '';
});


// Both the editor overview and a block link reveal the same form section.
function revealCourseBlock(selected) {
    const marker = [...document.querySelectorAll('[data-course-block]')].find(el => el.dataset.courseBlock === selected);
    const target = selected === 'hero' ? document.getElementById('course-editor-hero') : marker?.closest('.fi-fo-builder-item');
    if (!target) return false;
    target.dispatchEvent(new CustomEvent('expand'));
    requestAnimationFrame(() => {
        target.scrollIntoView({block: 'start'});
        const input = target.querySelector('input:not([type="hidden"]):not([type="file"]), textarea, button');
        input?.focus({preventScroll: true});
    });
    return true;
}

window.addEventListener('course-editor-focus', event => revealCourseBlock(event.detail.block));

function focusCourseBlock() {
    const selected = new URLSearchParams(location.search).get('block');
    if (!selected) return;
    const observer = new MutationObserver(() => {
        if (revealCourseBlock(selected)) observer.disconnect();
    });
    observer.observe(document.body, {childList: true, subtree: true});
    setTimeout(() => observer.disconnect(), 10000);
    if (revealCourseBlock(selected)) observer.disconnect();
}
document.addEventListener('livewire:navigated', focusCourseBlock);
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', focusCourseBlock); else focusCourseBlock();
