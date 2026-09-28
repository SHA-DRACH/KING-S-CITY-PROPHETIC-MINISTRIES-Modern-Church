/* King's City Prophetic Ministries — public site interactions */
(function () {
  'use strict';

  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  /* ---------- Toasts ---------- */
  function toast(message, type = 'success') {
    const stack = $('#toastStack');
    if (!stack || !window.bootstrap) { return; }
    const icons = { success: 'fa-circle-check', error: 'fa-circle-exclamation', info: 'fa-circle-info' };
    const el = document.createElement('div');
    el.className = `toast kc-toast ${type}`;
    el.setAttribute('role', type === 'error' ? 'alert' : 'status');
    el.innerHTML = `<div class="toast-body"><i class="fa-solid ${icons[type] || icons.info} t-icon" aria-hidden="true"></i><div class="flex-grow-1"></div><button type="button" class="btn-close ms-2" data-bs-dismiss="toast" aria-label="Close"></button></div>`;
    el.querySelector('.flex-grow-1').textContent = message;
    stack.appendChild(el);
    const t = new bootstrap.Toast(el, { delay: type === 'error' ? 7000 : 5000 });
    el.addEventListener('hidden.bs.toast', () => el.remove());
    t.show();
  }
  window.kcToast = toast;

  document.addEventListener('DOMContentLoaded', () => {
    /* ---------- AOS (disabled for reduced motion) ---------- */
    if (window.AOS) {
      AOS.init({ duration: 900, easing: 'ease-out-cubic', once: true, offset: 80, disable: reduceMotion });
    }

    /* ---------- Flash messages from server-side redirects ---------- */
    $$('.js-flash').forEach(f => toast(f.textContent, f.dataset.type === 'error' ? 'error' : (f.dataset.type || 'success')));

    /* ---------- Sticky header ---------- */
    const header = $('#siteHeader');
    const backTop = $('.back-to-top');
    const onScroll = () => {
      const y = window.scrollY;
      header && header.classList.toggle('scrolled', y > 40);
      backTop && backTop.classList.toggle('show', y > 700);
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
    backTop && backTop.addEventListener('click', () => window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' }));

    // Close the mobile drawer when an in-page link is chosen
    $$('#mobileNav a[href*="#"]').forEach(a => a.addEventListener('click', () => {
      const oc = window.bootstrap && bootstrap.Offcanvas.getInstance($('#mobileNav'));
      oc && oc.hide();
    }));

    initHeroVideo();
    initSwipers();
    initLightbox();
    initPublicForms();
    initLoadMore();
    initSmallBits();
  });

  /* ---------- Hero background video ----------
     Sources are attached by JS so that visitors on data-saver or with reduced
     motion only download the poster; small screens get the lighter mobile file. */
  function initHeroVideo() {
    // Homepage hero plus any page banner that has its own video
    $$('video.hero-video, video[data-bg-video]').forEach(setupBgVideo);
  }

  function setupBgVideo(video) {
    const conn = navigator.connection || {};
    if (reduceMotion || conn.saveData || /(^|-)2g$/.test(conn.effectiveType || '')) {
      video.remove();
      $('.hero-pause') && $('.hero-pause').remove();
      return;
    }
    const mobileSrc = video.dataset.mobileSrc;
    if (mobileSrc && window.innerWidth < 768) {
      $$('source', video).forEach(s => s.remove());
      const s = document.createElement('source');
      s.src = mobileSrc; s.type = 'video/mp4';
      video.appendChild(s);
    } else {
      $$('source', video).forEach(s => { s.src = s.dataset.src; });
    }
    video.muted = true;
    video.playsInline = true;
    video.load();
    video.addEventListener('playing', () => video.classList.add('is-playing'));
    video.addEventListener('ended', () => { video.currentTime = 0; video.play(); });
    const tryPlay = () => { const p = video.play(); p && p.catch(() => {}); };
    tryPlay();

    // Pause when off-screen to save battery/CPU; resume when the visitor returns to the tab
    let userPaused = false;
    document.addEventListener('visibilitychange', () => { if (!document.hidden && !userPaused) { tryPlay(); } });
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(entries => entries.forEach(e => {
        if (userPaused) { return; }
        e.isIntersecting ? tryPlay() : video.pause();
      }), { threshold: .1 }).observe(video);
    }
    const btn = video.classList.contains('hero-video') ? $('.hero-pause') : null;
    btn && btn.addEventListener('click', () => {
      userPaused = !video.paused;
      if (video.paused) { tryPlay(); } else { video.pause(); }
      btn.setAttribute('aria-pressed', userPaused ? 'true' : 'false');
      btn.setAttribute('aria-label', userPaused ? 'Play background video' : 'Pause background video');
      btn.innerHTML = `<i class="fa-solid ${userPaused ? 'fa-play' : 'fa-pause'}" aria-hidden="true"></i>`;
    });
  }

  function initSwipers() {
    if (!window.Swiper) { return; }
    const common = { speed: 650, a11y: { enabled: true }, keyboard: { enabled: true } };
    if ($('.ann-swiper')) {
      new Swiper('.ann-swiper', { ...common, spaceBetween: 18, slidesPerView: 1.1, navigation: { nextEl: '.ann-next', prevEl: '.ann-prev' },
        breakpoints: { 768: { slidesPerView: 2 }, 1200: { slidesPerView: 3 } } });
    }
    if ($('.min-swiper')) {
      new Swiper('.min-swiper', { ...common, spaceBetween: 20, slidesPerView: 1.2, navigation: { nextEl: '.min-next', prevEl: '.min-prev' },
        breakpoints: { 576: { slidesPerView: 2.2 }, 992: { slidesPerView: 3 }, 1200: { slidesPerView: 4 } } });
    }
    if ($('.testimony-swiper')) {
      new Swiper('.testimony-swiper', { ...common, spaceBetween: 22, slidesPerView: 1, autoHeight: false,
        autoplay: reduceMotion ? false : { delay: 6500, disableOnInteraction: true, pauseOnMouseEnter: true },
        pagination: { el: '.testimony-swiper .swiper-pagination', clickable: true },
        breakpoints: { 768: { slidesPerView: 2 }, 1200: { slidesPerView: 3 } } });
    }
  }

  function initLightbox() {
    if (window.GLightbox && $('.glightbox')) {
      GLightbox({ selector: '.glightbox', touchNavigation: true, loop: true, autoplayVideos: true });
    }
  }

  /* ---------- AJAX public forms (prayer, testimony, giving, contact) ---------- */
  function initPublicForms() {
    $$('form[data-public-form]').forEach(form => {
      form.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        clearErrors(form);
        if (!form.checkValidity()) {
          $$(':invalid', form).forEach(el => showError(form, el.name, el.validationMessage));
          const first = $(':invalid', form); first && first.focus();
          return;
        }
        const btn = $('button[type=submit]', form);
        const original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = `<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> ${btn.dataset.loadingText || 'Sending…'}`;
        try {
          const res = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
          const data = await res.json().catch(() => ({ ok: false, message: 'Unexpected server response.' }));
          if (data.ok) {
            if (data.redirect && !data.reset) { window.location.href = data.redirect; return; }
            form.reset();
            const success = $('.form-success', form);
            if (success) {
              $('[data-success-text]', success).textContent = data.message;
              success.hidden = false;
              success.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'center' });
              setTimeout(() => { success.hidden = true; }, 12000);
            }
            toast(data.message, 'success');
          } else {
            Object.entries(data.errors || {}).forEach(([name, msg]) => showError(form, name, msg));
            toast(data.message || 'Something went wrong. Please try again.', 'error');
          }
        } catch (e) {
          toast('Network error — please check your connection and try again.', 'error');
        } finally {
          btn.disabled = false;
          btn.innerHTML = original;
        }
      });
    });
  }
  function clearErrors(form) {
    $$('.is-invalid', form).forEach(el => el.classList.remove('is-invalid'));
    $$('[data-error-for]', form).forEach(el => { el.textContent = ''; });
  }
  function showError(form, name, msg) {
    const field = form.elements[name];
    const el = field && (field.length && !field.tagName ? field[0] : field);
    el && el.classList && el.classList.add('is-invalid');
    const fb = $(`[data-error-for="${name}"]`, form);
    if (fb) { fb.textContent = msg; }
  }

  /* ---------- Sermon "Load More" ---------- */
  function initLoadMore() {
    $$('[data-load-more]').forEach(btn => {
      btn.addEventListener('click', async () => {
        const grid = $(btn.dataset.loadMore);
        const page = parseInt(btn.dataset.nextPage, 10);
        const url = new URL(window.location.href);
        url.searchParams.set('page', page);
        url.searchParams.set('partial', '1');
        btn.disabled = true;
        const label = btn.textContent;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Loading…';
        try {
          const html = await (await fetch(url, { headers: { 'X-Requested-With': 'fetch' } })).text();
          grid.insertAdjacentHTML('beforeend', html);
          window.AOS && AOS.refreshHard();
          if (page >= parseInt(btn.dataset.pages, 10)) { btn.remove(); return; }
          btn.dataset.nextPage = page + 1;
        } catch (e) {
          toast('Could not load more sermons.', 'error');
        }
        btn.disabled = false;
        btn.textContent = label;
      });
    });
  }

  function initSmallBits() {
    // Copy account numbers / links
    $$('[data-copy]').forEach(b => b.addEventListener('click', async () => {
      try { await navigator.clipboard.writeText(b.dataset.copy); toast('Copied to clipboard.', 'info'); } catch (e) { /* ignore */ }
    }));
    // Share
    $$('[data-share]').forEach(b => b.addEventListener('click', async () => {
      const data = { title: b.dataset.title || document.title, url: window.location.href };
      if (navigator.share) { try { await navigator.share(data); } catch (e) { /* cancelled */ } }
      else { try { await navigator.clipboard.writeText(data.url); toast('Link copied — share it with a friend!', 'info'); } catch (e) { /* ignore */ } }
    }));
    // Giving: quick amounts + anonymous toggle
    $$('[data-amount]').forEach(chip => chip.addEventListener('click', () => {
      const input = $('#amount');
      if (input) { input.value = chip.dataset.amount; input.focus(); }
      $$('[data-amount]').forEach(c => c.classList.toggle('active', c === chip));
    }));
    const anon = $('[data-toggle-anon]');
    if (anon) {
      const sync = () => $$('[data-anon-hide]').forEach(el => { el.hidden = anon.checked; });
      anon.addEventListener('change', sync); sync();
    }
  }
})();
