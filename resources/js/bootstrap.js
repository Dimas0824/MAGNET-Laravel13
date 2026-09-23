/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Laravel Echo + Reverb: real-time chat over websockets (replaces polling).
 *
 * The VITE_REVERB_* values are injected at build time (see Dockerfile stage 2
 * and compose.yaml build.args). If they are absent the bundle would silently
 * fall back to a WSS connection against a plain-ws port, which fails and makes
 * chat look like it needs a refresh. Defaults here match the local compose
 * stack (plain ws on 8080).
 */
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const reverbScheme = import.meta.env.VITE_REVERB_SCHEME ?? 'http';
const reverbPort = Number(import.meta.env.VITE_REVERB_PORT ?? 8080);

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
    wsPort: reverbPort,
    wssPort: reverbPort,
    forceTLS: reverbScheme === 'https',
    enabledTransports: ['ws', 'wss'],
});
