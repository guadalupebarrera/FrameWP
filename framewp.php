<?php
/*
Plugin Name: FrameWP
Plugin URI: https://nathanguadalupe.com
Description: Simple content protection plugin that prevents casual users from getting under the hood of Wordpress content.
Version: 1.1 beta
Author: Nathan Guadalupe
Author URI: https://nathanguadalupe.com
License: GNU General Public License v3.0
License URI: https://nathanguadalupe.com
*/

if (!defined('ABSPATH')) { die; }

/*
--------------------------------------------
	PLUGIN ACTIVATION
--------------------------------------------
*/

/* CREATE APPROVED CLIENT LIST TABLE */
function framewp_install() {
	if (current_user_can('manage_options')) {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$table_acl = $wpdb->prefix . "framewp_acl";
		$table_diagnostics_log = $wpdb->prefix . "framewp_dl";

		$sql = "CREATE TABLE $table_acl (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			unique_id text NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		$sql .= " CREATE TABLE $table_diagnostics_log (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			url text NOT NULL,
			msg text NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";


		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);

		if (!isset($_COOKIE['unique_id'])) {
			diagnostic_log("Cookies not found; creating cookies now!");
			framewp_create_cookie();
		} else {
			diagnostic_log("Cookies have already been created!");
		}

		$framewp_option = get_option('framewp_options');

		$landing_page = $framewp_option['landing_page'];
		if (!isset($landing_page)) {
			$new_landing_page = array(
				'comment_status' => 'closed',
				'post_status' => 'publish',
				'post_title' => 'Landing Page',
				'post_type' => 'page',
				'page_template' => 'landing-page.php'
			);
			$landing_page = wp_insert_post($new_landing_page);
			$framewp_option['landing_page'] = $landing_page;
			update_option('framewp_options', $framewp_option);
		}

		$content_page = $framewp_option['content_page_folder'];
		if (!isset($content_page)) {
			$new_content_page = array(
				'comment_status' => 'closed',
				'post_status' => 'publish',
				'post_title' => 'Content Page',
				'post_type' => 'page',
				'page_template' => 'iframe-page.php'
			);
			$content_page = wp_insert_post($new_content_page);
			$framewp_option['content_page_folder'] = $content_page;
			update_option('framewp_options', $framewp_option);
		}

		$page_code = $framewp_option['unique_code'];
		if (!isset($page_code)) {
			$framewp_option['unique_code'] = get_random_string(64);
			update_option('framewp_options', $framewp_option);
		}

		$posts_page = $framewp_option['posts_page'];
		if (!isset($posts_page)) {
			$new_posts_page = array(
				'comment_status' => 'closed',
				'post_status' => 'publish',
				'post_title' => 'All Posts',
				'post_type' => 'page'
			);
			$posts_page = wp_insert_post($new_posts_page);
			$framewp_option['posts_page'] = $posts_page;
			update_option('framewp_options', $framewp_option);
		}

		$secret_key = $framewp_option['secret_key'];
		if (!isset($secret_key)) { 
			$framewp_option['secret_key'] = get_random_string(32);
			update_option('framewp_options', $framewp_option);
		}

		$secret_iv = $framewp_option['secret_iv'];
		if (!isset($secret_iv)) { 
			$framewp_option['secret_iv'] = get_random_string(32);
			update_option('framewp_options', $framewp_option);
		}

		framewp_add_to_acl();
	}
}

register_activation_hook(__FILE__, 'framewp_install');

/*
--------------------------------------------
	ADD LINK TO WP ADMIN BAR
--------------------------------------------
*/

function framewp_add_toolbar_items($admin_bar){
    $admin_bar->add_menu(array(
		'id'    => 'framewp',
		'title' => 'FrameWP',
        'href'  => '#',
        'meta'  => array(
			'title' => __('FrameWP'),            
        ),
    ));
	
    $admin_bar->add_menu(array(
        'id'    => 'all-posts',
        'parent' => 'framewp',
        'title' => 'All Posts',
        'href'  => get_posts_page_url(),
        'meta'  => array(
			'title' => __('All Posts'),
			'target' => '_self',
			'class' => 'framewp-all-posts'
        ),
    ));
	
	if (current_user_can('manage_options')){
		$admin_bar->add_menu(array(
			'id'    => 'framewp-settings',
			'parent' => 'framewp',
			'title' => 'Settings',
			'href'  => admin_url('options-general.php?page=framewp'),
			'meta'  => array(
				'title' => __('Settings'),
				'target' => '_self',
				'class' => 'framewp-settings'
        	),
    	));
	}
}

