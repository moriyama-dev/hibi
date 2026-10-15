<?php
/**
 * Hibi theme: makes the Hibi Journal app the front page of the site.
 *
 * The app itself (screens, offline support, notifications) is part of the
 * Hibi Journal plugin. This theme declares "hibi-app", which tells the
 * plugin to serve the app at the root of the site instead of /journal/,
 * and shows any other pages in a plain reading layout.
 *
 * @package Hibi
 */

defined( 'ABSPATH' ) || exit;

define( 'HIBI_THEME_VERSION', '0.1.0' );

add_action( 'after_setup_theme', 'hibi_theme_setup' );
add_action( 'wp_enqueue_scripts', 'hibi_theme_assets' );

function hibi_theme_setup() {
	load_theme_textdomain( 'hibi', get_template_directory() . '/languages' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'automatic-feed-links' );
	// The Hibi Journal plugin then serves the app on the front page, and
	// keeps the login on for a year and lands there after logging in.
	add_theme_support( 'hibi-app' );
}

function hibi_theme_assets() {
	wp_enqueue_style( 'hibi-content', get_template_directory_uri() . '/assets/content.css', array(), HIBI_THEME_VERSION );
}
