// Echipa de voluntari – comportament mic, fără biblioteci
(function () {
  function toast(msg) {
    var t = document.createElement('div');
    t.className = 'toast';
    t.setAttribute('role', 'status');
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(function () { t.remove(); }, 2200);
  }

  function copy(text) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(text).then(function () { toast('Copiat'); });
    }
    var ta = document.createElement('textarea');
    ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
    document.body.appendChild(ta); ta.select();
    try { document.execCommand('copy'); toast('Copiat'); } catch (e) { prompt('Copiază textul:', text); }
    ta.remove();
  }

  document.addEventListener('click', function (ev) {
    var c = ev.target.closest('[data-copy]');
    if (c) { ev.preventDefault(); copy(c.getAttribute('data-copy')); return; }

    var o = ev.target.closest('[data-open]');
    if (o) { ev.preventDefault(); var d = document.getElementById(o.getAttribute('data-open')); if (d && d.showModal) d.showModal(); return; }

    var x = ev.target.closest('[data-close]');
    if (x) { ev.preventDefault(); var dl = x.closest('dialog'); if (dl) dl.close(); return; }

    var ins = ev.target.closest('[data-insert]');
    if (ins) {
      ev.preventDefault();
      var ta = document.getElementById(ins.getAttribute('data-target'));
      if (!ta) return;
      var v = ins.getAttribute('data-insert');
      var s = ta.selectionStart || 0, e = ta.selectionEnd || 0;
      ta.value = ta.value.slice(0, s) + v + ta.value.slice(e);
      ta.focus(); ta.selectionStart = ta.selectionEnd = s + v.length;
    }
  });

  // confirmare înainte de acțiuni ireversibile
  document.addEventListener('submit', function (ev) {
    var f = ev.target;
    var btn = ev.submitter;
    var msg = (btn && btn.getAttribute('data-confirm')) || f.getAttribute('data-confirm');
    if (msg && !confirm(msg)) { ev.preventDefault(); return; }
    if (btn && !btn.hasAttribute('data-keep')) {
      setTimeout(function () { btn.disabled = true; }, 0);
    }
  });

  // o partidă vocală bifată bifează și poziția ei; debifarea poziției le debifează
  document.addEventListener('change', function (ev) {
    var el = ev.target;
    if (el.matches('input[data-part-of]') && el.checked) {
      var scope = el.closest('form, [data-scope]') || document;
      var pos = scope.querySelector('input[name="positions[]"][value="' + el.getAttribute('data-part-of') + '"]');
      if (pos) pos.checked = true;
    }
    if (el.matches('input[name="positions[]"]') && !el.checked) {
      var sc = el.closest('form, [data-scope]') || document;
      sc.querySelectorAll('input[data-part-of="' + el.value + '"]').forEach(function (i) { i.checked = false; });
    }
    if (el.matches('[data-autosubmit]')) el.form.submit();
    if (el.matches('[data-enables]')) { var t = document.getElementById(el.getAttribute('data-enables')); if (t) t.disabled = !el.checked; }
  });

  // pagina de așteptare se verifică singură
  var poll = document.querySelector('[data-poll]');
  if (poll) {
    setInterval(function () {
      fetch(poll.getAttribute('data-poll'), { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) { if (j.status === 'active') location.href = poll.getAttribute('data-done'); })
        .catch(function () {});
    }, 30000);
  }

  var curTab = document.querySelector('.subnav a[aria-current="page"]');
  if (curTab && curTab.scrollIntoView) curTab.scrollIntoView({ block: 'nearest', inline: 'center' });

  var dlg = document.querySelector('dialog[data-autoshow]');
  if (dlg && dlg.showModal) dlg.showModal();
})();