add_action('admin_bar_menu', 'framewp_add_toolbar_items', 100);

/*
--------------------------------------------
	UPDATE DIAGNOSTIC LOG
--------------------------------------------
*/

function diagnostic_log($msg) {
	if (isset($msg)) {
		global $wpdb;
	
		$table_name = $wpdb->prefix . "framewp_dl"; 
	
		$wpdb->insert( 
			$table_name, 
			array(
				'url' => get_current_url(), 
				'msg' => $msg,
			) 
		);
	}
}

/*
--------------------------------------------
	GET FULL URL because WP is STUPID
--------------------------------------------
*/

function get_current_url() {
		if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
			$url = "https://";
		} else {
			$url = "http://";
		}
		$url .= $_SERVER['HTTP_HOST'];
		$url .= $_SERVER['REQUEST_URI'];
	
		return $url;
}

/*
--------------------------------------------
	IS SITE HOMEPAGE
--------------------------------------------
*/

function is_site_homepage() {
		if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
			$url = "https://";
		} else {
			$url = "http://";
		}
		$url .= $_SERVER['HTTP_HOST'];
		$url .= $_SERVER['REQUEST_URI'];    
		
		if (substr($url,-1,1) === '/') { $url = substr($url,0,-1); }
	
		if ($url === get_site_url()) { 
			return true;
		}
}

/*
--------------------------------------------
	GET CONTENT PAGE URL
--------------------------------------------
*/

function get_content_page_url() {
	$framewp_option = get_option('framewp_options');
	
	$content_page = $framewp_option['content_page_folder'];
	$page_code = $framewp_option['unique_code'];
	
	return get_permalink($content_page) . '?id=' . $page_code;	
}


/*
--------------------------------------------
	GET LANDING PAGE URL
--------------------------------------------
*/

function get_landing_page_url() {
	$framewp_option = get_option('framewp_options');
	$post_id = $framewp_option['landing_page'];
	
	return get_permalink($post_id);
}

/*
--------------------------------------------
	GET POSTS PAGE URL
--------------------------------------------
*/

function get_posts_page_url() {
	$framewp_option = get_option('framewp_options');
	$post_id = $framewp_option['posts_page'];
	
	return get_permalink($post_id);
}

/*
--------------------------------------------
	LAUNCHER LINK SHORTCODE
--------------------------------------------
*/

function launcher_shortcode($atts, $content = null) {
	$framewp_option = get_option('framewp_options');
	
	extract (shortcode_atts(array(
		'toolbar' => $toolbar,
		'menubar' => $menubar,
        'scrollbars' => $scrollbars,
		'resizable' => $resizable
    ), $atts));
	
	if (!isset($toolbar)) {
		$toolbar = $framewp_option['show_toolbar'];
		if (isset($toolbar) && $toolbar === 'show_toolbar') { $toolbar = 'yes'; } else { $toolbar = 'no'; }
	}
	
	if (!isset($menubar)) {
		$menubar = $framewp_option['show_menubar'];
		if (isset($menubar) && $menubar === 'show_menubar') { $menubar = 'yes'; } else { $menubar = 'no'; }
	}
	
	if (!isset($scrollbars)) {
		$scrollbars = $framewp_option['show_scrollbars'];
		if (isset($scrollbars) && $scrollbars === 'show_scrollbars') { $scrollbars = 'yes'; } else { $scrollbars = 'no'; }
	}
	
	if (!isset($resizable)) {
		$resizable = $framewp_option['resizable_window'];
		if (isset($resizable) && $resizable === 'resizable_window') { $resizable = 'yes'; } else { $resizable = 'no'; }
	}
		
	return "<a href=\"link\" onclick=\"javascript:window.open('" . get_content_page_url() . "','Windows','width=+screen.availWidth+,height=+screen.availHeight,toolbar=" . esc_attr($toolbar) . ",menubar=" . esc_attr($menubar) . ",scrollbars=" . esc_attr($scrollbars) . ",resizable=" . esc_attr($resizable) . ",location=no,directories=no,status=no');return false\" )\"=\"\">" . $content . "</a>";
}

