/* King's City — Admin dashboard interactions.
   All actions are also authorised server-side; this file only improves UX. */
(function () {
  'use strict';

  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const csrf = () => $('meta[name="csrf-token"]')?.content || '';
  const endpoint = () => window.location.pathname + window.location.search;

  /* ---------- Toasts ---------- */
  function toast(message, type = 'success') {
    const stack = $('#toastStack');
    if (!stack || !window.bootstrap) { alert(message); return; }
    const icons = { success: 'fa-circle-check', error: 'fa-circle-exclamation', info: 'fa-circle-info' };
    const el = document.createElement('div');
    el.className = `toast kc-toast ${type}`;
    el.setAttribute('role', type === 'error' ? 'alert' : 'status');
    el.innerHTML = `<div class="toast-body"><i class="fa-solid ${icons[type] || icons.info} t-icon" aria-hidden="true"></i><div class="flex-grow-1"></div><button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button></div>`;
    el.querySelector('.flex-grow-1').textContent = message;
    stack.appendChild(el);
    const t = new bootstrap.Toast(el, { delay: type === 'error' ? 7000 : 4000 });
    el.addEventListener('hidden.bs.toast', () => el.remove());
    t.show();
  }

  /* ---------- Confirmation dialog (returns a Promise<boolean>) ---------- */
  function confirmDialog(text, okLabel = 'Delete') {
    return new Promise(resolve => {
      const modalEl = $('#confirmModal');
      if (!modalEl) { resolve(window.confirm(text)); return; }
      $('[data-confirm-text]', modalEl).textContent = text;
      const ok = $('[data-confirm-ok]', modalEl);
      ok.textContent = okLabel;
      ok.className = 'btn ' + (/delete|remove/i.test(okLabel) ? 'btn-danger' : 'btn-navy');
      const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
      let result = false;
      const onOk = () => { result = true; modal.hide(); };
      ok.addEventListener('click', onOk, { once: true });
      modalEl.addEventListener('hidden.bs.modal', () => { ok.removeEventListener('click', onOk); resolve(result); }, { once: true });
      modal.show();
    });
  }

  /* ---------- HTTP ---------- */
  async function postForm(url, formData, onProgress) {
    if (!formData.has('_token')) { formData.append('_token', csrf()); }
    if (onProgress) {
      // XHR gives upload progress for large videos
      return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', url);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.upload.addEventListener('progress', e => e.lengthComputable && onProgress(Math.round(e.loaded / e.total * 100)));
        xhr.onload = () => { try { resolve(JSON.parse(xhr.responseText)); } catch (e) { resolve({ ok: false, message: xhr.status === 413 ? 'The file is too large for the server.' : 'Unexpected server response (' + xhr.status + ').' }); } };
        xhr.onerror = () => reject(new Error('network'));
        xhr.send(formData);
      });
    }
    const res = await fetch(url, { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-Token': csrf() } });
    return res.json().catch(() => ({ ok: false, message: res.status === 413 ? 'The file is too large for the server.' : 'Unexpected server response (' + res.status + ').' }));
  }

  function setLoading(btn, on) {
    if (!btn) { return; }
    if (on) {
      btn.dataset.original = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = `<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> ${btn.dataset.loadingText || 'Working…'}`;
    } else {
      btn.disabled = false;
      if (btn.dataset.original) { btn.innerHTML = btn.dataset.original; }
    }
  }

  function clearErrors(form) {
    $$('.is-invalid', form).forEach(el => el.classList.remove('is-invalid'));
    $$('[data-error-for]', form).forEach(el => { el.textContent = ''; });
  }
  function showErrors(form, errors) {
    let first = null;
    Object.entries(errors || {}).forEach(([name, msg]) => {
      const field = form.querySelector(`[name="${name}"], [name="${name}[]"]`);
      field && field.classList.add('is-invalid');
      first = first || field;
      const fb = form.querySelector(`[data-error-for="${name}"]`);
      if (fb) { fb.textContent = msg; }
    });
    first && first.focus();
  }

  /** Re-render the list area (#crud-table) without a full page reload. */
  async function refreshTable() {
    const box = $('#crud-table');
    if (!box) { window.location.reload(); return; }
    box.classList.add('is-loading');
    try {
      const html = await (await fetch(endpoint(), { headers: { 'X-Requested-With': 'fetch' } })).text();
      const fresh = new DOMParser().parseFromString(html, 'text/html').querySelector('#crud-table');
      if (fresh) { box.innerHTML = fresh.innerHTML; }
    } finally {
      box.classList.remove('is-loading');
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    $$('.js-flash').forEach(f => toast(f.textContent, f.dataset.type === 'error' ? 'error' : (f.dataset.type || 'success')));

    /* ---------- Sidebar (mobile) ---------- */
    const toggle = $('[data-sidebar-toggle]');
    const setSidebar = open => { document.body.classList.toggle('sidebar-open', open); toggle && toggle.setAttribute('aria-expanded', open ? 'true' : 'false'); };
    toggle && toggle.addEventListener('click', () => setSidebar(!document.body.classList.contains('sidebar-open')));
    $$('[data-sidebar-close]').forEach(el => el.addEventListener('click', () => setSidebar(false)));
    document.addEventListener('keydown', e => { if (e.key === 'Escape') { setSidebar(false); } });

    initCrud();
    initAjaxForms();
    initPostActions();
    initPermissionUi();
    initCharts();

    $$('[data-copy]').forEach(b => b.addEventListener('click', async () => { try { await navigator.clipboard.writeText(b.dataset.copy); toast('Copied to clipboard.', 'info'); } catch (e) { /* ignore */ } }));
    $$('[data-copy-target]').forEach(b => b.addEventListener('click', async () => { try { await navigator.clipboard.writeText($(b.dataset.copyTarget).value); toast('Copied.', 'info'); } catch (e) { /* ignore */ } }));
  });

  /* ---------- Generic CRUD (CrudController pages) ---------- */
  function initCrud() {
    const modalEl = $('#crudModal');
    const form = $('#crudForm');
    const modal = modalEl && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
    const singular = modalEl?.dataset.singular || 'Item';

    const resetPreviews = () => {
      $$('[data-preview-for]', form).forEach(p => { p.innerHTML = ''; });
      $$('[data-remove-for]', form).forEach(r => { r.classList.add('d-none'); r.querySelector('input').checked = false; });
      $$('[data-static]', form).forEach(s => { s.innerHTML = ''; s.closest('[class*="col-"]').classList.add('d-none'); });
    };
    const openCreate = () => {
      if (!form) { return; }
      form.reset(); clearErrors(form); resetPreviews();
      form.elements.id.value = '';
      $('#crudModalTitle').textContent = 'Add ' + singular;
      modal.show();
    };

    document.addEventListener('click', async (e) => {
      const create = e.target.closest('[data-crud-create]');
      const edit = e.target.closest('[data-crud-edit]');
      const del = e.target.closest('[data-crud-delete]');
      const tog = e.target.closest('[data-crud-toggle]');

      if (create) { openCreate(); }

      if (edit && form) {
        const url = new URL(endpoint(), window.location.origin);
        url.searchParams.set('action', 'get');
        url.searchParams.set('id', edit.dataset.crudEdit);
        edit.disabled = true;
        try {
          const data = await (await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })).json();
          if (!data.ok) { toast(data.message || 'Could not load the record.', 'error'); return; }
          form.reset(); clearErrors(form); resetPreviews();
          const rec = data.record;
          form.elements.id.value = rec.id;
          Array.from(form.elements).forEach(el => {
            if (!el.name || !(el.name in rec) || el.type === 'file' || el.type === 'hidden') { return; }
            if (el.type === 'checkbox') { el.checked = String(rec[el.name]) === '1'; }
            else if (el.type === 'time' && rec[el.name]) { el.value = String(rec[el.name]).slice(0, 5); }
            else { el.value = rec[el.name] ?? ''; }
          });
          $$('[data-static]', form).forEach(s => {
            if (rec[s.dataset.static] !== undefined) { s.innerHTML = rec[s.dataset.static] || '—'; s.closest('[class*="col-"]').classList.remove('d-none'); }
          });
          Object.entries(data.previews || {}).forEach(([name, p]) => {
            const box = form.querySelector(`[data-preview-for="${name}"]`);
            if (!box) { return; }
            box.innerHTML = p.kind === 'image' ? `<img src="${p.url}" alt="Current file">`
              : p.kind === 'video' ? `<video src="${p.url}" muted controls preload="metadata"></video>`
              : p.kind === 'audio' ? `<audio src="${p.url}" controls preload="none"></audio>`
              : `<a href="${p.url}" target="_blank" rel="noopener">Current file</a>`;
            form.querySelector(`[data-remove-for="${name}"]`)?.classList.remove('d-none');
          });
          $('#crudModalTitle').textContent = 'Edit ' + singular;
          modal.show();
        } catch (err) {
          toast('Could not load the record.', 'error');
        } finally {
          edit.disabled = false;
        }
      }

      if (del) {
        const ok = await confirmDialog(`Are you sure you want to delete “${del.dataset.label}”? This cannot be undone.`);
        if (!ok) { return; }
        const fd = new FormData(); fd.append('action', 'delete'); fd.append('id', del.dataset.crudDelete);
        const data = await postForm(endpoint(), fd);
        toast(data.message, data.ok ? 'success' : 'error');
        if (data.ok) { refreshTable(); }
      }

      if (tog) {
        const fd = new FormData(); fd.append('action', 'toggle'); fd.append('id', tog.dataset.crudToggle);
        tog.disabled = true;
        const data = await postForm(endpoint(), fd);
        toast(data.message, data.ok ? 'success' : 'error');
        if (data.ok) { refreshTable(); } else { tog.disabled = false; }
      }
    });

    if (form) {
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearErrors(form);
        const btn = form.querySelector('[type="submit"]');
        setLoading(btn, true);
        try {
          const hasFile = Array.from(form.querySelectorAll('input[type=file]')).some(i => i.files.length);
          const data = await postForm(endpoint(), new FormData(form), hasFile ? pct => { btn.innerHTML = `<span class="spinner-border spinner-border-sm"></span> Uploading ${pct}%`; } : null);
          if (data.ok) {
            modal.hide();
            toast('✓ ' + data.message, 'success');
            refreshTable();
          } else {
            showErrors(form, data.errors);
            toast(data.message || 'Please check the form.', 'error');
          }
        } catch (err) {
          toast('Network error. Please try again.', 'error');
        } finally {
          setLoading(btn, false);
        }
      });
      // Open the "Add" modal directly from dashboard quick actions (?new=1)
      if (new URLSearchParams(window.location.search).get('new') === '1' && $('[data-crud-create]')) { openCreate(); }
    }
  }

  /* ---------- Other AJAX forms (users, roles, settings, pastor, hero video…) ---------- */
  function initAjaxForms() {
    $$('form[data-ajax]').forEach(form => {
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearErrors(form);
        const btn = form.querySelector('button:not([type=button])');
        const progress = form.querySelector('[data-upload-progress]');
        const hasFile = Array.from(form.querySelectorAll('input[type=file]')).some(i => i.files.length);
        setLoading(btn, true);
        if (progress && hasFile) { progress.classList.remove('d-none'); }
        try {
          const data = await postForm(form.getAttribute('action') || endpoint(), new FormData(form), progress && hasFile ? pct => {
            progress.querySelector('.progress-bar').style.width = pct + '%';
            const t = progress.querySelector('[data-progress-text]'); if (t) { t.textContent = pct < 100 ? `Uploading… ${pct}%` : 'Processing…'; }
          } : null);
          if (data.ok) {
            toast('✓ ' + data.message, 'success');
            const m = form.closest('.modal');
            m && bootstrap.Modal.getInstance(m)?.hide();
            if (data.redirect) { setTimeout(() => { window.location.href = data.redirect; }, 700); return; }
            if (data.reload) { setTimeout(() => window.location.reload(), 800); return; }
            form.querySelectorAll('input[type=password], input[type=file]').forEach(i => { i.value = ''; });
          } else {
            showErrors(form, data.errors);
            toast(data.message || 'Please check the form.', 'error');
          }
        } catch (err) {
          toast('Network error. Please try again.', 'error');
        } finally {
          setLoading(btn, false);
          progress && setTimeout(() => progress.classList.add('d-none'), 600);
        }
      });
    });
  }

  /* ---------- Row buttons that POST an action to the current page ---------- */
  function initPostActions() {
    document.addEventListener('click', async (e) => {
      const btn = e.target.closest('[data-post-action]');
      if (!btn) { return; }
      if (btn.dataset.confirm) {
        const ok = await confirmDialog(btn.dataset.confirm, btn.dataset.confirmOk || (btn.dataset.postAction === 'delete' ? 'Delete' : 'Confirm'));
        if (!ok) { return; }
      }
      const fd = new FormData();
      fd.append('action', btn.dataset.postAction);
      fd.append('id', btn.dataset.id);
      btn.disabled = true;
      try {
        const data = await postForm(endpoint(), fd);
        if (data.temp_password) {
          const m = $('#tempPasswordModal');
          $('[data-tp-user]', m).textContent = data.user;
          $('[data-tp-value]', m).value = data.temp_password;
          bootstrap.Modal.getOrCreateInstance(m).show();
        }
        toast(data.message, data.ok ? 'success' : 'error');
        if (data.ok && !data.temp_password) { data.reload ? setTimeout(() => window.location.reload(), 700) : refreshTable(); }
      } catch (err) {
        toast('Network error. Please try again.', 'error');
      } finally {
        btn.disabled = false;
      }
    });
  }

  /* ---------- Users / roles / permissions helpers ---------- */
  function initPermissionUi() {
    const matrix = $('.perm-matrix[data-role-perms]');
    const roleSelect = $('#f_role_id');
    if (matrix && roleSelect) {
      const map = JSON.parse(matrix.dataset.rolePerms || '{}');
      const apply = () => {
        const perms = map[roleSelect.value];
        $$('input[name="permissions[]"]', matrix).forEach(cb => {
          cb.checked = perms === 'all' || (Array.isArray(perms) && perms.includes(parseInt(cb.value, 10)));
          cb.closest('.perm-item').classList.remove('state-grant', 'state-revoke');
          cb.closest('.perm-item').classList.add('state-role');
        });
      };
      roleSelect.addEventListener('change', () => { apply(); toast('Loaded default permissions for ' + roleSelect.options[roleSelect.selectedIndex].text + '.', 'info'); });
      $('[data-reset-role-perms]')?.addEventListener('click', apply);
      // Visual state as the admin customises individual permissions
      matrix.addEventListener('change', (e) => {
        const cb = e.target;
        if (cb.name !== 'permissions[]') { return; }
        const perms = map[roleSelect.value];
        const inRole = perms === 'all' || (Array.isArray(perms) && perms.includes(parseInt(cb.value, 10)));
        const item = cb.closest('.perm-item');
        item.classList.remove('state-role', 'state-grant', 'state-revoke');
        item.classList.add(cb.checked === inRole ? 'state-role' : (cb.checked ? 'state-grant' : 'state-revoke'));
      });
    }

    $$('[data-check-all]').forEach(b => b.addEventListener('click', () => {
      $$('.perm-matrix input[type=checkbox]:not(:disabled)').forEach(cb => { cb.checked = b.dataset.checkAll === '1'; });
    }));

    $('[data-generate-password]')?.addEventListener('click', () => {
      const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
      const rnd = new Uint32Array(12); crypto.getRandomValues(rnd);
      let pw = Array.from(rnd, n => chars[n % chars.length]).join('');
      pw = pw.slice(0, 10) + (2 + (rnd[0] % 8)) + '!';
      $('#f_password').value = pw; $('#f_password_confirm').value = pw;
      $('[data-generated]').textContent = 'Generated: ' + pw + ' — share it securely; the user must change it at first sign-in.';
    });

    document.addEventListener('click', async (e) => {
      const cell = e.target.closest('[data-matrix-toggle]');
      if (!cell) { return; }
      const fd = new FormData();
      fd.append('action', 'toggle'); fd.append('role_id', cell.dataset.role); fd.append('permission_id', cell.dataset.perm);
      cell.disabled = true;
      const data = await postForm(endpoint(), fd);
      cell.disabled = false;
      toast(data.message, data.ok ? 'success' : 'error');
      if (data.ok) {
        cell.classList.toggle('on', data.granted);
        cell.setAttribute('aria-pressed', data.granted ? 'true' : 'false');
        cell.innerHTML = `<i class="fa-solid ${data.granted ? 'fa-check' : 'fa-minus'}"></i>`;
      }
    });
  }

  /* ---------- Dashboard charts ---------- */
  function initCharts() {
    const src = $('#chartData');
    if (!src || !window.Chart) { return; }
    const d = JSON.parse(src.textContent);
    Chart.defaults.font.family = 'Inter, system-ui, sans-serif';
    Chart.defaults.color = '#66728A';
    const grid = { color: '#EEF1F6' };
    if (d.giving && $('#givingChart')) {
      new Chart($('#givingChart'), { type: 'bar', data: { labels: d.giving.map(r => r.label), datasets: [{ label: 'USD', data: d.giving.map(r => r.total), backgroundColor: '#F4C542', borderRadius: 8, maxBarThickness: 42 }] },
        options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid, ticks: { callback: v => '$' + v } }, x: { grid: { display: false } } } } });
    }
    if (d.prayer && $('#prayerChart')) {
      new Chart($('#prayerChart'), { type: 'doughnut', data: { labels: d.prayer.labels, datasets: [{ data: d.prayer.data, backgroundColor: ['#F4C542', '#3B82F6', '#7C3AED', '#16A34A', '#94A3B8'], borderWidth: 0 }] },
        options: { maintainAspectRatio: false, cutout: '68%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12 } } } } });
    }
    if (d.content && $('#contentChart')) {
      new Chart($('#contentChart'), { type: 'line', data: { labels: d.content.labels, datasets: [
        { label: 'Sermons', data: d.content.sermons, borderColor: '#0D315C', backgroundColor: 'rgba(13,49,92,.08)', fill: true, tension: .35 },
        { label: 'Events', data: d.content.events, borderColor: '#F4C542', backgroundColor: 'rgba(244,197,66,.12)', fill: true, tension: .35 }] },
        options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } }, scales: { y: { beginAtZero: true, grid, ticks: { precision: 0 } }, x: { grid: { display: false } } } } });
    }
  }
})();
