{{-- Shared event search autocomplete (Home + Tickets). --}}
<style>
  .event-suggest-wrap {
    position: relative;
  }
  /* Keep dropdown visible inside hero / sidebar search widgets */
  .event-search .search-item,
  .widget-search,
  .widget-search form {
    overflow: visible !important;
  }
  .event-suggest-dropdown {
    display: none;
    position: absolute;
    left: 0;
    right: 0;
    top: 100%;
    z-index: 1050;
    margin-top: 4px;
    max-height: 320px;
    overflow-y: auto;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
  }
  .event-suggest-dropdown.is-open {
    display: block;
  }
  .event-suggest-item {
    display: block;
    width: 100%;
    text-align: left;
    border: 0;
    background: transparent;
    padding: 10px 14px;
    cursor: pointer;
    border-bottom: 1px solid #f1f5f9;
    color: inherit;
    text-decoration: none;
  }
  .event-suggest-item:last-child {
    border-bottom: 0;
  }
  .event-suggest-item:hover,
  .event-suggest-item.is-active {
    background: #f8fafc;
  }
  .event-suggest-title {
    display: block;
    font-weight: 600;
    color: #0f172a;
    font-size: 0.95rem;
  }
  .event-suggest-meta {
    display: block;
    color: #64748b;
    font-size: 0.8rem;
    margin-top: 2px;
  }
  .event-suggest-empty {
    padding: 12px 14px;
    color: #64748b;
    font-size: 0.85rem;
  }
</style>
<script>
(function () {
  var SUGGEST_URL = @json(route('events.suggest'));
  var MIN_CHARS = 2;
  var DEBOUNCE_MS = 250;

  function debounce(fn, wait) {
    var t;
    return function () {
      var ctx = this, args = arguments;
      clearTimeout(t);
      t = setTimeout(function () { fn.apply(ctx, args); }, wait);
    };
  }

  function ensureWrap(input) {
    var parent = input.parentElement;
    if (!parent) return null;
    if (parent.classList.contains('event-suggest-wrap')) return parent;
    var wrap = document.createElement('div');
    wrap.className = 'event-suggest-wrap';
    parent.insertBefore(wrap, input);
    wrap.appendChild(input);
    return wrap;
  }

  function ensureDropdown(wrap) {
    var dd = wrap.querySelector('.event-suggest-dropdown');
    if (!dd) {
      dd = document.createElement('div');
      dd.className = 'event-suggest-dropdown';
      dd.setAttribute('role', 'listbox');
      wrap.appendChild(dd);
    }
    return dd;
  }

  function render(dd, results) {
    if (!results || !results.length) {
      dd.innerHTML = '<div class="event-suggest-empty">{{ __("No matching events") }}</div>';
      dd.classList.add('is-open');
      return;
    }
    dd.innerHTML = results.map(function (r) {
      var meta = [r.location, r.start_date_label].filter(Boolean).join(' · ');
      return '<a class="event-suggest-item" href="' + r.url + '" role="option">' +
        '<span class="event-suggest-title"></span>' +
        '<span class="event-suggest-meta"></span>' +
      '</a>';
    }).join('');
    Array.prototype.forEach.call(dd.querySelectorAll('.event-suggest-item'), function (el, i) {
      el.querySelector('.event-suggest-title').textContent = results[i].title || '';
      el.querySelector('.event-suggest-meta').textContent = [results[i].location, results[i].start_date_label].filter(Boolean).join(' · ');
    });
    dd.classList.add('is-open');
  }

  function bindInput(input) {
    if (!input || input.dataset.suggestBound === '1') return;
    input.dataset.suggestBound = '1';
    var wrap = ensureWrap(input);
    if (!wrap) return;
    var dd = ensureDropdown(wrap);
    var controller = null;

    var run = debounce(function () {
      var q = (input.value || '').trim();
      if (q.length < MIN_CHARS) {
        dd.classList.remove('is-open');
        dd.innerHTML = '';
        return;
      }
      if (controller) {
        try { controller.abort(); } catch (e) {}
      }
      controller = (typeof AbortController !== 'undefined') ? new AbortController() : null;
      var opts = controller ? { signal: controller.signal } : {};
      fetch(SUGGEST_URL + '?q=' + encodeURIComponent(q), opts)
        .then(function (res) {
          if (!res.ok) {
            throw new Error('suggest_failed');
          }
          return res.json();
        })
        .then(function (data) { render(dd, (data && data.results) ? data.results : []); })
        .catch(function (err) {
          if (err && err.name === 'AbortError') return;
          dd.innerHTML = '<div class="event-suggest-empty">{{ __("Unable to load suggestions") }}</div>';
          dd.classList.add('is-open');
        });
    }, DEBOUNCE_MS);

    input.addEventListener('input', run);
    input.addEventListener('focus', function () {
      if ((input.value || '').trim().length >= MIN_CHARS && dd.innerHTML) {
        dd.classList.add('is-open');
      }
    });
    document.addEventListener('click', function (e) {
      if (!wrap.contains(e.target)) {
        dd.classList.remove('is-open');
      }
    });
  }

  function init() {
    document.querySelectorAll('[data-event-suggest]').forEach(bindInput);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
</script>
