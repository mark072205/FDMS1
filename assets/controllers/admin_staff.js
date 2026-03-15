// Admin Common Functionality
class AdminManager {
    constructor() {
        this.csrfToken = this.getCsrfToken();
        this.init();
    }

    init() {
        this.setupConfirmationModal();
        this.setupStatusToggles();
        this.setupDropdowns();
		this.setupVerificationToggles();
    }

    getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    setupConfirmationModal() {
        // Create confirmation modal if it doesn't exist
        if (!document.getElementById('confirmationModal')) {
            const modalHTML = `
                <div id="confirmationModal" class="modal-overlay" style="display: none;">
                    <div class="modal-content">
                        <button type="button" class="modal-close" onclick="adminManager.closeConfirmationModal()" aria-label="Close">
                            <i class="bi bi-x-lg"></i>
                        </button>
                        <div class="modal-body">
                            <div style="text-align: center; padding: 1rem 0 2rem;">
                                <div class="modal-warning-icon">
                                    <i class="bi bi-exclamation-triangle-fill"></i>
                                </div>
                                <h3 class="modal-title" id="modalTitle">Confirm Action</h3>
                                <p id="modalMessage" class="modal-message">Are you sure you want to perform this action?</p>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="adminManager.closeConfirmationModal()" style="min-width: 100px;">
                                <span>Cancel</span>
                            </button>
                            <button type="button" class="btn btn-primary" id="modalConfirmBtn" style="min-width: 120px; display: flex; align-items: center; gap: 0.5rem;">
                                <i class="bi bi-check-lg"></i>
                                <span>Confirm</span>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', modalHTML);
        }

        // Close modal when clicking outside
        document.addEventListener('click', (event) => {
            const modal = document.getElementById('confirmationModal');
            if (modal && event.target === modal) {
                this.closeConfirmationModal();
            }
        });
    }

    showConfirmationModal(message, confirmCallback, buttonType = 'primary', title = 'Confirm Action') {
        const modal = document.getElementById('confirmationModal');
        const modalMessage = document.getElementById('modalMessage');
        const modalTitle = document.getElementById('modalTitle');
        const confirmBtn = document.getElementById('modalConfirmBtn');
        
        if (!modal || !modalMessage || !confirmBtn) {
            console.error('Confirmation modal elements not found');
            return;
        }

        // Update title
        if (modalTitle) {
            modalTitle.textContent = title;
        }

        // Update message - support HTML content
        modalMessage.innerHTML = message;
        
        // Update button style and icon based on button type
        confirmBtn.className = `btn btn-${buttonType}`;
        
        // Update button icon based on type
        const iconClass = buttonType === 'danger' ? 'bi-trash-fill' : 
                         buttonType === 'success' ? 'bi-check-circle-fill' : 
                         buttonType === 'warning' ? 'bi-exclamation-triangle-fill' : 
                         'bi-check-lg';
        
        confirmBtn.innerHTML = `<i class="bi ${iconClass}"></i><span>${buttonType === 'danger' ? 'Delete' : buttonType === 'success' ? 'Confirm' : 'Confirm'}</span>`;
        
        // Remove any existing event listeners
        const newConfirmBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        
        // Add new event listener
        const actualConfirmBtn = document.getElementById('modalConfirmBtn');
        actualConfirmBtn.addEventListener('click', () => {
            this.closeConfirmationModal();
            confirmCallback();
        });
        
        modal.style.display = 'flex';
    }

    closeConfirmationModal() {
        const modal = document.getElementById('confirmationModal');
        if (modal) {
            modal.style.display = 'none';
        }
    }

    setupStatusToggles() {
        // Delegate to document to survive Turbo navigation; bind only once
        if (this.statusDelegationBound) return;
        this.statusDelegationBound = true;

        document.addEventListener('click', (event) => {
            const button = event.target.closest('.toggle-status');
            if (!button) return;
            event.preventDefault();

            const userId = button.dataset.userId;
            const currentStatus = button.dataset.currentStatus;
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
            const action = newStatus === 'active' ? 'enable' : 'disable';
            const buttonType = newStatus === 'active' ? 'success' : 'danger';

            this.showConfirmationModal(
                `Are you sure you want to ${action} this user?`,
                () => {
                    this.toggleUserStatus(userId, newStatus, button);
                },
                buttonType,
                `${action.charAt(0).toUpperCase() + action.slice(1)} User`
            );
        });
    }

    async toggleUserStatus(userId, newStatus, buttonElement) {
        try {
            const response = await fetch(`/admin/users/${userId}/toggle-status`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    _token: this.csrfToken
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            
            if (data.success) {
                // Update button state - maintain icon styling
                buttonElement.dataset.currentStatus = newStatus;
                buttonElement.className = `btn-icon toggle-status ${newStatus === 'active' ? 'btn-danger' : 'btn-success'}`;
                buttonElement.innerHTML = `<i class="bi bi-${newStatus === 'active' ? 'person-x' : 'person-check'}"></i>`;
                buttonElement.title = newStatus === 'active' ? 'Disable User' : 'Enable User';
                
                // Update status badge
                const statusBadge = document.getElementById(`status-${userId}`);
                if (statusBadge) {
                    statusBadge.textContent = newStatus === 'active' ? 'Active' : 'Disabled';
                    statusBadge.className = `badge-status ${newStatus === 'active' ? 'is-active' : 'is-inactive'}`;
                }
                
                // Show success message
                this.showAlert('success', data.message || `User ${newStatus === 'active' ? 'enabled' : 'disabled'} successfully`);
            } else {
                this.showAlert('error', data.message || 'An error occurred');
            }
        } catch (error) {
            console.error('Error toggling user status:', error);
            this.showAlert('error', 'An error occurred while updating user status');
        }
    }

    setupVerificationToggles() {
        // Delegate to document to survive Turbo navigation; bind only once
        if (this.verificationDelegationBound) return;
        this.verificationDelegationBound = true;

        document.addEventListener('click', (event) => {
            const button = event.target.closest('.toggle-verification');
            if (!button) return;
            event.preventDefault();

            const userId = button.dataset.userId;
            const currentVerified = button.dataset.currentVerified;
            const newStatus = currentVerified === 'verified' ? 'unverified' : 'verified';
            const action = newStatus === 'verified' ? 'verify' : 'unverify';
            const buttonType = newStatus === 'verified' ? 'success' : 'warning';

            this.showConfirmationModal(
                `Are you sure you want to ${action} this user?`,
                () => {
                    this.toggleUserVerification(userId, newStatus, button);
                },
                buttonType,
                `${action.charAt(0).toUpperCase() + action.slice(1)} User`
            );
        });
    }

    async toggleUserVerification(userId, newStatus, buttonElement) {
        try {
            const response = await fetch(`/admin/users/${userId}/toggle-verification`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    _token: this.csrfToken
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            
            if (data.success) {
                // Update button state - maintain icon styling
                buttonElement.dataset.currentVerified = newStatus;
                buttonElement.className = `btn-icon toggle-verification ${newStatus === 'verified' ? 'btn-warning' : 'btn-success'}`;
                buttonElement.innerHTML = `<i class="bi bi-${newStatus === 'verified' ? 'shield-x' : 'shield-check'}"></i>`;
                buttonElement.title = newStatus === 'verified' ? 'Unverify User' : 'Verify User';
                
                // Update verification badge
                const verifiedBadge = document.getElementById(`verified-status-${userId}`);
                if (verifiedBadge) {
                    verifiedBadge.textContent = newStatus === 'verified' ? 'Verified' : 'Unverified';
                    verifiedBadge.className = `badge-status ${newStatus === 'verified' ? 'is-active' : 'is-pending'}`;
                }
                
                // Show success message
                this.showAlert('success', data.message || `User ${newStatus === 'verified' ? 'verified' : 'unverified'} successfully`);
            } else {
                this.showAlert('error', data.message || 'An error occurred');
            }
        } catch (error) {
            console.error('Error toggling user verification:', error);
            this.showAlert('error', 'An error occurred while updating user verification');
        }
    }

    setupDropdowns() {
        // Toggle the admin dropdown and chevron (if present)
        const toggleDropdown = () => {
            const dropdown = document.getElementById('adminDropdown');
            const chevronIcon = document.getElementById('chevronIcon');

            if (!dropdown) {
                console.log('Dropdown not found');
                return;
            }

            console.log('Toggling dropdown');
            dropdown.classList.toggle('show');

            if (chevronIcon) {
                chevronIcon.className = dropdown.classList.contains('show') ? 'bi bi-chevron-up chevron-icon' : 'bi bi-chevron-down chevron-icon';
            }
        };

        // Make toggleDropdown globally accessible
        window.toggleDropdown = toggleDropdown;

		// Close dropdown when clicking outside the badge
		document.addEventListener('click', (event) => {
			const dropdown = document.getElementById('adminDropdown');
			const badge = document.querySelector('.admin-badge');
			const chevronIcon = document.getElementById('chevronIcon');

			if (!badge || !dropdown) {
				console.log('Badge or dropdown not found:', { badge: !!badge, dropdown: !!dropdown });
				return;
			}

			if (!badge.contains(event.target)) {
				dropdown.classList.remove('show');
				if (chevronIcon) chevronIcon.className = 'bi bi-chevron-down chevron-icon';
			}
		});
    }


    showAlert(type, message) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="alert-close" onclick="this.parentElement.remove()" aria-label="Close">&times;</button>
        `;
        
        const container = document.querySelector('.admin-container');
        if (container) {
            container.insertBefore(alertDiv, container.firstChild);
        } else {
            document.body.prepend(alertDiv);
        }
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }

    // Copy to clipboard functionality
    async copyToClipboard(text, successMessage = 'Copied to clipboard!') {
        try {
            await navigator.clipboard.writeText(text);
            this.showAlert('success', successMessage);
        } catch (err) {
            console.error('Failed to copy to clipboard:', err);
            this.showAlert('error', 'Failed to copy to clipboard');
        }
    }

    // Update user activity status
    async updateUserActivity(userId, isActive) {
        try {
            const response = await fetch(`/admin/users/${userId}/update-activity`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    _token: this.csrfToken,
                    is_active: isActive
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            
            if (data.success) {
                this.showAlert('success', data.message || 'User activity updated successfully');
            } else {
                this.showAlert('error', data.message || 'An error occurred');
            }
        } catch (error) {
            console.error('Error updating user activity:', error);
            this.showAlert('error', 'An error occurred while updating user activity');
        }
    }
}

// Initialize admin manager when DOM is ready
function initializeAdminManager() {
    console.log('Initializing AdminManager...');
    try {
        if (window.adminManager instanceof AdminManager) {
            // Re-init to ensure fresh bindings if needed
            window.adminManager.init();
        } else {
            window.adminManager = new AdminManager();
        }
        console.log('AdminManager ready');
    } catch (error) {
        console.error('Error initializing AdminManager:', error);
    }
}

document.addEventListener('DOMContentLoaded', initializeAdminManager);
document.addEventListener('turbo:load', initializeAdminManager);

// Make functions globally accessible for backward compatibility
window.showConfirmationModal = function(message, confirmCallback, buttonType, title) {
    if (window.adminManager) {
        window.adminManager.showConfirmationModal(message, confirmCallback, buttonType, title);
    }
};

window.closeConfirmationModal = function() {
    if (window.adminManager) {
        window.adminManager.closeConfirmationModal();
    }
};

window.showAlert = function(type, message) {
    if (window.adminManager) {
        window.adminManager.showAlert(type, message);
    }
};

window.copyToClipboard = function(text, successMessage) {
    if (window.adminManager) {
        window.adminManager.copyToClipboard(text, successMessage);
    }
};

// Notifications Page Functionality
class NotificationsPageManager {
    constructor() {
        this.currentTab = 'all';
        this.init();
    }

    init() {
        const markAllReadBtn = document.getElementById('markAllReadBtn');
        const notificationItems = document.querySelectorAll('.notification-item-page');

        // Initialize tabs
        this.initTabs();

        // Mark all as read
        if (markAllReadBtn) {
            markAllReadBtn.addEventListener('click', () => this.markAllAsRead(markAllReadBtn, notificationItems));
        }

        // Handle notification item clicks
        notificationItems.forEach(item => {
            item.addEventListener('click', () => this.handleNotificationClick(item, markAllReadBtn));
        });
    }

    initTabs() {
        const tabs = document.querySelectorAll('.notification-tab');
        const tabContents = document.querySelectorAll('.notifications-tab-content');

        if (tabs.length === 0) {
            console.warn('No notification tabs found');
            return;
        }

        if (tabContents.length === 0) {
            console.warn('No notification tab contents found');
            return;
        }

        tabs.forEach(tab => {
            tab.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                const tabName = tab.dataset.tab;
                
                if (!tabName) {
                    console.warn('Tab missing data-tab attribute');
                    return;
                }

                // Update active tab
                tabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');

                // Show/hide tab content
                tabContents.forEach(content => {
                    if (content.id === `tab-${tabName}`) {
                        content.classList.remove('hidden');
                    } else {
                        content.classList.add('hidden');
                    }
                });

                this.currentTab = tabName;
            });
        });
    }

