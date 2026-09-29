<?php
/**
 * Regenerates the wordpress.org directory banners.
 *
 * Kept next to the images so they can be rebuilt rather than re-drawn by hand.
 * This folder is export-ignored, so nothing here ships inside the plugin.
 *
 * Usage:  php assets/banner-generator.php
 *
 * @package PTT_Kargo_WC
 */

$title    = 'Barkoda';
$subtitle = 'Shipping for WooCommerce';
$features = 'Barcodes  ·  Thermal labels  ·  Tracking  ·  Courier pickup';

$font_bold    = 'C:/Windows/Fonts/segoeuib.ttf';
$font_regular = 'C:/Windows/Fonts/segoeui.ttf';

foreach ( [ $font_bold, $font_regular ] as $font ) {
	if ( ! is_readable( $font ) ) {
		fwrite( STDERR, "Font not found: $font\n" );
		exit( 1 );
	}
}

/**
 * The barcode motif on the right: width in design units and whether the bar is
 * one of the bright accent stripes. A width of 0 marks a gap.
 */
$bars = [
	[ 10, true ],
	[ 12, null ],
	[ 16, false ],
	[ 8, null ],
	[ 10, false ],
	[ 8, null ],
	[ 8, false ],
	[ 10, null ],
	[ 26, true ],
	[ 10, null ],
	[ 12, false ],
	[ 8, null ],
	[ 14, false ],
	[ 10, null ],
	[ 10, false ],
	[ 12, null ],
	[ 22, true ],
	[ 10, null ],
	[ 12, false ],
	[ 8, null ],
	[ 16, false ],
	[ 10, null ],
	[ 8, false ],
	[ 12, null ],
	[ 12, true ],
	[ 10, null ],
	[ 20, false ],
	[ 8, null ],
	[ 10, false ],
	[ 10, null ],
	[ 14, false ],
	[ 8, null ],
	[ 10, false ],
];

/**
 * Draws one banner at the given width. Every measurement below is expressed for
 * the 1544px master and scaled, so both sizes stay identical in proportion.
 *
 * @param int    $width  Target width in pixels.
 * @param int    $height Target height in pixels.
 * @param string $out    Destination file.
 */
function render_banner( int $width, int $height, string $out ): void {
	global $title, $subtitle, $features, $font_bold, $font_regular, $bars;

	$scale = $width / 1544;
	$img   = imagecreatetruecolor( $width, $height );
	imageantialias( $img, true );

	// Background: a quiet diagonal wash from the top left to the bottom right.
	for ( $y = 0; $y < $height; $y++ ) {
		for ( $x = 0; $x < $width; $x++ ) {
			$t = ( $x / $width * 0.45 ) + ( $y / $height * 0.55 );
			$r = (int) round( 27 - ( 11 * $t ) );
			$g = (int) round( 34 - ( 13 * $t ) );
			$b = (int) round( 49 - ( 18 * $t ) );
			imagesetpixel( $img, $x, $y, imagecolorallocate( $img, $r, $g, $b ) );
		}
	}

	$accent    = imagecolorallocate( $img, 79, 195, 255 );
	$bar_dark  = imagecolorallocate( $img, 47, 57, 74 );
	$white     = imagecolorallocate( $img, 255, 255, 255 );
	$grey      = imagecolorallocate( $img, 156, 166, 178 );
	$link_blue = imagecolorallocate( $img, 74, 165, 224 );

	// Barcode motif, stretched to fill the band from $bar_left to the right margin.
	$bar_top    = (int) round( 65 * $scale );
	$bar_bottom = (int) round( 433 * $scale );
	$bar_left   = 1000;
	$bar_right  = 1505;
	$bar_scale  = ( $bar_right - $bar_left ) / array_sum( array_column( $bars, 0 ) );

	$cursor = (float) $bar_left;
	foreach ( $bars as list( $w, $bright ) ) {
		$next = $cursor + ( $w * $bar_scale );
		if ( null !== $bright ) {
			imagefilledrectangle(
				$img,
				(int) round( $cursor * $scale ),
				$bar_top,
				(int) round( $next * $scale ) - 1,
				$bar_bottom,
				$bright ? $accent : $bar_dark
			);
		}
		$cursor = $next;
	}

	// Vertical accent rule to the left of the wordmark.
	$rule_w = max( 2, (int) round( 8 * $scale ) );
	imagefilledrectangle(
		$img,
		(int) round( 90 * $scale ),
		(int) round( 145 * $scale ),
		(int) round( 90 * $scale ) + $rule_w - 1,
		(int) round( 356 * $scale ),
		$accent
	);

	// The wordmark sits left of the barcode band; the subtitle is set smaller than the
	// original "for WooCommerce" so the longer string still clears it comfortably.
	$left = (int) round( 130 * $scale );
	imagettftext( $img, 92 * $scale, 0, $left, (int) round( 222 * $scale ), $white, $font_bold, $title );
	imagettftext( $img, 44 * $scale, 0, $left, (int) round( 292 * $scale ), $grey, $font_regular, $subtitle );
	imagettftext( $img, 25 * $scale, 0, $left, (int) round( 360 * $scale ), $link_blue, $font_regular, $features );

	imagepng( $img, $out, 9 );
	imagedestroy( $img );
	printf( "%s  (%dx%d, %d bytes)\n", $out, $width, $height, filesize( $out ) );
}

$dir = __DIR__;
render_banner( 1544, 500, $dir . '/banner-1544x500.png' );
render_banner( 772, 250, $dir . '/banner-772x250.png' );
