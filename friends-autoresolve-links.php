<?php
/**
 * Plugin name: Friends Autoresolve Links
 * Plugin author: Alex Kirk
 * Plugin URI: https://github.com/akirk/friends-autoresolve-links
 * Version: 1.1.0
 * Requires Plugins: friends
 *
 * Description: Experimental plugin to transform plaintext links of incoming content (especially t.co shortlinks) into rich(er) links.
 *
 * License: GPL2
 * Text Domain: friends
 *
 * @package Friends_Autoresolve_Links
 */

/**
 * This file contains the main plugin functionality.
 */

defined( 'ABSPATH' ) || exit;
require 'vendor/autoload.php';

function friends_autoresolve_links_embed_tokens() {
	return array(
		'facebook:token'  => 'friends-autoresolve-links_facebook_token',
		'instagram:token' => 'friends-autoresolve-links_instagram_token',
	);
}


/**
 * Display an about page for the plugin.
 *
 * @param      bool $display_about_friends  The display about friends section.
 */
function friends_autoresolve_links_about_page( $display_about_friends = false ) {
	$nonce_value = 'friends-autoresolve-links';
	if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], $nonce_value ) ) {
		update_option( 'friends-autoresolve-links_allow_iframes', isset( $_POST['allow_iframes'] ) ? boolval( $_POST['allow_iframes'] ) : false );
		foreach ( friends_autoresolve_links_embed_tokens() as $key => $option ) {
			if ( empty( $_POST[ $key ] ) ) {
				update_option( $option, $_POST[ $key ] );
			} else {
				update_option( $option, $_POST[ $key ] );
			}
		}
	}

	?><h1><?php _e( 'Friends Autoresolve Links', 'friends' ); ?></h1>

	<form method="post">
		<?php wp_nonce_field( $nonce_value ); ?>
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Allow iframes', 'friends' ); ?></th>
					<td>
						<fieldset>
							<label for="allow_iframes">
								<input name="allow_iframes" type="checkbox" id="allow_iframes" value="1" <?php checked( get_option( 'friends-autoresolve-links_allow_iframes' ) ); ?> />
								<?php _e( 'Use iframes to embed remote content (e.g. Youtube videos).', 'friends' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
				<?php foreach ( array_map( 'get_option', friends_autoresolve_links_embed_tokens() ) as $key => $value ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $key ); ?></th>
						<td>
							<fieldset>
								<label for="<?php echo sanitize_title( $key ); ?>">
									<input name="<?php echo esc_attr( $key ); ?>" type="text" id="<?php echo sanitize_title( $key ); ?>" value="<?php echo esc_html( $value ); ?>"  placeholder="<?php /* translators: Placeholder for a field where a token can be entered. */ esc_html_e( "Leave empty if you don't have one", 'friends' ); ?>" size="60" />
								</label>
							</fieldset>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p class="submit">
			<input type="submit" id="submit" class="button button-primary" value="<?php esc_html_e( 'Save Changes', 'friends' ); ?>">
		</p>
	</form>

	<?php if ( $display_about_friends ) : ?>
		<p>
			<?php
			echo wp_kses(
				// translators: %s: URL to the Friends Plugin page on WordPress.org.
				sprintf( __( 'The Friends plugin is all about connecting with friends and news. Learn more on its <a href=%s>plugin page on WordPress.org</a>.', 'friends' ), '"https://wordpress.org/plugins/friends" target="_blank" rel="noopener noreferrer"' ),
				array(
					'a' => array(
						'href'   => array(),
						'rel'    => array(),
						'target' => array(),
					),
				)
			);
			?>
		</p>
	<?php endif; ?>
	<p>
	<?php
	echo wp_kses(
		// translators: %s: URL to the Embed library.
		sprintf( __( 'This plugin is largely powered by the open source project <a href=%s>Embed</a> and provides support to resolve links to the following domains:', 'friends' ), '"https://github.com/oscarotero/Embed" target="_blank" rel="noopener noreferrer"' ),
		array(
			'a' => array(
				'href'   => array(),
				'rel'    => array(),
				'target' => array(),
			),
		)
	);
	?>
	</p>
	<ul>
		<?php
		$class = new ReflectionClass( '\\Embed\\ExtractorFactory' );
		$adapters = $class->getProperty( 'adapters' );
		$adapters->setAccessible( true );
		$domains = array_keys( $adapters->getValue( new \Embed\ExtractorFactory ) );
		foreach ( require( __DIR__ . '/vendor/embed/embed/src/resources/oembed.php' ) as $api => $urls ) {
			$domain = explode( '.', parse_url( $api, PHP_URL_HOST ) );
			$count = count( $domain );
			if ( 'ac' === $domain[ $count - 2 ] || 'co' === $domain[ $count - 2 ] || 'com' === $domain[ $count - 2 ] || 'org' === $domain[ $count - 2 ] ) {
				$domains[] = implode( '.', array_slice( $domain, - 3 ) );
			} else {
				$domains[] = implode( '.', array_slice( $domain, - 2 ) );
			}
		}
		$domains = array_unique( $domains );
		sort( $domains );
		foreach ( $domains as $domain ) {
			?>
			<li><a href="<?php echo esc_url( $domain ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $domain ); ?></a> <?php echo esc_html( '' ); ?></li>
			<?php
		}
		?>
	</ul>
	<?php
}

