/* Shared demonstration accounts and enrollments for group counts and student lists. */
'use strict';
(function (root) {
  const sampleNames = ['Анна Петрова','Иван Соловьёв','Софья Новикова','Михаил Орлов','Анастасия Белова','Дмитрий Смирнов','Екатерина Соколова','Александр Волков','Марина Кузьмина','Андрей Лебедев','Ольга Фролова','Павел Крылов'];
  const create = ({groups, sampleGroups, requests, closes, today = () => new Intl.DateTimeFormat('sv-SE',{timeZone:'Asia/Yekaterinburg'}).format(new Date())}) => {
    const fixtures = Object.keys(sampleGroups).map((group,i) => ({id:'student-'+i,name:sampleNames[i],email:`student-${i+1}@vesh.test`,groups:[group],visits:i%4===0?[]:[`2026-11-0${(i%5)+1}T0${(i%3)+6}:15:00Z`,'2026-10-28T09:30:00Z','2026-10-20T05:00:00Z']}));
    const statusFor = (person, groupId = '') => {
      const enrolled = person.groups.filter(id => groups[id] && (!groupId || id === groupId));
      return enrolled.length ? (enrolled.some(id => closes(groups[id]) >= today()) ? 'active' : 'closed') : 'pending';
    };
    const rows = () => {
      const accounts = new Map();
      for (const person of [...fixtures,...requests.map(r => ({id:'request-'+r.id,name:r.name,email:r.email,groups:r.status==='approved'?[r.group]:[],visits:[]}))]) {
        const key = person.email.trim().toLocaleLowerCase('en');
        const account = accounts.get(key) || {...person,groups:[],visits:[]};
        account.groups = [...new Set([...account.groups,...person.groups])].filter(id => groups[id]);
        account.visits = [...new Set([...account.visits,...person.visits])].sort().reverse();
        accounts.set(key,account);
      }
      return [...accounts.values()].map(person => ({...person,last:person.visits[0]||'',status:statusFor(person)}));
    };
    return {rows,statusFor,participants:groupId => rows().filter(person => person.groups.includes(groupId))};
  };
  const api = {create};
  if (typeof module === 'object' && module.exports) module.exports = api;
  root.StudentDirectory = api;
})(typeof window === 'undefined' ? globalThis : window);