    async markAllAsRead(button, notificationItems) {
        try {
            const response = await fetch('/admin/notifications/mark-all-read', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await response.json();

            if (data.success) {
                // Remove unread class from all notifications
                notificationItems.forEach(item => {
                    item.classList.remove('unread');
                    item.dataset.isRead = 'true';
                    // Remove pulse animation and dot
                    const icon = item.querySelector('.notification-item-page__icon');
                    if (icon) icon.classList.remove('pulse');
                    const dot = item.querySelector('.notification-dot');
                    if (dot) dot.remove();
                });

                // Hide the button
                button.style.display = 'none';

                // Update stats
                const unreadStatValue = document.querySelector('.notification-stat-value[style*="color: #3b82f6"]');
                if (unreadStatValue) {
                    unreadStatValue.textContent = '0';
                }

                const readStatValue = document.querySelector('.notification-stat-value[style*="color: #10b981"]');
                if (readStatValue) {
                    const totalNotifications = notificationItems.length;
                    readStatValue.textContent = totalNotifications;
                }

                // Update tab counts
                const unreadTabCount = document.querySelector('.tab-count-unread');
                if (unreadTabCount) {
                    unreadTabCount.textContent = '0';
                }

                // If on unread tab, show empty state
                if (this.currentTab === 'unread') {
                    const unreadTabContent = document.getElementById('tab-unread');
                    if (unreadTabContent) {
                        const list = unreadTabContent.querySelector('.notifications-list');
                        if (list) {
                            list.innerHTML = `
                                <div class="notification-empty-state">
                                    <div class="notification-empty-state__icon">
                                        <i class="bi bi-check-circle"></i>
                                    </div>
                                    <h3 class="notification-empty-state__title">No unread notifications</h3>
                                    <p class="notification-empty-state__message">All caught up! You have no unread notifications.</p>
                                </div>
                            `;
                        }
                    }
                }

                // Show success message
                if (window.adminManager) {
                    window.adminManager.showAlert('success', 'All notifications marked as read.');
                } else {
                    alert('All notifications marked as read.');
                }
            } else {
                if (window.adminManager) {
                    window.adminManager.showAlert('error', 'Failed to mark all notifications as read.');
                } else {
                    alert('Failed to mark all notifications as read.');
                }
            }
        } catch (error) {
            console.error('Error marking all as read:', error);
            if (window.adminManager) {
                window.adminManager.showAlert('error', 'An error occurred. Please try again.');
            } else {
                alert('An error occurred. Please try again.');
            }
        }
    }

    async handleNotificationClick(item, markAllReadBtn) {
        const notificationId = item.dataset.notificationId;
        const actionUrl = item.dataset.actionUrl;

        // Mark as read if unread
        if (item.classList.contains('unread')) {
            try {
                const response = await fetch(`/admin/notifications/${notificationId}/read`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await response.json();
                if (data.success) {
                    item.classList.remove('unread');
                    item.dataset.isRead = 'true';
                    
                    // Remove pulse animation and dot
                    const icon = item.querySelector('.notification-item-page__icon');
                    if (icon) icon.classList.remove('pulse');
                    const dot = item.querySelector('.notification-dot');
                    if (dot) dot.remove();
                    
                    // Update unread count
                    const unreadStatValue = document.querySelector('.notification-stat-value[style*="color: #3b82f6"]');
                    if (unreadStatValue) {
                        const currentCount = parseInt(unreadStatValue.textContent) || 0;
                        unreadStatValue.textContent = Math.max(0, currentCount - 1);
                    }

                    // Update read count
                    const readStatValue = document.querySelector('.notification-stat-value[style*="color: #10b981"]');
                    if (readStatValue) {
                        const currentCount = parseInt(readStatValue.textContent) || 0;
                        readStatValue.textContent = currentCount + 1;
                    }

                    // Update tab counts
                    const unreadTabCount = document.querySelector('.tab-count-unread');
                    if (unreadTabCount) {
                        const currentCount = parseInt(unreadTabCount.textContent) || 0;
                        const newCount = Math.max(0, currentCount - 1);
                        unreadTabCount.textContent = newCount;
                        if (newCount === 0) {
                            unreadTabCount.classList.remove('tab-count-unread');
                        }
                    }

                    // Hide mark all button if no unread notifications
                    const unreadItems = document.querySelectorAll('.notification-item-page.unread');
                    if (unreadItems.length === 0 && markAllReadBtn) {
                        markAllReadBtn.style.display = 'none';
                    }

                    // If on unread tab and this was the last unread, remove it from view
                    if (this.currentTab === 'unread') {
                        item.style.display = 'none';
                        const unreadTabContent = document.getElementById('tab-unread');
                        const visibleUnread = unreadTabContent.querySelectorAll('.notification-item-page:not([style*="display: none"])');
                        if (visibleUnread.length === 0) {
                            const list = unreadTabContent.querySelector('.notifications-list');
                            if (list) {
                                list.innerHTML = `
                                    <div class="notification-empty-state">
                                        <div class="notification-empty-state__icon">
                                            <i class="bi bi-check-circle"></i>
                                        </div>
                                        <h3 class="notification-empty-state__title">No unread notifications</h3>
                                        <p class="notification-empty-state__message">All caught up! You have no unread notifications.</p>
                                    </div>
                                `;
                            }
                        }
                    }
                }
            } catch (error) {
                console.error('Error marking notification as read:', error);
            }
        }

        // Navigate to action URL if available
        if (actionUrl) {
            window.location.href = actionUrl;
        }
    }
}

// Initialize notifications page manager
function initializeNotificationsPage() {
    if (document.querySelector('.notifications-card') || document.querySelector('.notification-tab')) {
        window.notificationsPageManager = new NotificationsPageManager();
    }
}

document.addEventListener('DOMContentLoaded', initializeNotificationsPage);
document.addEventListener('turbo:load', initializeNotificationsPage);

// ============================================
// Admin Notifications Functionality (Header Dropdown)
// ============================================
class AdminNotifications {
    constructor() {
        this.dropdown = document.getElementById('notificationDropdown');
        this.bell = document.getElementById('notificationBell');
        this.badge = document.getElementById('notificationBadge');
        this.content = document.getElementById('notificationContent');
        this.markAllReadBtn = document.getElementById('markAllNotificationsRead');
        this.isOpen = false;
        this.pollInterval = null;
        
        this.init();
    }

    init() {
        if (!this.bell || !this.dropdown) return;

        // Load initial unread count
        this.updateUnreadCount();

        // Bell click handler
        this.bell.addEventListener('click', (e) => {
            e.stopPropagation();
            this.toggleDropdown();
        });

        // Mark all as read handler
        if (this.markAllReadBtn) {
            this.markAllReadBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.markAllAsRead();
            });
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (this.isOpen && !this.dropdown.contains(e.target) && !this.bell.contains(e.target)) {
                this.closeDropdown();
            }
        });

