<?php
/**
 * The app shell: one page for every app URL. The app switches screens in
 * the browser and gets everything else from the REST API.
 *
 * This is a page of its own: the theme's header and footer are not used,
 * so no theme style or script reaches the app.
 *
 * @package HibiJournal
 */

defined( 'ABSPATH' ) || exit;

$hibi_build = Hibi_App::build();
?>
<!doctype html>
<html lang="<?php echo esc_attr( str_replace( '_', '-', determine_locale() ) ); ?>">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( get_bloginfo( 'name' ) ); ?></title>
	<link rel="manifest" href="<?php echo esc_url( home_url( '/hibi-manifest.webmanifest' ) ); ?>" crossorigin="use-credentials">
	<link rel="icon" href="<?php echo esc_url( Hibi_App::asset_url( 'icons/favicon-32.png' ) ); ?>" sizes="32x32">
	<link rel="apple-touch-icon" href="<?php echo esc_url( Hibi_App::asset_url( 'icons/apple-touch-icon.png' ) ); ?>">
	<meta name="theme-color" content="#f7f6f3" media="(prefers-color-scheme: light)">
	<meta name="theme-color" content="#15171b" media="(prefers-color-scheme: dark)">
	<meta name="apple-mobile-web-app-capable" content="yes">
	<meta name="mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-title" content="Hibi">
	<meta name="apple-mobile-web-app-status-bar-style" content="default">
	<?php
	if ( $hibi_build ) {
		foreach ( $hibi_build['css'] as $hibi_i => $hibi_file ) {
			// Hashed file names change on every build, so no version string is needed.
			wp_register_style( 'hibi-app-' . $hibi_i, Hibi_App::asset_url( 'app/' . $hibi_file ), array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			wp_print_styles( 'hibi-app-' . $hibi_i );
		}
	}
	?>
</head>
<body class="hibi">
	<div id="app">
		<noscript><p><?php esc_html_e( 'Hibi needs JavaScript to run.', 'hibi-journal' ); ?></p></noscript>
	</div>
	<?php
	// JSON_HEX_TAG keeps "</script>" inside an entry from closing the tag.
	wp_print_inline_script_tag(
		wp_json_encode( Hibi_App::bootstrap(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
		array(
			'type' => 'application/json',
			'id'   => 'hibi-bootstrap',
		)
	);
	if ( $hibi_build ) {
		wp_print_script_tag(
			array(
				'type' => 'module',
				'src'  => Hibi_App::asset_url( 'app/' . $hibi_build['js'] ),
			)
		);
	} else {
		echo '<p>' . esc_html__( 'The app files are missing. Please reinstall the Hibi Journal plugin.', 'hibi-journal' ) . '</p>';
	}
	?>
</body>
</html>
