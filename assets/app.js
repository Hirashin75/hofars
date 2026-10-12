// HOFARS front-end behaviour. All data handling is server-side; this file only adds small UI niceties.
(function () {
    'use strict';

    // ---- Dark mode ----
    var root = document.documentElement;
    var themeBtn = document.getElementById('themeToggle');

    function paintThemeIcon() {
        document.querySelectorAll('.theme-icon').forEach(function (el) {
            el.textContent = root.dataset.theme === 'dark' ? '☀️' : '🌙';
        });
    }
    paintThemeIcon();

    if (themeBtn) {
        themeBtn.addEventListener('click', function () {
            var dark = root.dataset.theme !== 'dark';
            if (dark) { root.dataset.theme = 'dark'; } else { delete root.dataset.theme; }
            try { localStorage.setItem('theme', dark ? 'dark' : 'light'); } catch (e) {}
            paintThemeIcon();
        });
    }

    // ---- User dropdown ----
    var menuBtn = document.getElementById('userMenuBtn');
    var dropdown = document.getElementById('userDropdown');
    if (menuBtn && dropdown) {
        menuBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            dropdown.classList.toggle('show');
        });
        document.addEventListener('click', function (e) {
            if (!dropdown.contains(e.target)) { dropdown.classList.remove('show'); }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { dropdown.classList.remove('show'); }
        });
    }

    // ---- Login / sign-up switch ----
    var loginBox = document.getElementById('loginBox');
    var signupBox = document.getElementById('signupBox');
    function swap(showSignup) {
        if (!loginBox || !signupBox) { return; }
        loginBox.hidden = showSignup;
        signupBox.hidden = !showSignup;
    }
    var showSignup = document.getElementById('showSignup');
    var showLogin = document.getElementById('showLogin');
    if (showSignup) { showSignup.addEventListener('click', function (e) { e.preventDefault(); swap(true); }); }
    if (showLogin) { showLogin.addEventListener('click', function (e) { e.preventDefault(); swap(false); }); }

    // ---- Show / hide password ----
    document.querySelectorAll('.password-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.dataset.target);
            if (!input) { return; }
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        });
    });

    // ---- Live password rules on sign-up ----
    var pw = document.getElementById('signupPassword');
    var rules = document.getElementById('passwordRules');
    if (pw && rules) {
        var checks = {
            length: function (v) { return v.length >= 8; },
            upper: function (v) { return /[A-Z]/.test(v); },
            number: function (v) { return /[0-9]/.test(v); }
        };
        pw.addEventListener('input', function () {
            rules.querySelectorAll('li').forEach(function (li) {
                li.classList.toggle('ok', checks[li.dataset.rule](pw.value));
            });
        });
    }

    // ---- Photo preview on the report form ----
    var photo = document.getElementById('photo');
    var preview = document.getElementById('photoPreview');
    if (photo && preview) {
        photo.addEventListener('change', function () {
            var file = photo.files && photo.files[0];
            if (!file) { preview.hidden = true; return; }
            preview.src = URL.createObjectURL(file);
            preview.hidden = false;
        });
    }

    // ---- Confirm before destructive forms ----
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!window.confirm(form.dataset.confirm)) { e.preventDefault(); }
        });
    });
})();
