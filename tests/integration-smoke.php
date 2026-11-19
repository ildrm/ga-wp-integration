<?php

declare(strict_types=1);

/** Run on disposable local WordPress with both plugins active. All output is captured locally.
 * GA_WP_RUN_INTEGRATION=1 php tests/integration-smoke.php /path/to/wordpress [checkout|settings|legacy|rest|ajax|disabled]
 */

if (PHP_SAPI !== 'cli' || getenv('GA_WP_RUN_INTEGRATION') !== '1' || empty($argv[1])) {
    exit('Requires explicit opt-in and a disposable local WordPress path.');
}

$mode = $argv[2] ?? 'checkout';
if (!in_array($mode, ['checkout', 'settings', 'legacy', 'rest', 'ajax', 'disabled'], true)) {
    throw new RuntimeException('Unknown integration mode.');
}
if ($mode === 'rest') { define('REST_REQUEST', true); }
if ($mode === 'ajax') { define('DOING_AJAX', true); }

$parameters = [
    ['ga_param' => 'transaction_id', 'source' => 'order_id', 'pattern' => ''],
    ['ga_param' => 'value', 'source' => 'order_total', 'pattern' => ''],
    ['ga_param' => 'tax', 'source' => 'order_tax', 'pattern' => ''],
];
$legacyMappings = [
    ['event' => 'purchase', 'hooks' => ['woocommerce_payment_complete', 'woocommerce_order_status_completed'], 'parameters' => $parameters],
    ['event' => 'fixture_event', 'hooks' => ['ga_fixture_event'], 'parameters' => [
        ['ga_param' => 'email', 'source' => 'user.email', 'pattern' => ''],
        ['ga_param' => 'username', 'source' => 'user.username', 'pattern' => ''],
        ['ga_param' => 'private_meta', 'source' => 'meta._ga_fixture_private', 'pattern' => ''],
        ['ga_param' => 'public_meta', 'source' => 'meta.ga_fixture_public', 'pattern' => ''],
    ]],
];

if ($mode !== 'settings') {
    $GLOBALS['wp_filter']['pre_option_ga_wp_settings'][10][] = [
        'function' => static fn() => ['gtm_code' => $mode === 'disabled' ? 'bad container value' : 'GTM-LOCALTEST'],
        'accepted_args' => 0,
    ];
    if ($mode !== 'checkout' && $mode !== 'disabled') {
        $GLOBALS['wp_filter']['pre_option_ga_wp_mappings'][10][] = [
            'function' => static fn() => $legacyMappings,
            'accepted_args' => 0,
        ];
    }
}
$GLOBALS['wp_filter']['pre_http_request'][99][] = [
    'function' => static fn() => new WP_Error('ga_fixture_offline', 'Requests outside the fixture are disabled.'),
    'accepted_args' => 0,
];
$GLOBALS['wp_filter']['pre_wp_mail'][99][] = ['function' => static fn() => true, 'accepted_args' => 0];
require rtrim($argv[1], '/') . '/wp-load.php';

if (wp_get_environment_type() !== 'local' || !class_exists('GA_WP_Integration')) {
    throw new RuntimeException('Activate GA WP Integration on a disposable local WordPress installation.');
}

$checks = 0;
$expect = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) { throw new RuntimeException($message); }
    ++$checks;
};
$capture = static function (callable $callback): string {
    ob_start();
    try {
        $callback();
        return (string) ob_get_contents();
    } finally {
        ob_end_clean();
    }
};
$parse = static function (string $html): array {
    if (!preg_match('~\}\)\((\[[^\n]*\])\);</script>~', $html, $match)) {
        throw new RuntimeException('Expected a JSON-encoded analytics event list.');
    }
    return json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
};
$plugin = GA_WP_Integration::get_instance();

