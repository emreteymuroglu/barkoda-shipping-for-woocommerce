# Barkoda Shipping for WooCommerce

> WooCommerce siparişlerinden PTT'nin SOAP servisleri üzerinden kargo oluşturan, barkod üreten, 80mm termal etiket basan ve takip yapan ücretsiz WordPress eklentisi.

[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](LICENSE)
![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4)
![WooCommerce 8.0+](https://img.shields.io/badge/WooCommerce-8.0%2B-96588a)
![WordPress 6.0+](https://img.shields.io/badge/WordPress-6.0%2B-21759b)

🇬🇧 [English README](README.md)

PTT Kargo ile gönderim yapan WooCommerce mağazaları için ücretsiz bir eklenti. Sipariş bilgilerini PTT portalına elle girmek yerine — ve aynı işi yapan ücretli eklentilere alternatif olarak — sipariş listesine bir "Kargoya İlet" butonu ekler.

**PTT entegrasyon sözleşmesi gerekir.** PTT size bir müşteri numarası, şifre ve barkod aralığı tahsis eder; bunlar olmadan eklentinin doğrulayacağı bir bilgi olmaz. PTT Başmüdürlüğünüzle ya da `entegrasyon@ptt.gov.tr` ile iletişime geçin.

## Özellikler

- **Tek popup'tan gönderi oluşturma** — `kabulEkle2`, çoklu pakette `kabulEkleParcaliBarkod` (her parçaya ayrı barkod)
- **İptal** — PTT fiziksel kabulü yapmadıysa `barkodVeriSil`, başarısız olursa `referansVeriSil` fallback
- **Takip** — barkod ya da referans no ile hareket geçmişi (`gonderiSorgu`) ve gönderinin bulunduğu PTT şubesi (`getDropPointInfo`)
- **Kurye çağırma** — `siparisIstekEkle2` ile adresinizden toplama talebi
- **80mm termal etiket** — HTML + Code128 SVG, sürücü gerektirmeden her 80mm yazıcıda çalışır; toplu baskıda tüm siparişler tek belgede
- **Kapıda ödeme** WooCommerce ödeme yöntemlerinden eşleştirilir (`UA` + `OS` otomatik eklenir), sipariş bazında sigorta toggle'ı (`DK`)
- **Atomik barkod tahsisi** — numaralar `FOR UPDATE` transaction içinde verilir, başarısız gönderide tüketilen numara yakılmaz, sonraki denemede tekrar kullanılır
- **AES-256-CBC şifreli** PTT şifresi, `AUTH_KEY` tabanlı; loglarda otomatik maskelenir
- **Tam SOAP logu** — her çağrının request/response çıktısı, 500 kayıtta rotasyon
- **HPOS uyumlu**, ürün verisinden otomatik ağırlık/boyut, gönderim öncesi eksik müşteri bilgisi kontrolü, farklı iade adresi, tek tıkla test/canlı geçişi

## Gereksinimler

WordPress 6.0+ · WooCommerce 8.0+ · PHP 7.4+ · PTT entegrasyon sözleşmesi

## Kurulum

1. Son sürümün ZIP'ini [Releases](../../releases) sayfasından indirin.
2. **Eklentiler → Yeni Ekle → Eklenti Yükle** ile yükleyip etkinleştirin.
3. **PTT Kargo → Ayarlar** sayfasını açın.

## Ayarlar

Ayarlar yedi sekmeye bölünmüştür; yalnızca ilk üçü zorunludur.

| Sekme | Gereken bilgi |
|---|---|
| **PTT Bağlantı** | Ortam (*Test* ile başlayın), müşteri numarası, şifre. "Bağlantıyı Test Et" bilgileri kaydetmeden PTT'ye doğrulatır. |
| **Barkod** | PTT'nin tahsis ettiği 8 haneli prefix ve seri aralığı (örn. `27918802`, `0000`–`9999`). Prefix + seri 12 hanedir; 13. hane check digit olarak hesaplanır. Sipariş referans öneki isteğe bağlıdır. |
| **Gönderici** | Firma adı, adres, telefon, e-posta — etikette ve PTT envelope'unda kullanılır. Kapıda ödeme için posta çeki hesap numarası zorunludur. |
| **Etiket** | Logo, başlık, görünecek bloklar; canlı önizleme ile. |
| **Ürün & Filtreler** | Kargo listesini belirli ürünlere ve sipariş durumlarına göre sınırlandırır. |
| **Gönderi Varsayılanları** | Ağırlık/boyut kaynağı (sabit, WC ürün verisi ya da fallback'li), sabit desi, ek hizmet kodları. |
| **Ödeme** | Hangi ödeme yöntemlerinin kapıda ödeme sayılacağı ve sigorta hizmet kodu. |

Önce bir test gönderisi oluşturun, çıkan barkodu onay için `entegrasyon@ptt.gov.tr` adresine iletin, onaydan sonra ortamı *Canlı*'ya alın.

## Günlük kullanım

- **PTT Kargo → Kargo Siparişleri** → **Kargoya İlet**. Popup'ta müşteri bilgileri (eksik alanlar kırmızı işaretli), değiştirilebilir otomatik ağırlık/desi, kapıda ödeme bilgisi, sigorta toggle'ı ve parça adedi yer alır. Onayladığınızda etiket yeni sekmede açılır.
- **Sipariş düzenleme ekranındaki** PTT Kargo metabox'ı aynı işlemi sunar; gönderim sonrası barkod, takip linki, etiket ve iptal butonlarını gösterir.
- WooCommerce sipariş listesindeki **toplu işlemler** ile seçili siparişleri topluca gönderebilir ya da etiketlerini bastırabilirsiniz.
- **PTT Kargo → Kurye Çağır** adresinizden toplama talebi oluşturur.

## Geliştirici notları

<details>
<summary>Filter ve action hook'ları</summary>

```php
// PTT'ye gitmeden önce envelope alanları
apply_filters( 'ptt_kargo_wc_kabul_fields', $fields );
apply_filters( 'ptt_kargo_wc_soap_request_body', $body, $operation, $context );

// Transport
apply_filters( 'ptt_kargo_wc_http_timeout', 30, $operation );
apply_filters( 'ptt_kargo_wc_sslverify', true, $operation );

// Barkod ve sipariş sınıflandırması
apply_filters( 'ptt_kargo_wc_barkod', $barkod, $cursor, $prefix );
apply_filters( 'ptt_kargo_wc_is_cod_order', $is_cod, $order, $cod_methods );

// Otomatik hesaplanan gönderi verisi
apply_filters( 'ptt_kargo_wc_resolved_weight', $grams, $order, $source );
apply_filters( 'ptt_kargo_wc_resolved_dimensions', $dims, $order, $source );
apply_filters( 'ptt_kargo_wc_resolved_desi', $desi, $order, $dims, $source );

// Etiket render'ı
apply_filters( 'ptt_kargo_wc_label_html', $html, $order, $barkod );
apply_filters( 'ptt_kargo_wc_label_header', $header_data, $order );
apply_filters( 'ptt_kargo_wc_label_products', $items, $order );

// Yaşam döngüsü
do_action( 'ptt_kargo_wc_after_send', $order, $barkod, $result );
do_action( 'ptt_kargo_wc_after_error', $order, $message, $result );
do_action( 'ptt_kargo_wc_after_cancel', $order, $old_barkod, $result );
do_action( 'ptt_kargo_wc_after_courier', $params, $result );
```
</details>

<details>
<summary>Sipariş meta anahtarları</summary>

| Meta key | Açıklama |
|---|---|
| `_ptt_kargo_wc_barkod` | 13 haneli PTT barkodu |
| `_ptt_kargo_wc_ref` | Müşteri referans numarası |
| `_ptt_kargo_wc_status` | `pending` / `sent` / `error` / `canceled` |
| `_ptt_kargo_wc_takip_url` | PTT'nin döndürdüğü takip linki |
| `_ptt_kargo_wc_dosya_adi` | İptal için kullanılan dosya adı |
| `_ptt_kargo_wc_pending_barkod` | Hata sonrası retry için saklanan barkod |
| `_ptt_kargo_wc_parca_barkodlar` | Çoklu pakette parça barkodları (JSON) |
</details>

## Bilinen sınırlamalar

- **Test ortamı izolasyonu** — PTT'de kabul (`PttVeriYukleme`) ve takip (`GonderiTakipV2`) test ortamında ayrı veritabanlarında çalışır; test ortamında kabul edilen barkod takipte "kayıt bulunamadı" döner. Canlıda bu sorun yoktur.
- **Kapıda ödeme için PTT Bank hesabı gerekir** — tahsil edilen tutar PTT işyerinden açtıracağınız posta çeki hesabına aktarılır.
- **Tek adres desteği** — PTT'nin alternatif adres alanları (`aIlKodu2/3`, `aIlceKodu2/3`) desteklenmiyor.
- **`etiketGetir` kullanılmıyor** — PTT'nin döndüğü PDF 80mm termal yazıcılarda sorunlu render olduğu için etiket eklenti tarafından üretilir.

## Katkı

Issue ve PR'lar memnuniyetle karşılanır; büyük değişiklikler için önce issue açın. Geliştirmeyi PTT'nin test credential'ları ile yapın — canlı ortamda gerçek barkod tüketilir. Sürüm geçmişi için [CHANGELOG.md](CHANGELOG.md).

## Lisans

[GPL-2.0-or-later](LICENSE).

---

**Not**: Bu eklenti PTT tarafından geliştirilmemiştir, resmi bir PTT ürünü değildir. PTT'nin sözleşmeli müşterilerine sunduğu SOAP servislerine bağımsız bir istemcidir. PTT ve PTT Kargo, hak sahibinin tescilli markalarıdır.