add_shortcode( 'framewp_launcher', 'launcher_shortcode' );

/*
--------------------------------------------
	CHECK REFERER
--------------------------------------------
*/

function check_referer() {
	$full_url = get_current_url();
	$client_referer = $_SERVER['HTTP_REFERER'];
	$unique_id_cookie = $_COOKIE['unique_id'];
	
	if ($full_url != get_content_page_url()) {
		if (!strstr($full_url, 'wp-login.php')) {
			if (isset($client_referer) && isset($unique_id_cookie)) {
				if ($client_referer === get_content_page_url()) {
					$msg = "Adding to the list... ";
					framewp_add_to_acl();
				} else {
					// Check if referer is the front page or the landing page 
					if (!is_site_homepage() || get_current_url() != get_landing_page_url()) {
						// CHECK ALL POSSIBLE REFERALS
						$admin_url = get_admin_url();
						$admin_url_len = strlen($admin_url);
						// Check if referer is coming from the posts page as defined in FrameWP settings
						if ($client_referer === get_posts_page_url()) {
							$matches .= + 1;
						// Check if user is coming from an admin page and is an admin
						} elseif (substr($client_referer, 0, $admin_url_len) === $admin_url && current_user_can('manage_options')) {
							$matches .= + 1;
						} else {
							// Check if referer is a single post
							$args = array("posts_per_page" => -1);
							$post_array = get_posts($args);
							foreach ($post_array as $post) {
								if ($client_referer === get_permalink($post->ID) || $client_referer === get_posts_page_url()) {
									$matches .= + 1;
									break;
								}
							}
							// Check if referer is a tag
							$tags = get_tags();
							if ($tags) {
								foreach ($tags as $tag) {
									if ($client_referer === get_tag_link($tag->term_id)) {
										$matches .= + 1;
										break;
									}
								}
							}
							// Check if referer is a category
							$categories = get_categories();
							if ($categories) {
								foreach ($categories as $category) {
									if ($client_referer === get_category_link($category->term_id)) {
										$matches .= + 1;
										break;
									}
								}
							}
							// Check if referer is from user page
							$args = array('orderby' => 'display_name');
							$wp_user_query = new WP_User_Query($args);
							$authors = $wp_user_query->get_results();
							if (!empty($authors)) {
								foreach ($authors as $author) {
									if ($client_referer == get_author_posts_url($author->ID)) {
										$matches .= + 1;
										break;
									}
								}
							}
						}
						
						if (isset($matches)) { 
							$msg = "Checking the list... ";

							global $wpdb;
							$query = $wpdb->get_results("SELECT * FROM gloryhole_3_framewp_acl", ARRAY_A);
							foreach ($query as $row) {
								if ($row['unique_id'] == $unique_id_cookie) {
									$msg .= "Match found!";
									$stop_redirect = 1; 
									break; 
								}
							}
							if (!isset($stop_redirect)) {
								$msg .= "No match found in database. If you're seeing this then a redirect will happen!";
								redirect();
							}
						} else {
							$msg .= "No referer match found. If you're seeing this then a redirect will happen!";
							if (!strstr($full_url, 'wp-admin')) {
								redirect();
							}
						}

					} else {
						$msg = "This is the launch page. No need to check anything here.";
					}
				}
			} else {
				$msg = "Double-checking cookies!";
				framewp_check_cookies();
			}	
		}
		
		if (isset($msg)) {
			if (isset($client_referer)) {
				$msg .= " Referrer: " . $client_referer . " ";
			}
			diagnostic_log($msg);
		}
	}
}

add_action('init', 'check_referer');

/*
--------------------------------------------
	URL SAFE LIST
--------------------------------------------
*/

function url_on_safe_list() {
		if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
			$url = "https://";
		} else {
			$url = "http://";
		}
		$url .= $_SERVER['HTTP_HOST'];
		$url .= $_SERVER['REQUEST_URI'];    
		
		if (substr($url,-1,1) === '/') { $url = substr($url,0,-1); }
	
		if ($url === get_site_url()) { 
			echo "cool!"; 
		}
}

