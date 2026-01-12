<?php
/**
 * AJAX Handler Class
 *
 * All frontend AJAX operations
 *
 * @package SwitchBusinessHub
 */

if (!defined('ABSPATH')) {
    exit;
}

class SBHA_Ajax {

    public function __construct() {
        // Customer auth (no WordPress users)
        add_action('wp_ajax_nopriv_sbha_register', array($this, 'register'));
        add_action('wp_ajax_nopriv_sbha_login', array($this, 'login'));
        add_action('wp_ajax_sbha_login', array($this, 'login'));
        add_action('wp_ajax_sbha_logout', array($this, 'logout'));
        add_action('wp_ajax_nopriv_sbha_logout', array($this, 'logout'));
        add_action('wp_ajax_nopriv_sbha_reset_password', array($this, 'reset_password'));

        // Quote/Order
        add_action('wp_ajax_sbha_submit_quote', array($this, 'submit_quote'));
        add_action('wp_ajax_nopriv_sbha_submit_quote', array($this, 'submit_quote'));

        // Order tracking
        add_action('wp_ajax_sbha_track_order', array($this, 'track_order'));
        add_action('wp_ajax_nopriv_sbha_track_order', array($this, 'track_order'));

        // Customer orders
        add_action('wp_ajax_sbha_get_my_orders', array($this, 'get_my_orders'));
        add_action('wp_ajax_nopriv_sbha_get_my_orders', array($this, 'get_my_orders'));

        // Documents
        add_action('wp_ajax_sbha_get_documents', array($this, 'get_documents'));
        add_action('wp_ajax_nopriv_sbha_get_documents', array($this, 'get_documents'));

        // Contact
        add_action('wp_ajax_sbha_contact', array($this, 'contact'));
        add_action('wp_ajax_nopriv_sbha_contact', array($this, 'contact'));

        // Notifications
        add_action('wp_ajax_sbha_get_notifications', array($this, 'get_notifications'));
        add_action('wp_ajax_sbha_mark_read', array($this, 'mark_notification_read'));

        // Session check
        add_action('wp_ajax_sbha_check_session', array($this, 'check_session'));
        add_action('wp_ajax_nopriv_sbha_check_session', array($this, 'check_session'));

        // Services
        add_action('wp_ajax_sbha_get_services', array($this, 'get_services'));
        add_action('wp_ajax_nopriv_sbha_get_services', array($this, 'get_services'));

        // Admin: Approve/Decline quotes
        add_action('wp_ajax_sbha_approve_quote', array($this, 'approve_quote'));
        add_action('wp_ajax_sbha_decline_quote', array($this, 'decline_quote'));

        // AI Chat with Gemini
        add_action('wp_ajax_sbha_ai_chat', array($this, 'ai_chat'));
        add_action('wp_ajax_nopriv_sbha_ai_chat', array($this, 'ai_chat'));
    }

