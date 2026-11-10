'use strict';
window.CourseNavigation = (() => {
  const mounts = new WeakMap();
  function mount() {
    const nav = document.querySelector('.course-nav');
    const desktop = nav?.querySelector('.course-nav-inner');
    if (!desktop) return;
    mounts.get(nav)?.abort();
    const controller = new AbortController(), options = {signal:controller.signal};
    mounts.set(nav,controller);
    nav.querySelector('.course-mobile-nav')?.remove();
    const menu = document.createElement('details');
    menu.className = 'course-mobile-nav';
    menu.innerHTML = '<summary><span class="course-menu-caption">Разделы курса</span><span class="course-menu-current"></span></summary><div class="course-mobile-links"></div>';
    const list = menu.querySelector('.course-mobile-links');
    desktop.querySelectorAll('a').forEach(link => list.append(link.cloneNode(true)));
    nav.append(menu);
    nav.classList.add('has-mobile-menu');
    const summary = menu.querySelector('summary'), current = menu.querySelector('.course-menu-current');
    const links = [...nav.querySelectorAll('a[href^="#"]')];
    const sections = [...desktop.querySelectorAll('a[href^="#"]')].map(link => document.getElementById(link.hash.slice(1))).filter(Boolean);
    function select(hash) {
      const active = links.find(link => link.hash === hash) || links[0];
      if (!active) return;
      current.textContent = active.textContent;
      links.forEach(link => {
        const selected = link.hash === active.hash;
        link.classList.toggle('active',selected);
        if (selected) link.setAttribute('aria-current','location'); else link.removeAttribute('aria-current');
      });
    }
    nav.addEventListener('click',event => {
      const link = event.target.closest('a[href^="#"]');
      if (!link || !nav.contains(link)) return;
      select(link.hash);
      if (menu.contains(link)) {
        menu.open = false;
        const target = document.getElementById(link.hash.slice(1))?.querySelector('h2');
        if (target) requestAnimationFrame(() => { target.tabIndex = -1; target.focus({preventScroll:true}); });
      }
    },options);
    document.addEventListener('keydown',event => {
      if (event.key === 'Escape' && menu.open) { menu.open = false; summary.focus(); }
    },options);
    document.addEventListener('click',event => { if (!nav.contains(event.target)) menu.open = false; },options);
    window.addEventListener('hashchange',() => select(location.hash),options);
    let ticking = false;
    window.addEventListener('scroll',() => {
      if (ticking) return;
      ticking = true;
      requestAnimationFrame(() => {
        ticking = false;
        const top = nav.getBoundingClientRect().height + 24;
        const section = sections.filter(item => item.getBoundingClientRect().top <= top).pop();
        if (section) select('#'+section.id);
      });
    },{...options,passive:true});
    select(location.hash);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded',mount,{once:true}); else mount();
  return {mount};
})();
