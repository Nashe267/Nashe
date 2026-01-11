/**
 * Switch Business Hub AI - Public JavaScript
 * Frontend functionality for customer interface
 */

(function($) {
    'use strict';

    // Main Public Handler
    window.SBHAPublic = {

        init: function() {
            this.bindEvents();
            this.initServiceCatalog();
            this.initQuoteForm();
            this.initRecommendations();
            this.trackPageView();
        },

        bindEvents: function() {
            // Service category filtering
            $(document).on('click', '.sbha-category-filter', this.filterServices);

            // Quote form submission
            $(document).on('submit', '.sbha-quote-form', this.submitQuoteRequest);

            // Service selection
            $(document).on('click', '.sbha-service-card', this.selectService);

            // File upload handling
            $(document).on('change', '.sbha-file-upload', this.handleFileUpload);

            // Recommendation clicks
            $(document).on('click', '.sbha-recommendation-item', this.handleRecommendationClick);
        },

        // Initialize service catalog
        initServiceCatalog: function() {
            var $catalog = $('.sbha-service-catalog');
            if (!$catalog.length) return;

            // Load services via AJAX
            this.loadServices($catalog.data('category') || 'all');
        },

        // Load services from API
        loadServices: function(category) {
            var self = this;

            $.ajax({
                url: sbha_public.ajax_url,
                type: 'POST',
                data: {
                    action: 'sbha_get_services',
                    category: category,
                    nonce: sbha_public.nonce
                },
                beforeSend: function() {
                    $('.sbha-service-catalog').addClass('loading');
                },
                success: function(response) {
                    if (response.success) {
                        self.renderServices(response.data.services);
                    }
                },
                complete: function() {
                    $('.sbha-service-catalog').removeClass('loading');
                }
            });
        },

        // Render services in catalog
        renderServices: function(services) {
            var $container = $('.sbha-services-grid');
            $container.empty();

            services.forEach(function(service) {
                var html = '<div class="sbha-service-card" data-service-id="' + service.id + '">' +
                    '<div class="sbha-service-icon"><i class="' + service.icon + '"></i></div>' +
                    '<h3 class="sbha-service-title">' + service.name + '</h3>' +
                    '<p class="sbha-service-desc">' + service.short_description + '</p>' +
                    '<div class="sbha-service-price">' +
                        (service.price_from ? 'From ' + service.price_from : 'Get Quote') +
                    '</div>' +
                    '<button class="sbha-btn sbha-btn-primary">Learn More</button>' +
                '</div>';

                $container.append(html);
            });
        },

        // Filter services by category
        filterServices: function(e) {
            e.preventDefault();
            var category = $(this).data('category');

            $('.sbha-category-filter').removeClass('active');
            $(this).addClass('active');

            SBHAPublic.loadServices(category);
        },

        // Select a service
        selectService: function(e) {
            var $card = $(this);
            var serviceId = $card.data('service-id');

            // Track selection for AI
            SBHAPublic.trackInteraction('service_view', {
                service_id: serviceId
            });

            // Open service modal or redirect
            SBHAPublic.openServiceModal(serviceId);
        },

        // Open service detail modal
        openServiceModal: function(serviceId) {
            $.ajax({
                url: sbha_public.ajax_url,
                type: 'POST',
                data: {
                    action: 'sbha_get_service_details',
                    service_id: serviceId,
                    nonce: sbha_public.nonce
                },
                success: function(response) {
                    if (response.success) {
                        SBHAPublic.showModal(response.data.html);
                    }
                }
            });
        },

        // Initialize quote form
        initQuoteForm: function() {
            var $form = $('.sbha-quote-form');
            if (!$form.length) return;

            // Initialize form validation
            this.initFormValidation($form);

            // Load service options
            this.loadServiceOptions();
        },

        // Load service options for quote form
        loadServiceOptions: function() {
            $.ajax({
                url: sbha_public.ajax_url,
                type: 'POST',
                data: {
                    action: 'sbha_get_service_options',
                    nonce: sbha_public.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var $select = $('#sbha-service-select');
                        response.data.services.forEach(function(service) {
                            $select.append('<option value="' + service.id + '">' + service.name + '</option>');
                        });
                    }
                }
            });
        },

        // Submit quote request
        submitQuoteRequest: function(e) {
            e.preventDefault();

            var $form = $(this);
            var formData = new FormData($form[0]);
            formData.append('action', 'sbha_submit_quote_request');
            formData.append('nonce', sbha_public.nonce);

            $.ajax({
                url: sbha_public.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: function() {
                    $form.find('.sbha-submit-btn').prop('disabled', true).text('Submitting...');
                },
                success: function(response) {
                    if (response.success) {
                        SBHAPublic.showNotification('success', response.data.message);
                        $form[0].reset();

                        // Track for AI
                        SBHAPublic.trackInteraction('quote_request', {
                            service_id: formData.get('service_id')
                        });
                    } else {
                        SBHAPublic.showNotification('error', response.data.message);
                    }
                },
                error: function() {
                    SBHAPublic.showNotification('error', 'An error occurred. Please try again.');
                },
                complete: function() {
                    $form.find('.sbha-submit-btn').prop('disabled', false).text('Submit Request');
                }
            });
        },

        // Handle file uploads
        handleFileUpload: function(e) {
            var files = e.target.files;
            var $preview = $(this).siblings('.sbha-file-preview');

            $preview.empty();

            for (var i = 0; i < files.length; i++) {
                var file = files[i];
                var $item = $('<div class="sbha-file-item">' +
                    '<span class="sbha-file-name">' + file.name + '</span>' +
                    '<span class="sbha-file-size">' + SBHAPublic.formatFileSize(file.size) + '</span>' +
                '</div>');
                $preview.append($item);
            }
        },

        // Initialize AI recommendations
        initRecommendations: function() {
            var $container = $('.sbha-recommendations');
            if (!$container.length) return;

            this.loadRecommendations();
        },

        // Load personalized recommendations
        loadRecommendations: function() {
            $.ajax({
                url: sbha_public.ajax_url,
                type: 'POST',
                data: {
                    action: 'sbha_get_recommendations',
                    nonce: sbha_public.nonce,
                    page_context: window.location.pathname
                },
                success: function(response) {
                    if (response.success && response.data.recommendations.length > 0) {
                        SBHAPublic.renderRecommendations(response.data.recommendations);
                    }
                }
            });
        },

        // Render recommendations
        renderRecommendations: function(recommendations) {
            var $container = $('.sbha-recommendations-list');
            $container.empty();

            recommendations.forEach(function(rec) {
                var html = '<div class="sbha-recommendation-item" data-service-id="' + rec.service_id + '">' +
                    '<div class="sbha-rec-icon"><i class="' + rec.icon + '"></i></div>' +
                    '<div class="sbha-rec-content">' +
                        '<h4>' + rec.title + '</h4>' +
                        '<p>' + rec.reason + '</p>' +
                    '</div>' +
                '</div>';
                $container.append(html);
            });

            $('.sbha-recommendations').addClass('has-recommendations');
        },

        // Handle recommendation click
        handleRecommendationClick: function() {
            var serviceId = $(this).data('service-id');

            SBHAPublic.trackInteraction('recommendation_click', {
                service_id: serviceId
            });

            SBHAPublic.openServiceModal(serviceId);
        },

        // Track page view for AI learning
        trackPageView: function() {
            if (typeof sbha_public === 'undefined') return;

            $.ajax({
                url: sbha_public.ajax_url,
                type: 'POST',
                data: {
                    action: 'sbha_track_page_view',
                    nonce: sbha_public.nonce,
                    page_url: window.location.href,
                    page_title: document.title,
                    referrer: document.referrer
                }
            });
        },

        // Track user interaction for AI
        trackInteraction: function(type, data) {
            if (typeof sbha_public === 'undefined') return;

            data = data || {};
            data.interaction_type = type;
            data.action = 'sbha_track_interaction';
            data.nonce = sbha_public.nonce;

            $.ajax({
                url: sbha_public.ajax_url,
                type: 'POST',
                data: data
            });
        },

        // Initialize form validation
        initFormValidation: function($form) {
            $form.find('input[required], textarea[required]').on('blur', function() {
                var $input = $(this);
                if (!$input.val().trim()) {
                    $input.addClass('error');
                } else {
                    $input.removeClass('error');
                }
            });
        },

        // Show modal
        showModal: function(content) {
            var $modal = $('#sbha-modal');

            if (!$modal.length) {
                $modal = $('<div id="sbha-modal" class="sbha-modal">' +
                    '<div class="sbha-modal-overlay"></div>' +
                    '<div class="sbha-modal-container">' +
                        '<button class="sbha-modal-close">&times;</button>' +
                        '<div class="sbha-modal-content"></div>' +
                    '</div>' +
                '</div>');
                $('body').append($modal);

                $modal.on('click', '.sbha-modal-overlay, .sbha-modal-close', function() {
                    SBHAPublic.closeModal();
                });
            }

            $modal.find('.sbha-modal-content').html(content);
            $modal.addClass('active');
            $('body').addClass('sbha-modal-open');
        },

        // Close modal
        closeModal: function() {
            $('#sbha-modal').removeClass('active');
            $('body').removeClass('sbha-modal-open');
        },

        // Show notification
        showNotification: function(type, message) {
            var $notification = $('<div class="sbha-notification sbha-notification-' + type + '">' +
                '<span class="sbha-notification-message">' + message + '</span>' +
                '<button class="sbha-notification-close">&times;</button>' +
            '</div>');

            $('body').append($notification);

            setTimeout(function() {
                $notification.addClass('show');
            }, 100);

            setTimeout(function() {
                $notification.removeClass('show');
                setTimeout(function() {
                    $notification.remove();
                }, 300);
            }, 5000);

            $notification.on('click', '.sbha-notification-close', function() {
                $notification.remove();
            });
        },

        // Format file size
        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            var k = 1024;
            var sizes = ['Bytes', 'KB', 'MB', 'GB'];
            var i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        SBHAPublic.init();
    });

})(jQuery);
