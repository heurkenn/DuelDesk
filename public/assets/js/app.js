(() => {
  document.documentElement.classList.add('js');

  const setupRulesetBuilder = () => {
    const tplEl = document.getElementById('ddRulesetTemplates');
    if (!(tplEl instanceof HTMLElement)) return;

    let templates = null;
    try {
      templates = JSON.parse(tplEl.textContent || 'null');
    } catch {
      templates = null;
    }
    if (!templates || typeof templates !== 'object') return;

    const poolBody = document.querySelector('[data-ruleset-pool]');
    const addMapBtn = document.querySelector('[data-ruleset-add-map]');
    const tplMap = document.getElementById('tplRulesetMapRow');

    const addMapRow = () => {
      if (!(poolBody instanceof HTMLElement)) return;
      if (!(tplMap instanceof HTMLTemplateElement)) return;
      const node = tplMap.content.firstElementChild?.cloneNode(true);
      if (!(node instanceof HTMLElement)) return;
      poolBody.appendChild(node);
    };

    if (addMapBtn instanceof HTMLButtonElement) {
      addMapBtn.addEventListener('click', addMapRow);
    }

    const addStepBtnEls = Array.from(document.querySelectorAll('[data-ruleset-add-step]'));
    const tplStep = document.getElementById('tplRulesetStepRow');

    const addStepRow = (bo) => {
      const body = document.querySelector(`[data-ruleset-steps="${CSS.escape(String(bo))}"]`);
      if (!(body instanceof HTMLElement)) return;
      if (!(tplStep instanceof HTMLTemplateElement)) return;

      const row = tplStep.content.firstElementChild?.cloneNode(true);
      if (!(row instanceof HTMLElement)) return;

      const selects = Array.from(row.querySelectorAll('select'));
      const actionSel = selects[0];
      const actorSel = selects[1];
      if (actionSel instanceof HTMLSelectElement) {
        actionSel.name = `steps[${bo}][action][]`;
      }
      if (actorSel instanceof HTMLSelectElement) {
        actorSel.name = `steps[${bo}][actor][]`;
      }

      body.appendChild(row);
    };

    for (const btn of addStepBtnEls) {
      if (!(btn instanceof HTMLButtonElement)) continue;
      const bo = btn.getAttribute('data-ruleset-add-step') || '';
      btn.addEventListener('click', () => addStepRow(bo));
    }

    document.addEventListener('click', (e) => {
      const t = e.target instanceof Element ? e.target : null;
      if (!t) return;

      const rm = t.closest('[data-row-remove]');
      if (rm instanceof HTMLButtonElement) {
        const tr = rm.closest('tr');
        if (tr) tr.remove();
        return;
      }

      const up = t.closest('[data-row-up]');
      if (up instanceof HTMLButtonElement) {
        const tr = up.closest('tr');
        if (!tr) return;
        const prev = tr.previousElementSibling;
        if (prev) prev.before(tr);
        return;
      }

      const down = t.closest('[data-row-down]');
      if (down instanceof HTMLButtonElement) {
        const tr = down.closest('tr');
        if (!tr) return;
        const next = tr.nextElementSibling;
        if (next) next.after(tr);
        return;
      }
    });

    const tplSelect = document.getElementById('rulesetTemplateSelect');
    const tplLoad = document.getElementById('rulesetTemplateLoad');

    const clearBody = (body) => {
      if (!(body instanceof HTMLElement)) return;
      while (body.firstChild) body.removeChild(body.firstChild);
    };

    const setInput = (el, value) => {
      if (!(el instanceof HTMLInputElement)) return;
      el.value = value || '';
    };

    const loadTemplate = (id) => {
      const tpl = templates[id];
      if (!tpl || typeof tpl !== 'object') return;

      clearBody(poolBody);
      if (Array.isArray(tpl.pool)) {
        for (const m of tpl.pool) {
          if (!m || typeof m !== 'object') continue;
          addMapRow();
          const last = poolBody instanceof HTMLElement ? poolBody.lastElementChild : null;
          if (!(last instanceof HTMLElement)) continue;
          const inputs = Array.from(last.querySelectorAll('input'));
          setInput(inputs[0], String(m.key || ''));
          setInput(inputs[1], String(m.name || ''));
        }
      }

      const stepsByBo = tpl.steps_by_best_of || {};
      for (const bo of [1, 3, 5]) {
        const body = document.querySelector(`[data-ruleset-steps="${bo}"]`);
        clearBody(body);

        const seq = stepsByBo[String(bo)];
        if (!Array.isArray(seq)) continue;

        const normalizeStep = (s) => {
          if (typeof s === 'string') {
            const action = String(s || '').toLowerCase().trim();
            return { action, actor: action === 'decider' ? 'any' : 'alternate' };
          }
          if (s && typeof s === 'object') {
            const action = String(s.action || s.step || '').toLowerCase().trim();
            const actor = String(s.actor || s.by || '').toLowerCase().trim();
            return { action, actor };
          }
          return null;
        };

        const seq2 = [];
        for (const s of seq) {
          const st = normalizeStep(s);
          if (!st) continue;
          if (st.action === 'decider' || !st.action) continue;
          if (st.action !== 'ban' && st.action !== 'pick') continue;
          if (!st.actor || st.actor === 'any') st.actor = 'alternate';
          if (st.actor !== 'starter' && st.actor !== 'other' && st.actor !== 'alternate') st.actor = 'alternate';
          seq2.push(st);
        }

        for (let i = 0; i < seq2.length; i++) {
          addStepRow(bo);
          const last = body instanceof HTMLElement ? body.lastElementChild : null;
          if (!(last instanceof HTMLElement)) continue;

          const actionSel = last.querySelector('select[name^="steps"]');
          const actorSel = last.querySelector('select[name*="[actor]"]');
          if (actionSel instanceof HTMLSelectElement) {
            actionSel.value = String(seq2[i].action || 'ban');
          }
          if (actorSel instanceof HTMLSelectElement) {
            actorSel.value = String(seq2[i].actor || ((i % 2 === 0) ? 'starter' : 'other'));
          }
        }
      }
    };

    if (tplLoad instanceof HTMLButtonElement) {
      tplLoad.addEventListener('click', () => {
        const id = (tplSelect instanceof HTMLSelectElement) ? (tplSelect.value || '') : '';
        if (!id) return;
        loadTemplate(id);
      });
    }
  };

  const isVisible = (el) => {
    if (!(el instanceof Element)) return false;
    return !!(el.getClientRects().length && (el instanceof HTMLElement ? (el.offsetWidth || el.offsetHeight) : true));
  };

  const rafDebounce = (fn) => {
    let raf = 0;
    return () => {
      if (raf) window.cancelAnimationFrame(raf);
      raf = window.requestAnimationFrame(() => {
        raf = 0;
        fn();
      });
    };
  };

  const drawBracketLines = (matrixEl) => {
    if (!(matrixEl instanceof HTMLElement)) return;
    if (!isVisible(matrixEl)) return;

    const svg = matrixEl.querySelector('.bracketlines');
    if (!(svg instanceof SVGElement)) return;

    const view = matrixEl.closest('.bracketview');
    const format = (view instanceof HTMLElement) ? (view.dataset.format || '') : '';

    const matchEls = Array.from(matrixEl.querySelectorAll('.matchcard[data-match-id]'));
    if (matchEls.length === 0) return;

    const key = (b, r, p) => `${b}:${r}:${p}`;
    const map = new Map();

    let winnersRounds = 0;

    for (const el of matchEls) {
      if (!(el instanceof HTMLElement)) continue;
      const b = el.dataset.bracket || 'winners';
      const r = Number.parseInt(el.dataset.round || '0', 10) || 0;
      const p = Number.parseInt(el.dataset.pos || '0', 10) || 0;
      if (!r || !p) continue;

      map.set(key(b, r, p), el);
      if (b === 'winners' && r > winnersRounds) winnersRounds = r;
    }

    const losersRounds = (format === 'double_elim') ? ((2 * winnersRounds) - 2) : 0;
    const grand1El = map.get(key('grand', 1, 1)) || null;
    const grand2El = map.get(key('grand', 2, 1)) || null;

    const matrixRect = matrixEl.getBoundingClientRect();
    const width = Math.max(1, Math.ceil(matrixEl.scrollWidth || matrixRect.width));
    const height = Math.max(1, Math.ceil(matrixEl.scrollHeight || matrixRect.height));
    const scaleX = (matrixRect.width > 0 && width > 0) ? (matrixRect.width / width) : 1;
    const scaleY = (matrixRect.height > 0 && height > 0) ? (matrixRect.height / height) : 1;

    svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
    svg.setAttribute('width', String(width));
    svg.setAttribute('height', String(height));

    // Clear existing paths.
    while (svg.firstChild) svg.removeChild(svg.firstChild);

    // Arrowhead markers (unique per SVG to avoid id collisions).
    const uid = svg.dataset.uid || (svg.dataset.uid = String(Math.floor(Math.random() * 1e9)));
    const ns = 'http://www.w3.org/2000/svg';
    const arrowWinId = `dd-arrow-win-${uid}`;
    const arrowLosId = `dd-arrow-los-${uid}`;

    const mkArrow = (id, color, opacity = null) => {
      const marker = document.createElementNS(ns, 'marker');
      marker.setAttribute('id', id);
      marker.setAttribute('viewBox', '0 0 10 10');
      marker.setAttribute('refX', '9');
      marker.setAttribute('refY', '5');
      marker.setAttribute('markerWidth', '6');
      marker.setAttribute('markerHeight', '6');
      marker.setAttribute('orient', 'auto');
      marker.setAttribute('markerUnits', 'strokeWidth');

      const path = document.createElementNS(ns, 'path');
      path.setAttribute('d', 'M 0 0 L 10 5 L 0 10 z');
      path.setAttribute('fill', color);
      if (opacity !== null) path.setAttribute('fill-opacity', String(opacity));
      marker.appendChild(path);
      return marker;
    };

    const defs = document.createElementNS(ns, 'defs');
    defs.appendChild(mkArrow(arrowWinId, '#60a5fa', 0.30));
    defs.appendChild(mkArrow(arrowLosId, '#e5e7eb', 0.18));
    svg.appendChild(defs);

    const markerUrlFor = (klass) => {
      if (klass === 'line--win') return `url(#${arrowWinId})`;
      if (klass === 'line--los') return `url(#${arrowLosId})`;
      return '';
    };

    const EDGE_PAD = 2; // Avoid lines bleeding under semi-transparent match cards.
    const pt = (el, side) => {
      const r = el.getBoundingClientRect();
      const xPx = side === 'right'
        ? ((r.right - matrixRect.left) + EDGE_PAD)
        : ((r.left - matrixRect.left) - EDGE_PAD);
      const yPx = (r.top - matrixRect.top) + (r.height / 2);
      return { x: xPx / scaleX, y: yPx / scaleY };
    };

    const pathElbow = (from, to, klass, fromKey, toKey) => {
      const s = pt(from, 'right');
      const e = pt(to, 'left');

      const dx = e.x - s.x;
      const minBend = 26;
      const midX = (Math.abs(dx) < (2 * minBend))
        ? (s.x + (dx / 2))
        : (s.x + (dx >= 0 ? Math.max(minBend, dx / 2) : -Math.max(minBend, (-dx) / 2)));

      const d = `M ${s.x.toFixed(2)} ${s.y.toFixed(2)} H ${midX.toFixed(2)} V ${e.y.toFixed(2)} H ${e.x.toFixed(2)}`;
      const p = document.createElementNS('http://www.w3.org/2000/svg', 'path');
      p.setAttribute('d', d);
      p.setAttribute('class', `line ${klass}`);
      const marker = markerUrlFor(klass);
      if (marker) p.setAttribute('marker-end', marker);
      if (fromKey) p.dataset.from = fromKey;
      if (toKey) p.dataset.to = toKey;
      svg.appendChild(p);
    };

    const connect = (fromBracket, fromRound, fromPos, toBracket, toRound, toPos, klass) => {
      const fromKey = key(fromBracket, fromRound, fromPos);
      const toKey = key(toBracket, toRound, toPos);
      const from = map.get(fromKey);
      const to = map.get(toKey);
      if (!(from instanceof HTMLElement) || !(to instanceof HTMLElement)) return;
      pathElbow(from, to, klass, fromKey, toKey);
    };

    // Winners bracket connections.
    if (winnersRounds > 0) {
      for (let r = 1; r <= winnersRounds; r++) {
        const matchesInRound = 2 ** (winnersRounds - r);
        for (let p = 1; p <= matchesInRound; p++) {
          if (r < winnersRounds) {
            connect('winners', r, p, 'winners', r + 1, Math.ceil(p / 2), 'line--win');
          } else if (format === 'double_elim' && grand1El) {
            connect('winners', r, p, 'grand', 1, 1, 'line--win');
          }

          if (format === 'double_elim' && losersRounds > 0) {
            // Loser drop from winners into losers.
            const dropRound = (r === 1) ? 1 : ((2 * r) - 2);
            const dropPos = (r === 1) ? Math.ceil(p / 2) : p;
            if (dropRound >= 1 && dropRound <= losersRounds) {
              connect('winners', r, p, 'losers', dropRound, dropPos, 'line--drop');
            }
          }
        }
      }
    }

    // Losers bracket connections.
    if (format === 'double_elim' && losersRounds > 0) {
      for (let r = 1; r <= losersRounds; r++) {
        // matchesInRound = bracketSize / 2^(ceil(r/2)+1). Using winnersRounds as log2(bracketSize).
        const phase = Math.floor((r + 1) / 2); // ceil(r/2)
        const exp = phase + 1;
        const matchesInRound = 2 ** Math.max(0, winnersRounds - exp);

        for (let p = 1; p <= matchesInRound; p++) {
          if (r < losersRounds) {
            const nextRound = r + 1;
            const nextPos = (r % 2 === 1) ? p : Math.ceil(p / 2);
            connect('losers', r, p, 'losers', nextRound, nextPos, 'line--los');
          } else if (grand1El) {
            connect('losers', r, p, 'grand', 1, 1, 'line--los');
          }
        }
      }
    }

    // Grand final reset (GF2) connection, if present.
    if (format === 'double_elim' && grand1El && grand2El) {
      connect('grand', 1, 1, 'grand', 2, 1, 'line--win');
    }
  };

  const setupBracketInteractivity = (matrixEl) => {
    if (!(matrixEl instanceof HTMLElement)) return;
    if (matrixEl.dataset.bracketInteractive === '1') return;
    matrixEl.dataset.bracketInteractive = '1';

    const svg = matrixEl.querySelector('.bracketlines');
    if (!(svg instanceof SVGElement)) return;

    const esc = (value) => {
      if (window.CSS && typeof window.CSS.escape === 'function') return window.CSS.escape(value);
      return value.replace(/[^a-zA-Z0-9_:-]/g, '\\$&');
    };

    const clear = () => {
      for (const p of Array.from(svg.querySelectorAll('.line.is-active'))) {
        p.classList.remove('is-active');
      }
      for (const el of Array.from(matrixEl.querySelectorAll('.matchcard.is-target'))) {
        el.classList.remove('is-target');
      }
    };

    const activate = (fromKey) => {
      clear();
      if (!fromKey) return;

      const paths = Array.from(svg.querySelectorAll(`.line[data-from="${esc(fromKey)}"]`));
      for (const p of paths) {
        p.classList.add('is-active');
        const toKey = p.getAttribute('data-to') || '';
        if (!toKey) continue;

        const target = matrixEl.querySelector(`.matchcard[data-key="${esc(toKey)}"]`);
        if (target instanceof HTMLElement) target.classList.add('is-target');
      }
    };

    const onHover = (e) => {
      const card = e.target instanceof Element ? e.target.closest('.matchcard[data-key]') : null;
      if (!(card instanceof HTMLElement) || !matrixEl.contains(card)) return;
      const fromKey = card.dataset.key || '';
      activate(fromKey);
    };

    matrixEl.addEventListener('mouseover', onHover);
    matrixEl.addEventListener('focusin', onHover);
    matrixEl.addEventListener('mouseleave', clear);
    matrixEl.addEventListener('focusout', () => {
      // Defer so document.activeElement is updated.
      window.setTimeout(() => {
        if (!matrixEl.contains(document.activeElement)) clear();
      }, 0);
    });
  };

  const setupBrackets = () => {
    const matrices = Array.from(document.querySelectorAll('.bracketview__matrix'));
    for (const matrix of matrices) {
      if (!(matrix instanceof HTMLElement)) continue;

      const schedule = rafDebounce(() => drawBracketLines(matrix));
      if (isVisible(matrix)) schedule();
      setupBracketInteractivity(matrix);

      if ('ResizeObserver' in window) {
        const ro = new ResizeObserver(() => schedule());
        ro.observe(matrix);
      } else {
        window.addEventListener('resize', schedule);
      }
    }
  };

  const setupBracketZoomPan = () => {
    const panel = document.querySelector('[data-tpanel="bracket"]');
    if (!(panel instanceof HTMLElement)) return;

    const matrix = panel.querySelector('.bracketview__matrix');
    const scroll = panel.querySelector('.bracketview__scroll');
    if (!(matrix instanceof HTMLElement) || !(scroll instanceof HTMLElement)) return;

    const label = panel.querySelector('[data-bracket-zoom-label]');
    const btns = Array.from(panel.querySelectorAll('button[data-bracket-zoom]')).filter((b) => b instanceof HTMLButtonElement);
    if (btns.length === 0) return;

    const clamp = (v, min, max) => Math.max(min, Math.min(max, v));
    const MIN_ZOOM = 0.55;
    const MAX_ZOOM = 1.0;
    const STEP = 0.1;

    let zoom = 1;

    const apply = (next) => {
      zoom = clamp(next, MIN_ZOOM, MAX_ZOOM);
      matrix.style.transformOrigin = '0 0';
      matrix.style.transform = `scale(${zoom})`;

      if (label instanceof HTMLElement) {
        label.textContent = `${Math.round(zoom * 100)}%`;
      }

      if (window.DuelDesk && typeof window.DuelDesk.redrawBrackets === 'function') {
        window.DuelDesk.redrawBrackets();
      }
    };

    const center = () => {
      const visibleW = scroll.clientWidth / zoom;
      const visibleH = scroll.clientHeight / zoom;
      const maxLeft = Math.max(0, (matrix.scrollWidth || 0) - visibleW);
      const maxTop = Math.max(0, (matrix.scrollHeight || 0) - visibleH);
      scroll.scrollLeft = clamp(((matrix.scrollWidth - visibleW) / 2), 0, maxLeft);
      scroll.scrollTop = clamp(((matrix.scrollHeight - visibleH) / 2), 0, maxTop);
    };

    const centerOnElement = (el) => {
      if (!(el instanceof HTMLElement)) return;

      const mRect = matrix.getBoundingClientRect();
      const eRect = el.getBoundingClientRect();
      if (mRect.width <= 0 || mRect.height <= 0) return;

      const visibleW = scroll.clientWidth / zoom;
      const visibleH = scroll.clientHeight / zoom;
      const maxLeft = Math.max(0, (matrix.scrollWidth || 0) - visibleW);
      const maxTop = Math.max(0, (matrix.scrollHeight || 0) - visibleH);

      const localX = (eRect.left - mRect.left) / zoom;
      const localY = (eRect.top - mRect.top) / zoom;
      const centerX = localX + ((eRect.width / zoom) / 2);
      const centerY = localY + ((eRect.height / zoom) / 2);

      scroll.scrollLeft = clamp(centerX - (visibleW / 2), 0, maxLeft);
      scroll.scrollTop = clamp(centerY - (visibleH / 2), 0, maxTop);
    };

    const centerOnCurrent = () => {
      const cards = Array.from(matrix.querySelectorAll('.matchcard[data-match-id]'));
      if (cards.length === 0) return;

      const brOrder = { winners: 0, losers: 1, grand: 2 };

      const candidates = cards
        .filter((el) => el instanceof HTMLElement)
        .filter((el) => {
          const st = el.dataset.status || '';
          if (st === 'confirmed' || st === 'void') return false;
          const a = el.dataset.aName || '';
          const b = el.dataset.bName || '';
          if (a === 'TBD' || b === 'TBD') return false;
          if (a === 'BYE' || b === 'BYE') return false;
          return true;
        })
        .sort((a, b) => {
          const ab = a.dataset.bracket || 'winners';
          const bb = b.dataset.bracket || 'winners';
          const ao = brOrder[ab] ?? 9;
          const bo = brOrder[bb] ?? 9;
          if (ao !== bo) return ao - bo;

          const ar = Number.parseInt(a.dataset.round || '0', 10) || 0;
          const br = Number.parseInt(b.dataset.round || '0', 10) || 0;
          if (ar !== br) return ar - br;

          const ap = Number.parseInt(a.dataset.pos || '0', 10) || 0;
          const bp = Number.parseInt(b.dataset.pos || '0', 10) || 0;
          return ap - bp;
        });

      const target = candidates[0] || cards[0];
      if (target instanceof HTMLElement) centerOnElement(target);
    };

    const fit = () => {
      const pad = 24;
      const w = Math.max(1, scroll.clientWidth - pad);
      const ratio = w / Math.max(1, matrix.scrollWidth || 1);
      apply(ratio);
      center();
    };

    panel.addEventListener('click', (e) => {
      const btn = e.target instanceof Element ? e.target.closest('button[data-bracket-zoom]') : null;
      if (!(btn instanceof HTMLButtonElement)) return;
      const action = btn.dataset.bracketZoom || '';

      if (action === 'out') apply(zoom - STEP);
      else if (action === 'in') apply(zoom + STEP);
      else if (action === 'reset') apply(1);
      else if (action === 'fit') fit();
      else if (action === 'center') center();
      else if (action === 'current') centerOnCurrent();
    });

    // Drag-to-pan (avoid interfering with match card clicks).
    let panning = false;
    let startX = 0;
    let startY = 0;
    let startLeft = 0;
    let startTop = 0;

    const isInteractiveTarget = (target) => {
      if (!(target instanceof Element)) return false;
      return !!target.closest('a, button, input, select, textarea, .matchcard');
    };

    scroll.addEventListener('pointerdown', (e) => {
      if (!(e instanceof PointerEvent)) return;
      if (e.button !== 0) return;
      if (isInteractiveTarget(e.target)) return;

      panning = true;
      startX = e.clientX;
      startY = e.clientY;
      startLeft = scroll.scrollLeft;
      startTop = scroll.scrollTop;
      scroll.classList.add('is-panning');
      scroll.setPointerCapture(e.pointerId);
    });

    scroll.addEventListener('pointermove', (e) => {
      if (!(e instanceof PointerEvent)) return;
      if (!panning) return;
      const dx = (e.clientX - startX) / zoom;
      const dy = (e.clientY - startY) / zoom;
      scroll.scrollLeft = startLeft - dx;
      scroll.scrollTop = startTop - dy;
    });

    const endPan = (e) => {
      if (!(e instanceof PointerEvent)) return;
      if (!panning) return;
      panning = false;
      scroll.classList.remove('is-panning');
      try { scroll.releasePointerCapture(e.pointerId); } catch {}
    };

    scroll.addEventListener('pointerup', endPan);
    scroll.addEventListener('pointercancel', endPan);

    // Initial.
    apply(1);
  };

  const setupTournamentPanels = () => {
    const bar = document.querySelector('[data-tournament-tabs]');
    if (!(bar instanceof HTMLElement)) return;

    const tabEls = Array.from(bar.querySelectorAll('button[data-tab]')).filter((el) => el instanceof HTMLButtonElement);
    if (tabEls.length === 0) return;

    const panelById = new Map();
    const getPanel = (id) => {
      if (panelById.has(id)) return panelById.get(id) || null;
      const panel = document.querySelector(`[data-tpanel="${id}"]`);
      panelById.set(id, panel instanceof HTMLElement ? panel : null);
      return panelById.get(id) || null;
    };

    const known = new Set(tabEls.map((t) => t.dataset.tab || '').filter(Boolean));

    const setHash = (id) => {
      const url = new URL(window.location.href);
      url.hash = (id && id !== 'registrations') ? `#${id}` : '';
      window.history.replaceState(null, '', url);
    };

    const activate = (id, { updateHash = true } = {}) => {
      if (!known.has(id)) return;

      for (const t of tabEls) {
        const isActive = (t.dataset.tab || '') === id;
        t.classList.toggle('is-active', isActive);
        t.setAttribute('aria-selected', isActive ? 'true' : 'false');
        t.tabIndex = isActive ? 0 : -1;
      }

      for (const pid of known) {
        const panel = getPanel(pid);
        if (!(panel instanceof HTMLElement)) continue;
        const isActive = pid === id;
        if (isActive) {
          panel.removeAttribute('hidden');
        } else {
          panel.setAttribute('hidden', '');
        }
      }

      if (updateHash) setHash(id);

      if (id === 'bracket') {
        window.requestAnimationFrame(() => {
          if (window.DuelDesk && typeof window.DuelDesk.redrawBrackets === 'function') {
            window.DuelDesk.redrawBrackets();
          }
        });
      }
    };

    const fromHash = () => {
      const raw = (window.location.hash || '').replace(/^#/, '');
      return known.has(raw) ? raw : '';
    };

    activate(fromHash() || 'registrations', { updateHash: false });

    bar.addEventListener('click', (e) => {
      const btn = e.target instanceof Element ? e.target.closest('button[data-tab]') : null;
      if (!(btn instanceof HTMLButtonElement)) return;
      const id = btn.dataset.tab || '';
      activate(id);
    });

    bar.addEventListener('keydown', (e) => {
      if (!(e instanceof KeyboardEvent)) return;
      if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;

      const activeIdx = tabEls.findIndex((t) => t.classList.contains('is-active'));
      const idx = activeIdx >= 0 ? activeIdx : 0;
      const next = e.key === 'ArrowRight' ? idx + 1 : idx - 1;
      const target = tabEls[(next + tabEls.length) % tabEls.length];
      if (!(target instanceof HTMLButtonElement)) return;

      e.preventDefault();
      target.focus();
      activate(target.dataset.tab || '');
    });

    window.addEventListener('hashchange', () => {
      const id = fromHash();
      if (id) activate(id, { updateHash: false });
    });
  };

  const setupCopyButtons = () => {
    document.addEventListener('click', async (e) => {
      const el = e.target instanceof Element ? e.target.closest('[data-copy]') : null;
      if (!(el instanceof HTMLElement)) return;

      const raw = el.getAttribute('data-copy') || '';
      if (!raw) return;

      let text = raw;
      if (raw.startsWith('http://') || raw.startsWith('https://')) {
        text = raw;
      } else if (raw.startsWith('/')) {
        text = new URL(raw, window.location.origin).href;
      }

      const original = el.textContent || '';

      try {
        if (!navigator.clipboard || typeof navigator.clipboard.writeText !== 'function') {
          throw new Error('Clipboard API unavailable');
        }

        await navigator.clipboard.writeText(text);

        el.textContent = 'Copie';
        el.classList.add('is-copied');

        window.setTimeout(() => {
          el.textContent = original;
          el.classList.remove('is-copied');
        }, 900);
      } catch {
        // Fallback: let the user copy manually.
        window.prompt('Copier le lien:', text);
      }
    });
  };

  // Init.
  setupRulesetBuilder();

  const setupDropLinesToggle = () => {
    document.addEventListener('click', (e) => {
      const btn = e.target instanceof Element ? e.target.closest('[data-toggle-drop-lines]') : null;
      if (!(btn instanceof HTMLButtonElement)) return;

      const panel = btn.closest('[data-tpanel="bracket"]') || document;
      const view = panel.querySelector('.bracketview');
      if (!(view instanceof HTMLElement)) return;

      const isOn = view.dataset.showDropLines === '1';
      if (isOn) {
        delete view.dataset.showDropLines;
      } else {
        view.dataset.showDropLines = '1';
      }

      btn.setAttribute('aria-pressed', isOn ? 'false' : 'true');
      btn.textContent = isOn ? 'Afficher drop lines' : 'Masquer drop lines';
    });
  };

  const setupBracketExport = () => {
    document.addEventListener('click', async (e) => {
      const btn = e.target instanceof Element ? e.target.closest('[data-bracket-export]') : null;
      if (!(btn instanceof HTMLButtonElement)) return;

      const action = btn.getAttribute('data-bracket-export') || '';

      const showBracketPanel = () => {
        const panel = document.querySelector('[data-tpanel="bracket"]');
        if (!(panel instanceof HTMLElement)) return;
        if (!panel.hasAttribute('hidden')) return;
        const tab = document.querySelector('button[data-tab="bracket"]');
        if (tab instanceof HTMLButtonElement) tab.click();
      };

      showBracketPanel();

      // Wait a tick so the panel is visible and SVG lines can be drawn correctly.
      await new Promise((r) => window.requestAnimationFrame(() => r()));
      if (window.DuelDesk && typeof window.DuelDesk.redrawBrackets === 'function') {
        window.DuelDesk.redrawBrackets();
      }

      const panel = document.querySelector('[data-tpanel="bracket"]');
      if (!(panel instanceof HTMLElement)) return;
      const matrix = panel.querySelector('.bracketview__matrix');
      if (!(matrix instanceof HTMLElement)) return;

      const idMatch = (window.location.pathname || '').match(/\/tournaments\/(\d+)/);
      const tid = idMatch ? idMatch[1] : 'bracket';

      if (action === 'pdf') {
        // Print-to-PDF via browser.
        window.setTimeout(() => window.print(), 50);
        return;
      }

      if (action !== 'svg') return;

      let css = '';
      try {
        const res = await fetch('/assets/css/app.css', { cache: 'force-cache' });
        if (res.ok) css = await res.text();
      } catch {}

      const w = Math.max(1, Math.ceil(matrix.scrollWidth || 1));
      const h = Math.max(1, Math.ceil(matrix.scrollHeight || 1));

      const clone = matrix.cloneNode(true);
      if (clone instanceof HTMLElement) {
        clone.style.transform = 'none';
        clone.style.transformOrigin = '0 0';
      }

      const extraCss = `
        .exportRoot{background:#0b1020;color:#e5e7eb;font-family:Oxanium,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;}
        .exportRoot *{box-sizing:border-box;}
        .exportRoot .bracketview__scroll{overflow:visible !important; cursor:default !important;}
        .exportRoot .bracketview{overflow:visible !important;}
        .exportRoot .bracketlines .line--drop{opacity:0.08;}
      `;

      const xhtml = `
        <div xmlns="http://www.w3.org/1999/xhtml" class="exportRoot">
          <style>${css}\n${extraCss}</style>
          ${clone instanceof Element ? clone.outerHTML : ''}
        </div>
      `;

      const svg = `<?xml version="1.0" encoding="UTF-8"?>\n` +
        `<svg xmlns="http://www.w3.org/2000/svg" width="${w}" height="${h}" viewBox="0 0 ${w} ${h}">` +
        `<foreignObject x="0" y="0" width="100%" height="100%">${xhtml}</foreignObject>` +
        `</svg>`;

      const blob = new Blob([svg], { type: 'image/svg+xml;charset=utf-8' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `dueldesk-bracket-t${tid}.svg`;
      document.body.appendChild(a);
      a.click();
      a.remove();
      window.setTimeout(() => URL.revokeObjectURL(url), 2000);
    });
  };

  const setupMatchModal = () => {
    const dialog = document.getElementById('matchModal');
    if (!(dialog instanceof HTMLDialogElement)) return;

    const titleEl = document.getElementById('matchModalTitle');
    const metaEl = document.getElementById('matchModalMeta');
    const linkEl = document.getElementById('matchModalLink');
    const aLabelEl = document.getElementById('matchModalALabel');
    const bLabelEl = document.getElementById('matchModalBLabel');
    const aNameEl = document.getElementById('matchModalAName');
    const bNameEl = document.getElementById('matchModalBName');
    const scoreEl = document.getElementById('matchModalScore');
    const statusEl = document.getElementById('matchModalStatus');

    const closeOnBackdrop = (e) => {
      if (e.target === dialog) dialog.close();
    };
    dialog.addEventListener('click', closeOnBackdrop);

    const bracketLabel = (bracket) => {
      if (bracket === 'winners') return 'Gagnants';
      if (bracket === 'losers') return 'Perdants';
      if (bracket === 'grand') return 'Finale';
      if (bracket === 'round_robin') return 'Round robin';
      return bracket || '';
    };

    document.addEventListener('click', (e) => {
      const card = e.target instanceof Element ? e.target.closest('.matchcard[data-match-id]') : null;
      if (!(card instanceof HTMLElement)) return;

      if (e instanceof MouseEvent) {
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;
      }
      e.preventDefault();

      const view = card.closest('.bracketview');
      const participantType = (view instanceof HTMLElement) ? (view.dataset.participantType || 'solo') : 'solo';

      const kind = participantType === 'team' ? 'Equipe' : 'Joueur';
      if (aLabelEl) aLabelEl.textContent = `${kind} A`;
      if (bLabelEl) bLabelEl.textContent = `${kind} B`;

      const tag = card.dataset.tag || 'Match';
      const bracket = card.dataset.bracket || '';
      const bestOf = card.dataset.bestOf || '';
      const status = card.dataset.status || '';
      const scheduledAt = card.dataset.scheduledAt || '';

      if (titleEl) titleEl.textContent = tag;

      if (metaEl) {
        const parts = [];
        const bl = bracketLabel(bracket);
        if (bl) parts.push(bl);
        if (bestOf && bestOf !== '0') parts.push(`BO${bestOf}`);
        metaEl.textContent = `${tag}${parts.length ? ' · ' + parts.join(' · ') : ''}`;
      }

      const aName = card.dataset.aName || 'TBD';
      const bName = card.dataset.bName || 'TBD';
      if (aNameEl) aNameEl.textContent = aName;
      if (bNameEl) bNameEl.textContent = bName;

      const s1 = card.dataset.score1 || '0';
      const s2 = card.dataset.score2 || '0';
      const rs1 = card.dataset.reportedScore1 || '';
      const rs2 = card.dataset.reportedScore2 || '';
      const crs1 = card.dataset.counterReportedScore1 || '';
      const crs2 = card.dataset.counterReportedScore2 || '';
      const isConfirmed = status === 'confirmed';
      const isReported = status === 'reported';
      const isDisputed = status === 'disputed';
      if (scoreEl) {
        if (isConfirmed) {
          scoreEl.textContent = `${s1} - ${s2}`;
        } else if (isDisputed && rs1 !== '' && rs2 !== '' && crs1 !== '' && crs2 !== '') {
          scoreEl.textContent = `A ${rs1}-${rs2} / B ${crs1}-${crs2}`;
        } else if (isReported && rs1 !== '' && rs2 !== '') {
          scoreEl.textContent = `${rs1} - ${rs2}`;
        } else {
          scoreEl.textContent = 'TBD';
        }
      }

      if (statusEl) {
        const parts = [];
        if (scheduledAt) parts.push(`Prevu: ${scheduledAt.slice(0, 16)} UTC`);
        if (isReported) {
          const who = card.dataset.reportedBy || '';
          const at = card.dataset.reportedAt || '';
          if (who) parts.push(`Reporte par: ${who}`);
          if (at) parts.push(`Reporte a: ${at.slice(0, 16)} UTC`);
        }
        if (isDisputed) {
          const aWho = card.dataset.reportedBy || '';
          const bWho = card.dataset.counterReportedBy || '';
          if (aWho) parts.push(`A: ${aWho}`);
          if (bWho) parts.push(`B: ${bWho}`);
        }
        if (status) parts.push(`Statut: ${status}`);
        statusEl.textContent = parts.join(' · ');
      }

      if (linkEl instanceof HTMLAnchorElement) {
        linkEl.href = (card instanceof HTMLAnchorElement) ? card.href : (card.getAttribute('href') || '#');
      }

      if (dialog.open) dialog.close();
      dialog.showModal();
    });
  };

  const armAlerts = (root = document) => {
    const alerts = Array.from(root.querySelectorAll('.alert'));
    for (const el of alerts) {
      if (!(el instanceof HTMLElement)) continue;
      if (el.dataset.ddArmed === '1') continue;
      el.dataset.ddArmed = '1';

      const timeout = window.setTimeout(() => {
        el.classList.add('is-dismissed');
        window.setTimeout(() => el.remove(), 250);
      }, 4500);

      el.addEventListener('mouseenter', () => window.clearTimeout(timeout), { once: true });
    }
  };

  armAlerts(document);

  document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;

    const msg = form.getAttribute('data-confirm');
    if (!msg) return;

    if (!window.confirm(msg)) {
      e.preventDefault();
    }
  });

  // Match page: submit pick/ban + report forms without a full reload (fetch HTML, patch fragments).
  let ddSubmitInFlight = false;
  let ddLiveInFlight = false;

  const replaceFromDoc = (doc, selector, { update = true } = {}) => {
    if (!update) return false;
    const cur = document.querySelector(selector);
    const next = doc.querySelector(selector);
    if (!(cur instanceof Element) || !(next instanceof Element)) return false;
    cur.replaceWith(next);
    return true;
  };

  const fetchAndPatch = async (url, { updateFlash = true } = {}) => {
    const res = await fetch(url, { cache: 'no-store', credentials: 'same-origin' });
    const html = await res.text();
    const doc = new DOMParser().parseFromString(html, 'text/html');

    replaceFromDoc(doc, '[data-dd-match-meta]');
    replaceFromDoc(doc, '[data-partial="pickban"]');
    replaceFromDoc(doc, '[data-partial="report"]');
    replaceFromDoc(doc, '[data-flash-root]', { update: updateFlash });

    armAlerts(document);
  };

  const submitAjaxForm = async (form, submitter = null) => {
    if (ddSubmitInFlight) return;
    ddSubmitInFlight = true;

    const method = (form.getAttribute('method') || 'GET').toUpperCase();
    const action = form.getAttribute('action') || window.location.href;

    // Include clicked submit button value (map_key) when applicable.
    const fd = new FormData(form);
    if (submitter instanceof HTMLElement) {
      const n = submitter.getAttribute('name') || '';
      if (n) fd.set(n, submitter.getAttribute('value') || '');
    }

    const disableAll = Array.from(form.querySelectorAll('button, input[type="submit"]'))
      .filter((el) => el instanceof HTMLElement);

    for (const el of disableAll) {
      el.setAttribute('disabled', '');
    }

    try {
      const res = await fetch(action, {
        method,
        body: method === 'GET' ? null : fd,
        credentials: 'same-origin',
        redirect: 'follow',
        cache: 'no-store',
        headers: { 'X-Requested-With': 'DuelDeskFetch' },
      });

      const html = await res.text();
      const doc = new DOMParser().parseFromString(html, 'text/html');

      // Patch only known fragments when present (match page).
      let patched = false;
      patched = replaceFromDoc(doc, '[data-dd-match-meta]') || patched;
      patched = replaceFromDoc(doc, '[data-partial="pickban"]') || patched;
      patched = replaceFromDoc(doc, '[data-partial="report"]') || patched;
      patched = replaceFromDoc(doc, '[data-flash-root]', { update: true }) || patched;

      armAlerts(document);

      // If we didn't patch anything, show the full server response (error page, etc.).
      if (!patched) {
        document.open();
        document.write(html);
        document.close();
      }
    } catch {
      // Last resort: let the normal flow happen via a native submit.
      try {
        if (method !== 'GET') {
          if (submitter instanceof HTMLElement) {
            const n = submitter.getAttribute('name') || '';
            if (n) {
              const h = document.createElement('input');
              h.type = 'hidden';
              h.name = n;
              h.value = submitter.getAttribute('value') || '';
              form.appendChild(h);
            }
          }
          form.submit();
          return;
        }
      } catch {}

      window.location.href = action;
    } finally {
      ddSubmitInFlight = false;
      for (const el of disableAll) {
        el.removeAttribute('disabled');
      }
    }
  };

  document.addEventListener('submit', (e) => {
    if (e.defaultPrevented) return;

    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (form.getAttribute('data-ajax') !== '1') return;

    e.preventDefault();
    const submitter = e.submitter || null;
    submitAjaxForm(form, submitter);
  });

  const setupMatchLive = () => {
    const getMeta = () => document.querySelector('[data-dd-match-meta]');
    const meta0 = getMeta();
    if (!(meta0 instanceof HTMLElement)) return;

    const shouldPoll = () => {
      const meta = getMeta();
      if (!(meta instanceof HTMLElement)) return false;
      const st = meta.dataset.status || '';
      const pbReq = meta.dataset.pickbanRequired === '1';
      const pbLocked = meta.dataset.pickbanLocked === '1';
      if (st === 'confirmed' || st === 'void') return false;
      if (pbReq && !pbLocked) return true;
      if (st === 'reported' || st === 'disputed') return true;
      return false;
    };

	    const tick = async () => {
	      if (!shouldPoll()) return;
	      if (ddSubmitInFlight) return;
	      if (document.visibilityState && document.visibilityState !== 'visible') return;

      // Avoid clobbering inputs while the user types.
      const active = document.activeElement;
      if (active instanceof HTMLElement && active.closest('[data-partial="report"]')) return;

	      await fetchAndPatch(window.location.pathname, { updateFlash: false });
	    };

    // Small delay so we don't fight the initial render.
    window.setTimeout(() => {
      if (!shouldPoll()) return;
      tick();
      const iv = window.setInterval(() => {
        if (!shouldPoll()) {
          window.clearInterval(iv);
          return;
        }
        tick();
      }, 3500);
    }, 1200);
  };

  const setupTournamentLive = () => {
    const el = document.querySelector('[data-dd-tournament-live]');
    if (!(el instanceof HTMLElement)) return;
    const tid = el.dataset.tournamentId || '';
    if (!tid || tid === '0') return;

    const ensureAlert = (card, on) => {
      if (!(card instanceof HTMLElement)) return;
      const existing = card.querySelector('.matchcard__alert');
      if (on) {
        if (existing) return;
        const s = document.createElement('span');
        s.className = 'matchcard__alert';
        s.title = 'Pick/Ban requis';
        s.setAttribute('aria-hidden', 'true');
        s.textContent = '!';
        card.prepend(s);
      } else if (existing) {
        existing.remove();
      }
    };

	    const apply = (m) => {
	      const id = m && typeof m.id === 'number' ? String(m.id) : '';
	      if (!id) return;

	      const card = document.querySelector(`.matchcard[data-match-id="${id}"]`);
	      if (!(card instanceof HTMLElement)) return;

      const st = String(m.status || '');
      card.dataset.status = st;
      card.classList.toggle('is-confirmed', st === 'confirmed');
      card.classList.toggle('is-reported', st === 'reported');
      card.classList.toggle('is-disputed', st === 'disputed');

      card.dataset.bestOf = String(m.best_of || 0);
      card.dataset.scheduledAt = String(m.scheduled_at || '');

      card.dataset.reportedScore1 = (m.reported_score1 === null || typeof m.reported_score1 === 'undefined') ? '' : String(m.reported_score1);
      card.dataset.reportedScore2 = (m.reported_score2 === null || typeof m.reported_score2 === 'undefined') ? '' : String(m.reported_score2);
      card.dataset.reportedWinnerSlot = (m.reported_winner_slot === null || typeof m.reported_winner_slot === 'undefined') ? '' : String(m.reported_winner_slot);
      card.dataset.reportedBy = String(m.reported_by_username || '');
      card.dataset.reportedAt = String(m.reported_at || '');

      card.dataset.counterReportedScore1 = (m.counter_reported_score1 === null || typeof m.counter_reported_score1 === 'undefined') ? '' : String(m.counter_reported_score1);
      card.dataset.counterReportedScore2 = (m.counter_reported_score2 === null || typeof m.counter_reported_score2 === 'undefined') ? '' : String(m.counter_reported_score2);
      card.dataset.counterReportedWinnerSlot = (m.counter_reported_winner_slot === null || typeof m.counter_reported_winner_slot === 'undefined') ? '' : String(m.counter_reported_winner_slot);
      card.dataset.counterReportedBy = String(m.counter_reported_by_username || '');
      card.dataset.counterReportedAt = String(m.counter_reported_at || '');

      card.dataset.aName = String(m.a_label || '');
      card.dataset.bName = String(m.b_label || '');
      card.dataset.score1 = String(m.score1 || 0);
      card.dataset.score2 = String(m.score2 || 0);
      card.dataset.winnerSlot = String(m.winner_slot || 0);

      const slotEls = Array.from(card.querySelectorAll('.matchcard__slot'));
      const aSlot = slotEls[0] instanceof HTMLElement ? slotEls[0] : null;
      const bSlot = slotEls[1] instanceof HTMLElement ? slotEls[1] : null;

      if (aSlot) {
        const name = aSlot.querySelector('.matchcard__name');
        const score = aSlot.querySelector('.matchcard__score');
        if (name) name.textContent = String(m.a_label || '');
        if (score) score.textContent = String(m.card_s1 || '-');
        aSlot.classList.toggle('is-empty', !!m.a_empty);
        aSlot.classList.toggle('is-winner', (m.winner_slot || 0) === 1);
      }
      if (bSlot) {
        const name = bSlot.querySelector('.matchcard__name');
        const score = bSlot.querySelector('.matchcard__score');
        if (name) name.textContent = String(m.b_label || '');
        if (score) score.textContent = String(m.card_s2 || '-');
        bSlot.classList.toggle('is-empty', !!m.b_empty);
        bSlot.classList.toggle('is-winner', (m.winner_slot || 0) === 2);
      }

      const pending = !!m.pickban_pending;
      card.dataset.pickbanPending = pending ? '1' : '';
      ensureAlert(card, pending);
    };

	    const tick = async () => {
	      if (ddSubmitInFlight) return;
	      if (ddLiveInFlight) return;
	      if (document.visibilityState && document.visibilityState !== 'visible') return;

      // Skip when bracket isn't on screen to keep it cheap.
      const bracketPanel = document.querySelector('[data-tpanel="bracket"]');
      if (bracketPanel instanceof HTMLElement && bracketPanel.hasAttribute('hidden')) return;

	      ddLiveInFlight = true;
	      try {
	        const res = await fetch(`/tournaments/${tid}/live`, {
          headers: { 'Accept': 'application/json' },
          credentials: 'same-origin',
          cache: 'no-store',
        });
        if (!res.ok) return;

        const data = await res.json();
        if (!data || data.ok !== true || !Array.isArray(data.matches)) return;
        for (const m of data.matches) apply(m);
	      } catch {
	        // ignore
	      } finally {
	        ddLiveInFlight = false;
	      }
	    };

    window.setTimeout(() => {
      tick();
      window.setInterval(tick, 6000);
    }, 1200);
  };

  // Expose redraw so panels can trigger it when a hidden bracket becomes visible.
  window.DuelDesk = window.DuelDesk || {};
  window.DuelDesk.redrawBrackets = () => {
    const matrices = Array.from(document.querySelectorAll('.bracketview__matrix'));
    for (const matrix of matrices) {
      if (!(matrix instanceof HTMLElement)) continue;
      if (!isVisible(matrix)) continue;
      drawBracketLines(matrix);
    }
  };

  setupTournamentPanels();
  setupCopyButtons();
  setupBrackets();
  setupBracketZoomPan();
  setupDropLinesToggle();
  setupBracketExport();
  setupMatchModal();
  setupMatchLive();
  setupTournamentLive();
})();
