(function () {
  'use strict';

  var cfg = window.ECC_CFG || null;
  if (!cfg || !cfg.texts) return;

  var root = document.getElementById('ecc-root');
  if (!root) return;

  var state = {
    consent: null,
    draft: {},
    showRevision: false,
  };

  function applyStyles() {
    var styles = cfg.styles || {};
    Object.keys(styles).forEach(function (key) {
      var cssKey = '--ecc-' + key.replace(/_/g, '-');
      if (styles[key] != null && styles[key] !== '') {
        root.style.setProperty(cssKey, String(styles[key]));
      }
    });
  }

  function readCookie(name) {
    var m = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/([.$?*|{}()[\]\\/+^])/g, '\\$1') + '=([^;]*)'));
    return m ? decodeURIComponent(m[1]) : '';
  }

  function writeCookie(name, value, days) {
    var maxAge = Math.max(1, Number(days) || 182) * 86400;
    var secure = location.protocol === 'https:' ? '; Secure' : '';
    document.cookie =
      name +
      '=' +
      encodeURIComponent(value) +
      '; Path=/; Max-Age=' +
      maxAge +
      '; SameSite=Lax' +
      secure;
  }

  function parseConsent() {
    try {
      var raw = readCookie(cfg.cookieName || 'ecc_consent');
      if (!raw) return null;
      var data = JSON.parse(raw);
      if (!data || typeof data !== 'object') return null;
      return data;
    } catch (e) {
      return null;
    }
  }

  function categoryIds() {
    return (cfg.categories || []).map(function (c) {
      return c.id;
    });
  }

  function essentialIds() {
    return (cfg.categories || [])
      .filter(function (c) {
        return c.essential;
      })
      .map(function (c) {
        return c.id;
      });
  }

  function defaultDraft(allOn) {
    var draft = {};
    (cfg.categories || []).forEach(function (c) {
      draft[c.id] = c.essential ? true : !!allOn;
    });
    return draft;
  }

  function normalizeConsent(data) {
    if (!data || typeof data !== 'object') return null;
    var cats = data.categories || data.cats || {};
    var out = {
      v: String(data.v || data.version || ''),
      ts: data.ts || data.timestamp || Date.now(),
      categories: {},
    };
    categoryIds().forEach(function (id) {
      var cat = (cfg.categories || []).find(function (c) {
        return c.id === id;
      });
      if (cat && cat.essential) out.categories[id] = true;
      else out.categories[id] = !!cats[id];
    });
    return out;
  }

  function saveConsent(draft) {
    var payload = {
      v: String(cfg.version || '1'),
      ts: Date.now(),
      categories: {},
    };
    categoryIds().forEach(function (id) {
      var cat = (cfg.categories || []).find(function (c) {
        return c.id === id;
      });
      payload.categories[id] = cat && cat.essential ? true : !!draft[id];
    });
    writeCookie(cfg.cookieName || 'ecc_consent', JSON.stringify(payload), cfg.days);
    state.consent = payload;
    state.showRevision = false;
    pushConsentMode(payload.categories);
    unlockScripts(payload.categories);
    document.dispatchEvent(
      new CustomEvent('ecc:consent', {
        detail: payload,
      })
    );
    return payload;
  }

  function pushConsentMode(cats) {
    if (!cfg.gtmConsentMode) return;
    window.dataLayer = window.dataLayer || [];
    function gtag() {
      window.dataLayer.push(arguments);
    }
    var analytics = cats.analytics ? 'granted' : 'denied';
    var marketing = cats.marketing ? 'granted' : 'denied';
    var prefs = cats.preferences ? 'granted' : 'denied';
    gtag('consent', 'update', {
      ad_storage: marketing,
      ad_user_data: marketing,
      ad_personalization: marketing,
      analytics_storage: analytics,
      functionality_storage: prefs,
      personalization_storage: prefs,
      security_storage: 'granted',
    });
  }

  function unlockScripts(cats) {
    if (!cfg.blockScripts) return;
    var nodes = document.querySelectorAll('script[type="text/plain"][data-ecc-category]');
    nodes.forEach(function (node) {
      var cat = node.getAttribute('data-ecc-category') || '';
      if (!cats[cat]) return;
      var s = document.createElement('script');
      Array.prototype.slice.call(node.attributes).forEach(function (attr) {
        if (attr.name === 'type' || attr.name === 'data-ecc-category') return;
        s.setAttribute(attr.name, attr.value);
      });
      if (node.src) s.src = node.src;
      else s.text = node.textContent || '';
      node.parentNode.insertBefore(s, node);
      node.parentNode.removeChild(node);
    });
  }

  function el(tag, className, html) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (html != null) node.innerHTML = html;
    return node;
  }

  function escapeHtml(str) {
    return String(str == null ? '' : str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function buildUI() {
    applyStyles();
    root.dataset.layout = cfg.layout || 'bottom';
    root.dataset.corner = cfg.cornerSide === 'left' ? 'left' : 'right';
    root.hidden = false;
    root.innerHTML = '';

    var overlay = el('div', 'ecc-overlay');
    overlay.hidden = true;
    overlay.setAttribute('data-ecc-overlay', '');

    var banner = el('div', 'ecc-banner');
    banner.setAttribute('role', 'dialog');
    banner.setAttribute('aria-modal', cfg.layout === 'modal' ? 'true' : 'false');
    banner.setAttribute('aria-labelledby', 'ecc-banner-title');

    if (state.showRevision) {
      banner.appendChild(el('p', 'ecc-revision', escapeHtml(cfg.texts.consent_revision_note || '')));
    }
    banner.appendChild(el('h2', 'ecc-banner__title', escapeHtml(cfg.texts.banner_title || '')));
    banner.querySelector('.ecc-banner__title').id = 'ecc-banner-title';
    banner.appendChild(el('p', 'ecc-banner__text', escapeHtml(cfg.texts.banner_text || '')));

    if (cfg.privacyUrl) {
      var priv = el('p', 'ecc-banner__privacy');
      priv.innerHTML =
        '<a href="' +
        escapeHtml(cfg.privacyUrl) +
        '" target="_blank" rel="noopener noreferrer">' +
        escapeHtml(cfg.texts.privacy_label || 'Privacy') +
        '</a>';
      banner.appendChild(priv);
    }

    var actions = el('div', 'ecc-actions');
    var btnAccept = el('button', 'ecc-btn ecc-btn--accept', escapeHtml(cfg.texts.btn_accept || ''));
    btnAccept.type = 'button';
    btnAccept.addEventListener('click', function () {
      saveConsent(defaultDraft(true));
      hideBanner();
      showReopen(true);
      hideModal();
    });
    actions.appendChild(btnAccept);

    if (cfg.showReject) {
      var btnReject = el('button', 'ecc-btn ecc-btn--reject', escapeHtml(cfg.texts.btn_reject || ''));
      btnReject.type = 'button';
      btnReject.addEventListener('click', function () {
        saveConsent(defaultDraft(false));
        hideBanner();
        showReopen(true);
        hideModal();
      });
      actions.appendChild(btnReject);
    }

    if (cfg.showSettings) {
      var btnSettings = el('button', 'ecc-btn ecc-btn--settings', escapeHtml(cfg.texts.btn_settings || ''));
      btnSettings.type = 'button';
      btnSettings.addEventListener('click', function () {
        openModal();
      });
      actions.appendChild(btnSettings);
    }

    banner.appendChild(actions);

    var modal = el('div', 'ecc-modal');
    modal.hidden = true;
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-labelledby', 'ecc-modal-title');

    var head = el('div', 'ecc-modal__head');
    head.appendChild(el('h2', 'ecc-modal__title', escapeHtml(cfg.texts.modal_title || '')));
    head.lastChild.id = 'ecc-modal-title';
    var close = el('button', 'ecc-modal__close', '×');
    close.type = 'button';
    close.setAttribute('aria-label', cfg.texts.btn_close || 'Close');
    close.addEventListener('click', hideModal);
    head.appendChild(close);
    modal.appendChild(head);
    modal.appendChild(el('p', 'ecc-modal__intro', escapeHtml(cfg.texts.modal_intro || '')));

    var list = el('div', 'ecc-cats');
    (cfg.categories || []).forEach(function (cat) {
      var row = el('div', 'ecc-cat');
      var meta = el('div', 'ecc-cat__meta');
      meta.appendChild(el('p', 'ecc-cat__name', escapeHtml(cat.label || cat.id)));
      meta.appendChild(el('p', 'ecc-cat__desc', escapeHtml(cat.desc || '')));
      row.appendChild(meta);

      var toggle = el('label', 'ecc-toggle');
      var input = document.createElement('input');
      input.type = 'checkbox';
      input.checked = !!state.draft[cat.id] || !!cat.essential;
      input.disabled = !!cat.essential;
      input.dataset.cat = cat.id;
      input.addEventListener('change', function () {
        if (cat.essential) {
          input.checked = true;
          return;
        }
        state.draft[cat.id] = !!input.checked;
      });
      toggle.appendChild(input);
      toggle.appendChild(el('span', 'ecc-toggle__ui'));
      row.appendChild(toggle);
      list.appendChild(row);
    });
    modal.appendChild(list);

    var mActions = el('div', 'ecc-modal__actions');
    var save = el('button', 'ecc-btn ecc-btn--save', escapeHtml(cfg.texts.btn_save || ''));
    save.type = 'button';
    save.addEventListener('click', function () {
      // Read current toggles.
      modal.querySelectorAll('input[data-cat]').forEach(function (inp) {
        var id = inp.getAttribute('data-cat');
        var cat = (cfg.categories || []).find(function (c) {
          return c.id === id;
        });
        state.draft[id] = cat && cat.essential ? true : !!inp.checked;
      });
      saveConsent(state.draft);
      hideBanner();
      hideModal();
      showReopen(true);
    });
    mActions.appendChild(save);

    if (cfg.showReject) {
      var reject2 = el('button', 'ecc-btn ecc-btn--reject', escapeHtml(cfg.texts.btn_reject || ''));
      reject2.type = 'button';
      reject2.addEventListener('click', function () {
        saveConsent(defaultDraft(false));
        hideBanner();
        hideModal();
        showReopen(true);
      });
      mActions.appendChild(reject2);
    }
    modal.appendChild(mActions);

    var reopen = el('button', 'ecc-reopen', escapeHtml(cfg.texts.reopen_label || ''));
    reopen.type = 'button';
    reopen.hidden = true;
    reopen.addEventListener('click', function () {
      openSettings();
    });

    root.appendChild(overlay);
    root.appendChild(banner);
    root.appendChild(modal);
    root.appendChild(reopen);

    overlay.addEventListener('click', function () {
      if (!modal.hidden) hideModal();
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !modal.hidden) hideModal();
    });

    return { banner: banner, modal: modal, overlay: overlay, reopen: reopen };
  }

  var ui = null;
  var enterTimer = null;
  var ENTER_DELAY = Math.max(0, Math.min(5000, parseInt(cfg.enterDelay, 10) || 450));

  function clearEnterTimer() {
    if (enterTimer) {
      clearTimeout(enterTimer);
      enterTimer = null;
    }
  }

  function showBanner() {
    if (!ui) return;
    clearEnterTimer();
    ui.banner.hidden = false;
    ui.banner.classList.remove('is-visible');
    ui.overlay.classList.remove('is-visible');
    if (cfg.layout === 'modal') {
      ui.overlay.hidden = false;
    }
    // Force reflow so the enter transition always runs.
    void ui.banner.offsetWidth;
    enterTimer = setTimeout(function () {
      enterTimer = null;
      if (!ui || ui.banner.hidden) return;
      ui.banner.classList.add('is-visible');
      if (cfg.layout === 'modal') {
        ui.overlay.classList.add('is-visible');
      }
    }, ENTER_DELAY);
  }

  function hideBanner() {
    if (!ui) return;
    clearEnterTimer();
    ui.banner.classList.remove('is-visible');
    ui.banner.hidden = true;
    if (ui.modal.hidden) {
      ui.overlay.classList.remove('is-visible');
      ui.overlay.hidden = true;
    }
  }

  function openModal() {
    if (!ui) return;
    // Sync draft from consent or defaults.
    state.draft = state.consent
      ? Object.assign({}, state.consent.categories)
      : defaultDraft(false);
    essentialIds().forEach(function (id) {
      state.draft[id] = true;
    });
    ui.modal.querySelectorAll('input[data-cat]').forEach(function (inp) {
      var id = inp.getAttribute('data-cat');
      var cat = (cfg.categories || []).find(function (c) {
        return c.id === id;
      });
      inp.checked = cat && cat.essential ? true : !!state.draft[id];
    });
    ui.modal.hidden = false;
    ui.overlay.hidden = false;
    void ui.overlay.offsetWidth;
    ui.overlay.classList.add('is-visible');
  }

  function hideModal() {
    if (!ui) return;
    ui.modal.hidden = true;
    if (ui.banner.hidden || cfg.layout !== 'modal') {
      // Keep overlay if main banner is modal-layout and still visible.
      if (!(cfg.layout === 'modal' && !ui.banner.hidden)) {
        ui.overlay.classList.remove('is-visible');
        ui.overlay.hidden = true;
      }
    }
  }

  function showReopen(on) {
    if (!ui || !cfg.showReopen) return;
    ui.reopen.hidden = !on;
  }

  function openSettings() {
    if (!ui) buildAll();
    state.draft = state.consent
      ? Object.assign({}, state.consent.categories)
      : defaultDraft(false);
    openModal();
    // Keep banner hidden when reopening from button.
    if (state.consent && !state.showRevision) hideBanner();
  }

  function buildAll() {
    ui = buildUI();
  }

  function bindDelegates() {
    document.addEventListener('click', function (e) {
      var t = e.target;
      if (!t) return;
      if (t.closest && t.closest('.ecc-open-settings')) {
        e.preventDefault();
        openSettings();
      }
    });
  }

  function boot() {
    applyStyles();
    var stored = normalizeConsent(parseConsent());
    if (stored && String(stored.v) === String(cfg.version || '1')) {
      state.consent = stored;
      state.draft = Object.assign({}, stored.categories);
      pushConsentMode(stored.categories);
      unlockScripts(stored.categories);
      buildAll();
      hideBanner();
      showReopen(true);
    } else {
      if (stored && String(stored.v) !== String(cfg.version || '1')) {
        state.showRevision = true;
        state.consent = null;
      }
      state.draft = defaultDraft(false);
      buildAll();
      showBanner();
      showReopen(false);
    }
    bindDelegates();
  }

  window.ECC = {
    openSettings: openSettings,
    getConsent: function () {
      return state.consent;
    },
    hasCategory: function (id) {
      return !!(state.consent && state.consent.categories && state.consent.categories[id]);
    },
    reset: function () {
      writeCookie(cfg.cookieName || 'ecc_consent', '', -1);
      state.consent = null;
      state.draft = defaultDraft(false);
      state.showRevision = false;
      buildAll();
      showBanner();
      showReopen(false);
    },
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
