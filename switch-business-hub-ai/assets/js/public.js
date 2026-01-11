/**
 * Switch Business Hub AI - Complete Hub JavaScript
 * Single-page app with AI assistant & all features
 */

(function($) {
    'use strict';

    window.SBHAHub = {
        ajaxUrl: '',
        nonce: '',
        currency: '$',

        init: function() {
            var $app = $('.sbha-hub-app');
            if (!$app.length) return;

            this.ajaxUrl = $app.data('ajax') || '/wp-admin/admin-ajax.php';
            this.nonce = $app.data('nonce') || '';

            this.bindEvents();
            this.initQuoteCalculator();
            this.loadCustomerOrders();
        },

        bindEvents: function() {
            // Bottom navigation
            $(document).on('click', '.sbha-nav-item[data-panel]', this.switchPanel);
            $(document).on('click', '.sbha-nav-item[data-action="focus-ai"]', this.focusAI);

            // AI Assistant
            $(document).on('click', '#sbha-ai-send', this.sendAIMessage);
            $(document).on('keypress', '#sbha-ai-input', function(e) {
                if (e.which === 13) SBHAHub.sendAIMessage();
            });
            $(document).on('click', '.sbha-quick-btn', this.handleQuickButton);

            // Category filtering
            $(document).on('click', '.sbha-cat-btn', this.filterCategory);

            // Service cards
            $(document).on('click', '.sbha-order-btn', this.orderService);

            // Forms
            $(document).on('submit', '#sbha-quote-form', this.submitQuote);
            $(document).on('submit', '#sbha-track-form', this.trackOrder);
            $(document).on('submit', '#sbha-contact-form', this.submitContact);
            $(document).on('submit', '#sbha-login-form', this.handleLogin);
            $(document).on('submit', '#sbha-register-form', this.handleRegister);
            $(document).on('submit', '#sbha-doc-lookup-form', this.lookupDocuments);

            // Quote calculator
            $(document).on('change', '#quote-service-select, #quote-quantity, [name="urgency"]', this.updateQuotePreview);

            // Modals
            $(document).on('click', '.sbha-login-btn', function() { SBHAHub.openModal('sbha-auth-modal'); });
            $(document).on('click', '.sbha-modal-overlay, .sbha-modal-close', this.closeModal);

            // Auth tabs
            $(document).on('click', '.sbha-auth-tab', this.switchAuthTab);

            // Document tabs
            $(document).on('click', '.sbha-doc-tab', this.switchDocTab);
        },

        // Switch panel via bottom nav
        switchPanel: function(e) {
            e.preventDefault();
            var panel = $(this).data('panel');

            // Update nav
            $('.sbha-nav-item').removeClass('active');
            $(this).addClass('active');

            // Update panel
            $('.sbha-panel').removeClass('sbha-panel-active');
            $('#sbha-panel-' + panel).addClass('sbha-panel-active');

            // Scroll to top of content
            $('html, body').animate({ scrollTop: $('.sbha-main-content').offset().top - 60 }, 300);
        },

        // Focus on AI input
        focusAI: function(e) {
            e.preventDefault();
            $('html, body').animate({ scrollTop: 0 }, 300, function() {
                $('#sbha-ai-input').focus();
            });
        },

        // Send AI message
        sendAIMessage: function() {
            var $input = $('#sbha-ai-input');
            var query = $input.val().trim();

            if (!query) return;

            var $chat = $('#sbha-chat-messages');

            // Add user message
            $chat.append('<div class="sbha-chat-message sbha-user-message">' + SBHAHub.escapeHtml(query) + '</div>');

            // Clear input
            $input.val('');

            // Show typing indicator
            $chat.append('<div class="sbha-chat-message sbha-bot-message sbha-typing">🤖 Thinking...</div>');
            $chat.scrollTop($chat[0].scrollHeight);

            // Process query
            SBHAHub.processAIQuery(query);
        },

        // Process AI query
        processAIQuery: function(query) {
            var $chat = $('#sbha-chat-messages');
            var lowerQuery = query.toLowerCase();
            var response = '';
            var matchedServices = [];

            // Check for track order
            if (lowerQuery.includes('track') || lowerQuery.includes('order') || lowerQuery.includes('status')) {
                response = '📦 I can help you track your order! Let me open the tracking panel for you.';
                setTimeout(function() {
                    $('.sbha-nav-item[data-panel="track"]').click();
                }, 1500);
            }
            // Check for quote/price request
            else if (lowerQuery.includes('quote') || lowerQuery.includes('price') || lowerQuery.includes('cost') || lowerQuery.includes('how much')) {
                response = '💰 I\'ll help you get a quote! Let me open the quote form for you.';
                setTimeout(function() {
                    $('.sbha-nav-item[data-panel="quote"]').click();
                }, 1500);
            }
            // Check for contact
            else if (lowerQuery.includes('contact') || lowerQuery.includes('call') || lowerQuery.includes('phone') || lowerQuery.includes('email')) {
                response = '📞 Here are our contact options:';
                setTimeout(function() {
                    $('.sbha-nav-item[data-panel="contact"]').click();
                }, 1500);
            }
            // Match services
            else {
                // Find matching services
                $('.sbha-service-card').each(function() {
                    var $card = $(this);
                    var name = $card.data('name').toLowerCase();
                    var category = $card.data('category').toLowerCase();

                    if (name.includes(lowerQuery) || lowerQuery.includes(name) ||
                        category.includes(lowerQuery) || lowerQuery.includes(category) ||
                        SBHAHub.matchKeywords(lowerQuery, name, category)) {
                        matchedServices.push({
                            id: $card.data('id'),
                            name: $card.data('name'),
                            price: $card.data('price')
                        });
                    }
                });

                if (matchedServices.length > 0) {
                    response = '✨ Based on your request, I found these services:';
                    response += '<div class="sbha-ai-results">';
                    matchedServices.slice(0, 3).forEach(function(s) {
                        response += '<button class="sbha-ai-service-btn" data-id="' + s.id + '">' +
                            s.name + ' - From $' + parseFloat(s.price).toFixed(2) +
                            '</button>';
                    });
                    response += '</div>';
                    response += '<p>Click a service above or <button class="sbha-quick-btn" data-query="get quote">Get a Custom Quote</button></p>';
                } else {
                    response = '🤔 I couldn\'t find an exact match, but I can help you get a custom quote for: "' + query + '"';
                    response += '<div style="margin-top:15px"><button class="sbha-quick-btn" data-query="custom quote">Request Custom Quote</button></div>';
                }
            }

            // Remove typing and add response
            setTimeout(function() {
                $chat.find('.sbha-typing').remove();
                $chat.append('<div class="sbha-chat-message sbha-bot-message"><p>' + response + '</p></div>');
                $chat.scrollTop($chat[0].scrollHeight);

                // Bind service buttons
                $('.sbha-ai-service-btn').off('click').on('click', function() {
                    var id = $(this).data('id');
                    SBHAHub.selectServiceForQuote(id);
                });
            }, 1000);
        },

        // Match keywords for AI
        matchKeywords: function(query, name, category) {
            var keywords = {
                'business card': ['card', 'business', 'visiting'],
                'logo': ['logo', 'brand', 'identity'],
                'flyer': ['flyer', 'flier', 'leaflet', 'brochure'],
                'website': ['website', 'web', 'site', 'online'],
                'banner': ['banner', 'sign', 'signage', 'poster'],
                'tshirt': ['tshirt', 't-shirt', 'shirt', 'apparel'],
                'architectural': ['architect', 'building', 'plan', 'drawing', 'blueprint'],
                'printing': ['print', 'printing', 'printed']
            };

            for (var key in keywords) {
                if (query.includes(key) || keywords[key].some(function(k) { return query.includes(k); })) {
                    if (name.includes(key) || category.includes(key.split(' ')[0])) {
                        return true;
                    }
                }
            }
            return false;
        },

        // Handle quick button
        handleQuickButton: function() {
            var query = $(this).data('query');
            $('#sbha-ai-input').val(query);
            SBHAHub.sendAIMessage();
        },

        // Filter category
        filterCategory: function() {
            var cat = $(this).data('cat');

            $('.sbha-cat-btn').removeClass('active');
            $(this).addClass('active');

            if (cat === 'all') {
                $('.sbha-service-card').removeClass('hidden');
            } else {
                $('.sbha-service-card').each(function() {
                    if ($(this).data('category') === cat) {
                        $(this).removeClass('hidden');
                    } else {
                        $(this).addClass('hidden');
                    }
                });
            }
        },

        // Order service - go to quote form
        orderService: function(e) {
            e.stopPropagation();
            var $card = $(this).closest('.sbha-service-card');
            var id = $card.data('id');

            SBHAHub.selectServiceForQuote(id);
        },

        // Select service for quote
        selectServiceForQuote: function(id) {
            $('#quote-service-select').val(id).trigger('change');
            $('#quote-service-id').val(id);

            // Switch to quote panel
            $('.sbha-nav-item[data-panel="quote"]').click();

            // Scroll to form
            setTimeout(function() {
                $('html, body').animate({ scrollTop: $('#sbha-panel-quote').offset().top - 70 }, 300);
            }, 300);
        },

        // Initialize quote calculator
        initQuoteCalculator: function() {
            this.updateQuotePreview();
        },

        // Update quote preview
        updateQuotePreview: function() {
            var $select = $('#quote-service-select');
            var selectedOption = $select.find('option:selected');
            var basePrice = parseFloat(selectedOption.data('price')) || 0;
            var quantity = parseInt($('#quote-quantity').val()) || 1;
            var urgency = $('[name="urgency"]').val();
            var urgencyMultiplier = 1;

            if (urgency === 'express') urgencyMultiplier = 1.25;
            if (urgency === 'rush') urgencyMultiplier = 1.5;

            var total = basePrice * quantity * urgencyMultiplier;

            $('#preview-service').text(selectedOption.text().split(' - ')[0] || '-');
            $('#preview-qty').text(quantity);
            $('#preview-base').text('$' + basePrice.toFixed(2));
            $('#preview-total').text('$' + total.toFixed(2));
        },

        // Submit quote
        submitQuote: function(e) {
            e.preventDefault();
            var $form = $(this);
            var $btn = $form.find('.sbha-btn-primary');

            if (!$form[0].checkValidity()) {
                $form[0].reportValidity();
                return;
            }

            $btn.find('.btn-text').hide();
            $btn.find('.btn-loading').show();
            $btn.prop('disabled', true);

            var formData = new FormData($form[0]);
            formData.append('action', 'sbha_submit_quote');
            formData.append('nonce', SBHAHub.nonce);

            $.ajax({
                url: SBHAHub.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        SBHAHub.showToast('success', '✅ Quote submitted! Check your email for the PDF.');

                        // Show download link if available
                        if (response.data && response.data.pdf_url) {
                            SBHAHub.showToast('info', '📄 <a href="' + response.data.pdf_url + '" target="_blank">Download your quote PDF</a>');
                        }

                        $form[0].reset();
                        SBHAHub.updateQuotePreview();
                    } else {
                        SBHAHub.showToast('error', response.data || 'Error submitting quote');
                    }
                },
                error: function() {
                    SBHAHub.showToast('error', 'Connection error. Please try again.');
                },
                complete: function() {
                    $btn.find('.btn-text').show();
                    $btn.find('.btn-loading').hide();
                    $btn.prop('disabled', false);
                }
            });
        },

        // Track order
        trackOrder: function(e) {
            e.preventDefault();
            var query = $('#track-input').val().trim();
            var $results = $('#sbha-track-results');

            if (!query) {
                $results.html('<div class="sbha-message error">Please enter an order number or email</div>');
                return;
            }

            $results.html('<div class="sbha-loading">🔍 Searching...</div>');

            $.ajax({
                url: SBHAHub.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sbha_track_order',
                    query: query,
                    nonce: SBHAHub.nonce
                },
                success: function(response) {
                    if (response.success && response.data.orders && response.data.orders.length > 0) {
                        var html = '';
                        response.data.orders.forEach(function(order) {
                            html += SBHAHub.renderOrderCard(order);
                        });
                        $results.html(html);
                    } else {
                        $results.html('<div class="sbha-message error">No orders found. Please check your order number or email.</div>');
                    }
                },
                error: function() {
                    $results.html('<div class="sbha-message error">Error searching. Please try again.</div>');
                }
            });
        },

        // Render order card
        renderOrderCard: function(order) {
            var html = '<div class="sbha-order-card">' +
                '<div class="sbha-order-header">' +
                    '<span class="sbha-order-number">#' + order.order_number + '</span>' +
                    '<span class="sbha-status-badge sbha-status-' + order.status + '">' + order.status_label + '</span>' +
                '</div>' +
                '<div class="sbha-order-details">' +
                    '<p><strong>Service:</strong> ' + order.service_name + '</p>' +
                    '<p><strong>Date:</strong> ' + order.created_date + '</p>' +
                    (order.estimated_completion ? '<p><strong>Est. Completion:</strong> ' + order.estimated_completion + '</p>' : '') +
                '</div>';

            if (order.admin_response) {
                html += '<div class="sbha-order-response">' +
                    '<h4>📬 Response from ' + order.business_name + ':</h4>' +
                    '<p>' + order.admin_response + '</p>' +
                '</div>';
            }

            if (order.invoice_url) {
                html += '<div class="sbha-order-actions" style="margin-top:15px">' +
                    '<a href="' + order.invoice_url + '" class="sbha-btn sbha-btn-primary" target="_blank">📄 View Invoice</a>' +
                '</div>';
            }

            html += '</div>';
            return html;
        },

        // Load customer orders (if logged in)
        loadCustomerOrders: function() {
            var $container = $('#sbha-customer-orders');
            if (!$container.length) return;

            $.ajax({
                url: SBHAHub.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sbha_get_my_orders',
                    nonce: SBHAHub.nonce
                },
                success: function(response) {
                    if (response.success && response.data.orders && response.data.orders.length > 0) {
                        var html = '';
                        response.data.orders.forEach(function(order) {
                            html += SBHAHub.renderOrderCard(order);
                        });
                        $container.html(html);

                        // Update badge
                        var pending = response.data.orders.filter(function(o) { return o.has_new_response; }).length;
                        if (pending > 0) {
                            $('#orders-badge').text(pending).show();
                        }
                    } else {
                        $container.html('<p>No orders yet.</p>');
                    }
                },
                error: function() {
                    $container.html('<p>Unable to load orders.</p>');
                }
            });
        },

        // Submit contact form
        submitContact: function(e) {
            e.preventDefault();
            var $form = $(this);
            var $btn = $form.find('.sbha-btn-primary');
            var $msg = $('#contact-message');

            $btn.prop('disabled', true).text('Sending...');

            $.ajax({
                url: SBHAHub.ajaxUrl,
                type: 'POST',
                data: $form.serialize() + '&action=sbha_contact&nonce=' + SBHAHub.nonce,
                success: function(response) {
                    if (response.success) {
                        $msg.removeClass('error').addClass('sbha-message success').text('✅ Message sent! We\'ll respond soon.').show();
                        $form[0].reset();
                    } else {
                        $msg.removeClass('success').addClass('sbha-message error').text(response.data || 'Error sending message').show();
                    }
                },
                error: function() {
                    $msg.removeClass('success').addClass('sbha-message error').text('Connection error').show();
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Send Message');
                }
            });
        },

        // Handle login
        handleLogin: function(e) {
            e.preventDefault();
            var $form = $(this);
            var $btn = $form.find('.sbha-btn-primary');
            var $msg = $('#login-message');

            $btn.prop('disabled', true).text('Logging in...');

            $.ajax({
                url: SBHAHub.ajaxUrl,
                type: 'POST',
                data: $form.serialize() + '&action=sbha_login&nonce=' + SBHAHub.nonce,
                success: function(response) {
                    if (response.success) {
                        $msg.removeClass('error').addClass('sbha-message success').text('✅ Login successful!').show();
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        $msg.removeClass('success').addClass('sbha-message error').text(response.data || 'Invalid credentials').show();
                        $btn.prop('disabled', false).text('Login');
                    }
                },
                error: function() {
                    $msg.removeClass('success').addClass('sbha-message error').text('Connection error').show();
                    $btn.prop('disabled', false).text('Login');
                }
            });
        },

        // Handle register
        handleRegister: function(e) {
            e.preventDefault();
            var $form = $(this);
            var $btn = $form.find('.sbha-btn-primary');
            var $msg = $('#register-message');

            $btn.prop('disabled', true).text('Creating account...');

            $.ajax({
                url: SBHAHub.ajaxUrl,
                type: 'POST',
                data: $form.serialize() + '&action=sbha_register&nonce=' + SBHAHub.nonce,
                success: function(response) {
                    if (response.success) {
                        $msg.removeClass('error').addClass('sbha-message success').text('✅ Account created! Logging you in...').show();
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        $msg.removeClass('success').addClass('sbha-message error').text(response.data || 'Registration failed').show();
                        $btn.prop('disabled', false).text('Create Account');
                    }
                },
                error: function() {
                    $msg.removeClass('success').addClass('sbha-message error').text('Connection error').show();
                    $btn.prop('disabled', false).text('Create Account');
                }
            });
        },

        // Lookup documents by email
        lookupDocuments: function(e) {
            e.preventDefault();
            var email = $(this).find('[name="email"]').val();
            var $container = $('#sbha-documents-list');

            if (!$container.length) {
                $container = $('<div id="sbha-documents-list"></div>');
                $(this).after($container);
            }

            $container.html('<div class="sbha-loading">Loading...</div>');

            $.ajax({
                url: SBHAHub.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sbha_get_documents',
                    email: email,
                    nonce: SBHAHub.nonce
                },
                success: function(response) {
                    if (response.success && response.data.documents && response.data.documents.length > 0) {
                        var html = '';
                        response.data.documents.forEach(function(doc) {
                            html += '<div class="sbha-doc-card">' +
                                '<div class="sbha-doc-info">' +
                                    '<h4>' + doc.type + ' #' + doc.number + '</h4>' +
                                    '<p class="sbha-doc-meta">' + doc.date + ' - ' + doc.service + '</p>' +
                                '</div>' +
                                '<div class="sbha-doc-actions">' +
                                    '<a href="' + doc.pdf_url + '" class="sbha-btn sbha-btn-primary" target="_blank">Download PDF</a>' +
                                '</div>' +
                            '</div>';
                        });
                        $container.html(html);
                    } else {
                        $container.html('<div class="sbha-message">No documents found for this email.</div>');
                    }
                },
                error: function() {
                    $container.html('<div class="sbha-message error">Error loading documents.</div>');
                }
            });
        },

        // Switch auth tab
        switchAuthTab: function() {
            var tab = $(this).data('auth');

            $('.sbha-auth-tab').removeClass('active');
            $(this).addClass('active');

            $('.sbha-auth-form').hide();
            $('#sbha-' + tab + '-form').show();
        },

        // Switch doc tab
        switchDocTab: function() {
            var tab = $(this).data('doc');

            $('.sbha-doc-tab').removeClass('active');
            $(this).addClass('active');

            // Reload documents for this type
            // (would filter server-side in real implementation)
        },

        // Open modal
        openModal: function(id) {
            $('#' + id).addClass('active');
            $('body').css('overflow', 'hidden');
        },

        // Close modal
        closeModal: function() {
            $('.sbha-modal').removeClass('active');
            $('body').css('overflow', '');
        },

        // Show toast notification
        showToast: function(type, message) {
            var $container = $('#sbha-notifications');
            var $toast = $('<div class="sbha-toast sbha-toast-' + type + '">' + message + '</div>');

            $container.append($toast);

            setTimeout(function() {
                $toast.fadeOut(300, function() { $(this).remove(); });
            }, 5000);
        },

        // Escape HTML
        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        SBHAHub.init();
    });

})(jQuery);