// ---------- cântări ----------
(function () {
  function plain(s) {
    return (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[șş]/g, 's').replace(/[țţ]/g, 't');
  }

  // player (YouTube sau MP3) într-un dialog
  var dlg = document.getElementById('player');
  if (dlg) {
    var body = dlg.querySelector('[data-player-body]');
    var titleEl = dlg.querySelector('[data-player-title]');
    document.addEventListener('click', function (ev) {
      var b = ev.target.closest('[data-play]');
      if (!b) return;
      ev.preventDefault();
      titleEl.textContent = b.getAttribute('data-title') || '';
      body.innerHTML = '';
      if (b.getAttribute('data-play') === 'yt') {
        var f = document.createElement('iframe');
        f.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(b.getAttribute('data-src')) + '?autoplay=1&rel=0&playsinline=1&origin=' + encodeURIComponent(location.origin);
        f.referrerPolicy = 'strict-origin-when-cross-origin';
        f.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
        f.allowFullscreen = true;
        f.title = titleEl.textContent;
        body.appendChild(f);
      } else {
        var a = document.createElement('audio');
        a.controls = true; a.autoplay = true; a.preload = 'auto';
        a.src = b.getAttribute('data-src');
        body.appendChild(a);
      }
      if (!dlg.open) dlg.showModal();
    });
    dlg.addEventListener('close', function () { body.innerHTML = ''; });
    dlg.addEventListener('click', function (ev) { if (ev.target === dlg) dlg.close(); });
  }

  // filtrare instantă în listă
  var list = document.querySelector('.songs');
  var search = document.querySelector('[data-song-search]');
  if (list && search) {
    var rows = Array.prototype.slice.call(list.querySelectorAll('.song-row[data-search]'));
    var show = document.querySelector('[data-song-show]');
    var count = document.querySelector('[data-count]');
    var empty = document.querySelector('[data-song-empty]');
    // filtre care se combină: mai multe cărți = oricare dintre ele; mai multe etichete = oricare; carte + etichetă = ambele
    var books = {}, labels = {};
    var store = null; try { store = window.localStorage; } catch (e) {}
    try { var saved = JSON.parse((store && store.getItem('b1_song_filters')) || '{}'); books = saved.b || {}; labels = saved.l || {}; } catch (e) {}
    function keys(o) { return Object.keys(o).filter(function (k) { return o[k]; }); }
    function sync() {
      document.querySelectorAll('[data-book-filter]').forEach(function (x) { var on = !!books[x.getAttribute('data-book-filter')]; x.classList.toggle('is-on', on); x.setAttribute('aria-pressed', on); });
      document.querySelectorAll('[data-label-filter]').forEach(function (x) { var on = !!labels[x.getAttribute('data-label-filter')]; x.classList.toggle('is-on', on); x.setAttribute('aria-pressed', on); });
      var all = document.querySelector('[data-filter-all]'); if (all) all.classList.toggle('is-on', !keys(books).length && !keys(labels).length);
      try { store && store.setItem('b1_song_filters', JSON.stringify({ b: books, l: labels })); } catch (e) {}
    }
    function apply() {
      var bs = keys(books), ls = keys(labels);
      var words = plain(search.value).split(/\s+/).filter(Boolean);
      var mode = show ? show.value : '';
      var n = 0;
      rows.forEach(function (r) {
        var hay = r.getAttribute('data-search');
        var ok = words.every(function (w) { return hay.indexOf(w) !== -1 || hay.replace(/\s+/g, '').indexOf(w) !== -1; });
        if (ok && bs.length) ok = bs.indexOf(r.getAttribute('data-book')) !== -1;
        if (ok && ls.length) { var rl = r.getAttribute('data-labels'); ok = ls.some(function (l) { return rl.indexOf(',' + l + ',') !== -1; }); }
        if (ok && mode === 'norec') ok = r.getAttribute('data-rec') === '0';
        if (ok && mode === 'nochords') ok = r.getAttribute('data-chords') === '0';
        if (ok && mode === 'nolabel') ok = r.getAttribute('data-labels') === ',,';
        r.classList.toggle('hidden', !ok);
        if (ok) n++;
      });
      if (count) count.textContent = n;
      if (empty) empty.classList.toggle('hidden', n > 0);
    }
    search.addEventListener('input', apply);
    if (show) show.addEventListener('change', apply);
    search.form.addEventListener('submit', function (ev) { ev.preventDefault(); });
    document.querySelectorAll('[data-book-filter]').forEach(function (b) {
      b.addEventListener('click', function () { var k = b.getAttribute('data-book-filter'); books[k] = !books[k]; sync(); apply(); });
    });
    document.querySelectorAll('[data-label-filter]').forEach(function (b) {
      b.addEventListener('click', function () { var k = b.getAttribute('data-label-filter'); labels[k] = !labels[k]; sync(); apply(); });
    });
    var allBtn = document.querySelector('[data-filter-all]');
    if (allBtn) allBtn.addEventListener('click', function () { books = {}; labels = {}; sync(); apply(); });
    // filtre rămase din altă dată, care nu mai există
    Object.keys(books).forEach(function (k) { if (!document.querySelector('[data-book-filter="' + k + '"]')) delete books[k]; });
    Object.keys(labels).forEach(function (k) { if (!document.querySelector('[data-label-filter="' + k + '"]')) delete labels[k]; });
    sync(); apply();
    var q = new URLSearchParams(location.search).get('q');
    if (q) { search.value = q; apply(); }
  }

  // formularul de cântare
  var addYt = document.querySelector('[data-add-yt]');
  if (addYt) {
    addYt.addEventListener('click', function () {
      var wrap = document.querySelector('[data-yt-rows]');
      var row = wrap.querySelector('.yt-row').cloneNode(true);
      row.querySelectorAll('input').forEach(function (i) { i.value = ''; });
      wrap.appendChild(row);
      row.querySelector('input[type=url]').focus();
    });
  }
  var audioIn = document.querySelector('[data-audio-input]');
  if (audioIn) {
    audioIn.addEventListener('change', function () {
      var out = document.querySelector('[data-audio-list]');
      var form = audioIn.form;
      form.querySelectorAll('input[name^="audio_duration"]').forEach(function (i) { i.remove(); });
      out.innerHTML = '';
      Array.prototype.forEach.call(audioIn.files, function (f, i) {
        var line = document.createElement('div');
        line.textContent = '♪ ' + f.name + ' · ' + (f.size / 1048576).toFixed(1).replace('.', ',') + ' MB';
        out.appendChild(line);
        var h = document.createElement('input');
        h.type = 'hidden'; h.name = 'audio_duration[' + i + ']';
        form.appendChild(h);
        var a = new Audio();
        a.preload = 'metadata';
        a.onloadedmetadata = function () { if (isFinite(a.duration)) h.value = Math.round(a.duration); URL.revokeObjectURL(a.src); };
        a.src = URL.createObjectURL(f);
      });
    });
  }
  document.querySelectorAll('.drop input[name=chords]').forEach(function (inp) {
    inp.addEventListener('change', function () {
      var t = inp.parentNode.querySelector('[data-file-name]');
      if (t && inp.files[0]) t.textContent = inp.files[0].name;
    });
  });
})();

