<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit();
}

/*
 * conditionally delete settings and records
 */
$options = get_option( 'gfw_options' );

if( $options['clear_settings'] ) {
	delete_option('gfw_options');
}

if( $options['clear_records'] ) {
	$args = array(
	    'post_type' => array( 'gfw_report', 'gfw_event' ),
	    'post_status' => 'any',
	    'posts_per_page' => -1
	);

	$query = new WP_Query( $args );

	while ( $query->have_posts() ) {
	    $query->next_post();
	    wp_delete_post( $query->post->ID );
	}
}

?>