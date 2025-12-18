/**
 * WooCommerce Ship to Different Address Control
 *
 * Simply unchecks the "Ship to a different address?" checkbox on page load.
 * After that, WooCommerce and the user control it normally.
 *
 * @since 1.0.37
 */
(function($) {
    'use strict';

    /**
     * Uncheck the shipping checkbox and hide the address fields
     */
    function uncheckShippingCheckbox() {
        var $checkbox = $('#ship-to-different-address-checkbox');

        if ($checkbox.length) {
            $checkbox.prop('checked', false);
            $('.woocommerce-shipping-fields .shipping_address').hide();
        }
    }

    // Run immediately when script loads
    uncheckShippingCheckbox();

    // Run again when DOM is ready
    $(document).ready(function() {
        uncheckShippingCheckbox();
    });

    // Run once more after a short delay to catch any late rendering
    $(window).on('load', function() {
        setTimeout(uncheckShippingCheckbox, 100);
    });

})(jQuery);