// ---------- șabloane ----------
(function () {
  var box = document.querySelector('[data-items]');
  if (box) {
    box.addEventListener('click', function (ev) {
      var row = ev.target.closest('[data-item]');
      if (!row) return;
      var mv = ev.target.closest('[data-move]');
      if (mv) {
        if (mv.getAttribute('data-move') === '-1' && row.previousElementSibling) box.insertBefore(row, row.previousElementSibling);
        if (mv.getAttribute('data-move') === '1' && row.nextElementSibling) box.insertBefore(row.nextElementSibling, row);
      }
      if (ev.target.closest('[data-remove-item]')) row.remove();
    });
    document.querySelectorAll('[data-add-item]').forEach(function (b) {
      b.addEventListener('click', function () {
        var t = document.getElementById('item-tpl');
        var row = t.content.firstElementChild.cloneNode(true);
        var kind = b.getAttribute('data-add-item');
        row.querySelector('select').value = kind;
        var inp = row.querySelector('input');
        inp.value = kind === 'song' ? 'Cântare' : (b.getAttribute('data-title') || '');
        box.appendChild(row);
        if (!inp.value) inp.focus();
      });
    });
  }
  document.querySelectorAll('[data-slot-input]').forEach(function (i) {
    i.addEventListener('input', function () { i.closest('.slot-item').classList.toggle('on', parseInt(i.value, 10) > 0); });
  });
})();

// ---------- calendar zile indisponibile ----------
(function () {
  var cal = document.querySelector('[data-cal]');
  if (!cal) return;
  var panel = document.querySelector('[data-cal-panel]');
  var addBox = panel.querySelector('[data-panel-add]');
  var blockBox = panel.querySelector('[data-panel-block]');
  var days = Array.prototype.slice.call(cal.querySelectorAll('button[data-date]'));
  var start = null, end = null;
  function paint() {
    days.forEach(function (d) {
      var v = d.getAttribute('data-date');
      var a = start, b = end || start;
      if (a && b && a > b) { var t = a; a = b; b = t; }
      d.classList.toggle('sel', !!a && (v === a || v === b));
      d.classList.toggle('sel-mid', !!a && v > a && v < b);
    });
  }
  function fmt(v) { var d = cal.querySelector('[data-date="' + v + '"]'); return d ? d.getAttribute('data-label') : v; }
  function showAdd() {
    var a = start, b = end || start;
    if (a > b) { var t = a; a = b; b = t; }
    addBox.querySelector('[data-from]').value = a;
    addBox.querySelector('[data-to]').value = b;
    var n = Math.round((new Date(b) - new Date(a)) / 86400000) + 1;
    addBox.querySelector('[data-sel-text]').textContent = n === 1 ? 'Indisponibil pe ' + fmt(a) : 'Indisponibil ' + fmt(a) + ' – ' + fmt(b) + ' (' + n + ' zile)';
    addBox.querySelector('[data-sel-hint]').textContent = end ? 'Apasă o zi nouă ca să începi altă selecție.' : 'Apasă pe altă zi ca să alegi o perioadă.';
    var clash = days.filter(function (d) { var v = d.getAttribute('data-date'); return v >= a && v <= b && d.hasAttribute('data-serve'); })
      .map(function (d) { return d.getAttribute('data-label') + ' (' + d.getAttribute('data-serve') + ')'; });
    addBox.querySelector('[data-sel-warn]').textContent = clash.length ? 'Ești deja programat: ' + clash.join(', ') + '. Spune și „Nu pot ajunge” din Programul meu.' : '';
    blockBox.classList.add('hidden'); addBox.classList.remove('hidden'); panel.classList.remove('hidden');
  }
  function showBlock(d) {
    start = end = null; paint();
    blockBox.querySelector('[data-block-text]').textContent = 'Indisponibil: ' + d.getAttribute('data-range');
    blockBox.querySelector('[data-block-reason]').textContent = d.getAttribute('data-reason') || '';
    blockBox.querySelector('[data-unmark-date]').value = d.getAttribute('data-date');
    blockBox.querySelector('[data-unmark-btn]').textContent = 'Deblochează doar ' + d.getAttribute('data-label');
    blockBox.querySelector('[data-block-id]').value = d.getAttribute('data-block');
    addBox.classList.add('hidden'); blockBox.classList.remove('hidden'); panel.classList.remove('hidden');
  }
  cal.addEventListener('click', function (ev) {
    var d = ev.target.closest('button[data-date]');
    if (!d) return;
    var v = d.getAttribute('data-date');
    if (d.hasAttribute('data-block') && !start) { showBlock(d); return; }
    if (!start || end) { start = v; end = null; }
    else if (v === start) { end = null; }
    else { end = v; }
    paint(); showAdd();
  });
  panel.querySelectorAll('[data-cal-clear]').forEach(function (b) {
    b.addEventListener('click', function () { start = end = null; paint(); panel.classList.add('hidden'); });
  });
})();

