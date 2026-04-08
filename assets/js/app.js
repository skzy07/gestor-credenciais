/**
 * app.js — Utilitários globais do VaultKeeper
 */

// ── API Helper ────────────────────────────────────────
const API = {
    baseUrl: document.querySelector('meta[name="app-url"]')?.content
             || window.location.origin + window.location.pathname.split('/').slice(0,2).join('/'),

    csrf: () => document.querySelector('meta[name="csrf-token"]')?.content || '',

    async post(endpoint, data = {}) {
        const url = endpoint.startsWith('http') || endpoint.startsWith('/') ? endpoint : `${this.baseUrl}/${endpoint}`;
        try {
            const r = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.csrf()
                },
                body: JSON.stringify(data)
            });
            return await r.json();
        } catch (err) {
            return { success: false, error: 'Erro de ligação ao servidor ou erro inesperado.' };
        }
    },

    async get(endpoint) {
        const url = endpoint.startsWith('http') || endpoint.startsWith('/') ? endpoint : `${this.baseUrl}/${endpoint}`;
        try {
            const r = await fetch(url, {
                headers: { 'X-CSRF-Token': this.csrf() }
            });
            return await r.json();
        } catch (err) {
            return { success: false, error: 'Erro de ligação ao servidor ou erro inesperado.' };
        }
    }
};

// ── Toast Notifications ───────────────────────────────
const Toast = {
    container: null,
    init() {
        this.container = document.getElementById('toast-container');
    },
    show(message, type = 'info', duration = 4000) {
        if (!this.container) this.container = document.getElementById('toast-container');
        const icons = { success: '✅', error: '❌', info: 'ℹ️', warn: '⚠️' };
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `<span>${icons[type] || 'ℹ️'}</span><span>${message}</span>`;
        this.container.appendChild(toast);
        setTimeout(() => {
            toast.style.animation = 'slideInToast .3s ease reverse';
            setTimeout(() => toast.remove(), 280);
        }, duration);
    },
    success: (msg, d) => Toast.show(msg, 'success', d),
    error:   (msg, d) => Toast.show(msg, 'error', d),
    info:    (msg, d) => Toast.show(msg, 'info', d),
    warn:    (msg, d) => Toast.show(msg, 'warn', d)
};
Toast.init();

// ── Copy to clipboard ─────────────────────────────────
function copyToClipboard(text, label = 'Copiado') {
    navigator.clipboard.writeText(text).then(() => Toast.success(`${label} copiado!`));
}

// ── Loading state on buttons ──────────────────────────
function setLoading(btn, loading, text = '') {
    if (loading) {
        btn._originalText = btn.innerHTML;
        btn.innerHTML = `<span class="spinner"></span> ${text || 'A processar...'}`;
        btn.disabled = true;
    } else {
        btn.innerHTML = btn._originalText || btn.innerHTML;
        btn.disabled = false;
    }
}

// ── Modals ────────────────────────────────────────────
function openModal(id) {
    document.getElementById(id)?.classList.add('open');
}
function closeModal(id) {
    document.getElementById(id)?.classList.remove('open');
    document.dispatchEvent(new CustomEvent('modalClosed', { detail: id }));
}
document.addEventListener('click', e => {
    if (e.target.matches('.modal-overlay')) closeModal(e.target.id);
    if (e.target.matches('.modal-close')) closeModal(e.target.closest('.modal-overlay').id);
});

// ── Nav user dropdown ─────────────────────────────────
document.getElementById('nav-user-menu')?.addEventListener('click', function(e) {
    this.classList.toggle('open');
    e.stopPropagation();
});
document.addEventListener('click', () => {
    document.getElementById('nav-user-menu')?.classList.remove('open');
});

// ── Logout: limpar sessão JS ──────────────────────────
document.getElementById('logout-btn')?.addEventListener('click', () => {
    VaultCrypto.clearSession();
});

// ── Notification badge polling ────────────────────────
async function refreshNotifBadge() {
    try {
        const r = await API.get('api/notifications.php?action=unread_count');
        if (r.success) {
            const badge = document.getElementById('notif-badge');
            const link  = document.getElementById('nav-notif-link');
            if (!link) return;
            if (r.data.count > 0) {
                if (!badge) {
                    const el = document.createElement('span');
                    el.className = 'notif-badge';
                    el.id = 'notif-badge';
                    el.textContent = r.data.count > 9 ? '9+' : r.data.count;
                    link.appendChild(el);
                } else {
                    badge.textContent = r.data.count > 9 ? '9+' : r.data.count;
                }
            } else if (badge) {
                badge.remove();
            }
        }
    } catch {}
}
setInterval(refreshNotifBadge, 30000);

// ── Password toggle ───────────────────────────────────
document.querySelectorAll('.toggle-password').forEach(btn => {
    btn.addEventListener('click', function() {
        const input = this.closest('.input-group')?.querySelector('input');
        if (!input) return;
        const isPass = input.type === 'password';
        input.type = isPass ? 'text' : 'password';
        this.textContent = isPass ? '🙈' : '👁';
    });
});

// ── APP_URL global (from meta tag) ───────────────────
window.APP_URL = document.querySelector('meta[name="app-url"]')?.content || '';

// ── Theme Switcher ────────────────────────────────────
function toggleTheme() {
    const root = document.documentElement;
    const isLight = root.getAttribute('data-theme') === 'light';
    const newTheme = isLight ? 'dark' : 'light';
    root.setAttribute('data-theme', newTheme);
    localStorage.setItem('vk_theme', newTheme);
    
    // Update all toggle buttons appearance if needed (optional)
    document.querySelectorAll('.theme-toggle').forEach(btn => {
        btn.textContent = newTheme === 'light' ? '☀️' : '🌓';
    });
}
document.querySelectorAll('.theme-toggle').forEach(btn => {
    btn.addEventListener('click', toggleTheme);
    // Initialize correct icon
    const current = document.documentElement.getAttribute('data-theme');
    btn.textContent = current === 'light' ? '☀️' : '🌓';
});
