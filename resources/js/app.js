import './bootstrap';
import { initializePusher, subscribeToChannels } from './pusher-setup';

// Initialize Pusher
window.Echo = initializePusher();

// Subscribe to channels
subscribeToChannels(window.Echo);