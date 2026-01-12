/**
 * Switch Business Hub AI - Premium App JavaScript v1.3.0
 * Complete single-page app with AI assistant & all features
 */

jQuery(document).ready(function($) {
    'use strict';

    // Get AJAX settings from WordPress or DOM
    var ajaxUrl = '';
    var nonce = '';

    // Try WordPress localized variable first
    if (typeof sbhaPublic !== 'undefined') {
        ajaxUrl = sbhaPublic.ajaxUrl;
        nonce = sbhaPublic.nonce;
    }

    // Fallback to DOM data attributes
    var $app = $('.sh-app');
    if ($app.length) {
        if (!ajaxUrl) ajaxUrl = $app.data('ajax');
        if (!nonce) nonce = $app.data('nonce');
    }

    // Final fallback
    if (!ajaxUrl) ajaxUrl = '/wp-admin/admin-ajax.php';

    console.log('SwitchHub v1.3.0 initialized');
    console.log('AJAX URL:', ajaxUrl);
    console.log('Nonce:', nonce ? 'Set' : 'Missing');

    // ==================== NAVIGATION ====================

    // Bottom navigation buttons
    $('.sh-nav-btn[data-panel]').on('click', function(e) {
        e.preventDefault();
        var panel = $(this).data('panel');
        switchPanel(panel);
    });

    // AI center button - scroll to top and focus input
    $('.sh-nav-ai').on('click', function(e) {
        e.preventDefault();
        $('html, body').animate({ scrollTop: 0 }, 300, function() {
            $('#ai-input').focus();
        });
    });

    function switchPanel(panel) {
        console.log('Switching to panel:', panel);
        $('.sh-nav-btn').removeClass('active');
        $('.sh-nav-btn[data-panel="' + panel + '"]').addClass('active');
        $('.sh-panel').removeClass('active');
        $('#panel-' + panel).addClass('active');

        var $main = $('.sh-main');
        if ($main.length) {
            $('html, body').animate({ scrollTop: $main.offset().top - 60 }, 300);
        }
    }

    // ==================== AI CHAT ====================

    // Send button click
    $('#ai-send').on('click', function(e) {
        e.preventDefault();
        sendAIMessage();
    });

    // Enter key in input
    $('#ai-input').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            sendAIMessage();
        }
    });

    // Quick buttons
    $(document).on('click', '.sh-quick-btns button', function(e) {
        e.preventDefault();
        var q = $(this).data('q');
        if (q) {
            $('#ai-input').val(q);
            sendAIMessage();
        }
    });

    function sendAIMessage() {
        var $input = $('#ai-input');
        var query = $input.val().trim();

        if (!query) {
            console.log('Empty query');
            return;
        }

        console.log('Sending AI message:', query);

        var $chat = $('#ai-chat');

        // Add user message
        $chat.append('<div class="sh-msg sh-msg-user">' + escapeHtml(query) + '</div>');
        $input.val('');

        // Show typing
        $chat.append('<div class="sh-msg sh-msg-bot sh-typing"><p>Thinking...</p></div>');
        $chat.scrollTop($chat[0].scrollHeight);

        // Send to server
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'sbha_ai_chat',
                message: query,
                nonce: nonce
            },
            success: function(res) {
                console.log('AI response:', res);
                $chat.find('.sh-typing').remove();

                var response = '';
                if (res.success && res.data && res.data.response) {
                    response = '<p>' + res.data.response + '</p>';
                } else {
                    response = '<p>I\'d be happy to help! Try asking about our services, requesting a quote, or tracking an order.</p>';
                }

                response += '<div class="sh-quick-btns" style="margin-top:12px">';
                response += '<button data-q="Get me a quote">Get Quote</button>';
                response += '<button data-q="Track my order">Track Order</button>';
                response += '</div>';

                $chat.append('<div class="sh-msg sh-msg-bot">' + response + '</div>');
                $chat.scrollTop($chat[0].scrollHeight);
            },
            error: function(xhr, status, error) {
                console.log('AI error:', error);
                $chat.find('.sh-typing').remove();
                $chat.append('<div class="sh-msg sh-msg-bot"><p>Sorry, I\'m having trouble connecting. Please try the menu below.</p></div>');
                $chat.scrollTop($chat[0].scrollHeight);
            }
        });
    }

    // ==================== SERVICE FILTERS ====================

    $('.sh-filter').on('click', function(e) {
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

    // ==================== GET QUOTE BUTTONS ====================

    $('.sh-get-quote').on('click', function(e) {
        e.preventDefault();
        var $card = $(this).closest('.sh-service');
        var id = $card.data('id');
        $('#quote-service').val(id).trigger('change');
        switchPanel('quote');
    });

    // Quote preview calculator
    $('#quote-service, #quote-qty, #quote-urg').on('change', function() {
        updateQuotePreview();
    });

    function updateQuotePreview() {
        var $sel = $('#quote-service option:selected');
        var basePrice = parseFloat($sel.data('price')) || 0;
        var qty = parseInt($('#quote-qty').val()) || 1;
        var urgency = $('#quote-urg').val() || 'standard';
        var mult = urgency === 'express' ? 1.25 : (urgency === 'rush' ? 1.5 : 1);
        var total = basePrice * qty * mult;

        $('#pv-svc').text($sel.text().split(' - ')[0] || '-');
        $('#pv-qty').text(qty);
        $('#pv-total').text('R' + total.toFixed(2));
    }

    // Show custom field
    $('#quote-service').on('change', function() {
        var val = $(this).val();
        var isCustom = val === 'custom' || val === '' || val === '0';
        $('.sh-custom-field').toggle(isCustom);
    });

    // ==================== QUOTE FORM ====================

    $('#quote-form').on('submit', function(e) {
        e.preventDefault();
        console.log('Quote form submitted');

        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        var $btnTxt = $btn.find('.btn-txt');
        var $btnLoad = $btn.find('.btn-load');

        $btn.prop('disabled', true);
        $btnTxt.hide();
        $btnLoad.show();

        var formData = new FormData(this);
        formData.append('action', 'sbha_submit_quote');
        formData.append('nonce', nonce);

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                console.log('Quote response:', res);
                var $msg = $('#quote-msg');
                if (res.success) {
                    $msg.removeClass('error').addClass('sh-message success')
                        .html('Quote submitted! Order: <strong>' + res.data.order_number + '</strong>').show();
                    $form[0].reset();
                    updateQuotePreview();
                    showToast('success', 'Quote submitted successfully!');
                } else {
                    $msg.removeClass('success').addClass('sh-message error')
                        .text(res.data || 'Error submitting quote').show();
                }
            },
            error: function() {
                $('#quote-msg').removeClass('success').addClass('sh-message error')
                    .text('Connection error. Please try again.').show();
            },
            complete: function() {
                $btn.prop('disabled', false);
                $btnTxt.show();
                $btnLoad.hide();
            }
        });
    });

    // ==================== TRACK ORDER ====================

    $('#track-form').on('submit', function(e) {
        e.preventDefault();
        var query = $('#track-input').val().trim();
        console.log('Tracking:', query);

        var $results = $('#track-results');

        if (!query) {
            $results.html('<div class="sh-message error">Please enter an order number or email</div>');
            return;
        }

        $results.html('<div class="sh-loading">Searching...</div>');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'sbha_track_order',
                query: query,
                nonce: nonce
            },
            success: function(res) {
                console.log('Track response:', res);
                if (res.success && res.data && res.data.orders && res.data.orders.length > 0) {
                    var html = '';
                    res.data.orders.forEach(function(o) {
                        html += renderOrder(o);
                    });
                    $results.html(html);
                } else {
                    $results.html('<div class="sh-message error">No orders found.</div>');
                }
            },
            error: function() {
                $results.html('<div class="sh-message error">Error searching. Please try again.</div>');
            }
        });
    });

    function renderOrder(o) {
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

        if (o.quote_status) {
            var bgColor = o.quote_status === 'approved' ? '#d1fae5' :
                          (o.quote_status === 'declined' ? '#fee2e2' : '#fef3c7');
            var borderColor = o.quote_status === 'approved' ? '#10b981' :
                              (o.quote_status === 'declined' ? '#ef4444' : '#f59e0b');
            var icon = o.quote_status === 'approved' ? '✅' :
                       (o.quote_status === 'declined' ? '❌' : '⏳');

            html += '<div style="margin:15px 0;padding:12px;border-radius:8px;background:' + bgColor + ';border-left:4px solid ' + borderColor + ';">';
            html += '<strong>' + icon + ' Quote: ' + o.quote_status_label + '</strong>';
            if (o.quote_response_note) {
                html += '<p style="margin-top:8px;font-size:14px;">' + o.quote_response_note + '</p>';
            }
            html += '</div>';
        }

        if (o.admin_response) {
            html += '<div class="sh-order-response"><h4>Response:</h4><p>' + o.admin_response + '</p></div>';
        }
        html += '</div>';
        return html;
    }

    // ==================== CONTACT FORM ====================

    $('#contact-form').on('submit', function(e) {
        e.preventDefault();
        console.log('Contact form submitted');

        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        var originalText = $btn.text();

        $btn.prop('disabled', true).text('Sending...');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: $form.serialize() + '&action=sbha_contact&nonce=' + nonce,
            success: function(res) {
                var $msg = $('#contact-msg');
                if (res.success) {
                    $msg.removeClass('error').addClass('sh-message success').text('Message sent!').show();
                    $form[0].reset();
                    showToast('success', 'Message sent successfully!');
                } else {
                    $msg.removeClass('success').addClass('sh-message error').text(res.data || 'Error').show();
                }
            },
            error: function() {
                $('#contact-msg').addClass('sh-message error').text('Connection error').show();
            },
            complete: function() {
                $btn.prop('disabled', false).text(originalText);
            }
        });
    });

    // ==================== AUTH MODAL ====================

    // Open modal
    $('.sh-login-btn, #login-btn').on('click', function(e) {
        e.preventDefault();
        console.log('Opening login modal');
        $('#auth-modal').addClass('active');
        $('body').css('overflow', 'hidden');
    });

    // Close modal
    $('.sh-modal-bg, .sh-modal-close').on('click', function(e) {
        e.preventDefault();
        $('.sh-modal').removeClass('active');
        $('body').css('overflow', '');
    });

    // Auth tabs
    $('.sh-auth-tab').on('click', function(e) {
        e.preventDefault();
        var tab = $(this).data('tab');
        $('.sh-auth-tab').removeClass('active');
        $(this).addClass('active');
        $('.sh-auth-form').removeClass('active');
        $('#' + tab + '-form').addClass('active');
    });

    // Login form
    $('#login-form').on('submit', function(e) {
        e.preventDefault();
        console.log('Login submitted');

        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        var $msg = $('#login-msg');

        $btn.prop('disabled', true).text('Logging in...');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: $form.serialize() + '&action=sbha_login&nonce=' + nonce,
            success: function(res) {
                console.log('Login response:', res);
                if (res.success) {
                    $msg.removeClass('error').addClass('sh-message success').text('Success! Reloading...').show();
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    $msg.removeClass('success').addClass('sh-message error').text(res.data || 'Invalid credentials').show();
                    $btn.prop('disabled', false).text('Login');
                }
            },
            error: function() {
                $msg.addClass('sh-message error').text('Connection error').show();
                $btn.prop('disabled', false).text('Login');
            }
        });
    });

    // Register form
    $('#register-form').on('submit', function(e) {
        e.preventDefault();
        console.log('Register submitted');

        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        var $msg = $('#register-msg');

        var pw = $form.find('[name="password"]').val();
        var pw2 = $form.find('[name="password_confirm"]').val();
        if (pw !== pw2) {
            $msg.addClass('sh-message error').text('Passwords do not match').show();
            return;
        }

        $btn.prop('disabled', true).text('Creating...');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: $form.serialize() + '&action=sbha_register&nonce=' + nonce,
            success: function(res) {
                console.log('Register response:', res);
                if (res.success) {
                    $msg.removeClass('error').addClass('sh-message success').text('Account created! Reloading...').show();
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    $msg.removeClass('success').addClass('sh-message error').text(res.data || 'Registration failed').show();
                    $btn.prop('disabled', false).text('Create Account');
                }
            },
            error: function() {
                $msg.addClass('sh-message error').text('Connection error').show();
                $btn.prop('disabled', false).text('Create Account');
            }
        });
    });

    // Reset password form
    $('#reset-form').on('submit', function(e) {
        e.preventDefault();
        console.log('Reset submitted');

        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        var $msg = $('#reset-msg');

        $btn.prop('disabled', true).text('Resetting...');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: $form.serialize() + '&action=sbha_reset_password&nonce=' + nonce,
            success: function(res) {
                if (res.success) {
                    $msg.removeClass('error').addClass('sh-message success').text('Password reset! Login now.').show();
                    $form[0].reset();
                    setTimeout(function() { $('.sh-auth-tab[data-tab="login"]').click(); }, 1500);
                } else {
                    $msg.removeClass('success').addClass('sh-message error').text(res.data || 'Reset failed').show();
                }
            },
            error: function() {
                $msg.addClass('sh-message error').text('Connection error').show();
            },
            complete: function() {
                $btn.prop('disabled', false).text('Reset Password');
            }
        });
    });

    // Logout
    $('.sh-logout, #logout-btn').on('click', function(e) {
        e.preventDefault();
        console.log('Logout clicked');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: { action: 'sbha_logout', nonce: nonce },
            success: function(res) {
                if (res.success) {
                    showToast('success', 'Logged out');
                    setTimeout(function() { location.reload(); }, 1000);
                }
            }
        });
    });

    // ==================== DROPDOWNS ====================

    // User menu
    $('.sh-user-btn, #user-menu-btn').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $('.sh-dropdown, #user-dropdown').toggleClass('active');
    });

    // Notifications
    $('.sh-notif-icon, #notif-btn').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $('.sh-notif-panel').toggleClass('active');
    });

    // Close on outside click
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.sh-user-menu').length) {
            $('.sh-dropdown, #user-dropdown').removeClass('active');
        }
        if (!$(e.target).closest('.sh-notif-panel, .sh-notif-icon, #notif-btn').length) {
            $('.sh-notif-panel').removeClass('active');
        }
    });

    // Dropdown links with panels
    $('.sh-dropdown a[data-panel], #user-dropdown a[data-panel]').on('click', function(e) {
        e.preventDefault();
        var panel = $(this).data('panel');
        if (panel) {
            switchPanel(panel);
            $('.sh-dropdown, #user-dropdown').removeClass('active');
        }
    });

    // ==================== NOTIFICATIONS ====================

    function loadNotifications() {
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: { action: 'sbha_get_notifications', nonce: nonce },
            success: function(res) {
                var $badge = $('#notif-badge, .sh-badge');
                if (res.success && res.data && res.data.notifications) {
                    var unread = 0;
                    res.data.notifications.forEach(function(n) {
                        if (!n.is_read) unread++;
                    });
                    if (unread > 0) {
                        $badge.text(unread).show();
                    } else {
                        $badge.hide();
                    }
                } else {
                    $badge.hide();
                }
            }
        });
    }

    // Load notifications on page load
    loadNotifications();

    // ==================== UTILITIES ====================

    function showToast(type, msg) {
        var $container = $('#sh-toasts');
        if (!$container.length) {
            $container = $('<div id="sh-toasts" class="sh-toasts"></div>');
            $('body').append($container);
        }

        var $toast = $('<div class="sh-toast sh-toast-' + type + '">' + msg + '</div>');
        $container.append($toast);

        setTimeout(function() {
            $toast.fadeOut(300, function() { $(this).remove(); });
        }, 4000);
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Initialize preview
    updateQuotePreview();

    console.log('SwitchHub: All event handlers bound successfully');
});
