<?php
/**
 * Ordinary posts and pages that are not part of the app, shown plainly so
 * existing content on the site stays readable.
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
<body <?php body_class( 'hibi-content' ); ?>>
<?php wp_body_open(); ?>
<main class="hibi-page">
	<p><a href="<?php echo esc_url( class_exists( 'Hibi_App' ) ? Hibi_App::url() : home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to the journal', 'hibi' ); ?></a></p>
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<h1><?php the_title(); ?></h1>
			<?php
			the_content();
			wp_link_pages(
				array(
					'before' => '<nav class="page-links">' . esc_html__( 'Pages:', 'hibi' ),
					'after'  => '</nav>',
				)
			);
			?>
		</article>
		<?php
	endwhile;
	?>
</main>
<?php wp_footer(); ?>
</body>
</html>
