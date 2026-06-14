// Theme Toggle Functionality
class ThemeManager {
    constructor() {
        this.themeToggle = document.getElementById('themeToggle');
        this.systemPreference = window.matchMedia('(prefers-color-scheme: dark)');
        this.init();
    }

    getStoredTheme() {
        try {
            return localStorage.getItem('theme');
        } catch (e) {
            return null;
        }
    }

    setStoredTheme(theme) {
        try {
            localStorage.setItem('theme', theme);
        } catch (e) {
            // Ignore storage failures (e.g. Safari private mode). Theme will still apply for this session.
        }
    }

    clearStoredTheme() {
        try {
            localStorage.removeItem('theme');
        } catch (e) {
            // Ignore
        }
    }

    init() {
        // Load saved theme or use system preference
        this.loadTheme();
        
        // Setup toggle button
        if (this.themeToggle) {
            this.themeToggle.addEventListener('click', () => this.toggleTheme());
        }
        
        // Listen for system theme changes
        const onSystemThemeChange = (e) => {
            if (!this.getStoredTheme()) {
                this.setTheme(e.matches ? 'dark' : 'light');
            }
        };

        // Safari/iOS compatibility: MediaQueryList used to support addListener/removeListener only.
        if (typeof this.systemPreference.addEventListener === 'function') {
            this.systemPreference.addEventListener('change', onSystemThemeChange);
        } else if (typeof this.systemPreference.addListener === 'function') {
            this.systemPreference.addListener(onSystemThemeChange);
        }
        
        // Add theme change event
        document.addEventListener('themeChange', (e) => {
            this.onThemeChange(e.detail);
        });
    }

    loadTheme() {
        const savedTheme = this.getStoredTheme();
        const systemTheme = this.systemPreference.matches ? 'dark' : 'light';
        const theme = savedTheme || 'auto';
        
        if (theme === 'auto') {
            this.setTheme(systemTheme);
        } else {
            this.setTheme(theme);
        }
    }

    toggleTheme() {
        const currentTheme = document.documentElement.getAttribute('data-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        
        this.setTheme(newTheme);
        this.setStoredTheme(newTheme);
        
        // Dispatch custom event
        document.dispatchEvent(new CustomEvent('themeChange', { detail: newTheme }));
    }

    setTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        this.updateToggleIcon(theme);
        this.updateMetaTheme(theme);
    }

    updateToggleIcon(theme) {
        if (!this.themeToggle) return;
        
        const icon = this.themeToggle.querySelector('i');
        if (icon) {
            if (theme === 'dark') {
                icon.className = 'fas fa-sun';
                icon.title = 'Switch to light theme';
            } else {
                icon.className = 'fas fa-moon';
                icon.title = 'Switch to dark theme';
            }
        }
    }

    updateMetaTheme(theme) {
        // Update meta theme-color for mobile browsers
        const themeColor = theme === 'dark' ? '#1a202c' : '#ffffff';
        let metaThemeColor = document.querySelector('meta[name="theme-color"]');
        
        if (!metaThemeColor) {
            metaThemeColor = document.createElement('meta');
            metaThemeColor.name = 'theme-color';
            document.head.appendChild(metaThemeColor);
        }
        
        metaThemeColor.content = themeColor;
    }

    onThemeChange(theme) {
        // Update charts if they exist
        this.updateCharts(theme);
        
        // Update any theme-specific elements
        this.updateThemeElements(theme);
        
        // Save preference in database if user is logged in
        this.saveThemePreference(theme);
    }

    updateCharts(theme) {
        // This would update chart.js themes if charts exist
        const charts = window.Chart ? Chart.instances : [];
        charts.forEach(chart => {
            chart.options.plugins.legend.labels.color = 
                theme === 'dark' ? '#f7fafc' : '#2d3748';
            chart.update();
        });
    }

    updateThemeElements(theme) {
        // Update any elements that need theme-specific adjustments
        const elements = document.querySelectorAll('[data-theme-update]');
        elements.forEach(element => {
            const darkValue = element.getAttribute('data-theme-dark');
            const lightValue = element.getAttribute('data-theme-light');
            
            if (theme === 'dark' && darkValue) {
                element.textContent = darkValue;
            } else if (theme === 'light' && lightValue) {
                element.textContent = lightValue;
            }
        });
    }

    async saveThemePreference(theme) {
        // Only save if user is logged in
        if (typeof userId === 'undefined') return;
        
        try {
            await fetch('/api/update_preference.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    preference: 'theme',
                    value: theme,
                    csrf_token: document.querySelector('meta[name="csrf-token"]')?.content
                })
            });
        } catch (error) {
            console.error('Failed to save theme preference:', error);
        }
    }

    // Get current theme
    getCurrentTheme() {
        return document.documentElement.getAttribute('data-theme');
    }

    // Check if dark mode is active
    isDarkMode() {
        return this.getCurrentTheme() === 'dark';
    }

    // Set theme based on preference
    setThemePreference(preference) {
        if (preference === 'auto') {
            this.clearStoredTheme();
            this.loadTheme();
        } else {
            this.setStoredTheme(preference);
            this.setTheme(preference);
        }
    }
}

// Initialize theme manager when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.themeManager = new ThemeManager();
    
    // Add theme transition
    document.documentElement.style.transition = 'background-color 0.3s ease, color 0.3s ease';
    
    // Add keyboard shortcut (Alt+T) for theme toggle
    document.addEventListener('keydown', (e) => {
        if (e.altKey && e.key === 't') {
            e.preventDefault();
            window.themeManager.toggleTheme();
        }
    });
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ThemeManager;
}