    /**
     * Register customer
     */
    public function register() {
        global $wpdb;

        $first_name = sanitize_text_field($_POST['first_name'] ?? '');
        $last_name = sanitize_text_field($_POST['last_name'] ?? '');
        $business_name = sanitize_text_field($_POST['business_name'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $cell_number = sanitize_text_field($_POST['cell_number'] ?? '');
        $whatsapp_number = sanitize_text_field($_POST['whatsapp_number'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($first_name) || empty($last_name) || empty($email) || empty($cell_number) || empty($password)) {
            wp_send_json_error('Please fill in all required fields.');
        }

        if (!is_email($email)) {
            wp_send_json_error('Please enter a valid email address.');
        }

        if (strlen($password) < 6) {
            wp_send_json_error('Password must be at least 6 characters.');
        }

        $table = $wpdb->prefix . 'sbha_customers';
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE email = %s", $email));

        if ($exists) {
            wp_send_json_error('Email already registered. Please login.');
        }

        $result = $wpdb->insert($table, array(
            'first_name' => $first_name,
            'last_name' => $last_name,
            'business_name' => $business_name,
            'email' => $email,
            'cell_number' => $cell_number,
            'whatsapp_number' => $whatsapp_number ?: $cell_number,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'status' => 'active'
        ));

        if (!$result) {
            wp_send_json_error('Registration failed. Please try again.');
        }

        $customer_id = $wpdb->insert_id;
        $token = $this->create_session($customer_id);

        setcookie('sbha_token', $token, time() + (30 * 24 * 60 * 60), COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);

        wp_send_json_success(array(
            'message' => 'Account created!',
            'customer' => array(
                'id' => $customer_id,
                'name' => $first_name . ' ' . $last_name,
                'email' => $email
            ),
            'token' => $token
        ));
    }

    /**
     * Login
     */
    public function login() {
        global $wpdb;

        $email = sanitize_email($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            wp_send_json_error('Enter email and password.');
        }

        $table = $wpdb->prefix . 'sbha_customers';
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE email = %s AND status = 'active'", $email
        ), ARRAY_A);

        if (!$customer || !password_verify($password, $customer['password'])) {
            wp_send_json_error('Invalid email or password.');
        }

        $wpdb->update($table, array('last_login' => current_time('mysql')), array('id' => $customer['id']));

        $token = $this->create_session($customer['id']);
        setcookie('sbha_token', $token, time() + (30 * 24 * 60 * 60), COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);

        wp_send_json_success(array(
            'message' => 'Welcome back!',
            'customer' => array(
                'id' => $customer['id'],
                'name' => $customer['first_name'] . ' ' . $customer['last_name'],
                'email' => $customer['email']
            ),
            'token' => $token
        ));
    }

    /**
     * Logout
     */
    public function logout() {
        global $wpdb;
        $token = $_COOKIE['sbha_token'] ?? '';
        if ($token) {
            $wpdb->delete($wpdb->prefix . 'sbha_sessions', array('session_token' => $token));
        }
        setcookie('sbha_token', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
        wp_send_json_success(array('message' => 'Logged out.'));
    }

    /**
     * Reset password (just email + new password)
     */
    public function reset_password() {
        global $wpdb;

        $email = sanitize_email($_POST['email'] ?? '');
        $new_password = $_POST['new_password'] ?? '';

        if (empty($email) || empty($new_password)) {
            wp_send_json_error('Enter email and new password.');
        }

        if (strlen($new_password) < 6) {
            wp_send_json_error('Password must be at least 6 characters.');
        }

        $table = $wpdb->prefix . 'sbha_customers';
        $customer = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table WHERE email = %s", $email));

        if (!$customer) {
            wp_send_json_error('Email not found.');
        }

        $wpdb->update($table, array('password' => password_hash($new_password, PASSWORD_DEFAULT)), array('id' => $customer->id));

        wp_send_json_success(array('message' => 'Password updated! You can now login.'));
    }

    /**
     * Submit quote/order
     */
    public function submit_quote() {
        global $wpdb;

        $customer_id = $this->get_customer_id();

        // Create customer from form if not logged in
        if (!$customer_id) {
            $email = sanitize_email($_POST['customer_email'] ?? '');
            $name = sanitize_text_field($_POST['customer_name'] ?? '');
            $phone = sanitize_text_field($_POST['customer_phone'] ?? '');

            if (empty($email) || empty($name) || empty($phone)) {
                wp_send_json_error('Please fill in your details.');
            }

            $table = $wpdb->prefix . 'sbha_customers';
            $existing = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table WHERE email = %s", $email));

            if ($existing) {
                $customer_id = $existing->id;
            } else {
                $parts = explode(' ', $name, 2);
                $wpdb->insert($table, array(
                    'first_name' => $parts[0],
                    'last_name' => $parts[1] ?? '',
                    'email' => $email,
                    'cell_number' => $phone,
                    'whatsapp_number' => $phone,
                    'password' => password_hash(wp_generate_password(12), PASSWORD_DEFAULT),
                    'status' => 'active'
                ));
                $customer_id = $wpdb->insert_id;
            }
        }

        $service_id = intval($_POST['service_type'] ?? 0);
        $custom_service = sanitize_text_field($_POST['custom_service'] ?? '');
        $description = sanitize_textarea_field($_POST['description'] ?? '');
        $quantity = max(1, intval($_POST['quantity'] ?? 1));
        $urgency = sanitize_text_field($_POST['urgency'] ?? 'standard');
        $title = sanitize_text_field($_POST['project_title'] ?? '');
        $client_budget = !empty($_POST['client_budget']) ? floatval($_POST['client_budget']) : null;
        $budget_notes = sanitize_textarea_field($_POST['budget_notes'] ?? '');

        if (!$service_id && empty($custom_service) && empty($title)) {
            wp_send_json_error('Please select a service or describe your request.');
        }

        $service_name = $custom_service ?: 'Custom Request';
        $unit_price = 0;

        if ($service_id) {
            $service = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}sbha_services WHERE id = %d", $service_id
            ));
            if ($service) {
                $unit_price = floatval($service->base_price);
                $service_name = $service->name;
            }
        }

