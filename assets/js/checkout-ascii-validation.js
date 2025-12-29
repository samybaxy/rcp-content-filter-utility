/**
 * WooCommerce Checkout Email & Phone Field Validation
 *
 * Validates email and phone fields for proper format and ASCII-only characters.
 * Address/name/city/state fields are skipped to allow Loqate transliteration.
 *
 * Validation:
 * - Email: Valid format (user@domain.com) + ASCII-only
 * - Phone: Valid format (7+ digits) + ASCII-only
 *
 * Visual Feedback:
 * - Red border on invalid fields on blur
 * - Real-time validation during input
 * - Prevents pasting invalid content
 *
 * @since 1.0.0
 * @updated 1.0.55 - Production-ready, optimized code without debug logs
 */
(function($) {
    'use strict';

    // Email and phone fields that require validation
    // Address/name/city/state fields are excluded to allow Loqate transliteration
    const EMAIL_FIELDS = ['billing_email', 'shipping_email'];
    const PHONE_FIELDS = ['billing_phone', 'shipping_phone'];
    const FIELDS_TO_VALIDATE = [...EMAIL_FIELDS, ...PHONE_FIELDS];

    /**
     * Check if field is an email field
     */
    function isEmailField(fieldId) {
        return EMAIL_FIELDS.includes(fieldId);
    }

    /**
     * Check if field is a phone field
     */
    function isPhoneField(fieldId) {
        return PHONE_FIELDS.includes(fieldId);
    }

    /**
     * Check if field should be validated
     * Only validate email and phone - skip address fields for transliteration
     */
    function shouldValidateField(fieldId) {
        return FIELDS_TO_VALIDATE.includes(fieldId);
    }

    /**
     * Validate email format
     */
    function isValidEmail(email) {
        // Basic email regex - matches most common email formats
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    /**
     * Validate phone format
     * Allows: digits, spaces, hyphens, parentheses, plus sign
     */
    function isValidPhone(phone) {
        // Remove spaces, hyphens, parentheses for validation
        const cleaned = phone.replace(/[\s\-()]/g, '');
        // Must have at least 7 digits, can start with +
        const phoneRegex = /^\+?[0-9]{7,}$/;
        return phoneRegex.test(cleaned);
    }

    /**
     * Check if value contains non-ASCII characters (kanji, emoji, etc.)
     * ASCII range is 0x00-0x7F
     */
    function hasNonAsciiChars(value) {
        return /[^\x00-\x7F]/.test(value);
    }

    /**
     * Check if value contains disallowed characters for address fields
     * Allows: a-z, A-Z, 0-9, space, hyphen, period, comma, apostrophe, #, /, (, ), &, +, _, %
     */
    function hasDisallowedAddressChars(value) {
        const pattern = /^[A-Za-z0-9\s\-.,'\/#()&+_%]*$/;
        return !pattern.test(value);
    }

    /**
     * Validate field value
     * Returns true if valid, false if invalid
     */
    function validateValue(value, fieldId) {
        if (!value) {
            return true; // Empty values are valid (required validation is WooCommerce's job)
        }

        const emailField = isEmailField(fieldId);
        const phoneField = isPhoneField(fieldId);
        const hasNonAscii = hasNonAsciiChars(value);

        // All fields: reject non-ASCII (kanji, emoji, etc.)
        if (hasNonAscii) {
            return false;
        }

        // Email fields: validate email format
        if (emailField) {
            return isValidEmail(value);
        }

        // Phone fields: validate phone format
        if (phoneField) {
            return isValidPhone(value);
        }

        // Other fields: check for disallowed characters (shouldn't reach here for email/phone)
        return !hasDisallowedAddressChars(value);
    }

    /**
     * Show validation error for a field
     */
    function showFieldError($field, message) {
        removeFieldError($field);

        const $formRow = $field.closest('.form-row');
        $formRow.addClass('woocommerce-invalid');
        $field.addClass('rcf-validation-error');

        const $errorMsg = $('<span>', {
            'class': 'rcf-validation-error-message',
            'text': message
        });

        $field.attr('style', function(i, style) {
            return (style || '') + 'border-color: #e2401c !important; border-width: 2px !important;';
        });

        const $inputWrapper = $field.closest('.woocommerce-input-wrapper');
        if ($inputWrapper.length) {
            $inputWrapper.after($errorMsg);
        } else {
            $field.after($errorMsg);
        }

        $errorMsg.css({
            'color': '#e2401c',
            'font-size': '0.875em',
            'display': 'block',
            'margin-top': '0.5em',
            'font-weight': '600'
        });
    }

    /**
     * Remove validation error from a field
     */
    function removeFieldError($field) {
        $field.removeClass('rcf-validation-error');

        const $formRow = $field.closest('.form-row');
        $formRow.find('.rcf-validation-error-message').remove();

        if ($formRow.find('.woocommerce-invalid').length === 0) {
            $formRow.removeClass('woocommerce-invalid');
        }

        $field.css({
            'border-color': '',
            'border-width': ''
        });
    }

    /**
     * Get appropriate error message for field
     */
    function getErrorMessage(fieldId, value) {
        if (hasNonAsciiChars(value)) {
            return 'Use Roman/English characters only (A–Z, 0–9). Alternative characters not supported.';
        }

        if (isEmailField(fieldId)) {
            return 'Please enter a valid email address.';
        }

        if (isPhoneField(fieldId)) {
            return 'Please enter a valid phone number.';
        }

        return rcfCheckoutValidation.errorMessage;
    }

    /**
     * Validate a single field
     */
    function validateField($field) {
        const value = $field.val();
        const fieldId = $field.attr('id');

        const isValid = validateValue(value, fieldId);

        if (!isValid) {
            const errorMessage = getErrorMessage(fieldId, value);
            showFieldError($field, errorMessage);
            return false;
        } else {
            removeFieldError($field);
            return true;
        }
    }

    /**
     * Validate all checkout fields
     * Only validates email and phone - skips address fields for transliteration
     */
    function validateAllFields() {
        let allValid = true;

        rcfCheckoutValidation.fieldsToValidate.forEach(function(fieldName) {
            const $field = $('#' + fieldName);

            // Only validate email and phone fields
            if ($field.length && $field.val() && shouldValidateField(fieldName)) {
                if (!validateField($field)) {
                    allValid = false;
                }
            }
        });

        return allValid;
    }

    /**
     * Initialize validation on checkout page
     */
    function initValidation() {
        if (typeof rcfCheckoutValidation === 'undefined') {
            return;
        }

        rcfCheckoutValidation.fieldsToValidate.forEach(function(fieldName) {
            const $field = $('#' + fieldName);
            const shouldValidate = shouldValidateField(fieldName);

            // Only validate email and phone fields - skip address fields for transliteration
            if ($field.length && shouldValidate) {
                // Validate on blur
                $field.on('blur.rcf-validation', function() {
                    validateField($(this));
                });

                // Real-time validation on input
                $field.on('input.rcf-validation', function() {
                    const $this = $(this);
                    if ($this.val()) {
                        validateField($this);
                    } else {
                        removeFieldError($this);
                    }
                });

                // Prevent paste of non-ASCII content
                $field.on('paste.rcf-validation', function(e) {
                    const $this = $(this);
                    const fieldId = $this.attr('id');

                    let pastedText = '';
                    if (window.clipboardData && window.clipboardData.getData) {
                        pastedText = window.clipboardData.getData('Text');
                    } else if (e.originalEvent.clipboardData && e.originalEvent.clipboardData.getData) {
                        pastedText = e.originalEvent.clipboardData.getData('text/plain');
                    }

                    // Check if pasted content is invalid
                    if (pastedText && !validateValue(pastedText, fieldId)) {
                        e.preventDefault();
                        $this.val('');

                        showFieldError($this, rcfCheckoutValidation.errorMessage);
                        setTimeout(function() {
                            removeFieldError($this);
                        }, 3000);
                    }
                });
            }
        });

        // Validate before checkout submission
        $(document.body).on('checkout_error', function() {
            validateAllFields();
        });

        $('form.checkout').on('checkout_place_order', function() {
            return validateAllFields();
        });
    }

    // Initialize when DOM is ready
    $(document).ready(function() {
        if (typeof wc_checkout_params !== 'undefined') {
            initValidation();
        } else {
            setTimeout(initValidation, 1000);
        }
    });

    // Re-initialize after AJAX updates
    $(document.body).on('updated_checkout', function() {
        rcfCheckoutValidation.fieldsToValidate.forEach(function(fieldName) {
            $('#' + fieldName).off('.rcf-validation');
        });
        initValidation();
    });

})(jQuery);
