<?php
/**
 * Plugin Name: Destiny SEO
 * Description: AI-assisted WordPress SEO review with direct Google Search Console OAuth integration, bulk audits, Gutenberg editor panel, and safe improved drafts.
 * Version: 0.5.0
 * Author: Burkalo Digital Limited
 * Requires at least: 7.0
 * Requires PHP: 8.0
 * Text Domain: destiny-seo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DESTINY_SEO_VERSION', '0.5.0' );
define( 'DESTINY_SEO_FILE', __FILE__ );
define( 'DESTINY_SEO_DIR', plugin_dir_path( __FILE__ ) );

require_once DESTINY_SEO_DIR . 'includes/class-destiny-seo-search-console.php';
require_once DESTINY_SEO_DIR . 'includes/class-destiny-seo-reviewer.php';
require_once DESTINY_SEO_DIR . 'includes/class-destiny-seo-drafts.php';
require_once DESTINY_SEO_DIR . 'includes/class-destiny-seo-admin.php';
require_once DESTINY_SEO_DIR . 'includes/class-destiny-seo-editor.php';
require_once DESTINY_SEO_DIR . 'includes/class-destiny-seo-abilities.php';

add_action(
	'plugins_loaded',
	static function (): void {
		Destiny_SEO_Search_Console::init();
		Destiny_SEO_Admin::init();
		Destiny_SEO_Editor::init();
		Destiny_SEO_Abilities::init();
	}
);
