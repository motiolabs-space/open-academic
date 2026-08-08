import Alpine from 'alpinejs';

/**
 * Shell-wide UI state: sidebar collapse and the toast queue.
 * Sidebar collapse persists so the layout survives a page load.
 */
Alpine.store('ui', {
    sidebarCollapsed: window.localStorage.getItem('oa.sidebar') === 'collapsed',
    sidebarMobileOpen: false,
    toasts: [],

    toggleSidebar() {
        this.sidebarCollapsed = !this.sidebarCollapsed;
        window.localStorage.setItem('oa.sidebar', this.sidebarCollapsed ? 'collapsed' : 'expanded');
    },

    /**
     * @param {string} message
     * @param {'success'|'error'|'warning'|'info'} type
     */
    toast(message, type = 'success') {
        const id = Date.now() + Math.random();

        this.toasts.push({ id, message, type });

        setTimeout(() => this.dismiss(id), 5000);
    },

    dismiss(id) {
        this.toasts = this.toasts.filter((toast) => toast.id !== id);
    },
});