// ---------- planificare: fereastra de alegere (tabelul lunii și pagina evenimentului) ----------
(function () {
  document.addEventListener('click', function (ev) {
    var row = ev.target.closest('[data-href]');
    if (!row || ev.target.closest('a, button, form, summary, details, input, select, label')) return;
    location.href = row.getAttribute('data-href');
  });

  var dlg = document.getElementById('pick-dlg');
  if (!dlg) return;
  var body = dlg.querySelector('[data-pick-body]');
  var hdr = { 'X-Requested-With': 'fetch' };
  var busy = false;

  // reîncarcă doar zonele marcate cu data-live, păstrând derularea paginii și a tabelului
  function refresh() {
    return fetch(location.pathname + location.search.replace(/([?&])alege=\d+&?/, '$1'), { credentials: 'same-origin' })
      .then(function (r) { return r.text(); })
      .then(function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var y = window.scrollY;
        document.querySelectorAll('[data-live]').forEach(function (cur) {
          var fresh = doc.querySelector('[data-live="' + cur.getAttribute('data-live') + '"]');
          if (!fresh) return;
          var df = cur.querySelector('form');
          if (df && df._dirty) return; // nu pierdem modificările nesalvate din ordine
          var x = cur.scrollLeft;
          cur.innerHTML = fresh.innerHTML;
          cur.scrollLeft = x;
        });
        window.scrollTo(0, y);
      });
  }
  window.b1refresh = refresh;
  function lock(on) { document.documentElement.classList.toggle('dlg-open', on); }
  function open(url) {
    body.innerHTML = '<div class="dlg-loading"><span class="spinner" aria-hidden="true"></span><span class="small muted">Se încarcă lista…</span></div>';
    if (!dlg.open) { dlg.showModal(); lock(true); }
    fetch(url, { credentials: 'same-origin', headers: hdr }).then(function (r) { return r.text(); })
      .then(function (html) { body.innerHTML = html; body.scrollTop = 0; })
      .catch(function () { body.innerHTML = '<p class="small">Nu s-a putut încărca lista. Încearcă din nou.</p>'; });
  }
  function close() { if (dlg.open) dlg.close(); }
  dlg.addEventListener('close', function () { lock(false); });
  document.addEventListener('click', function (ev) {
    var c = ev.target.closest('[data-pick]');
    if (!c) return;
    // butoanele din rând (meniu, glisare) nu deschid fereastra
    var inner = ev.target.closest('form, details, summary, [data-unswipe]');
    if (inner && c.contains(inner) && inner !== c) return;
    ev.preventDefault();
    open(c.getAttribute('data-pick'));
  });
  dlg.addEventListener('click', function (ev) {
    if (ev.target === dlg || ev.target.closest('[data-close]')) { ev.preventDefault(); close(); }
  });

  function post(f, btn, after) {
    var msg = btn && btn.getAttribute('data-confirm');
    if (msg && !confirm(msg)) return;
    if (busy) return;
    busy = true;
    if (btn) btn.disabled = true;
    var fd = new FormData(f);
    if (btn && btn.name) fd.append(btn.name, btn.value);
    fetch(f.getAttribute('action') || location.pathname, { method: 'POST', body: fd, credentials: 'same-origin', headers: hdr })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        busy = false;
        if (!j.ok) { alert((j.messages || []).join('\n') || 'Nu s-a putut salva.'); if (btn) btn.disabled = false; return; }
        if (after) after();
        if (j.messages && j.messages.length && window.b1toast) window.b1toast(j.messages[j.messages.length - 1]);
        return refresh();
      })
      .catch(function () { busy = false; alert('Nu s-a putut salva. Verifică conexiunea.'); if (btn) btn.disabled = false; });
  }
  // formularele din fereastră
  dlg.addEventListener('submit', function (ev) {
    ev.preventDefault(); ev.stopPropagation();
    post(ev.target, ev.submitter, close);
  }, true);
  // formularele din lista evenimentului (scoate persoana / poziția, adaugă loc) se salvează pe loc
  document.addEventListener('submit', function (ev) {
    var f = ev.target;
    if (!f.closest('[data-ajax-forms]')) return;
    ev.preventDefault(); ev.stopImmediatePropagation();
    var pre = new CustomEvent('b1:beforesave', { cancelable: true });
    if (!f.dispatchEvent(pre)) return;
    post(f, ev.submitter, null);
  }, true);
})();

// ---------- meniul ☰ ----------
(function () {
  var btn = document.querySelector('[data-menu-toggle]');
  var pop = document.getElementById('menu-pop');
  if (!btn || !pop) return;
  function set(open) { pop.hidden = !open; btn.setAttribute('aria-expanded', open ? 'true' : 'false'); }
  btn.addEventListener('click', function (ev) { ev.stopPropagation(); set(pop.hidden); });
  document.addEventListener('click', function (ev) { if (!pop.hidden && !pop.contains(ev.target)) set(false); });
  pop.addEventListener('click', function (ev) { if (ev.target.closest('a')) set(false); });
  document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape' && !pop.hidden) { set(false); btn.focus(); } });
  window.addEventListener('resize', function () { if (window.innerWidth >= 800) set(false); });
})();