        // Load notifications when dropdown opens
        this.bell.addEventListener('click', () => {
            if (!this.isOpen) {
                this.loadNotifications();
            }
        });

        // Start polling for new notifications (every 60 seconds)
        this.startPolling();
    }

    toggleDropdown() {
        if (this.isOpen) {
            this.closeDropdown();
        } else {
            this.openDropdown();
        }
    }

    openDropdown() {
        if (!this.dropdown) return;
        
        this.dropdown.classList.remove('hidden');
        this.isOpen = true;
        if (this.bell) {
            this.bell.classList.add('active');
        }
        this.loadNotifications();
    }

    closeDropdown() {
        if (!this.dropdown) return;
        
        this.dropdown.classList.add('hidden');
        this.isOpen = false;
        if (this.bell) {
            this.bell.classList.remove('active');
        }
    }

    async loadNotifications() {
        if (!this.content) return;

        // Show loading state
        this.content.innerHTML = `
            <div class="notification-loading">
                <p>Loading notifications...</p>
            </div>
        `;

        try {
            const response = await fetch('/notifications/list?limit=15');
            const data = await response.json();

            if (data.success) {
                this.renderNotifications(data.notifications);
            } else {
                this.showError('Failed to load notifications.');
            }
        } catch (error) {
            console.error('Error loading notifications:', error);
            this.showError('Network error. Please try again.');
        }
    }

    renderNotifications(notifications) {
        if (!this.content) return;

        if (notifications.length === 0) {
            this.content.innerHTML = `
                <div class="notification-empty">
                    <p>No new notifications</p>
                </div>
            `;
            if (this.markAllReadBtn) {
                this.markAllReadBtn.classList.remove('visible');
            }
            return;
        }

        // Show mark all as read button if there are unread notifications
        const hasUnread = notifications.some(n => !n.isRead);
        if (this.markAllReadBtn) {
            if (hasUnread) {
                this.markAllReadBtn.classList.add('visible');
            } else {
                this.markAllReadBtn.classList.remove('visible');
            }
        }

        const notificationsHtml = notifications.map(notification => {
            const unreadClass = !notification.isRead ? 'unread' : '';
            const user = notification.user || null;
            const avatarHtml = this.getAvatarHtml(user);

            return `
                <div class="notification-item ${unreadClass}" data-notification-id="${notification.id}" data-action-url="${notification.actionUrl || ''}">
                    ${avatarHtml}
                    <div class="notification-item__content">
                        <h4 class="notification-item__title">${this.escapeHtml(notification.title)}</h4>
                        <p class="notification-item__message">${this.escapeHtml(notification.message)}</p>
                        <p class="notification-item__time">${notification.createdAtFormatted}</p>
                    </div>
                </div>
            `;
        }).join('');

        this.content.innerHTML = notificationsHtml;

        // Add click handlers to notification items
        this.content.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', () => {
                const notificationId = item.dataset.notificationId;
                const actionUrl = item.dataset.actionUrl;

                // Mark as read if unread
                if (item.classList.contains('unread')) {
                    this.markAsRead(notificationId, item);
                }

                // Navigate to action URL if available
                if (actionUrl) {
                    window.location.href = actionUrl;
                }
            });
        });
    }

    async updateUnreadCount() {
        try {
            const response = await fetch('/notifications/unread-count');
            const data = await response.json();

            if (data.success && this.badge && this.bell) {
                const count = data.count;
                
                if (count > 0) {
                    // Display 99+ for 3 digits (100 or more)
                    this.badge.textContent = count >= 100 ? '99+' : count;
                    this.badge.style.display = 'block';
                    // Add active class to bell icon
                    this.bell.classList.add('has-notifications');
                } else {
                    this.badge.style.display = 'none';
                    // Remove active class from bell icon
                    this.bell.classList.remove('has-notifications');
                }
            }
        } catch (error) {
            console.error('Error updating unread count:', error);
        }
    }

    async markAsRead(notificationId, element) {
        try {
            const response = await fetch(`/notifications/${notificationId}/read`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
            });

            const data = await response.json();

            if (data.success && element) {
                element.classList.remove('unread');
                this.updateUnreadCount();
            }
        } catch (error) {
            console.error('Error marking notification as read:', error);
        }
    }

    async markAllAsRead() {
        try {
            const response = await fetch('/notifications/mark-all-read', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
            });

            const data = await response.json();

            if (data.success) {
                // Hide the button
                if (this.markAllReadBtn) {
                    this.markAllReadBtn.classList.remove('visible');
                }
                // Reload notifications
                this.loadNotifications();
                this.updateUnreadCount();
            }
        } catch (error) {
            console.error('Error marking all as read:', error);
        }
    }

    startPolling() {
        // Poll every 60 seconds for new notifications
        this.pollInterval = setInterval(() => {
            this.updateUnreadCount();
            // If dropdown is open, refresh notifications
            if (this.isOpen) {
                this.loadNotifications();
            }
        }, 60000);
    }

    stopPolling() {
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
            this.pollInterval = null;
        }
    }

    getIconName(type) {
        const iconMap = {
            'new_user': 'bi-person-plus-fill',
            'new_project': 'bi-folder-plus-fill',
            'new_proposal': 'bi-file-earmark-text-fill',
            'user_status_changed': 'bi-person-check-fill',
            'file_uploaded': 'bi-cloud-upload-fill',
            'system_alert': 'bi-exclamation-triangle-fill',
            'profile_picture_changed': 'bi-image-fill',
            'project_updated': 'bi-folder-check-fill',
            'project_deleted': 'bi-folder-x-fill',
            'proposal_updated': 'bi-file-earmark-check-fill',
            'proposal_deleted': 'bi-file-earmark-x-fill',
        };
        return iconMap[type] || 'bi-bell-fill';
    }

    getAvatarHtml(user) {
        if (!user) {
            // Fallback to icon if no user
            return `
                <div class="notification-item__icon">
                    <i class="bi bi-bell-fill"></i>
                </div>
            `;
        }

        const firstName = user.firstName || '';
        const lastName = user.lastName || '';
        const initials = (firstName.charAt(0) + lastName.charAt(0)).toUpperCase() || 'U';
        const profilePicture = user.profilePicture || null;
        const userType = user.userType || 'user';
        
        // Determine role class for avatar background
        const roleClass = userType === 'designer' ? 'is-designer' : (userType === 'client' ? 'is-client' : (userType === 'staff' ? 'is-staff' : 'is-admin'));

        if (profilePicture) {
            return `
                <div class="notification-item__avatar ${roleClass}">
                    <img src="/uploads/profile_pictures/${this.escapeHtml(profilePicture)}" 
                         alt="${this.escapeHtml(firstName + ' ' + lastName)}" 
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <span style="display: none;">${this.escapeHtml(initials)}</span>
                </div>
            `;
        } else {
            return `
                <div class="notification-item__avatar ${roleClass}">
                    <span>${this.escapeHtml(initials)}</span>
                </div>
            `;
        }
    }

    showError(message) {
        if (!this.content) return;
        
        this.content.innerHTML = `
            <div class="notification-empty">
                <p>${this.escapeHtml(message)}</p>
            </div>
        `;
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize notifications when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    window.adminNotifications = new AdminNotifications();
});

