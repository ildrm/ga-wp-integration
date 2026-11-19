<?php

defined('ABSPATH') || exit;

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
    'woocommerce_thankyou',
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
    'order_items_value',
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

foreach ($mappings as $mapping) {
    $ga_events[] = $mapping['event'];
    $available_hooks = array_merge($available_hooks, $mapping['hooks']);
    foreach ($mapping['parameters'] as $param) {
        $ga_params[] = $param['ga_param'];
        $sources[] = $param['source'];
    }
}
$ga_events = array_values(array_unique($ga_events));
$available_hooks = array_values(array_unique($available_hooks));
$ga_params = array_values(array_unique($ga_params));
$sources = array_values(array_unique($sources));
?>
    <div class="ga-actions">
        <button type="submit" id="ga-reset-defaults" form="ga-mappings-reset-form" class="button"><?php esc_html_e('Reset to Defaults', 'ga-wp-integration'); ?></button>
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
                if (typeof $.fn.select2 !== 'function') {
                    return;
                }
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

            // Mapping counter
            let mappingCount = <?php echo count($mappings); ?>;

            // Reset to defaults
            $('#ga-reset-defaults').on('click', function(e) {
                if (!confirm('<?php echo esc_js(__('Reset all mappings to defaults?', 'ga-wp-integration')); ?>')) {
                    e.preventDefault();
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
                        hooks: ['woocommerce_thankyou'],
                        parameters: [{
                                ga_param: 'transaction_id',
                                source: 'order_id',
                                pattern: ''
                            },
                            {
                                ga_param: 'value',
                                source: 'order_items_value',
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

            function escapeAttribute(value) {
                return String(value).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            }

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
                                                       value="${escapeAttribute(param.pattern ?? "")}" placeholder="e.g., {product_id}-{variation_id}"
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
                const indexes = $paramsContainer.find('input[name$="[pattern]"]').map(function() {
                    const key = this.name.match(/\[parameters\]\[(\d+)\]/);
                    return key ? Number(key[1]) : -1;
                }).get();
                const paramCount = Math.max(-1, ...indexes) + 1;

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
            $('#ga-integration-settings').on('submit', function(e) {
                let isValid = true;
                $('.mapping-item').each(function() {
                    const $mapping = $(this);
                    const event = $mapping.find('.ga-event-select').val();
                    const $params = $mapping.find('.parameter-item');
                    if (!event || !($mapping.find('.ga-hooks-select').val() || []).length) {
                        isValid = false;
                        $mapping.find('.ga-event-select').addClass('error');
                    }
                    $params.each(function() {
                        const ga_param = $(this).find('.ga-param-select').val();
                        const source = $(this).find('.ga-source-select').val();
                        if ((ga_param || source) && (!ga_param || !source)) {
                            isValid = false;
                            $(this).find('select[aria-required="true"]').addClass('error');
                        }
                    });
                });
                if (!isValid) {
                    e.preventDefault();
                    alert('<?php echo esc_js(__('Select an event and at least one hook. Give each parameter a name and a source.', 'ga-wp-integration')); ?>');
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
