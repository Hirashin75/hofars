// HOFARS assistant widget. Talks to hofars-help.php; all message text is inserted with textContent (never innerHTML).
(function () {
    'use strict';

    var root = document.getElementById('chatWidget');
    if (!root) { return; }

    var toggle = document.getElementById('chatToggle');
    var panel = document.getElementById('chatPanel');
    var log = document.getElementById('chatLog');
    var chips = document.getElementById('chatChips');
    var form = document.getElementById('chatForm');
    var input = document.getElementById('chatInput');
    var sendBtn = document.getElementById('chatSend');
    var modeLabel = document.getElementById('chatMode');
    var csrf = root.dataset.csrf;

    var loaded = false;
    var busy = false;
    var suggestions = [];

    var WELCOME = 'Hi! I can help you use HOFARS. Ask me about reporting, statuses, uploads or accounts.';

    function setMode(mode) {
        modeLabel.textContent = mode === 'ai' ? 'AI assistant' : 'Built-in help';
    }

    function scrollDown() { log.scrollTop = log.scrollHeight; }

    function addMessage(role, text, notice) {
        var wrap = document.createElement('div');
        wrap.className = 'chat-msg chat-' + role;
        var bubble = document.createElement('div');
        bubble.className = 'chat-bubble';
        bubble.textContent = text;
        wrap.appendChild(bubble);
        if (notice) {
            var note = document.createElement('small');
            note.className = 'chat-notice';
            note.textContent = notice;
            wrap.appendChild(note);
        }
        log.appendChild(wrap);
        scrollDown();
        return wrap;
    }

    function showTyping() {
        var wrap = document.createElement('div');
        wrap.className = 'chat-msg chat-assistant chat-typing';
        wrap.setAttribute('aria-label', 'Assistant is typing');
        wrap.innerHTML = '<div class="chat-bubble"><span></span><span></span><span></span></div>';
        log.appendChild(wrap);
        scrollDown();
        return wrap;
    }

    function renderChips() {
        chips.textContent = '';
        suggestions.forEach(function (text) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'chat-chip';
            b.textContent = text;
            b.addEventListener('click', function () { send(text); });
            chips.appendChild(b);
        });
    }

    function setBusy(state) {
        busy = state;
        sendBtn.disabled = state;
        input.disabled = state;
    }

    function parse(res) {
        // A non-JSON reply means the session/CSRF check failed (419) or the server errored.
        return res.json().catch(function () {
            throw new Error(res.status === 419
                ? 'Your session expired. Please refresh the page.'
                : 'Something went wrong. Please try again.');
        }).then(function (data) {
            if (!res.ok) { throw new Error(data.error || 'Something went wrong. Please try again.'); }
            return data;
        });
    }

    function load() {
        loaded = true;
        fetch('hofars-help.php', { credentials: 'same-origin' })
            .then(parse)
            .then(function (data) {
                setMode(data.mode);
                suggestions = data.suggestions || [];
                if (!data.messages || !data.messages.length) {
                    addMessage('assistant', WELCOME);
                    renderChips();
                } else {
                    data.messages.forEach(function (m) { addMessage(m.role, m.content); });
                }
            })
            .catch(function (err) {
                loaded = false;
                addMessage('assistant', err.message);
            });
    }

    function send(text) {
        text = (text || '').trim();
        if (!text || busy) { return; }
        chips.textContent = '';
        addMessage('user', text);
        input.value = '';
        autosize();
        setBusy(true);
        var typing = showTyping();

        var body = new FormData();
        body.append('csrf', csrf);
        body.append('message', text);

        fetch('hofars-help.php', { method: 'POST', body: body, credentials: 'same-origin' })
            .then(parse)
            .then(function (data) {
                typing.remove();
                setMode(data.mode);
                addMessage('assistant', data.reply, data.notice);
            })
            .catch(function (err) {
                typing.remove();
                addMessage('assistant', err.message);
            })
            .then(function () {
                setBusy(false);
                input.focus();
            });
    }

    function clearChat() {
        var body = new FormData();
        body.append('csrf', csrf);
        body.append('action', 'clear');
        fetch('hofars-help.php', { method: 'POST', body: body, credentials: 'same-origin' })
            .then(parse)
            .then(function () {
                log.textContent = '';
                addMessage('assistant', WELCOME);
                renderChips();
            })
            .catch(function (err) { addMessage('assistant', err.message); });
    }

    function open() {
        panel.hidden = false;
        root.classList.add('open');
        toggle.setAttribute('aria-expanded', 'true');
        if (!loaded) { load(); }
        input.focus();
    }

    function close() {
        panel.hidden = true;
        root.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.focus();
    }

    function autosize() {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 110) + 'px';
    }

    toggle.addEventListener('click', function () { panel.hidden ? open() : close(); });
    document.getElementById('chatClose').addEventListener('click', close);
    document.getElementById('chatClear').addEventListener('click', clearChat);
    form.addEventListener('submit', function (e) { e.preventDefault(); send(input.value); });
    input.addEventListener('input', autosize);
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(input.value); }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !panel.hidden) { close(); }
    });
})();
