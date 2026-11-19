<?php

/**
 * Plugin Name: Google Analytics WP Integration
 * Description: Integrates WordPress and WooCommerce events with Google Analytics by mapping hooks to GA events.
 * Version: 1.0.0
 * Requires at least: 5.0
 * Requires PHP: 7.4
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

    /** Events collected for the current HTML response. */
    private $pending_events = [];

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
        $this->ga_settings = $this->sanitize_settings(get_option('ga_wp_settings', []));
        $saved_mappings = get_option('ga_wp_mappings', false);
        $this->event_mappings = is_array($saved_mappings) ? $this->sanitize_mappings($saved_mappings) : $this->default_mappings;

        add_action('init', [$this, 'load_textdomain']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_post_ga_wp_reset_mappings', [$this, 'reset_mappings']);

        if (! empty($this->ga_settings['gtm_code'])) {
            add_action('wp_head', [$this, 'inject_gtm_code']);
            add_action('wp_body_open', [$this, 'inject_gtm_noscript']);
            add_action('wp_footer', [$this, 'render_events'], 50);
            $this->register_event_hooks();
        }
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
                ],
            ],
            [
                'event'      => 'sign_up',
                'hooks'      => ['user_register'],
                'parameters' => [
                    ['ga_param' => 'method', 'source' => 'wordpress', 'pattern' => ''],
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
                    'event'      => 'purchase',
                    'hooks'      => ['woocommerce_thankyou'],
                    'parameters' => [
                        ['ga_param' => 'transaction_id', 'source' => 'order_id', 'pattern' => ''],
                        ['ga_param' => 'value', 'source' => 'order_items_value', 'pattern' => ''],
                        ['ga_param' => 'currency', 'source' => 'order_currency', 'pattern' => ''],
                        ['ga_param' => 'tax', 'source' => 'order_tax', 'pattern' => ''],
                        ['ga_param' => 'shipping', 'source' => 'order_shipping', 'pattern' => ''],
                        ['ga_param' => 'items', 'source' => 'items_json', 'pattern' => ''],
                        ['ga_param' => 'coupon', 'source' => 'order_coupon_codes', 'pattern' => ''],
                        ['ga_param' => 'affiliation', 'source' => 'site_name', 'pattern' => ''],
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
        wp_enqueue_style('ga-wp-select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', [], '4.1.0-rc.0');
        wp_enqueue_style('ga-wp-event-mappings', plugins_url('assets/css/event-mappings.css', __FILE__), ['ga-wp-select2'], '1.0.0');
        wp_enqueue_script('ga-wp-select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], '4.1.0-rc.0', true);

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
        register_setting('ga_wp_settings_group', 'ga_wp_mappings', ['sanitize_callback' => [$this, 'sanitize_mappings']]);

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
        $id = isset($input['gtm_code']) && is_string($input['gtm_code']) ? strtoupper(trim($input['gtm_code'])) : '';
        return ['gtm_code' => preg_match('/^GTM-[A-Z0-9]+$/D', $id) ? $id : ''];
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
        foreach ((array) $input as $mapping) {
            if (! is_array($mapping)) {
                continue;
            }
            $event = sanitize_text_field($mapping['event'] ?? '');
            $hooks = array_values(array_unique(array_filter(array_map('sanitize_text_field', (array) ($mapping['hooks'] ?? [])))));
            $hooks = array_values(array_filter($hooks, static function ($hook) {
                return preg_match('~^[A-Za-z0-9_.:/-]+$~D', $hook);
            }));
            if (! preg_match('/^[A-Za-z][A-Za-z0-9_]{0,39}$/D', $event) || ! $hooks) {
                continue;
            }
            $parameters = [];
            foreach ((array) ($mapping['parameters'] ?? []) as $param) {
                if (! is_array($param)) {
                    continue;
                }
                $name = sanitize_text_field($param['ga_param'] ?? '');
                $source = sanitize_text_field($param['source'] ?? '');
                if ('event' !== $name && preg_match('/^[A-Za-z][A-Za-z0-9_]{0,39}$/D', $name) && preg_match('/^[A-Za-z0-9_.:-]+$/D', $source)) {
                    $parameters[] = [
                        'ga_param' => $name,
                        'source'   => $source,
                        'pattern'  => sanitize_text_field($param['pattern'] ?? ''),
                    ];
                }
            }
            $sanitized[] = ['event' => $event, 'hooks' => $hooks, 'parameters' => $parameters];
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
        require __DIR__ . '/views/event-mappings.php';
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

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Google Analytics Integration Settings', 'ga-wp-integration'); ?></h1>
            <?php settings_errors(); ?>
            <form id="ga-integration-settings" method="post" action="options.php">
                <?php
                settings_fields('ga_wp_settings_group');
                do_settings_sections('ga-wp-integration');
                submit_button(__('Save Settings', 'ga-wp-integration'));
                ?>
            </form>
            <form id="ga-mappings-reset-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="ga_wp_reset_mappings">
                <?php wp_nonce_field('ga_wp_reset_mappings'); ?>
            </form>
        </div>
    <?php
    }

    public function reset_mappings()
    {
        if (! current_user_can('manage_options') || 'POST' !== ($_SERVER['REQUEST_METHOD'] ?? '')) {
            wp_die(esc_html__('You do not have permission to reset these mappings.', 'ga-wp-integration'), '', ['response' => 403]);
        }
        check_admin_referer('ga_wp_reset_mappings');
        delete_option('ga_wp_mappings');
        wp_safe_redirect(admin_url('admin.php?page=ga-wp-integration'));
        exit;
    }

    /**
     * Inject Google Tag Manager code in the head.
     *
     * @since 1.0.0
     */
    public function inject_gtm_code()
    {
        if (! $this->tracking_allowed()) {
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
        if (! $this->tracking_allowed()) {
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
        if (empty($mapping['event']) || ! $this->tracking_allowed()) {
            return;
        }

        // Event-specific conditions
        if ('view_item' === $mapping['event'] && (! function_exists('is_product') || ! is_product())) {
            return;
        }
        if ('view_item_list' === $mapping['event'] && (! function_exists('is_shop') || ! (is_shop() || is_product_category() || is_product_tag() || is_search()))) {
            return;
        }

        if ('purchase' === $mapping['event'] && 'woocommerce_thankyou' === $hook) {
            $order = function_exists('wc_get_order') && ! empty($args[0]) ? wc_get_order($args[0]) : false;
            if (! $order || ! $order->is_paid()) {
                return;
            }
        }

        $event_data = [];
        if (! empty($mapping['parameters'])) {
            foreach ($mapping['parameters'] as $param) {
                if (empty($param['ga_param']) || empty($param['source'])) {
                    continue;
                }

                $value = $this->get_parameter_value($param['source'], $param['pattern'] ?? '', $hook, $args);

                if ('items' === $param['ga_param']) {
                    $items = is_string($value) ? json_decode($value, true) : $value;
                    if (is_array($items) && array_values($items) === $items) {
                        $event_data['items'] = $items;
                    }
                    continue;
                }

                if (is_scalar($value)) {
                    $numeric_parameters = ['value', 'price', 'quantity', 'tax', 'shipping'];
                    $event_data[$param['ga_param']] = is_numeric($value) && in_array($param['ga_param'], $numeric_parameters, true)
                        ? (float) $value
                        : $value;
                }
            }
        }

        // Skip if essential data is missing
        if (in_array($mapping['event'], ['view_item', 'view_item_list', 'add_to_cart'], true) && empty($event_data['items'])) {
            return;
        }

        $event = ['event' => $mapping['event']] + $event_data;
        if ('purchase' === $mapping['event'] && ! empty($event['transaction_id'])) {
            $event['transaction_id'] = (string) $event['transaction_id'];
            $this->pending_events['purchase:' . $event['transaction_id']] = $event;
        } else {
            $this->pending_events[] = $event;
        }
    }

    private function tracking_allowed()
    {
        if (empty($this->ga_settings['gtm_code']) || is_admin() || wp_doing_ajax() || wp_doing_cron()
            || (defined('REST_REQUEST') && REST_REQUEST) || (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST)
            || (defined('WP_CLI') && WP_CLI) || (did_action('wp') && (is_feed() || is_embed() || is_preview()))) {
            return false;
        }
        if (did_action('wp') && is_singular()) {
            $post = get_queried_object();
            if ($post instanceof WP_Post && ('publish' !== $post->post_status || '' !== $post->post_password)) {
                return false;
            }
        }
        return (bool) apply_filters('ga_wp_tracking_allowed', true);
    }

    public function render_events()
    {
        if (! $this->pending_events || ! $this->tracking_allowed()) {
            return;
        }

        $events = wp_json_encode(array_values($this->pending_events), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $this->pending_events = [];
        if (false === $events) {
            return;
        }
        $debug = current_user_can('manage_options') && isset($_GET['ga_debug']) && '1' === $_GET['ga_debug'];
        echo '<script>(function(events){window.dataLayer=window.dataLayer||[];events.forEach(function(event){window.dataLayer.push(event);';
        if ($debug) {
            echo 'console.debug("[GA Integration]", event);';
        }
        echo '});})(' . $events . ');</script>';
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
        } elseif ('user.id' === $source || 'user_id' === $source) {
            $user = wp_get_current_user();
            if ('wp_login' === $hook && isset($args[1]) && $args[1] instanceof WP_User) {
                $user = $args[1];
            }
            $value = $user->ID > 0 ? (string) $user->ID : null;
        } elseif ('user.role' === $source) {
            $roles = wp_get_current_user()->roles;
            $value = $roles ? reset($roles) : null;
        } elseif (strpos($source, 'taxonomy.') === 0) {
            $tax_parts = explode('.', $source, 3);
            if (count($tax_parts) >= 3) {
                $taxonomy     = $tax_parts[1];
                $tax_property = $tax_parts[2];
                $term = get_queried_object();
                if ($term instanceof WP_Term && $term->taxonomy === $taxonomy) {
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
            if ($post && 'publish' === $post->post_status && '' === $post->post_password) {
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
                        $value = ! is_protected_meta($post_property, 'post') ? get_post_meta($post->ID, $post_property, true) : null;
                }
            }
        } elseif (strpos($source, 'meta.') === 0) {
            // Handle custom meta fields
            $meta_key = substr($source, 5);
            if ('woocommerce_currency' === $meta_key && function_exists('get_woocommerce_currency')) {
                $value = get_woocommerce_currency();
            } else {
                $post = get_post();
                if ($post && 'publish' === $post->post_status && '' === $post->post_password && ! is_protected_meta($meta_key, 'post')) {
                    $value = get_post_meta($post->ID, $meta_key, true);
                }
            }
        } elseif (strpos($source, 'jet.') === 0) {
            // Handle JetEngine custom fields
            $field_name = substr($source, 4);
            if (function_exists('jet_engine') && ! is_protected_meta($field_name, 'post')) {
                $data = jet_engine()->listings->data ?? null;
                if (is_object($data) && is_callable([$data, 'get_meta'])) {
                    $value = $data->get_meta($field_name);
                }
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
                        $product = wc_get_product(! empty($args[3]) ? $args[3] : $args[1]);
                        $value   = $product ? wc_get_price_to_display($product) * $args[2] : null;
                    }
                    break;
                case 'quantity':
                    if ('woocommerce_add_to_cart' === $hook && ! empty($args[2])) {
                        $value = $args[2];
                    }
                    break;
                case 'variation_id':
                    if ('woocommerce_add_to_cart' === $hook && ! empty($args[3])) {
                        $value = $args[3];
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
                    if (in_array($hook, ['woocommerce_thankyou', 'woocommerce_payment_complete', 'woocommerce_order_status_completed', 'woocommerce_order_status_refunded'], true) && ! empty($args[0])) {
                        $value = $args[0];
                    }
                    break;
                case 'order_total':
                    if (in_array($hook, ['woocommerce_thankyou', 'woocommerce_payment_complete', 'woocommerce_order_status_completed', 'woocommerce_order_status_refunded'], true) && ! empty($args[0])) {
                        $order = wc_get_order($args[0]);
                        $value = $order ? $order->get_total() : null;
                    }
                    break;
                case 'order_items_value':
                    if ('woocommerce_thankyou' === $hook && ! empty($args[0])) {
                        $order = wc_get_order($args[0]);
                        if ($order) {
                            $value = array_sum(array_map(static function ($item) {
                                return (float) $item->get_total();
                            }, $order->get_items()));
                        }
                    }
                    break;
                case 'order_currency':
                    if (in_array($hook, ['woocommerce_thankyou', 'woocommerce_payment_complete', 'woocommerce_order_status_completed', 'woocommerce_order_status_refunded'], true) && ! empty($args[0])) {
                        $order = wc_get_order($args[0]);
                        $value = $order ? $order->get_currency() : null;
                    }
                    break;
                case 'order_tax':
                    if (in_array($hook, ['woocommerce_thankyou', 'woocommerce_payment_complete', 'woocommerce_order_status_completed'], true) && ! empty($args[0])) {
                        $order = wc_get_order($args[0]);
                        $value = $order ? $order->get_total_tax() : null;
                    }
                    break;
                case 'order_shipping':
                    if (in_array($hook, ['woocommerce_thankyou', 'woocommerce_payment_complete', 'woocommerce_order_status_completed'], true) && ! empty($args[0])) {
                        $order = wc_get_order($args[0]);
                        $value = $order ? $order->get_shipping_total() : null;
                    }
                    break;
                case 'order_coupon_codes':
                    if (in_array($hook, ['woocommerce_thankyou', 'woocommerce_payment_complete', 'woocommerce_order_status_completed'], true) && ! empty($args[0])) {
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
            $value = $this->apply_pattern($pattern, $hook, $args);
        }

        return $value;
    }

    /**
     * Apply a pattern to format the parameter value.
     *
     * @since 1.0.0
     * @param string $pattern The pattern to apply.
     * @param string $hook    The triggered hook.
     * @param array  $args    Hook arguments.
     * @return string The formatted value.
     */
    private function apply_pattern($pattern, $hook, $args)
    {
        $replacements = [];
        preg_match_all('/\{([^\}]+)\}/', $pattern, $matches);
        if (! empty($matches[1])) {
            foreach ($matches[1] as $placeholder) {
                $placeholder_value = $this->get_parameter_value($placeholder, '', $hook, $args);
                $replacements['{' . $placeholder . '}'] = is_scalar($placeholder_value) ? (string) $placeholder_value : '';
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
            $product = wc_get_product(! empty($args[3]) ? $args[3] : $args[1]);
            if ($product) {
                $items[] = [
                    'item_id'      => $product->get_id(),
                    'item_name'    => $product->get_name(),
                    'price'        => wc_get_price_to_display($product),
                    'quantity'     => $args[2],
                    'item_variant' => ! empty($args[3]) ? $args[3] : '',
                ];
            }
        } elseif (in_array($hook, ['woocommerce_thankyou', 'woocommerce_payment_complete', 'woocommerce_order_status_completed', 'woocommerce_order_status_refunded'], true) && ! empty($args[0])) {
            $order = wc_get_order($args[0]);
            if ($order) {
                foreach ($order->get_items() as $item) {
                    $items[] = [
                        'item_id'      => (string) ($item->get_variation_id() ?: $item->get_product_id()),
                        'item_name'    => $item->get_name(),
                        'price'        => (float) $order->get_item_total($item),
                        'quantity'     => $item->get_quantity(),
                        'item_variant' => (string) ($item->get_variation_id() ?: ''),
                    ];
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
            foreach ((array) $wp_query->posts as $post) {
                $product = wc_get_product($post instanceof WP_Post ? $post->ID : $post);
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
        $cart_item = $this->get_removed_item($hook, $args);
        if ($cart_item) {
            $product = $cart_item['data'] ?? null;
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
        $cart_item = $this->get_removed_item($hook, $args);
        if ($cart_item) {
            $product = $cart_item['data'] ?? null;
            if ($product) {
                return wc_get_price_to_display($product) * $cart_item['quantity'];
            }
        }
        return null;
    }

    private function get_removed_item($hook, $args)
    {
        if ('woocommerce_cart_item_removed' !== $hook || ! isset($args[0], $args[1]) || ! $args[1] instanceof WC_Cart) {
            return null;
        }
        $cart = $args[1];
        $removed = $cart->get_removed_cart_contents();
        return $removed[$args[0]] ?? null;
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