/*
--------------------------------------------
	CHECK COOKIES
--------------------------------------------
*/

function framewp_check_cookies() {	
	if (is_site_homepage() || get_current_url() === get_landing_page_url()) {
		$msg = "This is the launch page: ";
		if (!isset($_COOKIE['unique_id'])) {
			$msg .= "Cookies not found; creating cookies now!";
			framewp_create_cookie();
		} else {
			$msg .= "Cookies have already been created!";
		}
	} else {
		$msg = "Wrong page without referral or cookies. If you're seeing this then a redirect will happen!";
		redirect();
	}
	
	if (isset($msg)) { diagnostic_log($msg); }
}

/*
--------------------------------------------
	CREATE COOKIES
--------------------------------------------
*/

function framewp_create_cookie() {
	$framewp_option = get_option('framewp_options');
	
	$secret_key = $framewp_option['secret_key'];
	$secret_iv = $framewp_option['secret_iv'];
		
	$encryption_method = 'AES-256-CBC';
	$key = hash('sha256', $secret_key);
	$iv = substr(hash('sha256', $secret_iv), 0, 16);
	
	$unique_id = base64_encode(openssl_encrypt($_SERVER['REMOTE_ADDR'], $encryption_method, $key, 0, $iv));
	setcookie('unique_id', $unique_id, time() + (86400 * 1), "/");
}

/*
--------------------------------------------
	GENERATE RANDOM STRING
--------------------------------------------
*/

function get_random_string($length) {
	$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	$character_length = strlen($characters);
	$random_string = '';
	for ($i = 0; $i < $length; $i++) {
		$random_string .= $characters[rand(0, $character_length - 1)];
    }
    return $random_string;
}

/*
--------------------------------------------
	KIll LOADING CONTENT
--------------------------------------------
*/

function redirect() {
	$client_referer = $_SERVER['HTTP_REFERER'];
	wp_die('It looks you\'re trying to access something in a way you\'re not supposed to. ', get_bloginfo('name'), array(
		'response' => 403,
		'link_url' => get_landing_page_url(),
		'link_text' => 'Click here to give it another go!'
	));
}

/*
--------------------------------------------
	ADD TO APPROVED CLIENT LIST
--------------------------------------------
*/

function framewp_add_to_acl() {
	global $wpdb;
	
	$time = current_time('mysql');
	$unique_id_cookie = $_COOKIE['unique_id'];
	
	$table_name = $wpdb->prefix . "framewp_acl"; 
	
	
	$wpdb->insert( 
		$table_name, 
		array(
			'time' => $time, 
			'unique_id' => $unique_id_cookie,
		) 
	);
	diagnostic_log("Success!");
}

/*
--------------------------------------------
	ADMIN SETTINGS PAGE
--------------------------------------------
*/

class FrameWP_Options {
	private $framewp_options;

	public function __construct() {
		add_action('admin_menu', array( $this, 'framewp_add_plugin_page'));
		add_action('admin_init', array( $this, 'framewp_page_init'));
	}
	
	public function framewp_add_plugin_page() {
		add_options_page(
			'FrameWP Settings', // page_title
			'FrameWP', // menu_title
			'manage_options', // capability
			'framewp', // menu_slug
			array($this, 'framewp_create_admin_page') // function
		);
	}

	public function framewp_create_admin_page() {
		$this->framewp_options = get_option('framewp_options'); ?>

		<div class="wrap">
			<h2>FrameWP Settings</h2>
			<p></p>
			<?php settings_errors(); ?>

			<form method="post" action="options.php">
				<?php
					settings_fields('framewp_option_group');
					do_settings_sections('framewp-admin');
					submit_button();
				?>
			</form>
		</div>
	<?php }

