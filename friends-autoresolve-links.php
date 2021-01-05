<?php
/**
 * Plugin name: Friends Autoresolve Links
 * Plugin author: Alex Kirk
 * Plugin URI: https://github.com/akirk/friends-autoresolve-links
 * Version: 0.1
 *
 * Description: Experimental plugin to transform plaintext links of incoming content (especially t.co shortlinks) into rich(er) links.
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
					} elseif ( $info->image && ! $info->url ) {
						$content = str_replace( $m, '<img src="' . $info->image . '" />', $item->content );
					} elseif ( $info->url ) {
						$text = $info->url;
						if ( $info->image ) {
							$text .= ' <img src="' . $info->image . '" />';
						}

						$text = '<a href="' . esc_url( $info->url ) . '" rel="noopener noreferer" target="_blank">' . wp_kses( $text, array( 'img' => array( 'src' => array() ) ) ) . '</a>';

						if ( $info->description ) {
							$text .= '<br/>' . esc_html( $info->description );
						}
						$content = str_replace( $m, $text, $item->content );
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

add_action( 'friends_entry_dropdown_menu', function() {
	if ( apply_filters( 'friends_debug', false ) ) {
		?><li class="menu-item"><a href="#" data-id="<?php echo esc_attr( get_the_ID() ); ?>" class="friends-re-resolve">Re-resolve</a></li><?php
	}
} );

add_action( 'wp_enqueue_scripts', function() {
	if ( is_user_logged_in() ) {
		wp_enqueue_script( 'friends-autoresolve-links', plugins_url( 'friends-autoresolve-links.js', __FILE__ ), array( 'friends' ), 1.0 );
	}
} );


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


