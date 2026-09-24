import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

// Whatever hostname a browser uses to reach the Vite dev server — see compose.yaml's `vite`
// service, which passes this through from .env. Defaults to 'localhost', which only ever works
// for the one machine actually running Docker. Set VITE_DEV_HOST=<your machine's LAN IP> in .env
// (e.g. 192.168.1.127) to also load the app from a phone/another device on the same network —
// without it, every asset <script> tag Blade renders points at a "localhost" that means something
// different on every other device, and the page loads completely blank (nothing under that host
// exists on a phone, so the whole JS bundle 404s silently as far as the user can see).
const devHost = process.env.VITE_DEV_HOST || 'localhost';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.jsx',
            refresh: true,
        }),
        react(),
    ],
    server: {
        // Runs inside the `vite` container (see compose.yaml) — must bind every interface so the
        // port mapping to the host actually works, not just localhost-inside-the-container.
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        // The browser connects its HMR websocket to `devHost`, even though the server itself is
        // bound to 0.0.0.0 inside the container — without this override the client tries to reach
        // the container's internal hostname, which nothing outside Docker can resolve.
        hmr: {
            host: devHost,
        },
        // Determines the origin Laravel's @vite directive writes into public/hot for every plain
        // (non-HMR) asset <script>/<link> tag — same reasoning as hmr.host above, just for the
        // initial script load rather than the live-reload socket.
        origin: `http://${devHost}:5173`,
        // The page itself is served by Laravel on :8080, but its module <script> tags load from
        // Vite on :5173 — a different origin (port counts), so the browser enforces CORS on those
        // module fetches. Vite's default CORS behaviour only allows its OWN `origin` above, not
        // whatever origin the page actually loaded from, which is why cross-origin/LAN access
        // failed even after fixing the host itself. `true` reflects back whatever Origin the
        // browser sends instead of a fixed one — fine for a local dev server nothing else talks to.
        cors: true,
    },
});
