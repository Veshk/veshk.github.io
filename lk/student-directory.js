/* Shared demonstration accounts and enrollments for group counts and student lists. */
'use strict';
(function (root) {
  const sampleNames = ['Анна Петрова','Иван Соловьёв','Софья Новикова','Михаил Орлов','Анастасия Белова','Дмитрий Смирнов','Екатерина Соколова','Александр Волков','Марина Кузьмина','Андрей Лебедев','Ольга Фролова','Павел Крылов'];
  const create = ({groups, sampleGroups, requests, closes, canManageGroup = () => false, canReviewUnassigned = false, staffEmails = [], canViewRequest = () => true, today = () => new Intl.DateTimeFormat('sv-SE',{timeZone:'Asia/Yekaterinburg'}).format(new Date())}) => {
    const fixtures = Object.keys(sampleGroups).map((group,i) => ({id:'student-'+i,name:sampleNames[i],email:`student-${i+1}@vesh.test`,groups:i===0&&sampleGroups.kd26&&sampleGroups.mba?['kd26','mba']:[group],visits:i%4===0?[]:[`2026-11-0${(i%5)+1}T0${(i%3)+6}:15:00Z`,'2026-10-28T09:30:00Z','2026-10-20T05:00:00Z']}));
    const statusFor = (person, groupId = '') => {
      const enrolled = person.groups.filter(id => groups[id] && (!groupId || id === groupId));
      return enrolled.length ? (enrolled.some(id => closes(groups[id]) >= today()) ? 'active' : 'closed') : ((person.pendingApplications || []).some(r => !groupId || r.group === groupId) ? 'waiting' : 'pending');
    };
    const rows = () => {
      const accounts = new Map();
      for (const person of [...fixtures,...requests.map(r => ({id:'request-'+r.id,name:r.name,email:r.email,groups:r.status==='approved'?[r.group]:[],pendingApplications:r.status==='pending'&&canViewRequest(r)?[r]:[],visits:[]}))]) {
        const key = person.email.trim().toLocaleLowerCase('en');
        const account = accounts.get(key) || {...person,groups:[],pendingApplications:[],visits:[]};
        account.groups = [...new Set([...account.groups,...person.groups])].filter(id => groups[id]);
        account.pendingApplications = [...account.pendingApplications,...(person.pendingApplications||[])];
        account.pendingGroups = [...new Set(account.pendingApplications.map(r=>r.group))].filter(id=>groups[id]);
        account.visits = [...new Set([...account.visits,...person.visits])].sort().reverse();
        accounts.set(key,account);
      }
      return [...accounts.values()].map(person => ({...person,last:person.visits[0]||'',status:statusFor(person)}));
    };
    const add = ({name,email,group='',mode='pending'}) => {
      name=String(name||'').trim();email=String(email||'').trim().toLowerCase();
      if(!name||!email||!email.includes('@'))throw Error('Укажите ФИО и корректную почту');
      if(staffEmails.some(value=>value.toLowerCase()===email))throw Error('Эта почта принадлежит сотруднику');
      if(group ? !groups[group]||!canManageGroup(group) : !canReviewUnassigned||mode==='approved')throw Error('Выберите доступную группу');
      const person=rows().find(row=>row.email.toLowerCase()===email);
      if(person?.groups.includes(group))throw Error('Слушатель уже зачислен в эту группу');
      const pending=requests.find(row=>row.email.toLowerCase()===email&&row.group===group&&row.status==='pending');
      const request=pending||{id:Math.max(0,...requests.map(row=>Number(row.id)||0))+1,name:person?.name||name,email,group,status:'pending'};
      if(!pending)requests.push(request);
      if(mode==='approved')request.status='approved';
      return request;
    };
    return {rows,add,statusFor,participants:groupId => rows().filter(person => person.groups.includes(groupId)),applicants:groupId => rows().filter(person => person.pendingGroups.includes(groupId)),belongsTo:(person,groupId)=>person.groups.includes(groupId)||person.pendingGroups.includes(groupId)};
  };
  const api = {create};
  if (typeof module === 'object' && module.exports) module.exports = api;
  root.StudentDirectory = api;
})(typeof window === 'undefined' ? globalThis : window);
