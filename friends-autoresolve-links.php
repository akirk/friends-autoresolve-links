<?php
/**
 * Plugin name: Friends Autoresolve Links
 * Plugin author: Alex Kirk
 * Plugin URI: https://github.com/akirk/friends-autoresolve-links
 * Version: 1.0
 *
 * Description: This plugin can transform plaintext links of incoming content (especially t.co shortlinks) into rich links.
 *
 * License: GPL2
 * Text Domain: friends-autoresolve-links
 * Domain Path: /languages/
 *
 * @package Friends_Autoresolve_Links
 */

/**
 * This file contains the main plugin functionality.
 */

require 'vendor/autoload.php';

function friends_autoresolve_links_in_feed_item( $item ) {
	static $count = 10;
	if ( --$count < 0 ) {
		return $item;
	}
	preg_match_all( '#\bhttps?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))#', $item->content, $matches );

	if ( ! empty( $matches[0] ) ) {
		$embed = new \Embed\Embed();
		$content = false;

		foreach ( $matches[0] as $m ) {
			try {
				$info = $embed->get( $m );
				if ( $info ) {
					if ( $info->code && false === strpos( $info->code, '<iframe' ) ) {
						$content = str_replace( $m, $info->code, $item->content );
					} elseif ( $info->image ) {
						$content = str_replace( $m, '<img src="' . $info->image . '" />', $item->content );
					} elseif ( $info->url ) {
						$text = $info->url;
						if ( $info->image ) {
							$text = '<img src="' . $info->image . '" />';
						}
						$content = str_replace( $m, '<a href="' . esc_url( $info->url ) . '" rel="noopener noreferer" target="_blank">' . wp_kses( $text, array( 'img' => array( 'src' => array() ) ) ) . '</a>', $item->content );
					}
				}
			} catch ( Exception $e ) {
				// ignore and not modify anything.
			}
		}

		if ( $content ) {
			$item->_feed_rule_transform = array(
				'post_content' => $content,
			);
		}
	}
	return $item;
}

add_action( 'wp_ajax_re-resolve-post', function() {
	$post = get_post( $_POST['id'] );
	$item = friends_autoresolve_links_in_feed_item( (object) array(
		'content' => $post->post_content,
	));
	if ( ! is_array( $item->_feed_rule_transform ) ) {
		wp_send_json_error('no-change');
	}
	wp_send_json_success( $item->_feed_rule_transform );
} );

add_filter( 'friends_autoresolve_links', '__return_true' );
add_filter( 'friends_modify_feed_item', 'friends_autoresolve_links_in_feed_item', 10 );


