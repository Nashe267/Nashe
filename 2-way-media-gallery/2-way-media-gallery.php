<?php
/*
Plugin Name: 2 Way Media Gallery
Description: Upload and manage galleries with public links and folder structure.
Version: 1.0.0
Author: 2 Way Media
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

define( 'TWMG_DIR', plugin_dir_path( __FILE__ ) );
define( 'TWMG_URL', plugin_dir_url( __FILE__ ) );

class Two_Way_Media_Gallery {

    public function __construct() {
        add_shortcode( 'gallery_uploader', [ $this, 'render_gallery_page' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_twmg_upload', [ $this, 'handle_upload' ] );
        add_action( 'wp_ajax_nopriv_twmg_upload', [ $this, 'handle_upload' ] );
        add_action( 'wp_ajax_twmg_list_galleries', [ $this, 'ajax_list_galleries' ] );
        add_action( 'wp_ajax_twmg_get_gallery', [ $this, 'ajax_get_gallery' ] );
        add_action( 'wp_ajax_twmg_delete_gallery', [ $this, 'ajax_delete_gallery' ] );
        add_action( 'wp_ajax_twmg_regenerate_link', [ $this, 'ajax_regenerate_link' ] );
    }

    public function enqueue_assets() {
        wp_enqueue_style( 'twmg-style', TWMG_URL . 'css/gallery-uploader.css' );
        wp_enqueue_script( 'twmg-script', TWMG_URL . 'js/gallery-uploader.js', ['jquery'], null, true );
        wp_localize_script( 'twmg-script', 'twmg', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'twmg_nonce' ),
            'is_admin' => current_user_can( 'manage_options' ),
        ] );
    }

    public function render_gallery_page() {
        ob_start();
        ?>
        <div class="twmg-tabs">
            <ul class="twmg-tab-nav">
                <li class="active" data-tab="upload">Upload</li>
                <li data-tab="manage">Manage Folders</li>
            </ul>
            <div id="twmg-upload" class="twmg-tab-content active">
                <h2>Upload Gallery</h2>
                <input type="text" id="twmg-gallery-name" placeholder="Gallery Name" />
                <div id="twmg-dropzone" class="twmg-dropzone">Drag & Drop Images Here</div>
                <input type="file" id="twmg-file-input" multiple style="display:none;" />
                <div id="twmg-progress"></div>
                <button id="twmg-start-upload">Upload</button>
                <div id="twmg-message"></div>
            </div>
            <div id="twmg-manage" class="twmg-tab-content">
                <h2>Manage Folders</h2>
                <div id="twmg-gallery-list"></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function get_gallery_base() {
        $upload = wp_upload_dir();
        $base   = trailingslashit( $upload['basedir'] ) . 'twmg';
        if ( ! file_exists( $base ) ) {
            wp_mkdir_p( $base );
        }
        return $base;
    }

    public function handle_upload() {
        check_ajax_referer( 'twmg_nonce', 'nonce' );

        if ( empty( $_FILES['files'] ) || empty( $_POST['gallery'] ) ) {
            wp_send_json_error( 'Missing data.' );
        }

        $gallery = sanitize_text_field( $_POST['gallery'] );
        $base    = $this->get_gallery_base();

        $path = $base . '/' . date('Y/m/d') . '/' . sanitize_title( $gallery );
        wp_mkdir_p( $path );

        foreach ( $_FILES['files']['name'] as $i => $name ) {
            if ( $_FILES['files']['error'][$i] !== UPLOAD_ERR_OK ) {
                continue;
            }

            $filename = wp_unique_filename( $path, $name );
            move_uploaded_file( $_FILES['files']['tmp_name'][$i], $path . '/' . $filename );
        }

        // store gallery info
        $galleries = get_option( 'twmg_galleries', [] );
        $slug      = sanitize_title( $gallery );
        $galleries[ $slug ] = [
            'name' => $gallery,
            'path' => $path,
            'created' => current_time( 'timestamp' ),
            'expires' => current_time( 'timestamp' ) + DAY_IN_SECONDS * 7,
        ];
        update_option( 'twmg_galleries', $galleries );

        wp_send_json_success( 'Upload complete.' );
    }

    public function ajax_list_galleries() {
        check_ajax_referer( 'twmg_nonce', 'nonce' );
        $galleries = get_option( 'twmg_galleries', [] );
        $out = [];
        foreach ( $galleries as $slug => $data ) {
            if ( $data['expires'] < current_time( 'timestamp' ) ) {
                continue;
            }
            $url = content_url( str_replace( WP_CONTENT_DIR, '', $data['path'] ) );
            $out[] = [ 'name' => $data['name'], 'slug' => $slug, 'url' => $url ];
        }
        wp_send_json_success( $out );
    }

    public function ajax_get_gallery() {
        check_ajax_referer( 'twmg_nonce', 'nonce' );
        $slug = sanitize_title( $_POST['slug'] ?? '' );
        $galleries = get_option( 'twmg_galleries', [] );
        if ( empty( $galleries[ $slug ] ) ) {
            wp_send_json_error( 'Gallery not found' );
        }
        $path = $galleries[ $slug ]['path'];
        $url  = content_url( str_replace( WP_CONTENT_DIR, '', $path ) );
        $images = array_values( array_filter( scandir( $path ), function( $f ) {
            return preg_match( '/\.(jpg|jpeg|png|gif)$/i', $f );
        } ) );
        $data = [
            'name' => $galleries[ $slug ]['name'],
            'images' => [],
        ];
        foreach ( $images as $img ) {
            $data['images'][] = [
                'src' => $url . '/' . $img,
                'download' => $url . '/' . $img,
            ];
        }
        wp_send_json_success( $data );
    }

    public function ajax_delete_gallery() {
        check_ajax_referer( 'twmg_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }
        $slug = sanitize_title( $_POST['slug'] ?? '' );
        $galleries = get_option( 'twmg_galleries', [] );
        if ( empty( $galleries[ $slug ] ) ) {
            wp_send_json_error( 'Gallery not found' );
        }
        $path = $galleries[ $slug ]['path'];
        $this->delete_folder( $path );
        unset( $galleries[ $slug ] );
        update_option( 'twmg_galleries', $galleries );
        wp_send_json_success( 'Gallery deleted' );
    }

    public function ajax_regenerate_link() {
        check_ajax_referer( 'twmg_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }
        $slug = sanitize_title( $_POST['slug'] ?? '' );
        $galleries = get_option( 'twmg_galleries', [] );
        if ( empty( $galleries[ $slug ] ) ) {
            wp_send_json_error( 'Gallery not found' );
        }
        $galleries[ $slug ]['expires'] = current_time( 'timestamp' ) + DAY_IN_SECONDS * 7;
        update_option( 'twmg_galleries', $galleries );
        wp_send_json_success( 'Link regenerated' );
    }

    private function delete_folder( $dir ) {
        if ( ! file_exists( $dir ) ) {
            return;
        }
        $files = array_diff( scandir( $dir ), ['.', '..'] );
        foreach ( $files as $file ) {
            $path = "$dir/$file";
            if ( is_dir( $path ) ) {
                $this->delete_folder( $path );
            } else {
                unlink( $path );
            }
        }
        rmdir( $dir );
    }
}

new Two_Way_Media_Gallery();
