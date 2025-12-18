/**
 * WooCommerce Checkout ASCII-Only Field Validation
 *
 * Blocks non-ASCII characters (kanji, hiragana, katakana, emoji) from address/name fields.
 * Email fields allow all ASCII including @ symbol.
 *
 * @since 1.0.0
 */
(function($) {
    'use strict';

    // Email fields that allow @ symbol
    const EMAIL_FIELDS = ['billing_email', 'shipping_email'];

    /**
     * Check if field is an email field
     */
    function isEmailField(fieldId) {
        return EMAIL_FIELDS.includes(fieldId);
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
            return true; // Empty values are valid
        }

        const emailField = isEmailField(fieldId);
        const hasNonAscii = hasNonAsciiChars(value);

        // All fields: reject non-ASCII (kanji, emoji, etc.)
        if (hasNonAscii) {
            return false;
        }

        // Email fields: only need non-ASCII check, we're done
        if (emailField) {
            return true;
        }

        // Address fields: also check for disallowed characters (like @)
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
     * Validate a single field
     */
    function validateField($field) {
        const value = $field.val();
        const fieldId = $field.attr('id');

        if (!validateValue(value, fieldId)) {
            showFieldError($field, rcfCheckoutValidation.errorMessage);
            return false;
        } else {
            removeFieldError($field);
            return true;
        }
    }

    /**
     * Validate all checkout fields
     */
    function validateAllFields() {
        let allValid = true;

        rcfCheckoutValidation.fieldsToValidate.forEach(function(fieldName) {
            const $field = $('#' + fieldName);

            if ($field.length && $field.val()) {
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
        rcfCheckoutValidation.fieldsToValidate.forEach(function(fieldName) {
            const $field = $('#' + fieldName);

            if ($field.length) {
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