if ($mode === 'settings') {
    require_once ABSPATH . 'wp-admin/includes/admin.php';
    $plugin->register_settings();
    $registered = get_registered_settings();
    $expect($registered['ga_wp_settings']['group'] === $registered['ga_wp_mappings']['group'], 'Both options must share the settings form group.');
    $expect($plugin->sanitize_settings(['gtm_code' => ' gtm-fixture123 '])['gtm_code'] === 'GTM-FIXTURE123', 'Valid GTM IDs must normalize.');
    $expect($plugin->sanitize_settings(['gtm_code' => 'bad container value'])['gtm_code'] === '', 'Malformed GTM IDs must be rejected.');

    $before = get_option('ga_wp_mappings', false);
    try {
        update_option('ga_wp_mappings', $legacyMappings);
        $expect(get_option('ga_wp_mappings') === $legacyMappings, 'Valid legacy mappings must survive a settings write.');
        update_option('ga_wp_mappings', []);
        $expect(get_option('ga_wp_mappings') === [], 'Deleting all mappings must persist as an empty array.');
    } finally {
        if ($before === false) { delete_option('ga_wp_mappings'); }
        else { update_option('ga_wp_mappings', $before); }
    }
} elseif (in_array($mode, ['rest', 'ajax', 'disabled'], true)) {
    $html = $capture(static function () use ($plugin): void {
        $plugin->inject_gtm_code();
        $plugin->inject_gtm_noscript();
        do_action('ga_fixture_event');
        do_action('wp_login', 'fixture', get_user_by('id', 1));
        $plugin->render_events();
    });
    $expect($html === '', 'This request must contain no tracking output.');
} else {
    if (!function_exists('wc_create_order')) { throw new RuntimeException('Activate WooCommerce for checkout/legacy integration.'); }
    $product = new WC_Product_Variable();
    $product->set_name('Offline sample');
    $product->set_status('publish');
    $product->save();
    $variation = new WC_Product_Variation();
    $variation->set_parent_id($product->get_id());
    $variation->set_regular_price('10');
    $variation->set_price('10');
    $variation->save();

    $order = wc_create_order();
    if (is_wp_error($order)) { throw new RuntimeException('Could not create the local order fixture.'); }
    $order->set_currency('USD');
    $order->add_product($variation, 2);
    $order->set_shipping_total('3.50');
    $order->set_cart_tax('1.25');
    $order->set_total('24.75');
    $order->save();

    if ($mode === 'checkout') {
        $capture(static fn() => do_action('woocommerce_thankyou', $order->get_id()));
        $expect($capture([$plugin, 'render_events']) === '', 'Unpaid receipt must not emit a purchase event.');
        $order->set_date_paid(time());
        $order->set_status('processing');
        $order->save();
        $early = $capture(static function () use ($order): void {
            do_action('woocommerce_thankyou', $order->get_id());
            do_action('woocommerce_thankyou', $order->get_id());
        });
        $expect(!str_contains($early, 'window.dataLayer.push'), 'Receipt hooks must wait for the footer before emitting analytics.');

        $cart = new WC_Cart();
        $key = 'fixture-variation';
        $cart->set_removed_cart_contents([$key => [
            'data' => $variation, 'quantity' => 2, 'variation_id' => $variation->get_id(),
            'line_total' => 20, 'line_tax' => 0,
        ]]);
        do_action('woocommerce_cart_item_removed', $key, $cart);
        $events = $parse($capture([$plugin, 'render_events']));
        $expect(count($events) === 2, 'Duplicate purchase events must collapse per receipt, alongside the cart removal event.');
        [$purchase, $removed] = $events;
        $expect($purchase['event'] === 'purchase' && $purchase['transaction_id'] === (string) $order->get_id(), 'Paid receipt must identify its order safely.');
        $expect($purchase['value'] == 20 && $purchase['tax'] == 1.25 && $purchase['shipping'] == 3.50, 'Default purchase value must exclude separately reported shipping and tax.');
        $expect($purchase['items'][0]['item_variant'] == $variation->get_id() && $purchase['items'][0]['quantity'] == 2, 'Purchase must retain the variation and item count.');
        $expect($removed['event'] === 'remove_from_cart' && $removed['items'][0]['item_variant'] == $variation->get_id(), 'Cart removal must resolve the real WooCommerce key and variant.');
    } else {
        wp_set_current_user(1);
        $postId = wp_insert_post(['post_title' => 'Local fixture', 'post_status' => 'publish']);
        $GLOBALS['post'] = get_post($postId);
        update_post_meta($postId, '_ga_fixture_private', 'Server-only fixture metadata');
        $content = 'Local fixture </script> & "quoted"';
        update_post_meta($postId, 'ga_fixture_public', $content);
        do_action('ga_fixture_event');
        $order->set_status('processing');
        $order->set_date_paid(time());
        $order->save();
        do_action('woocommerce_payment_complete', $order->get_id());
        do_action('woocommerce_order_status_completed', $order->get_id());
        $html = $capture([$plugin, 'render_events']);
        $events = $parse($html);
        $expect(count($events) === 2, 'Saved legacy purchase hooks must remain effective and deduplicate.');
        [$custom, $purchase] = $events;
        $expect($custom === ['event' => 'fixture_event', 'public_meta' => $content], 'Direct identities and protected fields must be absent.');
        $expect(substr_count($html, '</script>') === 1, 'A literal closing tag in data must stay encoded in the dataLayer JSON.');
        $expect($purchase['event'] === 'purchase' && $purchase['value'] == 24.75, 'Existing order_total mappings must preserve the gross total.');
    }
    $expect($capture([$plugin, 'render_events']) === '', 'Rendering must drain the per-response event buffer.');
}

echo "PASS: {$mode}, {$checks} checks.\n";
