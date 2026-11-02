let toastHost = null;
let seed = 0;

export function ensureToastHost() {
    if (toastHost && document.body.contains(toastHost)) {
        return toastHost;
    }

    const existing = document.getElementById('toast-host');
    if (existing) {
        toastHost = existing;
        return toastHost;
    }

    toastHost = document.createElement('div');
    toastHost.id = 'toast-host';
    toastHost.className = 'toast-host';
    toastHost.setAttribute('aria-live', 'polite');
    toastHost.setAttribute('aria-atomic', 'false');
    document.body.appendChild(toastHost);
    return toastHost;
}

export function showToast({ message, variant = 'info', duration = 3800 }) {
    if (!message || typeof message !== 'string') {
        throw new Error('showToast: "message" is required');
    }

    const host = ensureToastHost();
    const toast = document.createElement('div');
    toast.className = `toast toast--${variant}`;
    toast.dataset.toastId = `toast-${Date.now()}-${seed++}`;
    toast.setAttribute('role', variant === 'error' ? 'alert' : 'status');
    toast.textContent = message;
    host.appendChild(toast);

    requestAnimationFrame(() => toast.classList.add('is-visible'));

    if (duration > 0) {
        window.setTimeout(() => {
            toast.classList.remove('is-visible');
            window.setTimeout(() => toast.remove(), 300);
        }, duration);
    }

    return toast;
}

export function toastError(message, duration = 5000) {
    return showToast({ message, variant: 'error', duration });
}

export function toastSuccess(message, duration = 3200) {
    return showToast({ message, variant: 'success', duration });
}
