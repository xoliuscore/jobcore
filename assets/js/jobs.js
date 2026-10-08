/**
 * JobCore — front: bookmarks (account when signed in, this device for 7 days otherwise),
 * header menus, mobile drawer, copy link.
 */
(() => {
  'use strict';

  const L10N = window.wpjcL10n || {};
  const CFG = window.wpjcFavs || {};
  const KEY = 'wpjc_favs';
  const TTL = 7 * 24 * 60 * 60 * 1000;
  const loggedIn = Boolean(CFG.loggedIn);

  document.documentElement.classList.add('wpjc-js');

  /* ---------- Bookmarks ---------- */
  const readLocal = () => {
    try {
      const list = JSON.parse(window.localStorage.getItem(KEY) || '[]');
      const now = Date.now();
      return Array.isArray(list) ? list.filter((f) => f && f.id && now - (f.t || 0) < TTL) : [];
    } catch (e) {
      return [];
    }
  };
  const writeLocal = (list) => {
    try {
      window.localStorage.setItem(KEY, JSON.stringify(list));
    } catch (e) {
      // Storage full or blocked: bookmarks just won't persist.
    }
  };

  let favs = loggedIn && Array.isArray(CFG.items) ? CFG.items.slice() : readLocal();

  const favList = document.querySelector('[data-wpjc-fav-list]');
  const favEmpty = document.querySelector('[data-wpjc-fav-empty]');
  const favCount = document.querySelectorAll('[data-wpjc-fav-count]');

  const el = (tag, cls, text) => {
    const node = document.createElement(tag);
    if (cls) {
      node.className = cls;
    }
    if (text) {
      node.textContent = text;
    }
    return node;
  };

  const setFavs = (list) => {
    favs = Array.isArray(list) ? list : [];
    renderFavs();
  };

  const renderFavs = () => {
    const ids = new Set(favs.map((f) => String(f.id)));

    document.querySelectorAll('[data-wpjc-fav]').forEach((btn) => {
      const on = ids.has(btn.getAttribute('data-wpjc-fav'));
      btn.setAttribute('aria-pressed', String(on));
      btn.setAttribute('aria-label', on ? L10N.saved || 'Remove bookmark' : L10N.save || 'Bookmark job');
    });

    document.querySelectorAll('[data-wpjc-saved]').forEach((row) => {
      row.hidden = !ids.has(row.getAttribute('data-wpjc-saved'));
    });

    favCount.forEach((badge) => {
      badge.textContent = String(favs.length);
      badge.hidden = favs.length === 0;
    });

    document.querySelectorAll('[data-wpjc-fav-total]').forEach((node) => {
      node.textContent = String(favs.length);
    });

    if (!favList) {
      return;
    }
    favList.replaceChildren();
    favs.forEach((f) => {
      const li = el('li', 'wpjc-favlist__item');
      const a = el('a', 'wpjc-favlist__link');
      a.href = f.url;
      if (f.logo) {
        const img = el('img', 'wpjc-favlist__logo');
        img.src = f.logo;
        img.alt = '';
        a.append(img);
      }
      const txt = el('span', 'wpjc-favlist__text');
      txt.append(el('strong', '', f.title || ''), el('span', '', f.company || ''));
      a.append(txt);
      const rm = el('button', 'wpjc-favlist__rm', '×');
      rm.type = 'button';
      rm.setAttribute('aria-label', L10N.remove || 'Remove');
      rm.addEventListener('click', (ev) => {
        ev.stopPropagation();
        toggleFav(String(f.id));
      });
      li.append(a, rm);
      favList.append(li);
    });
    if (favEmpty) {
      favEmpty.hidden = favs.length > 0;
    }
  };

  const itemFromBtn = (btn) => ({
    id: btn.getAttribute('data-wpjc-fav'),
    title: btn.dataset.title || '',
    company: btn.dataset.company || '',
    url: btn.dataset.url || '',
    logo: btn.dataset.logo || '',
    t: Date.now(),
  });

  const toggleLocal = (id, btn) => {
    const list = readLocal();
    const has = list.some((f) => String(f.id) === id);
    const next = has
      ? list.filter((f) => String(f.id) !== id)
      : [itemFromBtn(btn), ...list].slice(0, 50);
    writeLocal(next);
    setFavs(next);
  };

  const api = async (method, body) => {
    const res = await fetch(CFG.url, {
      method,
      credentials: 'same-origin',
      headers: {
        'X-WP-Nonce': CFG.nonce,
        Accept: 'application/json',
        ...(body ? { 'Content-Type': 'application/json' } : {}),
      },
      body: body ? JSON.stringify(body) : undefined,
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
      throw data;
    }
    return data;
  };

  const toggleFav = (id, btn) => {
    if (!loggedIn) {
      toggleLocal(id, btn || document.querySelector(`[data-wpjc-fav="${id}"]`));
      return;
    }
    const prev = favs.slice();
    const has = prev.some((f) => String(f.id) === String(id));
    if (has) {
      setFavs(prev.filter((f) => String(f.id) !== String(id)));
    } else if (btn) {
      setFavs([itemFromBtn(btn), ...prev]);
    }
    api('POST', { id: Number(id) })
      .then((data) => setFavs(data.items || []))
      .catch(() => setFavs(prev));
  };

  document.addEventListener('click', (ev) => {
    const btn = ev.target.closest('[data-wpjc-fav]');
    if (!btn) {
      return;
    }
    ev.preventDefault();
    toggleFav(btn.getAttribute('data-wpjc-fav'), btn);
  });

  renderFavs();

  if (loggedIn) {
    const local = readLocal();
    if (local.length) {
      api('POST', { merge: local.map((f) => Number(f.id)).filter(Boolean) })
        .then((data) => {
          writeLocal([]);
          setFavs(data.items || []);
        })
        .catch(() => {});
    }
  }

  /* ---------- WooCommerce cart link: shown only with items (cart cookie + Store API, cache-safe) ---------- */
  (() => {
    const links = document.querySelectorAll('[data-wpjc-cart]');
    if (!links.length || !/(?:^|;\s*)woocommerce_items_in_cart=1/.test(document.cookie)) return;
    const show = (count) => {
      const text = count > 99 ? '99+' : String(count);
      links.forEach((link) => {
        link.hidden = count < 1;
        const wrap = link.closest('[data-wpjc-cart-wrap]');
        if (wrap) wrap.hidden = count < 1;
        const badge = link.querySelector('[data-wpjc-cart-count]');
        if (badge) {
          badge.textContent = text;
          badge.hidden = count < 1;
        }
        link.setAttribute('aria-label', `${(L10N.cart || 'Cart')} (${text})`);
      });
    };
    fetch(links[0].dataset.cartApi, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then((r) => (r.ok ? r.json() : null))
      .then((data) => { if (data) show(parseInt(data.items_count, 10) || 0); })
      .catch(() => {});
  })();

  /* ---------- Header menus (hover on desktop via CSS, click/tap here) ---------- */
  const pops = document.querySelectorAll('.wpjc-pop');
  const closePops = (except) => {
    pops.forEach((pop) => {
      if (pop !== except) {
        pop.classList.remove('is-open');
        const b = pop.querySelector('.wpjc-pop__btn');
        if (b) {
          b.setAttribute('aria-expanded', 'false');
        }
      }
    });
  };
  pops.forEach((pop) => {
    const btn = pop.querySelector('.wpjc-pop__btn');
    if (!btn) {
      return;
    }
    btn.addEventListener('click', (ev) => {
      ev.stopPropagation();
      const canHover = window.matchMedia('(hover: hover) and (min-width: 1024px)').matches;
      if (btn.classList.contains('wpjc-login') && canHover) {
        return;
      }
      if (btn.classList.contains('wpjc-login')) {
        ev.preventDefault();
      }
      const open = !pop.classList.contains('is-open');
      closePops(pop);
      pop.classList.toggle('is-open', open);
      btn.setAttribute('aria-expanded', String(open));
    });
  });
  document.addEventListener('click', (ev) => {
    if (!ev.target.closest('.wpjc-pop')) {
      closePops();
    }
  });

  /* ---------- Mobile drawer (board header) ---------- */
  const burger = document.querySelector('.wpjc-burger');
  const drawer = document.getElementById('wpjc-drawer');
  const setDrawer = (open) => {
    if (!drawer || !burger) {
      return;
    }
    drawer.hidden = !open;
    burger.setAttribute('aria-expanded', String(open));
    document.body.classList.toggle('wpjc-drawer-open', open);
    if (open) {
      const input = drawer.querySelector('input[type="search"]');
      if (input) {
        input.focus();
      }
    } else {
      burger.focus();
    }
  };
  if (burger && drawer) {
    burger.addEventListener('click', () => setDrawer(true));
    drawer.addEventListener('click', (ev) => {
      if (ev.target === drawer || ev.target.closest('[data-wpjc-drawer-close]')) {
        setDrawer(false);
      }
    });
  }

  document.addEventListener('keydown', (ev) => {
    if (ev.key !== 'Escape') {
      return;
    }
    closePops();
    if (drawer && !drawer.hidden) {
      setDrawer(false);
    }
  });

  /* ---------- Copy link ---------- */
  // The Clipboard API only exists on HTTPS / localhost; plain-HTTP sites use a hidden textarea.
  const copyText = async (text) => {
    if (navigator.clipboard && window.isSecureContext) {
      try {
        await navigator.clipboard.writeText(text);
        return true;
      } catch (e) {
        // Blocked: try the fallback.
      }
    }
    const area = document.createElement('textarea');
    area.value = text;
    area.setAttribute('readonly', '');
    area.style.cssText = 'position:fixed;top:0;left:0;width:1px;height:1px;opacity:0;';
    document.body.appendChild(area);
    area.select();
    let ok = false;
    try {
      ok = document.execCommand('copy');
    } catch (e) {
      ok = false;
    }
    area.remove();
    return ok;
  };
  const CHECK = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m5 12 5 5L20 7"/></svg>';

  document.querySelectorAll('[data-wpjc-copy]').forEach((btn) => {
    const label = btn.getAttribute('aria-label') || '';
    const icon = btn.innerHTML;
    let timer = 0;
    btn.addEventListener('click', async () => {
      const url = btn.getAttribute('data-wpjc-copy');
      if (!url) {
        return;
      }
      const ok = await copyText(url);
      if (!ok) {
        window.prompt(label, url);
        return;
      }
      window.clearTimeout(timer);
      btn.classList.add('is-copied');
      btn.innerHTML = CHECK;
      btn.dataset.tip = L10N.copied || 'Link copied';
      btn.setAttribute('aria-label', btn.dataset.tip);
      timer = window.setTimeout(() => {
        btn.classList.remove('is-copied');
        btn.innerHTML = icon;
        btn.setAttribute('aria-label', label);
      }, 2000);
    });
  });

  /* ---------- Apply form uploads: list, remove, drag & drop, limits ---------- */
  const formatSize = (bytes) => (bytes < 1048576 ? `${Math.max(1, Math.round(bytes / 1024))} KB` : `${(bytes / 1048576).toFixed(1)} MB`);

  document.querySelectorAll('[data-wpjc-upload]').forEach((box) => {
    const input = box.querySelector('.wpjc-upload__input');
    const list = box.querySelector('[data-wpjc-upload-list]');
    const error = box.querySelector('[data-wpjc-upload-error]');
    const drop = box.querySelector('.wpjc-drop');
    const maxFiles = parseInt(box.getAttribute('data-max-files'), 10) || 1;
    const maxBytes = (parseFloat(box.getAttribute('data-max-mb')) || 1) * 1048576;
    if (!input || !list || typeof DataTransfer === 'undefined') {
      return;
    }

    const setFiles = (files) => {
      const dt = new DataTransfer();
      files.forEach((f) => dt.items.add(f));
      input.files = dt.files;
    };

    const validate = (files) => {
      if (files.length > maxFiles) {
        return (L10N.tooMany || 'Up to %d files.').replace('%d', maxFiles);
      }
      const total = files.reduce((sum, f) => sum + f.size, 0);
      if (total > maxBytes) {
        return (L10N.tooBig || 'Files are too large (max %d MB).').replace('%d', Math.round(maxBytes / 1048576));
      }
      return '';
    };

    const render = () => {
      const files = Array.from(input.files || []);
      const msg = validate(files);
      error.textContent = msg;
      error.hidden = !msg;
      input.setCustomValidity(msg);
      list.textContent = '';
      files.forEach((file, i) => {
        const li = el('li', 'wpjc-upload__item');
        if (file.type.startsWith('image/')) {
          const img = el('img', 'wpjc-upload__thumb');
          img.alt = '';
          img.src = URL.createObjectURL(file);
          img.addEventListener('load', () => URL.revokeObjectURL(img.src), { once: true });
          li.append(img);
        }
        const rm = el('button', 'wpjc-upload__rm', '×');
        rm.type = 'button';
        rm.setAttribute('aria-label', `${L10N.remove || 'Remove'}: ${file.name}`);
        rm.addEventListener('click', () => {
          const rest = Array.from(input.files);
          rest.splice(i, 1);
          setFiles(rest);
          render();
        });
        li.append(el('span', 'wpjc-upload__name', file.name), el('span', 'wpjc-upload__size', formatSize(file.size)), rm);
        list.append(li);
      });
    };

    let kept = [];
    input.addEventListener('click', () => {
      kept = input.multiple ? Array.from(input.files || []) : [];
    });
    input.addEventListener('change', () => {
      if (input.multiple && kept.length) {
        const added = Array.from(input.files || []);
        setFiles(kept.concat(added.filter((f) => !kept.some((k) => k.name === f.name && k.size === f.size))));
      }
      kept = [];
      render();
    });

    if (drop) {
      ['dragenter', 'dragover'].forEach((type) => drop.addEventListener(type, (ev) => {
        ev.preventDefault();
        drop.classList.add('is-over');
      }));
      ['dragleave', 'drop'].forEach((type) => drop.addEventListener(type, () => drop.classList.remove('is-over')));
      drop.addEventListener('drop', (ev) => {
        ev.preventDefault();
        const dropped = Array.from((ev.dataTransfer && ev.dataTransfer.files) || []);
        setFiles(Array.from(input.files || []).concat(dropped));
        render();
      });
    }
  });

  /* ---------- Jobs account: phone menu, confirm, saved jobs count, apply method ---------- */
  document.querySelectorAll('[data-wpjc-acc-toggle]').forEach((btn) => {
    const panel = document.getElementById(btn.getAttribute('aria-controls'));
    if (!panel) {
      return;
    }
    btn.addEventListener('click', () => {
      const open = btn.getAttribute('aria-expanded') !== 'true';
      btn.setAttribute('aria-expanded', String(open));
      panel.classList.toggle('is-open', open);
    });
  });

  document.addEventListener('submit', (ev) => {
    const form = ev.target.closest('[data-wpjc-confirm]');
    if (form && !window.confirm(form.getAttribute('data-wpjc-confirm'))) {
      ev.preventDefault();
    }
  });

  document.querySelectorAll('[data-wpjc-autosubmit]').forEach((field) => {
    field.addEventListener('change', () => field.form && field.form.requestSubmit());
  });

  if (/^#wpjc-app-\d+$/.test(window.location.hash)) {
    const more = document.querySelector(`${window.location.hash} details`);
    if (more) {
      more.open = true;
    }
  }

  document.querySelectorAll('input[name="job_apply_how"]').forEach((radio, i, all) => {
    const form = radio.form;
    const sync = () => {
      const how = Array.from(all).find((r) => r.checked);
      form.querySelectorAll('[data-wpjc-apply-how]').forEach((row) => {
        const on = how && row.getAttribute('data-wpjc-apply-how') === how.value;
        row.hidden = !on;
        row.querySelectorAll('input').forEach((input) => {
          input.required = Boolean(on);
        });
      });
    };
    radio.addEventListener('change', sync);
    if (i === 0) {
      sync();
    }
  });

  /* ---------- Live search: results panel under every board search box ---------- */
  const LIVE = window.wpjcSearch;
  const RECENT = 'wpjc_recent';
  const fold = (s) => String(s).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  const fmt = (str, ...args) => {
    let n = 0;
    return String(str).replace(/%(\d\$)?s/g, (m, pos) => String(args[pos ? parseInt(pos, 10) - 1 : n++] ?? ''));
  };
  const readRecent = () => {
    try {
      const list = JSON.parse(window.localStorage.getItem(RECENT) || '[]');
      return Array.isArray(list) ? list.filter((q) => typeof q === 'string').slice(0, 5) : [];
    } catch (e) {
      return [];
    }
  };
  const saveRecent = (q) => {
    const clean = String(q).trim();
    if (clean.length < 2) {
      return;
    }
    const list = [clean, ...readRecent().filter((r) => fold(r) !== fold(clean))].slice(0, 5);
    try {
      window.localStorage.setItem(RECENT, JSON.stringify(list));
    } catch (e) {
      /* Private mode: no history. */
    }
  };
  const clearRecent = () => {
    try {
      window.localStorage.removeItem(RECENT);
    } catch (e) {
      /* Nothing stored. */
    }
  };
  /** Text with the first match of q wrapped in <mark>. */
  const marked = (tag, cls, text, q) => {
    const node = el(tag, cls);
    const i = q ? fold(text).indexOf(fold(q)) : -1;
    if (i < 0) {
      node.textContent = text;
      return node;
    }
    node.append(text.slice(0, i), el('mark', '', text.slice(i, i + q.length)), text.slice(i + q.length));
    return node;
  };
  const logo = (src, name) => {
    const box = el('span', 'wpjc-live__logo');
    if (src) {
      const img = el('img');
      img.src = src;
      img.alt = '';
      img.loading = 'lazy';
      box.append(img);
    } else {
      box.classList.add('is-ph');
      box.textContent = String(name || '').trim().charAt(0).toUpperCase();
    }
    return box;
  };

  const cache = new Map();
  const fetchLive = (q, signal) => {
    if (cache.has(q)) {
      return Promise.resolve(cache.get(q));
    }
    const url = `${LIVE.url}${LIVE.url.includes('?') ? '&' : '?'}q=${encodeURIComponent(q)}`;
    return fetch(url, { headers: { 'X-WP-Nonce': LIVE.nonce, Accept: 'application/json' }, credentials: 'same-origin', signal })
      .then((res) => (res.ok ? res.json() : Promise.reject(res.status)))
      .then((data) => {
        cache.set(q, data);
        return data;
      });
  };

  let liveSeq = 0;
  const initLive = (form) => {
    const input = form.querySelector('.wpjc-search__input');
    if (!input || form.dataset.wpjcLive) {
      return;
    }
    form.dataset.wpjcLive = '1';
    const T = LIVE.i18n || {};
    const id = `wpjc-live-${++liveSeq}`;
    const panel = el('div', 'wpjc-live');
    panel.id = id;
    panel.hidden = true;
    panel.setAttribute('role', 'listbox');
    panel.setAttribute('aria-label', T.jobs || 'Jobs');
    form.classList.add('has-live');
    form.append(panel);
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-controls', id);
    input.setAttribute('aria-expanded', 'false');

    let sel = -1;
    let timer = 0;
    let ctrl = null;
    let seq = 0;

    const options = () => Array.from(panel.querySelectorAll('[data-wpjc-opt]'));
    const paintSel = () => {
      options().forEach((opt, i) => {
        opt.classList.toggle('is-sel', i === sel);
        opt.setAttribute('aria-selected', String(i === sel));
        if (i === sel) {
          input.setAttribute('aria-activedescendant', opt.id);
          opt.scrollIntoView({ block: 'nearest' });
        }
      });
      if (sel < 0) {
        input.removeAttribute('aria-activedescendant');
      }
    };
    const option = (node) => {
      node.setAttribute('role', 'option');
      node.setAttribute('data-wpjc-opt', '');
      node.id = `${id}-o${options().length}`;
      return node;
    };
    const open = () => {
      panel.hidden = false;
      form.classList.add('is-live-open');
      input.setAttribute('aria-expanded', 'true');
    };
    const close = () => {
      panel.hidden = true;
      form.classList.remove('is-live-open');
      input.setAttribute('aria-expanded', 'false');
      sel = -1;
      paintSel();
    };
    const group = (title, count) => {
      const box = el('div', 'wpjc-live__grp');
      const head = el('div', 'wpjc-live__h', title);
      if (count !== undefined) {
        head.append(el('span', '', String(count)));
      }
      box.append(head);
      panel.append(box);
      return box;
    };
    const chips = (box, items, q) => {
      const row = el('div', 'wpjc-live__chips');
      items.forEach((c) => {
        const a = option(el('a', 'wpjc-live__chip'));
        a.href = c.url;
        a.append(marked('span', '', c.name, q));
        if (c.count) {
          a.append(el('small', '', String(c.count)));
        }
        row.append(a);
      });
      box.append(row);
    };
    const keys = () => {
      const row = el('div', 'wpjc-live__keys');
      [['↑', '↓', T.navigate], ['↵', T.open], ['Esc', T.close]].forEach((k) => {
        const span = el('span');
        k.slice(0, -1).forEach((key) => span.append(el('kbd', '', key)));
        span.append(` ${k[k.length - 1] || ''}`);
        row.append(span);
      });
      panel.append(row);
    };

    const paintIdle = (data) => {
      panel.replaceChildren();
      const recent = readRecent();
      if (recent.length) {
        const box = group(T.recent);
        const clear = el('button', 'wpjc-live__clear', T.clear);
        clear.type = 'button';
        clear.addEventListener('click', () => {
          clearRecent();
          paintIdle(data);
          input.focus();
        });
        box.firstChild.append(clear);
        const row = el('div', 'wpjc-live__chips');
        recent.forEach((q) => {
          const b = option(el('button', 'wpjc-live__chip is-recent', q));
          b.type = 'button';
          b.addEventListener('click', () => {
            input.value = q;
            run();
            input.focus();
          });
          row.append(b);
        });
        box.append(row);
      }
      if (data && data.categories && data.categories.length) {
        chips(group(T.popular), data.categories, '');
      } else if (!recent.length) {
        panel.append(el('p', 'wpjc-live__hint', T.idle));
      }
      sel = -1;
      paintSel();
      open();
    };

    const paintHits = (q, data) => {
      panel.replaceChildren();
      const jobs = data.jobs || [];
      if (!jobs.length && !(data.employers || []).length && !(data.categories || []).length) {
        const empty = el('div', 'wpjc-live__empty');
        empty.append(el('b', '', fmt(T.none, q)), el('p', '', T.noneText));
        if (LIVE.alertUrl) {
          const a = el('a', 'wpjc-live__btn', T.alert);
          a.href = LIVE.alertUrl;
          empty.append(a);
        }
        panel.append(empty);
        sel = -1;
        paintSel();
        open();
        return;
      }
      if (jobs.length) {
        const box = group(T.jobs, data.total);
        jobs.forEach((job) => {
          const a = option(el('a', 'wpjc-live__hit'));
          a.href = job.url;
          const txt = el('span', 'wpjc-live__txt');
          txt.append(marked('b', '', job.t, q), el('span', '', [job.company, job.place, job.until].filter(Boolean).join(' · ')));
          a.append(logo(job.logo, job.company), txt);
          if (job.tier) {
            const tier = el('span', 'wpjc-live__tier', job.tier.short);
            tier.style.setProperty('--t', job.tier.color);
            tier.title = job.tier.label;
            a.append(tier);
          }
          box.append(a);
        });
      }
      if ((data.employers || []).length) {
        const box = group(T.employers);
        data.employers.forEach((emp) => {
          const a = option(el('a', 'wpjc-live__hit is-employer'));
          a.href = emp.url;
          const txt = el('span', 'wpjc-live__txt');
          txt.append(marked('b', '', emp.name, q), el('span', '', fmt(T.count, emp.count)));
          a.append(logo(emp.logo, emp.name), txt);
          box.append(a);
        });
      }
      if ((data.categories || []).length) {
        chips(group(T.categories), data.categories, q);
      }
      if (data.total > jobs.length && data.all) {
        const all = option(el('a', 'wpjc-live__all', fmt(T.all, data.total, q)));
        all.href = data.all;
        all.append(el('span', '', '→'));
        panel.append(all);
      }
      keys();
      sel = jobs.length ? 0 : -1;
      paintSel();
      open();
    };

    const run = () => {
      const q = input.value.trim();
      const mine = ++seq;
      window.clearTimeout(timer);
      if (ctrl) {
        ctrl.abort();
      }
      timer = window.setTimeout(() => {
        ctrl = typeof AbortController === 'function' ? new AbortController() : null;
        if (q.length >= 2 && !cache.has(q)) {
          form.classList.add('is-live-busy');
        }
        fetchLive(q.length < 2 ? '' : q, ctrl ? ctrl.signal : undefined)
          .then((data) => {
            if (mine !== seq) {
              return;
            }
            if (q.length < 2) {
              paintIdle(data);
            } else {
              paintHits(q, data);
            }
          })
          .catch(() => {
            if (mine === seq) {
              close();
            }
          })
          .finally(() => {
            if (mine === seq) {
              form.classList.remove('is-live-busy');
            }
          });
      }, q.length < 2 || cache.has(q) ? 0 : 180);
    };

    input.addEventListener('input', run);
    input.addEventListener('focus', () => {
      if (panel.hidden) {
        run();
      }
    });
    input.addEventListener('keydown', (ev) => {
      const opts = options();
      if (ev.key === 'ArrowDown' || ev.key === 'ArrowUp') {
        if (panel.hidden) {
          run();
          return;
        }
        ev.preventDefault();
        if (opts.length) {
          sel = ev.key === 'ArrowDown' ? (sel + 1) % opts.length : (sel <= 0 ? opts.length : sel) - 1;
          paintSel();
        }
      } else if (ev.key === 'Enter' && !panel.hidden && sel >= 0 && opts[sel]) {
        ev.preventDefault();
        saveRecent(input.value);
        opts[sel].click();
      } else if (ev.key === 'Escape' && !panel.hidden) {
        ev.stopPropagation();
        close();
      }
    });
    panel.addEventListener('mousemove', (ev) => {
      const opt = ev.target.closest('[data-wpjc-opt]');
      const i = opt ? options().indexOf(opt) : -1;
      if (i >= 0 && i !== sel) {
        sel = i;
        paintSel();
      }
    });
    panel.addEventListener('click', (ev) => {
      if (ev.target.closest('a[data-wpjc-opt]')) {
        saveRecent(input.value);
      }
    });
    form.addEventListener('submit', () => saveRecent(input.value));
    form.addEventListener('focusout', (ev) => {
      if (!form.contains(ev.relatedTarget)) {
        window.setTimeout(() => {
          if (!form.contains(document.activeElement)) {
            close();
          }
        }, 120);
      }
    });
  };

  /* ---------- Advanced search: filters under the hero search, with a live job count ---------- */
  const initAdv = (box) => {
    const form = document.getElementById(box.dataset.form);
    const toggle = box.querySelector('.wpjc-adv__toggle');
    const panel = box.querySelector('.wpjc-adv__panel');
    if (!form || !toggle || !panel) {
      return;
    }
    const badge = box.querySelector('[data-wpjc-adv-badge]');
    const label = box.querySelector('[data-wpjc-adv-label]');
    const fields = () => Array.from(form.elements).filter((f) => f.name && f.name !== 'wpjc_q' && panel.contains(f));
    const cache = new Map();
    let seq = 0;
    let timer = 0;

    const setOpen = (open) => {
      box.classList.toggle('is-open', open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      panel.hidden = !open;
      if (open) {
        refresh();
      }
    };

    const params = () => {
      const p = new URLSearchParams();
      new FormData(form).forEach((v, k) => {
        if (String(v).trim() !== '') {
          p.set(k, String(v).trim());
        }
      });
      return p;
    };

    const setLabel = (n) => {
      const i = (LIVE && LIVE.i18n) || {};
      if (n === null) {
        label.textContent = i.showAny || label.textContent;
      } else if (n === 0) {
        label.textContent = i.showNone || '0';
      } else if (n === 1) {
        label.textContent = i.showOne || '1';
      } else {
        label.textContent = fmt(i.show || '%s', n.toLocaleString());
      }
    };

    const refresh = () => {
      const n = fields().filter((f) => (f.type === 'radio' || f.type === 'checkbox' ? f.checked : true) && f.value !== '').length;
      if (badge) {
        badge.hidden = n === 0;
        badge.textContent = String(n);
      }
      if (!LIVE || !LIVE.countUrl) {
        return;
      }
      const qs = params().toString();
      if (cache.has(qs)) {
        setLabel(cache.get(qs));
        return;
      }
      const mine = ++seq;
      box.classList.add('is-counting');
      fetch(`${LIVE.countUrl}${LIVE.countUrl.includes('?') ? '&' : '?'}${qs}`, {
        headers: { 'X-WP-Nonce': LIVE.nonce || '' },
        credentials: 'same-origin',
      })
        .then((r) => (r.ok ? r.json() : Promise.reject(r)))
        .then((data) => {
          const count = Number(data && data.count) || 0;
          cache.set(qs, count);
          if (mine === seq) {
            setLabel(count);
          }
        })
        .catch(() => mine === seq && setLabel(null))
        .finally(() => mine === seq && box.classList.remove('is-counting'));
    };
    const later = () => {
      window.clearTimeout(timer);
      timer = window.setTimeout(refresh, 250);
    };

    toggle.addEventListener('click', () => setOpen(panel.hidden));
    panel.addEventListener('change', refresh);
    const input = form.querySelector('.wpjc-search__input');
    if (input) {
      input.addEventListener('input', () => !panel.hidden && later());
    }
    panel.addEventListener('keydown', (ev) => {
      if (ev.key === 'Escape') {
        setOpen(false);
        toggle.focus();
      }
    });
    box.querySelector('[data-wpjc-adv-clear]')?.addEventListener('click', () => {
      fields().forEach((f) => {
        if (f.type === 'radio') {
          f.checked = f.value === '';
        } else if (f.type === 'checkbox') {
          f.checked = false;
        } else {
          f.value = '';
        }
      });
      refresh();
    });

    // Leave empty filters out of the URL.
    form.addEventListener('submit', () => {
      Array.from(form.elements).forEach((f) => {
        if (f.name && !f.disabled && String(f.value).trim() === '' && !(f.type === 'radio' && !f.checked)) {
          f.disabled = true;
          f.dataset.wpjcOff = '1';
        }
      });
    });
    window.addEventListener('pageshow', () => {
      Array.from(form.elements).forEach((f) => {
        if (f.dataset.wpjcOff) {
          f.disabled = false;
          delete f.dataset.wpjcOff;
        }
      });
    });

    if (!panel.hidden) {
      refresh();
    }
  };
  document.querySelectorAll('[data-wpjc-adv]').forEach(initAdv);

  if (LIVE && LIVE.url) {
    document.querySelectorAll('form.wpjc-search').forEach(initLive);

    // "/" jumps to the board search, unless the theme's own search overlay owns the shortcut.
    document.addEventListener('keydown', (ev) => {
      if (ev.key !== '/' || ev.ctrlKey || ev.metaKey || ev.altKey || document.getElementById('srch')) {
        return;
      }
      const t = ev.target;
      if (t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName))) {
        return;
      }
      const input = document.querySelector('.wpjc-hero .wpjc-search__input');
      if (input) {
        ev.preventDefault();
        input.scrollIntoView({ block: 'center', behavior: 'smooth' });
        input.focus({ preventScroll: true });
      }
    });
  }
})();

/* Sign in / Create account: switch tabs in place, without reloading or moving the page. */
(function () {
  'use strict';
  const root = document.querySelector('[data-wpjc-auth]');
  if (!root) {
    return;
  }
  root.addEventListener('click', (ev) => {
    const link = ev.target.closest('[data-wpjc-auth-go]');
    if (!link || ev.ctrlKey || ev.metaKey || ev.shiftKey || ev.button) {
      return;
    }
    ev.preventDefault();
    const mode = link.getAttribute('data-wpjc-auth-go');
    root.querySelectorAll('[data-wpjc-auth-pane]').forEach((el) => {
      el.hidden = el.getAttribute('data-wpjc-auth-pane') !== mode;
    });
    root.querySelectorAll('.wpjc-auth__tabs [data-wpjc-auth-go]').forEach((tab) => {
      const on = tab.getAttribute('data-wpjc-auth-go') === mode;
      tab.classList.toggle('is-active', on);
      if (on) {
        tab.setAttribute('aria-current', 'page');
      } else {
        tab.removeAttribute('aria-current');
      }
    });
    root.querySelectorAll('.wpjc-auth .wpjc-notice').forEach((n) => n.remove());
    try {
      history.replaceState(null, '', link.href);
    } catch (e) { /* ignore */ }
  });
})();

/* Light / Dark / Auto in the profile menu. */
(function () {
  'use strict';
  document.addEventListener('click', (ev) => {
    const btn = ev.target.closest('[data-wpjc-scheme-set]');
    if (!btn) {
      return;
    }
    ev.preventDefault();
    const pref = btn.getAttribute('data-wpjc-scheme-set');
    const html = document.documentElement;
    html.setAttribute('data-wpjc-scheme-pref', pref);
    if (pref === 'auto') {
      if (window.wpjcScheme) {
        window.wpjcScheme();
      }
    } else {
      html.setAttribute('data-wpjc-scheme', pref);
    }
    document.querySelectorAll('[data-wpjc-scheme-set]').forEach((b) => {
      b.setAttribute('aria-pressed', b.getAttribute('data-wpjc-scheme-set') === pref ? 'true' : 'false');
    });
    document.cookie = 'wpjc_scheme=' + pref + ';path=/;max-age=31536000;SameSite=Lax' + (location.protocol === 'https:' ? ';Secure' : '');
    const box = btn.closest('.wpjc-scheme');
    if (box && box.dataset.nonce && window.fetch) {
      const body = new URLSearchParams({ action: 'wpjc_scheme', nonce: box.dataset.nonce, scheme: pref });
      fetch(box.dataset.ajax, { method: 'POST', credentials: 'same-origin', body }).catch(() => {});
    }
  });
})();
