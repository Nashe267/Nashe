/**
 * Switch Business Hub AI - Premium App JavaScript
 * Complete single-page app with AI assistant & all features
 */

(function($) {
    'use strict';

    window.SwitchHub = {
        ajaxUrl: '',
        nonce: '',

        init: function() {
            var $app = $('.sh-app');
            if (!$app.length) return;

            this.ajaxUrl = $app.data('ajax') || '/wp-admin/admin-ajax.php';
            this.nonce = $app.data('nonce') || '';

            this.bindEvents();
            this.loadNotifications();
            this.loadMyOrders();
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

            // AI Chat
            $(document).on('click', '#ai-send', function() { self.sendAI(); });
            $(document).on('keypress', '#ai-input', function(e) {
                if (e.which === 13) self.sendAI();
            });
            $(document).on('click', '.sh-quick-btns button', function() {
                var q = $(this).data('q');
                $('#ai-input').val(q);
                self.sendAI();
            });

            // Category filter
            $(document).on('click', '.sh-filter', function() {
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

            // Get Quote buttons
            $(document).on('click', '.sh-get-quote', function(e) {
                e.preventDefault();
                var $card = $(this).closest('.sh-service');
                var id = $card.data('id');
                var name = $card.data('name');

                $('#quote-service').val(id);
                self.switchPanel('quote');
                self.updatePreview();
            });

            // Quote form
            $(document).on('submit', '#quote-form', function(e) {
                e.preventDefault();
                self.submitQuote($(this));
            });

            // Quote calculator
            $(document).on('change', '#quote-service, #quote-qty, #quote-urgency', function() {
                self.updatePreview();
            });

            // Show custom field when "Other" selected
            $(document).on('change', '#quote-service', function() {
                var isCustom = $(this).val() === 'custom';
                $('.sh-custom-field').toggle(isCustom);
            });

            // Track form
            $(document).on('submit', '#track-form', function(e) {
                e.preventDefault();
                self.trackOrder($('#track-input').val());
            });

            // Contact form
            $(document).on('submit', '#contact-form', function(e) {
                e.preventDefault();
                self.submitContact($(this));
            });

            // Auth modal
            $(document).on('click', '.sh-login-btn', function() { self.openModal('auth-modal'); });
            $(document).on('click', '.sh-modal-bg, .sh-modal-close', function() { self.closeModal(); });

            // Auth tabs
            $(document).on('click', '.sh-auth-tab', function() {
                var tab = $(this).data('tab');
                $('.sh-auth-tab').removeClass('active');
                $(this).addClass('active');
                $('.sh-auth-form').removeClass('active');
                $('#' + tab + '-form').addClass('active');
            });

            // Login form
            $(document).on('submit', '#login-form', function(e) {
                e.preventDefault();
                self.login($(this));
            });

            // Register form
            $(document).on('submit', '#register-form', function(e) {
                e.preventDefault();
                self.register($(this));
            });

            // Reset form
            $(document).on('submit', '#reset-form', function(e) {
                e.preventDefault();
                self.resetPassword($(this));
            });

            // Logout
            $(document).on('click', '.sh-logout', function(e) {
                e.preventDefault();
                self.logout();
            });

            // User menu dropdown
            $(document).on('click', '.sh-user-btn', function(e) {
                e.stopPropagation();
                $('.sh-dropdown').toggleClass('active');
            });

            // Notifications
            $(document).on('click', '.sh-notif-icon', function(e) {
                e.stopPropagation();
                $('.sh-notif-panel').toggleClass('active');
            });

            // Close dropdowns on outside click
            $(document).on('click', function() {
                $('.sh-dropdown').removeClass('active');
                $('.sh-notif-panel').removeClass('active');
            });
        },

        // Switch panel
        switchPanel: function(panel) {
            $('.sh-nav-btn').removeClass('active');
            $('.sh-nav-btn[data-panel="' + panel + '"]').addClass('active');
            $('.sh-panel').removeClass('active');
            $('#panel-' + panel).addClass('active');
            $('html, body').animate({ scrollTop: $('.sh-main').offset().top - 60 }, 300);
        },

        // Send AI message
        sendAI: function() {
            var $input = $('#ai-input');
            var query = $input.val().trim();
            if (!query) return;

            var $chat = $('#ai-chat');

            // Add user message
            $chat.append('<div class="sh-msg sh-msg-user">' + this.escapeHtml(query) + '</div>');
            $input.val('');

            // Show typing
            $chat.append('<div class="sh-msg sh-msg-bot sh-typing"><p>Thinking...</p></div>');
            $chat.scrollTop($chat[0].scrollHeight);

            // Process
            this.processAI(query);
        },

        // Process AI query
        processAI: function(query) {
            var self = this;
            var $chat = $('#ai-chat');
            var q = query.toLowerCase();
            var response = '';

            setTimeout(function() {
                $chat.find('.sh-typing').remove();

                // Check keywords
                if (q.includes('track') || q.includes('order') || q.includes('status')) {
                    response = '<p>I can help you track your order! Let me open the tracking panel for you.</p>';
                    setTimeout(function() { self.switchPanel('track'); }, 1200);
                }
                else if (q.includes('quote') || q.includes('price') || q.includes('cost') || q.includes('how much')) {
                    response = '<p>I\'ll help you get a quote! Let me open the quote form.</p>';
                    setTimeout(function() { self.switchPanel('quote'); }, 1200);
                }
                else if (q.includes('contact') || q.includes('call') || q.includes('phone') || q.includes('whatsapp')) {
                    response = '<p>Here are our contact options!</p>';
                    setTimeout(function() { self.switchPanel('contact'); }, 1200);
                }
                else if (q.includes('logo') || q.includes('brand')) {
                    response = '<p>We offer professional logo design and branding services! Check out our services or get a custom quote.</p>';
                    response += '<div class="sh-quick-btns" style="margin-top:12px"><button data-q="get quote for logo">Get Logo Quote</button></div>';
                }
                else if (q.includes('website') || q.includes('web')) {
                    response = '<p>We build modern, responsive websites! From landing pages to full e-commerce solutions.</p>';
                    response += '<div class="sh-quick-btns" style="margin-top:12px"><button data-q="get quote for website">Get Website Quote</button></div>';
                }
                else if (q.includes('print') || q.includes('card') || q.includes('flyer') || q.includes('banner')) {
                    response = '<p>We offer high-quality printing services including business cards, flyers, banners, and more!</p>';
                    response += '<div class="sh-quick-btns" style="margin-top:12px"><button data-q="get quote for printing">Get Printing Quote</button></div>';
                }
                else if (q.includes('architect') || q.includes('building') || q.includes('plan') || q.includes('drawing')) {
                    response = '<p>We provide professional architectural drawings and building plans!</p>';
                    response += '<div class="sh-quick-btns" style="margin-top:12px"><button data-q="get quote for architectural">Get Architecture Quote</button></div>';
                }
                else {
                    response = '<p>I\'d be happy to help with that! For "' + self.escapeHtml(query) + '", let me get you a custom quote.</p>';
                    response += '<div class="sh-quick-btns" style="margin-top:12px">';
                    response += '<button data-q="get quote">Get Custom Quote</button>';
                    response += '<button data-q="contact">Contact Us</button>';
                    response += '</div>';
                }

                $chat.append('<div class="sh-msg sh-msg-bot">' + response + '</div>');
                $chat.scrollTop($chat[0].scrollHeight);
            }, 1000);
        },

        // Update quote preview
        updatePreview: function() {
            var $sel = $('#quote-service option:selected');
            var basePrice = parseFloat($sel.data('price')) || 0;
            var qty = parseInt($('#quote-qty').val()) || 1;
            var urgency = $('#quote-urgency').val();
            var mult = urgency === 'express' ? 1.25 : (urgency === 'rush' ? 1.5 : 1);
            var total = basePrice * qty * mult;

            $('#prev-service').text($sel.text().split(' - ')[0] || '-');
            $('#prev-qty').text(qty);
            $('#prev-base').text('$' + basePrice.toFixed(2));
            $('#prev-total').text('$' + total.toFixed(2));
        },

        // Submit quote
        submitQuote: function($form) {
            var self = this;
            var $btn = $form.find('.sh-btn-primary');

            if (!$form[0].checkValidity()) {
                $form[0].reportValidity();
                return;
            }

            $btn.prop('disabled', true).text('Submitting...');

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
                    if (res.success) {
                        self.toast('success', 'Quote submitted! Check your email for the PDF.');
                        if (res.data && res.data.pdf_url) {
                            self.toast('info', '<a href="' + res.data.pdf_url + '" target="_blank">Download Quote PDF</a>');
                        }
                        $form[0].reset();
                        self.updatePreview();
                    } else {
                        self.toast('error', res.data || 'Error submitting quote');
                    }
                },
                error: function() {
                    self.toast('error', 'Connection error. Please try again.');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Get Quote');
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
                    if (res.success && res.data.orders && res.data.orders.length > 0) {
                        var html = '';
                        res.data.orders.forEach(function(o) {
                            html += self.renderOrder(o);
                        });
                        $results.html(html);
                    } else {
                        $results.html('<div class="sh-message error">No orders found.</div>');
                    }
                },
                error: function() {
                    $results.html('<div class="sh-message error">Error searching. Try again.</div>');
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
                    if (res.success && res.data.orders && res.data.orders.length > 0) {
                        var html = '';
                        res.data.orders.forEach(function(o) {
                            html += self.renderOrder(o);
                        });
                        $container.html(html);
                    } else {
                        $container.html('<p>No orders yet.</p>');
                    }
                }
            });
        },

        // Submit contact
        submitContact: function($form) {
            var self = this;
            var $btn = $form.find('.sh-btn-primary');
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
                    } else {
                        $msg.removeClass('success').addClass('sh-message error').text(res.data || 'Error sending').show();
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
            var $btn = $form.find('.sh-btn-primary');
            var $msg = $('#login-msg');

            $btn.prop('disabled', true).text('Logging in...');

            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: $form.serialize() + '&action=sbha_login&nonce=' + this.nonce,
                success: function(res) {
                    if (res.success) {
                        $msg.removeClass('error').addClass('sh-message success').text('Login successful!').show();
                        setTimeout(function() { window.location.reload(); }, 1000);
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
            var $btn = $form.find('.sh-btn-primary');
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
                    if (res.success) {
                        $msg.removeClass('error').addClass('sh-message success').text('Account created! Logging in...').show();
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
            var $btn = $form.find('.sh-btn-primary');
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
            if (!$list.length) return;

            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sbha_get_notifications',
                    nonce: this.nonce
                },
                success: function(res) {
                    if (res.success && res.data.notifications && res.data.notifications.length > 0) {
                        var html = '';
                        var unread = 0;
                        res.data.notifications.forEach(function(n) {
                            html += '<div class="sh-notif-item' + (n.is_read ? '' : ' unread') + '" data-id="' + n.id + '">';
                            html += '<div class="sh-notif-title">' + n.title + '</div>';
                            html += '<div class="sh-notif-msg">' + n.message + '</div>';
                            html += '<div class="sh-notif-time">' + n.time_ago + '</div>';
                            html += '</div>';
                            if (!n.is_read) unread++;
                        });
                        $list.html(html);

                        if (unread > 0) {
                            $('.sh-badge').text(unread).show();
                        } else {
                            $('.sh-badge').hide();
                        }
                    } else {
                        $list.html('<div style="padding:20px;text-align:center;color:#666">No notifications</div>');
                        $('.sh-badge').hide();
                    }
                }
            });
        },

        // Open modal
        openModal: function(id) {
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
            var $container = $('#toasts');
            if (!$container.length) {
                $container = $('<div id="toasts" class="sh-toasts"></div>');
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

    // Initialize
    $(document).ready(function() {
        SwitchHub.init();
    });

})(jQuery);