// ---------- calendar: derulare între luni ----------
(function () {
  var sc = document.querySelector('.cal-scroll');
  if (!sc) return;
  var prev = document.querySelector('[data-cal-prev]'), next = document.querySelector('[data-cal-next]'), title = document.querySelector('[data-cal-title]');
  var cards = Array.prototype.slice.call(sc.querySelectorAll('.cal'));
  function step() { return cards.length > 1 ? cards[1].offsetLeft - cards[0].offsetLeft : sc.clientWidth; }
  function update() {
    var i = Math.round(sc.scrollLeft / step());
    var per = Math.max(1, Math.round(sc.clientWidth / step()));
    var first = cards[Math.min(i, cards.length - 1)], last = cards[Math.min(i + per - 1, cards.length - 1)];
    title.textContent = first === last ? first.getAttribute('data-month') : first.getAttribute('data-month') + ' – ' + last.getAttribute('data-month');
    prev.disabled = sc.scrollLeft <= 2;
    next.disabled = sc.scrollLeft + sc.clientWidth >= sc.scrollWidth - 2;
  }
  prev.addEventListener('click', function () { sc.scrollBy({ left: -step() }); });
  next.addEventListener('click', function () { sc.scrollBy({ left: step() }); });
  sc.addEventListener('scroll', function () { window.requestAnimationFrame(update); });
  window.addEventListener('resize', update);
  update();
})();

// ---------- 1.3.1: glisare pe rândurile evenimentului, tabelul lunii doar de la tabletă ----------
(function () {
  if (document.querySelector('[data-month-page]') && window.innerWidth < 768) location.replace(location.pathname.replace(/\/luna$/, ''));

  var sx = 0, sy = 0, row = null;
  document.addEventListener('touchstart', function (ev) {
    row = ev.target.closest('.roster-row');
    if (!row || !row.querySelector('.r-swipe')) { row = null; return; }
    sx = ev.touches[0].clientX; sy = ev.touches[0].clientY;
  }, { passive: true });
  document.addEventListener('touchend', function (ev) {
    if (!row) return;
    var dx = ev.changedTouches[0].clientX - sx, dy = ev.changedTouches[0].clientY - sy;
    if (Math.abs(dx) > 60 && Math.abs(dx) > Math.abs(dy) * 1.5) {
      document.querySelectorAll('.roster-row.swiped').forEach(function (r) { if (r !== row) r.classList.remove('swiped'); });
      row.classList.toggle('swiped', dx > 0);
      row.setAttribute('data-just-swiped', '1');
      setTimeout(function (r) { r.removeAttribute('data-just-swiped'); }.bind(null, row), 400);
    }
    row = null;
  });
  document.addEventListener('click', function (ev) {
    var x = ev.target.closest('[data-unswipe]');
    if (x) { ev.preventDefault(); ev.stopPropagation(); x.closest('.roster-row').classList.remove('swiped'); return; }
    var r = ev.target.closest('.roster-row');
    if (r && (r.hasAttribute('data-just-swiped') || r.classList.contains('swiped')) && !ev.target.closest('.r-swipe')) { ev.preventDefault(); ev.stopPropagation(); }
  }, true);

  // panoul de selecție din calendar: pe telefon apare sub calendar și se aduce în vedere
  var panel = document.querySelector('[data-cal-panel]');
  if (panel && window.innerWidth < 800) {
    new MutationObserver(function () { if (!panel.classList.contains('hidden')) panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); })
      .observe(panel, { attributes: true, attributeFilter: ['class'] });
  }
})();

// ---------- 1.3.2: mesaj scurt de confirmare ----------
window.b1toast = function (msg) {
  var t = document.querySelector('.toast');
  if (!t) { t = document.createElement('div'); t.className = 'toast'; t.setAttribute('role', 'status'); document.body.appendChild(t); }
  t.textContent = msg; t.classList.add('on');
  clearTimeout(t._h); t._h = setTimeout(function () { t.classList.remove('on'); }, 2200);
};

// ---------- 1.3.2: copiere în clipboard ----------
document.addEventListener('click', function (ev) {
  var b = ev.target.closest('[data-copy]');
  if (!b) return;
  var src = document.querySelector(b.getAttribute('data-copy'));
  if (!src) return;
  var text = src.value !== undefined ? src.value : src.textContent;
  var done = function () { var o = b.textContent; b.textContent = 'Copiat ✓'; setTimeout(function () { b.textContent = o; }, 1800); };
  if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(text).then(done, fallback);
  else fallback();
  function fallback() {
    if (src.select) { src.focus(); src.select(); }
    try { document.execCommand('copy'); done(); } catch (e) { alert('Selectează textul și copiază-l manual.'); }
  }
});

