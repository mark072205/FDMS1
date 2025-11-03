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
                        <div class="modal-header">
                            <h3 class="modal-title">Confirm Action</h3>
                            <button type="button" class="modal-close" onclick="adminManager.closeConfirmationModal()">&times;</button>
                        </div>
                        <div class="modal-body">
                            <p id="modalMessage">Are you sure you want to perform this action?</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="adminManager.closeConfirmationModal()">Cancel</button>
                            <button type="button" class="btn btn-primary" id="modalConfirmBtn">Confirm</button>
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

    showConfirmationModal(message, confirmCallback, buttonType = 'primary') {
        const modal = document.getElementById('confirmationModal');
        const modalMessage = document.getElementById('modalMessage');
        const confirmBtn = document.getElementById('modalConfirmBtn');
        
        if (!modal || !modalMessage || !confirmBtn) {
            console.error('Confirmation modal elements not found');
            return;
        }

        modalMessage.textContent = message;
        confirmBtn.className = `btn btn-${buttonType}`;
        
        // Remove any existing event listeners
        const newConfirmBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        
        // Add new event listener
        newConfirmBtn.addEventListener('click', () => {
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
                buttonType
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
                buttonType
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
window.showConfirmationModal = function(message, confirmCallback, buttonType) {
    if (window.adminManager) {
        window.adminManager.showConfirmationModal(message, confirmCallback, buttonType);
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

