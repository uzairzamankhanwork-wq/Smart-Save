// Runs synchronously in <head> to apply the saved theme before first paint (no flash).
(function () {
    try {
        var t = localStorage.getItem('smartsave-theme');
        var dark = t ? t === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
        if (dark) document.documentElement.classList.add('dark');
    } catch (e) { /* storage blocked: fall back to light */ }
})();