// ---------- 1.3.2: ordinea serviciului ----------
(function () {
  function form() { return document.querySelector('[data-order-form]'); }
  function dirty(on) {
    var f = form(); if (!f) return;
    f._dirty = on;
    var d = f.querySelector('[data-odirty]'); if (d) d.hidden = !on;
  }
  // ---- căutarea cântării (lista noastră, nu cea a browserului: merge la fel pe orice telefon) ----
  var SONGS = null, byId = {};
  function plain(x) { return (x || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[șş]/g, 's').replace(/[țţ]/g, 't').replace(/[^a-z0-9 ]+/g, ' ').replace(/\s+/g, ' ').trim(); }
  function songs() {
    if (SONGS) return SONGS;
    var el = document.getElementById('song-data');
    try { SONGS = el ? JSON.parse(el.textContent) : []; } catch (e) { SONGS = []; }
    SONGS.forEach(function (s) { s.pt = plain(s.t); s.pn = plain(s.n); s.px = plain(s.x); byId[s.id] = s; });
    return SONGS;
  }
  function search(q) {
    var words = plain(q).split(' ').filter(Boolean);
    if (!words.length) return [];
    var out = [];
    songs().forEach(function (s) {
      var hay = s.pt + ' ' + s.pn + ' ' + s.px, score = 0, ok = true;
      words.forEach(function (w) {
        if (!ok) return;
        if (/^\d+$/.test(w)) { // număr de cântare: potrivire exactă înainte de 30 → 300
          var nums = s.pn.split(' ');
          if (nums.indexOf(w) >= 0) score += 50; else if ((' ' + s.pn).indexOf(' ' + w) >= 0 || s.pt.indexOf(w) >= 0) score += 5; else ok = false;
          return;
        }
        if (s.pt.indexOf(w) === 0) score += 40;
        else if ((' ' + s.pt).indexOf(' ' + w) >= 0) score += 25;
        else if (s.pt.indexOf(w) >= 0) score += 15;
        else if (s.pn.indexOf(w) >= 0) score += 10;
        else if (s.px.indexOf(w) >= 0) score += 3;
        else ok = false;
      });
      if (ok) out.push([score, s]);
    });
    out.sort(function (a, b) { return b[0] - a[0] || a[1].t.localeCompare(b[1].t, 'ro'); });
    return out.slice(0, 8).map(function (x) { return x[1]; });
  }
  function esc(x) { return String(x).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function box(inp) {
    var b = inp.parentNode.querySelector('.song-sug');
    if (!b) { b = document.createElement('div'); b.className = 'song-sug'; b.setAttribute('role', 'listbox'); inp.insertAdjacentElement('afterend', b); }
    return b;
  }
  function closeSug(inp) { var b = inp.parentNode.querySelector('.song-sug'); if (b) b.remove(); inp._hits = null; inp._sel = -1; }
  function setSong(inp, s) {
    var row = inp.closest('[data-oitem]');
    var hid = row.querySelector('[data-song-id]'), key = row.querySelector('[data-song-key]');
    hid.value = s ? s.id : '';
    if (s) inp.value = s.l;
    if (key) key.placeholder = (s && s.k) || 'Ton';
    inp.classList.toggle('unmatched', !s && inp.value.trim() !== '');
  }
  function showSug(inp) {
    var hits = search(inp.value);
    inp._hits = hits; inp._sel = hits.length ? 0 : -1;
    if (!inp.value.trim()) { closeSug(inp); return; }
    var b = box(inp);
    b.innerHTML = hits.length ? hits.map(function (s, i) {
      return '<button type="button" role="option" data-sug="' + i + '"' + (i === 0 ? ' class="on"' : '') + '><strong>' + esc(s.t) + '</strong><span>' + esc([s.n, s.k].filter(Boolean).join(' · ')) + '</span></button>';
    }).join('') : '<p class="xs muted" style="padding:10px 12px;margin:0">Nicio cântare găsită.</p>';
  }
  function mark(inp) { var b = inp.parentNode.querySelector('.song-sug'); if (!b) return; b.querySelectorAll('[data-sug]').forEach(function (x, i) { x.classList.toggle('on', i === inp._sel); if (i === inp._sel) x.scrollIntoView({ block: 'nearest' }); }); }
  document.addEventListener('input', function (ev) {
    var inp = ev.target;
    if (!inp.matches || !inp.matches('[data-song-input]')) return;
    var row = inp.closest('[data-oitem]');
    row.querySelector('[data-song-id]').value = '';
    inp.classList.remove('unmatched');
    showSug(inp);
  });
  document.addEventListener('keydown', function (ev) {
    var inp = ev.target;
    if (!inp.matches || !inp.matches('[data-song-input]') || !inp._hits) return;
    if (ev.key === 'ArrowDown') { ev.preventDefault(); inp._sel = Math.min(inp._hits.length - 1, inp._sel + 1); mark(inp); }
    else if (ev.key === 'ArrowUp') { ev.preventDefault(); inp._sel = Math.max(0, inp._sel - 1); mark(inp); }
    else if (ev.key === 'Enter') { ev.preventDefault(); if (inp._hits[inp._sel]) { setSong(inp, inp._hits[inp._sel]); closeSug(inp); dirty(true); } }
    else if (ev.key === 'Escape') { closeSug(inp); }
  });
  // apăsarea pe o sugestie (pointerdown, ca să nu piardă câmpul focusul înainte)
  document.addEventListener('pointerdown', function (ev) {
    var o = ev.target.closest('[data-sug]');
    if (!o) return;
    ev.preventDefault();
    var inp = o.parentNode.previousElementSibling;
    setSong(inp, inp._hits[+o.getAttribute('data-sug')]);
    closeSug(inp); dirty(true);
  });
  document.addEventListener('focusout', function (ev) {
    var inp = ev.target;
    if (!inp.matches || !inp.matches('[data-song-input]')) return;
    setTimeout(function () {
      closeSug(inp);
      var row = inp.closest('[data-oitem]');
      if (row && !row.querySelector('[data-song-id]').value && inp.value.trim()) {
        // dacă textul e exact titlul unei cântări, o alegem; altfel rămâne marcat
        var v = plain(inp.value), hit = null;
        songs().forEach(function (s) { if (!hit && (plain(s.l) === v || s.pt === v)) hit = s; });
        setSong(inp, hit);
      }
    }, 120);
  });
  document.addEventListener('input', function (ev) { if (ev.target.type !== 'file' && ev.target.closest && ev.target.closest('[data-order-form]')) dirty(true); });
  document.addEventListener('click', function (ev) {
    var f = ev.target.closest('[data-order-form]');
    if (!f) return;
    var list = f.querySelector('[data-olist]');
    var add = ev.target.closest('[data-oadd]');
    if (add) {
      var tpl = f.querySelector('template[data-otpl="' + add.getAttribute('data-oadd') + '"]');
      var node = tpl.content.firstElementChild.cloneNode(true);
      var t = node.querySelector('.o-title');
      if (t) t.value = add.getAttribute('data-title') || '';
      list.appendChild(node);
      var empty = f.querySelector('[data-oempty]'); if (empty) empty.remove();
      (node.querySelector('[data-song-input]') || (t && !t.value ? t : null) || node.querySelector('.o-note')).focus();
      dirty(true); return;
    }
    var row = ev.target.closest('[data-oitem]');
    if (!row) return;
    var mv = ev.target.closest('[data-omove]');
    if (mv) {
      if (mv.getAttribute('data-omove') === '-1' && row.previousElementSibling) list.insertBefore(row, row.previousElementSibling);
      if (mv.getAttribute('data-omove') === '1' && row.nextElementSibling) list.insertBefore(row.nextElementSibling, row);
      mv.focus(); dirty(true); return;
    }
    if (ev.target.closest('[data-oremove]')) { row.remove(); dirty(true); }
  });
  document.addEventListener('b1:beforesave', function (ev) {
    if (!ev.target.matches('[data-order-form]')) return;
    var bad = ev.target.querySelector('[data-song-input].unmatched');
    if (bad && !confirm('„' + bad.value + '” nu e în bibliotecă și nu va fi salvată ca cântare. Continui?')) { ev.preventDefault(); return; }
    ev.target._dirty = false;
  }, true);
  // Enter în căutarea cântării nu trimite formularul
  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Enter' && ev.target.closest && ev.target.closest('[data-order-form]') && ev.target.tagName === 'INPUT') ev.preventDefault();
  });
  window.addEventListener('beforeunload', function (ev) { var f = form(); if (f && f._dirty) { ev.preventDefault(); ev.returnValue = ''; } });
})();

