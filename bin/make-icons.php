<?php
/**
 * Draws the app icons into theme/assets/icons. Run: php bin/make-icons.php
 *
 * A sheet of paper with three lines on the accent colour. Shapes only, so
 * no font licence is involved.
 *
 * @package Hibi
 */

$hibi_out = dirname( __DIR__ ) . '/plugin/assets/icons';

/**
 * @param int  $size     Pixels.
 * @param bool $maskable Keep the drawing inside the 80% safe zone and fill the corners.
 * @param bool $rounded  Round the background corners.
 * @return Imagick
 */
function hibi_icon( $size, $maskable, $rounded ) {
	$s    = $size / 512;
	$img  = new Imagick();
	$img->newImage( $size, $size, new ImagickPixel( 'transparent' ) );
	$draw = new ImagickDraw();

	$draw->setFillColor( '#35558a' );
	if ( $rounded ) {
		$draw->roundRectangle( 0, 0, $size - 1, $size - 1, 112 * $s, 112 * $s );
	} else {
		$draw->rectangle( 0, 0, $size - 1, $size - 1 );
	}

	// Maskable icons may be cropped to a circle: shrink the artwork into the safe zone.
	$k  = $maskable ? 0.72 : 1.0;
	$cx = $size / 2;
	$cy = $size / 2;
	$w  = 236 * $s * $k;
	$h  = 300 * $s * $k;

	$draw->setFillColor( '#ffffff' );
	$draw->roundRectangle( $cx - $w / 2, $cy - $h / 2, $cx + $w / 2, $cy + $h / 2, 28 * $s * $k, 28 * $s * $k );

	$draw->setFillColor( '#35558a' );
	$line = 18 * $s * $k;
	foreach ( array( -62, -6, 50 ) as $i => $offset ) {
		$y     = $cy + $offset * $s * $k;
		$right = 0 === $i ? $w * 0.5 - 52 * $s * $k : $w * 0.5 - 44 * $s * $k;
		$draw->roundRectangle( $cx - $w / 2 + 44 * $s * $k, $y, $cx + $right, $y + $line, $line / 2, $line / 2 );
	}
	// A small sun in the top corner: the day.
	$draw->setFillColor( '#e0a35e' );
	$draw->circle( $cx + $w / 2 - 50 * $s * $k, $cy - $h / 2 + 52 * $s * $k, $cx + $w / 2 - 50 * $s * $k + 22 * $s * $k, $cy - $h / 2 + 52 * $s * $k );

	$img->drawImage( $draw );
	$img->setImageFormat( 'png' );
	$img->stripImage();
	return $img;
}

$hibi_icons = array(
	'icon-192.png'          => array( 192, false, true ),
	'icon-512.png'          => array( 512, false, true ),
	'icon-maskable-512.png' => array( 512, true, false ),
	'apple-touch-icon.png'  => array( 180, false, false ),
	'favicon-32.png'        => array( 32, false, true ),
);
foreach ( $hibi_icons as $hibi_name => $hibi_args ) {
	hibi_icon( ...$hibi_args )->writeImage( $hibi_out . '/' . $hibi_name );
	echo $hibi_name, "\n";
}
