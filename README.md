# PTT Kargo for WooCommerce

> Create PTT Kargo shipments from WooCommerce orders over PTT's SOAP API: barcodes, 80mm thermal labels, tracking and courier pickup.

[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](LICENSE)
![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4)
![WooCommerce 8.0+](https://img.shields.io/badge/WooCommerce-8.0%2B-96588a)
![WordPress 6.0+](https://img.shields.io/badge/WordPress-6.0%2B-21759b)

🇹🇷 [Türkçe README](README.tr.md)

A free plugin for Turkish WooCommerce stores that ship with PTT Kargo. It replaces retyping order data into PTT's portal — and the paid plugins that do the same job — with a "Ship" button on the order list.

**Requires a PTT integration contract.** PTT issues a customer number, a password and a block of barcode numbers; without those the plugin has nothing to authenticate with. Contact your regional PTT directorate or `entegrasyon@ptt.gov.tr`.

## Features

- **Ship an order** in one popup — `kabulEkle2`, or `kabulEkleParcaliBarkod` for multi-package shipments (one barcode per package)
- **Cancel** shipments PTT has not accepted yet — `barkodVeriSil` with a `referansVeriSil` fallback
- **Track** by barcode or reference (`gonderiSorgu`), including the PTT branch currently holding the parcel (`getDropPointInfo`)
- **Courier pickup** requests — `siparisIstekEkle2`
- **80mm thermal labels** as HTML + Code128 SVG, so any 80mm printer works without a driver; bulk printing puts every selected order in one document
- **Cash on delivery** mapped from your WooCommerce payment methods (`UA` + `OS` applied automatically), plus a per-order insurance toggle (`DK`)
- **Atomic barcode allocation** — numbers are handed out inside a `FOR UPDATE` transaction, and a failed shipment reuses its number instead of burning it
- **AES-256-CBC encrypted** PTT password, keyed from `AUTH_KEY` and masked out of every log entry
- **Full SOAP log** — request and response for every call, rotated at 500 rows
- **HPOS compatible**, weight/dimensions auto-computed from products, missing recipient data caught before sending, optional separate return address, one-click test/live switch

## Requirements

WordPress 6.0+ · WooCommerce 8.0+ · PHP 7.4+ · a PTT integration contract

## Installation

1. Download the latest ZIP from [Releases](../../releases).
2. **Plugins → Add New → Upload Plugin**, then activate.
3. Open **PTT Kargo → Settings**.

## Setup

Settings are split across seven tabs; only the first three are mandatory.

| Tab | What it needs |
|---|---|
| **PTT Connection** | Environment (start on *Test*), customer number, password. "Test Connection" validates them against PTT before you save. |
| **Barcode** | The 8-digit prefix and serial range PTT allocated (e.g. `27918802`, `0000`–`9999`). Prefix + serial is 12 digits; the 13th check digit is calculated. Optional order-reference prefix. |
| **Sender** | Company name, address, phone, email — used on the label and in the PTT envelope. Postal Cheque account number is required for cash on delivery. |
| **Label** | Logo, title, which blocks appear, with a live preview. |
| **Products & Filters** | Restrict the shipping list to certain products and order statuses. |
| **Shipment Defaults** | Weight/dimension source (fixed, WC product data, or product data with fallback), fixed desi, extra service codes. |
| **Payment** | Which payment method IDs count as cash on delivery, and the insurance service code. |

Create a test shipment first, email the barcode to `entegrasyon@ptt.gov.tr` for approval, then switch the environment to *Live*.

## Daily use

- **PTT Kargo → Shipping Orders** → **Ship**. The popup shows recipient data (missing fields flagged in red), auto-computed weight and dimensions you can override, the COD notice, the insurance toggle and a package count. Confirm and the label opens in a new tab.
- The **order edit screen** has the same action in a PTT Kargo metabox, plus barcode, tracking link, label, and cancel once shipped.
- **Bulk actions** on the WooCommerce order list ship or print labels for a whole selection.
- **PTT Kargo → Request Courier** sends a pickup request for collection from your address.

## Developer notes

<details>
<summary>Filters and actions</summary>

```php
// Envelope fields before they go to PTT
apply_filters( 'ptt_kargo_wc_kabul_fields', $fields );
apply_filters( 'ptt_kargo_wc_soap_request_body', $body, $operation, $context );

// Transport
apply_filters( 'ptt_kargo_wc_http_timeout', 30, $operation );
apply_filters( 'ptt_kargo_wc_sslverify', true, $operation );

// Barcode and order classification
apply_filters( 'ptt_kargo_wc_barkod', $barkod, $cursor, $prefix );
apply_filters( 'ptt_kargo_wc_is_cod_order', $is_cod, $order, $cod_methods );

// Auto-computed shipment data
apply_filters( 'ptt_kargo_wc_resolved_weight', $grams, $order, $source );
apply_filters( 'ptt_kargo_wc_resolved_dimensions', $dims, $order, $source );
apply_filters( 'ptt_kargo_wc_resolved_desi', $desi, $order, $dims, $source );

// Label rendering
apply_filters( 'ptt_kargo_wc_label_html', $html, $order, $barkod );
apply_filters( 'ptt_kargo_wc_label_header', $header_data, $order );
apply_filters( 'ptt_kargo_wc_label_products', $items, $order );

// Lifecycle
do_action( 'ptt_kargo_wc_after_send', $order, $barkod, $result );
do_action( 'ptt_kargo_wc_after_error', $order, $message, $result );
do_action( 'ptt_kargo_wc_after_cancel', $order, $old_barkod, $result );
do_action( 'ptt_kargo_wc_after_courier', $params, $result );
```
</details>

<details>
<summary>Order meta keys</summary>

| Meta key | Description |
|---|---|
| `_ptt_kargo_wc_barkod` | 13-digit PTT barcode |
| `_ptt_kargo_wc_ref` | Customer reference number |
| `_ptt_kargo_wc_status` | `pending` / `sent` / `error` / `canceled` |
| `_ptt_kargo_wc_takip_url` | Tracking URL returned by PTT |
| `_ptt_kargo_wc_dosya_adi` | File name used for cancellation |
| `_ptt_kargo_wc_pending_barkod` | Barcode held for retry after a failure |
| `_ptt_kargo_wc_parca_barkodlar` | Per-package barcodes for multi-package shipments (JSON) |
</details>

## Known limitations

- **Test environment isolation** — PTT runs acceptance (`PttVeriYukleme`) and tracking (`GonderiTakipV2`) on separate databases in test, so a barcode accepted there returns "not found" when tracked. It resolves on Live.
- **COD needs a PTT Bank account** — collected amounts are transferred to a Postal Cheque account you open at a PTT branch.
- **Single address only** — PTT's alternative address fields (`aIlKodu2/3`, `aIlceKodu2/3`) are not supported.
- **`etiketGetir` is not used** — the plugin renders its own label, because PTT's PDF does not render reliably on 80mm thermal printers.

## Contributing

Issues and PRs are welcome; please open an issue first for larger changes. Develop against PTT's test credentials — live credentials consume real barcodes. See [CHANGELOG.md](CHANGELOG.md) for release history.

## License

[GPL-2.0-or-later](LICENSE).

---

**Disclaimer**: This plugin is not developed, endorsed or supported by PTT. It is an independent client for the SOAP services PTT provides to contracted customers. PTT and PTT Kargo are trademarks of their respective owner.
