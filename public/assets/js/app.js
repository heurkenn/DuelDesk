(() => {
  document.documentElement.classList.add('js');

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

  const alerts = Array.from(document.querySelectorAll('.alert'));
  for (const el of alerts) {
    const timeout = window.setTimeout(() => {
      el.classList.add('is-dismissed');
      window.setTimeout(() => el.remove(), 250);
    }, 4500);

    el.addEventListener('mouseenter', () => window.clearTimeout(timeout), { once: true });
  }

  document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;

    const msg = form.getAttribute('data-confirm');
    if (!msg) return;

    if (!window.confirm(msg)) {
      e.preventDefault();
    }
  });

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
})();
