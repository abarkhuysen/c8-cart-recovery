/**
 * Guest Email Capture
 *
 * Captures guest email address via AJAX when entered on checkout page
 *
 * @package WC_Cart_Recovery
 */

(function($) {
    'use strict';

    var WCCR_Guest_Email_Capture = {
        /**
         * Debounce timer
         */
        debounceTimer: null,

        /**
         * Last captured email (to avoid duplicate requests)
         */
        lastCapturedEmail: '',

        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            var self = this;

            // Capture email on blur and change
            $(document).on('blur change', '#billing_email', function() {
                self.captureEmail();
            });

            // Also capture on keyup with debounce (for real-time capture)
            $(document).on('keyup', '#billing_email', function() {
                self.debouncedCapture();
            });

            // Capture first name and phone changes
            $(document).on('blur change', '#billing_first_name, #billing_phone', function() {
                self.captureEmail();
            });
        },

        /**
         * Debounced capture (for keyup events)
         */
        debouncedCapture: function() {
            var self = this;

            clearTimeout(this.debounceTimer);

            this.debounceTimer = setTimeout(function() {
                self.captureEmail();
            }, 1500); // 1.5 second delay
        },

        /**
         * Validate email format
         *
         * @param {string} email Email address to validate
         * @return {boolean}
         */
        isValidEmail: function(email) {
            var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return regex.test(email);
        },

        /**
         * Capture email via AJAX
         */
        captureEmail: function() {
            var self = this;
            var email = $('#billing_email').val();
            var firstName = $('#billing_first_name').val();
            var phone = $('#billing_phone').val();

            // Validate email
            if (!email || !this.isValidEmail(email)) {
                return;
            }

            // Avoid duplicate requests
            if (email === this.lastCapturedEmail) {
                return;
            }

            // Check if wccr_params is available
            if (typeof wccr_params === 'undefined') {
                return;
            }

            // Send AJAX request
            $.ajax({
                url: wccr_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'wccr_capture_email',
                    nonce: wccr_params.nonce,
                    email: email,
                    first_name: firstName,
                    phone: phone
                },
                success: function(response) {
                    if (response.success) {
                        self.lastCapturedEmail = email;
                    }
                },
                error: function() {
                    // Silently fail - we don't want to disrupt checkout
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        WCCR_Guest_Email_Capture.init();
    });

})(jQuery);
