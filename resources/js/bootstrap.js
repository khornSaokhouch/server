import axios from 'axios';
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Echo configuration with debugging
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
    forceTLS: true,
    encrypted: true,
});

// Debug connection events
window.Echo.connector.pusher.connection.bind('connected', () => {
    console.log('✅ Pusher connected successfully!');
    console.log('Socket ID:', window.Echo.socketId());
});

window.Echo.connector.pusher.connection.bind('connecting', () => {
    console.log('🔄 Connecting to Pusher...');
});

window.Echo.connector.pusher.connection.bind('disconnected', () => {
    console.log('❌ Pusher disconnected');
});

window.Echo.connector.pusher.connection.bind('error', (err) => {
    console.error('❌ Pusher connection error:', err);
});

// Subscribe to channel and listen for events
window.Echo.channel("my-channel")
    .listen(".my-event", (data) => {
        console.log("📨 Received event:", data);
        alert("Received message: " + data.message);
    })
    .error((error) => {
        console.error('Channel subscription error:', error);
    });

console.log('Pusher Echo initialized with key:', import.meta.env.VITE_PUSHER_APP_KEY);