        $mult = $urgency === 'express' ? 1.25 : ($urgency === 'rush' ? 1.5 : 1);
        $total = $unit_price * $quantity * $mult;

        // Generate order number
        $prefix = get_option('sbha_order_prefix', 'SWH');
        $year = date('Y');
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sbha_orders") + 1;
        $order_number = $prefix . '-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

        // Handle files
        $files = array();
        if (!empty($_FILES['files'])) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');

            $uploaded = $_FILES['files'];
            $count = is_array($uploaded['name']) ? count($uploaded['name']) : 1;

            for ($i = 0; $i < $count; $i++) {
                $file = is_array($uploaded['name']) ? array(
                    'name' => $uploaded['name'][$i],
                    'type' => $uploaded['type'][$i],
                    'tmp_name' => $uploaded['tmp_name'][$i],
                    'error' => $uploaded['error'][$i],
                    'size' => $uploaded['size'][$i]
                ) : $uploaded;

                if ($file['error'] === UPLOAD_ERR_OK) {
                    $_FILES['upload'] = $file;
                    $att_id = media_handle_upload('upload', 0);
                    if (!is_wp_error($att_id)) {
                        $files[] = array('id' => $att_id, 'url' => wp_get_attachment_url($att_id), 'name' => $file['name']);
                    }
                }
            }
        }

        $wpdb->insert($wpdb->prefix . 'sbha_orders', array(
            'order_number' => $order_number,
            'customer_id' => $customer_id,
            'service_id' => $service_id ?: null,
            'custom_service' => $custom_service,
            'title' => $title ?: $service_name,
            'description' => $description,
            'quantity' => $quantity,
            'urgency' => $urgency,
            'unit_price' => $unit_price,
            'total' => $total,
            'client_budget' => $client_budget,
            'budget_notes' => $budget_notes,
            'files' => json_encode($files),
            'status' => 'pending',
            'quote_status' => 'pending'
        ));

        $order_id = $wpdb->insert_id;

        // Notify
        $this->notify($customer_id, 'order', 'Request Submitted', "Your request #{$order_number} is received!");

        // Email admin
        $customer = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sbha_customers WHERE id = %d", $customer_id));
        $admin_email = get_option('sbha_business_email', get_option('admin_email'));

        $budget_info = $client_budget ? "\nClient Budget: R" . number_format($client_budget, 2) : '';
        $budget_info .= $budget_notes ? "\nBudget Notes: {$budget_notes}" : '';

        wp_mail($admin_email, "New Order: {$order_number}",
            "Order: {$order_number}\nCustomer: {$customer->first_name} {$customer->last_name}\n" .
            "Email: {$customer->email}\nPhone: {$customer->cell_number}\n" .
            "Service: {$service_name}\nOur Quote: R" . number_format($total, 2) . $budget_info .
            "\n\nPlease review and approve/decline in admin panel."
        );

        wp_send_json_success(array(
            'message' => 'Request submitted!',
            'order_number' => $order_number,
            'total' => 'R' . number_format($total, 2)
        ));
    }

    /**
     * Track order
     */
    public function track_order() {
        global $wpdb;

        $query = sanitize_text_field($_POST['query'] ?? '');
        if (empty($query)) {
            wp_send_json_error('Enter order number or email.');
        }

        $is_email = strpos($query, '@') !== false;

        if ($is_email) {
            $orders = $wpdb->get_results($wpdb->prepare("
                SELECT o.*, c.first_name, c.last_name, s.name as service_name
                FROM {$wpdb->prefix}sbha_orders o
                JOIN {$wpdb->prefix}sbha_customers c ON o.customer_id = c.id
                LEFT JOIN {$wpdb->prefix}sbha_services s ON o.service_id = s.id
                WHERE c.email = %s ORDER BY o.created_at DESC LIMIT 10
            ", $query), ARRAY_A);
        } else {
            $orders = $wpdb->get_results($wpdb->prepare("
                SELECT o.*, c.first_name, c.last_name, s.name as service_name
                FROM {$wpdb->prefix}sbha_orders o
                JOIN {$wpdb->prefix}sbha_customers c ON o.customer_id = c.id
                LEFT JOIN {$wpdb->prefix}sbha_services s ON o.service_id = s.id
                WHERE o.order_number = %s
            ", $query), ARRAY_A);
        }

        $result = array();
        $biz = get_option('sbha_business_name', 'Switch Hub');

        foreach ($orders as $o) {
            // Mark response as viewed
            if ($o['admin_response'] && !$o['customer_viewed_response']) {
                $wpdb->update($wpdb->prefix . 'sbha_orders', array('customer_viewed_response' => 1), array('id' => $o['id']));
            }

            $quote_status_labels = array(
                'pending' => 'Awaiting Review',
                'approved' => 'Approved',
                'declined' => 'Declined'
            );

            $result[] = array(
                'order_number' => $o['order_number'],
                'service_name' => $o['service_name'] ?: $o['custom_service'] ?: $o['title'],
                'status' => $o['status'],
                'status_label' => ucfirst(str_replace('_', ' ', $o['status'])),
                'quote_status' => $o['quote_status'] ?? 'pending',
                'quote_status_label' => $quote_status_labels[$o['quote_status'] ?? 'pending'],
                'quote_response_note' => $o['quote_response_note'] ?? '',
                'total' => 'R' . number_format($o['total'], 2),
                'client_budget' => $o['client_budget'] ? 'R' . number_format($o['client_budget'], 2) : null,
                'created_date' => date('d M Y', strtotime($o['created_at'])),
                'estimated_completion' => $o['estimated_completion'] ? date('d M Y', strtotime($o['estimated_completion'])) : null,
                'admin_response' => $o['admin_response'],
                'has_new_response' => $o['admin_response'] && !$o['customer_viewed_response'],
                'invoice_url' => $o['invoice_pdf_url'],
                'quote_url' => $o['quote_pdf_url'],
                'business_name' => $biz
            );
        }

        wp_send_json_success(array('orders' => $result));
    }

    /**
     * Get customer's orders
     */
    public function get_my_orders() {
        global $wpdb;

        $customer_id = $this->get_customer_id();
        if (!$customer_id) {
            wp_send_json_success(array('orders' => array()));
        }

        $orders = $wpdb->get_results($wpdb->prepare("
            SELECT o.*, s.name as service_name
            FROM {$wpdb->prefix}sbha_orders o
            LEFT JOIN {$wpdb->prefix}sbha_services s ON o.service_id = s.id
            WHERE o.customer_id = %d ORDER BY o.created_at DESC LIMIT 20
        ", $customer_id), ARRAY_A);

        $result = array();
        $biz = get_option('sbha_business_name', 'Switch Hub');

        foreach ($orders as $o) {
            $result[] = array(
                'order_number' => $o['order_number'],
                'service_name' => $o['service_name'] ?: $o['custom_service'] ?: $o['title'],
                'status' => $o['status'],
                'status_label' => ucfirst(str_replace('_', ' ', $o['status'])),
                'total' => 'R' . number_format($o['total'], 2),
                'created_date' => date('d M Y', strtotime($o['created_at'])),
                'admin_response' => $o['admin_response'],
                'has_new_response' => $o['admin_response'] && !$o['customer_viewed_response'],
                'invoice_url' => $o['invoice_pdf_url'],
                'business_name' => $biz
            );
        }

        wp_send_json_success(array('orders' => $result));
    }

    /**
     * Get documents
     */
    public function get_documents() {
        global $wpdb;

        $email = sanitize_email($_POST['email'] ?? '');
        $customer_id = $this->get_customer_id();

        if (!$customer_id && $email) {
            $c = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$wpdb->prefix}sbha_customers WHERE email = %s", $email));
            $customer_id = $c ? $c->id : null;
        }

        if (!$customer_id) {
            wp_send_json_success(array('documents' => array()));
        }

        $quotes = $wpdb->get_results($wpdb->prepare("
            SELECT q.*, o.title as service FROM {$wpdb->prefix}sbha_quotes q
            JOIN {$wpdb->prefix}sbha_orders o ON q.order_id = o.id
            WHERE q.customer_id = %d ORDER BY q.created_at DESC
        ", $customer_id), ARRAY_A);

        $invoices = $wpdb->get_results($wpdb->prepare("
            SELECT i.*, o.title as service FROM {$wpdb->prefix}sbha_invoices i
            JOIN {$wpdb->prefix}sbha_orders o ON i.order_id = o.id
            WHERE i.customer_id = %d ORDER BY i.created_at DESC
        ", $customer_id), ARRAY_A);

        $docs = array();
        foreach ($quotes as $q) {
            $docs[] = array('type' => 'Quote', 'number' => $q['quote_number'], 'service' => $q['service'],
                'total' => 'R' . number_format($q['total'], 2), 'date' => date('d M Y', strtotime($q['created_at'])),
                'status' => $q['status'], 'pdf_url' => $q['pdf_url']);
        }
        foreach ($invoices as $i) {
            $docs[] = array('type' => 'Invoice', 'number' => $i['invoice_number'], 'service' => $i['service'],
                'total' => 'R' . number_format($i['total'], 2), 'date' => date('d M Y', strtotime($i['created_at'])),
                'status' => $i['status'], 'pdf_url' => $i['pdf_url']);
        }

        wp_send_json_success(array('documents' => $docs));
    }

    /**
     * Contact
     */
    public function contact() {
        global $wpdb;

        $name = sanitize_text_field($_POST['name'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $message = sanitize_textarea_field($_POST['message'] ?? '');

        if (empty($name) || empty($email) || empty($message)) {
            wp_send_json_error('Fill in all required fields.');
        }

        $wpdb->insert($wpdb->prefix . 'sbha_messages', array(
            'customer_id' => $this->get_customer_id(),
            'name' => $name, 'email' => $email, 'phone' => $phone, 'message' => $message
        ));

        wp_mail(get_option('sbha_business_email', get_option('admin_email')),
            "Message from {$name}", "Name: {$name}\nEmail: {$email}\nPhone: {$phone}\n\n{$message}",
            array("Reply-To: {$email}"));

        wp_send_json_success(array('message' => 'Message sent!'));
    }

    /**
     * Get notifications
     */
    public function get_notifications() {
        global $wpdb;
        $customer_id = $this->get_customer_id();
        if (!$customer_id) wp_send_json_success(array('notifications' => array(), 'unread' => 0));

        $notifs = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}sbha_notifications
            WHERE customer_id = %d ORDER BY created_at DESC LIMIT 20
        ", $customer_id), ARRAY_A);

        $unread = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}sbha_notifications WHERE customer_id = %d AND is_read = 0
        ", $customer_id));

        wp_send_json_success(array('notifications' => $notifs, 'unread' => intval($unread)));
    }

    /**
     * Mark notification read
     */
    public function mark_notification_read() {
        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        $customer_id = $this->get_customer_id();
        if ($id && $customer_id) {
            $wpdb->update($wpdb->prefix . 'sbha_notifications', array('is_read' => 1),
                array('id' => $id, 'customer_id' => $customer_id));
        }
        wp_send_json_success();
    }

    /**
     * Check session
     */
    public function check_session() {
        $customer = $this->get_customer();
        if ($customer) {
            wp_send_json_success(array('logged_in' => true, 'customer' => array(
                'id' => $customer['id'],
                'name' => $customer['first_name'] . ' ' . $customer['last_name'],
                'email' => $customer['email']
            )));
        }
        wp_send_json_success(array('logged_in' => false));
    }

    /**
     * Get services
     */
    public function get_services() {
        global $wpdb;
        $category = sanitize_text_field($_POST['category'] ?? 'all');

        $where = "status = 'active'";
        if ($category !== 'all') {
            $where .= $wpdb->prepare(" AND category = %s", $category);
        }

        $services = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sbha_services WHERE {$where} ORDER BY display_order ASC");
        wp_send_json_success(array('services' => $services));
    }

    // Helpers
    private function create_session($customer_id) {
        global $wpdb;
        $token = bin2hex(random_bytes(32));
        $wpdb->insert($wpdb->prefix . 'sbha_sessions', array(
            'customer_id' => $customer_id,
            'session_token' => $token,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'expires_at' => date('Y-m-d H:i:s', time() + 2592000)
        ));
        return $token;
    }

    private function get_customer_id() {
        $c = $this->get_customer();
        return $c ? $c['id'] : null;
    }

    private function get_customer() {
        global $wpdb;
        $token = $_COOKIE['sbha_token'] ?? ($_POST['token'] ?? '');
        if (!$token) return null;

        $sess = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}sbha_sessions WHERE session_token = %s AND expires_at > NOW()
        ", $token), ARRAY_A);
        if (!$sess) return null;

        return $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}sbha_customers WHERE id = %d AND status = 'active'
        ", $sess['customer_id']), ARRAY_A);
    }

    private function notify($customer_id, $type, $title, $message, $link = '') {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'sbha_notifications', array(
            'customer_id' => $customer_id, 'type' => $type, 'title' => $title, 'message' => $message, 'link' => $link
        ));
    }

    /**
     * Approve quote (Admin only)
     */
    public function approve_quote() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        global $wpdb;
        $order_id = intval($_POST['order_id'] ?? 0);
        $note = sanitize_textarea_field($_POST['note'] ?? '');

        if (!$order_id) {
            wp_send_json_error('Invalid order.');
        }

        $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sbha_orders WHERE id = %d", $order_id), ARRAY_A);
        if (!$order) {
            wp_send_json_error('Order not found.');
        }

        $wpdb->update($wpdb->prefix . 'sbha_orders', array(
            'quote_status' => 'approved',
            'quote_response_note' => $note,
            'quote_responded_at' => current_time('mysql'),
            'status' => 'confirmed'
        ), array('id' => $order_id));

        // Notify customer
        $this->notify(
            $order['customer_id'],
            'quote_approved',
            'Quote Approved!',
            "Great news! Your quote #{$order['order_number']} has been approved." . ($note ? " Note: {$note}" : '')
        );

        // Email customer
        $customer = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sbha_customers WHERE id = %d", $order['customer_id']));
        if ($customer) {
            $biz = get_option('sbha_business_name', 'Switch Hub');
            wp_mail($customer->email, "Quote Approved - {$order['order_number']}",
                "Hi {$customer->first_name},\n\nGreat news! Your quote #{$order['order_number']} has been APPROVED.\n\n" .
                ($note ? "Note from {$biz}: {$note}\n\n" : "") .
                "We will begin working on your order shortly.\n\nThank you!\n{$biz}"
            );
        }

        wp_send_json_success(array('message' => 'Quote approved!'));
    }

    /**
     * Decline quote (Admin only)
     */
    public function decline_quote() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        global $wpdb;
        $order_id = intval($_POST['order_id'] ?? 0);
        $note = sanitize_textarea_field($_POST['note'] ?? '');

        if (!$order_id) {
            wp_send_json_error('Invalid order.');
        }

        $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sbha_orders WHERE id = %d", $order_id), ARRAY_A);
        if (!$order) {
            wp_send_json_error('Order not found.');
        }

        $wpdb->update($wpdb->prefix . 'sbha_orders', array(
            'quote_status' => 'declined',
            'quote_response_note' => $note,
            'quote_responded_at' => current_time('mysql'),
            'status' => 'cancelled'
        ), array('id' => $order_id));

        // Notify customer
        $this->notify(
            $order['customer_id'],
            'quote_declined',
            'Quote Update',
            "Your quote #{$order['order_number']} could not be approved at this time." . ($note ? " Note: {$note}" : '')
        );

        // Email customer
        $customer = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sbha_customers WHERE id = %d", $order['customer_id']));
        if ($customer) {
            $biz = get_option('sbha_business_name', 'Switch Hub');
            wp_mail($customer->email, "Quote Update - {$order['order_number']}",
                "Hi {$customer->first_name},\n\nWe've reviewed your quote #{$order['order_number']}.\n\n" .
                "Unfortunately, we're unable to proceed with this request at this time.\n\n" .
                ($note ? "Note: {$note}\n\n" : "") .
                "Please feel free to submit a new request or contact us to discuss alternatives.\n\nThank you!\n{$biz}"
            );
        }

        wp_send_json_success(array('message' => 'Quote declined.'));
    }

    /**
     * AI Chat using Google Gemini
     */
    public function ai_chat() {
        $message = sanitize_text_field($_POST['message'] ?? '');
        if (empty($message)) {
            wp_send_json_error('Please enter a message.');
        }

        $api_key = get_option('sbha_gemini_api_key', '');
        if (empty($api_key)) {
            // Fallback to basic responses if no API key
            wp_send_json_success(array('response' => $this->basic_ai_response($message)));
            return;
        }

        // Get services for context
        global $wpdb;
        $services = $wpdb->get_results("SELECT name, category, base_price, short_description FROM {$wpdb->prefix}sbha_services WHERE status = 'active' LIMIT 20", ARRAY_A);
        $service_list = '';
        foreach ($services as $s) {
            $service_list .= "- {$s['name']} ({$s['category']}): R{$s['base_price']} - {$s['short_description']}\n";
        }

        $biz = get_option('sbha_business_name', 'Switch Hub');
        $phone = get_option('sbha_business_phone', '');
        $whatsapp = get_option('sbha_whatsapp', '');

        $system_prompt = "You are Switch, a friendly AI assistant for {$biz}. You help customers with graphics design, printing, web services, branding, and architectural drawings. Be helpful, professional, and concise. Keep responses under 100 words.

Our services:
{$service_list}

Contact: Phone: {$phone}, WhatsApp: {$whatsapp}

If customers want to order, tell them to use the Quote form or click 'Get Quote' on any service. If they want to track an order, tell them to use the Track panel.";

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $api_key;

        $body = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array('text' => $system_prompt . "\n\nCustomer: " . $message . "\n\nSwitch:")
                    )
                )
            ),
            'generationConfig' => array(
                'temperature' => 0.7,
                'maxOutputTokens' => 200,
                'topP' => 0.9
            )
        );

        $response = wp_remote_post($url, array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode($body),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            wp_send_json_success(array('response' => $this->basic_ai_response($message)));
            return;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['candidates'][0]['content']['parts'][0]['text'])) {
            $ai_response = $body['candidates'][0]['content']['parts'][0]['text'];
            wp_send_json_success(array('response' => $ai_response));
        } else {
            wp_send_json_success(array('response' => $this->basic_ai_response($message)));
        }
    }

    /**
     * Basic AI response fallback
     */
    private function basic_ai_response($message) {
        $msg = strtolower($message);
        $biz = get_option('sbha_business_name', 'Switch Hub');

        if (strpos($msg, 'track') !== false || strpos($msg, 'order') !== false || strpos($msg, 'status') !== false) {
            return "I can help you track your order! Use the Track panel below to enter your order number or email.";
        }
        if (strpos($msg, 'quote') !== false || strpos($msg, 'price') !== false || strpos($msg, 'cost') !== false || strpos($msg, 'how much') !== false) {
            return "I'd love to get you a quote! Click on the Quote tab below or click 'Get Quote' on any service you're interested in.";
        }
        if (strpos($msg, 'contact') !== false || strpos($msg, 'call') !== false || strpos($msg, 'phone') !== false || strpos($msg, 'whatsapp') !== false) {
            return "You can reach us through the Contact panel. We're always happy to chat!";
        }
        if (strpos($msg, 'logo') !== false || strpos($msg, 'brand') !== false) {
            return "We create stunning logos and complete brand identities! Check out our Logo Design and Brand Identity services, or get a custom quote.";
        }
        if (strpos($msg, 'website') !== false || strpos($msg, 'web') !== false) {
            return "We build beautiful, modern websites! From simple landing pages to full e-commerce stores. Check our Web Services or request a quote.";
        }
        if (strpos($msg, 'print') !== false || strpos($msg, 'card') !== false || strpos($msg, 'flyer') !== false || strpos($msg, 'banner') !== false) {
            return "We offer high-quality printing services including business cards, flyers, banners, and more! Browse our Printing services or get a quote.";
        }
        if (strpos($msg, 'architect') !== false || strpos($msg, 'building') !== false || strpos($msg, 'plan') !== false || strpos($msg, 'floor') !== false) {
            return "We provide professional architectural drawings, floor plans, and 3D renderings. Check our Architecture services!";
        }
        if (strpos($msg, 'hello') !== false || strpos($msg, 'hi') !== false || strpos($msg, 'hey') !== false) {
            return "Hello! Welcome to {$biz}! I'm Switch, your AI assistant. How can I help you today? Need a quote, want to track an order, or looking for a specific service?";
        }

        return "I'd be happy to help with that! For custom requests like yours, I recommend filling out our Quote form - just click the Quote tab below. Our team will get back to you quickly with pricing and options!";
    }
}

new SBHA_Ajax();
