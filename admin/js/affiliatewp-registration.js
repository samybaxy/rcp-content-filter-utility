/**
 * AffiliateWP Registration Form Enhancement
 *
 * Handles autopopulation and field visibility for AffiliateWP registration forms
 *
 * Rules:
 * - Name, Username, Account Email: Autopopulate and hide when filled
 * - Payment Email: Always visible and active
 * - Website URL: Hidden
 * - How will you promote us: Hidden
 */

(function($) {
    'use strict';

    /**
     * Initialize the form enhancements
     */
    function initAffiliateWPFormEnhancements() {
        // Find the AffiliateWP registration form
        const form = document.querySelector('form[id^="affwp-register-form-"]');

        if (!form) {
            // Form not found - fail silently (script only loads on registration page)
            // Only log if debug mode is explicitly enabled
            if (window.rcfAffiliateWP && window.rcfAffiliateWP.debug) {
                console.log('RCP: AffiliateWP registration form not found');
            }
            return;
        }

        if (window.rcfAffiliateWP && window.rcfAffiliateWP.debug) {
            console.log('RCP: AffiliateWP registration form found, applying enhancements');
        }

        // Get form fields
        const fields = {
            userName: form.querySelector('#affwp-user-name'),
            userLogin: form.querySelector('#affwp-user-login'),
            userEmail: form.querySelector('#affwp-user-email'),
            paymentEmail: form.querySelector('#affwp-payment-email'),
            websiteUrl: form.querySelector('#affwp-user-url'),
            promotionMethod: form.querySelector('#affwp-promotion-method')
        };

        // Autopopulate fields if user is logged in
        autopopulateFields(fields);

        // Apply visibility rules
        applyVisibilityRules(fields);

        // Add custom styles for hidden fields
        addCustomStyles();

        if (window.rcfAffiliateWP && window.rcfAffiliateWP.debug) {
            console.log('RCP: AffiliateWP form enhancements applied successfully');
        }
    }

    /**
     * Autopopulate fields from WordPress user data
     */
    function autopopulateFields(fields) {
        // Check if fields are already populated (user is logged in)
        const isLoggedIn = fields.userLogin && fields.userLogin.value && fields.userLogin.value.trim() !== '';
        const debug = window.rcfAffiliateWP && window.rcfAffiliateWP.debug;

        if (!isLoggedIn) {
            if (debug) console.log('RCP: User not logged in, skipping autopopulation');
            return;
        }

        if (debug) {
            console.log('RCP: User logged in, fields already autopopulated');

            // Name field - already populated by AffiliateWP
            if (fields.userName && !fields.userName.value) {
                console.log('RCP: Name field empty, could autopopulate if needed');
            }

            console.log('RCP: Username and Email fields already populated');
        }
    }

    /**
     * Apply visibility rules to form fields
     */
    function applyVisibilityRules(fields) {
        const debug = window.rcfAffiliateWP && window.rcfAffiliateWP.debug;

        // Rule 1: Hide Name, Username, Account Email if they are autofilled
        hideFieldIfFilled(fields.userName, 'Your Name');
        hideFieldIfFilled(fields.userLogin, 'Username');
        hideFieldIfFilled(fields.userEmail, 'Account Email');

        // Rule 2: Payment Email stays visible and active (no action needed)
        if (fields.paymentEmail) {
            if (debug) console.log('RCP: Payment Email field remains visible and active');
            // Ensure it's visible
            const paymentWrapper = fields.paymentEmail.closest('p');
            if (paymentWrapper) {
                paymentWrapper.classList.remove('rcf-hidden-field');
                paymentWrapper.style.display = '';
            }
        }

        // Rule 3: Hide Website URL
        hideField(fields.websiteUrl, 'Website URL');

        // Rule 4: Hide "How will you promote us?"
        hideField(fields.promotionMethod, 'Promotion Method');
    }

    /**
     * Hide a field if it has a value (autofilled)
     */
    function hideFieldIfFilled(field, fieldName) {
        const debug = window.rcfAffiliateWP && window.rcfAffiliateWP.debug;

        if (!field) {
            if (debug) console.log(`RCP: ${fieldName} field not found`);
            return;
        }

        const value = field.value ? field.value.trim() : '';

        if (value !== '') {
            if (debug) console.log(`RCP: ${fieldName} is filled with value, hiding field`);
            const wrapper = field.closest('p');
            if (wrapper) {
                wrapper.classList.add('rcf-hidden-field');
                wrapper.setAttribute('data-rcf-field', fieldName.toLowerCase().replace(/\s+/g, '-'));
            }
        } else {
            if (debug) console.log(`RCP: ${fieldName} is empty, keeping visible`);
        }
    }

    /**
     * Hide a field unconditionally
     */
    function hideField(field, fieldName) {
        const debug = window.rcfAffiliateWP && window.rcfAffiliateWP.debug;

        if (!field) {
            if (debug) console.log(`RCP: ${fieldName} field not found`);
            return;
        }

        if (debug) console.log(`RCP: Hiding ${fieldName} field unconditionally`);
        const wrapper = field.closest('p');
        if (wrapper) {
            wrapper.classList.add('rcf-hidden-field');
            wrapper.setAttribute('data-rcf-field', fieldName.toLowerCase().replace(/\s+/g, '-'));
        }
    }

    /**
     * Add custom CSS styles for hidden fields
     */
    function addCustomStyles() {
        // Check if styles already exist
        if (document.getElementById('rcf-affiliatewp-styles')) {
            return;
        }

        const style = document.createElement('style');
        style.id = 'rcf-affiliatewp-styles';
        style.textContent = `
            /* RCP Content Filter - AffiliateWP Form Enhancements */
            .rcf-hidden-field {
                display: none !important;
                visibility: hidden !important;
                height: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: hidden !important;
            }

            /* Ensure payment email field is always visible */
            .affwp-register-form #affwp-payment-email {
                display: block !important;
                visibility: visible !important;
            }

            /* Smooth transition for field visibility */
            .affwp-register-form p {
                transition: opacity 0.3s ease, height 0.3s ease;
            }

            /* Optional: Add visual indicator for required payment email */
            .affwp-register-form #affwp-payment-email:focus {
                border-color: #0073aa;
                box-shadow: 0 0 5px rgba(0, 115, 170, 0.5);
            }
        `;

        document.head.appendChild(style);

        if (window.rcfAffiliateWP && window.rcfAffiliateWP.debug) {
            console.log('RCP: Custom styles added for hidden fields');
        }
    }

    /**
     * Debug function - shows current state of all fields
     */
    function debugFormState() {
        const form = document.querySelector('form[id^="affwp-register-form-"]');
        if (!form) return;

        console.group('RCP: AffiliateWP Form State');

        const fields = form.querySelectorAll('input, textarea, select');
        fields.forEach(field => {
            const label = field.id || field.name || 'unknown';
            const value = field.value || '';
            const visible = field.offsetParent !== null;
            const required = field.hasAttribute('required');

            console.log(`${label}:`, {
                value: value.substring(0, 30) + (value.length > 30 ? '...' : ''),
                visible: visible,
                required: required,
                readonly: field.hasAttribute('readonly')
            });
        });

        console.groupEnd();
    }

    /**
     * Initialize when DOM is ready
     */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            initAffiliateWPFormEnhancements();

            // Debug in development mode
            if (window.location.search.includes('debug=affwp')) {
                debugFormState();
            }
        });
    } else {
        // DOM already loaded
        initAffiliateWPFormEnhancements();

        // Debug in development mode
        if (window.location.search.includes('debug=affwp')) {
            debugFormState();
        }
    }

    // Also try to initialize after a short delay (for AJAX-loaded forms)
    setTimeout(function() {
        if (!document.querySelector('.rcf-hidden-field')) {
            if (window.rcfAffiliateWP && window.rcfAffiliateWP.debug) {
                console.log('RCP: Re-initializing form enhancements (delayed)');
            }
            initAffiliateWPFormEnhancements();
        }
    }, 1000);

})(jQuery);
