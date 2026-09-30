# Changelog

All notable changes to this project are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the
project adheres to [Semantic Versioning](https://semver.org/).

## [2.2.0] - 2026-09-30

### Changed
- Renamed to **Barkoda Shipping for WooCommerce**. The name no longer leads with a
  carrier, so support for carriers other than PTT can be added without renaming again.
- Every option, order meta key, hook, constant, AJAX action and the log table moved
  onto a `barkoda` prefix, which satisfies the plugin directory's four-character
  minimum. A schema migration (version 3) renames stored data on upgrade. The AES
  salt is deliberately unchanged, so the stored PTT password carries over as it is
  and does not need re-entering.

### Fixed
- Activation on a site upgrading from an earlier version created the current log
  table before the migration ran. The migration then saw both the old and the new
  table, treated the old one as an orphan and dropped it, losing every existing log
  row. Migrations now run first.
- The PHP version requirement notice was hardcoded Turkish text naming the old
  plugin; it is now translatable.

## [2.1.0] - 2026-09-28

### Changed
- All user-facing strings are now English, with a Turkish translation bundled in
  `languages/`. Sites running in Turkish see the same wording as before.
- 36 strings that were hardcoded in JavaScript are now localized through the
  `Barkoda.i18n` object, making them translatable for the first time.
- Internal identifiers renamed to a consistent `barkoda` prefix: the PHP
  namespace, constants, functions, hooks, AJAX actions and the text domain. Stored
  data is migrated automatically; see below.
- The order status setting key is now `order_statuses`. The previous key contained a
  non-ASCII character, which `sanitize_key()` strips and which has to be URL-encoded
  in form field names.
- The codebase now follows the WordPress coding standards, enforced by PHPCS with
  the `WordPress` and `PHPCompatibilityWP` rulesets.
- Line endings normalised to LF, with `.gitattributes` to keep them that way.

### Added
- A version-stamped, idempotent upgrade routine that renames the plugin's options,
  its log table and all thirteen order meta keys, across both HPOS and legacy post
  meta storage. Interrupted runs are safe to repeat.
- The stored PTT password is re-encrypted during the upgrade. The AES key is derived
  from a salt that changed with the rename, so the password is read with the old salt
  and written back under the new one. If it cannot be read, the field is cleared so
  the store re-enters it rather than keeping a value nothing can decrypt.
- Translator comments on every string containing a placeholder.
- A `.pot` template and a `tr_TR` translation.

### Fixed
- Uninstall now removes order meta in addition to the options and the log table, and
  cleans up both the old and the new identifiers so an upgrade that never ran leaves
  nothing behind.

## [2.0.1] - 2026-05-04

### Fixed
- The `siparisIstekEkle2` courier envelope now matches the WSDL exactly:
  - `ekhizmetler` renamed to `ek_hizmetler` (snake_case)
  - `gondericiBilgi` renamed to `gondericibilgi` (lowercase wrapper)
  - ten child elements converted from camelCase to snake_case
  - `randevuBaslangic` renamed to `randevu_baslangic`
  - fields emitted in the WSDL's alphabetical sequence
  - response parsing switched from `hataKodu`/`aciklama` to `sonucKodu`/`sonucAciklama`
- `gondericibilgi` moved to its correct position in the `kabulEkle2` and
  `kabulEkleParcaliBarkod` sequences, between `en` and `iadeAAdres`. Axis2 enforces
  `<xs:sequence>` order strictly and failed silently when it was violated.
- Added the `gonderici_soyadi` and `gonderici_sms` fields to `GondericiBilgi`. Both
  are defined in the WSDL but the plugin never sent them.
- Fixed "expired link" errors on label URLs. `wp_nonce_url()` HTML-escapes the
  separator, so `&` became `&amp;` once the URL passed through JSON into JavaScript
  and `_wpnonce` was lost. Raw URLs are now built with `add_query_arg()`.
- Fixed the "Send to PTT Kargo" button in the order edit metabox. The admin script is
  now enqueued on every admin screen, since the hook string varies between HPOS,
  legacy and different WooCommerce versions.

## [2.0.0] - 2026-04-29

### Added
- HPOS-compatible order list with a bulk action.
- Barcoded shipments via `kabulEkle2`.
- Multi-package shipments via `kabulEkleParcaliBarkod`.
- Shipment cancellation via `barkodVeriSil` and `referansVeriSil`.
- Tracking via `gonderiSorgu` and `gonderiSorgu_referansNo`.
- Current branch lookup via `getDropPointInfo`.
- Courier pickup requests via `siparisIstekEkle2`.
- 80mm thermal labels with a Code128 SVG barcode.
- Bulk label printing.
- Atomic barcode allocation using `SELECT ... FOR UPDATE`.
- PTT password stored AES-256-CBC encrypted, keyed from the WordPress `AUTH_KEY`.
- Per-order insurance (Valuable Goods) toggle.
- Cash on delivery mapped from WooCommerce payment methods.
- Optional separate return address.
- Detection of missing recipient data, with a popup to fill it in.
- Custom log table with rotation at 500 records.
- Connection test using a synthetic barcode against `gonderiSorgu`.
- Barcode reuse after a failed attempt, so a failure does not consume a number.
