/**
 * Teacher Evaluation Pro - Admin JavaScript
 */

(function($) {
    'use strict';
    
    // TEP Admin Module
    const TEPAdmin = {
        
        config: window.tepConfig || {},
        
        init: function() {
            this.bindEvents();
            this.loadReactApp();
        },
        
        bindEvents: function() {
            $(document).on('click', '.tep-action-button', this.handleActionClick.bind(this));
            $(document).on('submit', '.tep-form', this.handleFormSubmit.bind(this));
        },
        
        handleActionClick: function(e) {
            e.preventDefault();
            const $button = $(e.currentTarget);
            const action = $button.data('action');
            
            if (action) {
                this.executeAction(action, $button.data());
            }
        },
        
        handleFormSubmit: function(e) {
            e.preventDefault();
            const $form = $(e.currentTarget);
            const formData = new FormData($form[0]);
            
            this.submitForm(formData);
        },
        
        executeAction: function(action, data) {
            const self = this;
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'tep_' + action,
                    nonce: this.config.nonce,
                    ...data
                },
                beforeSend: function() {
                    self.showLoading();
                },
                success: function(response) {
                    self.handleResponse(response);
                },
                error: function(xhr, status, error) {
                    self.showError(error);
                },
                complete: function() {
                    self.hideLoading();
                }
            });
        },
        
        submitForm: function(formData) {
            const self = this;
            
            formData.append('nonce', this.config.nonce);
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: function() {
                    self.showLoading();
                },
                success: function(response) {
                    self.handleResponse(response);
                },
                error: function(xhr, status, error) {
                    self.showError(error);
                },
                complete: function() {
                    self.hideLoading();
                }
            });
        },
        
        handleResponse: function(response) {
            if (response.success) {
                this.showNotification('success', response.data.message || 'Operation completed successfully');
                
                if (response.data.redirect) {
                    window.location.href = response.data.redirect;
                }
            } else {
                this.showNotification('error', response.data.message || 'An error occurred');
            }
        },
        
        showNotification: function(type, message) {
            const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.tep-admin-view').prepend($notice);
            
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },
        
        showError: function(message) {
            this.showNotification('error', message || 'An unexpected error occurred');
        },
        
        showLoading: function() {
            $('body').addClass('tep-loading');
        },
        
        hideLoading: function() {
            $('body').removeClass('tep-loading');
        },
        
        loadReactApp: function() {
            // Check if React app container exists
            const container = document.getElementById('tep-react-app');
            if (container && window.TEPReactApp) {
                window.TEPReactApp.render(container);
            }
        },
        
        api: {
            get: function(endpoint) {
                return $.ajax({
                    url: tepConfig.restUrl + endpoint,
                    type: 'GET',
                    headers: {
                        'X-WP-Nonce': tepConfig.nonce
                    }
                });
            },
            
            post: function(endpoint, data) {
                return $.ajax({
                    url: tepConfig.restUrl + endpoint,
                    type: 'POST',
                    headers: {
                        'X-WP-Nonce': tepConfig.nonce
                    },
                    data: JSON.stringify(data),
                    contentType: 'application/json'
                });
            },
            
            put: function(endpoint, data) {
                return $.ajax({
                    url: tepConfig.restUrl + endpoint,
                    type: 'PUT',
                    headers: {
                        'X-WP-Nonce': tepConfig.nonce
                    },
                    data: JSON.stringify(data),
                    contentType: 'application/json'
                });
            },
            
            delete: function(endpoint) {
                return $.ajax({
                    url: tepConfig.restUrl + endpoint,
                    type: 'DELETE',
                    headers: {
                        'X-WP-Nonce': tepConfig.nonce
                    }
                });
            }
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        TEPAdmin.init();
    });
    
    // Expose to global scope
    window.TEPAdmin = TEPAdmin;
    
})(jQuery);
