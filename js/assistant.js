(function () {
    const fab = document.getElementById('ai-fab');
    const modal = document.getElementById('ai-modal');
    const messages = document.getElementById('ai-messages');
    const form = document.getElementById('ai-form');
    const input = document.getElementById('ai-input');
    const send = document.getElementById('ai-send');
    const typing = document.getElementById('ai-typing');

    if (!fab || !modal || !messages || !form || !input || !send || !typing) return;

    function openAssistant() {
        modal.hidden = false;
        fab.setAttribute('aria-expanded', 'true');
        document.body.classList.add('ai-open');
        setTimeout(function () { input.focus(); }, 80);
    }

    function closeAssistant() {
        modal.hidden = true;
        fab.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('ai-open');
        fab.focus();
    }

    function scrollToEnd() {
        messages.scrollTop = messages.scrollHeight;
    }

    function addMessage(role, text, confirmation) {
        const item = document.createElement('article');
        item.className = 'ai-message ' + role;

        const avatar = document.createElement('div');
        avatar.className = 'ai-avatar';
        avatar.textContent = role === 'user' ? 'Você' : 'AI';

        const bubble = document.createElement('div');
        bubble.className = 'ai-bubble';
        bubble.textContent = text;

        if (confirmation && confirmation.id) {
            const actions = document.createElement('div');
            actions.className = 'ai-confirm-actions';

            const confirm = document.createElement('button');
            confirm.type = 'button';
            confirm.className = 'btn';
            confirm.textContent = confirmation.label || 'Confirmar';
            confirm.addEventListener('click', function () {
                actions.remove();
                sendPayload({ confirm_action: confirmation.id });
            });

            const cancel = document.createElement('button');
            cancel.type = 'button';
            cancel.className = 'btn btn-secondary';
            cancel.textContent = 'Cancelar';
            cancel.addEventListener('click', function () {
                actions.remove();
                sendPayload({ cancel_action: confirmation.id });
            });

            actions.append(confirm, cancel);
            bubble.appendChild(actions);
        }

        item.append(avatar, bubble);
        messages.appendChild(item);
        scrollToEnd();
    }

    function setBusy(isBusy) {
        fab.classList.toggle('is-busy', isBusy);
        typing.hidden = !isBusy;
        send.disabled = isBusy;
        input.disabled = isBusy;
        if (isBusy) scrollToEnd();
    }

    async function sendPayload(payload) {
        setBusy(true);

        try {
            const response = await fetch(modal.dataset.endpoint || 'assistant.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': modal.dataset.csrfToken || ''
                },
                body: JSON.stringify(payload)
            });
            const data = await response.json();
            addMessage('assistant', data.reply || 'Não consegui responder isso agora.', data.confirmation);
        } catch (error) {
            addMessage('assistant', 'Não consegui conectar com o assistente agora. Verifique a conexão local e tente novamente.');
        } finally {
            setBusy(false);
            input.focus();
        }
    }

    fab.addEventListener('click', openAssistant);
    modal.querySelectorAll('[data-ai-close]').forEach(function (button) {
        button.addEventListener('click', closeAssistant);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !modal.hidden) closeAssistant();
    });

    input.addEventListener('input', function () {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 140) + 'px';
    });

    input.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            form.requestSubmit();
        }
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        const text = input.value.trim();
        if (!text) return;

        addMessage('user', text);
        input.value = '';
        input.style.height = 'auto';
        sendPayload({ message: text });
    });
})();
