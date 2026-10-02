=== Barkoda Shipping for WooCommerce ===
Contributors: emreteymuroglu
Tags: woocommerce, shipping, kargo, barcode, turkey
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create PTT Kargo shipments from WooCommerce orders: barcodes, 80mm thermal labels, tracking and courier pickup.

== Description ==

Barkoda Shipping for WooCommerce connects your store to the PTT Kargo SOAP integration service. From the WooCommerce order screens you can create a barcoded shipment, print an 80mm thermal label, follow the parcel's movements and request a courier pickup, without retyping order data into a separate portal.

**This plugin requires a PTT integration contract.** PTT issues you a customer number, a password and a block of barcode numbers. Without those the plugin has nothing to authenticate with. See the FAQ for how to apply.

= Features =

* Order list with a "Ship" action, plus a WooCommerce bulk action
* Single-package shipments via `kabulEkle2`
* Multi-package shipments via `kabulEkleParcaliBarkod`, one barcode per package
* Cancel a shipment PTT has not accepted yet, by barcode or by reference number
* Tracking by barcode or reference, with the parcel's movement history
* The PTT branch currently holding the parcel, with address, hours and a map link
* Courier pickup requests via `siparisIstekEkle2`
* 80mm thermal labels rendered as HTML and Code128 SVG, so any 80mm printer works
* Bulk label printing: every selected order in one document with page breaks
* Per-order insurance toggle (Valuable Goods / DK service code)
* Cash on delivery mapped from your WooCommerce payment methods
* Optional separate return address
* Missing recipient data is detected before sending and can be filled in from a popup
* Weight and dimensions computed from your products, overridable per order
* Full request and response log for every SOAP call, with passwords masked
* WooCommerce HPOS compatible

= Built for reliability =

PTT allocates a finite block of barcode numbers, so the plugin treats them as a scarce resource. Numbers are handed out inside a database transaction, so two simultaneous orders can never receive the same barcode. If a shipment fails, the consumed number is held against the order and reused on the next attempt rather than burned.

Your PTT password is stored AES-256-CBC encrypted, keyed from your site's `AUTH_KEY`, and is masked out of every log entry before it is written.

= Disclaimer =

This plugin is not developed, endorsed or supported by PTT. It is an independent client for the SOAP services PTT provides to contracted customers. PTT and PTT Kargo are trademarks of their respective owner.

== External services ==

This plugin sends data to PTT (Posta ve Telgraf Teskilati A.S.), the Turkish postal service, which is required for it to function. No data is sent anywhere else, and nothing is transmitted until you enter PTT credentials and act on an order.

Requests go to `pttws.ptt.gov.tr`, using the test endpoints `PttVeriYuklemeTest` and `GonderiTakipV2Test` or the live endpoints `PttVeriYukleme` and `GonderiTakipV2`, depending on the environment you select.

Data is sent when you ship an order, cancel a shipment, track a parcel, request a courier or use the connection test. Depending on the action it includes:

* your PTT customer number and password
* the recipient's name, address, province, district, postcode, phone number and email address
* the order's weight, dimensions, total value and payment method
* the sender details you configure in the plugin settings
* the barcode or customer reference number of the shipment

PTT's terms of service: https://www.ptt.gov.tr/Sayfalar/Kurumsal/KullanimKosullari.aspx
PTT's privacy policy: https://www.ptt.gov.tr/Sayfalar/Kurumsal/KisiselVerilerinKorunmasi.aspx

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install the ZIP through Plugins, then Add New, then Upload Plugin.
2. Activate the plugin. WooCommerce must be installed and active.
3. Go to PTT Kargo, then Settings, and work through the tabs:
   * **PTT Connection** - leave the environment on Test, then enter the customer number and password PTT issued. Use "Test Connection" to confirm they work.
   * **Barcode** - enter the 8-digit prefix and the serial range PTT allocated. Prefix plus serial must total 12 digits; the 13th is calculated for you.
   * **Sender** - your company name, address, phone and email. Add the Postal Cheque account number if you ship cash on delivery.
   * **Products & Filters**, **Shipment Defaults**, **Payment** and **Label** are optional, with sensible defaults.
4. Create a test shipment, then email the resulting barcode to `entegrasyon@ptt.gov.tr` for approval.
5. Once PTT approves, switch the environment to Live.

== Frequently Asked Questions ==

= Do I need a contract with PTT? =

Yes. The plugin talks to the integration service PTT provides to contracted customers, and PTT issues the customer number, password and barcode range it needs. Contact your regional PTT directorate, or email `entegrasyon@ptt.gov.tr`, to start the process. Both corporate and individual integrations are possible.

