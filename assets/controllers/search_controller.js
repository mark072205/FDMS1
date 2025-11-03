// Admin Search Functionality
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
        if (path.includes('/admin/users')) return 'users';
        if (path.includes('/admin/category')) return 'categories';
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
            let endpoint;
            if (this.currentPage === 'users') {
                endpoint = '/admin/search/users';
            } else if (this.currentPage === 'categories') {
                endpoint = '/admin/search/categories';
            } else {
                endpoint = '/admin/search/global';
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
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing AdminSearch...');
    
    // Test if search elements exist
    const searchBox = document.querySelector('.search-box');
    const searchInput = document.querySelector('#adminSearchInput');
    console.log('Search box found:', searchBox);
    console.log('Search input found:', searchInput);
    
    try {
        new AdminSearch();
        console.log('AdminSearch initialized successfully');
    } catch (error) {
        console.error('Error initializing AdminSearch:', error);
    }
});
