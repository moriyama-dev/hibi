<?php
/**
 * Fallback template. The app shell replaces this in a later phase.
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
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<p><?php esc_html_e( 'Please activate the Hibi Journal plugin.', 'hibi' ); ?></p>
<?php wp_footer(); ?>
</body>
</html>
