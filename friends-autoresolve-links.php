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

function friends_autoresolve_links_embed_tokens() {
	return array(
		'facebook:token' => 'friends-autoresolve-links_facebook_token',
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
			if ( empty( $_POST[$key] ) ) {
				update_option( $option, $_POST[$key] );
			} else {
				update_option( $option, $_POST[$key] );
			}
		}
	}

	?><h1><?php _e( 'Friends Autoresolve Links', 'friends-autoresolve-links' ); ?></h1>

	<form method="post">
		<?php wp_nonce_field( $nonce_value ); ?>
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Allow iframes', 'friends-autoresolve-links' ); ?></th>
					<td>
						<fieldset>
							<label for="allow_iframes">
								<input name="allow_iframes" type="checkbox" id="allow_iframes" value="1" <?php checked( get_option( 'friends-autoresolve-links_allow_iframes' ) ); ?> />
								<?php _e( "Use iframes to embed remote content (e.g. Youtube videos)." ); ?>							</label>
						</fieldset>
					</td>
				</tr>
				<?php foreach ( array_map( 'get_option', friends_autoresolve_links_embed_tokens() ) as $key => $value ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $key ); ?></th>
						<td>
							<fieldset>
								<label for="<?php echo sanitize_title( $key ); ?>">
									<input name="<?php echo esc_attr( $key ); ?>" type="text" id="<?php echo sanitize_title( $key ); ?>" value="<?php echo esc_html( $value ); ?>" />
								</label>
							</fieldset>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p class="submit">
			<input type="submit" id="submit" class="button button-primary" value="<?php esc_html_e( 'Save Changes', 'friends-autoresolve-links' ); ?>">
		</p>
	</form>

	<?php if ( $display_about_friends ) : ?>
		<p>
			<?php
			echo wp_kses(
				// translators: %s: URL to the Friends Plugin page on WordPress.org.
				sprintf( __( 'The Friends plugin is all about connecting with friends and news. Learn more on its <a href=%s>plugin page on WordPress.org</a>.', 'friends-autoresolve-links' ), '"https://wordpress.org/plugins/friends" target="_blank" rel="noopener noreferrer"' ),
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
		// translators: %s: URL to the RSS Bridge.
		sprintf( __( 'This parser is powered by the open source project <a href=%s>Embed</a> and provides support to parse the following properties:', 'friends-autoresolve-links' ), '"https://github.com/oscarotero/Embed" target="_blank" rel="noopener noreferrer"' ),
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

	preg_match_all( '#\bhttps?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))#', strip_tags( str_replace( '>', '>' . PHP_EOL, $item->post_content ) ), $matches );

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
		$content = false;

		foreach ( $matches[0] as $m ) {
			try {
				$info = $embed->get( $m );
				if ( $info ) {
					$content = str_replace( '<a class="auto-link" href="' . $m . '">' . $m . '</a>', $m, $item->post_content );

					if ( $info->code && ( get_option( 'friends-autoresolve-links_allow_iframes' ) || false === strpos( $info->code, '<iframe' ) ) ) {
						$content = str_replace( $m, $info->code, $content );
					} elseif ( $info->image && ! $info->url ) {
						$content = str_replace( $m, '<img src="' . $info->image . '" />', $content );
					} elseif ( $info->url ) {
						$text = $info->url;
						if ( $info->image ) {
							$text .= ' <img src="' . $info->image . '" />';
						}

						$text = '<a href="' . esc_url( $info->url ) . '" rel="noopener noreferer" target="_blank">' . wp_kses( $text, array( 'img' => array( 'src' => array() ) ) ) . '</a>';

						if ( $info->description ) {
							$text .= '<br/>' . esc_html( $info->description );
						}
						$content = str_replace( '<a class="auto-link" href="' . $m . '">' . $m . '</a>', $m, $content );
						$content = str_replace( $m, $text, $content );
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

add_action(
	'friends_entry_dropdown_menu',
	function() {
		if ( apply_filters( 'friends_debug', false ) ) {
			?>
		<li class="menu-item"><a href="#" data-id="<?php echo esc_attr( get_the_ID() ); ?>" class="friends-re-resolve">Re-resolve</a></li>
			<?php
		}
	}
);

add_action(
	'wp_enqueue_scripts',
	function() {
		if ( is_user_logged_in() ) {
			wp_enqueue_script( 'friends-autoresolve-links', plugins_url( 'friends-autoresolve-links.js', __FILE__ ), array( 'friends' ), 1.0 );
		}
	}
);

add_action(
	'admin_menu',
	function () {
		// Only show the menu if installed standalone.
		$friends_settings_exist = '' !== menu_page_url( 'friends-settings', false );
		if ( $friends_settings_exist ) {
			add_submenu_page(
				'friends-settings',
				__( 'Plugin: Autoresolve Links', 'friends-autoresolve-links' ),
				__( 'Plugin: Autoresolve Links', 'friends-autoresolve-links' ),
				'administrator',
				'friends-rss-bridge',
				'friends_autoresolve_links_about_page'
			);
		} else {
			add_menu_page( 'friends', __( 'Friends', 'friends-autoresolve-links' ), 'administrator', 'friends-settings', null, 'dashicons-groups', 3.73 );
			add_submenu_page(
				'friends-settings',
				__( 'About', 'friends-autoresolve-links' ),
				__( 'About', 'friends-autoresolve-links' ),
				'administrator',
				'friends-settings',
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
		$item = friends_autoresolve_links_in_feed_item( new Friends_Feed_Item( $post ) );
		if ( ! is_array( $item->_feed_rule_transform ) ) {
			wp_send_json_error( 'no-change' );
		}
		wp_send_json_success( $item->_feed_rule_transform );
	}
);

add_filter( 'friends_autoresolve_links', '__return_true' );
add_filter( 'friends_modify_feed_item', 'friends_autoresolve_links_in_feed_item', 10 );


