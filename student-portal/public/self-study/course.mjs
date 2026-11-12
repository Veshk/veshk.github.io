import {mountKinescope} from './lesson-player.mjs';
const form=document.querySelector('[data-progress-form]');
let busy=false;
async function saveProgress(completed) {
  if(!form||busy)return;
  busy=true;
  const status=form.querySelector('[data-progress-status]');
  try {
    const response=await fetch(form.action,{method:'POST',headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':form.querySelector('[name="_token"]').value},body:JSON.stringify({completed})});
    if(!response.ok)throw new Error('save');
    const data=await response.json();
    document.querySelector('[data-progress]').textContent=data.progress+'%';
    form.elements.completed.value=data.completed?'0':'1';
    form.querySelector('[data-complete-button]').textContent=data.completed?'Снять отметку о прохождении':'Отметить пройденным';
    document.querySelector('[data-lesson-check="'+form.dataset.lessonId+'"]').textContent=data.completed?'✓':'';
    status.textContent=data.completed?'Урок отмечен как пройденный':'Отметка снята';
  }catch{
    status.textContent='Не удалось сохранить отметку. Проверьте соединение и попробуйте ещё раз.';
  }finally{busy=false;}
}
form?.addEventListener('submit',event=>{event.preventDefault();saveProgress(form.elements.completed.value==='1');});
const iframe=document.querySelector('[data-kinescope-url]');
if(iframe)mountKinescope(iframe,()=>saveProgress(true),{onUnavailable:()=>{
  document.querySelector('[data-playback-hint]').textContent='После просмотра отметьте урок как пройденный кнопкой ниже';
}});
