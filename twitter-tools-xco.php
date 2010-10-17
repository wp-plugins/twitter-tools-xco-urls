<?php
/*
Plugin Name: Twitter Tools - x.co URLs 
Plugin URI: http://www.chriskdesigns.com
Description: Use x.co for URL shortening with Twitter Tools. This plugin relies on Twitter Tools, configure it on the Twitter Tools settings page.
Version: 1.1.1
Author: Chris Klosowski
Author URI: http://www.chriskdesigns.com
*/

// Thanks to the writers of Twitter Tools for their Bit.ly plugin, which this plugin has used as a template

if (!defined('PLUGINDIR')) {
	define('PLUGINDIR','wp-content/plugins');
}

load_plugin_textdomain('twitter-tools-xco');

define('AKTT_XCO_API_SHORTEN_URL', 'http://api.x.co/Squeeze.svc');

function aktt_xco_shorten_url($url) {
	$parts = parse_url($url);
	if (!in_array($parts['host'], array('x.co'))) {
		$snoop = get_snoopy();
		$api_urls = array(
			'xco' => AKTT_XCO_API_SHORTEN_URL,
		);
		$key = get_option('aktt_xco_api_key');
		$api = 'http://api.x.co/Squeeze.svc/' . $key . '?url='.urlencode($url);
		$snoop->agent = 'Twitter Tools - X.co Urls http://www.chriskdesigns.com';
		$snoop->fetch($api);
		$url = json_decode($snoop->results);
		if (!empty($result->results->{$url}->shortUrl)) {
			$url = $result->results->{$url}->shortUrl;
		}
	}
	return $url;
}
add_filter('tweet_blog_post_url', 'aktt_xco_shorten_url');

function aktt_xco_shorten_tweet($tweet) {
	if (strpos($tweet->tw_text, 'http') !== false) {
		preg_match_all('$\b(https?|ftp|file)://[-A-Z0-9+&@#/%?=~_|!:,.;]*[-A-Z0-9+&@#/%=~_|]$i', $test, $urls);
		if (isset($urls[0]) && count($urls[0])) {
			foreach ($urls[0] as $url) {
// borrowed from WordPress's make_clickable code
				if ( in_array(substr($url, -1), array('.', ',', ';', ':', ')')) === true ) {
					$url = substr($url, 0, strlen($url)-1);
				}
				$tweet->tw_text = str_replace($url, aktt_xco_shorten_url($url), $tweet->tw_text);
			}
		}
	}
	return $tweet;
}
add_filter('aktt_do_tweet', 'aktt_xco_shorten_tweet');

function aktt_xco_request_handler() {
	if (!empty($_POST['cf_action'])) {
		switch ($_POST['cf_action']) {
			case 'aktt_xco_update_settings':
				if (!wp_verify_nonce($_POST['_wpnonce'], 'aktt_xco_save_settings')) {
					wp_die('Oops, please try again.');
				}
				aktt_xco_save_settings();
				wp_redirect(admin_url('options-general.php?page=twitter-tools.php&updated=true'));
				die();
				break;
		}
	}
}
add_action('init', 'aktt_xco_request_handler');

$aktt_xco_settings = array(
	'aktt_xco_api_key' => array(
		'type' => 'string',
		'label' => __('X.co API key', 'twitter-tools-xco'),
		'default' => '',
		'help' => '',
	),
);

function aktt_xco_setting($option) {
	$value = get_option($option);
	if (empty($value)) {
		global $aktt_xco_settings;
		$value = $aktt_xco_settings[$option]['default'];
	}
	return $value;
}

if (!function_exists('cf_settings_field')) {
	function cf_settings_field($key, $config) {
		$option = get_option($key);
		if (empty($option) && !empty($config['default'])) {
			$option = $config['default'];
		}
		$label = '<label for="'.$key.'">'.$config['label'].'</label>';
		$help = '<span class="help">'.$config['help'].'</span>';
		switch ($config['type']) {
			case 'select':
				$output = $label.'<select name="'.$key.'" id="'.$key.'">';
				foreach ($config['options'] as $val => $display) {
					$option == $val ? $sel = ' selected="selected"' : $sel = '';
					$output .= '<option value="'.$val.'"'.$sel.'>'.htmlspecialchars($display).'</option>';
				}
				$output .= '</select>'.$help;
				break;
			case 'textarea':
				$output = $label.'<textarea name="'.$key.'" id="'.$key.'">'.htmlspecialchars($option).'</textarea>'.$help;
				break;
			case 'string':
			case 'int':
			default:
				$output = $label.'<input name="'.$key.'" id="'.$key.'" value="'.htmlspecialchars($option).'" />'.$help;
				break;
		}
		return '<div class="option">'.$output.'<div class="clear"></div></div>';
	}
}

