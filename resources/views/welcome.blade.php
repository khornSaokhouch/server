<!DOCTYPE html>
<html>
<head>
    <title>Pusher Test</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/js/app.js'])
</head>
<body>
    <h1>Pusher Laravel Test</h1>
    <input type="text" id="msg" placeholder="Type message">
    <button onclick="sendMessage()">Send</button>

    <script>
    async function sendMessage() {
        const msg = document.getElementById('msg').value;
        
        if (!msg.trim()) {
            alert('Please enter a message');
            return;
        }

        try {
            const res = await fetch('/send-message', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ message: msg })
            });

            const data = await res.json();
            console.log('Response:', data);
            
            // Clear input after sending
            document.getElementById('msg').value = '';
            
        } catch (error) {
            console.error('Error:', error);
        }
    }

    // Optional: Send message on Enter key
    document.getElementById('msg').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            sendMessage();
        }
    });
    </script>
</body>
</html>