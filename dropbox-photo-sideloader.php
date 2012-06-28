<?php
/*
Plugin Name: Dropbox Photo Sideloader
Plugin URI: http://ottopress.com/wordpress-plugins/dropbox-photo-sideloader/
Description: Adds a new tab to the Media Uploader, which allows you to pull image files from your Dropbox into WordPress.
Version: 0.3
Author: Otto
Author URI: http://ottopress.com
License: GPLv2
License URI: http://www.opensource.org/licenses/GPL-2.0
*/

/*
// These aren't needed anymore, but you can use them in your wp-config.php if you want to skip the configuration steps in the plugin screen.

define('DROPBOX_KEY', 		'put your dropbox app key in here');
define('DROPBOX_SECRET', 	'put your dropbox app secret in here');
*/

add_action('init','dbsideload_init');
function dbsideload_init() {
	
	require_once 'Dropbox/OAuth.php';
	require_once 'Dropbox/OAuth/Wordpress.php';
	require_once 'Dropbox/API.php';

	global $dbsideload_oauth, $dropbox;
	
	$options = get_option('dbsideload');
	
	if (defined('DROPBOX_KEY')) $options['key'] = DROPBOX_KEY;
	if (defined('DROPBOX_SECRET')) $options['secret'] = DROPBOX_SECRET;
	
	if (!empty($options['key']) && !empty($options['secret'])) {
		$dbsideload_oauth = new Dropbox_OAuth_Wordpress($options['key'], $options['secret']);
		$dropbox = new Dropbox_API($dbsideload_oauth);
	}

	global $wp;
	$wp->add_query_var('dbsideloadoauth');
}

add_filter('media_upload_tabs','dbsideload_photos_upload_tab');
function dbsideload_photos_upload_tab($tabs) {
	$tabs['dbsideloadphotos'] = 'Dropbox Images';
	return $tabs;
}

add_action('media_upload_dbsideloadphotos', 'dbsideload_photos_tab');
function dbsideload_photos_tab() {		
	$errors = array();

	return wp_iframe( 'media_dbsideload_photos_form', $errors );
}

function media_dbsideload_photos_form($errors) {
	global $redir_tab, $dropbox, $type, $tab;
	$redir_tab = 'dbsideloadphotos';

	media_upload_header();

	$post_id = intval($_REQUEST['post_id']);
	
	$path = '/';
	if (!empty($_REQUEST['dropboxpath'])) $path = $_REQUEST['dropboxpath'];
	
?>
<form id="filter" action="" method="post">
<input type="hidden" name="type" value="<?php echo esc_attr( $type ); ?>" />
<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>" />
<input type="hidden" name="post_id" value="<?php echo (int) $post_id; ?>" />
<?php
	if (!dbsideload_check_auth()) {
		echo '</form>';
		return;
	}
	
	dbsideload_check_sideload($post_id);

	$folder = $dropbox->getMetaData($path);
	
	$dirs = array();
	$files = array();
	
	foreach ($folder['contents'] as $item) {
		if ($item['is_dir']) $dirs[] = $item;
		else if (!preg_match('/[^\?]+\.(jpg|JPG|jpe|JPE|jpeg|JPEG|gif|GIF|png|PNG)/', $item['path'])) continue; //only show images
		else $files[] = $item;
	}
	
	if ($path != '/') {
		$args = $_GET;
		$url = admin_url('media-upload.php');
		$args['dropboxpath'] = dirname($path);
		if (DIRECTORY_SEPARATOR == '\\') $args['dropboxpath'] = str_replace(DIRECTORY_SEPARATOR, '/', $args['dropboxpath']);
		$url = add_query_arg( $args, $url );
		echo "<p><a href='{$url}'>Go Up a Directory</a></p>";
	}

	if (!empty($dirs)) {
		echo '<ul>';
		foreach($dirs as $dir) {
			$args = $_GET;
			$url = admin_url('media-upload.php');
			$args['dropboxpath'] = $dir['path'];
			$url = add_query_arg( $args, $url );
			echo "<li><a href='{$url}'>{$dir['path']}</a></li>";
		}
		echo '</ul>';
	}
	
	if (!empty($files)) {
		?>
<script type="text/javascript">
function toggleChecked(status) { jQuery(".dropboxfile").each( function() { jQuery(this).attr("checked",status); } ) }
</script>
		<?php
		echo '<ul><li><input type="checkbox" onclick="toggleChecked(this.checked)"> Select / Deselect All</li>';
		foreach($files as $file) {
			echo "<li><input type='checkbox' class='dropboxfile' name='dropboxfiles[]' value='{$file['path']}'></input> {$file['path']}</li>";
		}
		echo '</ul>';
		
		submit_button('Sideload these images');
	} else {
		echo '<ul><li>No image files found in this Dropbox directory.</li></ul>';
	}
?>
</form>
<?php
}

