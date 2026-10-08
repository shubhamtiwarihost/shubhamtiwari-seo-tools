<?php
/**
 * Uninstall handler.
 *
 * What is deleted when the plugin is deleted from the Plugins screen:
 *
 * - Always: the internal migration lock (temporary bookkeeping).
 * - Only if the site owner ticked "Remove all DumpSEO data when the plugin is
 *   deleted": the settings option, the data version marker, and the SEO
 *   fields saved on posts and terms (_dumpseo_title, _dumpseo_description,
 *   _dumpseo_canonical, _dumpseo_robots, _dumpseo_social_title,
 *   _dumpseo_social_description, _dumpseo_social_image, _dumpseo_focus_keyphrase),
 *   and all redirects (dumpseo_redirect posts and the dumpseo_redirect_index option).
 *
 * When the box is not ticked, settings and the version marker are kept so that
 * reinstalling restores the configuration and upgrades it correctly.
 *
 * Posts, pages, media and other content are never deleted.
 *
 * On multisite, each site is handled according to its own setting.
 *
 * @package DumpSEO
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Closure rather than a named function, so this file can be included more than once (tests).
$dumpseo_uninstall_site = static function () {
	delete_option( 'dumpseo_migration_lock' );

	$settings = get_option( 'dumpseo_settings' );
	if ( ! is_array( $settings ) || true !== ( $settings['remove_data_on_uninstall'] ?? false ) ) {
		return;
	}

	delete_option( 'dumpseo_settings' );
	delete_option( 'dumpseo_db_version' );

	// Per-post and per-term SEO fields. delete_all removes the key from every object in one query.
	delete_option( 'dumpseo_redirect_index' );
	$dumpseo_redirects = get_posts(
		array(
			'post_type'      => 'dumpseo_redirect',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);
	foreach ( $dumpseo_redirects as $dumpseo_redirect ) {
		wp_delete_post( (int) $dumpseo_redirect, true );
	}

	foreach ( array( '_dumpseo_title', '_dumpseo_description', '_dumpseo_canonical', '_dumpseo_robots', '_dumpseo_social_title', '_dumpseo_social_description', '_dumpseo_social_image', '_dumpseo_focus_keyphrase' ) as $meta_key ) {
		delete_metadata( 'post', 0, $meta_key, '', true );
		delete_metadata( 'term', 0, $meta_key, '', true );
	}
};

if ( is_multisite() ) {
	$dumpseo_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $dumpseo_site_ids as $dumpseo_site_id ) {
		switch_to_blog( (int) $dumpseo_site_id );
		$dumpseo_uninstall_site();
		restore_current_blog();
	}
} else {
	$dumpseo_uninstall_site();
}
