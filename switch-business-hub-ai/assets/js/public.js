/**
 * Switch Business Hub AI - Public JavaScript
 * Tabbed Interface functionality
 */

(function($) {
    'use strict';

    window.SBHAPublic = {

        init: function() {
            this.bindEvents();
            this.initTabs();
        },

        bindEvents: function() {
            // Tab navigation
            $(document).on('click', '.sbha-tab-btn', this.switchTab);

            // Category filter
            $(document).on('click', '.sbha-filter-btn', this.filterServices);

            // Service actions
            $(document).on('click', '.sbha-view-details', this.viewServiceDetails);
            $(document).on('click', '.sbha-get-quote', this.getQuote);

            // Modal
            $(document).on('click', '.sbha-modal-overlay, .sbha-modal-close', this.closeModal);

            // Forms
            $(document).on('submit', '#sbha-quote-form', this.submitQuoteForm);
            $(document).on('submit', '#sbha-track-form', this.submitTrackForm);
            $(document).on('submit', '#sbha-contact-form', this.submitContactForm);
        },

        // Initialize tabs
        initTabs: function() {
            // Check URL hash for initial tab
            var hash = window.location.hash.replace('#', '');
            if (hash && $('#sbha-tab-' + hash).length) {
                this.activateTab(hash);
            }
        },

        // Switch tab
        switchTab: function(e) {
            e.preventDefault();
            var tab = $(this).data('tab');
            SBHAPublic.activateTab(tab);
        },

        // Activate specific tab
        activateTab: function(tab) {
            // Update buttons
            $('.sbha-tab-btn').removeClass('active');
            $('.sbha-tab-btn[data-tab="' + tab + '"]').addClass('active');

            // Update panels
            $('.sbha-tab-panel').removeClass('active');
            $('#sbha-tab-' + tab).addClass('active');

            // Update URL hash
            history.replaceState(null, null, '#' + tab);
        },

        // Filter services by category
        filterServices: function(e) {
            e.preventDefault();
            var category = $(this).data('category');

            // Update active button
            $('.sbha-filter-btn').removeClass('active');
            $(this).addClass('active');

            // Filter cards
            var $cards = $('.sbha-service-card');

            if (category === 'all') {
                $cards.removeClass('hidden').fadeIn(300);
            } else {
                $cards.each(function() {
                    var $card = $(this);
                    if ($card.data('category') === category) {
                        $card.removeClass('hidden').fadeIn(300);
                    } else {
                        $card.addClass('hidden').fadeOut(300);
                    }
                });
            }
        },

        // View service details
        viewServiceDetails: function(e) {
            e.preventDefault();
            e.stopPropagation();

            var serviceId = $(this).data('id');
            var $card = $(this).closest('.sbha-service-card');
            var serviceName = $card.find('.sbha-service-title').text();
            var serviceDesc = $card.find('.sbha-service-desc').text();
            var servicePrice = $card.find('.sbha-price-amount').text();

            var html = '<div class="sbha-service-detail">' +
                '<h2>' + serviceName + '</h2>' +
                '<div class="sbha-detail-price">' + servicePrice + '</div>' +
                '<p>' + serviceDesc + '</p>' +
                '<div class="sbha-detail-actions">' +
                    '<button class="sbha-btn sbha-btn-primary sbha-get-quote" data-id="' + serviceId + '" data-name="' + serviceName + '">Request Quote</button>' +
                '</div>' +
            '</div>';

            SBHAPublic.showModal(html);
        },

        // Get quote for service
        getQuote: function(e) {
            e.preventDefault();
            e.stopPropagation();

            var serviceId = $(this).data('id');
            var serviceName = $(this).data('name');

            // Close modal if open
            SBHAPublic.closeModal();

            // Switch to quote tab
            SBHAPublic.activateTab('quote');

            // Pre-select service
            $('#sbha-service').val(serviceId);

            // Scroll to form
            $('html, body').animate({
                scrollTop: $('.sbha-quote-container').offset().top - 50
            }, 500);
        },

        // Submit quote form
        submitQuoteForm: function(e) {
            e.preventDefault();

            var $form = $(this);
            var $btn = $form.find('.sbha-btn-primary');
            var $message = $('#sbha-quote-message');

            // Validate
            if (!$form[0].checkValidity()) {
                $form[0].reportValidity();
                return;
            }

            $btn.find('.sbha-btn-text').hide();
            $btn.find('.sbha-btn-loading').show();
            $btn.prop('disabled', true);

            var formData = new FormData($form[0]);
            formData.append('action', 'sbha_submit_quote');

            $.ajax({
                url: typeof sbha_public !== 'undefined' ? sbha_public.ajax_url : '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        $message.removeClass('error').addClass('success').text('Thank you! Your quote request has been submitted. We will contact you within 24 hours.').show();
                        $form[0].reset();
                    } else {
                        $message.removeClass('success').addClass('error').text(response.data || 'An error occurred. Please try again.').show();
                    }
                },
                error: function() {
                    $message.removeClass('success').addClass('error').text('An error occurred. Please try again.').show();
                },
                complete: function() {
                    $btn.find('.sbha-btn-text').show();
                    $btn.find('.sbha-btn-loading').hide();
                    $btn.prop('disabled', false);
                }
            });
        },

        // Submit track form
        submitTrackForm: function(e) {
            e.preventDefault();

            var $form = $(this);
            var jobNumber = $('#sbha-job-number').val().trim();
            var $result = $('#sbha-track-result');

            if (!jobNumber) {
                $result.html('<div class="sbha-message error">Please enter a job number.</div>');
                return;
            }

            $result.html('<div class="sbha-loading">Searching...</div>');

            $.ajax({
                url: typeof sbha_public !== 'undefined' ? sbha_public.ajax_url : '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {
                    action: 'sbha_track_job',
                    job_number: jobNumber,
                    nonce: $form.find('[name="sbha_track_nonce"]').val()
                },
                success: function(response) {
                    if (response.success) {
                        var job = response.data;
                        var html = '<div class="sbha-job-status">' +
                            '<div class="sbha-job-header">' +
                                '<span class="sbha-job-number">' + job.job_number + '</span>' +
                                '<span class="sbha-status-badge sbha-status-' + job.status + '">' + job.status_label + '</span>' +
                            '</div>' +
                            '<div class="sbha-job-info">' +
                                '<p><strong>Service:</strong> ' + job.service_name + '</p>' +
                                '<p><strong>Created:</strong> ' + job.created_date + '</p>' +
                                (job.estimated_completion ? '<p><strong>Est. Completion:</strong> ' + job.estimated_completion + '</p>' : '') +
                            '</div>' +
                        '</div>';
                        $result.html(html);
                    } else {
                        $result.html('<div class="sbha-message error">Job not found. Please check the job number and try again.</div>');
                    }
                },
                error: function() {
                    $result.html('<div class="sbha-message error">An error occurred. Please try again.</div>');
                }
            });
        },

        // Submit contact form
        submitContactForm: function(e) {
            e.preventDefault();

            var $form = $(this);
            var $btn = $form.find('.sbha-btn-primary');
            var $message = $('#sbha-contact-message-result');

            if (!$form[0].checkValidity()) {
                $form[0].reportValidity();
                return;
            }

            $btn.prop('disabled', true).text('Sending...');

            $.ajax({
                url: typeof sbha_public !== 'undefined' ? sbha_public.ajax_url : '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: $form.serialize() + '&action=sbha_contact',
                success: function(response) {
                    if (response.success) {
                        $message.removeClass('error').addClass('success').text('Thank you! Your message has been sent.').show();
                        $form[0].reset();
                    } else {
                        $message.removeClass('success').addClass('error').text(response.data || 'An error occurred.').show();
                    }
                },
                error: function() {
                    $message.removeClass('success').addClass('error').text('An error occurred. Please try again.').show();
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Send Message');
                }
            });
        },

        // Show modal
        showModal: function(content) {
            var $modal = $('#sbha-modal');
            $modal.find('#sbha-modal-body').html(content);
            $modal.addClass('active');
            $('body').css('overflow', 'hidden');
        },

        // Close modal
        closeModal: function() {
            $('#sbha-modal').removeClass('active');
            $('body').css('overflow', '');
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        SBHAPublic.init();
    });

})(jQuery);
