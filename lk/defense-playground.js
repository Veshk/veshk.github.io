'use strict';
(() => {
  const students = ['Анна Петрова','Иван Соловьёв','Софья Новикова'].map((name,i)=>({id:'student-'+i,name,groups:['defense-playground']}));
  const data = {block_id:'playground-v1',open:true,text:'Выберите удобное время. Для совместной защиты достаточно одной записи на двоих.',days:[4,5,11].map(day=>({date:`2026-12-${String(day).padStart(2,'0')}`,slots:Array.from({length:8},(_,i)=>{const start=600+i*45,end=start+45,time=n=>`${String(Math.floor(n/60)).padStart(2,'0')}:${String(n%60).padStart(2,'0')}`;return {id:`day-${day}-${i}`,start:time(start),end:time(end)};})}))};
  const render = () => {
    const who = document.getElementById('demo-person').value, root = document.createElement('div'); root.className='defense-booking'; root.dataset.defenseDemo='';root.dataset.defenseSource='defense-playground';root.dataset.defenseData=JSON.stringify(data);
    document.getElementById('demo-defense').replaceChildren(root);
    DefenseDemo.mount({defenseAdmin:who==='admin',studentView:false,viewedStudent:{id:who},studentAccounts:{rows:()=>students},groups:{'defense-playground':{}},closes:()=> '2099-12-31',canEditGroup:()=>true},[]);
  };
  document.getElementById('demo-person').addEventListener('change',render); render();
})();
