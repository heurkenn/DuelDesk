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
    const grandEl = map.get(key('grand', 1, 1)) || null;

    const matrixRect = matrixEl.getBoundingClientRect();
    const width = Math.max(1, Math.ceil(matrixEl.scrollWidth || matrixRect.width));
    const height = Math.max(1, Math.ceil(matrixEl.scrollHeight || matrixRect.height));

    svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
    svg.setAttribute('width', String(width));
    svg.setAttribute('height', String(height));

    // Clear existing paths.
    while (svg.firstChild) svg.removeChild(svg.firstChild);

    const EDGE_PAD = 2; // Avoid lines bleeding under semi-transparent match cards.
    const pt = (el, side) => {
      const r = el.getBoundingClientRect();
      const x = side === 'right'
        ? ((r.right - matrixRect.left) + EDGE_PAD)
        : ((r.left - matrixRect.left) - EDGE_PAD);
      const y = (r.top - matrixRect.top) + (r.height / 2);
      return { x, y };
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
          } else if (format === 'double_elim' && grandEl) {
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
          } else if (grandEl) {
            connect('losers', r, p, 'grand', 1, 1, 'line--los');
          }
        }
      }
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
      const isConfirmed = status === 'confirmed';
      if (scoreEl) scoreEl.textContent = isConfirmed ? `${s1} - ${s2}` : 'TBD';

      if (statusEl) statusEl.textContent = status ? `Statut: ${status}` : '';

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
  setupBrackets();
  setupMatchModal();
})();