function dbsideload_check_auth() {
	global $dbsideload_oauth, $dropbox;
	
	if (!wp_http_supports(array('ssl'=>true))) {
		echo '<p>This system does not appear to support making web connections via HTTPS/SSL. This support is required for the Dropbox Photo Sideloader plugin.</p><p>Suggested fix: Have your administrator install "curl" support on the PHP installation.</p>';
		return false;
	}
	
	// regular user
	if (empty($dropbox) && !current_user_can('manage_options')) {
		echo 'Dropbox does not appear to be configured yet. Have your site administrator configure the Dropbox Photo Sideloader Plugin.';
		return false;
	}

	// admin user
	if (empty($dropbox) && current_user_can('manage_options') && dbsideload_check_setup() == false) {
		
		$options = get_option('dbsideload');
		if (empty($options['key'])) $options['key'] = '';
		if (empty($options['secret'])) $options['secret'] = '';
		
		if (!empty($_POST['dbsideload']['key'])) {
			$options['key'] = $_POST['dbsideload']['key'];
			$options['secret'] = $_POST['dbsideload']['secret'];
		}
	?>
		<p>To configure the Dropbox Photo Sideloader plugin, you'll need to do a few steps first.</p>
		<ol>
		<li>Visit <a href="https://www.dropbox.com/developers/apps" target="_blank">https://www.dropbox.com/developers/apps</a> and Click "Create an App". <br>(You'll need to have a Dropbox account to do this.)</li>
		<li>Give it a name and description. This will be displayed to users when they authorize Dropbox to talk to this website.</li>
		<li>Select "Full Dropbox" so that the plugin can access all of the user's files (for finding the images they want to upload).</li>
		<li>After the app has been created, copy the App Key and App Secret into the boxes below, and Save.</li>
		<li>Note: You can leave the App in "Development" status on Dropbox, unless other people than you need to access their own Dropboxes using the plugin. Nobody will be able to access the Dropbox of another person using this plugin, only their own.</li>
		</ol>
		<p>Dropbox App Key: <input type='text' name='dbsideload[key]' value='<?php esc_attr_e($options['key']); ?>'></input></p>
		<p>Dropbox App Secret: <input type='text' name='dbsideload[secret]' value='<?php esc_attr_e($options['secret']); ?>'></input></p>
		<?php
		wp_nonce_field('dbsideload-setup');
		submit_button('Save Dropbox App Settings');
		return false;
	}
	
	$user = wp_get_current_user();
	
	$tokens = get_user_meta($user->ID, 'dbsideload_tokens', true);
	
	if ( $tokens['type'] == 'auth' ) {
		$dbsideload_oauth->setToken($tokens);
		
		$info = $dropbox->getAccountInfo();
		if (!empty($info['error'])) {
			delete_user_meta($user->ID, 'dbsideload_tokens', $tokens);	
		}
		else {
			return true;
		}
	}
	
	global $dbsideload_oauth_tokens;
	if (isset($dbsideload_oauth_tokens)) $tokens = $dbsideload_oauth_tokens;
	else $tokens = $dbsideload_oauth->getRequestToken();
	
	$tokens['type'] = 'request';		
	update_user_meta($user->ID, 'dbsideload_tokens', $tokens);
	$url = $dbsideload_oauth->getAuthorizeUrl(home_url('?dbsideloadoauth=1'));
	?>
	<script>
	function dbsideload_poptastic(url) {
		var newWindow = window.open(url, 'name', 'height=350,width=450,toolbar=0,titlebar=0,resizable=0,status=0,location=0');
		if (window.focus) {
			newWindow.focus();
		}
	}
	</script>
	<p>You must authorize Dropbox to connect to your Application for the plugin to be able to retrieve data from it.</p>
	<p><a onclick="dbsideload_poptastic('<?php echo $url; ?>'); return false;" href='<?php echo $url; ?>'>Click here to authorize Dropbox.</a> (This link will pop up an authorization window.)</p>
	<?php
	return false;
}

function dbsideload_check_setup() {
	global $dbsideload_oauth, $dropbox;
	
	if (!empty($_POST['submit']) && !empty($_POST['dbsideload']['key'])) {
		check_admin_referer('dbsideload-setup');
		
		$options=$_POST['dbsideload'];

		$dbsideload_oauth = new Dropbox_OAuth_Wordpress($options['key'], $options['secret']);
		
		// try to get a request token, to test the key and secret
		$error='';
		try {
			$tokens = $dbsideload_oauth->getRequestToken();
			
			// save the token for later, so we don't have to re-request it
			global $dbsideload_oauth_tokens;
			$dbsideload_oauth_tokens = $tokens;
		} catch (Exception $e) {
			$error = $e->getMessage();
		}
		
		if ($error) {
			echo "<div class='error'>{$error}</div>";
			return false;
		}
		
		$dropbox = new Dropbox_API($dbsideload_oauth);
		
		update_option('dbsideload',$options);
		
		return true;
	}
	return false;
}

function dbsideload_check_sideload($post_id) {
	global $dropbox;

	if (!empty($_POST['submit']) && !empty($_POST['dropboxfiles'])) {

		// necessary for old ssl certs
		add_filter('https_ssl_verify','__return_false');

		$dbfiles = (array) $_POST['dropboxfiles'];
		echo '<ul>';		
		foreach($dbfiles as $file) {
			echo "<li>Sideloading {$file} ... ";
			$tempurl = $dropbox->media($file);
			$result = media_sideload_image(urldecode($tempurl['url']), $post_id);
			if (is_wp_error($result)) {
				echo 'Error when sideloading.<br />';
				echo $result->get_error_message();
			} else {
				echo 'Success!';
			}
			echo '</li>';
		}
		echo '</ul>';
		
		remove_filter('https_ssl_verify','__return_false');
	}
}


add_action('template_redirect','dbsideload_oauth_catcher');
function dbsideload_oauth_catcher() {
	global $dbsideload_oauth;
	if ( get_query_var('dbsideloadoauth') == 1 ) {
		$user = wp_get_current_user();
		$tokens = get_user_meta($user->ID, 'dbsideload_tokens', true);
		$dbsideload_oauth->setToken($tokens);
		$tokens = $dbsideload_oauth->getAccessToken();
		$tokens['type'] = 'auth';
		update_user_meta($user->ID, 'dbsideload_tokens', $tokens);
		$dbsideload_oauth->setToken($tokens);
		?>
<html><body>
<p>Authorization complete. You can close this window now.</p>
<script type="text/javascript">
window.opener.location.reload(true);
window.close();
</script>
</body></html>
		<?php
		exit;
	}
}