	public function framewp_page_init() {
		register_setting(
			'framewp_option_group', // option_group
			'framewp_options', // option_name
			array($this, 'framewp_sanitize') // sanitize_callback
		);
		
		// SETTINGS SECTIONS
		add_settings_section(
			'framewp_landing_page_section', // id
			'Landing Page Settings', // title
			array($this, 'framewp_landing_page_section_info'), // callback
			'framewp-admin' // page
		);
		
		add_settings_section(
			'framewp_content_page_section',
			'Content Page Settings',
			array($this, 'framewp_content_page_section_info'),
			'framewp-admin'
		);
		
		add_settings_section(
			'framewp_window_settings_section',
			'Window Settings',
			array($this, 'framewp_window_section_info'),
			'framewp-admin'
		);
		
		add_settings_section(
			'framewp_unique_identifier_section',
			'Unique Identifier Settings',
			array($this, 'framewp_unique_identifier_section_info'),
			'framewp-admin'
		);
		
		// SETTINGS FIELDS
		add_settings_field(
			'landing_page', // id
			'Landing Page', // title
			array($this, 'landing_page_callback'), // callback
			'framewp-admin', // page
			'framewp_landing_page_section' // section
		);
		
		add_settings_field(
			'landing_page_img_url',
			'Landing Page Image URL',
			array($this, 'landing_page_img_url_callback'),
			'framewp-admin',
			'framewp_landing_page_section'
		);

		add_settings_field(
			'content_page_folder',
			'Content Page',
			array($this, 'content_page_folder_callback'),
			'framewp-admin',
			'framewp_content_page_section'
		);
		
		add_settings_field(
			'unique_code',
			'Unique Code',
			array($this, 'unique_code_callback'),
			'framewp-admin',
			'framewp_content_page_section'
		);
		
		add_settings_field(
			'posts_page',
			'Posts Page',
			array($this, 'posts_page_callback'),
			'framewp-admin',
			'framewp_content_page_section'
		);
		
		add_settings_field(
			'show_toolbar',
			'Show Toolbar',
			array($this, 'show_toolbar_callback'),
			'framewp-admin',
			'framewp_window_settings_section'
		);

		add_settings_field(
			'show_menubar',
			'Show Menubar',
			array($this, 'show_menubar_callback'),
			'framewp-admin',
			'framewp_window_settings_section'
		);

		add_settings_field(
			'show_scrollbars',
			'Show Scrollbars',
			array($this, 'show_scrollbars_callback'),
			'framewp-admin',
			'framewp_window_settings_section'
		);

		add_settings_field(
			'resizable_window',
			'Resizable Window',
			array($this, 'resizable_window_callback'),
			'framewp-admin',
			'framewp_window_settings_section'
		);
		
		add_settings_field(
			'secret_key',
			'Secret Key',
			array($this, 'secret_key_callback'),
			'framewp-admin',
			'framewp_unique_identifier_section'
		);
		
		add_settings_field(
			'secret_iv',
			'Secret Initialization Vector',
			array($this, 'secret_iv_callback'),
			'framewp-admin',
			'framewp_unique_identifier_section'
		);
	}

	public function framewp_sanitize($input) {
		$sanitary_values = array();
		if (isset( $input['content_page_folder'])) {
			$sanitary_values['content_page_folder'] = sanitize_text_field( $input['content_page_folder'] );
		}

		if (isset($input['unique_code'])) {
			$sanitary_values['unique_code'] = sanitize_text_field( $input['unique_code'] );
		}

		if (isset($input['show_toolbar'])) {
			$sanitary_values['show_toolbar'] = $input['show_toolbar'];
		}

		if (isset($input['show_menubar'])) {
			$sanitary_values['show_menubar'] = $input['show_menubar'];
		}

		if (isset($input['show_scrollbars'])) {
			$sanitary_values['show_scrollbars'] = $input['show_scrollbars'];
		}

		if (isset($input['resizable_window'])) {
			$sanitary_values['resizable_window'] = $input['resizable_window'];
		}
		
		if (isset($input['landing_page'])) {
			$sanitary_values['landing_page'] = $input['landing_page'];
		}
		
		if (isset($input['landing_page_img_url'])) {
			$sanitary_values['landing_page_img_url'] = $input['landing_page_img_url'];
		}
		
		if (isset($input['posts_page'])) {
			$sanitary_values['posts_page'] = $input['posts_page'];
		}
		
		if (isset($input['secret_key'])) {
			$sanitary_values['secret_key'] = $input['secret_key'];
		}
		
		if (isset($input['secret_iv'])) {
			$sanitary_values['secret_iv'] = $input['secret_iv'];
		}

		return $sanitary_values;
	}

