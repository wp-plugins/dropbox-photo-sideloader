<?php
/*
Plugin Name: Dropbox Photo Sideloader
Plugin URI: http://ottopress.com/wordpress-plugins/dropbox-photo-sideloader/
Description: Adds a new tab to the Media Uploader, which allows you to pull image files from your Dropbox into WordPress.
Version: 0.1
Author: Otto
Author URI: http://ottopress.com
License: GPLv2
License URI: http://www.opensource.org/licenses/GPL-2.0
*/




define('DROPBOX_KEY', 		'put your dropbox app key in here');
define('DROPBOX_SECRET', 	'put your dropbox app secret in here');






add_action('init','dbsideload_init');
function dbsideload_init() {
	
	require_once 'Dropbox/OAuth.php';
	require_once 'Dropbox/OAuth/Wordpress.php';
	require_once 'Dropbox/API.php';

	global $dbsideload_oauth, $dropbox;

	$dbsideload_oauth = new Dropbox_OAuth_Wordpress(DROPBOX_KEY, DROPBOX_SECRET);
	$dropbox = new Dropbox_API($dbsideload_oauth);

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
	
	$tokens = $dbsideload_oauth->getRequestToken();
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

function dbsideload_check_sideload($post_id) {
	global $dropbox;

	if (!empty($_POST['submit']) && !empty($_POST['dropboxfiles'])) {

		// necessary for old ssl certs
		//add_filter('https_ssl_verify','__return_false');

		$dbfiles = (array) $_POST['dropboxfiles'];
		echo '<ul>';		
		foreach($dbfiles as $file) {
			echo "<li>Sideloading {$file} ... ";
			$tempurl = $dropbox->media($file);
			$result = media_sideload_image($tempurl['url'], $post_id);
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