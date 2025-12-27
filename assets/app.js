(function () {
  const burger = document.getElementById('burgerBtn');
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('overlay');
  const closeBtn = document.getElementById('closeSidebar');

  if (!burger || !sidebar || !overlay || !closeBtn) return;

  function open() {
    sidebar.classList.add('open');
    overlay.classList.add('open');
  }
  function close() {
    sidebar.classList.remove('open');
    overlay.classList.remove('open');
  }

  burger.addEventListener('click', open);
  closeBtn.addEventListener('click', close);
  overlay.addEventListener('click', close);
})();



(function () {
  const table = document.querySelector('#linesTable');
  if (!table) return;

  const addBtn = document.querySelector('#addLine');
  const debitTotalEl = document.querySelector('#debitTotal');
  const creditTotalEl = document.querySelector('#creditTotal');
  const diffEl = document.querySelector('#diffTotal');
  const submitBtn = document.querySelector('#saveEntry');

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
      row.querySelector('input[name="credit[]"]').value = '';
    }
    if (e.target.name === 'credit[]' && e.target.value) {
      const row = e.target.closest('tr');
      row.querySelector('input[name="debit[]"]').value = '';
    }
    recalc();
  });

  table.addEventListener('click', (e) => {
    if (e.target.classList.contains('removeLine')) {
      e.preventDefault();
      e.target.closest('tr').remove();
      recalc();
    }
  });

  addBtn.addEventListener('click', (e) => {
    e.preventDefault();
    const tpl = document.querySelector('#lineTemplate');
    const clone = tpl.content.cloneNode(true);
    table.querySelector('tbody').appendChild(clone);
    recalc();
  });

  recalc();
})();
