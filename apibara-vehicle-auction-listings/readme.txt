=== Apibara Vehicle Auction Listings ===
Contributors: apibara
Tags: vehicle auction, copart, iaai, car auction, auto listings
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.5.8
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display Copart and IAAI vehicle auction listings on your WordPress site using the Apibara API. Free plan available.

== Description ==

Apibara Vehicle Auction Listings helps WordPress site owners display Copart and IAAI vehicle auction listings using the Apibara API.

The plugin includes a modern built-in vehicle marketplace template inspired by the Apibara demo interface: sidebar filters, grid/list view, image sliders, badges, countdowns, VIN/Lot copy buttons, price panels, single lot pages, and a configurable contact form.

Vehicles are rendered live from the Apibara API. They are not stored as WordPress posts.

A free Apibara plan is available. Paid plans are optional and can be used when you need higher API limits or additional usage.

Need help with integration? Contact Apibara if you want to connect the Vehicle Auction API to your own CRM, marketplace, dealer website, importer platform or custom design. We can help with API integration, plugin setup, custom templates and adapting the design to your system.

= Main Features =

* Live vehicle auction listings from the Apibara API.
* Virtual SEO-friendly auction URLs.
* Modern listing template with filter sidebar.
* Grid and list result views.
* Mini image slider on listing cards.
* Lightbox-style image viewer.
* Live countdown timers.
* VIN and lot click-to-copy buttons.
* Configurable listing page blocks.
* Configurable single lot page blocks.
* Configurable single lot details table.
* Design settings for colors, spacing, radius, shadows, gallery mode and grid columns.
* Configurable contact popup form.
* Email notifications with vehicle data.
* Translation-ready using the apibara-vehicle-auction-listings text domain.

= Free Plan Available =

This plugin requires an Apibara API key.

Apibara offers a free plan that can be used to connect the plugin and display vehicle auction data. Paid plans are optional and intended for higher usage limits.

Pricing: https://apibara.tech/en/products/vehicle-auction-data-api

= External Service =

This plugin connects to the Apibara API, an external service provided by Apibara, to retrieve vehicle auction data.

The plugin sends requests to Apibara only after the site administrator enters an API key in the plugin settings.

Depending on the plugin settings and usage, the following data may be sent to Apibara:

* API key entered by the site administrator.
* Vehicle search or listing request parameters.
* Vehicle VIN when loading a single vehicle.
* Pagination and filtering parameters.
* The WordPress site URL may be included in standard HTTP headers.

The plugin uses this data only to retrieve vehicle auction listings and vehicle details from the Apibara API.

The plugin does not send contact form submissions to Apibara by default. Contact form submissions are sent by email from the WordPress site to the email address configured by the site administrator.

Apibara service pages:

* Terms of Service: https://apibara.tech/en/terms
* Privacy Policy: https://apibara.tech/en/privacy
* Pricing: https://apibara.tech/en/products/vehicle-auction-data-api
* Website: https://apibara.tech

Apibara is not affiliated with, endorsed by, or sponsored by Copart, IAAI, or their parent companies. All trademarks belong to their respective owners.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/apibara-vehicle-auction-listings/` directory, or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the Plugins screen in WordPress.
3. Go to Apibara Vehicles in the WordPress admin area.
4. Enter your Apibara API key.
5. Configure general settings, filters, design, fields, and contact form settings.
6. Go to Settings > Permalinks and click Save Changes.
7. Open `/auctions/` or add `[apibara_vehicles]` to any page.

== Frequently Asked Questions ==

= Do I need an Apibara account? =

Yes. The plugin requires an Apibara API key to retrieve vehicle auction data.

= Is there a free plan? =

Yes. Apibara offers a free plan. Paid plans are optional for higher usage limits.

= Are vehicles stored in WordPress? =

No. Vehicles are rendered live from the Apibara API.

= Can I customize the template? =

Yes. The plugin includes separate settings for listing page blocks, single lot page blocks, single lot details table fields, filters, design, and contact form fields.

= Can I customize vehicle URLs? =

Yes. You can configure the base slug and URL pattern. Example: `/auctions/copart/46205636/2020-chevrolet-equinox-lt/2gnaxkev5l6257738/`.

= Does single vehicle loading use the full URL? =

No. The public URL can include platform, lot number, slug and VIN, but the plugin sends only VIN to the Apibara single vehicle API endpoint.

= Can Apibara help with custom integration or design? =

Yes. If you want to connect Apibara Vehicle Auction API to your own CRM, marketplace, dealer website, importer platform or custom design, contact us at admin@apibara.tech. We can help with API integration, plugin setup, custom templates and adapting the design to your system.

== Screenshots ==

1. Vehicle auction listing page with live vehicle cards, image previews, prices, statuses and the filter sidebar.
2. Single vehicle detail page with gallery, VIN, lot number, auction information, price panel and key vehicle specifications.
3. Contact popup on the single vehicle page, allowing visitors to send an inquiry about a selected vehicle.
4. API Key admin tab with Apibara API key setup, quick links and plan usage statistics.
5. General settings tab for configuring the listing URL structure, vehicles per page, view mode and frontend behavior.
6. Filter panel settings for enabling API filters, visible filter blocks and fallback filter values.
7. Design settings tab for customizing colors, card style, gallery mode, image ratio and responsive grid columns.
8. Fields & Template settings for choosing listing blocks, single vehicle blocks and the fields shown in the single lot details table.
9. Contact form settings for enabling the inquiry form, recipient email, form labels, required fields and success message.
10. Documentation tab with listing URLs, shortcode examples and setup instructions.

== Changelog ==

= 0.5.0 =
* Added modern built-in listing template inspired by the Apibara demo layout.
* Added filter sidebar with configurable filter groups and range sliders.
* Added grid/list result views.
* Added listing card mini sliders, badges, countdowns, price panels, and VIN/Lot copy buttons.
* Added separate listing blocks, single lot blocks, and single details table settings.
* Improved design settings.
* Kept API endpoints internal and hidden from the admin UI.

= 0.4.0 =
* Added tabs in admin settings.
* Removed API URL settings from admin UI.
* Rendered vehicles live instead of storing them as posts.
