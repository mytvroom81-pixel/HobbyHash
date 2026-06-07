(function () {
  'use strict';

  function cfg() {
    return window.HOBC_I18N || {};
  }

  function cookieName() {
    return cfg().cookieName || 'hobc_lang';
  }

  function storageKey() {
    return cfg().storageKey || 'hobc_lang';
  }

  function defaultLocale() {
    return cfg().defaultLocale || 'en';
  }

  function localeSlugs() {
    return cfg().localeSlugs || {};
  }

  function slugValues() {
    return Object.values(localeSlugs());
  }

  function setCookie(name, value) {
    document.cookie = name + '=' + encodeURIComponent(value) + ';path=/;max-age=31536000;samesite=lax';
  }

  function persistLocale(locale) {
    if (!locale) {
      return;
    }
    setCookie(cookieName(), locale);
    try {
      window.localStorage.setItem(storageKey(), locale);
    } catch (e) {}
  }

  function normalizePath(path) {
    if (!path || path === '') {
      return '/';
    }
    if (path !== '/' && !path.endsWith('/') && !/\.[a-z0-9]+$/i.test(path)) {
      path += '/';
    }
    return path;
  }

  function stripLocalePrefix(path) {
    path = normalizePath(path || '/');
    var slugs = slugValues().slice().sort(function (a, b) {
      return b.length - a.length;
    });
    for (var i = 0; i < slugs.length; i += 1) {
      var slug = slugs[i];
      if (path === '/' + slug + '/') {
        return '/';
      }
      var prefix = '/' + slug + '/';
      if (path.indexOf(prefix) === 0) {
        return normalizePath('/' + path.slice(prefix.length));
      }
    }
    return path;
  }

  function buildPathWithLocale(path, locale) {
    path = normalizePath(path || '/');
    if (!cfg().prefixUrls || !locale || locale === defaultLocale()) {
      return path;
    }
    var slug = localeSlugs()[locale] || '';
    if (!slug) {
      return path;
    }
    if (path === '/') {
      return '/' + slug + '/';
    }
    return '/' + slug + path;
  }

  function currentUrlWithLang(locale) {
    var url = new URL(window.location.href);
    var canonicalPath = cfg().canonicalPath || stripLocalePrefix(url.pathname);

    if (cfg().prefixUrls) {
      url.pathname = buildPathWithLocale(canonicalPath, locale);
      url.searchParams.delete('lang');
      return url.pathname + url.search + url.hash;
    }

    if (!locale || locale === defaultLocale()) {
      url.searchParams.delete('lang');
    } else {
      url.searchParams.set('lang', locale);
    }
    return url.pathname + url.search + url.hash;
  }

  function syncDocumentDirection() {
    var locale = cfg().locale || document.documentElement.getAttribute('lang') || defaultLocale();
    var dir = cfg().dir || document.documentElement.getAttribute('dir') || 'ltr';
    document.documentElement.setAttribute('lang', locale);
    document.documentElement.setAttribute('dir', dir);
    document.documentElement.setAttribute('data-hobc-locale', locale);
    if (cfg().canonicalPath) {
      document.documentElement.setAttribute('data-hobc-canonical-path', cfg().canonicalPath);
    }
  }

  function closeLangMenu(root) {
    if (!root) return;
    var trigger = root.querySelector('[data-lang-trigger]');
    var menu = root.querySelector('[data-lang-menu]');
    if (!trigger || !menu) return;
    trigger.setAttribute('aria-expanded', 'false');
    menu.hidden = true;
  }

  function openLangMenu(root) {
    if (!root) return;
    document.querySelectorAll('[data-lang-switcher]').forEach(function (other) {
      if (other !== root) closeLangMenu(other);
    });
    var trigger = root.querySelector('[data-lang-trigger]');
    var menu = root.querySelector('[data-lang-menu]');
    if (!trigger || !menu) return;
    trigger.setAttribute('aria-expanded', 'true');
    menu.hidden = false;
    var active = menu.querySelector('.lang-switcher-option.is-active');
    if (active) active.focus();
  }

  function switchLocale(locale) {
    if (!locale) return;
    persistLocale(locale);
    window.location.assign(currentUrlWithLang(locale));
  }

  function initLanguageSwitcher() {
    document.querySelectorAll('[data-lang-switcher]').forEach(function (root) {
      if (root.dataset.langBound === '1') {
        return;
      }
      root.dataset.langBound = '1';

      var trigger = root.querySelector('[data-lang-trigger]');
      var menu = root.querySelector('[data-lang-menu]');
      if (!trigger || !menu) {
        return;
      }

      trigger.addEventListener('click', function (event) {
        event.stopPropagation();
        var expanded = trigger.getAttribute('aria-expanded') === 'true';
        if (expanded) {
          closeLangMenu(root);
        } else {
          openLangMenu(root);
        }
      });

      menu.querySelectorAll('.lang-switcher-option').forEach(function (option) {
        option.addEventListener('click', function () {
          switchLocale(String(option.dataset.locale || '').trim());
        });
        option.addEventListener('keydown', function (event) {
          if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            switchLocale(String(option.dataset.locale || '').trim());
          }
        });
      });
    });

    document.addEventListener('click', function (event) {
      document.querySelectorAll('[data-lang-switcher]').forEach(function (root) {
        if (!root.contains(event.target)) {
          closeLangMenu(root);
        }
      });
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        document.querySelectorAll('[data-lang-switcher]').forEach(closeLangMenu);
      }
    });
  }

  syncDocumentDirection();
  document.addEventListener('DOMContentLoaded', function () {
    syncDocumentDirection();
    initLanguageSwitcher();
  });
})();
