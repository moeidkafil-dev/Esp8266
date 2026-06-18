// Frontend JavaScript
(function($) {
    'use strict';

    $(document).ready(function() {
        // Add confirmation dialog for order submission
        $('.dom-create-order-form form').on('submit', function(e) {
            const email = $('#dom_customer_email').val();
            const productName = $('#dom_product_name').val();

            if (!email || !productName) {
                e.preventDefault();
                alert(domFrontendData.requiredFieldsMessage);
            }
        });

        // Add smooth scroll to order status
        $('.dom-order-status').hide().fadeIn(500);
    });

})(jQuery);
