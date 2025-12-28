/* =========================================================
   assets/app.js
   - Menú mobile toggle
   - Acordeón del nav: SOLO 1 abierto
   - El padre es link directo, la flecha es toggle (data-nav-toggle)
   - ✅ Soporta grupos "toggle-only" (ej: Reportes):
       - Click en el label (button.nav-group-link[data-nav-toggle])
       - Click en la flecha (button.nav-group-toggle[data-nav-toggle])
   ========================================================= */

(function () {
  // ===== Debug helper =====
  const DEBUG = (localStorage.getItem('navDebug') === '1');
  const log = (...args) => { if (DEBUG) console.log('[NAV]', ...args); };
  const warn = (...args) => { if (DEBUG) console.warn('[NAV]', ...args); };

  // ===== MENU TOGGLE (no romper lo actual) =====
  (function () {
    const btn = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');

    if (!btn || !sidebar || !overlay) {
      log('MenuToggle: faltan elementos', { btn: !!btn, sidebar: !!sidebar, overlay: !!overlay });
      return;
    }

    btn.setAttribute(
      'aria-expanded',
      document.body.classList.contains('menu-open') ? 'true' : 'false'
    );

    function openMenu() {
      document.body.classList.add('menu-open');
      btn.setAttribute('aria-expanded', 'true');
      log('Menu: OPEN');
    }

    function closeMenu() {
      document.body.classList.remove('menu-open');
      btn.setAttribute('aria-expanded', 'false');
      log('Menu: CLOSE');
    }

    btn.addEventListener('click', () => {
      const opened = document.body.classList.contains('menu-open');
      opened ? closeMenu() : openMenu();
    });

    overlay.addEventListener('click', closeMenu);

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeMenu();
    });

    window.addEventListener('resize', () => {
      if (window.innerWidth >= 1024) closeMenu();
    });

    // UX: si haces click en un link del nav en mobile, cierra el menú
    document.addEventListener('click', (e) => {
      const a = e.target.closest && e.target.closest('.nav a[href]');
      if (!a) return;
      if (window.innerWidth < 1024) closeMenu();
    });
  })();


  // ===== ACCORDION NAV GROUPS (SOLO 1 ABIERTO SIEMPRE) =====
  (function () {
    const allGroups = Array.from(document.querySelectorAll('[data-nav-group]'));
    if (!allGroups.length) {
      log('Accordion: no se encontraron [data-nav-group]');
      return;
    }

    const isDetails = (el) => el && el.tagName === 'DETAILS';

    // ✅ Ahora devolvemos TODOS los toggles (label + flecha si existen)
    function getToggleEls(group) {
      const toggles = Array.from(group.querySelectorAll('[data-nav-toggle], .nav-group-toggle'));
      // filtra duplicados (por si coinciden selectores)
      return toggles.filter((el, i, arr) => arr.indexOf(el) === i);
    }

    function getPanelEl(group) {
      return (
        group.querySelector('[data-nav-panel]') ||
        group.querySelector('.nav-sub') ||
        group.querySelector('.nav-children') ||
        (isDetails(group) ? group : null)
      );
    }

    function groupLabel(group) {
      const link = group.querySelector('.nav-group-link');
      const t = link ? (link.textContent || '').trim().replace(/\s+/g,' ') : '';
      return t || group.getAttribute('data-nav-group') || '(sin label)';
    }

    // Solo los grupos que realmente tienen toggle (submenú)
    const groups = allGroups.filter(g => {
      const toggles = getToggleEls(g);
      const panel = getPanelEl(g);
      return !!toggles.length && !!panel;
    });

    function setOpen(group, open) {
      const toggles = getToggleEls(group);

      if (isDetails(group)) {
        group.open = !!open;
      } else {
        group.classList.toggle('open', !!open);
      }

      // ✅ Actualiza aria-expanded en TODOS los toggles del grupo
      toggles.forEach(btn => btn.setAttribute('aria-expanded', open ? 'true' : 'false'));

      log('setOpen:', groupLabel(group), '=>', open);
    }

    function isOpen(group) {
      return isDetails(group) ? !!group.open : group.classList.contains('open');
    }

    function closeAllExcept(exceptGroup) {
      groups.forEach((g) => {
        if (g !== exceptGroup) setOpen(g, false);
      });
    }

    // 1) Grupo del link activo (si existe)
    const activeLink = document.querySelector('.nav a.active, .nav a[aria-current="page"]');
    let activeGroup = null;
    if (activeLink) {
      const candidate = activeLink.closest('[data-nav-group]');
      // solo si ese grupo tiene toggle (si no, abrimos el primero con toggle)
      if (candidate && groups.includes(candidate)) activeGroup = candidate;
      log('Active link:', activeLink.getAttribute('href'), '=> activeGroup:', activeGroup ? groupLabel(activeGroup) : '(sin toggle)');
    }

    // 2) En carga: asegurar SOLO 1 abierto
    const opened = groups.filter(isOpen);
    log('Grupos (con toggle) abiertos al cargar:', opened.map(groupLabel));

    let initial = activeGroup || opened[0] || groups[0] || null;

    if (initial) {
      closeAllExcept(initial);
      setOpen(initial, true);
    }

    // 3) Bind clicks: SOLO 1 ABIERTO (y no permitimos “cerrar el último”)
    groups.forEach((g) => {
      const toggles = getToggleEls(g);
      const panel = getPanelEl(g);

      if (!toggles.length || !panel) {
        warn('Grupo sin toggle/panel:', groupLabel(g), { toggles: toggles.length, panel: !!panel });
        return;
      }

      // Inicializa aria-expanded si falta
      toggles.forEach(btn => {
        if (!btn.hasAttribute('aria-expanded')) {
          btn.setAttribute('aria-expanded', isOpen(g) ? 'true' : 'false');
        }
      });

      if (isDetails(g)) {
        g.addEventListener('toggle', () => {
          const nowOpen = isOpen(g);
          if (nowOpen) {
            closeAllExcept(g);
            setOpen(g, true);
          }
        });
        return;
      }

      // ✅ Bind en todos los toggles del grupo (label + flecha)
      toggles.forEach((btn) => {
        btn.addEventListener('click', (e) => {
          e.preventDefault();
          const currentlyOpen = isOpen(g);

          // Si está cerrado => abrir y cerrar los demás
          if (!currentlyOpen) {
            closeAllExcept(g);
            setOpen(g, true);
            return;
          }

          // Si ya está abierto => NO lo cierres (así siempre hay 1 abierto)
          // (si quisieras permitir cierre, aquí iría: setOpen(g,false);)
        });
      });
    });

    log('Accordion init OK. Total groups con toggle:', groups.length);
  })();


  // ===== LÍNEAS ASIENTO (tu lógica actual, SIN duplicados) =====
  (function () {
    const table = document.querySelector('#linesTable');
    if (!table) return;

    const addBtn = document.querySelector('#addLine');
    const debitTotalEl = document.querySelector('#debitTotal');
    const creditTotalEl = document.querySelector('#creditTotal');
    const diffEl = document.querySelector('#diffTotal');
    const submitBtn = document.querySelector('#saveEntry');

    if (!addBtn || !debitTotalEl || !creditTotalEl || !diffEl || !submitBtn) return;

    if (table.dataset.linesInit === '1') return;
    table.dataset.linesInit = '1';

    function recalc() {
      let d = 0, c = 0;
      document.querySelectorAll('input[name="debit[]"]').forEach(i => d += parseFloat(i.value || 0));
      document.querySelectorAll('input[name="credit[]"]').forEach(i => c += parseFloat(i.value || 0));
      const diff = (d - c);

      debitTotalEl.textContent = d.toFixed(2);
      creditTotalEl.textContent = c.toFixed(2);
      diffEl.textContent = diff.toFixed(2);

      submitBtn.disabled = !(Math.abs(diff) < 0.005 && d > 0);
    }

    table.addEventListener('input', (e) => {
      if (e.target.name === 'debit[]' && e.target.value) {
        const row = e.target.closest('tr');
        const credit = row && row.querySelector('input[name="credit[]"]');
        if (credit) credit.value = '';
      }
      if (e.target.name === 'credit[]' && e.target.value) {
        const row = e.target.closest('tr');
        const debit = row && row.querySelector('input[name="debit[]"]');
        if (debit) debit.value = '';
      }
      recalc();
    });

    table.addEventListener('click', (e) => {
      if (e.target.classList.contains('removeLine')) {
        e.preventDefault();
        const row = e.target.closest('tr');
        if (row) row.remove();
        recalc();
      }
    });

    addBtn.addEventListener('click', (e) => {
      e.preventDefault();
      const tpl = document.querySelector('#lineTemplate');
      const tbody = table.querySelector('tbody');
      if (!tpl || !tbody) return;
      const clone = tpl.content.cloneNode(true);
      tbody.appendChild(clone);
      recalc();
    });

    recalc();
  })();


  // ===== TOGGLE PASSWORD (tus SVG + accesibilidad) =====
  (function () {
    const EYE_OPEN = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12.0003 3C17.3924 3 21.8784 6.87976 22.8189 12C21.8784 17.1202 17.3924 21 12.0003 21C6.60812 21 2.12215 17.1202 1.18164 12C2.12215 6.87976 6.60812 3 12.0003 3ZM12.0003 19C16.2359 19 19.8603 16.052 20.7777 12C19.8603 7.94803 16.2359 5 12.0003 5C7.7646 5 4.14022 7.94803 3.22278 12C4.14022 16.052 7.7646 19 12.0003 19ZM12.0003 16.5C9.51498 16.5 7.50026 14.4853 7.50026 12C7.50026 9.51472 9.51498 7.5 12.0003 7.5C14.4855 7.5 16.5003 9.51472 16.5003 12C16.5003 14.4853 14.4855 16.5 12.0003 16.5ZM12.0003 14.5C13.381 14.5 14.5003 13.3807 14.5003 12C14.5003 10.6193 13.381 9.5 12.0003 9.5C10.6196 9.5 9.50026 10.6193 9.50026 12C9.50026 13.3807 10.6196 14.5 12.0003 14.5Z"></path></svg>`;
    const EYE_OFF = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M17.8827 19.2968C16.1814 20.3755 14.1638 21.0002 12.0003 21.0002C6.60812 21.0002 2.12215 17.1204 1.18164 12.0002C1.61832 9.62282 2.81932 7.5129 4.52047 5.93457L1.39366 2.80777L2.80788 1.39355L22.6069 21.1925L21.1927 22.6068L17.8827 19.2968ZM5.9356 7.3497C4.60673 8.56015 3.6378 10.1672 3.22278 12.0002C4.14022 16.0521 7.7646 19.0002 12.0003 19.0002C13.5997 19.0002 15.112 18.5798 16.4243 17.8384L14.396 15.8101C13.7023 16.2472 12.8808 16.5002 12.0003 16.5002C9.51498 16.5002 7.50026 14.4854 7.50026 12.0002C7.50026 11.1196 7.75317 10.2981 8.19031 9.60442L5.9356 7.3497ZM12.9139 14.328L9.67246 11.0866C9.5613 11.3696 9.50026 11.6777 9.50026 12.0002C9.50026 13.3809 10.6196 14.5002 12.0003 14.5002C12.3227 14.5002 12.6309 14.4391 12.9139 14.328ZM20.8068 16.5925L19.376 15.1617C20.0319 14.2268 20.5154 13.1586 20.7777 12.0002C19.8603 7.94818 16.2359 5.00016 12.0003 5.00016C11.1544 5.00016 10.3329 5.11773 9.55249 5.33818L7.97446 3.76015C9.22127 3.26959 10.5793 3.00016 12.0003 3.00016C17.3924 3.00016 21.8784 6.87992 22.8189 12.0002C22.5067 13.6998 21.8038 15.2628 20.8068 16.5925ZM11.7229 7.50857C11.8146 7.50299 11.9071 7.50016 12.0003 7.50016C14.4855 7.50016 16.5003 9.51488 16.5003 12.0002C16.5003 12.0933 16.4974 12.1858 16.4919 12.2775L11.7229 7.50857Z"></path></svg>`;

    if (document.documentElement.dataset.pwInit === '1') return;
    document.documentElement.dataset.pwInit = '1';

    document.querySelectorAll('input[type="password"][data-pw-toggle="1"]').forEach((input) => {
      if (input.dataset.pwMounted === '1') return;
      input.dataset.pwMounted = '1';

      if (!input.id) input.id = 'pw_' + Math.random().toString(16).slice(2);

      let wrap = input.closest('.input-wrap');
      if (!wrap) {
        wrap = document.createElement('div');
        wrap.className = 'input-wrap';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);
      }

      if (wrap.querySelector('.pw-toggle')) return;

      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'pw-toggle';
      btn.setAttribute('aria-controls', input.id);
      btn.setAttribute('aria-pressed', 'false');
      btn.setAttribute('title', 'Mostrar contraseña');
      btn.innerHTML = `${EYE_OFF}<span class="sr-only">Mostrar contraseña</span>`;

      function setState(show) {
        input.type = show ? 'text' : 'password';
        btn.setAttribute('aria-pressed', show ? 'true' : 'false');
        btn.setAttribute('title', show ? 'Ocultar contraseña' : 'Mostrar contraseña');
        btn.innerHTML = `${show ? EYE_OPEN : EYE_OFF}<span class="sr-only">${show ? 'Ocultar' : 'Mostrar'} contraseña</span>`;
      }

      btn.addEventListener('click', () => {
        setState(input.type === 'password');
        input.focus();
      });

      wrap.appendChild(btn);
    });
  })();

})();
