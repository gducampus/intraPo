import './stimulus_bootstrap.js';

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // Intentionally silent: PWA remains optional.
        });
    });
}