function aktt_xco_settings_form() {
	global $aktt_xco_settings;

	print('
<div class="wrap">
	<h2>'.__('X.co for Twitter Tools', 'twitter-tools-xco').'</h2>
	<form id="aktt_xco_settings_form" class="aktt" name="aktt_xco_settings_form" action="'.admin_url('options-general.php').'" method="post">
		<input type="hidden" name="cf_action" value="aktt_xco_update_settings" />
		<fieldset class="options">
	');
	foreach ($aktt_xco_settings as $key => $config) {
		echo cf_settings_field($key, $config);
	}
	print('
		</fieldset>
		<p class="submit">
			<input type="submit" name="submit" class="button-primary" value="'.__('Save Settings', 'twitter-tools-xco').'" />
		</p>
		'.wp_nonce_field('aktt_xco_save_settings', '_wpnonce', true, false).wp_referer_field(false).'
	</form>
</div>
	');
}
add_action('aktt_options_form', 'aktt_xco_settings_form');

function aktt_xco_save_settings() {
	if (!current_user_can('manage_options')) {
		return;
	}
	global $aktt_xco_settings;
	foreach ($aktt_xco_settings as $key => $option) {
		$value = '';
		switch ($option['type']) {
			case 'int':
				$value = intval($_POST[$key]);
				break;
			case 'select':
				$test = stripslashes($_POST[$key]);
				if (isset($option['options'][$test])) {
					$value = $test;
				}
				break;
			case 'string':
			case 'textarea':
			default:
				$value = stripslashes($_POST[$key]);
				break;
		}
		$value = trim($value);
		update_option($key, $value);
	}
}


if (!function_exists('get_snoopy')) {
	function get_snoopy() {
		include_once(ABSPATH.'/wp-includes/class-snoopy.php');
		return new Snoopy;
	}
}

// Dashboard Widget Section

// The Output
function xco_urls_widget_function() {
	global $wpdb;
	$list = xco_urls_get();
	if ($list) {
		get_posts_from_urls($list);
	} else {
		print('<span><em>Currently No Posts with X.co URLs</em></span>');
	}
} 

// The Function to Hook
function xco_urls_add_dashboard_widget() {
	wp_add_dashboard_widget('xco_urls_dashboard_widget', 'X.co URL Stats', 'xco_urls_widget_function');	
} 

// The Hook
add_action('wp_dashboard_setup', 'xco_urls_add_dashboard_widget' );


// Business Functions for the Dashboard widget
function xco_urls_get() {
	$key = get_option('aktt_xco_api_key');
	$jsonData = file_get_contents('http://api.x.co/Reporting.svc/maps/'.$key.'?p=1&s=255');
	$jsonDecoded = json_decode($jsonData);
	$siteURL = trim(domain(trim(get_bloginfo('url'))));
	$list = array();
	foreach ($jsonDecoded->GetMapsResult as $key=>$linkdata) {
		if ( strpos( $linkdata->Url, $siteURL ) ) {
			if (!get_option('permalink_structure')) {
				$list[$key]['url'] = $linkdata->Url;
			} else {
				$list[$key]['url'] = substr( strrchr( substr( $linkdata->Url, 0, strlen( $linkdata->Url ) - 1 ), '/' ), 1 );
			}
			$list[$key]['clicks'] = $linkdata->TotalClicks;
			$list[$key]['short'] = 'http://x.co/' . $linkdata->Encoded;
		}
	}

	return $list;
}

add_action ('admin_head', 'xco_dashboard_css');
function xco_dashboard_css() {
	?><link rel="stylesheet" type="text/css" href="<?php bloginfo('url');?>/wp-content/plugins/twitter-tools-xco-urls/styles.css" /> <?php
}

function get_posts_from_urls($list) {
	global $wpdb;
	?><div id="xco_stats"><table><tr><td width="75%" class="titlecol title_label">Post</td><td width="25%" class="clickscol clicks_label">Clicks</td></tr><?php
	$i = 0;
	if (!get_option('permalink_structure')) {
		$permCol = 'guid';
	}
	foreach ($list as $key=>$post) {
		if ($i < 10) {
			if ($permCol == 'guid') {
				$postData = $wpdb->get_results( $wpdb->prepare ( 'SELECT ID, post_title from wp_posts WHERE guid = %s AND post_type = "post"', $post['url'] ) );
			} else {
				$postData = $wpdb->get_results( $wpdb->prepare ( 'SELECT ID, post_title from wp_posts WHERE post_name = %s AND post_type = "post"', $post['url'] ) );
			}
				if ($postData[0]->ID) {
				?>
				<tr>
					<td class="title"><a href="<? echo get_permalink($postData[0]->ID); ?>"><?php echo $postData[0]->post_title; ?></a> - <span class="edit"><a href="post.php?post=<?php echo $postData[0]->ID; ?>&action=edit">Edit</a></span></td>
					<td class="clicks"><?php echo $post['clicks']; ?></td>
				</tr>
				<tr>
					<td colspan="2" class="shorturl"><?php echo $post['short']; ?><br /></td>
				</tr>
			<?php
				}
			$i++;
		}
	}
	?><tr class="footer"><td colspan="2">Limited to most recent 10 posts</td></tr></table></div><?php
	return;
}

// Domain RegEx Section
function fulldomain($site_url) {
    $bits = explode('/', $site_url);
    if ($bits[0]=='http:' || $bits[0]=='https:')
        {
        return $bits[0].'//'.$bits[2].'/';
        } else {
        return 'http://'.$bits[0].'/';
        }
    unset($bits);
    }

//function two - use regex to get entire domain
function preg_fulldomain ($site_url) {
    return 

preg_replace('/^([a-z0-9][a-z0-9\-]{1,63})\.[a-z\.]{2,6}$/i','$1',$domain);
//preg_replace('/^((http(s)?:\/\/)?([^\/]+)(\/)?)(.*)/','$1',$domain);
    }

//function three - get domain and remove subdomain.
function domain($site_url)
	{
	$bits = explode('/', $site_url);
	if ($bits[0]=='http:' || $bits[0]=='https:')
		{
		$site_url= $bits[2];
		} else {
		$site_url= $bits[0];
		}
	unset($bits);
	$bits = explode('.', $site_url);
	$idz=count($bits);
	$idz-=3;
	if (strlen($bits[($idz+2)])==2) {
	$url=$bits[$idz].'.'.$bits[($idz+1)].'.'.$bits[($idz+2)];
	} else if (strlen($bits[($idz+2)])==0) {
	$url=$bits[($idz)].'.'.$bits[($idz+1)];
	} else {
	$url=$bits[($idz+1)].'.'.$bits[($idz+2)];
	}
	return $url;
	}
?>