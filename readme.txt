=== Google Analytics WP Integration ===
Contributors: shahinilderemi
Tags: google analytics, google tag manager, woocommerce, analytics, tracking
Requires at least: 5.0
Tested up to: 6.6
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Integrates WordPress and WooCommerce events with Google Analytics using Google Tag Manager for seamless event tracking.

== Description ==

Google Analytics WP Integration maps WordPress and WooCommerce hooks to Google Analytics events via Google Tag Manager (GTM). Events are available to the browser when a mapped hook runs while WordPress renders a frontend HTML page. Common uses include product views, cart actions, and paid order confirmations.

### Key Features
- **Google Tag Manager Integration**: Easily inject GTM code into your site with a simple configuration.
- **Event Mapping**: Map WordPress and WooCommerce hooks to Google Analytics events with customizable parameters.
- **WooCommerce Support**: Track key e-commerce events like `add_to_cart`, `purchase`, `view_item`, and more.
- **Admin Interface**: User-friendly settings page with live preview, event management, and preset loading.
- **Default Mappings**: Predefined mappings for common WordPress and WooCommerce events.
- **Customizable Parameters**: Define sources and patterns for event parameters to suit your tracking needs.
- **RTL Support**: Full support for right-to-left languages in the admin interface.
- **Debug Mode**: Optional debug output for administrators to verify event data.
- **Localization**: Translation-ready with support for multiple languages.

This plugin is ideal for website owners, developers, and marketers who want to leverage Google Analytics to gain insights into user behavior without complex coding.

== Installation ==

1. **Install the Plugin**:
   - Download the plugin from the WordPress Plugin Repository or upload the plugin ZIP file.
   - Go to **Plugins > Add New** in your WordPress admin dashboard, upload the ZIP file, and click **Install Now**.
   - Alternatively, install directly from the WordPress Plugin Directory by searching for "Google Analytics WP Integration".

2. **Activate the Plugin**:
   - After installation, click **Activate** to enable the plugin.

3. **Configure Settings**:
   - Navigate to **GA Integration** in the WordPress admin menu.
   - Enter your Google Tag Manager container ID (e.g., `GTM-XXXXXX`) in the settings page.
   - Configure event mappings or use the default mappings provided.

4. **Save and Test**:
   - Save your settings and verify that the GTM code is injected into your site’s `<head>` and `<body>` sections.
   - Use Google Tag Manager’s Preview mode or the plugin’s debug mode (`?ga_debug=1` URL parameter for admins) to test event tracking.

== Frequently Asked Questions ==

= Do I need a Google Tag Manager account to use this plugin? =
Yes, you need a Google Tag Manager account and a container ID (e.g., `GTM-XXXXXX`) to use this plugin. The plugin injects the GTM code and pushes events to the dataLayer for Google Analytics.

= Does this plugin support WooCommerce? =
Yes. The plugin auto-detects WooCommerce. Its frontend mappings include product views, cart actions, and paid classic-checkout confirmations. Background refunds, redirect-only actions, and API or AJAX responses require a separate event transport.

= Can I customize event mappings? =
Absolutely! The plugin provides a flexible admin interface to create, edit, or delete event mappings. You can specify hooks, Google Analytics events, parameters, and patterns to customize tracking.

= How do I reset mappings to default? =
In the plugin’s settings page, click the **Reset to Defaults** button to restore the default event mappings for WordPress and WooCommerce.

= Is the plugin translation-ready? =
Yes, the plugin is fully translation-ready. It includes a text domain (`ga-wp-integration`) and supports localization in the `/languages` directory.

= How can I debug events? =
Administrators can append `?ga_debug=1` to a frontend URL to see the events in their browser console. Google Tag Manager’s Preview mode can also inspect dataLayer events.

== Screenshots ==

1. **Settings Page**: Configure your Google Tag Manager container ID and manage event mappings.
2. **Event Mapping Interface**: Add, edit, or remove mappings with a live preview of the configuration.
3. **WooCommerce Events**: Predefined mappings for WooCommerce events like `add_to_cart` and `purchase`.
4. **Help & Documentation**: Built-in help tabs explaining hooks, parameter sources, and patterns.

== Changelog ==

= 1.0.0 =
* Initial release with Google Tag Manager integration, WordPress/WooCommerce event mapping, and admin interface.

== Upgrade Notice ==

= 1.0.0 =
This is the initial release. No upgrades are available yet. Please report any issues to the plugin author.

== Support ==

For support, please visit the [plugin support forum](https://wordpress.org/support/plugin/ga-wp-integration/) on WordPress.org or contact the author at [ildrm.com](https://ildrm.com).

== License ==

This plugin is licensed under the GPLv2 or later. See the [license file](https://www.gnu.org/licenses/gpl-2.0.html) for details.

== Credits ==

Developed by Shahin Ilderemi. Special thanks to the WordPress and WooCommerce communities for their invaluable resources and support.
