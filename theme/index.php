<?php
/**
 * The app shell, served for every front-end URL.
 *
 * @package Hibi
 */

defined( 'ABSPATH' ) || exit;
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
	<meta name="robots" content="noindex, nofollow">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'hibi' ); ?>>
<?php wp_body_open(); ?>
<?php if ( function_exists( 'hibi_is_active' ) ) : ?>
	<div id="app">
		<noscript><p><?php esc_html_e( 'Hibi needs JavaScript to run.', 'hibi' ); ?></p></noscript>
	</div>
	<?php hibi_theme_print_bootstrap(); ?>
<?php else : ?>
	<p class="hibi-missing"><?php esc_html_e( 'Please activate the Hibi Journal plugin.', 'hibi' ); ?></p>
<?php endif; ?>
<?php wp_footer(); ?>
</body>
</html>
