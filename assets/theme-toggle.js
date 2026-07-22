/**
 * Sistema de Temas - SIGLECH
 * Maneja el toggle entre Light y Dark mode
 */

class ThemeManager {
    constructor() {
        this.darkClass = 'dark';
        this.storageKey = 'siglech-theme';
        this.init();
    }

    init() {
        // Cargar preferencia guardada o usar preferencia del sistema
        const savedTheme = localStorage.getItem(this.storageKey);
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

        if (savedTheme) {
            this.setTheme(savedTheme === 'dark');
        } else {
            this.setTheme(prefersDark);
        }

        // Escuchar cambios de tema del sistema
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
            if (!localStorage.getItem(this.storageKey)) {
                this.setTheme(e.matches);
            }
        });

        // Agregar listener al toggle
        this.setupToggleListener();
    }

    setTheme(isDark) {
        const html = document.documentElement;

        if (isDark) {
            html.classList.add(this.darkClass);
            html.setAttribute('data-theme', 'dark');
            localStorage.setItem(this.storageKey, 'dark');
        } else {
            html.classList.remove(this.darkClass);
            html.setAttribute('data-theme', 'light');
            localStorage.setItem(this.storageKey, 'light');
        }

        // Actualizar emoji del toggle
        this.updateToggleEmoji();
    }

    toggle() {
        const isDark = document.documentElement.classList.contains(this.darkClass);
        this.setTheme(!isDark);
    }

    setupToggleListener() {
        const toggle = document.getElementById('theme-toggle');
        if (toggle) {
            toggle.addEventListener('click', () => this.toggle());
        }
    }

    updateToggleEmoji() {
        const toggle = document.getElementById('theme-toggle');
        if (toggle) {
            const isDark = document.documentElement.classList.contains(this.darkClass);
            toggle.textContent = isDark ? '☀️' : '🌙';
        }
    }
}

// Inicializar cuando el DOM esté listo
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => new ThemeManager());
} else {
    new ThemeManager();
}
