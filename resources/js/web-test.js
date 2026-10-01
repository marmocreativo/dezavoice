import { RetellWebClient } from 'retell-client-js-sdk';

const root = document.querySelector('[data-web-test]');

if (root) {
    const $ = (selector) => root.querySelector(selector);
    const ui = {
        start: $('[data-start]'),
        stop: $('[data-stop]'),
        status: $('[data-status]'),
        error: $('[data-error]'),
        result: $('[data-result]'),
        order: $('[data-order]'),
        consumed: $('[data-consumed]'),
        remaining: $('[data-remaining]'),
    };

    let client = null;
    let callUuid = null;
    let pollTimer = null;
    let pollCount = 0;

    const setStatus = (text) => { ui.status.textContent = text; };

    const showError = (message) => {
        ui.error.textContent = message;
        ui.error.classList.remove('hidden');
    };

    const clearError = () => {
        ui.error.textContent = '';
        ui.error.classList.add('hidden');
    };

    // idle | connecting | live | finishing
    const setState = (state) => {
        ui.start.classList.toggle('hidden', state !== 'idle');
        ui.stop.classList.toggle('hidden', state === 'idle');
        ui.stop.disabled = state === 'finishing';
    };

    const endClient = () => {
        try { client?.stopCall(); } catch (_) { /* ya estaba cerrado */ }
        client = null;
    };

    const stopPolling = () => {
        clearInterval(pollTimer);
        pollTimer = null;
    };

    const fmt = (value) => (value === null || value === undefined ? '—' : String(Number(value)));

    const showResult = (data) => {
        ui.order.textContent = data.pedido ?? 'No se registró ningún pedido en esta llamada.';
        ui.consumed.textContent = fmt(data.minutos_consumidos);
        ui.remaining.textContent = fmt(data.uso?.restantes);
        ui.result.classList.remove('hidden');
    };

    const poll = async () => {
        if (!callUuid) return;
        pollCount += 1;

        try {
            const response = await fetch(root.dataset.statusUrl.replace('__CALL__', callUuid), {
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) return;

            const data = await response.json();

            if (data.status === 'ended') {
                stopPolling();
                showResult(data);
                endClient();
                setState('idle');
                setStatus('Llamada terminada.');
            } else if (pollCount > 120) {
                stopPolling();
                setState('idle');
                setStatus('La llamada sigue procesándose. Recarga la página en unos segundos.');
            }
        } catch (_) {
            // se reintenta en el siguiente ciclo
        }
    };

    ui.start.addEventListener('click', async () => {
        clearError();
        ui.result.classList.add('hidden');
        setState('connecting');
        setStatus('Validando minutos…');

        let data;
        try {
            const response = await fetch(root.dataset.startUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': root.dataset.csrf,
                },
                body: '{}',
            });
            data = await response.json();
            if (!response.ok) throw new Error(data.message || 'No se pudo iniciar la llamada.');
        } catch (error) {
            showError(error.message);
            setState('idle');
            setStatus('Listo para llamar.');
            return;
        }

        callUuid = data.call_uuid;
        pollCount = 0;
        client = new RetellWebClient();

        client.on('call_started', () => {
            setState('live');
            setStatus('En llamada. Habla con la asistente.');
        });
        client.on('call_ended', () => {
            setState('finishing');
            setStatus('Llamada terminada. Guardando resultados…');
        });
        client.on('error', (error) => {
            showError('Error en la llamada: ' + (error?.message ?? 'revisa el micrófono y vuelve a intentar.'));
            endClient();
            setState('finishing');
            setStatus('Guardando resultados…');
        });

        setStatus('Conectando…');

        try {
            await client.startCall({
                callId: data.call_id,
                accessToken: data.access_token,
                transport: data.transport,
                iceServers: data.ice_servers,
            });

            setState('live');
            setStatus('En llamada. Habla con la asistente.');
        } catch (error) {
            showError('No se pudo conectar el audio: ' + (error?.message ?? 'permite el micrófono e intenta de nuevo.'));
            endClient();
            setState('finishing');
            setStatus('Guardando resultados…');
        }

        stopPolling();
        pollTimer = setInterval(poll, 3000);
    });

    ui.stop.addEventListener('click', () => {
        setState('finishing');
        setStatus('Terminando llamada…');
        endClient();
    });
}