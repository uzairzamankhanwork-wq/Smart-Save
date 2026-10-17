import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import {
    Chart, BarController, BarElement, LineController, LineElement, PointElement,
    DoughnutController, ArcElement, CategoryScale, LinearScale, Tooltip, Legend, Filler,
} from 'chart.js';

Chart.register(BarController, BarElement, LineController, LineElement, PointElement,
    DoughnutController, ArcElement, CategoryScale, LinearScale, Tooltip, Legend, Filler);

// Goal colour names → hex, shared by charts (Blade uses matching Tailwind classes).
const PALETTE = {
    emerald: '#10b981', teal: '#14b8a6', sky: '#0ea5e9', indigo: '#6366f1', violet: '#8b5cf6',
    pink: '#ec4899', rose: '#f43f5e', amber: '#f59e0b', cyan: '#06b6d4', slate: '#64748b',
};
const SERIES = ['#10b981', '#f43f5e', '#6366f1', '#f59e0b', '#0ea5e9', '#8b5cf6', '#ec4899', '#64748b'];

const isDark = () => document.documentElement.classList.contains('dark');

/* ---------------------------------------------------------------- Theme -- */
Alpine.store('theme', {
    dark: isDark(),
    toggle() {
        this.dark = !this.dark;
        document.documentElement.classList.toggle('dark', this.dark);
        try { localStorage.setItem('smartsave-theme', this.dark ? 'dark' : 'light'); } catch (e) { /* ignore */ }
        refreshCharts();
    },
});

/* ------------------------------------------------- Installable app (PWA) -- */
const standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
let deferredPrompt = null;

Alpine.store('pwa', {
    canInstall: false,
    // iOS Safari has no install prompt API: show manual instructions instead.
    isIos: !standalone && /iphone|ipad|ipod/i.test(navigator.userAgent),
    iosHelp: false,
    async install() {
        if (deferredPrompt) {
            deferredPrompt.prompt();
            await deferredPrompt.userChoice;
            deferredPrompt = null;
            this.canInstall = false;
        } else if (this.isIos) {
            this.iosHelp = true;
        }
    },
});

window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    Alpine.store('pwa').canInstall = true;
});
window.addEventListener('appinstalled', () => {
    deferredPrompt = null;
    Alpine.store('pwa').canInstall = false;
    Alpine.store('toasts').push('SmartSave was installed. Open it from your home screen.');
});

// Service workers need HTTPS (or localhost). See public/sw.js for what is (not) cached.
if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost')) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}

/* -------------------------------------------------------------- Sidebar -- */
Alpine.data('shell', () => ({
    mobileNav: false,
    collapsed: (() => { try { return localStorage.getItem('smartsave-sidebar') === 'collapsed'; } catch (e) { return false; } })(),
    toggleCollapse() {
        this.collapsed = !this.collapsed;
        try { localStorage.setItem('smartsave-sidebar', this.collapsed ? 'collapsed' : 'open'); } catch (e) { /* ignore */ }
    },
}));

/* --------------------------------------------------------------- Toasts -- */
Alpine.store('toasts', {
    items: [],
    push(message, type = 'success') {
        const id = Date.now() + Math.random();
        this.items.push({ id, message, type });
        setTimeout(() => this.dismiss(id), type === 'error' ? 7000 : 4500);
    },
    dismiss(id) { this.items = this.items.filter((t) => t.id !== id); },
});

/* ------------------------------------------------------ Confirm dialogs -- */
// Any <form data-confirm="Message"> asks before submitting.
Alpine.store('confirm', {
    open: false, title: '', message: '', action: 'Confirm', danger: true, form: null,
    ask(form) {
        this.form = form;
        this.title = form.dataset.confirmTitle || 'Are you sure?';
        this.message = form.dataset.confirm;
        this.action = form.dataset.confirmAction || 'Confirm';
        this.danger = form.dataset.confirmTone !== 'safe';
        this.open = true;
    },
    accept() {
        const form = this.form;
        this.open = false;
        if (form) { form.dataset.confirmed = '1'; form.requestSubmit(); }
    },
    cancel() { this.open = false; this.form = null; },
});

document.addEventListener('submit', (e) => {
    const form = e.target;
    if (form.dataset.confirm && form.dataset.confirmed !== '1') {
        e.preventDefault();
        if (form.reportValidity()) Alpine.store('confirm').ask(form);
        return;
    }
    // Prevent double submits: disable the buttons and show a spinner.
    if (form.dataset.submitting === '1') { e.preventDefault(); return; }
    if (form.method.toLowerCase() !== 'get') {
        form.dataset.submitting = '1';
        // Deferred so the clicked button's name/value is still part of the submission.
        setTimeout(() => form.querySelectorAll('button[type=submit], button:not([type])').forEach((b) => {
            b.disabled = true;
            b.setAttribute('aria-busy', 'true');
        }), 0);
    }
}, true);

// Re-enable forms when navigating back to a cached page.
window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[data-submitting]').forEach((f) => {
        delete f.dataset.submitting;
        delete f.dataset.confirmed;
        f.querySelectorAll('button[disabled][aria-busy]').forEach((b) => { b.disabled = false; b.removeAttribute('aria-busy'); });
    });
});

