/**
 * Jarvis — "Deck" voice assistant
 *
 * A dropdown voice panel with a continuous, full-duplex conversation:
 * the microphone keeps listening while Jarvis speaks, so the admin can
 * interrupt (barge in). Echo of Jarvis's own voice is filtered out.
 */
(function () {
    'use strict';

    const CLOSE_PHRASE = /^(?:ok(?:ay)?\s+)?(?:thanks?(?:\s+you)?(?:\s+(?:jarvis|so much|very much))?|that'?s all|goodbye|bye|stop listening|end (?:the )?session)[.!\s]*$/i;
    const CONFIRM_PHRASE = /^(?:yes|yeah|yep|confirm(?:ed)?|do it|go ahead|proceed|sure|okay|ok|please do)\b/i;
    const CANCEL_PHRASE = /^(?:no|nope|cancel|stop|don'?t|never ?mind|abort)\b/i;
    const SECRET_PHRASE = /\b(?:password|passcode|pin|api key|secret)\s+(?:is|to|as|equals|:)\s+\S+/i;

    document.addEventListener('DOMContentLoaded', function () {
        const config = window.jarvisConfig;
        const panel = document.getElementById('jarvisPanel');
        if (!config || !panel) return;

        const el = {
            launcher: document.getElementById('jarvisOpen'),
            close: document.getElementById('jarvisClose'),
            orb: document.getElementById('jarvisOrb'),
            status: document.getElementById('jarvisStatus'),
            modeText: document.getElementById('jarvisModeText'),
            conversation: document.getElementById('jarvisConversation'),
            composer: document.getElementById('jarvisComposer'),
            input: document.getElementById('jarvisInput'),
            keyboard: document.getElementById('jarvisKeyboard'),
            mic: document.getElementById('jarvisMic'),
            micLabel: document.getElementById('jarvisMicLabel'),
            clear: document.getElementById('jarvisClear')
        };

        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        const state = {
            open: false,
            session: false,
            busy: false,
            speaking: false,
            recognizing: false,
            recognition: null,
            finalBuffer: '',
            interimText: '',
            utteranceTimer: null,
            interimCard: null,
            spokenText: '',
            speechGen: 0,
            finishSpeech: null,
            audio: null,
            audioUrl: null,
            audioCtx: null,
            speechAnalyser: null,
            micStream: null,
            micAnalyser: null,
            levelRaf: null,
            useBrowserVoice: false,
            messages: [],
            pending: null,
            greeted: false,
            startedAt: Date.now()
        };

        // ------------------------------------------------------------
        // Rendering helpers
        // ------------------------------------------------------------

        function phase() {
            if (state.busy) return 'thinking';
            if (state.speaking && state.recognizing) return 'duplex';
            if (state.speaking) return 'speaking';
            if (state.recognizing && state.session) return 'listening';
            return 'idle';
        }

        function render() {
            const current = phase();
            panel.dataset.state = current;
            panel.dataset.session = state.session ? 'on' : 'off';
            panel.dataset.duplex = state.session && state.recognizing ? 'on' : 'off';
            const labels = {
                thinking: 'Thinking…',
                duplex: 'Listening & Speaking…',
                speaking: 'Speaking…',
                listening: 'Listening…',
                idle: state.session ? 'Connecting microphone…' : 'Tap the mic to talk'
            };
            el.status.textContent = labels[current];
            el.modeText.textContent = !SpeechRecognition
                ? 'Voice input needs Chrome or Edge — type instead'
                : (state.session && state.recognizing ? 'Duplex Mode Active' : 'Duplex Mode Ready');
            el.micLabel.textContent = state.session ? 'Tap to end' : 'Tap to speak';
            el.mic.setAttribute('aria-label', state.session ? 'Stop talking' : 'Start talking');
            if (el.launcher) {
                el.launcher.classList.toggle('is-live', state.session || state.speaking);
                el.launcher.setAttribute('aria-expanded', state.open ? 'true' : 'false');
            }
        }

        function elapsedLabel() {
            const seconds = Math.max(0, Math.floor((Date.now() - state.startedAt) / 1000));
            return String(Math.floor(seconds / 60)).padStart(2, '0') + ':' + String(seconds % 60).padStart(2, '0');
        }

        function waveBars(seed, count) {
            const wave = document.createElement('div');
            wave.className = 'deck-wave';
            wave.setAttribute('aria-hidden', 'true');
            let hash = 0;
            for (let i = 0; i < seed.length; i++) hash = (hash * 31 + seed.charCodeAt(i)) >>> 0;
            for (let i = 0; i < count; i++) {
                hash = (hash * 1103515245 + 12345) >>> 0;
                const envelope = Math.sin((i / count) * Math.PI) * 0.6 + 0.4;
                const bar = document.createElement('i');
                bar.style.height = Math.round((20 + (hash % 80)) * envelope) + '%';
                bar.style.animationDelay = (-(hash % 900) / 1000) + 's';
                wave.appendChild(bar);
            }
            return wave;
        }

        function clearEmptyState() {
            const empty = el.conversation.querySelector('.deck-empty');
            if (empty) empty.remove();
        }

        function scrollToEnd() {
            el.conversation.scrollTop = el.conversation.scrollHeight;
        }

        function addCard(role, text, options) {
            options = options || {};
            clearEmptyState();
            const card = document.createElement('article');
            card.className = 'deck-msg ' + (role === 'user' ? 'user' : 'ai') + (options.interim ? ' interim' : '') + (options.error ? ' error' : '');

            const avatar = document.createElement('div');
            avatar.className = 'deck-avatar';
            avatar.setAttribute('aria-hidden', 'true');
            avatar.innerHTML = role === 'user'
                ? '<i class="fas fa-user"></i>'
                : '<span class="deck-bars"><i></i><i></i><i></i><i></i><i></i></span>';

            const body = document.createElement('div');
            body.className = 'deck-msg-body';
            const meta = document.createElement('div');
            meta.className = 'deck-msg-meta';
            const name = document.createElement('span');
            name.className = 'deck-msg-name';
            name.textContent = role === 'user' ? 'You' : 'Jarvis (AI)';
            const time = document.createElement('span');
            time.className = 'deck-msg-time end';
            time.textContent = elapsedLabel();
            meta.append(name, time);

            const paragraph = document.createElement('p');
            paragraph.className = 'deck-msg-text';
            paragraph.textContent = text;

            const foot = document.createElement('div');
            foot.className = 'deck-msg-foot';
            foot.appendChild(waveBars(text || role, 46));
            if (role !== 'user' && !options.interim) {
                const replay = document.createElement('button');
                replay.type = 'button';
                replay.className = 'deck-replay';
                replay.setAttribute('aria-label', 'Play this reply aloud');
                replay.innerHTML = '<i class="fas fa-volume-high"></i>';
                replay.addEventListener('click', function () {
                    ensureAudioContext();
                    speak(paragraph.textContent, card);
                });
                foot.appendChild(replay);
            }

            body.append(meta, paragraph, foot);
            card.append(avatar, body);
            el.conversation.appendChild(card);
            scrollToEnd();
            return card;
        }

        function setInterim(text) {
            if (!text) {
                if (state.interimCard) state.interimCard.remove();
                state.interimCard = null;
                return;
            }
            if (!state.interimCard) {
                state.interimCard = addCard('user', text, {interim: true});
            } else {
                state.interimCard.querySelector('.deck-msg-text').textContent = text;
                scrollToEnd();
            }
        }

        // ------------------------------------------------------------
        // Networking
        // ------------------------------------------------------------

        async function api(payload) {
            const response = await fetch(config.apiUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': config.csrfToken
                },
                body: JSON.stringify(payload)
            });
            let result = {};
            try { result = await response.json(); } catch (error) { result = {}; }
            if (response.status === 401) throw new Error('Your session has expired. Please sign in again.');
            if (!response.ok || !result.success) {
                throw new Error(result.message || result.error || 'The request could not be completed (HTTP ' + response.status + ').');
            }
            return result.data || {};
        }

        function rememberMessage(role, content) {
            const text = String(content || '').trim().slice(0, 1990);
            if (!text) return;
            state.messages.push({role: role, content: text});
            if (state.messages.length > 24) state.messages.splice(0, state.messages.length - 24);
        }

        // ------------------------------------------------------------
        // Audio: context, microphone level, speech output
        // ------------------------------------------------------------

        function ensureAudioContext() {
            const Context = window.AudioContext || window.webkitAudioContext;
            if (!Context) return null;
            if (!state.audioCtx) state.audioCtx = new Context();
            if (state.audioCtx.state === 'suspended') state.audioCtx.resume().catch(function () {});
            return state.audioCtx;
        }

        async function openMicMeter() {
            if (state.micStream || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) return;
            try {
                state.micStream = await navigator.mediaDevices.getUserMedia({
                    audio: {echoCancellation: true, noiseSuppression: true, autoGainControl: true}
                });
                const ctx = ensureAudioContext();
                if (!ctx) return;
                const source = ctx.createMediaStreamSource(state.micStream);
                state.micAnalyser = ctx.createAnalyser();
                state.micAnalyser.fftSize = 512;
                source.connect(state.micAnalyser);
            } catch (error) {
                console.warn('Jarvis: microphone meter unavailable:', error.message);
            }
        }

        function closeMicMeter() {
            if (state.micStream) state.micStream.getTracks().forEach(function (track) { track.stop(); });
            state.micStream = null;
            state.micAnalyser = null;
        }

        function analyserLevel(analyser) {
            if (!analyser) return 0;
            const data = new Uint8Array(analyser.fftSize);
            analyser.getByteTimeDomainData(data);
            let sum = 0;
            for (let i = 0; i < data.length; i++) {
                const v = (data[i] - 128) / 128;
                sum += v * v;
            }
            return Math.min(1, Math.sqrt(sum / data.length) * 5);
        }

        let smoothedLevel = 0;
        function levelLoop() {
            let target = 0;
            if (state.speaking) {
                target = state.speechAnalyser
                    ? analyserLevel(state.speechAnalyser)
                    : 0.35 + Math.abs(Math.sin(performance.now() / 140)) * 0.4;
            } else if (state.recognizing) {
                target = analyserLevel(state.micAnalyser);
            }
            smoothedLevel += (target - smoothedLevel) * 0.25;
            panel.style.setProperty('--deck-level', smoothedLevel.toFixed(3));
            state.levelRaf = state.open ? requestAnimationFrame(levelLoop) : null;
        }

        function stopSpeaking() {
            state.speechGen += 1;
            if (state.audio) {
                state.audio.pause();
                state.audio.removeAttribute('src');
                state.audio = null;
            }
            if (state.audioUrl) {
                URL.revokeObjectURL(state.audioUrl);
                state.audioUrl = null;
            }
            if (window.speechSynthesis) window.speechSynthesis.cancel();
            state.speechAnalyser = null;
            if (state.finishSpeech) {
                const finish = state.finishSpeech;
                state.finishSpeech = null;
                finish();
            }
        }

        async function playServerVoice(text, generation) {
            const response = await fetch(config.apiUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'audio/mpeg, application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': config.csrfToken
                },
                body: JSON.stringify({action: 'speech', text: text})
            });
            if (!response.ok) {
                const error = new Error('Voice service unavailable (HTTP ' + response.status + ')');
                error.permanent = response.status === 503;
                throw error;
            }
            const blob = await response.blob();
            if (generation !== state.speechGen) return;
            state.audioUrl = URL.createObjectURL(blob);
            state.audio = new Audio(state.audioUrl);
            const ctx = ensureAudioContext();
            if (ctx) {
                try {
                    const source = ctx.createMediaElementSource(state.audio);
                    state.speechAnalyser = ctx.createAnalyser();
                    state.speechAnalyser.fftSize = 512;
                    source.connect(state.speechAnalyser);
                    state.speechAnalyser.connect(ctx.destination);
                } catch (error) {
                    state.speechAnalyser = null;
                }
            }
            await new Promise(function (resolve, reject) {
                state.finishSpeech = resolve;
                state.audio.addEventListener('ended', resolve, {once: true});
                state.audio.addEventListener('error', function () { reject(new Error('Playback failed')); }, {once: true});
                state.audio.play().catch(reject);
            });
        }

        function pickBrowserVoice() {
            const voices = window.speechSynthesis ? window.speechSynthesis.getVoices() : [];
            const english = voices.filter(function (voice) { return /^en[-_]/i.test(voice.lang); });
            return english.find(function (voice) { return /Google UK English Male|Daniel|Ryan|Guy|David/i.test(voice.name); })
                || english.find(function (voice) { return /en-GB/i.test(voice.lang); })
                || english[0] || voices[0] || null;
        }

        function playBrowserVoice(text, generation) {
            if (!window.speechSynthesis || generation !== state.speechGen) return Promise.resolve();
            return new Promise(function (resolve) {
                const utterance = new SpeechSynthesisUtterance(text);
                const voice = pickBrowserVoice();
                if (voice) utterance.voice = voice;
                utterance.rate = 1.03;
                utterance.onend = resolve;
                utterance.onerror = resolve;
                state.finishSpeech = resolve;
                window.speechSynthesis.cancel();
                window.speechSynthesis.speak(utterance);
            });
        }

        async function speak(text, card) {
            text = String(text || '').trim().slice(0, 1200);
            if (!text) return;
            stopSpeaking();
            const generation = state.speechGen;
            state.speaking = true;
            state.spokenText = text;
            if (card) card.classList.add('playing');
            render();
            try {
                if (!state.useBrowserVoice) {
                    try {
                        await playServerVoice(text, generation);
                    } catch (error) {
                        if (generation !== state.speechGen) return;
                        if (error.permanent) state.useBrowserVoice = true;
                        await playBrowserVoice(text, generation);
                    }
                } else {
                    await playBrowserVoice(text, generation);
                }
            } finally {
                if (card) card.classList.remove('playing');
                if (generation === state.speechGen) {
                    state.speaking = false;
                    state.speechAnalyser = null;
                    state.finishSpeech = null;
                    if (state.audioUrl) {
                        URL.revokeObjectURL(state.audioUrl);
                        state.audioUrl = null;
                    }
                    state.audio = null;
                    render();
                }
            }
        }

        // ------------------------------------------------------------
        // Speech recognition (continuous, duplex)
        // ------------------------------------------------------------

        function wordsOf(text) {
            return String(text || '').toLowerCase().replace(/[^a-z0-9\s']/g, ' ').split(/\s+/).filter(function (word) { return word.length > 2; });
        }

        // True when what we "heard" is mostly Jarvis's own voice coming back through the speakers.
        function isEcho(heard) {
            const heardWords = wordsOf(heard);
            if (!state.spokenText || heardWords.length === 0) return false;
            const spoken = new Set(wordsOf(state.spokenText));
            const overlap = heardWords.filter(function (word) { return spoken.has(word); }).length;
            return overlap / heardWords.length >= 0.6;
        }

        function startRecognition() {
            if (!SpeechRecognition || !state.session || state.recognition) return;
            const recognition = new SpeechRecognition();
            recognition.lang = navigator.language || 'en-US';
            recognition.continuous = true;
            recognition.interimResults = true;
            recognition.maxAlternatives = 1;

            recognition.onstart = function () {
                state.recognizing = true;
                render();
            };
            recognition.onresult = onRecognitionResult;
            recognition.onerror = function (event) {
                if (event.error === 'not-allowed' || event.error === 'service-not-allowed' || event.error === 'audio-capture') {
                    endSession();
                    addCard('ai', event.error === 'audio-capture'
                        ? 'I could not find a microphone. Check that one is connected, or type your request instead.'
                        : 'Microphone access is blocked. Allow it in the browser address bar, or type your request instead.', {error: true});
                    showComposer(true);
                }
            };
            recognition.onend = function () {
                state.recognizing = false;
                state.recognition = null;
                render();
                // Chrome ends continuous recognition periodically; resume while the session is open.
                if (state.session) window.setTimeout(startRecognition, 250);
            };
            state.recognition = recognition;
            try {
                recognition.start();
            } catch (error) {
                state.recognition = null;
            }
        }

        function stopRecognition() {
            if (state.recognition) {
                try { state.recognition.abort(); } catch (error) { /* already stopped */ }
            }
            state.recognition = null;
            state.recognizing = false;
            state.finalBuffer = '';
            state.interimText = '';
            window.clearTimeout(state.utteranceTimer);
            setInterim('');
        }

        function onRecognitionResult(event) {
            let interim = '';
            let newFinal = '';
            for (let i = event.resultIndex; i < event.results.length; i++) {
                const result = event.results[i];
                const transcript = result[0].transcript;
                if (result.isFinal) newFinal += ' ' + transcript; else interim += transcript;
            }

            if (state.speaking) {
                // Duplex: decide whether this is the user interrupting or our own echo.
                if (!newFinal.trim()) return;
                if (isEcho(newFinal)) return;
                if (wordsOf(newFinal).length < 2 && !CANCEL_PHRASE.test(newFinal.trim())) return;
                stopSpeaking();
            }
            if (state.busy) return;

            state.finalBuffer = (state.finalBuffer + ' ' + newFinal).trim();
            state.interimText = interim.trim();
            const display = (state.finalBuffer + ' ' + state.interimText).trim();
            if (!display) return;
            setInterim(display);
            window.clearTimeout(state.utteranceTimer);
            state.utteranceTimer = window.setTimeout(commitUtterance, state.interimText ? 1600 : 850);
        }

        function commitUtterance() {
            const text = (state.finalBuffer + ' ' + state.interimText).trim().replace(/\s+/g, ' ');
            state.finalBuffer = '';
            state.interimText = '';
            setInterim('');
            if (text) handleUserText(text);
        }

        // ------------------------------------------------------------
        // Conversation flow
        // ------------------------------------------------------------

        async function respond(text, card) {
            if (!card) card = addCard('ai', text);
            rememberMessage('assistant', text);
            await speak(text, card);
            return card;
        }

        async function handleUserText(text) {
            text = String(text || '').trim();
            if (!text || state.busy) return;

            if (state.pending && CONFIRM_PHRASE.test(text) && wordsOf(text).length <= 6) {
                addCard('user', text);
                state.pending.confirm();
                return;
            }
            if (state.pending && CANCEL_PHRASE.test(text) && wordsOf(text).length <= 6) {
                addCard('user', text);
                state.pending.cancel();
                return;
            }

            addCard('user', text);

            if (CLOSE_PHRASE.test(text)) {
                await respond('You’re welcome. I’m here whenever you need me.');
                endSession();
                return;
            }
            if (SECRET_PHRASE.test(text)) {
                await respond('For security, please don’t say passwords out loud. Ask me to change it and type the new password privately in the confirmation card.');
                return;
            }

            rememberMessage('user', text);
            await converse();
        }

        async function converse(isSummary) {
            state.busy = true;
            render();
            let result;
            try {
                result = await api({action: 'chat', messages: state.messages.slice(-12)});
            } catch (error) {
                state.busy = false;
                render();
                const card = addCard('ai', error.message, {error: true});
                await speak(error.message, card);
                return;
            }
            state.busy = false;
            render();

            const operation = result.operation || null;
            const reply = String(result.reply || '').trim() || 'Done.';

            if (!operation) {
                await respond(reply);
                return;
            }

            if (operation.method === 'GET' && !isSummary) {
                const card = addCard('ai', reply);
                rememberMessage('assistant', reply);
                await runOperation(operation, false, card, true);
                return;
            }

            if (!operation.requires_confirmation) {
                const card = addCard('ai', reply);
                rememberMessage('assistant', reply);
                await runOperation(operation, false, card, false);
                return;
            }

            const card = addCard('ai', reply);
            rememberMessage('assistant', reply);
            renderProposal(card, operation);
            await speak(reply + ' Say confirm or cancel.', card);
        }

        function appendResult(card, message, ok) {
            const body = card.querySelector('.deck-msg-body');
            let line = body.querySelector('.deck-result');
            if (!line) {
                line = document.createElement('div');
                line.className = 'deck-result';
                body.insertBefore(line, body.querySelector('.deck-msg-foot'));
            }
            line.className = 'deck-result ' + (ok ? 'ok' : 'fail');
            line.textContent = (ok ? '✓ ' : '✕ ') + message;
            scrollToEnd();
        }

        async function runOperation(operation, confirmed, card, summarize) {
            state.busy = true;
            render();
            let outcome;
            try {
                outcome = await api({action: 'execute', confirmed: confirmed, operation: operation});
            } catch (error) {
                state.busy = false;
                render();
                appendResult(card, error.message, false);
                rememberMessage('assistant', 'The operation failed: ' + error.message);
                await speak(error.message, card);
                return false;
            }
            state.busy = false;
            render();

            const message = outcome.message || 'Done.';
            if (operation.method !== 'GET') appendResult(card, message, true);

            if (summarize) {
                rememberMessage('user', '[SYSTEM RESULT] ' + operation.method + ' ' + operation.endpoint + ': ' + String(outcome.result || message).slice(0, 1700));
                await converse(true);
            } else {
                rememberMessage('assistant', 'Result: ' + message);
                await speak(message, card);
            }
            return true;
        }

        function renderProposal(card, operation) {
            const box = document.createElement('div');
            box.className = 'deck-proposal';

            const title = document.createElement('p');
            title.className = 'deck-proposal-title';
            title.innerHTML = '<i class="fas fa-shield-halved"></i> Confirm action';
            const summary = document.createElement('p');
            summary.className = 'deck-proposal-summary';
            summary.textContent = operation.summary || (operation.method + ' ' + operation.endpoint);

            const details = document.createElement('details');
            const detailsLabel = document.createElement('summary');
            detailsLabel.textContent = 'Details (' + operation.method + ' /portal/api/' + operation.endpoint + ')';
            const editor = document.createElement('textarea');
            editor.rows = 6;
            editor.spellcheck = false;
            editor.setAttribute('aria-label', 'Operation details as JSON');
            // PHP encodes empty objects as [], so normalise before editing.
            const asObject = function (value) { return value && typeof value === 'object' && !Array.isArray(value) ? value : {}; };
            editor.value = JSON.stringify({query: asObject(operation.query), data: asObject(operation.data)}, null, 2);
            details.append(detailsLabel, editor);

            let secretInput = null;
            box.append(title, summary, details);
            if (operation.requires_local_secret) {
                const label = document.createElement('label');
                label.textContent = 'New password (entered privately, never sent to the AI)';
                secretInput = document.createElement('input');
                secretInput.type = 'password';
                secretInput.autocomplete = 'new-password';
                secretInput.minLength = 6;
                secretInput.maxLength = 127;
                label.appendChild(secretInput);
                box.appendChild(label);
            }

            const actions = document.createElement('div');
            actions.className = 'deck-proposal-actions';
            const cancelButton = document.createElement('button');
            cancelButton.type = 'button';
            cancelButton.className = 'deck-btn';
            cancelButton.textContent = 'Cancel';
            const confirmButton = document.createElement('button');
            confirmButton.type = 'button';
            confirmButton.className = 'deck-btn primary';
            confirmButton.textContent = 'Confirm';
            actions.append(cancelButton, confirmButton);
            box.appendChild(actions);

            const body = card.querySelector('.deck-msg-body');
            body.insertBefore(box, body.querySelector('.deck-msg-foot'));
            scrollToEnd();

            function settle() {
                state.pending = null;
                confirmButton.disabled = true;
                cancelButton.disabled = true;
                editor.readOnly = true;
            }

            async function confirm() {
                if (state.pending !== handle) return;
                let edited;
                try {
                    edited = JSON.parse(editor.value);
                    if (!edited || typeof edited.query !== 'object' || typeof edited.data !== 'object' || Array.isArray(edited.query) || Array.isArray(edited.data)) {
                        throw new Error('bad');
                    }
                } catch (error) {
                    details.open = true;
                    await speak('The operation details are not valid JSON. Please fix them and confirm again.', card);
                    return;
                }
                if (secretInput) {
                    if (secretInput.value.length < 6) {
                        secretInput.focus();
                        await speak('Please type the new password in the card first.', card);
                        return;
                    }
                    edited.data[operation.endpoint === 'stations.php' ? 'new_password' : 'password'] = secretInput.value;
                }
                settle();
                confirmButton.textContent = 'Running…';
                const ok = await runOperation({
                    endpoint: operation.endpoint,
                    method: operation.method,
                    query: edited.query,
                    data: edited.data,
                    requires_local_secret: operation.requires_local_secret === true
                }, true, card, operation.method === 'GET');
                if (secretInput) secretInput.value = '';
                box.classList.add(ok ? 'done' : 'cancelled');
                confirmButton.textContent = ok ? 'Done' : 'Failed';
            }

            async function cancel() {
                if (state.pending !== handle) return;
                settle();
                box.classList.add('cancelled');
                appendResult(card, 'Cancelled', false);
                await respond('Okay, I cancelled that.');
            }

            const handle = {confirm: confirm, cancel: cancel};
            state.pending = handle;
            confirmButton.addEventListener('click', confirm);
            cancelButton.addEventListener('click', cancel);
        }

        // ------------------------------------------------------------
        // Session & panel controls
        // ------------------------------------------------------------

        function greetingName() {
            return String(config.userName || '').trim().split(/\s+/)[0] || 'there';
        }

        async function startSession() {
            ensureAudioContext();
            if (!SpeechRecognition) {
                showComposer(true);
                if (!state.greeted) {
                    state.greeted = true;
                    await respond('Hi ' + greetingName() + '! Voice input isn’t supported in this browser, so type your request below.');
                }
                return;
            }
            state.session = true;
            render();
            openMicMeter();
            if (!state.greeted) {
                state.greeted = true;
                state.startedAt = Date.now();
                startRecognition();
                await respond('Hi ' + greetingName() + '! I’m Jarvis, your AI voice assistant. How can I help you today?');
            } else {
                startRecognition();
            }
        }

        function endSession() {
            state.session = false;
            stopRecognition();
            closeMicMeter();
            render();
        }

        function showComposer(show) {
            el.composer.hidden = !show;
            el.keyboard.setAttribute('aria-pressed', show ? 'true' : 'false');
            if (show) el.input.focus();
        }

        function openPanel() {
            if (state.open) return;
            state.open = true;
            panel.hidden = false;
            render();
            if (!state.levelRaf) state.levelRaf = requestAnimationFrame(levelLoop);
        }

        function closePanel() {
            state.open = false;
            endSession();
            stopSpeaking();
            panel.hidden = true;
            render();
            if (el.launcher) el.launcher.focus();
        }

        function toggleSession() {
            if (state.session) {
                endSession();
                stopSpeaking();
            } else {
                startSession();
            }
        }

        if (el.launcher) {
            el.launcher.addEventListener('click', function () {
                if (state.open) {
                    closePanel();
                } else {
                    openPanel();
                    startSession();
                }
            });
        }
        el.close.addEventListener('click', closePanel);
        el.mic.addEventListener('click', toggleSession);
        el.orb.addEventListener('click', function () {
            if (state.speaking) {
                stopSpeaking();
                return;
            }
            toggleSession();
        });
        el.keyboard.addEventListener('click', function () {
            showComposer(el.composer.hidden);
        });
        el.clear.addEventListener('click', function () {
            stopSpeaking();
            state.messages.length = 0;
            state.pending = null;
            state.interimCard = null;
            state.startedAt = Date.now();
            el.conversation.innerHTML = '<p class="deck-empty">Conversation cleared. Tap the mic or type to start again.</p>';
        });
        el.composer.addEventListener('submit', function (event) {
            event.preventDefault();
            const text = el.input.value.trim();
            if (!text || state.busy) return;
            el.input.value = '';
            ensureAudioContext();
            stopSpeaking();
            handleUserText(text);
        });

        document.addEventListener('keydown', function (event) {
            if (event.altKey && (event.key === 'j' || event.key === 'J')) {
                event.preventDefault();
                if (state.open) closePanel(); else { openPanel(); startSession(); }
            } else if (event.key === 'Escape' && state.open) {
                closePanel();
            }
        });

        if (window.speechSynthesis) {
            window.speechSynthesis.getVoices();
            window.speechSynthesis.addEventListener && window.speechSynthesis.addEventListener('voiceschanged', function () {});
        }
        window.addEventListener('beforeunload', function () {
            endSession();
            stopSpeaking();
        });

        render();
    });
})();
