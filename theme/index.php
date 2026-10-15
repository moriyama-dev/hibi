<?php
/**
 * Shown for anything that is neither the app (served by the Hibi Journal
 * plugin) nor a single post or page.
 *
 * @package Hibi
 */

defined( 'ABSPATH' ) || exit;
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'hibi-content' ); ?>>
<?php wp_body_open(); ?>
<main class="hibi-page">
	<?php if ( ! class_exists( 'Hibi_App' ) ) : ?>
		<p><?php esc_html_e( 'Please activate the Hibi Journal plugin.', 'hibi' ); ?></p>
	<?php else : ?>
		<h1><?php esc_html_e( 'Page not found', 'hibi' ); ?></h1>
		<p><a href="<?php echo esc_url( Hibi_App::url() ); ?>"><?php esc_html_e( 'Back to the journal', 'hibi' ); ?></a></p>
	<?php endif; ?>
</main>
<?php wp_footer(); ?>
</body>
</html>
