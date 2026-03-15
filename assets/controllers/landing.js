import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['nav', 'mobileMenu'];

    connect() {
        console.log('Landing controller connected!');
        
        // Initialize scrollspy highlighting
        this.initScrollSpy();
        
        // Initialize segment tabs toggle
        this.initSegmentTabs();
        
        // Initialize smooth scrolling for anchor links
        this.initSmoothScroll();
    }

    disconnect() {
        // Clean up scroll event listeners
        if (this._scrollHandler) {
            window.removeEventListener('scroll', this._scrollHandler);
        }
        
        // Clean up segment tab listeners if set
        if (this._segmentTabHandlers && this._segmentTabHandlers.length) {
            this._segmentTabHandlers.forEach(({ el, handler }) => el.removeEventListener('click', handler));
            this._segmentTabHandlers = [];
        }
        
        // Clean up smooth scroll listeners if set
        if (this._smoothScrollHandlers && this._smoothScrollHandlers.length) {
            this._smoothScrollHandlers.forEach(({ el, handler }) => el.removeEventListener('click', handler));
            this._smoothScrollHandlers = [];
        }
    }

    // ---- Scrollspy for navbar active state ----
    initScrollSpy() {
        const navLinks = Array.from(document.querySelectorAll('.admin-nav .nav-item[href^="#"]'));
        console.log('Found nav links:', navLinks.length);
        if (!navLinks.length) return;

        const sections = Array.from(document.querySelectorAll('section[id], footer[id]'));
        console.log('Found sections:', sections.length);
        if (!sections.length) return;

        this._navLinks = navLinks;
        this._sections = sections;

        // Bind scroll handler
        this._scrollHandler = this.updateActiveNav.bind(this);
        window.addEventListener('scroll', this._scrollHandler, { passive: true });
        
        // Initial call
        this.updateActiveNav();
    }

    updateActiveNav() {
        if (!this._navLinks || !this._sections) return;
        
        let current = '';
        const scrollY = window.scrollY;
        const windowHeight = window.innerHeight;
        
        // Special handling for CTA section and footer (about section)
        const ctaSection = document.querySelector('.cta-section');
        const footer = document.getElementById('about');
        
        if (ctaSection) {
            const ctaTop = ctaSection.offsetTop;
            // If we're in the CTA section or near the footer, activate "about"
            if (scrollY >= ctaTop - 100) {
                current = 'about';
            }
        }
        
        if (footer) {
            const footerTop = footer.offsetTop;
            // If we're near the footer (within 200px), activate it
            if (scrollY + windowHeight >= footerTop - 200) {
                current = 'about';
            }
        }
        
        // If footer is not active, check other sections
        if (!current) {
            this._sections.forEach(section => {
                const sectionTop = section.offsetTop - 100;
                const sectionHeight = section.offsetHeight;
                
                if (scrollY >= sectionTop && scrollY < sectionTop + sectionHeight) {
                    current = section.getAttribute('id');
                }
            });
        }
        
        this._navLinks.forEach(link => {
            link.classList.remove('active');
            if (link.getAttribute('href') === '#' + current) {
                link.classList.add('active');
                console.log('Set active:', current);
            }
        });
    }

    // ---- Segment tabs toggle ----
    initSegmentTabs() {
        const tabs = Array.from(document.querySelectorAll('.segment-tab'));
        if (!tabs.length) return;

        this._segmentTabHandlers = [];

        tabs.forEach(tab => {
            const handler = () => {
                // Remove active class from all tabs
                tabs.forEach(t => {
                    t.classList.remove('active');
                    t.setAttribute('aria-selected', 'false');
                });
                
                // Add active class to clicked tab
                tab.classList.add('active');
                tab.setAttribute('aria-selected', 'true');
                
                // Get the tab data
                const selectedTab = tab.getAttribute('data-tab');
                
                // Hide all tab content
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.classList.remove('active');
                });
                
                // Show selected tab content
                const contentToShow = document.getElementById(selectedTab + '-content');
                if (contentToShow) {
                    contentToShow.classList.add('active');
                }
            };
            
            tab.addEventListener('click', handler);
            this._segmentTabHandlers.push({ el: tab, handler });
        });
    }

    // ---- Smooth scrolling for anchor links ----
    initSmoothScroll() {
        const anchorLinks = Array.from(document.querySelectorAll('a[href^="#"]'));
        if (!anchorLinks.length) return;

        this._smoothScrollHandlers = [];

        anchorLinks.forEach(link => {
            const handler = (e) => {
                const href = link.getAttribute('href');
                // Handle specific anchor links including Learn More button
                if (href === '#how-it-works' || href === '#about' || href === '#categories' || href === '#home' || href === '#marketplace-snapshot') {
                    e.preventDefault();
                    const targetId = href.substring(1);
                    const targetElement = document.getElementById(targetId);
                    
                    if (targetElement) {
                        const headerOffset = 80; // Adjust based on header height
                        const elementPosition = targetElement.getBoundingClientRect().top;
                        const offsetPosition = elementPosition + window.pageYOffset - headerOffset;
                        
                        window.scrollTo({
                            top: offsetPosition,
                            behavior: 'smooth'
                        });
                    }
                }
            };
            
            link.addEventListener('click', handler);
            this._smoothScrollHandlers.push({ el: link, handler });
        });
    }
}