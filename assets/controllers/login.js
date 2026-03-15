document.addEventListener('DOMContentLoaded', function () {
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');

    // Password toggle functionality
    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', () => {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Toggle icon between open and closed eye
            if (type === 'text') {
                // Show open eye icon (password is visible)
                togglePassword.innerHTML = `
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                `;
            } else {
                // Show closed/crossed eye icon (password is hidden)
                togglePassword.innerHTML = `
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                        <line x1="1" y1="1" x2="23" y2="23"></line>
                    </svg>
                `;
            }
        });
    }

    // Force footer to maintain fixed width
    const footer = document.querySelector('.footer-login');
    if (footer) {
        // Set initial width
        footer.style.width = '1263px';
        footer.style.maxWidth = '1263px';
        footer.style.minWidth = '1263px';
        
        // Monitor for changes and force width back
        const observer = new MutationObserver(function(mutations) {
            const computedWidth = window.getComputedStyle(footer).width;
            if (computedWidth !== '1263px') {
                footer.style.width = '1263px';
                footer.style.maxWidth = '1263px';
                footer.style.minWidth = '1263px';
            }
        });
        
        observer.observe(footer, {
            attributes: true,
            attributeFilter: ['style', 'class'],
            childList: false,
            subtree: false
        });
        
        // Also check on resize and after DOM mutations
        window.addEventListener('resize', function() {
            footer.style.width = '1263px';
            footer.style.maxWidth = '1263px';
            footer.style.minWidth = '1263px';
        });
        
        // Check periodically as a fallback
        setInterval(function() {
            const computedWidth = window.getComputedStyle(footer).width;
            if (computedWidth !== '1263px') {
                footer.style.width = '1263px';
                footer.style.maxWidth = '1263px';
                footer.style.minWidth = '1263px';
            }
        }, 100);
    }
});