// ---------- 1.3.3: ascultare direct în blocul cântării (un singur player odată) ----------
(function () {
  function stop(btn) {
    var slot = btn._slot;
    if (slot) { slot.innerHTML = ''; slot.hidden = true; }
    btn.classList.remove('on');
    btn.setAttribute('aria-pressed', 'false');
  }
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-play-inline]');
    if (!b) return;
    ev.preventDefault();
    if (b.classList.contains('on')) { stop(b); return; }
    document.querySelectorAll('[data-play-inline].on').forEach(stop);
    var block = b.closest('.oitem') || b.parentNode;
    var slot = block.querySelector('[data-inline-player]');
    if (!slot) return;
    b._slot = slot;
    slot.innerHTML = '';
    if (b.getAttribute('data-play-inline') === 'yt') {
      var f = document.createElement('iframe');
      f.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(b.getAttribute('data-src')) + '?autoplay=1&rel=0&playsinline=1&origin=' + encodeURIComponent(location.origin);
      f.referrerPolicy = 'strict-origin-when-cross-origin';
      f.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
      f.allowFullscreen = true;
      f.title = 'YouTube';
      slot.appendChild(f);
    } else {
      var a = document.createElement('audio');
      a.controls = true; a.autoplay = true; a.preload = 'auto';
      a.src = b.getAttribute('data-src');
      a.addEventListener('ended', function () { stop(b); });
      slot.appendChild(a);
    }
    slot.hidden = false;
    b.classList.add('on');
    b.setAttribute('aria-pressed', 'true');
  });
})();

// ---------- 1.3.5: glisare între cântările serviciului (telefon) ----------
(function () {
  var nav = document.querySelector('[data-svc-nav]');
  if (!nav) return;
  var sx = 0, sy = 0, ok = false;
  document.addEventListener('touchstart', function (ev) {
    ok = !ev.target.closest('iframe, audio, input, textarea, select, .cal-scroll, [data-inline-player], .media-item');
    sx = ev.touches[0].clientX; sy = ev.touches[0].clientY;
  }, { passive: true });
  document.addEventListener('touchend', function (ev) {
    if (!ok) return;
    var dx = ev.changedTouches[0].clientX - sx, dy = ev.changedTouches[0].clientY - sy;
    if (Math.abs(dx) < 80 || Math.abs(dx) < Math.abs(dy) * 2) return;
    var to = dx < 0 ? nav.getAttribute('data-next') : nav.getAttribute('data-prev');
    if (to) location.href = to;
  });
})();

