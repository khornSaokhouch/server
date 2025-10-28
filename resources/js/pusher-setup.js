import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

export function initializePusher() {
    window.Pusher = Pusher;

    const echo = new Echo({
        broadcaster: 'pusher',
        key: import.meta.env.VITE_PUSHER_APP_KEY,
        cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
        forceTLS: true,
        encrypted: true,
    });

    // Debug connection
    echo.connector.pusher.connection.bind('connected', () => {
        console.log('✅ Pusher connected successfully!');
        console.log('Socket ID:', echo.socketId());
    });

    echo.connector.pusher.connection.bind('error', (err) => {
        console.error('❌ Pusher connection error:', err);
    });

    return echo;
}

export function subscribeToChannels(echo) {
    echo.channel("my-channel")
        .listen(".my-event", (data) => {
            console.log("📨 Received event:", data);
            alert("Received message: " + data.message);
        })
        .error((error) => {
            console.error('Channel subscription error:', error);
        });
}