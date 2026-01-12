/**
 * Switch Business Hub AI - Premium App JavaScript v1.2.0
 * Complete single-page app with AI assistant & all features
 */

(function($) {
    'use strict';

    var SwitchHub = {
        ajaxUrl: '',
        nonce: '',
        initialized: false,

        init: function() {
            var $app = $('.sh-app');
            if (!$app.length) {
                console.log('SwitchHub: No .sh-app element found');
                return;
            }

            this.ajaxUrl = $app.data('ajax') || '/wp-admin/admin-ajax.php';
            this.nonce = $app.data('nonce') || '';

            console.log('SwitchHub: Initializing with AJAX URL:', this.ajaxUrl);

            this.bindEvents();
            this.loadNotifications();
            this.loadMyOrders();
            this.initialized = true;
            console.log('SwitchHub: Initialized successfully');
        },

        bindEvents: function() {
            var self = this;

            // Bottom navigation
            $(document).on('click', '.sh-nav-btn[data-panel]', function(e) {
                e.preventDefault();
                self.switchPanel($(this).data('panel'));
            });

            // AI focus button
            $(document).on('click', '.sh-nav-ai', function(e) {
                e.preventDefault();
                $('html, body').animate({ scrollTop: 0 }, 300, function() {
                    $('#ai-input').focus();
                });
            });

            // AI Chat - Send button
            $(document).on('click', '#ai-send', function(e) {
                e.preventDefault();
                console.log('SwitchHub: AI send clicked');
                self.sendAI();
            });

            // AI Chat - Enter key
            $(document).on('keypress', '#ai-input', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    console.log('SwitchHub: AI enter pressed');
                    self.sendAI();
                }
            });

            // Quick buttons in AI chat
            $(document).on('click', '.sh-quick-btns button', function(e) {
                e.preventDefault();
                var q = $(this).data('q');
                if (q) {
                    $('#ai-input').val(q);
                    self.sendAI();
                }
            });

            // Category filter
            $(document).on('click', '.sh-filter', function(e) {
                e.preventDefault();
                var cat = $(this).data('cat');
                $('.sh-filter').removeClass('active');
                $(this).addClass('active');

                if (cat === 'all') {
                    $('.sh-service').removeClass('hidden');
                } else {
                    $('.sh-service').each(function() {
                        $(this).toggleClass('hidden', $(this).data('cat') !== cat);
                    });
                }
            });

            // Get Quote buttons on service cards
            $(document).on('click', '.sh-get-quote', function(e) {
                e.preventDefault();
                var $card = $(this).closest('.sh-service');
                var id = $card.data('id');
                $('#quote-service').val(id);
                self.switchPanel('quote');
                self.updatePreview();
            });

            // Quote form submission
            $(document).on('submit', '#quote-form', function(e) {
                e.preventDefault();
                console.log('SwitchHub: Quote form submitted');
                self.submitQuote($(this));
            });

            // Quote calculator inputs
            $(document).on('change', '#quote-service, #quote-qty, #quote-urg', function() {
                self.updatePreview();
            });

            // Show custom field when "Other" or custom selected
            $(document).on('change', '#quote-service', function() {
                var val = $(this).val();
                var isCustom = val === 'custom' || val === '' || val === '0';
                $('.sh-custom-field').toggle(isCustom);
            });

            // Track order form
            $(document).on('submit', '#track-form', function(e) {
                e.preventDefault();
                console.log('SwitchHub: Track form submitted');
                self.trackOrder($('#track-input').val());
            });

            // Contact form
            $(document).on('submit', '#contact-form', function(e) {
                e.preventDefault();
                console.log('SwitchHub: Contact form submitted');
                self.submitContact($(this));
            });

            // Login button - open modal
            $(document).on('click', '.sh-login-btn, #login-btn', function(e) {
                e.preventDefault();
                console.log('SwitchHub: Login button clicked');
                self.openModal('auth-modal');
            });

            // Close modal
            $(document).on('click', '.sh-modal-bg, .sh-modal-close', function(e) {
                e.preventDefault();
                self.closeModal();
            });

            // Auth tabs
            $(document).on('click', '.sh-auth-tab', function(e) {
                e.preventDefault();
                var tab = $(this).data('tab');
                $('.sh-auth-tab').removeClass('active');
                $(this).addClass('active');
                $('.sh-auth-form').removeClass('active');
                $('#' + tab + '-form').addClass('active');
            });

            // Login form
            $(document).on('submit', '#login-form', function(e) {
                e.preventDefault();
                console.log('SwitchHub: Login form submitted');
                self.login($(this));
            });

            // Register form
            $(document).on('submit', '#register-form', function(e) {
                e.preventDefault();
                console.log('SwitchHub: Register form submitted');
                self.register($(this));
            });

            // Reset password form
            $(document).on('submit', '#reset-form', function(e) {
                e.preventDefault();
                console.log('SwitchHub: Reset form submitted');
                self.resetPassword($(this));
            });

            // Logout
            $(document).on('click', '.sh-logout, #logout-btn', function(e) {
                e.preventDefault();
                console.log('SwitchHub: Logout clicked');
                self.logout();
            });

            // User menu dropdown toggle
            $(document).on('click', '.sh-user-btn, #user-menu-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $('.sh-dropdown, #user-dropdown').toggleClass('active');
            });

            // Notifications panel toggle
            $(document).on('click', '.sh-notif-icon, #notif-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $('.sh-notif-panel').toggleClass('active');
            });

            // Close dropdowns on outside click
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.sh-user-menu').length) {
                    $('.sh-dropdown, #user-dropdown').removeClass('active');
                }
                if (!$(e.target).closest('.sh-notif-panel, .sh-notif-icon, #notif-btn').length) {
                    $('.sh-notif-panel').removeClass('active');
                }
            });

            // Dropdown menu items with panels
            $(document).on('click', '.sh-dropdown a[data-panel], #user-dropdown a[data-panel]', function(e) {
                e.preventDefault();
                var panel = $(this).data('panel');
                if (panel) {
                    self.switchPanel(panel);
                    $('.sh-dropdown, #user-dropdown').removeClass('active');
                }
            });

            console.log('SwitchHub: Events bound');
        },

        // Switch panel
        switchPanel: function(panel) {
            console.log('SwitchHub: Switching to panel:', panel);
            $('.sh-nav-btn').removeClass('active');
            $('.sh-nav-btn[data-panel="' + panel + '"]').addClass('active');
            $('.sh-panel').removeClass('active');
            $('#panel-' + panel).addClass('active');

            var $main = $('.sh-main');
            if ($main.length) {
                $('html, body').animate({ scrollTop: $main.offset().top - 60 }, 300);
            }
        },

        // Send AI message
        sendAI: function() {
            var $input = $('#ai-input');
            var query = $input.val().trim();

            if (!query) {
                console.log('SwitchHub: Empty AI query');
                return;
            }

            console.log('SwitchHub: Sending AI query:', query);

            var $chat = $('#ai-chat');

            // Add user message
            $chat.append('<div class="sh-msg sh-msg-user">' + this.escapeHtml(query) + '</div>');
            $input.val('');

            // Show typing indicator
            $chat.append('<div class="sh-msg sh-msg-bot sh-typing"><p>Thinking...</p></div>');
            $chat.scrollTop($chat[0].scrollHeight);

            // Process with Gemini AI
            this.processAI(query);
        },

        // Process AI query using Gemini
        processAI: function(query) {
            var self = this;
            var $chat = $('#ai-chat');

            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sbha_ai_chat',
                    message: query,
                    nonce: this.nonce
                },
                success: function(res) {
                    console.log('SwitchHub: AI response received', res);
                    $chat.find('.sh-typing').remove();

                    var response = '';
                    if (res.success && res.data && res.data.response) {
                        response = '<p>' + res.data.response + '</p>';

                        // Auto-navigate based on keywords
                        var q = query.toLowerCase();
                        if (q.includes('track') || q.includes('order status')) {
                            setTimeout(function() { self.switchPanel('track'); }, 2000);
                        } else if (q.includes('get quote') || q.includes('request quote')) {
                            setTimeout(function() { self.switchPanel('quote'); }, 2000);
                        } else if (q.includes('contact') && !q.includes('form')) {
                            setTimeout(function() { self.switchPanel('contact'); }, 2000);
                        }
                    } else {
                        response = '<p>I\'d be happy to help! Try asking about our services, requesting a quote, or tracking an order.</p>';
                    }

                    // Add quick action buttons
                    response += '<div class="sh-quick-btns" style="margin-top:12px">';
                    response += '<button data-q="Get me a quote">Get Quote</button>';
                    response += '<button data-q="Track my order">Track Order</button>';
                    response += '</div>';

                    $chat.append('<div class="sh-msg sh-msg-bot">' + response + '</div>');
                    $chat.scrollTop($chat[0].scrollHeight);
                },
                error: function(xhr, status, error) {
                    console.log('SwitchHub: AI error', error);
                    $chat.find('.sh-typing').remove();
                    $chat.append('<div class="sh-msg sh-msg-bot"><p>Sorry, I\'m having trouble connecting. Please try again or use the menu below.</p></div>');
                    $chat.scrollTop($chat[0].scrollHeight);
                }
            });
        },

        // Update quote preview
        updatePreview: function() {
            var $sel = $('#quote-service option:selected');
            var basePrice = parseFloat($sel.data('price')) || 0;
            var qty = parseInt($('#quote-qty').val()) || 1;
            var urgency = $('#quote-urg').val() || 'standard';
            var mult = urgency === 'express' ? 1.25 : (urgency === 'rush' ? 1.5 : 1);
            var total = basePrice * qty * mult;

            $('#pv-svc').text($sel.text().split(' - ')[0] || '-');
            $('#pv-qty').text(qty);
            $('#pv-total').text('R' + total.toFixed(2));
        },

        // Submit quote
        submitQuote: function($form) {
            var self = this;
            var $btn = $form.find('button[type="submit"]');
            var $btnTxt = $btn.find('.btn-txt');
            var $btnLoad = $btn.find('.btn-load');

            if (!$form[0].checkValidity()) {
                $form[0].reportValidity();
                return;
            }

            $btn.prop('disabled', true);
            $btnTxt.hide();
            $btnLoad.show();

            var formData = new FormData($form[0]);
            formData.append('action', 'sbha_submit_quote');
            formData.append('nonce', this.nonce);

            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(res) {
                    console.log('SwitchHub: Quote response', res);
                    var $msg = $('#quote-msg');
                    if (res.success) {
                        $msg.removeClass('error').addClass('sh-message success')
                            .html('Quote submitted successfully! Order: <strong>' + res.data.order_number + '</strong>')
                            .show();
                        $form[0].reset();
                        self.updatePreview();
                        self.toast('success', 'Quote submitted! We\'ll get back to you soon.');
                    } else {
                        $msg.removeClass('success').addClass('sh-message error')
                            .text(res.data || 'Error submitting quote').show();
                        self.toast('error', res.data || 'Error submitting quote');
                    }
                },
                error: function() {
                    $('#quote-msg').removeClass('success').addClass('sh-message error')
                        .text('Connection error. Please try again.').show();
                    self.toast('error', 'Connection error');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                    $btnTxt.show();
                    $btnLoad.hide();
                }
            });
        },

        // Track order
        trackOrder: function(query) {
            var self = this;
            var $results = $('#track-results');

            if (!query) {
                $results.html('<div class="sh-message error">Please enter an order number or email</div>');
                return;
            }

            console.log('SwitchHub: Tracking order:', query);
            $results.html('<div class="sh-loading">Searching...</div>');

            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sbha_track_order',
                    query: query,
                    nonce: this.nonce
                },
                success: function(res) {
                    console.log('SwitchHub: Track response', res);
                    if (res.success && res.data && res.data.orders && res.data.orders.length > 0) {
                        var html = '';
                        res.data.orders.forEach(function(o) {
                            html += self.renderOrder(o);
                        });
                        $results.html(html);
                    } else {
                        $results.html('<div class="sh-message error">No orders found for that search.</div>');
                    }
                },
                error: function() {
                    $results.html('<div class="sh-message error">Error searching. Please try again.</div>');
                }
            });
        },

        // Render order card
        renderOrder: function(o) {
            var html = '<div class="sh-order-card">';
            html += '<div class="sh-order-header">';
            html += '<span class="sh-order-number">#' + o.order_number + '</span>';
            html += '<span class="sh-status sh-status-' + o.status + '">' + o.status_label + '</span>';
            html += '</div>';
            html += '<p><strong>Service:</strong> ' + o.service_name + '</p>';
            html += '<p><strong>Date:</strong> ' + o.created_date + '</p>';
            html += '<p><strong>Quote:</strong> ' + o.total + '</p>';

            if (o.client_budget) {
                html += '<p><strong>Your Budget:</strong> ' + o.client_budget + '</p>';
            }

            // Show quote status with styling
            if (o.quote_status) {
                var bgColor = o.quote_status === 'approved' ? '#d1fae5' :
                              (o.quote_status === 'declined' ? '#fee2e2' : '#fef3c7');
                var borderColor = o.quote_status === 'approved' ? '#10b981' :
                                  (o.quote_status === 'declined' ? '#ef4444' : '#f59e0b');
                var icon = o.quote_status === 'approved' ? '✅' :
                           (o.quote_status === 'declined' ? '❌' : '⏳');

                html += '<div style="margin:15px 0;padding:12px;border-radius:8px;background:' + bgColor + ';border-left:4px solid ' + borderColor + ';">';
                html += '<strong>' + icon + ' Quote Status: ' + o.quote_status_label + '</strong>';
                if (o.quote_response_note) {
                    html += '<p style="margin-top:8px;font-size:14px;">' + o.quote_response_note + '</p>';
                }
                html += '</div>';
            }

            if (o.estimated_completion) {
                html += '<p><strong>Est. Completion:</strong> ' + o.estimated_completion + '</p>';
            }
            if (o.admin_response) {
                html += '<div class="sh-order-response">';
                html += '<h4>Response from Switch Hub:</h4>';
                html += '<p>' + o.admin_response + '</p>';
                html += '</div>';
            }
            if (o.invoice_url) {
                html += '<div style="margin-top:15px"><a href="' + o.invoice_url + '" class="sh-btn sh-btn-primary sh-btn-sm" target="_blank">View Invoice</a></div>';
            }
            html += '</div>';
            return html;
        },

        // Load my orders
        loadMyOrders: function() {
            var self = this;
            var $container = $('#my-orders');
            if (!$container.length) return;

            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sbha_get_my_orders',
                    nonce: this.nonce
                },
                success: function(res) {
                    if (res.success && res.data && res.data.orders && res.data.orders.length > 0) {
                        var html = '';
                        res.data.orders.forEach(function(o) {
                            html += self.renderOrder(o);
                        });
                        $container.html(html);
                    } else {
                        $container.html('<p class="sh-no-orders">No orders yet. Submit a quote to get started!</p>');
                    }
                }
            });
        },

        // Submit contact form
        submitContact: function($form) {
            var self = this;
            var $btn = $form.find('button[type="submit"]');
            var $msg = $('#contact-msg');

            $btn.prop('disabled', true).text('Sending...');

            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: $form.serialize() + '&action=sbha_contact&nonce=' + this.nonce,
                success: function(res) {
                    if (res.success) {
                        $msg.removeClass('error').addClass('sh-message success').text('Message sent! We\'ll respond soon.').show();
                        $form[0].reset();
                        self.toast('success', 'Message sent!');
                    } else {
                        $msg.removeClass('success').addClass('sh-message error').text(res.data || 'Error sending message').show();
                    }
                },
                error: function() {
                    $msg.removeClass('success').addClass('sh-message error').text('Connection error').show();
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Send Message');
                }
            });
        },

        // Login
        login: function($form) {
            var self = this;
            var $btn = $form.find('button[type="submit"]');
            var $msg = $('#login-msg');

            $btn.prop('disabled', true).text('Logging in...');

            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: $form.serialize() + '&action=sbha_login&nonce=' + this.nonce,
                success: function(res) {
                    console.log('SwitchHub: Login response', res);
                    if (res.success) {
                        $msg.removeClass('error').addClass('sh-message success').text('Login successful! Reloading...').show();
                        self.toast('success', 'Welcome back!');
                        setTimeout(function() { window.location.reload(); }, 1500);
                    } else {
                        $msg.removeClass('success').addClass('sh-message error').text(res.data || 'Invalid credentials').show();
                        $btn.prop('disabled', false).text('Login');
                    }
                },
                error: function() {
                    $msg.removeClass('success').addClass('sh-message error').text('Connection error').show();
                    $btn.prop('disabled', false).text('Login');
                }
            });
        },

        // Register
        register: function($form) {
            var self = this;
            var $btn = $form.find('button[type="submit"]');
            var $msg = $('#register-msg');

            // Validate password match
            var pw = $form.find('[name="password"]').val();
            var pw2 = $form.find('[name="password_confirm"]').val();
            if (pw !== pw2) {
                $msg.removeClass('success').addClass('sh-message error').text('Passwords do not match').show();
                return;
            }

            $btn.prop('disabled', true).text('Creating account...');

            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: $form.serialize() + '&action=sbha_register&nonce=' + this.nonce,
                success: function(res) {
                    console.log('SwitchHub: Register response', res);
                    if (res.success) {
                        $msg.removeClass('error').addClass('sh-message success').text('Account created! Logging in...').show();
                        self.toast('success', 'Account created!');
                        setTimeout(function() { window.location.reload(); }, 1500);
                    } else {
                        $msg.removeClass('success').addClass('sh-message error').text(res.data || 'Registration failed').show();
                        $btn.prop('disabled', false).text('Create Account');
                    }
                },
                error: function() {
                    $msg.removeClass('success').addClass('sh-message error').text('Connection error').show();
                    $btn.prop('disabled', false).text('Create Account');
                }
            });
        },

        // Reset password
        resetPassword: function($form) {
            var self = this;
            var $btn = $form.find('button[type="submit"]');
            var $msg = $('#reset-msg');

            $btn.prop('disabled', true).text('Resetting...');

            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: $form.serialize() + '&action=sbha_reset_password&nonce=' + this.nonce,
                success: function(res) {
                    if (res.success) {
                        $msg.removeClass('error').addClass('sh-message success').text('Password reset! You can now login.').show();
                        $form[0].reset();
                        self.toast('success', 'Password reset successfully!');
                        // Switch to login tab
                        setTimeout(function() {
                            $('.sh-auth-tab[data-tab="login"]').click();
                        }, 1500);
                    } else {
                        $msg.removeClass('success').addClass('sh-message error').text(res.data || 'Reset failed').show();
                    }
                },
                error: function() {
                    $msg.removeClass('success').addClass('sh-message error').text('Connection error').show();
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Reset Password');
                }
            });
        },

        // Logout
        logout: function() {
            var self = this;
            console.log('SwitchHub: Logging out...');

            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sbha_logout',
                    nonce: this.nonce
                },
                success: function(res) {
                    if (res.success) {
                        self.toast('success', 'Logged out successfully');
                        setTimeout(function() { window.location.reload(); }, 1000);
                    }
                }
            });
        },

        // Load notifications
        loadNotifications: function() {
            var self = this;
            var $list = $('#notif-list');
            var $badge = $('#notif-badge, .sh-badge');

            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sbha_get_notifications',
                    nonce: this.nonce
                },
                success: function(res) {
                    if (res.success && res.data && res.data.notifications && res.data.notifications.length > 0) {
                        if ($list.length) {
                            var html = '';
                            res.data.notifications.forEach(function(n) {
                                html += '<div class="sh-notif-item' + (n.is_read ? '' : ' unread') + '" data-id="' + n.id + '">';
                                html += '<div class="sh-notif-title">' + n.title + '</div>';
                                html += '<div class="sh-notif-msg">' + n.message + '</div>';
                                html += '</div>';
                            });
                            $list.html(html);
                        }

                        var unread = res.data.unread || 0;
                        if (unread > 0) {
                            $badge.text(unread).show();
                        } else {
                            $badge.hide();
                        }
                    } else {
                        if ($list.length) {
                            $list.html('<div style="padding:20px;text-align:center;color:#666">No notifications</div>');
                        }
                        $badge.hide();
                    }
                }
            });
        },

        // Open modal
        openModal: function(id) {
            console.log('SwitchHub: Opening modal:', id);
            $('#' + id).addClass('active');
            $('body').css('overflow', 'hidden');
        },

        // Close modal
        closeModal: function() {
            $('.sh-modal').removeClass('active');
            $('body').css('overflow', '');
        },

        // Toast notification
        toast: function(type, msg) {
            var $container = $('#sh-toasts');
            if (!$container.length) {
                $container = $('<div id="sh-toasts" class="sh-toasts"></div>');
                $('body').append($container);
            }

            var $toast = $('<div class="sh-toast sh-toast-' + type + '">' + msg + '</div>');
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

    // Initialize when document is ready
    $(document).ready(function() {
        console.log('SwitchHub: Document ready, initializing...');
        SwitchHub.init();
    });

    // Make it globally accessible for debugging
    window.SwitchHub = SwitchHub;

})(jQuery);