// ---------- 1.3.7: ghidul în imagini ----------
(function () {
  // „Mai târziu” în fereastra de bun venit: se notează fără reîncărcare
  var later = document.querySelector('[data-guide-later]');
  if (later) later.addEventListener('submit', function (ev) {
    ev.preventDefault(); ev.stopImmediatePropagation();
    fetch(later.getAttribute('action'), { method: 'POST', body: new FormData(later), credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } }).catch(function () {});
    var d = later.closest('dialog'); if (d) d.close();
  }, true);

  var tabs = document.querySelectorAll('[data-gtab]');
  if (!tabs.length) return;
  function show(k) {
    var ok = false;
    document.querySelectorAll('[data-gpanel]').forEach(function (p) { var on = p.getAttribute('data-gpanel') === k; p.hidden = !on; ok = ok || on; });
    if (!ok) return;
    tabs.forEach(function (t) { if (t.getAttribute('data-gtab') === k) t.setAttribute('aria-current', 'page'); else t.removeAttribute('aria-current'); });
  }
  tabs.forEach(function (t) { t.addEventListener('click', function (ev) { ev.preventDefault(); var k = t.getAttribute('data-gtab'); show(k); history.replaceState(null, '', '#' + k); window.scrollTo({ top: 0, behavior: 'smooth' }); }); });
  if (location.hash) show(location.hash.slice(1));

  var z = document.getElementById('gzoom');
  if (z) {
    document.addEventListener('click', function (ev) {
      var b = ev.target.closest('[data-zoom]'); if (!b) return;
      z.querySelector('img').src = b.getAttribute('data-zoom'); z.showModal(); z.scrollTop = 0;
    });
    z.addEventListener('click', function (ev) { if (ev.target === z || ev.target.closest('[data-close]')) z.close(); });
  }
})();

// ---------- 1.3.8: fișiere atașate elementelor din ordinea serviciului ----------
(function () {
  function token() { var t = document.querySelector('[data-order-form] input[name="_csrf"]') || document.querySelector('input[name="_csrf"]'); return t ? t.value : ''; }
  function after(j) {
    if (j && j.messages && j.messages.length && window.b1toast) window.b1toast(j.messages[j.messages.length - 1]);
    if (j && !j.ok) alert((j.messages || []).join('\n') || 'Nu s-a putut salva.');
    if (window.b1refresh) window.b1refresh(); else location.reload();
  }
  document.addEventListener('change', function (ev) {
    var inp = ev.target;
    if (!inp.matches || !inp.matches('[data-item-file]') || !inp.files.length) return;
    var f = inp.files[0], lbl = inp.closest('label'), span = lbl && lbl.querySelector('span');
    var form = inp.closest('[data-order-form]');
    if (form && form._dirty) { alert('Salvează mai întâi ordinea serviciului, apoi atașează fișierul.'); inp.value = ''; return; }
    if (!/\.(pptx?|pdf|key|odp)$/i.test(f.name)) { alert('Poți atașa doar PowerPoint, PDF, Keynote sau ODP.'); inp.value = ''; return; }
    var maxMb = +inp.getAttribute('data-max-mb') || 60;
    if (f.size > maxMb * 1048576) { alert('Fișierul are ' + Math.round(f.size / 1048576) + ' MB. Maximul este ' + maxMb + ' MB.'); inp.value = ''; return; }
    var fd = new FormData();
    fd.append('_csrf', token()); fd.append('action', 'file_add'); fd.append('item_uid', inp.getAttribute('data-item-file')); fd.append('file', f);
    var x = new XMLHttpRequest();
    x.open('POST', location.pathname);
    x.setRequestHeader('X-Requested-With', 'fetch');
    if (lbl) lbl.classList.add('busy');
    x.upload.onprogress = function (e) { if (e.lengthComputable && span) span.textContent = 'Se încarcă… ' + Math.round(e.loaded / e.total * 100) + '%'; };
    x.onload = function () {
      var j = null; try { j = JSON.parse(x.responseText); } catch (e) {}
      if (!j) { alert(x.status === 413 ? 'Fișierul e prea mare pentru server.' : 'Încărcarea nu a reușit. Încearcă din nou.'); if (span) span.textContent = 'Atașează fișier'; if (lbl) lbl.classList.remove('busy'); return; }
      after(j);
    };
    x.onerror = function () { alert('Încărcarea nu a reușit. Verifică conexiunea.'); if (span) span.textContent = 'Atașează fișier'; if (lbl) lbl.classList.remove('busy'); };
    x.send(fd);
  });
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-file-del]');
    if (!b) return;
    ev.preventDefault();
    if (!confirm('Ștergi fișierul „' + b.getAttribute('data-name') + '”? Nu poate fi recuperat.')) return;
    var fd = new FormData();
    fd.append('_csrf', token()); fd.append('action', 'file_delete'); fd.append('file_id', b.getAttribute('data-file-del'));
    b.disabled = true;
    fetch(location.pathname, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
      .then(function (r) { return r.json(); }).then(after).catch(function () { b.disabled = false; alert('Nu s-a putut șterge.'); });
  });
  // Setări → Fișiere: selectări rapide
  var all = document.querySelector('[data-check-all]');
  if (all) all.addEventListener('change', function () { document.querySelectorAll('[data-files-form] input[name="ids[]"]').forEach(function (c) { c.checked = all.checked; }); });
  var past = document.querySelector('[data-check-past]');
  if (past) past.addEventListener('click', function () { document.querySelectorAll('[data-files-form] input[data-past]').forEach(function (c) { c.checked = true; }); });
})();