	public function framewp_landing_page_section_info() {
		print 'The landing page is the first page your visitors will see when they go to <strong>' . get_site_url() . '</strong>, as defined in WordPress general settings. You can create a new page and choose "FrameWP Landing Page" under templates. The plugin will take care of the rest.</p><p>The other option is to create a new page and add this shortcode:</p><p><span style="color:red;font-weight:bold">[framewp_launcher]</span><span style="color:black;font-weight:bold">Link title!</span><span style="color:red;font-weight:bold">[/framewp_launcher]</span></p><p>Make sure your landing page is the same as the static homepage in WordPress reading settings.';
	}
	
	public function framewp_content_page_section_info() {
		print 'The "Content Page" and "Unique Code" will become a part of your website\'s replacement URL. Users should see the following URL when browsing in window mode no matter what page they\'re on (except for the landing page):</p><p style="color:blue;font-weight:bold">' . get_content_page_url() . '</p>';
	}
	
	public function framewp_window_section_info() {
		printf('Control your default window settings. Chances are these settings will not matter if the user is running a modern brower.');
	}

	public function framewp_unique_identifier_section_info() {

	}

	public function landing_page_callback() {
		wp_dropdown_pages(array(
    		'child_of'     => 0,
    		'sort_order'   => 'ASC',
			'sort_column'  => 'post_title',
			'hierarchical' => 1,
			'post_type' => 'page',
			'name' => 'framewp_options[landing_page]',
			'id' => 'landing_page',
			'selected' => isset($this->framewp_options['landing_page']) ? esc_attr($this->framewp_options['landing_page']) : ''
		));
	}
	

	
	public function landing_page_img_url_callback() {
		printf(
			'<input class="regular-text" type="text" name="framewp_options[landing_page_img_url]" id="landing_page_img_url" value="%s">',
			isset($this->framewp_options['landing_page_img_url']) ? esc_attr($this->framewp_options['landing_page_img_url']) : ''
		);
	}
	
	public function content_page_folder_callback() {
		wp_dropdown_pages(array(
    		'child_of'     => 0,
    		'sort_order'   => 'ASC',
			'sort_column'  => 'post_title',
			'hierarchical' => 1,
			'post_type' => 'page',
			'name' => 'framewp_options[content_page_folder]',
			'id' => 'content_page_folder',
			'selected' => isset($this->framewp_options['content_page_folder']) ? esc_attr($this->framewp_options['content_page_folder']) : ''
		));
	}
	
	public function unique_code_callback() {
		printf(
			'<input class="regular-text" type="text" name="framewp_options[unique_code]" id="unique_code" value="%s">',
			isset($this->framewp_options['unique_code']) ? esc_attr($this->framewp_options['unique_code']) : ''
		);
	}
	
	public function posts_page_callback() {
		wp_dropdown_pages(array(
    		'child_of'     => 0,
    		'sort_order'   => 'ASC',
			'sort_column'  => 'post_title',
			'hierarchical' => 1,
			'post_type' => 'page',
			'name' => 'framewp_options[posts_page]',
			'id' => 'posts_page',
			'selected' => isset($this->framewp_options['posts_page']) ? esc_attr($this->framewp_options['posts_page']) : ''
		));
	}

	public function show_toolbar_callback() {
		printf(
			'<input disabled type="checkbox" name="framewp_options[show_toolbar]" id="show_toolbar" value="show_toolbar" %s>',
			(isset($this->framewp_options['show_toolbar']) && $this->framewp_options['show_toolbar'] === 'show_toolbar') ? 'checked' : ''
		);
	}

	public function show_menubar_callback() {
		printf(
			'<input disabled type="checkbox" name="framewp_options[show_menubar]" id="show_menubar" value="show_menubar" %s>',
			(isset($this->framewp_options['show_menubar']) && $this->framewp_options['show_menubar'] === 'show_menubar') ? 'checked' : ''
		);
	}

	public function show_scrollbars_callback() {
		printf(
			'<input type="checkbox" name="framewp_options[show_scrollbars]" id="show_scrollbars" value="show_scrollbars" %s>',
			(isset($this->framewp_options['show_scrollbars']) && $this->framewp_options['show_scrollbars'] === 'show_scrollbars') ? 'checked' : ''
		);
	}

