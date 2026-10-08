import Echo from "laravel-echo";
import Pusher from "pusher-js";
import { initRoomChat } from "./chat";

function connectRoom() {
    const roomCode = document.querySelector('meta[name="room-code"]')?.content;

    const csrfToken = document.querySelector(
        'meta[name="csrf-token"]',
    )?.content;

    const playerName = document.querySelector(
        'meta[name="player-name"]',
    )?.content;

    const status = document.getElementById("realtime-status");

    const updateStatusColor = (state) => {
        if (!status) {
            return;
        }

        status.classList.remove(
            "status-connected",
            "status-connecting",
            "status-disconnected",
        );

        if (state === "connected") {
            status.classList.add("status-connected");
            return;
        }

        if (state === "connecting") {
            status.classList.add("status-connecting");
            return;
        }

        status.classList.add("status-disconnected");
    };

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

    const secure = import.meta.env.VITE_REVERB_SCHEME === "https";
    const port = Number(import.meta.env.VITE_REVERB_PORT || 8080);

    console.log('REVERB DEBUG', {host:import.meta.env.VITE_REVERB_HOST,
        port:import.meta.env.VITE_REVERB_PORT,
        scheme:import.meta.env.VITE_REVERB_SCHEME
    });

    window.Echo = new Echo({
        broadcaster: "reverb",
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: port,
        wssPort: port,
        forceTLS: secure,
        enabledTransports: ["ws", "wss"],

        authorizer: (channel) => ({
            authorize: async (socketId, callback) => {
                try {
                    const response = await fetch("/game-broadcast/auth", {
                        method: "POST",
                        credentials: "same-origin",
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                            "X-CSRF-TOKEN": csrfToken,
                        },
                        body: JSON.stringify({
                            socket_id: socketId,
                            channel_name: channel.name,
                        }),
                    });

                    if (response.status === 403) {
                        callback(new Error("คุณไม่ได้อยู่ในห้องนี้แล้ว"), null);

                        window.Echo.disconnect();
                        window.location.replace("/room-test");

                        return;
                    }

                    if (!response.ok) {
                        throw new Error(
                            `ตรวจสิทธิ์ห้องไม่สำเร็จ (${response.status})`,
                        );
                    }

                    callback(null, await response.json());
                } catch (error) {
                    showStatus("เข้าฟังห้องไม่สำเร็จ");
                    console.error(error);
                    callback(error, null);
                }
            },
        }),
    });

    updateStatusColor("connecting");
    showStatus("กำลังเชื่อมต่อ…");

    let reloading = false;

    async function refreshRoom(event) {
        if (event.room_code !== roomCode || reloading) {
            return;
        }

        const socketId = window.Echo.socketId();

        if (!socketId) {
            updateStatusColor("connecting");
            showStatus("กำลังรอการเชื่อมต่อกลับ…");
            return;
        }

        reloading = true;
        updateStatusColor("connecting");
        showStatus("กำลังอัปเดตห้อง…");

        try {
            // ใช้จุดตรวจสิทธิ์เดิม ตรวจสมาชิกอีกครั้ง
            const response = await fetch("/game-broadcast/auth", {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                    "X-CSRF-TOKEN": csrfToken,
                },
                body: JSON.stringify({
                    socket_id: socketId,
                    channel_name: `private-rooms.${roomCode}`,
                }),
            });

            if (response.status === 403) {
                window.Echo.leave(`rooms.${roomCode}`);
                window.Echo.disconnect();
                window.location.replace("/room-test");
                return;
            }

            if (!response.ok) {
                throw new Error(`โหลดสิทธิ์ห้องไม่สำเร็จ (${response.status})`);
            }

            window.location.reload();
        } catch (error) {
            reloading = false;
            updateStatusColor("connecting");
            showStatus("อัปเดตไม่สำเร็จ กรุณารีเฟรชหน้า");
            console.error(error);
        }
    }

    const refreshChat = initRoomChat(roomCode, csrfToken, playerName);

    window.Echo.private(`rooms.${roomCode}`)
        .subscribed(() => {
            if (hasSubscribed) {
                updateStatusColor("connecting");
                showStatus("เชื่อมต่อกลับแล้ว กำลังโหลดสถานะล่าสุด…");

                refreshRoom({
                    room_code: roomCode,
                });

                return;
            }

            hasSubscribed = true;

            updateStatusColor("connected");
            showStatus("เชื่อมต่อห้องแล้ว");
            console.log(`Subscribed: private-rooms.${roomCode}`);

            refreshChat();
        })
        .error((error) => {
            updateStatusColor("disconnected");
            showStatus("เชื่อมต่อห้องไม่สำเร็จ");
            console.error("Room subscription error:", error);
        })
        .listen(".phase.changed", refreshRoom)
        .listen(".room.updated", refreshRoom)
        .listen(".chat.updated", (event) => {
            if (event.room_code === roomCode) {
                refreshChat(false);
            }
        })

        .listen(".night.event.triggered", (event) => {
            if (event.room_code !== roomCode) {
                return;
            }

            console.log("[Realtime] Night event triggered:", event);

            refreshRoom(event);
        })
        .listen(".vote.result", (event) => {
            if (event.room_code !== roomCode) {
                return;
            }

            console.log("[Realtime] Vote result:", event);

            refreshRoom(event);
        });

    window.Echo.connector.pusher.connection.bind(
        "state_change",
        ({ current }) => {
            if (current === "connected") {
                updateStatusColor("connected");
                showStatus("เชื่อมต่อเซิร์ฟเวอร์แล้ว กำลังเข้าฟังห้อง…");
                return;
            }

            updateStatusColor(current);

            const messages = {
                connecting: "กำลังเชื่อมต่อ…",
                unavailable: "เชื่อมต่อไม่ได้ กำลังลองใหม่…",
                disconnected: "การเชื่อมต่อถูกตัด",
                failed: "เชื่อมต่อไม่สำเร็จ กรุณารีเฟรชหน้า",
            };

            showStatus(messages[current] ?? `สถานะการเชื่อมต่อ: ${current}`);

            const input = document.getElementById("chat-message");
            const submit = document.getElementById("chat-submit");

            if (input) {
                input.disabled = true;
            }

            if (submit) {
                submit.disabled = true;
            }
        },
    );
}

connectRoom();
