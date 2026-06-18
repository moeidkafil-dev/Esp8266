// Admin settings page JavaScript
(function($) {
    'use strict';

    $(document).ready(function() {
        // Show welcome message on settings page
        const noticeContainer = $('#dom-admin-notice');
        if (noticeContainer.length) {
            noticeContainer.html(
                '<div class="notice notice-info is-dismissible">' +
                '<p>' + domAdminData.welcomeMessage + '</p>' +
                '</div>'
            );
        }

        // Add input validation for number fields
        $('input[name="dom_max_downloads"], input[name="dom_expiry_days"]').on('input', function() {
            const value = parseInt($(this).val());
            if (value < 1) {
                $(this).val(1);
            }
        });
    });

})(jQuery);
