import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const echo = new Echo({
    broadcaster: 'reverb',

    key: import.meta.env.VITE_REVERB_APP_KEY,

    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,

    forceTLS: false,
    enabledTransports: ['ws'],
});

console.log('Echo initialized:', echo);

console.log('Listening for phase.changed');

const channel = echo.channel('rooms.test-room');

channel.subscribed(() => {
    console.log('Successfully subscribed to rooms.test-room');
});

console.log('Listening for phase.changed');
// Phase Changed
channel.listen('.phase.changed', (event) => {
    console.log('Phase Changed:', event);
});

// Chat Message
channel.listen('.chat.message', (event) => {
    console.log('Chat Message:', event);
});

const chatInput = document.getElementById('chatInput');
const chatSend = document.getElementById('chatSend');

chatSend.addEventListener('click', async () => {
    const message = chatInput.value;

    if (!message.trim()) {
        return;
    }

    await fetch('/chat', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({
            message: message,
            sender_id: 'player-1',
            sender_name: 'Mafiaz',
        }),
    });

    chatInput.value = '';
});