// ============================================
// Admin Search Functionality
// ============================================
class AdminSearch {
    constructor() {
        this.searchInput = null;
        this.searchDropdown = null;
        this.debounceTimer = null;
        this.currentPage = this.detectCurrentPage();
        this.isSearching = false;
        
        this.init();
    }

    init() {
        this.createSearchElements();
        this.bindEvents();
    }

    detectCurrentPage() {
        const path = window.location.pathname;
        // Check if user is ROLE_STAFF (not ROLE_ADMIN)
        const isStaff = document.body.dataset.userRole === 'staff' || 
                       (document.querySelector('meta[name="user-role"]')?.getAttribute('content') === 'staff');
        
        if (path.includes('/admin/users')) {
            // ROLE_STAFF cannot search users, redirect to global search
            return isStaff ? 'dashboard' : 'users';
        }
        if (path.includes('/activity-logs')) return 'activitylogs';
        if (path.includes('/admin/category')) return 'categories';
        if (path.includes('/admin/projects')) return 'projects';
        if (path.includes('/admin/proposals')) return 'proposals';
        if (path.includes('/admin/files')) return 'files';
        if (path.includes('/admin')) return 'dashboard';
        return 'dashboard'; // default to global search
    }

    createSearchElements() {
        const searchBox = document.querySelector('.search-box');
        if (!searchBox) return;

        // Create dropdown container
        this.searchDropdown = document.createElement('div');
        this.searchDropdown.className = 'search-dropdown';
        this.searchDropdown.style.display = 'none';
        
        // Insert dropdown as a child of the search box for proper positioning
        searchBox.appendChild(this.searchDropdown);
        
        // Get the search input
        this.searchInput = searchBox.querySelector('input[type="search"]');
        if (this.searchInput) {
            this.searchInput.setAttribute('autocomplete', 'off');
            
            // Set dropdown width to match search box width
            const updateDropdownWidth = () => {
                if (this.searchDropdown && searchBox) {
                    const boxRect = searchBox.getBoundingClientRect();
                    this.searchDropdown.style.width = boxRect.width + 'px';
                }
            };
            
            // Update width on load and resize
            updateDropdownWidth();
            window.addEventListener('resize', updateDropdownWidth);
        }
    }

