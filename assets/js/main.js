(function () {
  'use strict';

  /* ---------- Header: shadow on scroll + mobile menu ---------- */
  var header = document.querySelector('.site-header');
  var menuBtn = document.querySelector('.menu-btn');
  var nav = document.getElementById('site-nav');

  function onScroll() {
    if (header) header.classList.toggle('scrolled', window.scrollY > 8);
  }
  onScroll();
  window.addEventListener('scroll', onScroll, { passive: true });

  if (menuBtn && nav) {
    menuBtn.addEventListener('click', function () {
      var open = nav.classList.toggle('open');
      menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && nav.classList.contains('open')) {
        nav.classList.remove('open');
        menuBtn.setAttribute('aria-expanded', 'false');
        menuBtn.focus();
      }
    });
  }

  /* ---------- Footer year ---------- */
  var yr = document.getElementById('year');
  if (yr) yr.textContent = new Date().getFullYear();

  /* ---------- pH scale (home) ---------- */
  var tabs = document.querySelectorAll('.ph-tab');
  var track = document.querySelector('.ph-track');
  if (tabs.length && track) {
    var num = document.getElementById('ph-num');
    var title = document.getElementById('ph-title');
    var text = document.getElementById('ph-text');

    function select(tab) {
      tabs.forEach(function (t) { t.setAttribute('aria-selected', t === tab ? 'true' : 'false'); });
      track.style.setProperty('--from', tab.dataset.from);
      track.style.setProperty('--to', tab.dataset.to);
      num.textContent = 'pH ' + tab.dataset.from + '\u2013' + tab.dataset.to;
      title.textContent = tab.dataset.title;
      text.textContent = tab.dataset.text;
    }
    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () { select(tab); });
    });
    select(tabs[0]);
  }

  /* ---------- Product filter ---------- */
  var filters = document.querySelectorAll('.filter');
  var cards = document.querySelectorAll('.prod');
  if (filters.length && cards.length) {
    var counter = document.getElementById('count');

    function apply(cat) {
      var shown = 0;
      cards.forEach(function (c) {
        var match = cat === 'all' || c.dataset.cat === cat;
        c.hidden = !match;
        if (match) shown++;
      });
      filters.forEach(function (f) { f.setAttribute('aria-pressed', f.dataset.filter === cat ? 'true' : 'false'); });
      if (counter) counter.textContent = shown + (shown === 1 ? ' product' : ' products');
    }
    filters.forEach(function (f) {
      f.addEventListener('click', function () {
        apply(f.dataset.filter);
        history.replaceState(null, '', f.dataset.filter === 'all' ? location.pathname : '#' + f.dataset.filter);
      });
    });
    var hash = location.hash.replace('#', '');
    apply(['industrial', 'domestic', 'consumables'].indexOf(hash) > -1 ? hash : 'all');
  }

  /* ---------- Contact form ---------- */
  var form = document.getElementById('contact-form');
  if (form) {
    var status = document.getElementById('form-status');
    var btn = form.querySelector('button[type="submit"]');
    var params = new URLSearchParams(location.search);

    // Pre-fill from a product enquiry link
    var typeSel = form.elements['enquiry_type'];
    if (params.get('type') && typeSel) {
      Array.prototype.forEach.call(typeSel.options, function (o) {
        if (o.value === params.get('type')) typeSel.value = o.value;
      });
    }
    if (params.get('product') && form.elements['message'] && !form.elements['message'].value) {
      form.elements['message'].value = 'I would like to enquire about: ' + params.get('product') + '.\n\n';
    }

    // Timestamp used by the server to spot instant bot submissions
    if (form.elements['ts']) form.elements['ts'].value = Math.floor(Date.now() / 1000);

    function show(state, msg) {
      status.dataset.state = state;
      status.textContent = msg;
      status.focus();
    }

    function setFieldError(field, message) {
      var isValid = !message;
      field.setCustomValidity(message);
      field.setAttribute('aria-invalid', isValid ? 'false' : 'true');
      field.classList.toggle('is-invalid', !isValid);
      return isValid;
    }

    function validateField(field) {
      var value = (field.value || '').trim();
      var id = field.id;
      var blocked = /(test|spam|dummy|example|sample|asdf|qwerty|n\/a|na|null|undefined)/i;

      if (id === 'name') {
        if (!value) return setFieldError(field, 'Please enter your full name.');
        if (value.length < 2) return setFieldError(field, 'Name must be at least 2 characters long.');
        if (!/^[A-Za-zÀ-ÖØ-öø-ÿ' .-]{2,80}$/.test(value)) return setFieldError(field, 'Use letters, spaces, apostrophes, periods or hyphens only.');
        if (blocked.test(value)) return setFieldError(field, 'Please enter a realistic name.');
        return setFieldError(field, '');
      }

      if (id === 'company') {
        if (!value) return setFieldError(field, '');
        if (value.length < 2) return setFieldError(field, 'Company name must be at least 2 characters.');
        if (!/^[A-Za-z0-9À-ÖØ-öø-ÿ&.,' -]{2,100}$/.test(value)) return setFieldError(field, 'Use a valid company name without special symbols.');
        if (blocked.test(value)) return setFieldError(field, 'Please enter a real company name.');
        return setFieldError(field, '');
      }

      if (id === 'email') {
        if (!value) return setFieldError(field, 'Please enter your email address.');
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/i.test(value)) return setFieldError(field, 'Please enter a valid email address.');
        if (blocked.test(value)) return setFieldError(field, 'Please enter a real email address.');
        return setFieldError(field, '');
      }

      if (id === 'phone') {
        if (!value) return setFieldError(field, '');
        if (!/^[0-9+()\-\s]{7,20}$/.test(value)) return setFieldError(field, 'Phone number must contain 7 to 20 digits, spaces, +, parentheses or dashes.');
        if (value.replace(/\D/g, '').length < 7) return setFieldError(field, 'Please enter a realistic phone number.');
        if (blocked.test(value)) return setFieldError(field, 'Please enter a real phone number.');
        return setFieldError(field, '');
      }

      return setFieldError(field, '');
    }

    function validateForm() {
      var fields = ['name', 'company', 'email', 'phone'];
      var valid = true;
      fields.forEach(function (id) {
        var field = form.elements[id];
        if (!field) return;
        if (!validateField(field)) valid = false;
      });
      return valid;
    }

    ['name', 'company', 'email', 'phone'].forEach(function (id) {
      var field = form.elements[id];
      if (!field) return;
      field.addEventListener('input', function () {
        validateField(field);
      });
      field.addEventListener('blur', function () {
        validateField(field);
      });
    });

    // Result of a non-JavaScript submission (PHP redirects back here)
    if (params.get('status') === 'sent') show('ok', 'Thank you. Your message has been sent and we will reply within one working day.');
    if (params.get('status') === 'error') show('error', 'Your message could not be sent. Please check the form and try again, or call us directly.');

    form.addEventListener('submit', function (e) {
      var allValid = validateForm();
      if (!form.checkValidity() || !allValid) {
        e.preventDefault();
        show('error', 'Please correct the highlighted fields before sending your message.');
        var firstInvalid = form.querySelector('.is-invalid, :invalid');
        if (firstInvalid) firstInvalid.focus();
        return;
      }
      e.preventDefault();
      btn.disabled = true;
      var label = btn.textContent;
      btn.textContent = 'Sending\u2026';

      fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: { 'Accept': 'application/json' }
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.ok) {
            show('ok', data.message || 'Thank you. Your message has been sent.');
            form.reset();
            if (form.elements['ts']) form.elements['ts'].value = Math.floor(Date.now() / 1000);
          } else {
            show('error', data.message || 'Your message could not be sent. Please try again.');
          }
        })
        .catch(function () {
          show('error', 'We could not reach the server. Please try again, or call us directly.');
        })
        .finally(function () {
          btn.disabled = false;
          btn.textContent = label;
        });
    });
  }
})();