	public function resizable_window_callback() {
		printf(
			'<input type="checkbox" name="framewp_options[resizable_window]" id="resizable_window" value="resizable_window" %s>',
			(isset($this->framewp_options['resizable_window']) && $this->framewp_options['resizable_window'] === 'resizable_window') ? 'checked' : ''
		);
	}
	
	public function secret_key_callback() {
		printf(
			'<input class="regular-text" type="text" name="framewp_options[secret_key]" id="secret_key" value="%s">',
			isset($this->framewp_options['secret_key']) ? esc_attr($this->framewp_options['secret_key']) : ''
		);
	}
	
	public function secret_iv_callback() {
		printf(
			'<input class="regular-text" type="text" name="framewp_options[secret_iv]" id="secret_iv" value="%s">',
			isset($this->framewp_options['secret_iv']) ? esc_attr($this->framewp_options['secret_iv']) : ''
		);
	}

}

if (is_admin() )
	$framewp = new FrameWP_Options();

/*
--------------------------------------------
	PAGE TEMPLATES
--------------------------------------------
*/

class FrameWP_Templates {
	// A reference to an instance of this class.
	private static $instance;

	// The array of templates that this plugin tracks.
	protected $templates;

	// Returns an instance of this class.
	public static function get_instance() {
		if (null == self::$instance) { self::$instance = new FrameWP_Templates(); } 

		return self::$instance;
	} 

	// Initializes the plugin by setting filters and administration functions.
	private function __construct() {
		$this->templates = array();

		// Add a filter to the attributes metabox to inject template into the cache.
		if ( version_compare( floatval(get_bloginfo( 'version' ) ), '4.7', '<' ) ) {
			// 4.6 and older
			add_filter('page_attributes_dropdown_pages_args', array($this, 'register_project_templates'));
		} else {
			// Add a filter to the wp 4.7 version attributes metabox
			add_filter('theme_page_templates', array($this, 'add_new_template'));
		}

		// Add a filter to the save post to inject out template into the page cache
		add_filter('wp_insert_post_data', array($this, 'register_project_templates'));

		// Add a filter to the template include to determine if the page has our template assigned and return it's path
		add_filter('template_include', array($this, 'view_project_template'));

		// Add your templates to this array.
		$this->templates = array(
			'iframe-page.php' => 'FrameWP Iframe Page',
			'landing-page.php' => 'FrameWP Landing Page',
		);		
	} 

	// Adds our template to the page dropdown for v4.7+
	public function add_new_template($posts_templates) {
		$posts_templates = array_merge($posts_templates, $this->templates);
		return $posts_templates;
	}

	// Adds our template to the pages cache in order to trick WordPress into thinking the template file exists where it doens't really exist.
	public function register_project_templates($atts) {
		// Create the key used for the themes cache
		$cache_key = 'page_templates-' . md5(get_theme_root() . '/' . get_stylesheet());

		// Retrieve the cache list. 
		// If it doesn't exist, or it's empty prepare an array
		$templates = wp_get_theme()->get_page_templates();
		if (empty($templates)) {
			$templates = array();
		} 

		// New cache, therefore remove the old one
		wp_cache_delete($cache_key , 'themes');

		// Now add our template to the list of templates by merging our templates
		// with the existing templates array from the cache.
		$templates = array_merge($templates, $this->templates);

		// Add the modified cache to allow WordPress to pick it up for listing
		// available templates
		wp_cache_add($cache_key, $templates, 'themes', 1800);

		return $atts;

	} 

	// Checks if the template is assigned to the page
	public function view_project_template($template) {
		// Get global post
		global $post;

		// Return template if post is empty
		if (!$post) { return $template; }

		// Return default template if we don't have a custom one defined
		if (!isset($this->templates[get_post_meta($post->ID, '_wp_page_template', true)])) { return $template; } 

		$file = plugin_dir_path( __FILE__ ). get_post_meta( 
			$post->ID, '_wp_page_template', true
		);

		// Check if the file exists just to be safe
		if (file_exists($file)) {
			return $file;
		} else {
			wp_die('The template file, ' . $file . ' could not be found.', get_bloginfo('name'));
		}

		// Return template
		return $template;
	}

} 
add_action( 'plugins_loaded', array('FrameWP_Templates', 'get_instance') );

?>
