import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { initRoomChat } from './chat';

function connectRoom() {
    const roomCode = document
        .querySelector('meta[name="room-code"]')
        ?.content;

    const csrfToken = document
        .querySelector('meta[name="csrf-token"]')
        ?.content;

    const status = document.getElementById('realtime-status');

    if (!roomCode || !csrfToken) {
        return;
    }

    const showStatus = (message) => {
        if (status) {
            status.textContent = message;
        }
    };
    let hasSubscribed = false;
    window.Pusher = Pusher;

    const secure = import.meta.env.VITE_REVERB_SCHEME === 'https';
    const port = Number(import.meta.env.VITE_REVERB_PORT || 8080);

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: port,
        wssPort: port,
        forceTLS: secure,
        enabledTransports: secure ? ['wss'] : ['ws'],

        authorizer: (channel) => ({
            authorize: async (socketId, callback) => {
                try {
                    const response = await fetch('/game-broadcast/auth', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({
                            socket_id: socketId,
                            channel_name: channel.name,
                        }),
                    });

                    if (response.status === 403) {
                        callback(new Error('คุณไม่ได้อยู่ในห้องนี้แล้ว'), null);

                        window.Echo.disconnect();
                        window.location.replace('/room-test');

                        return;
                    }

                    if (!response.ok) {
                        throw new Error(
                            `ตรวจสิทธิ์ห้องไม่สำเร็จ (${response.status})`
                        );
                    }

                    callback(null, await response.json());
                } catch (error) {
                    showStatus('เข้าฟังห้องไม่สำเร็จ');
                    console.error(error);
                    callback(error, null);
                }
            },
        }),
    });

    showStatus('กำลังเชื่อมต่อ…');

    let reloading = false;

    async function refreshRoom(event) {
        if (event.room_code !== roomCode || reloading) {
            return;
        }

        const socketId = window.Echo.socketId();

        if (!socketId) {
            showStatus('กำลังรอการเชื่อมต่อกลับ…');
            return;
        }

        reloading = true;
        showStatus('กำลังอัปเดตห้อง…');

        try {
            // ใช้จุดตรวจสิทธิ์เดิม ตรวจสมาชิกอีกครั้ง
            const response = await fetch('/game-broadcast/auth', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    socket_id: socketId,
                    channel_name: `private-rooms.${roomCode}`,
                }),
            });

            if (response.status === 403) {
                window.Echo.leave(`rooms.${roomCode}`);
                window.Echo.disconnect();
                window.location.replace('/room-test');
                return;
            }

            if (!response.ok) {
                throw new Error(
                    `โหลดสิทธิ์ห้องไม่สำเร็จ (${response.status})`
                );
            }

            window.location.reload();
        } catch (error) {
            reloading = false;
            showStatus('อัปเดตไม่สำเร็จ กรุณารีเฟรชหน้า');
            console.error(error);
        }
    }

    const refreshChat = initRoomChat(roomCode, csrfToken);

    window.Echo.private(`rooms.${roomCode}`)
        .subscribed(() => {
            if (hasSubscribed) {
                showStatus('เชื่อมต่อกลับแล้ว กำลังโหลดสถานะล่าสุด…');

                refreshRoom({
                    room_code: roomCode,
                });

                return;
            }

            hasSubscribed = true;

            showStatus('เชื่อมต่อห้องแล้ว');
            console.log(`Subscribed: private-rooms.${roomCode}`);

            refreshChat();
        })
        .error((error) => {
            showStatus('เชื่อมต่อห้องไม่สำเร็จ');
            console.error('Room subscription error:', error);
        })
        .listen('.phase.changed', refreshRoom)
        .listen('.room.updated', refreshRoom)
        .listen('.chat.updated', (event) => {
            if (event.room_code === roomCode) {
                refreshChat();
            }
        });

        

    window.Echo.connector.pusher.connection.bind(
        'state_change',
        ({ current }) => {
            if (current === 'connected') {
                showStatus('เชื่อมต่อเซิร์ฟเวอร์แล้ว กำลังเข้าฟังห้อง…');
                return;
            }

            const messages = {
                connecting: 'กำลังเชื่อมต่อ…',
                unavailable: 'เชื่อมต่อไม่ได้ กำลังลองใหม่…',
                disconnected: 'การเชื่อมต่อถูกตัด',
                failed: 'เชื่อมต่อไม่สำเร็จ กรุณารีเฟรชหน้า',
            };

            showStatus(
                messages[current] ?? `สถานะการเชื่อมต่อ: ${current}`
            );

            const input = document.getElementById('chat-message');
            const submit = document.getElementById('chat-submit');

            if (input) {
                input.disabled = true;
            }

            if (submit) {
                submit.disabled = true;
            }
        }
    );
}

connectRoom();