= Do I need a driver for my thermal printer? =

No. The label is plain HTML and CSS sized for 80mm, printed through your browser's print dialog. Any 80mm printer your operating system already knows about will work.

= Where is my PTT password stored? =

Encrypted with AES-256-CBC in the `barkoda_settings` option. The key is derived from your site's `AUTH_KEY`, so the stored value is useless without your `wp-config.php`. The password is also masked in the plugin's log table and in order meta, so it never appears in a saved request.

= What happens when the barcode range runs out? =

Shipping fails with a clear error and no barcode is consumed. Ask PTT for a new range and enter it on the Barcode tab, which also shows how many numbers you have left.

= A shipment failed. Did I lose a barcode? =

No. The consumed number is stored against the order and reused on the next attempt, so a failing order does not eat through your allocation.

= Can I cancel a shipment? =

Only while PTT has not physically accepted it, which in practice means before the courier collects it. The plugin tries `barkodVeriSil` first and falls back to `referansVeriSil`. After cancelling, resending the order consumes a new barcode.

= Why does tracking say the barcode is not found in the test environment? =

PTT runs acceptance and tracking on separate databases in test, so a barcode accepted in the test environment is not visible to the test tracking service. This resolves once you switch to Live.

= Is the plugin available in Turkish? =

Yes. The Turkish translation is served through translate.wordpress.org, so WordPress fetches and applies it by itself on a site running in Turkish, and updates it whenever the translation improves. Corrections and other languages are welcome at https://translate.wordpress.org/projects/wp-plugins/barkoda-shipping-for-woocommerce/.

== Screenshots ==

1. The order list, with the shipping status of each order and the action that creates the shipment.
2. The shipment popup, showing recipient data, auto-computed weight and dimensions, and the insurance toggle.
3. An 80mm thermal label with the Code128 barcode.
4. The panel on the order edit screen, with the barcode, the tracking link and the label and cancel actions.
5. The settings screen with the live label preview.
6. The integration log, showing the full request and response for each SOAP call.

== Changelog ==

= 2.2.0 =
* Renamed to Barkoda Shipping for WooCommerce. The plugin is no longer named after a single carrier, which leaves room for other carriers later.
* Every option, order meta key, hook, constant and the log table moved onto a `barkoda` prefix. Stored data is migrated automatically on upgrade; the stored PTT password carries over unchanged.
* Fixed activation on sites upgrading from an earlier version, where the log table was created before the migration ran and the migration then discarded the existing log rows.
* The PHP version notice is now translatable instead of hardcoded Turkish.

= 2.1.0 =
* The plugin is now in English, with a Turkish translation bundled. Turkish sites see no change in wording.
* Renamed internal identifiers to a consistent prefix. Stored settings, order meta and the log table are migrated automatically on upgrade.
* The order status setting key no longer contains a non-ASCII character.
* Added translator comments to every string containing a placeholder.
* Codebase brought in line with the WordPress coding standards.
* Uninstall now removes order meta as well as options and the log table, under both the old and the new key names.

= 2.0.1 =
* Fixed the `siparisIstekEkle2` courier envelope to match the WSDL: snake_case field names, lowercase `gondericibilgi` wrapper, alphabetical element order, and `sonucKodu`/`sonucAciklama` response parsing.
* Moved `gondericibilgi` to its correct position in the `kabulEkle2` and `kabulEkleParcaliBarkod` sequences. Axis2 enforces sequence order strictly and failed silently when it was wrong.
* Added the `gonderici_soyadi` and `gonderici_sms` fields, which the WSDL defines but the plugin never sent.
* Fixed expired label links. `wp_nonce_url()` HTML-escapes the separator, which dropped `_wpnonce` once the URL passed through JSON into JavaScript.
* Fixed the "Send to PTT Kargo" button on the order edit screen, which did not work because the admin script was not enqueued on every relevant screen.

= 2.0.0 =
* First public release: HPOS-compatible order list and bulk actions, barcoded shipments, multi-package support, cancellation, tracking, drop point lookup, courier pickup, 80mm thermal labels, bulk label printing, atomic barcode allocation, encrypted credentials, per-order insurance, cash on delivery mapping, separate return address, missing-data detection and a full integration log.

== Upgrade Notice ==

= 2.2.0 =
Options, order meta and the log table are renamed automatically on upgrade. The stored PTT password is unaffected and does not need re-entering. Back up your database first, as you should before any upgrade.

= 2.1.0 =
Settings, order meta and the log table are renamed automatically on upgrade, and the stored PTT password is re-encrypted. Back up your database first, as you should before any upgrade.
