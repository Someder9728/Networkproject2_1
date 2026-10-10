import {initChatExtras} from './chat-extras';
export function initRoomChat(roomCode, csrfToken, playerName) {
    const form = document.getElementById("chat-form");

    if (!form) {
        return async () => {};
    }

    const channelButtons = document.querySelectorAll("[data-chat-channel]");
    const list = document.getElementById("chat-messages");
    const input = document.getElementById("chat-message");
    const submit = document.getElementById("chat-submit");
    const feedback = document.getElementById("chat-feedback");
    const refreshButton = document.getElementById("chat-refresh");

    const url = `/rooms/${encodeURIComponent(roomCode)}/chat`;

    const recipientLabel = document.createElement('label');
    recipientLabel.className = 'chat-recipient';
    const recipientTitle = document.createElement('span');
    recipientTitle.textContent = 'ส่งถึง';
    const recipientSelect = document.createElement('select');
    recipientSelect.setAttribute('aria-label', 'เลือกผู้รับข้อความ');
    const privateNote = document.createElement('div');
    privateNote.className = 'chat-private-note';
    recipientLabel.append(recipientTitle, recipientSelect);
    form.before(recipientLabel, privateNote);
    let recipients = [];
    let recipient = null;
    let channels = [];
    let currentChannel = "all";
    let requestNumber = 0;
    let sending = false;

    function setActiveChannel(channel) {
        if (currentChannel !== channel) recipient = null;
        currentChannel = channel;
        window.dispatchEvent(new CustomEvent("game:chat-channel", {detail: {channel}}));

        channelButtons.forEach((button) => {
            button.classList.toggle(
                "active",
                button.dataset.chatChannel === channel,
            );
        });
    }

    function getCurrentChannel() {
        return channels.find((item) => item.name === currentChannel);
    }

    function canSend() {
        const connected = window.Echo?.connector?.pusher?.connection?.state === 'connected';
        return connected && !sending && (recipient ? currentChannel === 'all' && recipients.some(person => person.uuid === recipient) : getCurrentChannel()?.can_send);
    }

    function renderRecipients() {
        recipientLabel.hidden = currentChannel !== 'all';
        recipientSelect.replaceChildren();
        recipientSelect.add(new Option('ทุกคน · แชทรวม', ''));
        for (const person of recipients) recipientSelect.add(new Option(`🔒 กระซิบ: ${person.name}`, person.uuid));
        if (recipient && !recipients.some(person => person.uuid === recipient)) recipientSelect.add(new Option('ผู้รับไม่พร้อมรับข้อความ · เลือกใหม่', recipient));
        recipientSelect.value = recipient ?? '';
        recipientSelect.disabled = sending;
        recipientLabel.classList.toggle('private', Boolean(recipient));
        document.querySelectorAll('[data-whisper-to]').forEach(button => {button.disabled = !recipients.some(person => person.uuid === button.dataset.whisperTo);});
        const person = recipients.find(person => person.uuid === recipient);
        privateNote.textContent = recipient ? (person ? `ส่วนตัวกับ ${person.name} · เห็นเฉพาะคุณสองคน` : 'ส่งไม่ได้ในช่วงนี้ เลือกผู้รับใหม่ก่อนส่ง') : (currentChannel === 'all' ? 'ข้อความถึงทุกคน ส่วนกระซิบเลือกชื่อผู้รับด้านบน' : 'ข้อความจะส่งในช่องที่เลือก');
        input.placeholder = recipient ? 'พิมพ์ข้อความส่วนตัว…' : 'พิมพ์ข้อความ…';
    }

    function render() {
        renderRecipients();
        const channel = getCurrentChannel();

        const existingAudio = new Map([...list.querySelectorAll('audio[data-message-id]')].map(audio => [audio.dataset.messageId, audio]));
        list.replaceChildren();

        for (const message of channel?.messages ?? []) {
            const item = document.createElement("li");
            const bubble = document.createElement("div");

            const isMine = (message.is_mine ?? (message.sender_name === playerName));

            item.className = isMine
                ? "chat-message-row mine"
                : "chat-message-row";

            if (!isMine) {
                const meta = document.createElement("div");

                meta.className = "chat-message-meta";

                const name = document.createElement("span");

                name.className = "chat-message-name";
                name.textContent = message.sender_name;

                meta.appendChild(name);
                item.appendChild(meta);
            }

            bubble.className = "chat-message-bubble";
            bubble.textContent = message.content;
            if (message.is_private) {
                bubble.classList.add('private');
                const label = document.createElement('span');
                label.className = 'chat-private-label';
                label.textContent = isMine ? `🔒 คุณ → ${message.recipient_name ?? 'ผู้เล่น'} · ส่วนตัว` : `🔒 ${message.sender_name} → คุณ · ส่วนตัว`;
                bubble.prepend(label);
                const reply = document.createElement('button');
                reply.type = 'button'; reply.className = 'chat-private-label'; reply.textContent = 'ตอบกระซิบ';
                reply.addEventListener('click', () => selectRecipient(isMine ? message.recipient_uuid : message.sender_uuid));
                bubble.append(reply);
            }
            if (message.audio_url) {
                const audio = existingAudio.get(String(message.id)) ?? document.createElement('audio');
                audio.dataset.messageId = String(message.id);
                audio.controls = true;
                audio.preload = 'none';
                if (audio.getAttribute('src') !== message.audio_url) audio.src = message.audio_url;
                audio.style.cssText = 'max-width:100%;width:240px;height:36px;display:block;margin-top:6px';
                bubble.append(audio);
            }

            item.appendChild(bubble);
            list.appendChild(item);
        }

        input.disabled = !canSend();

        submit.disabled = input.disabled;

        list.scrollTop = list.scrollHeight;
    }

    async function refresh(showLoading = true) {
        const currentRequest = ++requestNumber;

        if (showLoading && refreshButton) {
            refreshButton.disabled = true;
            refreshButton.classList.add("active");
        }

        if (showLoading) {
            list.innerHTML = `
        <div class="chat-empty">
            กำลังโหลดข้อความ...
        </div>
        `;
        }

        try {
            const response = await fetch(url, {
                credentials: "same-origin",
                cache: "no-store",
                headers: {
                    Accept: "application/json",
                },
            });

            if (!response.ok) {
                throw new Error(`โหลดแชตไม่สำเร็จ (${response.status})`);
            }

            const data = await response.json();

            if (currentRequest !== requestNumber) {
                return;
            }

            channels = data.channels ?? [];
            recipients = data.recipients ?? [];
            window.dispatchEvent(new CustomEvent("game:chat-update", {detail: {channels, currentChannel}}));

            const currentChannelExists = channels.some(
                (channel) => channel.name === currentChannel,
            );

            if (!currentChannelExists) {
                setActiveChannel("all");
            } else {
                setActiveChannel(currentChannel);
            }
            if (showLoading) {
                list.innerHTML = `
        <div class="chat-empty">
            อัปเดตข้อความแล้ว
        </div>
    `;

                setTimeout(() => {
                    if (currentRequest === requestNumber) {
                        render();
                    }
                }, 500);
            } else {
                render();
            }
            
        } catch (error) {
            if (currentRequest !== requestNumber) {
                return;
            }

            channels = [];

            list.innerHTML = `
            <div class="chat-empty">
                ${error.message}
            </div>
        `;
        } finally {
            if (refreshButton) {
                refreshButton.disabled = false;
                refreshButton.classList.remove("active");
            }
        }
    }

    if (refreshButton) {
        refreshButton.addEventListener("click", refresh);
    }

    channelButtons.forEach((button) => {
        button.addEventListener("click", () => {
            const channel = button.dataset.chatChannel;

            if (!channel || channel === currentChannel) {
                return;
            }

            setActiveChannel(channel);
            render();
        });
    });

    form.addEventListener("submit", async (event) => {
        event.preventDefault();

        const message = input.value.trim();

        if (!message || input.disabled || sending) {
            return;
        }

        sending = true;

        render();

        try {
            const response = await fetch(url, {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                    "X-CSRF-TOKEN": csrfToken,
                },
                body: JSON.stringify({
                    channel: currentChannel,
                    message,
                    recipient_uuid: recipient,
                }),
            });

            if (!response.ok) {
                throw new Error(
                    `ส่งไม่สำเร็จ (${response.status}) กรุณาโหลดแชตใหม่`,
                );
            }

            input.value = "";

            await refresh(false);
        } catch (error) {
            feedback.textContent = error.message;
        } finally {
            sending = false;

            render();
        }
    });

    function selectRecipient(uuid) {
        if (!recipients.some(person => person.uuid === uuid)) {
            feedback.textContent = 'กระซิบหาคนนี้ไม่ได้ในช่วงนี้ (คนเป็นกับคนตายคุยข้ามกันไม่ได้ และคนเป็นกระซิบได้ตอนกลางวัน)';
            return;
        }
        setActiveChannel('all');
        recipient = uuid;
        render();
        feedback.textContent = '';
        input.focus();
    }
    recipientSelect.addEventListener('change', () => {
        recipient = recipientSelect.value || null;
        feedback.textContent = '';
        render();
        input.focus();
    });
    window.addEventListener('game:whisper-select', async event => {
        await refresh(false);
        selectRecipient(event.detail.uuid);
    });
    setActiveChannel("all");

    initChatExtras({form, input, url, csrfToken, channel: () => currentChannel, canSend, recipient: () => recipient, refresh});
    return refresh;
}