/**
 * Display an about page for the plugin with the friends section.
 */
function friends_autoresolve_links_about_page_with_friends_about() {
	return friends_autoresolve_links_about_page( true );
}

function friends_autoresolve_links_in_feed_item( $item ) {
	if ( ! $item->is_new() ) {
		return $item;
	}

	$content = $item->post_content;

	$protected_tags = array();
	$protected_post_content = preg_replace_callback( '#<a href=[^>]+>.*?</a>#i', function( $m ) use ( &$protected_tags ) {
		$c = count( $protected_tags );
		$protect = '!#!#PROTECT' . $c . '#!#!';
		$protected_tags[ $protect ] = $m[0];
		return $protect;
	}, $content );
	$stripped_post_content = strip_tags( $protected_post_content );

	$url_regex = 'https?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))';
	preg_match_all( '#\b' . $url_regex . '#', $stripped_post_content, $matches );

	if ( ! empty( $matches[0] ) ) {
		$embed = new \Embed\Embed();
		$embed->setSettings(
			array_merge(
				array(
					'twitch:parent' => $_SERVER['SERVER_NAME'] === 'localhost' ? null : $_SERVER['SERVER_NAME'],
				),
				array_map( 'get_option', friends_autoresolve_links_embed_tokens() )
			)
		);
		$content = $protected_post_content;

		foreach ( $matches[0] as $m ) {
			try {
				$info = $embed->get( $m );
				if ( $info ) {
					if ( $info->code && ( get_option( 'friends-autoresolve-links_allow_iframes' ) || false === strpos( $info->code, '<iframe' ) ) ) {
						$content = str_replace( $m, $info->code, $content );
					} elseif ( $info->image && ! $info->url ) {
						$content = str_replace( $m, '<img src="' . $info->image . '" />', $content );
					} elseif ( $info->url ) {
						$text = $info->url;
						if ( $info->image ) {
							$text .= ' <img src="' . $info->image . '" />';
						}

						$text = '<a\s+href="' . esc_url( $info->url ) . '" rel="noopener noreferer" target="_blank">' . wp_kses( $text, array( 'img' => array( 'src' => array() ) ) ) . '</a>';

						if ( $info->description ) {
							$text .= '<br/>' . esc_html( $info->description );
						}
						$content = str_replace( $m, $text, $content );
					}
				}
			} catch ( Exception $e ) {
				// ignore and not modify anything.
			}
		}

		$content = str_replace( array_keys( $protected_tags ), array_values( $protected_tags ), $content );
		if ( $content ) {
			$item->_feed_rule_transform = array(
				'post_content' => $content,
			);
		}
	}
	return $item;
}

add_action(
	'friends_entry_dropdown_menu',
	function() {
		if ( apply_filters( 'friends_debug', false ) ) {
			?>
		<li class="menu-item"><a href="#" data-id="<?php echo esc_attr( get_the_ID() ); ?>" class="friends-re-resolve"><?php esc_html_e( 'Re-resolve', 'friends' ); ?></a></li>
			<?php
		}
	}
);

add_action(
	'wp_enqueue_scripts',
	function() {
		if ( is_user_logged_in() && class_exists( 'Friends\Friends' ) && Friends\Friends::on_frontend() ) {
			wp_enqueue_script( 'friends-autoresolve-links', plugins_url( 'friends-autoresolve-links.js', __FILE__ ), array( 'friends' ), 1.0 );
		}
	}
);

add_action(
	'admin_menu',
	function () {
		// Only show the menu if installed standalone.
		$friends_settings_exist = '' !== menu_page_url( 'friends', false );
		if ( $friends_settings_exist ) {
			add_submenu_page(
				'friends',
				__( 'Autoresolve Links', 'friends' ),
				__( 'Autoresolve Links', 'friends' ),
				'administrator',
				'friends-autoresolve-links',
				'friends_autoresolve_links_about_page'
			);
		} else {
			add_menu_page( 'friends', __( 'Friends', 'friends' ), 'administrator', 'friends', null, 'dashicons-groups', 3 );
			add_submenu_page(
				'friends',
				__( 'About', 'friends' ),
				__( 'About', 'friends' ),
				'administrator',
				'friends',
				'friends_autoresolve_links_about_page_with_friends_about'
			);
		}
	},
	50
);

add_action(
	'wp_ajax_re-resolve-post',
	function() {
		$post = (array) get_post( $_POST['id'] );
		$post['_is_new'] = true;
		$item = friends_autoresolve_links_in_feed_item( new Friends\Feed_Item( $post ) );
		if ( ! is_array( $item->_feed_rule_transform ) ) {
			wp_send_json_error( 'no-change' );
		}
		wp_send_json_success( $item->_feed_rule_transform );
	}
);

add_filter( 'friends_autoresolve_links', '__return_true' );
add_filter( 'friends_modify_feed_item', 'friends_autoresolve_links_in_feed_item', 10 );


