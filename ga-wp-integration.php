<?php

/**
 * Plugin Name: Google Analytics WP Integration
 * Description: Integrates WordPress and WooCommerce events with Google Analytics by mapping hooks to GA events.
 * Version: 1.0.0
 * Author: Shahin Ilderemi
 * Author URI: https://ildrm.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ga-wp-integration
 * Domain Path: /languages
 *
 * @package GA_WP_Integration
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Main plugin class for Google Analytics integration.
 *
 * Handles initialization, admin settings, GTM injection, and event mapping.
 *
 * @since 1.0.0
 */
class GA_WP_Integration
{
    /**
     * Singleton instance of the plugin.
     *
     * @since 1.0.0
     * @var GA_WP_Integration|null
     */
    private static $instance = null;

    /**
     * Plugin settings from the database.
     *
     * @since 1.0.0
     * @var array
     */
    private $ga_settings = [];

    /**
     * Event mappings (saved or default).
     *
     * @since 1.0.0
     * @var array
     */
    private $event_mappings = [];

    /**
     * Default event mappings.
     *
     * @since 1.0.0
     * @var array
     */
    private $default_mappings = [];

    /**
     * Get the singleton instance of the plugin.
     *
     * @since 1.0.0
     * @return GA_WP_Integration
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     *
     * Initializes settings, mappings, and hooks.
     *
     * @since 1.0.0
     */
    private function __construct()
    {
        $this->set_default_mappings();
        $this->ga_settings    = get_option('ga_wp_settings', []);
        $saved_mappings       = get_option('ga_wp_mappings', []);
        $this->event_mappings = ! empty($saved_mappings) ? $saved_mappings : $this->default_mappings;

        add_action('init', [$this, 'load_textdomain']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        if (! empty($this->ga_settings['gtm_code'])) {
            add_action('wp_head', [$this, 'inject_gtm_code']);
            add_action('wp_body_open', [$this, 'inject_gtm_noscript']);
        }

        $this->register_event_hooks();
    }

    /**
     * Load the plugin text domain for translations.
     *
     * @since 1.0.1
     */
    public function load_textdomain()
    {
        load_plugin_textdomain('ga-wp-integration', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /**
     * Set default event mappings for WordPress and WooCommerce (if active).
     *
     * @since 1.0.0
     */
    private function set_default_mappings()
    {
        $wordpress_mappings = [
            [
                'event'      => 'login',
                'hooks'      => ['wp_login'],
                'parameters' => [
                    ['ga_param' => 'method', 'source' => 'wordpress', 'pattern' => ''],
                    ['ga_param' => 'user_id', 'source' => 'user_id', 'pattern' => ''],
                ],
            ],
            [
                'event'      => 'sign_up',
                'hooks'      => ['user_register'],
                'parameters' => [
                    ['ga_param' => 'method', 'source' => 'wordpress', 'pattern' => ''],
                    ['ga_param' => 'user_id', 'source' => 'user_id', 'pattern' => ''],
                ],
            ],
        ];

        $this->default_mappings = $wordpress_mappings;

        if (function_exists('WC')) {
            $woocommerce_mappings = [
                [
                    'event'      => 'view_item_list',
                    'hooks'      => ['template_redirect'],
                    'parameters' => [
                        ['ga_param' => 'item_list_id', 'source' => 'taxonomy.product_cat.id', 'pattern' => ''],
                        ['ga_param' => 'item_list_name', 'source' => 'taxonomy.product_cat.name', 'pattern' => ''],
                        ['ga_param' => 'items', 'source' => 'product_list_items', 'pattern' => ''],
                    ],
                ],
                [
                    'event'      => 'view_item',
                    'hooks'      => ['template_redirect'],
                    'parameters' => [
                        ['ga_param' => 'currency', 'source' => 'meta.woocommerce_currency', 'pattern' => ''],
                        ['ga_param' => 'value', 'source' => 'product_price', 'pattern' => ''],
                        ['ga_param' => 'items', 'source' => 'current_product_item', 'pattern' => ''],
                    ],
                ],
                [
                    'event'      => 'add_to_cart',
                    'hooks'      => ['woocommerce_add_to_cart'],
                    'parameters' => [
                        ['ga_param' => 'currency', 'source' => 'meta.woocommerce_currency', 'pattern' => ''],
                        ['ga_param' => 'value', 'source' => 'price', 'pattern' => ''],
                        ['ga_param' => 'items', 'source' => 'items_json', 'pattern' => ''],
                    ],
                ],
                [
                    'event'      => 'remove_from_cart',
                    'hooks'      => ['woocommerce_cart_item_removed'],
                    'parameters' => [
                        ['ga_param' => 'currency', 'source' => 'meta.woocommerce_currency', 'pattern' => ''],
                        ['ga_param' => 'value', 'source' => 'removed_item_price', 'pattern' => ''],
                        ['ga_param' => 'items', 'source' => 'removed_item_json', 'pattern' => ''],
                    ],
                ],
                [
                    'event'      => 'view_cart',
                    'hooks'      => ['woocommerce_before_cart'],
                    'parameters' => [
                        ['ga_param' => 'currency', 'source' => 'meta.woocommerce_currency', 'pattern' => ''],
                        ['ga_param' => 'value', 'source' => 'cart_total', 'pattern' => ''],
                        ['ga_param' => 'items', 'source' => 'cart_items_json', 'pattern' => ''],
                    ],
                ],
                [
                    'event'      => 'begin_checkout',
                    'hooks'      => ['woocommerce_before_checkout_form'],
                    'parameters' => [
                        ['ga_param' => 'currency', 'source' => 'meta.woocommerce_currency', 'pattern' => ''],
                        ['ga_param' => 'value', 'source' => 'cart_total', 'pattern' => ''],
                        ['ga_param' => 'items', 'source' => 'cart_items_json', 'pattern' => ''],
                        ['ga_param' => 'coupon', 'source' => 'cart_coupon_code', 'pattern' => ''],
                    ],
                ],
                [
                    'event'      => 'add_shipping_info',
                    'hooks'      => ['woocommerce_checkout_update_order_review'],
                    'parameters' => [
                        ['ga_param' => 'currency', 'source' => 'meta.woocommerce_currency', 'pattern' => ''],
                        ['ga_param' => 'value', 'source' => 'cart_total', 'pattern' => ''],
                        ['ga_param' => 'shipping_tier', 'source' => 'selected_shipping_method', 'pattern' => ''],
                        ['ga_param' => 'items', 'source' => 'cart_items_json', 'pattern' => ''],
                    ],
                ],
                [
                    'event'      => 'add_payment_info',
                    'hooks'      => ['woocommerce_checkout_order_processed'],
                    'parameters' => [
                        ['ga_param' => 'currency', 'source' => 'meta.woocommerce_currency', 'pattern' => ''],
                        ['ga_param' => 'value', 'source' => 'cart_total', 'pattern' => ''],
                        ['ga_param' => 'payment_type', 'source' => 'payment_method', 'pattern' => ''],
                        ['ga_param' => 'items', 'source' => 'cart_items_json', 'pattern' => ''],
                    ],
                ],
                [
                    'event'      => 'purchase',
                    'hooks'      => ['woocommerce_payment_complete', 'woocommerce_order_status_completed'],
                    'parameters' => [
                        ['ga_param' => 'transaction_id', 'source' => 'order_id', 'pattern' => ''],
                        ['ga_param' => 'value', 'source' => 'order_total', 'pattern' => ''],
                        ['ga_param' => 'currency', 'source' => 'order_currency', 'pattern' => ''],
                        ['ga_param' => 'tax', 'source' => 'order_tax', 'pattern' => ''],
                        ['ga_param' => 'shipping', 'source' => 'order_shipping', 'pattern' => ''],
                        ['ga_param' => 'items', 'source' => 'items_json', 'pattern' => ''],
                        ['ga_param' => 'coupon', 'source' => 'order_coupon_codes', 'pattern' => ''],
                        ['ga_param' => 'affiliation', 'source' => 'site_name', 'pattern' => ''],
                    ],
                ],
                [
                    'event'      => 'refund',
                    'hooks'      => ['woocommerce_order_status_refunded'],
                    'parameters' => [
                        ['ga_param' => 'transaction_id', 'source' => 'order_id', 'pattern' => ''],
                        ['ga_param' => 'value', 'source' => 'order_total', 'pattern' => ''],
                        ['ga_param' => 'currency', 'source' => 'order_currency', 'pattern' => ''],
                        ['ga_param' => 'items', 'source' => 'items_json', 'pattern' => ''],
                    ],
                ],
                [
                    'event'      => 'select_item',
                    'hooks'      => ['woocommerce_before_shop_loop_item_title'],
                    'parameters' => [
                        ['ga_param' => 'item_list_id', 'source' => 'taxonomy.product_cat.id', 'pattern' => ''],
                        ['ga_param' => 'item_list_name', 'source' => 'taxonomy.product_cat.name', 'pattern' => ''],
                        ['ga_param' => 'items', 'source' => 'current_loop_product', 'pattern' => ''],
                    ],
                ],
                [
                    'event'      => 'select_promotion',
                    'hooks'      => ['woocommerce_before_shop_loop_item'],
                    'parameters' => [
                        ['ga_param' => 'promotion_id', 'source' => 'promotion_id', 'pattern' => ''],
                        ['ga_param' => 'promotion_name', 'source' => 'promotion_name', 'pattern' => ''],
                        ['ga_param' => 'creative_name', 'source' => 'creative_name', 'pattern' => ''],
                        ['ga_param' => 'creative_slot', 'source' => 'creative_slot', 'pattern' => ''],
                        ['ga_param' => 'location_id', 'source' => 'location_id', 'pattern' => ''],
                        ['ga_param' => 'items', 'source' => 'promotion_items', 'pattern' => ''],
                    ],
                ],
                [
                    'event'      => 'view_promotion',
                    'hooks'      => ['woocommerce_before_shop_loop'],
                    'parameters' => [
                        ['ga_param' => 'promotion_id', 'source' => 'promotion_id', 'pattern' => ''],
                        ['ga_param' => 'promotion_name', 'source' => 'promotion_name', 'pattern' => ''],
                        ['ga_param' => 'creative_name', 'source' => 'creative_name', 'pattern' => ''],
                        ['ga_param' => 'creative_slot', 'source' => 'creative_slot', 'pattern' => ''],
                        ['ga_param' => 'location_id', 'source' => 'location_id', 'pattern' => ''],
                        ['ga_param' => 'items', 'source' => 'promotion_items', 'pattern' => ''],
                    ],
                ],
            ];

            $this->default_mappings = array_merge($this->default_mappings, $woocommerce_mappings);
        }
    }

    /**
     * Enqueue admin scripts and styles.
     *
     * @since 1.0.0
     * @param string $hook The current admin page.
     */
    public function enqueue_admin_assets($hook)
    {
        if ('toplevel_page_ga-wp-integration' !== $hook) {
            return;
        }

        wp_enqueue_script('jquery');

        // Enqueue Select2
        wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', [], '4.1.0-rc.0');
        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], '4.1.0-rc.0', true);

        // Enqueue custom admin script
        wp_enqueue_script(
            'ga-wp-admin-script',
            plugins_url('assets/js/admin.js', __FILE__),
            ['jquery', 'select2'],
            '1.0.2',
            true
        );

        // Enqueue custom admin styles
        wp_enqueue_style(
            'ga-wp-admin-style',
            plugins_url('assets/css/admin.css', __FILE__),
            [],
            '1.0.2'
        );
    }

    /**
     * Add admin menu page for GA settings.
     *
     * @since 1.0.0
     */
    public function add_admin_menu()
    {
        add_menu_page(
            __('GA Integration', 'ga-wp-integration'),
            __('GA Integration', 'ga-wp-integration'),
            'manage_options',
            'ga-wp-integration',
            [$this, 'render_settings_page'],
            'dashicons-analytics',
            30
        );

        add_submenu_page(
            'ga-wp-integration',
            __('GA Settings', 'ga-wp-integration'),
            __('Settings', 'ga-wp-integration'),
            'manage_options',
            'ga-wp-integration',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Register settings and fields for the admin page.
     *
     * @since 1.0.0
     */
    public function register_settings()
    {
        register_setting('ga_wp_settings_group', 'ga_wp_settings', ['sanitize_callback' => [$this, 'sanitize_settings']]);
        register_setting('ga_wp_mappings_group', 'ga_wp_mappings', ['sanitize_callback' => [$this, 'sanitize_mappings']]);

        add_settings_section(
            'ga_wp_main_section',
            __('GA Configuration', 'ga-wp-integration'),
            null,
            'ga-wp-integration'
        );

        add_settings_field(
            'gtm_code',
            __('Google Tag Manager Code', 'ga-wp-integration'),
            [$this, 'render_gtm_field'],
            'ga-wp-integration',
            'ga_wp_main_section'
        );

        add_settings_section(
            'ga_wp_mappings_section',
            __('Event Mappings', 'ga-wp-integration'),
            [$this, 'render_mappings_section'],
            'ga-wp-integration'
        );
    }

    /**
     * Sanitize plugin settings.
     *
     * @since 1.0.0
     * @param array $input The input settings.
     * @return array Sanitized settings.
     */
    public function sanitize_settings($input)
    {
        $sanitized = [];
        if (isset($input['gtm_code'])) {
            $sanitized['gtm_code'] = sanitize_text_field($input['gtm_code']);
        }
        return $sanitized;
    }

    /**
     * Sanitize event mappings.
     *
     * @since 1.0.0
     * @param array $input The input mappings.
     * @return array Sanitized mappings.
     */
    public function sanitize_mappings($input)
    {
        $sanitized = [];
        if (is_array($input)) {
            foreach ($input as $index => $mapping) {
                $sanitized[$index] = [
                    'event'      => sanitize_text_field($mapping['event'] ?? ''),
                    'hooks'      => array_map('sanitize_text_field', (array) ($mapping['hooks'] ?? [])),
                    'parameters' => [],
                ];
                if (isset($mapping['parameters']) && is_array($mapping['parameters'])) {
                    foreach ($mapping['parameters'] as $param) {
                        $sanitized[$index]['parameters'][] = [
                            'ga_param' => sanitize_text_field($param['ga_param'] ?? ''),
                            'source'   => sanitize_text_field($param['source'] ?? ''),
                            'pattern'  => sanitize_text_field($param['pattern'] ?? ''),
                        ];
                    }
                }
            }
        }
        return $sanitized;
    }

    /**
     * Render the GTM code input field.
     *
     * @since 1.0.0
     */
    public function render_gtm_field()
    {
        $gtm_code = $this->ga_settings['gtm_code'] ?? '';
?>
        <input type="text" name="ga_wp_settings[gtm_code]" value="<?php echo esc_attr($gtm_code); ?>" placeholder="GTM-XXXXXX" pattern="GTM-[A-Z0-9]+" aria-required="true" />
        <span class="description"><?php esc_html_e('Enter your Google Tag Manager container ID (e.g., GTM-XXXXXX).', 'ga-wp-integration'); ?></span>
    <?php
    }

    /**
     * Render the event mappings section.
     *
     * @since 1.0.0
     */
    public function render_mappings_section()
    {
        $mappings = $this->event_mappings ?: [];
        $ga_events = [
            'add_payment_info',
            'add_shipping_info',
            'add_to_cart',
            'begin_checkout',
            'login',
            'purchase',
            'refund',
            'remove_from_cart',
            'select_item',
            'select_promotion',
            'sign_up',
            'view_cart',
            'view_item',
            'view_item_list',
            'view_promotion',
        ];
        $available_hooks = [
            'wp_login',
            'user_register',
            'comment_post',
            'template_redirect',
            'woocommerce_add_to_cart',
            'woocommerce_before_checkout_form',
            'woocommerce_payment_complete',
            'woocommerce_order_status_completed',
            'woocommerce_cart_item_removed',
            'woocommerce_before_cart',
            'woocommerce_checkout_update_order_review',
            'woocommerce_checkout_order_processed',
            'woocommerce_order_status_refunded',
            'woocommerce_before_shop_loop_item_title',
            'woocommerce_before_shop_loop_item',
            'woocommerce_before_shop_loop',
        ];
        $ga_params = [
            'currency',
            'value',
            'items',
            'item_id',
            'item_name',
            'item_list_id',
            'item_list_name',
            'transaction_id',
            'tax',
            'shipping',
            'coupon',
            'affiliation',
            'payment_type',
            'shipping_tier',
            'promotion_id',
            'promotion_name',
            'creative_name',
            'creative_slot',
            'location_id',
        ];
        $sources = [
            'site_name',
            'wordpress',
            'user.id',
            'user.username',
            'user.email',
            'user.display_name',
            'user.role',
            'taxonomy.product_cat.id',
            'taxonomy.product_cat.name',
            'taxonomy.product_cat.slug',
            'post.id',
            'post.title',
            'post.type',
            'post.category',
            'post.tags',
            'meta.woocommerce_currency',
            'product_id',
            'product_name',
            'product_price',
            'price',
            'quantity',
            'variation_id',
            'cart_total',
            'cart_coupon_code',
            'selected_shipping_method',
            'payment_method',
            'order_id',
            'order_total',
            'order_currency',
            'order_tax',
            'order_shipping',
            'order_coupon_codes',
            'items_json',
            'cart_items_json',
            'product_list_items',
            'current_product_item',
            'current_loop_product',
            'removed_item_json',
            'removed_item_price',
            'promotion_id',
            'promotion_name',
            'creative_name',
            'creative_slot',
            'location_id',
            'promotion_items',
        ];
    ?>
        <div class="ga-actions">
            <button type="button" id="ga-reset-defaults" class="button"><?php esc_html_e('Reset to Defaults', 'ga-wp-integration'); ?></button>
            <button type="button" id="ga-add-mapping" class="button button-primary"><?php esc_html_e('Add Mapping', 'ga-wp-integration'); ?></button>
            <button type="button" id="ga-load-preset" class="button"><?php esc_html_e('Load Preset', 'ga-wp-integration'); ?></button>
        </div>
        <div class="ga-mappings-wrap">
            <div id="ga-mappings-container">
                <?php foreach ($mappings as $index => $mapping) : ?>
                    <div class="mapping-item" data-index="<?php echo esc_attr($index); ?>">
                        <div class="mapping-header">
                            <span class="mapping-title"><?php echo esc_html(sprintf(__('Mapping #%d: %s', 'ga-wp-integration'), $index + 1, $mapping['event'])); ?></span>
                            <button type="button" class="toggle-mapping dashicons dashicons-arrow-down-alt2" aria-label="<?php esc_attr_e('Toggle mapping', 'ga-wp-integration'); ?>"></button>
                            <button type="button" class="copy-mapping dashicons dashicons-admin-page" aria-label="<?php esc_attr_e('Copy mapping', 'ga-wp-integration'); ?>"></button>
                            <button type="button" class="remove-mapping dashicons dashicons-trash" aria-label="<?php esc_attr_e('Remove mapping', 'ga-wp-integration'); ?>"></button>
                        </div>
                        <div class="mapping-content">
                            <div class="mapping-field">
                                <label for="ga-event-<?php echo esc_attr($index); ?>"><?php esc_html_e('GA Event', 'ga-wp-integration'); ?>:</label>
                                <select id="ga-event-<?php echo esc_attr($index); ?>" name="ga_wp_mappings[<?php echo esc_attr($index); ?>][event]" class="ga-event-select" aria-required="true">
                                    <?php foreach ($ga_events as $event) : ?>
                                        <option value="<?php echo esc_attr($event); ?>" <?php selected($mapping['event'], $event); ?>>
                                            <?php echo esc_html($event); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mapping-field">
                                <label for="ga-hooks-<?php echo esc_attr($index); ?>"><?php esc_html_e('Hooks', 'ga-wp-integration'); ?>:</label>
                                <select id="ga-hooks-<?php echo esc_attr($index); ?>" name="ga_wp_mappings[<?php echo esc_attr($index); ?>][hooks][]" class="ga-hooks-select" multiple>
                                    <optgroup label="WordPress Hooks">
                                        <?php foreach (array('wp_login', 'user_register', 'comment_post', 'template_redirect') as $hook) : ?>
                                            <option value="<?php echo esc_attr($hook); ?>" <?php echo in_array($hook, (array) ($mapping['hooks'] ?? []), true) ? 'selected' : ''; ?>>
                                                <?php echo esc_html($hook); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                    <optgroup label="WooCommerce Hooks">
                                        <?php foreach (array_diff($available_hooks, ['wp_login', 'user_register', 'comment_post', 'template_redirect']) as $hook) : ?>
                                            <option value="<?php echo esc_attr($hook); ?>" <?php echo in_array($hook, (array) ($mapping['hooks'] ?? []), true) ? 'selected' : ''; ?>>
                                                <?php echo esc_html($hook); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                </select>
                            </div>

                            <div class="parameters-container">
                                <div class="parameters-header">
                                    <span><?php esc_html_e('Parameters', 'ga-wp-integration'); ?></span>
                                    <button type="button" class="add-parameter dashicons dashicons-plus-alt" aria-label="<?php esc_attr_e('Add parameter', 'ga-wp-integration'); ?>"></button>
                                </div>
                                <div class="parameters-grid">
                                    <?php
                                    $parameters = $mapping['parameters'] ?? [['ga_param' => '', 'source' => '', 'pattern' => '']];
                                    foreach ($parameters as $param_index => $param) :
                                    ?>
                                        <div class="parameter-item">
                                            <div class="param-field">
                                                <label for="param-<?php echo esc_attr($index); ?>-<?php echo esc_attr($param_index); ?>"><?php esc_html_e('GA Param', 'ga-wp-integration'); ?>:</label>
                                                <select id="param-<?php echo esc_attr($index); ?>-<?php echo esc_attr($param_index); ?>"
                                                    name="ga_wp_mappings[<?php echo esc_attr($index); ?>][parameters][<?php echo esc_attr($param_index); ?>][ga_param]"
                                                    class="ga-param-select" aria-required="true">
                                                    <option value=""><?php esc_html_e('Select or type a parameter', 'ga-wp-integration'); ?></option>
                                                    <?php foreach ($ga_params as $ga_param) : ?>
                                                        <option value="<?php echo esc_attr($ga_param); ?>" <?php selected($param['ga_param'], $ga_param); ?>>
                                                            <?php echo esc_html($ga_param); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="param-field">
                                                <label for="source-<?php echo esc_attr($index); ?>-<?php echo esc_attr($param_index); ?>"><?php esc_html_e('Source', 'ga-wp-integration'); ?>:</label>
                                                <select id="source-<?php echo esc_attr($index); ?>-<?php echo esc_attr($param_index); ?>"
                                                    name="ga_wp_mappings[<?php echo esc_attr($index); ?>][parameters][<?php echo esc_attr($param_index); ?>][source]"
                                                    class="ga-source-select" aria-required="true"
                                                    title="<?php esc_attr_e('Select or type a source', 'ga-wp-integration'); ?>">
                                                    <option value=""><?php esc_html_e('Select or type a source', 'ga-wp-integration'); ?></option>
                                                    <?php foreach ($sources as $source_option) : ?>
                                                        <option value="<?php echo esc_attr($source_option); ?>" <?php selected($param['source'], $source_option); ?>>
                                                            <?php echo esc_html($source_option); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="param-field">
                                                <label for="pattern-<?php echo esc_attr($index); ?>-<?php echo esc_attr($param_index); ?>"><?php esc_html_e('Pattern', 'ga-wp-integration'); ?>:</label>
                                                <input type="text" id="pattern-<?php echo esc_attr($index); ?>-<?php echo esc_attr($param_index); ?>"
                                                    name="ga_wp_mappings[<?php echo esc_attr($index); ?>][parameters][<?php echo esc_attr($param_index); ?>][pattern]"
                                                    value="<?php echo esc_attr($param['pattern'] ?? ''); ?>" placeholder="e.g., {product_id}-{variation_id}"
                                                    title="<?php esc_attr_e('Optional: Combine values, e.g., {product_id}-{variation_id}', 'ga-wp-integration'); ?>" />
                                            </div>
                                            <button type="button" class="remove-parameter dashicons dashicons-trash" aria-label="<?php esc_attr_e('Remove parameter', 'ga-wp-integration'); ?>"></button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="ga-preview">
                <h2><?php esc_html_e('Live Preview', 'ga-wp-integration'); ?></h2>
                <pre id="ga-preview-output"></pre>
            </div>
        </div>

        <div class="ga-help-section">
            <h2><?php esc_html_e('Help & Documentation', 'ga-wp-integration'); ?></h2>
            <div class="ga-help-tabs">
                <ul class="nav-tabs">
                    <li><a href="#tab-hooks" class="active"><?php esc_html_e('Hooks', 'ga-wp-integration'); ?></a></li>
                    <li><a href="#tab-sources"><?php esc_html_e('Parameter Sources', 'ga-wp-integration'); ?></a></li>
                    <li><a href="#tab-patterns"><?php esc_html_e('Patterns', 'ga-wp-integration'); ?></a></li>
                </ul>
                <div class="tab-content">
                    <div id="tab-hooks" class="tab-pane active">
                        <ul>
                            <li><code>wp_login</code> - <?php esc_html_e('Triggered when a user logs in.', 'ga-wp-integration'); ?></li>
                            <li><code>user_register</code> - <?php esc_html_e('Triggered when a new user is registered.', 'ga-wp-integration'); ?></li>
                            <li><code>comment_post</code> - <?php esc_html_e('Triggered when a comment is posted.', 'ga-wp-integration'); ?></li>
                            <li><code>template_redirect</code> - <?php esc_html_e('Triggered when a page is loaded.', 'ga-wp-integration'); ?></li>
                            <li><code>woocommerce_add_to_cart</code> - <?php esc_html_e('Triggered when a product is added to cart.', 'ga-wp-integration'); ?></li>
                            <li><code>woocommerce_before_checkout_form</code> - <?php esc_html_e('Triggered before checkout form.', 'ga-wp-integration'); ?></li>
                            <li><code>woocommerce_payment_complete</code> - <?php esc_html_e('Triggered when payment is completed.', 'ga-wp-integration'); ?></li>
                            <li><code>woocommerce_order_status_completed</code> - <?php esc_html_e('Triggered when an order is completed.', 'ga-wp-integration'); ?></li>
                        </ul>
                    </div>
                    <div id="tab-sources" class="tab-pane">
                        <ul>
                            <li><strong><?php esc_html_e('WordPress User', 'ga-wp-integration'); ?>:</strong> <code>user.id</code>, <code>user.username</code>, <code>user.email</code>, <code>user.display_name</code>, <code>user.role</code></li>
                            <li><strong><?php esc_html_e('WordPress Post', 'ga-wp-integration'); ?>:</strong> <code>post.id</code>, <code>post.title</code>, <code>post.type</code>, <code>post.category</code>, <code>post.tags</code></li>
                            <li><strong><?php esc_html_e('WooCommerce Product', 'ga-wp-integration'); ?>:</strong> <code>product_id</code>, <code>product_name</code>, <code>price</code>, <code>quantity</code>, <code>variation_id</code></li>
                            <li><strong><?php esc_html_e('JetEngine', 'ga-wp-integration'); ?>:</strong> <code>jet.field_name</code></li>
                            <li><strong><?php esc_html_e('Custom Fields', 'ga-wp-integration'); ?>:</strong> <code>meta.field_name</code></li>
                        </ul>
                    </div>
                    <div id="tab-patterns" class="tab-pane">
                        <p><?php esc_html_e('Use patterns to combine values, e.g., <code>{product_id}-{variation_id}</code>.', 'ga-wp-integration'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <script>
            jQuery(document).ready(function($) {
                // Initialize Select2
                function initializeSelect2($container) {
                    $container.find('.ga-event-select').select2({
                        width: '100%',
                        allowClear: false,
                        placeholder: '<?php echo esc_js(__('Select a GA event', 'ga-wp-integration')); ?>'
                    });
                    $container.find('.ga-hooks-select').select2({
                        width: '100%',
                        allowClear: true,
                        placeholder: '<?php echo esc_js(__('Select hooks', 'ga-wp-integration')); ?>'
                    });
                    $container.find('.ga-param-select').select2({
                        width: '100%',
                        allowClear: true,
                        placeholder: '<?php echo esc_js(__('Select or type a parameter', 'ga-wp-integration')); ?>',
                        tags: true,
                        createTag: function(params) {
                            return {
                                id: params.term,
                                text: params.term,
                                newOption: true
                            };
                        }
                    });
                    $container.find('.ga-source-select').select2({
                        width: '100%',
                        allowClear: true,
                        placeholder: '<?php echo esc_js(__('Select or type a source', 'ga-wp-integration')); ?>',
                        tags: true,
                        createTag: function(params) {
                            return {
                                id: params.term,
                                text: params.term,
                                newOption: true
                            };
                        }
                    });
                }
                initializeSelect2($('#ga-mappings-container'));

                // RTL Support
                $('html').attr('dir', 'rtl').css('direction', 'rtl');

                // Mapping counter
                let mappingCount = <?php echo count($mappings); ?>;

                // Reset to defaults
                $('#ga-reset-defaults').on('click', function() {
                    if (confirm('<?php echo esc_js(__('Reset all mappings to defaults?', 'ga-wp-integration')); ?>')) {
                        window.location.href = '<?php echo esc_url(admin_url('admin.php?page=ga-wp-integration&reset=1')); ?>';
                    }
                });

                // Load preset
                $('#ga-load-preset').on('click', function() {
                    const presets = [{
                            event: 'add_to_cart',
                            hooks: ['woocommerce_add_to_cart'],
                            parameters: [{
                                    ga_param: 'currency',
                                    source: 'meta.woocommerce_currency',
                                    pattern: ''
                                },
                                {
                                    ga_param: 'value',
                                    source: 'price',
                                    pattern: ''
                                },
                                {
                                    ga_param: 'items',
                                    source: 'items_json',
                                    pattern: ''
                                }
                            ]
                        },
                        {
                            event: 'purchase',
                            hooks: ['woocommerce_payment_complete'],
                            parameters: [{
                                    ga_param: 'transaction_id',
                                    source: 'order_id',
                                    pattern: ''
                                },
                                {
                                    ga_param: 'value',
                                    source: 'order_total',
                                    pattern: ''
                                },
                                {
                                    ga_param: 'currency',
                                    source: 'order_currency',
                                    pattern: ''
                                }
                            ]
                        }
                    ];
                    const preset = presets[Math.floor(Math.random() * presets.length)];
                    addMapping(preset.event, preset.hooks, preset.parameters);
                });

                // Add new mapping
                function addMapping(event = '', hooks = [], parameters = [{
                    ga_param: '',
                    source: '',
                    pattern: ''
                }]) {
                    const template = `
                        <div class="mapping-item" data-index="${mappingCount}">
                            <div class="mapping-header">
                                <span class="mapping-title"><?php echo esc_js(sprintf(__('Mapping #%d', 'ga-wp-integration'), '" + (mappingCount + 1) + "')); ?></span>
                                <button type="button" class="toggle-mapping dashicons dashicons-arrow-down-alt2" aria-label="<?php esc_attr_e('Toggle mapping', 'ga-wp-integration'); ?>"></button>
                                <button type="button" class="copy-mapping dashicons dashicons-admin-page" aria-label="<?php esc_attr_e('Copy mapping', 'ga-wp-integration'); ?>"></button>
                                <button type="button" class="remove-mapping dashicons dashicons-trash" aria-label="<?php esc_attr_e('Remove mapping', 'ga-wp-integration'); ?>"></button>
                            </div>
                            <div class="mapping-content">
                                <div class="mapping-field">
                                    <label for="ga-event-${mappingCount}"><?php echo esc_js(__('GA Event', 'ga-wp-integration')); ?>:</label>
                                    <select id="ga-event-${mappingCount}" name="ga_wp_mappings[${mappingCount}][event]" class="ga-event-select" aria-required="true">
                                        <?php foreach ($ga_events as $event_option) : ?>
                                            <option value="<?php echo esc_attr($event_option); ?>" ${event === '<?php echo esc_attr($event_option); ?>' ? 'selected' : ''}>
                                                <?php echo esc_html($event_option); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mapping-field">
                                    <label for="ga-hooks-${mappingCount}"><?php echo esc_js(__('Hooks', 'ga-wp-integration')); ?>:</label>
                                    <select id="ga-hooks-${mappingCount}" name="ga_wp_mappings[${mappingCount}][hooks][]" class="ga-hooks-select" multiple>
                                        <optgroup label="WordPress Hooks">
                                            <?php foreach (array('wp_login', 'user_register', 'comment_post', 'template_redirect') as $hook) : ?>
                                                <option value="<?php echo esc_attr($hook); ?>" ${hooks.includes('<?php echo esc_attr($hook); ?>') ? 'selected' : ''}>
                                                    <?php echo esc_html($hook); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                        <optgroup label="WooCommerce Hooks">
                                            <?php foreach (array_diff($available_hooks, ['wp_login', 'user_register', 'comment_post', 'template_redirect']) as $hook) : ?>
                                                <option value="<?php echo esc_attr($hook); ?>" ${hooks.includes('<?php echo esc_attr($hook); ?>') ? 'selected' : ''}>
                                                    <?php echo esc_html($hook); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    </select>
                                </div>
                                <div class="parameters-container">
                                    <div class="parameters-header">
                                        <span><?php echo esc_js(__('Parameters', 'ga-wp-integration')); ?></span>
                                        <button type="button" class="add-parameter dashicons dashicons-plus-alt" aria-label="<?php esc_attr_e('Add parameter', 'ga-wp-integration'); ?>"></button>
                                    </div>
                                    <div class="parameters-grid">
                                        ${parameters.map((param, paramIndex) => `
                                            <div class="parameter-item">
                                                <div class="param-field">
                                                    <label for="param-${mappingCount}-${paramIndex}"><?php echo esc_js(__('GA Param', 'ga-wp-integration')); ?>:</label>
                                                    <select id="param-${mappingCount}-${paramIndex}"
                                                            name="ga_wp_mappings[${mappingCount}][parameters][${paramIndex}][ga_param]"
                                                            class="ga-param-select" aria-required="true">
                                                        <option value=""><?php echo esc_js(__('Select or type a parameter', 'ga-wp-integration')); ?></option>
                                                        <?php foreach ($ga_params as $ga_param) : ?>
                                                            <option value="<?php echo esc_attr($ga_param); ?>" ${param.ga_param === '<?php echo esc_attr($ga_param); ?>' ? 'selected' : ''}>
                                                                <?php echo esc_html($ga_param); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="param-field">
                                                    <label for="source-${mappingCount}-${paramIndex}"><?php echo esc_js(__('Source', 'ga-wp-integration')); ?>:</label>
                                                    <select id="source-${mappingCount}-${paramIndex}"
                                                            name="ga_wp_mappings[${mappingCount}][parameters][${paramIndex}][source]"
                                                            class="ga-source-select" aria-required="true"
                                                            title="<?php esc_attr_e('Select or type a source', 'ga-wp-integration'); ?>">
                                                        <option value=""><?php echo esc_js(__('Select or type a source', 'ga-wp-integration')); ?></option>
                                                        <?php foreach ($sources as $source_option) : ?>
                                                            <option value="<?php echo esc_attr($source_option); ?>" ${param.source === '<?php echo esc_attr($source_option); ?>' ? 'selected' : ''}>
                                                                <?php echo esc_html($source_option); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="param-field">
                                                    <label for="pattern-${mappingCount}-${paramIndex}"><?php echo esc_js(__('Pattern', 'ga-wp-integration')); ?>:</label>
                                                    <input type="text" id="pattern-${mappingCount}-${paramIndex}"
                                                           name="ga_wp_mappings[${mappingCount}][parameters][${paramIndex}][pattern]"
                                                           value="${param.pattern}" placeholder="e.g., {product_id}-{variation_id}"
                                                           title="<?php esc_attr_e('Optional: Combine values, e.g., {product_id}-{variation_id}', 'ga-wp-integration'); ?>" />
                                                </div>
                                                <button type="button" class="remove-parameter dashicons dashicons-trash" aria-label="<?php esc_attr_e('Remove parameter', 'ga-wp-integration'); ?>"></button>
                                            </div>
                                        `).join('')}
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;

                    $('#ga-mappings-container').append(template);
                    initializeSelect2($('.mapping-item').last());
                    updatePreview();
                    mappingCount++;
                }

                $('#ga-add-mapping').on('click', function() {
                    addMapping();
                });

                // Toggle mapping visibility
                $('#ga-mappings-container').on('click', '.toggle-mapping', function() {
                    const $mapping = $(this).closest('.mapping-item');
                    $mapping.toggleClass('collapsed');
                    $mapping.find('.mapping-content').slideToggle(200);
                    $(this).toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
                });

                // Copy mapping
                $('#ga-mappings-container').on('click', '.copy-mapping', function() {
                    const $mapping = $(this).closest('.mapping-item');
                    const index = $mapping.data('index');
                    const event = $mapping.find('.ga-event-select').val();
                    const hooks = $mapping.find('.ga-hooks-select').val() || [];
                    const parameters = [];
                    $mapping.find('.parameter-item').each(function() {
                        parameters.push({
                            ga_param: $(this).find('.ga-param-select').val(),
                            source: $(this).find('.ga-source-select').val(),
                            pattern: $(this).find('input[name$="[pattern]"]').val()
                        });
                    });
                    addMapping(event, hooks, parameters);
                });

                // Add parameter
                $('#ga-mappings-container').on('click', '.add-parameter', function() {
                    const $paramsContainer = $(this).closest('.parameters-container').find('.parameters-grid');
                    const mappingIndex = $(this).closest('.mapping-item').data('index');
                    const paramCount = $paramsContainer.find('.parameter-item').length;

                    const template = `
                        <div class="parameter-item">
                            <div class="param-field">
                                <label for="param-${mappingIndex}-${paramCount}"><?php echo esc_js(__('GA Param', 'ga-wp-integration')); ?>:</label>
                                <select id="param-${mappingIndex}-${paramCount}"
                                        name="ga_wp_mappings[${mappingIndex}][parameters][${paramCount}][ga_param]"
                                        class="ga-param-select" aria-required="true">
                                    <option value=""><?php echo esc_js(__('Select or type a parameter', 'ga-wp-integration')); ?></option>
                                    <?php foreach ($ga_params as $ga_param) : ?>
                                        <option value="<?php echo esc_attr($ga_param); ?>">
                                            <?php echo esc_html($ga_param); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="param-field">
                                <label for="source-${mappingIndex}-${paramCount}"><?php echo esc_js(__('Source', 'ga-wp-integration')); ?>:</label>
                                <select id="source-${mappingIndex}-${paramCount}"
                                        name="ga_wp_mappings[${mappingIndex}][parameters][${paramCount}][source]"
                                        class="ga-source-select" aria-required="true"
                                        title="<?php esc_attr_e('Select or type a source', 'ga-wp-integration'); ?>">
                                    <option value=""><?php echo esc_js(__('Select or type a source', 'ga-wp-integration')); ?></option>
                                    <?php foreach ($sources as $source_option) : ?>
                                        <option value="<?php echo esc_attr($source_option); ?>">
                                            <?php echo esc_html($source_option); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="param-field">
                                <label for="pattern-${mappingIndex}-${paramCount}"><?php echo esc_js(__('Pattern', 'ga-wp-integration')); ?>:</label>
                                <input type="text" id="pattern-${mappingIndex}-${paramCount}"
                                       name="ga_wp_mappings[${mappingIndex}][parameters][${paramCount}][pattern]"
                                       placeholder="e.g., {product_id}-{variation_id}"
                                       title="<?php esc_attr_e('Optional: Combine values, e.g., {product_id}-{variation_id}', 'ga-wp-integration'); ?>" />
                            </div>
                            <button type="button" class="remove-parameter dashicons dashicons-trash" aria-label="<?php esc_attr_e('Remove parameter', 'ga-wp-integration'); ?>"></button>
                        </div>
                    `;

                    $paramsContainer.append(template);
                    initializeSelect2($paramsContainer.find('.parameter-item').last());
                    updatePreview();
                });

                // Remove parameter
                $('#ga-mappings-container').on('click', '.remove-parameter', function() {
                    const $paramsContainer = $(this).closest('.parameters-grid');
                    if ($paramsContainer.find('.parameter-item').length > 1) {
                        $(this).closest('.parameter-item').remove();
                        updatePreview();
                    }
                });

                // Remove mapping
                $('#ga-mappings-container').on('click', '.remove-mapping', function() {
                    $(this).closest('.mapping-item').remove();
                    updatePreview();
                });

                // Live preview
                function updatePreview() {
                    const mappings = [];
                    $('.mapping-item').each(function() {
                        const $mapping = $(this);
                        const mappingData = {
                            event: $mapping.find('.ga-event-select').val(),
                            hooks: $mapping.find('.ga-hooks-select').val() || [],
                            parameters: []
                        };
                        $mapping.find('.parameter-item').each(function() {
                            const param = {
                                ga_param: $(this).find('.ga-param-select').val(),
                                source: $(this).find('.ga-source-select').val(),
                                pattern: $(this).find('input[name$="[pattern]"]').val()
                            };
                            if (param.ga_param && param.source) {
                                mappingData.parameters.push(param);
                            }
                        });
                        if (mappingData.event) {
                            mappings.push(mappingData);
                        }
                    });
                    $('#ga-preview-output').text(JSON.stringify(mappings, null, 2));
                }
                $('#ga-mappings-container').on('change input', updatePreview);
                updatePreview();

                // Form validation
                $('form').on('submit', function(e) {
                    let isValid = true;
                    $('.mapping-item').each(function() {
                        const $mapping = $(this);
                        const event = $mapping.find('.ga-event-select').val();
                        const $params = $mapping.find('.parameter-item');
                        if (!event) {
                            isValid = false;
                            $mapping.find('.ga-event-select').addClass('error');
                        }
                        $params.each(function() {
                            const ga_param = $(this).find('.ga-param-select').val();
                            const source = $(this).find('.ga-source-select').val();
                            if (!ga_param || !source) {
                                isValid = false;
                                $(this).find('select[aria-required="true"]').addClass('error');
                            }
                        });
                    });
                    if (!isValid) {
                        e.preventDefault();
                        alert('<?php echo esc_js(__('Please fill all required fields (Event and Parameter fields).', 'ga-wp-integration')); ?>');
                    }
                });

                // Help tabs
                $('.nav-tabs a').on('click', function(e) {
                    e.preventDefault();
                    $('.nav-tabs a').removeClass('active');
                    $(this).addClass('active');
                    $('.tab-pane').removeClass('active');
                    $($(this).attr('href')).addClass('active');
                });
            });
        </script>

        <style>
            /* RTL Support */
            html[dir="rtl"] .ga-mappings-wrap,
            html[dir="rtl"] .ga-help-section {
                direction: rtl;
                text-align: right;
            }

            html[dir="rtl"] .mapping-header,
            html[dir="rtl"] .parameters-header {
                flex-direction: row-reverse;
            }

            html[dir="rtl"] .mapping-field label,
            html[dir="rtl"] .param-field label {
                margin-left: 8px;
                margin-right: 0;
            }

            html[dir="rtl"] .dashicons {
                transform: scaleX(-1);
            }

            /* General Layout */
            .ga-mappings-wrap {
                display: flex;
                gap: 20px;
                margin-bottom: 20px;
            }

            #ga-mappings-container {
                flex: 2;
                background: #fff;
                border: 1px solid #ccd0d4;
                padding: 10px;
                border-radius: 4px;
            }

            .ga-preview {
                flex: 1;
                background: #f8f9fa;
                border: 1px solid #ccd0d4;
                padding: 10px;
                border-radius: 4px;
                max-height: 600px;
                overflow-y: auto;
                text-align: left;
                direction: ltr;
            }

            .ga-preview pre {
                margin: 0;
                font-size: 12px;
                white-space: pre-wrap;
            }

            .ga-actions {
                gap: 10px;
                margin-bottom: 10px;
                justify-content: flex-end;
            }

            /* Mapping Item */
            .mapping-item {
                border: 1px solid #e5e5e5;
                margin-bottom: 8px;
                border-radius: 4px;
                background: #f9f9f9;
            }

            .mapping-header {
                display: flex;
                align-items: center;
                padding: 8px;
                background: #e5e5e5;
                border-bottom: 1px solid #ddd;
            }

            .mapping-title {
                flex: 1;
                font-size: 14px;
                font-weight: 600;
            }

            .mapping-header button {
                background: none;
                border: none;
                cursor: pointer;
                padding: 4px;
                line-height: 1;
            }

            .mapping-content {
                padding: 8px;
                display: block;
            }

            .mapping-item.collapsed .mapping-content {
                display: none;
            }

            .mapping-field {
                display: flex;
                align-items: center;
                gap: 8px;
                margin-bottom: 8px;
            }

            .mapping-field label {
                width: 80px;
                font-size: 13px;
                margin-right: 8px;
            }

            .mapping-field select {
                flex: 1;
                max-width: 300px;
            }

            /* Parameters */
            .parameters-container {
                margin-top: 8px;
            }

            .parameters-header {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 4px 8px;
                background: #e5e5e5;
                border-bottom: 1px solid #ddd;
            }

            .parameters-header span {
                font-size: 13px;
                font-weight: 600;
            }

            .parameters-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 8px;
                padding: 8px;
            }

            .parameter-item {
                display: flex;
                flex-direction: column;
                gap: 4px;
                background: #fff;
                border: 1px solid #e5e5e5;
                padding: 8px;
                border-radius: 4px;
                position: relative;
            }

            .param-field {
                display: flex;
                align-items: center;
                gap: 4px;
            }

            .param-field label {
                width: 60px;
                font-size: 12px;
            }

            .param-field select,
            .param-field input {
                flex: 1;
                padding: 4px;
                font-size: 12px;
                border: 1px solid #ddd;
                border-radius: 3px;
            }

            .param-field select.error,
            .param-field input.error {
                border-color: #d63638;
            }

            .remove-parameter {
                position: absolute;
                top: 4px;
                right: 4px;
                background: none;
                border: none;
                cursor: pointer;
                padding: 2px;
            }

            /* Help Section */
            .ga-help-section {
                background: #fff;
                border: 1px solid #ccd0d4;
                padding: 10px;
                border-radius: 4px;
            }

            .nav-tabs {
                display: flex;
                gap: 10px;
                border-bottom: 1px solid #ddd;
                margin-bottom: 10px;
            }

            .nav-tabs a {
                padding: 8px 16px;
                text-decoration: none;
                color: #0073aa;
                font-size: 13px;
            }

            .nav-tabs a.active {
                border-bottom: 2px solid #0073aa;
                color: #000;
            }

            .tab-pane {
                display: none;
            }

            .tab-pane.active {
                display: block;
            }

            .tab-pane ul {
                list-style: none;
                padding: 0;
            }

            .tab-pane li {
                margin-bottom: 8px;
                font-size: 13px;
            }

            .tab-pane code {
                background: #f1f1f1;
                padding: 2px 4px;
                border-radius: 3px;
            }

            /* Accessibility */
            [aria-required="true"]:focus {
                outline: 2px solid #0073aa;
            }

            .description {
                color: #444;
                font-size: 12px;
                display: block;
            }

            .remove-parameter.dashicons.dashicons-trash {
                margin-top: -15px;
                color: #ff0000;
                font-size: 20px;
            }

            .remove-mapping.dashicons.dashicons-trash {
                color: #ff0000;
            }

            .add-parameter.dashicons.dashicons-plus-alt {
                color: #00aa00;
            }

            .copy-mapping.dashicons.dashicons-admin-page {
                color: #0000aa;
            }
        </style>
        <?php
    }

    /**
     * Render the admin settings page.
     *
     * @since 1.0.0
     */
    public function render_settings_page()
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'ga-wp-integration'));
        }

        if (isset($_GET['reset']) && '1' === $_GET['reset']) {
            update_option('ga_wp_mappings', $this->default_mappings);
            $this->event_mappings = $this->default_mappings;
        ?>
            <div class="notice notice-success">
                <p><?php esc_html_e('Mappings have been reset to default values.', 'ga-wp-integration'); ?></p>
            </div>
        <?php
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Google Analytics Integration Settings', 'ga-wp-integration'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('ga_wp_settings_group');
                do_settings_sections('ga-wp-integration');
                submit_button(__('Save Settings', 'ga-wp-integration'));
                ?>
            </form>
        </div>
    <?php
    }

    /**
     * Inject Google Tag Manager code in the head.
     *
     * @since 1.0.0
     */
    public function inject_gtm_code()
    {
        if (empty($this->ga_settings['gtm_code'])) {
            return;
        }
    ?>
        <!-- Google Tag Manager -->
        <script>
            (function(w, d, s, l, i) {
                w[l] = w[l] || [];
                w[l].push({
                    'gtm.start': new Date().getTime(),
                    event: 'gtm.js'
                });
                var f = d.getElementsByTagName(s)[0],
                    j = d.createElement(s),
                    dl = l != 'dataLayer' ? '&l=' + l : '';
                j.async = true;
                j.src = 'https://www.googletagmanager.com/gtm.js?id=' + i + dl;
                f.parentNode.insertBefore(j, f);
            })(window, document, 'script', 'dataLayer', '<?php echo esc_js($this->ga_settings['gtm_code']); ?>');
        </script>
        <!-- End Google Tag Manager -->
    <?php
    }

    /**
     * Inject Google Tag Manager noscript code in the body.
     *
     * @since 1.0.0
     */
    public function inject_gtm_noscript()
    {
        if (empty($this->ga_settings['gtm_code'])) {
            return;
        }
    ?>
        <!-- Google Tag Manager (noscript) -->
        <noscript>
            <iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo esc_attr($this->ga_settings['gtm_code']); ?>"
                height="0" width="0" style="display:none;visibility:hidden"></iframe>
        </noscript>
        <!-- End Google Tag Manager (noscript) -->
    <?php
    }

    /**
     * Register hooks for event mappings.
     *
     * @since 1.0.0
     */
    private function register_event_hooks()
    {
        if (empty($this->event_mappings)) {
            return;
        }

        foreach ($this->event_mappings as $mapping) {
            if (empty($mapping['event']) || empty($mapping['hooks'])) {
                continue;
            }

            foreach ((array) $mapping['hooks'] as $hook) {
                if (empty($hook)) {
                    continue;
                }

                add_action(
                    $hook,
                    function (...$args) use ($mapping, $hook) {
                        $this->send_ga_event($mapping, $hook, $args);
                    },
                    10,
                    99
                );
            }
        }
    }

    /**
     * Send a Google Analytics event to the dataLayer.
     *
     * @since 1.0.0
     * @param array  $mapping The event mapping configuration.
     * @param string $hook    The triggered hook.
     * @param array  $args    Hook arguments.
     */
    private function send_ga_event($mapping, $hook, $args)
    {
        if (empty($mapping['event']) || empty($this->ga_settings['gtm_code'])) {
            return;
        }

        // Event-specific conditions
        if ('view_item' === $mapping['event'] && ! is_product()) {
            return;
        }
        if ('view_item_list' === $mapping['event'] && ! (is_shop() || is_product_category() || is_product_tag() || is_search())) {
            return;
        }

        $event_data = [];
        if (! empty($mapping['parameters'])) {
            foreach ($mapping['parameters'] as $param) {
                if (empty($param['ga_param']) || empty($param['source'])) {
                    continue;
                }

                $value = $this->get_parameter_value($param['source'], $param['pattern'] ?? '', $hook, $args);

                if (false !== strpos($param['ga_param'], 'items') && null !== $value) {
                    $decoded = json_decode($value, true);
                    if (is_array($decoded)) {
                        $event_data[$param['ga_param']] = $decoded;
                        continue;
                    }
                }

                if (null !== $value) {
                    $event_data[$param['ga_param']] = $value;
                }
            }
        }

        // Skip if essential data is missing
        if (in_array($mapping['event'], ['view_item', 'view_item_list', 'add_to_cart'], true) && empty($event_data['items'])) {
            return;
        }

        // Debug output for admins
        if (current_user_can('administrator') && isset($_GET['ga_debug'])) {
            echo '<pre>';
            echo esc_html('Event: ' . $mapping['event'] . "\n");
            echo esc_html('Hook: ' . $hook . "\n");
            echo esc_html('Data: ');
            print_r($event_data);
            echo '</pre>';
        }

    ?>
        <script>
            window.dataLayer = window.dataLayer || [];
            dataLayer.push({
                'event': '<?php echo esc_js($mapping['event']); ?>',
                <?php foreach ($event_data as $key => $value) : ?>
                    <?php if (is_array($value)) : ?> '<?php echo esc_js($key); ?>': <?php echo wp_json_encode($value); ?>,
                    <?php else : ?>
                        wojny '<?php echo esc_js($key); ?>': <?php echo is_numeric($value) ? $value : "'" . esc_js($value) . "'"; ?>,
                    <?php endif; ?>
                <?php endforeach; ?>
            });
        </script>
<?php
    }

    /**
     * Retrieve the value for a parameter based on its source.
     *
     * @since 1.0.0
     * @param string $source The data source.
     * @param string $pattern The optional pattern for formatting.
     * @param string $hook   The triggered hook.
     * @param array  $args   Hook arguments.
     * @return mixed|null The parameter value or null if not found.
     */
    private function get_parameter_value($source, $pattern, $hook, $args)
    {
        $value = null;

        // Handle WordPress sources
        if ('site_name' === $source) {
            $value = get_bloginfo('name');
        } elseif ('wordpress' === $source) {
            $value = 'WordPress';
        } elseif (strpos($source, 'user.') === 0) {
            $user_property = substr($source, 5);
            $current_user  = wp_get_current_user();
            if ($current_user->ID > 0) {
                switch ($user_property) {
                    case 'id':
                        $value = $current_user->ID;
                        break;
                    case 'username':
                        $value = $current_user->user_login;
                        break;
                    case 'email':
                        $value = $current_user->user_email;
                        break;
                    case 'display_name':
                        $value = $current_user->display_name;
                        break;
                    case 'role':
                        $value = ! empty($current_user->roles) ? $current_user->roles[0] : '';
                        break;
                    default:
                        $value = get_user_meta($current_user->ID, $user_property, true);
                }
            }
        } elseif (strpos($source, 'taxonomy.') === 0) {
            $tax_parts = explode('.', $source, 3);
            if (count($tax_parts) >= 3) {
                $taxonomy     = $tax_parts[1];
                $tax_property = $tax_parts[2];
                if (is_tax($taxonomy) || is_category() || is_tag()) {
                    $term = get_queried_object();
                    switch ($tax_property) {
                        case 'id':
                            $value = $term->term_id;
                            break;
                        case 'name':
                            $value = $term->name;
                            break;
                        case 'slug':
                            $value = $term->slug;
                            break;
                        case 'description':
                            $value = $term->description;
                            break;
                        case 'count':
                            $value = $term->count;
                            break;
                    }
                }
            }
        } elseif (strpos($source, 'post.') === 0) {
            $post_property = substr($source, 5);
            $post          = get_post();
            if ($post) {
                switch ($post_property) {
                    case 'id':
                        $value = $post->ID;
                        break;
                    case 'title':
                        $value = $post->post_title;
                        break;
                    case 'type':
                        $value = $post->post_type;
                        break;
                    case 'category':
                        $categories = get_the_category($post->ID);
                        $value      = ! empty($categories) ? $categories[0]->name : '';
                        break;
                    case 'tags':
                        $tags       = get_the_tags($post->ID);
                        $tag_names  = [];
                        if ($tags) {
                            foreach ($tags as $tag) {
                                $tag_names[] = $tag->name;
                            }
                        }
                        $value = implode(', ', $tag_names);
                        break;
                    default:
                        $value = get_post_meta($post->ID, $post_property, true);
                }
            }
        } elseif (strpos($source, 'meta.') === 0) {
            // Handle custom meta fields
            $meta_key = substr($source, 5);
            if ('woocommerce_currency' === $meta_key && function_exists('get_woocommerce_currency')) {
                $value = get_woocommerce_currency();
            } else {
                $post = get_post();
                if ($post) {
                    $value = get_post_meta($post->ID, $meta_key, true);
                }
            }
        } elseif (strpos($source, 'jet.') === 0) {
            // Handle JetEngine custom fields
            $field_name = substr($source, 4);
            if (function_exists('jet_engine')) {
                $value = jet_engine()->listings->data->get_meta($field_name);
            }
        } elseif (function_exists('WC')) {
            // Handle WooCommerce sources
            switch ($source) {
                case 'product_id':
                    if (is_product()) {
                        $product = wc_get_product(get_the_ID());
                        $value   = $product ? $product->get_id() : null;
                    }
                    break;
                case 'product_name':
                    if (is_product()) {
                        $product = wc_get_product(get_the_ID());
                        $value   = $product ? $product->get_name() : null;
                    }
                    break;
                case 'product_price':
                    if (is_product()) {
                        $product = wc_get_product(get_the_ID());
                        $value   = $product ? wc_get_price_to_display($product) : null;
                    }
                    break;
                case 'price':
                    if ('woocommerce_add_to_cart' === $hook && ! empty($args[2])) {
                        $product = wc_get_product($args[1]);
                        $value   = $product ? wc_get_price_to_display($product) * $args[2] : null;
                    }
                    break;
                case 'quantity':
                    if ('woocommerce_add_to_cart' === $hook && ! empty($args[2])) {
                        $value = $args[2];
                    }
                    break;
                case 'variation_id':
                    if ('woocommerce_add_to_cart' === $hook && ! empty($args[4])) {
                        $value = $args[4];
                    }
                    break;
                case 'cart_total':
                    if (WC()->cart) {
                        $value = WC()->cart->get_total('edit');
                    }
                    break;
                case 'cart_coupon_code':
                    if (WC()->cart) {
                        $coupons = WC()->cart->get_applied_coupons();
                        $value   = ! empty($coupons) ? implode(', ', $coupons) : '';
                    }
                    break;
                case 'selected_shipping_method':
                    if ('woocommerce_checkout_update_order_review' === $hook && ! empty($args[0])) {
                        parse_str($args[0], $posted_data);
                        $value = ! empty($posted_data['shipping_method'][0]) ? $posted_data['shipping_method'][0] : '';
                    }
                    break;
                case 'payment_method':
                    if ('woocommerce_checkout_order_processed' === $hook && ! empty($args[0])) {
                        $order = wc_get_order($args[0]);
                        $value = $order ? $order->get_payment_method() : null;
                    }
                    break;
                case 'order_id':
                    if (in_array($hook, ['woocommerce_payment_complete', 'woocommerce_order_status_completed', 'woocommerce_order_status_refunded'], true) && ! empty($args[0])) {
                        $value = $args[0];
                    }
                    break;
                case 'order_total':
                    if (in_array($hook, ['woocommerce_payment_complete', 'woocommerce_order_status_completed', 'woocommerce_order_status_refunded'], true) && ! empty($args[0])) {
                        $order = wc_get_order($args[0]);
                        $value = $order ? $order->get_total() : null;
                    }
                    break;
                case 'order_currency':
                    if (in_array($hook, ['woocommerce_payment_complete', 'woocommerce_order_status_completed', 'woocommerce_order_status_refunded'], true) && ! empty($args[0])) {
                        $order = wc_get_order($args[0]);
                        $value = $order ? $order->get_currency() : null;
                    }
                    break;
                case 'order_tax':
                    if (in_array($hook, ['woocommerce_payment_complete', 'woocommerce_order_status_completed'], true) && ! empty($args[0])) {
                        $order = wc_get_order($args[0]);
                        $value = $order ? $order->get_total_tax() : null;
                    }
                    break;
                case 'order_shipping':
                    if (in_array($hook, ['woocommerce_payment_complete', 'woocommerce_order_status_completed'], true) && ! empty($args[0])) {
                        $order = wc_get_order($args[0]);
                        $value = $order ? $order->get_shipping_total() : null;
                    }
                    break;
                case 'order_coupon_codes':
                    if (in_array($hook, ['woocommerce_payment_complete', 'woocommerce_order_status_completed'], true) && ! empty($args[0])) {
                        $order = wc_get_order($args[0]);
                        $coupons = $order ? $order->get_coupon_codes() : [];
                        $value   = ! empty($coupons) ? implode(', ', $coupons) : '';
                    }
                    break;
                case 'items_json':
                    $value = $this->get_items_json($hook, $args);
                    break;
                case 'cart_items_json':
                    $value = $this->get_cart_items_json();
                    break;
                case 'product_list_items':
                    $value = $this->get_product_list_items_json();
                    break;
                case 'current_product_item':
                    $value = $this->get_current_product_item_json();
                    break;
                case 'current_loop_product':
                    $value = $this->get_current_loop_product_json();
                    break;
                case 'removed_item_json':
                    $value = $this->get_removed_item_json($hook, $args);
                    break;
                case 'removed_item_price':
                    $value = $this->get_removed_item_price($hook, $args);
                    break;
                case 'promotion_id':
                case 'promotion_name':
                case 'creative_name':
                case 'creative_slot':
                case 'location_id':
                case 'promotion_items':
                    // Placeholder for promotion data (requires custom implementation)
                    $value = '';
                    break;
            }
        }

        // Apply pattern if provided
        if ($value !== null && ! empty($pattern)) {
            $value = $this->apply_pattern($pattern, $source, $value, $hook, $args);
        }

        return $value;
    }

    /**
     * Apply a pattern to format the parameter value.
     *
     * @since 1.0.0
     * @param string $pattern The pattern to apply.
     * @param string $source  The original source.
     * @param mixed  $value   The original value.
     * @param string $hook    The triggered hook.
     * @param array  $args    Hook arguments.
     * @return string The formatted value.
     */
    private function apply_pattern($pattern, $source, $value, $hook, $args)
    {
        $replacements = [];
        preg_match_all('/\{([^\}]+)\}/', $pattern, $matches);
        if (! empty($matches[1])) {
            foreach ($matches[1] as $placeholder) {
                $placeholder_value = $this->get_parameter_value($placeholder, '', $hook, $args);
                $replacements['{' . $placeholder . '}'] = $placeholder_value ?? '';
            }
        }
        return str_replace(array_keys($replacements), array_values($replacements), $pattern);
    }

    /**
     * Get JSON for items in WooCommerce events (e.g., add_to_cart, purchase).
     *
     * @since 1.0.0
     * @param string $hook The triggered hook.
     * @param array  $args Hook arguments.
     * @return string|null JSON string of items or null.
     */
    private function get_items_json($hook, $args)
    {
        $items = [];
        if ('woocommerce_add_to_cart' === $hook && ! empty($args[1]) && ! empty($args[2])) {
            $product = wc_get_product($args[1]);
            if ($product) {
                $items[] = [
                    'item_id'      => $product->get_id(),
                    'item_name'    => $product->get_name(),
                    'price'        => wc_get_price_to_display($product),
                    'quantity'     => $args[2],
                    'item_variant' => ! empty($args[4]) ? $args[4] : '',
                ];
            }
        } elseif (in_array($hook, ['woocommerce_payment_complete', 'woocommerce_order_status_completed', 'woocommerce_order_status_refunded'], true) && ! empty($args[0])) {
            $order = wc_get_order($args[0]);
            if ($order) {
                foreach ($order->get_items() as $item) {
                    $product = $item->get_product();
                    if ($product) {
                        $items[] = [
                            'item_id'      => $product->get_id(),
                            'item_name'    => $product->get_name(),
                            'price'        => wc_get_price_to_display($product),
                            'quantity'     => $item->get_quantity(),
                            'item_variant' => $item->get_variation_id() ? $item->get_variation_id() : '',
                        ];
                    }
                }
            }
        }
        return ! empty($items) ? wp_json_encode($items) : null;
    }

    /**
     * Get JSON for cart items.
     *
     * @since 1.0.0
     * @return string|null JSON string of cart items or null.
     */
    private function get_cart_items_json()
    {
        $items = [];
        if (WC()->cart) {
            foreach (WC()->cart->get_cart() as $cart_item) {
                $product = $cart_item['data'];
                if ($product) {
                    $items[] = [
                        'item_id'      => $product->get_id(),
                        'item_name'    => $product->get_name(),
                        'price'        => wc_get_price_to_display($product),
                        'quantity'     => $cart_item['quantity'],
                        'item_variant' => ! empty($cart_item['variation_id']) ? $cart_item['variation_id'] : '',
                    ];
                }
            }
        }
        return ! empty($items) ? wp_json_encode($items) : null;
    }

    /**
     * Get JSON for product list items (e.g., shop or category page).
     *
     * @since 1.0.0
     * @return string|null JSON string of product list items or null.
     */
    private function get_product_list_items_json()
    {
        $items = [];
        if (is_shop() || is_product_category() || is_product_tag() || is_search()) {
            global $wp_query;
            if ($wp_query->have_posts()) {
                while ($wp_query->have_posts()) {
                    $wp_query->the_post();
                    $product = wc_get_product(get_the_ID());
                    if ($product) {
                        $items[] = [
                            'item_id'      => $product->get_id(),
                            'item_name'    => $product->get_name(),
                            'price'        => wc_get_price_to_display($product),
                            'quantity'     => 1,
                            'item_variant' => '',
                        ];
                    }
                }
                wp_reset_postdata();
            }
        }
        return ! empty($items) ? wp_json_encode($items) : null;
    }

    /**
     * Get JSON for the current product item (e.g., single product page).
     *
     * @since 1.0.0
     * @return string|null JSON string of current product item or null.
     */
    private function get_current_product_item_json()
    {
        if (is_product()) {
            $product = wc_get_product(get_the_ID());
            if ($product) {
                $item = [
                    'item_id'      => $product->get_id(),
                    'item_name'    => $product->get_name(),
                    'price'        => wc_get_price_to_display($product),
                    'quantity'     => 1,
                    'item_variant' => '',
                ];
                return wp_json_encode([$item]);
            }
        }
        return null;
    }

    /**
     * Get JSON for the current loop product (e.g., in shop loop).
     *
     * @since 1.0.0
     * @return string|null JSON string of current loop product or null.
     */
    private function get_current_loop_product_json()
    {
        $product = wc_get_product(get_the_ID());
        if ($product) {
            $item = [
                'item_id'      => $product->get_id(),
                'item_name'    => $product->get_name(),
                'price'        => wc_get_price_to_display($product),
                'quantity'     => 1,
                'item_variant' => '',
            ];
            return wp_json_encode([$item]);
        }
        return null;
    }

    /**
     * Get JSON for a removed cart item.
     *
     * @since 1.0.0
     * @param string $hook The triggered hook.
     * @param array  $args Hook arguments.
     * @return string|null JSON string of removed item or null.
     */
    private function get_removed_item_json($hook, $args)
    {
        if ('woocommerce_cart_item_removed' === $hook && ! empty($args[0]) && ! empty($args[1])) {
            $cart_item = $args[0];
            $product   = $cart_item['data'];
            if ($product) {
                $item = [
                    'item_id'      => $product->get_id(),
                    'item_name'    => $product->get_name(),
                    'price'        => wc_get_price_to_display($product),
                    'quantity'     => $cart_item['quantity'],
                    'item_variant' => ! empty($cart_item['variation_id']) ? $cart_item['variation_id'] : '',
                ];
                return wp_json_encode([$item]);
            }
        }
        return null;
    }

    /**
     * Get price for a removed cart item.
     *
     * @since 1.0.0
     * @param string $hook The triggered hook.
     * @param array  $args Hook arguments.
     * @return float|null Price of removed item or null.
     */
    private function get_removed_item_price($hook, $args)
    {
        if ('woocommerce_cart_item_removed' === $hook && ! empty($args[0]) && ! empty($args[1])) {
            $cart_item = $args[0];
            $product   = $cart_item['data'];
            if ($product) {
                return wc_get_price_to_display($product) * $cart_item['quantity'];
            }
        }
        return null;
    }
}

/**
 * Initialize the plugin.
 *
 * @since 1.0.0
 */
function ga_wp_integration_init()
{
    GA_WP_Integration::get_instance();
}
add_action('plugins_loaded', 'ga_wp_integration_init');