/* ------------------------------------------------------ Animated number -- */
Alpine.data('countUp', (target, decimals = 2) => ({
    display: '',
    init() {
        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const fmt = (n) => n.toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
        if (reduce || !target) { this.display = fmt(target); return; }
        const start = performance.now();
        const duration = 900;
        const step = (now) => {
            const p = Math.min(1, (now - start) / duration);
            const eased = 1 - Math.pow(1 - p, 3);
            this.display = fmt(target * eased);
            if (p < 1) requestAnimationFrame(step); else this.display = fmt(target);
        };
        requestAnimationFrame(step);
    },
}));

/* --------------------------------------------------------------- Charts -- */
// Charts read their config from <canvas data-chart="type" data-chart-data='{"..."}'>,
// so no inline scripts are needed (keeps the Content-Security-Policy strict).
const charts = [];

function themeColors() {
    const dark = isDark();
    return {
        text: dark ? '#94a3b8' : '#64748b',
        grid: dark ? 'rgba(148,163,184,.12)' : 'rgba(100,116,139,.12)',
        tooltipBg: dark ? '#0f172a' : '#ffffff',
        tooltipText: dark ? '#e2e8f0' : '#0f172a',
        border: dark ? '#1e293b' : '#e2e8f0',
    };
}

function money(v, currency) {
    return `${currency} ${Number(v).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 })}`;
}

function buildConfig(type, data, currency) {
    const c = themeColors();
    const tooltip = {
        backgroundColor: c.tooltipBg, titleColor: c.tooltipText, bodyColor: c.tooltipText,
        borderColor: c.border, borderWidth: 1, padding: 10, cornerRadius: 10, boxPadding: 4,
        callbacks: { label: (ctx) => ` ${ctx.dataset.label || ctx.label}: ${money(ctx.parsed.y ?? ctx.parsed, currency)}` },
    };
    const legend = { labels: { color: c.text, usePointStyle: true, pointStyle: 'circle', boxWidth: 8, padding: 16 } };
    const scales = {
        x: { grid: { display: false }, ticks: { color: c.text }, border: { display: false } },
        y: { grid: { color: c.grid }, border: { display: false }, beginAtZero: true,
            ticks: { color: c.text, callback: (v) => Intl.NumberFormat('en', { notation: 'compact' }).format(v) } },
    };

    if (type === 'doughnut') {
        return {
            type: 'doughnut',
            data: { labels: data.labels, datasets: [{
                data: data.values,
                backgroundColor: data.colors ? data.colors.map((n, i) => PALETTE[n] || SERIES[i % SERIES.length]) : SERIES,
                borderColor: isDark() ? '#0f172a' : '#ffffff', borderWidth: 3, hoverOffset: 6,
            }] },
            options: { cutout: '68%', maintainAspectRatio: false,
                plugins: { legend: { ...legend, position: 'bottom' },
                    tooltip: { ...tooltip, callbacks: { label: (ctx) => ` ${ctx.label}: ${money(ctx.parsed, currency)}` } } } },
        };
    }

    const datasets = data.datasets.map((d, i) => {
        const color = d.color || SERIES[i % SERIES.length];
        return d.type === 'line' || type === 'line'
            ? { type: 'line', label: d.label, data: d.data, borderColor: color, backgroundColor: color + '22',
                fill: !!d.fill, tension: 0.35, pointRadius: 3, pointHoverRadius: 5, borderWidth: 2.5 }
            : { type: 'bar', label: d.label, data: d.data, backgroundColor: color, borderRadius: 6, maxBarThickness: 28 };
    });

    return {
        type: type === 'line' ? 'line' : 'bar',
        data: { labels: data.labels, datasets },
        options: { maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
            plugins: { legend, tooltip }, scales },
    };
}

function initCharts() {
    document.querySelectorAll('canvas[data-chart]').forEach((canvas) => {
        const data = JSON.parse(canvas.dataset.chartData || '{}');
        const chart = new Chart(canvas, buildConfig(canvas.dataset.chart, data, canvas.dataset.currency || ''));
        charts.push({ canvas, chart });
        canvas.closest('[data-chart-wrap]')?.querySelector('[data-skeleton]')?.remove();
        canvas.classList.remove('opacity-0');
    });
}

function refreshCharts() {
    charts.forEach(({ canvas, chart }) => {
        const data = JSON.parse(canvas.dataset.chartData || '{}');
        const cfg = buildConfig(canvas.dataset.chart, data, canvas.dataset.currency || '');
        chart.data = cfg.data;
        chart.options = cfg.options;
        chart.update('none');
    });
}

Alpine.plugin(focus);
window.Alpine = Alpine;
Alpine.start();

document.addEventListener('DOMContentLoaded', () => {
    initCharts();
    // Flash messages rendered by the server become toasts.
    document.querySelectorAll('[data-flash]').forEach((el) => {
        Alpine.store('toasts').push(el.dataset.flash, el.dataset.flashType || 'success');
    });
});
