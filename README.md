# Google Analytics WP Integration

A WordPress plugin that seamlessly integrates WordPress and WooCommerce events with Google Analytics using Google Tag Manager (GTM) for robust event tracking.

![Plugin Banner](https://img.shields.io/badge/WordPress-Plugin-blue.svg) ![License](https://img.shields.io/badge/License-GPLv2%20or%20later-green.svg) ![Version](https://img.shields.io/badge/Version-1.0.0-brightgreen.svg)

## Overview

The **Google Analytics WP Integration** plugin enables WordPress and WooCommerce site owners to track user interactions by mapping WordPress hooks to Google Analytics events via Google Tag Manager. It provides a user-friendly admin interface to configure event mappings, supports WooCommerce e-commerce tracking, and includes default mappings for common events.

### Features
- **Google Tag Manager Integration**: Inject GTM code with a simple container ID setup.
- **Flexible Event Mapping**: Map WordPress and WooCommerce hooks to Google Analytics events with customizable parameters.
- **WooCommerce Support**: Track e-commerce events like `add_to_cart`, `purchase`, `view_item`, and more.
- **Admin Interface**: Intuitive settings page with live preview, preset loading, and event management.
- **Default Mappings**: Predefined mappings for WordPress and WooCommerce events.
- **Custom Parameters**: Define sources and patterns for event parameters.
- **RTL Support**: Full compatibility with right-to-left languages in the admin UI.
- **Debug Mode**: Debug output for administrators to verify event data.
- **Translation-Ready**: Supports localization with a dedicated text domain (`ga-wp-integration`).

## Installation

1. **Download the Plugin**:
   - Clone this repository: `git clone https://github.com/ildrm/ga-wp-integration.git`
   - Or download the ZIP file from the [Releases](https://github.com/ildrm/ga-wp-integration/releases) page.

2. **Install the Plugin**:
   - Upload the `ga-wp-integration` folder to the `/wp-content/plugins/` directory of your WordPress installation.
   - Alternatively, upload the ZIP file via **Plugins > Add New > Upload Plugin** in the WordPress admin dashboard.

3. **Activate the Plugin**:
   - Go to **Plugins** in the WordPress admin dashboard and activate **Google Analytics WP Integration**.

4. **Configure Settings**:
   - Navigate to **GA Integration** in the WordPress admin menu.
   - Enter your Google Tag Manager container ID (e.g., `GTM-XXXXXX`).
   - Configure event mappings or use the default mappings provided.

5. **Test the Setup**:
   - Save your settings and verify GTM code injection in the `<head>` and `<body>` sections.
   - Use Google Tag Manager’s Preview mode or add `?ga_debug=1` to a frontend URL while signed in as an administrator to see events in your browser console.

## Usage

1. **Configure GTM**:
   - Enter your GTM container ID in the plugin’s settings page under **GA Integration > Settings**.
   - The plugin automatically injects GTM scripts and noscript iframes.

2. **Manage Event Mappings**:
   - Add, edit, or remove event mappings in the settings page.
   - Map WordPress/WooCommerce hooks (e.g., `wp_login`, `woocommerce_add_to_cart`) to Google Analytics events (e.g., `login`, `add_to_cart`).
   - Customize parameters using sources (e.g., `product_id`, `order_total`) and patterns (e.g., `{product_id}-{variation_id}`).

3. **Debugging**:
   - Append `?ga_debug=1` to a frontend URL while signed in as an administrator to log events to the browser console.
   - Use Google Tag Manager’s Preview mode to monitor `dataLayer` events.

4. **WooCommerce Tracking**:
   - The plugin automatically detects WooCommerce and provides mappings for e-commerce events.
   - Default purchase tracking uses the paid order confirmation in classic WooCommerce checkout. It sends the order ID as the transaction ID and the sum of line totals as the event value, with tax and shipping as separate fields.
   - Existing sites that map purchases to background payment hooks can select `woocommerce_thankyou` in Event Mappings. The browser data layer requires a rendered HTML page. Redirect-only login and registration, background refunds, AJAX, and API-only workflows need a separate event transport.

## Tracking controls

The plugin starts tracking after a valid GTM container ID is saved. A consent integration can return `false` from the `ga_wp_tracking_allowed` filter to suppress the GTM script, fallback iframe, and event data. Sites that vary tracking by visitor consent must apply the same policy to their page cache.

Map only public, non-identifying values. Direct email, username, display name, and arbitrary user metadata sources are excluded, as are protected post metadata values.

## Requirements

- **WordPress**: Version 5.0 or higher
- **PHP**: Version 7.4 or higher
- **WooCommerce**: Optional, for e-commerce tracking (version 3.0 or higher recommended)
- **Google Tag Manager**: A valid GTM container ID

## Development

### Folder Structure
```
ga-wp-integration/
├── ga-wp-integration.php
├── LICENSE
├── readme.txt
└── README.md
```

### Contributing
Contributions are welcome! To contribute:

1. Fork the repository.
2. Create a feature branch (`git checkout -b feature/your-feature`).
3. Commit your changes (`git commit -m 'Add your feature'`).
4. Push to the branch (`git push origin feature/your-feature`).
5. Open a Pull Request.

Please ensure your code follows WordPress coding standards and includes appropriate documentation.

### Building the Plugin
- Update the version number in `ga-wp-integration.php` and this README.
- Test thoroughly on a WordPress installation with and without WooCommerce.
- Create a ZIP file for distribution: `zip -r ga-wp-integration.zip ga-wp-integration/ -x "*.git*"`.

Run the integration checks against a disposable WordPress install with WooCommerce and this plugin active.

```sh
for mode in checkout settings legacy rest ajax disabled; do
  GA_WP_RUN_INTEGRATION=1 php tests/integration-smoke.php /path/to/wordpress "$mode" || exit 1
done
```

## Changelog

### 1.0.0
- Initial release with GTM integration, event mapping, and WooCommerce support.

## License

This plugin is licensed under the [GNU General Public License v2 or later](https://www.gnu.org/licenses/gpl-2.0.html).

## Credits

- **Author**: Shahin Ilderemi ([ildrm.com](https://ildrm.com))
- **Contributors**: Open to contributions via GitHub.
- **Thanks**: To the WordPress and WooCommerce communities for their resources and support.

## Support

For issues, feature requests, or questions:
- Open an issue on the [GitHub Issues page](https://github.com/ildrm/ga-wp-integration/issues).
- Contact the author via [ildrm.com](https://ildrm.com).