    bindEvents() {
        if (!this.searchInput) return;

        // Input event with debouncing
        this.searchInput.addEventListener('input', (e) => {
            this.handleSearch(e.target.value);
        });

        // Focus events
        this.searchInput.addEventListener('focus', () => {
            if (this.searchInput.value.length >= 2) {
                this.showDropdown();
            }
        });

        // Click outside to close
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.search-box') && !e.target.closest('.search-dropdown')) {
                this.hideDropdown();
            }
        });

        // Keyboard navigation
        this.searchInput.addEventListener('keydown', (e) => {
            this.handleKeyboardNavigation(e);
        });
    }

    handleSearch(query) {
        // Clear previous timer
        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }

        // Hide dropdown if query is too short
        if (query.length < 2) {
            this.hideDropdown();
            return;
        }

        // Debounce the search
        this.debounceTimer = setTimeout(() => {
            this.performSearch(query);
        }, 300);
    }

    async performSearch(query) {
        if (this.isSearching) return;
        
        this.isSearching = true;
        this.showLoading();

        try {
            // Check if user is ROLE_STAFF (not ROLE_ADMIN)
            const isStaff = document.body.dataset.userRole === 'staff' || 
                           (document.querySelector('meta[name="user-role"]')?.getAttribute('content') === 'staff');
            
            let endpoint;
            if (this.currentPage === 'users') {
                // ROLE_STAFF cannot search users, use global search instead
                if (isStaff) {
                    endpoint = '/search/global';
                } else {
                    endpoint = '/search/users';
                }
            } else if (this.currentPage === 'categories') {
                endpoint = '/search/categories';
            } else if (this.currentPage === 'projects') {
                endpoint = '/search/projects';
            } else if (this.currentPage === 'proposals') {
                endpoint = '/search/proposals';
            } else if (this.currentPage === 'files') {
                endpoint = '/search/files';
            } else if (this.currentPage === 'activitylogs') {
                endpoint = '/search/activity-logs';
            } else {
                endpoint = '/search/global';
            }
            const response = await fetch(`${endpoint}?q=${encodeURIComponent(query)}`, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json',
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            this.displayResults(data.results);
            
        } catch (error) {
            console.error('Search error:', error);
            this.showError('Search failed. Please try again.');
        } finally {
            this.isSearching = false;
        }
    }

    displayResults(results) {
        if (!this.searchDropdown) return;

        if (results.length === 0) {
            this.searchDropdown.innerHTML = `
                <div class="search-no-results">
                    <div class="search-no-results-icon">
                        <i class="bi bi-search"></i>
                    </div>
                    <div class="search-no-results-text">No results found</div>
                </div>
            `;
        } else {
            const resultsHtml = results.map(result => this.createResultItem(result)).join('');
            this.searchDropdown.innerHTML = resultsHtml;
        }

        this.showDropdown();
    }

    createResultItem(result) {
        const badgeClass = result.badge ? `badge ${result.badge.class}` : '';
        const badgeHtml = result.badge ? `<span class="${badgeClass}">${result.badge.text}</span>` : '';
        
        return `
            <a href="${result.url}" class="search-result-item" data-type="${result.type}">
                <div class="search-result-content">
                    <div class="search-result-title">${this.escapeHtml(result.title)}</div>
                    <div class="search-result-subtitle">${this.escapeHtml(result.subtitle)}</div>
                </div>
                <div class="search-result-badge">
                    ${badgeHtml}
                </div>
            </a>
        `;
    }

    showLoading() {
        if (!this.searchDropdown) return;
        
        this.searchDropdown.innerHTML = `
            <div class="search-loading">
                <div class="search-loading-spinner"></div>
                <div class="search-loading-text">Searching...</div>
            </div>
        `;
        this.showDropdown();
    }

    showError(message) {
        if (!this.searchDropdown) return;
        
        this.searchDropdown.innerHTML = `
            <div class="search-error">
                <div class="search-error-icon">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
                <div class="search-error-text">${this.escapeHtml(message)}</div>
            </div>
        `;
        this.showDropdown();
    }

    showDropdown() {
        if (this.searchDropdown) {
            this.searchDropdown.style.display = 'block';
        }
    }

    hideDropdown() {
        if (this.searchDropdown) {
            this.searchDropdown.style.display = 'none';
        }
    }

    handleKeyboardNavigation(e) {
        if (!this.searchDropdown || this.searchDropdown.style.display === 'none') return;

        const results = this.searchDropdown.querySelectorAll('.search-result-item');
        const currentActive = this.searchDropdown.querySelector('.search-result-item.active');
        let activeIndex = -1;

        if (currentActive) {
            activeIndex = Array.from(results).indexOf(currentActive);
        }

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                activeIndex = Math.min(activeIndex + 1, results.length - 1);
                this.setActiveResult(results, activeIndex);
                break;
            case 'ArrowUp':
                e.preventDefault();
                activeIndex = Math.max(activeIndex - 1, -1);
                this.setActiveResult(results, activeIndex);
                break;
            case 'Enter':
                e.preventDefault();
                if (currentActive) {
                    currentActive.click();
                }
                break;
            case 'Escape':
                this.hideDropdown();
                this.searchInput.blur();
                break;
        }
    }

    setActiveResult(results, index) {
        results.forEach((result, i) => {
            result.classList.toggle('active', i === index);
        });
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize search when DOM is ready
function initializeAdminSearch() {
    console.log('Initializing AdminSearch...');
    
    // Test if search elements exist
    const searchBox = document.querySelector('.search-box');
    const searchInput = document.querySelector('#adminSearchInput');
    console.log('Search box found:', searchBox);
    console.log('Search input found:', searchInput);
    
    if (!searchBox || !searchInput) {
        console.log('Search elements not found, skipping AdminSearch initialization');
        return;
    }
    
    try {
        // Only initialize if not already initialized
        if (!window.adminSearchInstance) {
            window.adminSearchInstance = new AdminSearch();
            console.log('AdminSearch initialized successfully');
        }
    } catch (error) {
        console.error('Error initializing AdminSearch:', error);
    }
}

document.addEventListener('DOMContentLoaded', initializeAdminSearch);
document.addEventListener('turbo:load', initializeAdminSearch);

// ============================================
// Admin Analytics Charts
// ============================================
class AdminAnalytics {
    constructor() {
        this.charts = {};
        this.init();
    }

    init() {
        if (!window.analyticsData) {
            // Silently return if not on analytics page
            return;
        }

        this.initRevenueChart();
        this.initUserGrowthChart();
        this.initProjectActivityChart();
        this.initProposalActivityChart();
        this.setupDateRangeReset();
        this.setupExport();
    }

    initRevenueChart() {
        const ctx = document.getElementById('revenueChart');
        if (!ctx) return;

        const data = window.analyticsData.timeSeries;
        if (!data || !data.labels || !data.revenue) {
            console.warn('Revenue chart data not available');
            return;
        }
        
        this.charts.revenue = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Revenue',
                    data: data.revenue,
                    borderColor: '#1e3a8a',
                    backgroundColor: 'rgba(30, 58, 138, 0.1)',
                    tension: 0.4,
                    fill: true,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return '$' + context.parsed.y.toFixed(2);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toFixed(0);
                            }
                        }
                    }
                }
            }
        });
    }

    initUserGrowthChart() {
        const ctx = document.getElementById('userGrowthChart');
        if (!ctx) return;

        const data = window.analyticsData.timeSeries;
        if (!data || !data.labels || !data.users) {
            console.warn('User growth chart data not available');
            return;
        }
        
        this.charts.userGrowth = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'New Users',
                    data: data.users,
                    backgroundColor: '#10b981',
                    borderRadius: 4,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }

    initProjectActivityChart() {
        const ctx = document.getElementById('projectActivityChart');
        if (!ctx) return;

        const data = window.analyticsData.timeSeries;
        if (!data || !data.labels || !data.projects) {
            console.warn('Project activity chart data not available');
            return;
        }
        
        this.charts.projectActivity = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'New Projects',
                    data: data.projects,
                    borderColor: '#d97706',
                    backgroundColor: 'rgba(217, 119, 6, 0.1)',
                    tension: 0.4,
                    fill: true,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }

    initProposalActivityChart() {
        const ctx = document.getElementById('proposalActivityChart');
        if (!ctx) return;

        const data = window.analyticsData.timeSeries;
        if (!data || !data.labels || !data.proposals) {
            console.warn('Proposal activity chart data not available');
            return;
        }
        
        this.charts.proposalActivity = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'New Proposals',
                    data: data.proposals || [],
                    borderColor: '#8b5cf6',
                    backgroundColor: 'rgba(139, 92, 246, 0.1)',
                    tension: 0.4,
                    fill: true,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }

    setupDateRangeReset() {
        const resetBtn = document.getElementById('resetDateRange');
        if (resetBtn) {
            resetBtn.addEventListener('click', () => {
                const endDate = new Date();
                const startDate = new Date();
                startDate.setDate(startDate.getDate() - 30);

                document.getElementById('start_date').value = startDate.toISOString().split('T')[0];
                document.getElementById('end_date').value = endDate.toISOString().split('T')[0];
                document.getElementById('dateRangeForm').submit();
            });
        }
    }

    setupExport() {
        const exportBtn = document.getElementById('exportReportBtn');
        if (exportBtn) {
            exportBtn.addEventListener('click', () => {
                // TODO: Implement export functionality
                alert('Export functionality will be implemented soon.');
            });
        }
    }
}

// Initialize analytics when DOM is ready (only on analytics page)
function initializeAnalytics() {
    // Only initialize if analytics data is available (i.e., we're on the analytics page)
    if (window.analyticsData) {
        new AdminAnalytics();
    }
}

// Handle both regular page loads and Turbo navigation
document.addEventListener('DOMContentLoaded', initializeAnalytics);
document.addEventListener('turbo:load', initializeAnalytics);


