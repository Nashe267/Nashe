<?php
/**
 * Admin Services
 *
 * Service management admin page
 *
 * @package SwitchBusinessHub
 */

if (!defined('ABSPATH')) {
    exit;
}

class SBHA_Admin_Services {

    /**
     * Render page
     */
    public function render() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        switch ($action) {
            case 'new':
            case 'edit':
                $this->render_form($id);
                break;
            default:
                $this->render_list();
        }
    }

    /**
     * Render services list
     */
    private function render_list() {
        $services = SBHA()->get_service_catalog()->get_services(array('status' => ''));
        $categories = SBHA()->get_service_catalog()->get_categories();
        ?>
        <div class="wrap sbha-admin-wrap">
            <h1 class="sbha-admin-title">
                Services
                <a href="<?php echo admin_url('admin.php?page=sbha-services&action=new'); ?>" class="page-title-action">Add New</a>
            </h1>

            <div class="sbha-services-filters">
                <select id="sbha-filter-category" class="sbha-select">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $slug => $name): ?>
                        <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($name); ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="sbha-filter-status" class="sbha-select">
                    <option value="">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="draft">Draft</option>
                </select>
            </div>

            <table class="wp-list-table widefat fixed striped sbha-table">
                <thead>
                    <tr>
                        <th width="30"></th>
                        <th>Service Name</th>
                        <th>Category</th>
                        <th>Base Price</th>
                        <th>Orders</th>
                        <th>Status</th>
                        <th width="150">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($services)): ?>
                        <tr>
                            <td colspan="7">No services found. <a href="<?php echo admin_url('admin.php?page=sbha-services&action=new'); ?>">Add your first service</a></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($services as $service): ?>
                            <tr data-id="<?php echo esc_attr($service['id']); ?>">
                                <td>
                                    <?php if ($service['is_featured']): ?>
                                        <span class="dashicons dashicons-star-filled" title="Featured" style="color:#FF6600;"></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><a href="<?php echo admin_url('admin.php?page=sbha-services&action=edit&id=' . $service['id']); ?>"><?php echo esc_html($service['name']); ?></a></strong>
                                    <?php if ($service['is_popular']): ?>
                                        <span class="sbha-badge">Popular</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html(isset($categories[$service['category']]) ? $categories[$service['category']] : $service['category']); ?></td>
                                <td>
                                    <?php echo esc_html(get_option('sbha_currency_symbol', '$')); ?><?php echo number_format($service['base_price'], 2); ?>
                                    <small>(<?php echo esc_html($service['price_type']); ?>)</small>
                                </td>
                                <td><?php echo esc_html($service['popularity_score']); ?></td>
                                <td>
                                    <span class="sbha-status-badge status-<?php echo esc_attr($service['status']); ?>"><?php echo esc_html(ucfirst($service['status'])); ?></span>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=sbha-services&action=edit&id=' . $service['id']); ?>" class="button button-small">Edit</a>
                                    <button class="button button-small sbha-duplicate-service" data-id="<?php echo esc_attr($service['id']); ?>">Duplicate</button>
                                    <button class="button button-small button-link-delete sbha-delete-service" data-id="<?php echo esc_attr($service['id']); ?>">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('.sbha-delete-service').on('click', function() {
                if (!confirm(sbhaAdmin.strings.confirm_delete)) return;

                var $btn = $(this);
                var id = $btn.data('id');

                $.ajax({
                    url: sbhaAdmin.ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'sbha_admin_action',
                        sbha_action: 'delete_service',
                        nonce: sbhaAdmin.nonce,
                        service_id: id
                    },
                    success: function(response) {
                        if (response.success) {
                            $btn.closest('tr').fadeOut(function() { $(this).remove(); });
                        } else {
                            alert(response.data || sbhaAdmin.strings.error);
                        }
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Render service form
     */
    private function render_form($id = 0) {
        $service = $id ? SBHA()->get_service_catalog()->get_service($id) : null;
        $categories = SBHA()->get_service_catalog()->get_categories();
        $is_new = !$service;

        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sbha_service_nonce'])) {
            if (wp_verify_nonce($_POST['sbha_service_nonce'], 'sbha_save_service')) {
                $data = array(
                    'name' => sanitize_text_field($_POST['name']),
                    'slug' => sanitize_title($_POST['slug']),
                    'category' => sanitize_text_field($_POST['category']),
                    'description' => wp_kses_post($_POST['description']),
                    'short_description' => sanitize_textarea_field($_POST['short_description']),
                    'base_price' => floatval($_POST['base_price']),
                    'price_type' => sanitize_text_field($_POST['price_type']),
                    'features' => isset($_POST['features']) ? array_map('sanitize_text_field', $_POST['features']) : array(),
                    'image_url' => esc_url($_POST['image_url']),
                    'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
                    'is_popular' => isset($_POST['is_popular']) ? 1 : 0,
                    'status' => sanitize_text_field($_POST['status'])
                );

                if ($is_new) {
                    $id = SBHA()->get_service_catalog()->create_service($data);
                    if ($id) {
                        wp_redirect(admin_url('admin.php?page=sbha-services&action=edit&id=' . $id . '&saved=1'));
                        exit;
                    }
                } else {
                    SBHA()->get_service_catalog()->update_service($id, $data);
                    wp_redirect(admin_url('admin.php?page=sbha-services&action=edit&id=' . $id . '&saved=1'));
                    exit;
                }
            }
        }
        ?>
        <div class="wrap sbha-admin-wrap">
            <h1 class="sbha-admin-title">
                <?php echo $is_new ? 'Add New Service' : 'Edit Service'; ?>
                <a href="<?php echo admin_url('admin.php?page=sbha-services'); ?>" class="page-title-action">Back to Services</a>
            </h1>

            <?php if (isset($_GET['saved'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p>Service saved successfully!</p>
                </div>
            <?php endif; ?>

            <form method="post" class="sbha-service-form">
                <?php wp_nonce_field('sbha_save_service', 'sbha_service_nonce'); ?>

                <div class="sbha-form-grid">
                    <div class="sbha-form-main">
                        <div class="sbha-form-section">
                            <h3>Basic Information</h3>

                            <div class="sbha-form-row">
                                <label for="name">Service Name *</label>
                                <input type="text" id="name" name="name" value="<?php echo esc_attr($service['name'] ?? ''); ?>" required>
                            </div>

                            <div class="sbha-form-row">
                                <label for="slug">URL Slug</label>
                                <input type="text" id="slug" name="slug" value="<?php echo esc_attr($service['slug'] ?? ''); ?>" placeholder="auto-generated-from-name">
                            </div>

                            <div class="sbha-form-row">
                                <label for="category">Category *</label>
                                <select id="category" name="category" required>
                                    <?php foreach ($categories as $slug => $name): ?>
                                        <option value="<?php echo esc_attr($slug); ?>" <?php selected($service['category'] ?? '', $slug); ?>><?php echo esc_html($name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="sbha-form-row">
                                <label for="short_description">Short Description</label>
                                <textarea id="short_description" name="short_description" rows="2"><?php echo esc_textarea($service['short_description'] ?? ''); ?></textarea>
                            </div>

                            <div class="sbha-form-row">
                                <label for="description">Full Description</label>
                                <?php
                                wp_editor(
                                    $service['description'] ?? '',
                                    'description',
                                    array(
                                        'media_buttons' => true,
                                        'textarea_rows' => 10,
                                        'teeny' => false
                                    )
                                );
                                ?>
                            </div>
                        </div>

                        <div class="sbha-form-section">
                            <h3>Pricing</h3>

                            <div class="sbha-form-row-inline">
                                <div class="sbha-form-row">
                                    <label for="base_price">Base Price</label>
                                    <div class="sbha-input-group">
                                        <span class="sbha-input-prefix"><?php echo esc_html(get_option('sbha_currency_symbol', '$')); ?></span>
                                        <input type="number" id="base_price" name="base_price" value="<?php echo esc_attr($service['base_price'] ?? 0); ?>" step="0.01" min="0">
                                    </div>
                                </div>

                                <div class="sbha-form-row">
                                    <label for="price_type">Price Type</label>
                                    <select id="price_type" name="price_type">
                                        <option value="fixed" <?php selected($service['price_type'] ?? '', 'fixed'); ?>>Fixed Price</option>
                                        <option value="starting_from" <?php selected($service['price_type'] ?? '', 'starting_from'); ?>>Starting From</option>
                                        <option value="hourly" <?php selected($service['price_type'] ?? '', 'hourly'); ?>>Hourly Rate</option>
                                        <option value="custom" <?php selected($service['price_type'] ?? '', 'custom'); ?>>Custom Quote</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="sbha-form-section">
                            <h3>Features</h3>
                            <div id="sbha-features-list">
                                <?php
                                $features = $service['features'] ?? array();
                                if (empty($features)) $features = array('');
                                foreach ($features as $i => $feature):
                                ?>
                                    <div class="sbha-feature-row">
                                        <input type="text" name="features[]" value="<?php echo esc_attr($feature); ?>" placeholder="Feature description">
                                        <button type="button" class="button sbha-remove-feature">&times;</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" id="sbha-add-feature" class="button">+ Add Feature</button>
                        </div>
                    </div>

                    <div class="sbha-form-sidebar">
                        <div class="sbha-form-section">
                            <h3>Status</h3>
                            <select id="status" name="status">
                                <option value="active" <?php selected($service['status'] ?? 'active', 'active'); ?>>Active</option>
                                <option value="inactive" <?php selected($service['status'] ?? '', 'inactive'); ?>>Inactive</option>
                                <option value="draft" <?php selected($service['status'] ?? '', 'draft'); ?>>Draft</option>
                            </select>
                        </div>

                        <div class="sbha-form-section">
                            <h3>Visibility</h3>
                            <label class="sbha-checkbox">
                                <input type="checkbox" name="is_featured" value="1" <?php checked($service['is_featured'] ?? 0, 1); ?>>
                                Featured Service
                            </label>
                            <label class="sbha-checkbox">
                                <input type="checkbox" name="is_popular" value="1" <?php checked($service['is_popular'] ?? 0, 1); ?>>
                                Popular Service
                            </label>
                        </div>

                        <div class="sbha-form-section">
                            <h3>Service Image</h3>
                            <div class="sbha-image-upload">
                                <input type="hidden" id="image_url" name="image_url" value="<?php echo esc_url($service['image_url'] ?? ''); ?>">
                                <div id="sbha-image-preview">
                                    <?php if (!empty($service['image_url'])): ?>
                                        <img src="<?php echo esc_url($service['image_url']); ?>" alt="">
                                    <?php endif; ?>
                                </div>
                                <button type="button" id="sbha-upload-image" class="button">Select Image</button>
                                <button type="button" id="sbha-remove-image" class="button" <?php echo empty($service['image_url']) ? 'style="display:none;"' : ''; ?>>Remove</button>
                            </div>
                        </div>

                        <?php if (!$is_new): ?>
                        <div class="sbha-form-section">
                            <h3>Statistics</h3>
                            <p><strong>Orders:</strong> <?php echo esc_html($service['popularity_score']); ?></p>
                            <p><strong>Conversion:</strong> <?php echo esc_html($service['conversion_rate']); ?>%</p>
                        </div>
                        <?php endif; ?>

                        <div class="sbha-form-section">
                            <button type="submit" class="button button-primary button-large">
                                <?php echo $is_new ? 'Create Service' : 'Update Service'; ?>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Add feature
            $('#sbha-add-feature').on('click', function() {
                $('#sbha-features-list').append(
                    '<div class="sbha-feature-row">' +
                    '<input type="text" name="features[]" placeholder="Feature description">' +
                    '<button type="button" class="button sbha-remove-feature">&times;</button>' +
                    '</div>'
                );
            });

            // Remove feature
            $(document).on('click', '.sbha-remove-feature', function() {
                $(this).parent().remove();
            });

            // Image upload
            var mediaUploader;
            $('#sbha-upload-image').on('click', function(e) {
                e.preventDefault();
                if (mediaUploader) {
                    mediaUploader.open();
                    return;
                }
                mediaUploader = wp.media({
                    title: 'Select Service Image',
                    button: { text: 'Use this image' },
                    multiple: false
                });
                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('#image_url').val(attachment.url);
                    $('#sbha-image-preview').html('<img src="' + attachment.url + '" alt="">');
                    $('#sbha-remove-image').show();
                });
                mediaUploader.open();
            });

            $('#sbha-remove-image').on('click', function() {
                $('#image_url').val('');
                $('#sbha-image-preview').empty();
                $(this).hide();
            });
        });
        </script>
        <?php
    }
}
