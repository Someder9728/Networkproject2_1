export function initRoomChat(roomCode, csrfToken) {
    const form = document.getElementById('chat-form');

    if (!form) {
        return async () => {};
    }

    const select = document.getElementById('chat-channel');
    const list = document.getElementById('chat-messages');
    const input = document.getElementById('chat-message');
    const submit = document.getElementById('chat-submit');
    const feedback = document.getElementById('chat-feedback');

    const labels = {
        all: 'แชตรวม',
        werewolf: 'แชตหมาป่า',
        dead: 'แชตคนตาย',
    };

    const url = `/rooms/${encodeURIComponent(roomCode)}/chat`;

    let channels = [];
    let requestNumber = 0;
    let sending = false;

    function render() {
        const channel = channels.find(
            (item) => item.name === select.value
        );

        list.replaceChildren();

        for (const message of channel?.messages ?? []) {
            const item = document.createElement('li');

            // แสดงข้อความเป็นข้อความล้วน ไม่ตีความเป็น HTML
            item.textContent =
                `${message.sender_name}: ${message.content}`;

            list.appendChild(item);
        }

        const connected =
            window.Echo?.connector?.pusher?.connection?.state === 'connected';

        input.disabled = !connected || !channel?.can_send || sending;
        submit.disabled = input.disabled;

        list.scrollTop = list.scrollHeight;
    }

    async function refresh() {
        const currentRequest = ++requestNumber;

        try {
            const response = await fetch(url, {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    'Accept': 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error(
                    `โหลดแชตไม่สำเร็จ (${response.status})`
                );
            }

            const data = await response.json();

            if (currentRequest !== requestNumber) {
                return;
            }

            const previousChannel = select.value;
            channels = data.channels;
            select.replaceChildren();

            for (const channel of channels) {
                select.add(new Option(
                    labels[channel.name],
                    channel.name
                ));
            }

            if (channels.some((item) => item.name === previousChannel)) {
                select.value = previousChannel;
            }

            feedback.textContent = '';
            render();
        } catch (error) {
            if (currentRequest !== requestNumber) {
                return;
            }

            channels = [];
            select.replaceChildren();
            render();

            feedback.textContent = error.message;
        }
    }

    select.addEventListener('change', render);

    document.getElementById('chat-refresh')
        .addEventListener('click', refresh);

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const message = input.value.trim();

        if (!message || input.disabled || sending) {
            return;
        }

        sending = true;
        render();

        try {
            const response = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    channel: select.value,
                    message,
                }),
            });

            if (!response.ok) {
                throw new Error(
                    `ส่งไม่สำเร็จ (${response.status}) กรุณาโหลดแชตใหม่`
                );
            }

            input.value = '';
            await refresh();
        } catch (error) {
            feedback.textContent = error.message;
        } finally {
            sending = false;
            render();
        }
    });

    return refresh;
}