// Toast Notification Service
// Muestra notificaciones pop-up estilo toast

class ToastNotification {
    constructor() {
        this.container = null;
        this.createContainer();
    }

    createContainer() {
        if (!document.getElementById('toast-container')) {
            const container = document.createElement('div');
            container.id = 'toast-container';
            document.body.appendChild(container);
            this.container = container;
        } else {
            this.container = document.getElementById('toast-container');
        }
    }

    show(options = {}) {
        const {
            title = 'Notificación',
            message = '',
            type = 'info', // info, success, warning, error
            duration = 5000,
            action = null,
            actionText = null,
            onAction = null
        } = options;

        const toastEl = document.createElement('div');
        toastEl.className = `toast toast-${type}`;
        
        let iconClass = 'fas fa-info-circle';
        if (type === 'success') iconClass = 'fas fa-check-circle';
        if (type === 'warning') iconClass = 'fas fa-exclamation-circle';
        if (type === 'error') iconClass = 'fas fa-times-circle';

        toastEl.innerHTML = `
            <div class="toast-icon">
                <i class="${iconClass}"></i>
            </div>
            <div class="toast-content">
                <div class="toast-title">${this.escapeHtml(title)}</div>
                ${message ? `<div class="toast-message">${this.escapeHtml(message)}</div>` : ''}
            </div>
            ${actionText ? `<button class="toast-action">${this.escapeHtml(actionText)}</button>` : ''}
            <button class="toast-close" aria-label="Cerrar">
                <i class="fas fa-times"></i>
            </button>
        `;

        // Event listeners
        const closeBtn = toastEl.querySelector('.toast-close');
        closeBtn.addEventListener('click', () => this.removeToast(toastEl));

        if (actionText && onAction) {
            const actionBtn = toastEl.querySelector('.toast-action');
            actionBtn.addEventListener('click', () => {
                onAction();
                this.removeToast(toastEl);
            });
        }

        // Auto-remove
        const timer = setTimeout(() => this.removeToast(toastEl), duration);

        // Pause timer on hover
        toastEl.addEventListener('mouseenter', () => clearTimeout(timer));
        toastEl.addEventListener('mouseleave', () => {
            setTimeout(() => this.removeToast(toastEl), duration);
        });

        this.container.appendChild(toastEl);

        // Trigger animation
        setTimeout(() => toastEl.classList.add('show'), 10);

        return toastEl;
    }

    removeToast(toastEl) {
        toastEl.classList.remove('show');
        setTimeout(() => {
            if (toastEl.parentNode) {
                toastEl.remove();
            }
        }, 300);
    }

    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    // Métodos de conveniencia
    info(title, message = '', options = {}) {
        return this.show({ type: 'info', title, message, ...options });
    }

    success(title, message = '', options = {}) {
        return this.show({ type: 'success', title, message, ...options });
    }

    warning(title, message = '', options = {}) {
        return this.show({ type: 'warning', title, message, ...options });
    }

    error(title, message = '', options = {}) {
        return this.show({ type: 'error', title, message, ...options });
    }
}

// Instancia global
const toast = new ToastNotification();
