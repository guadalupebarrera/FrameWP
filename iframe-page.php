<?php
	$framewp_option = get_option('framewp_options');
	$page_code = $framewp_option['unique_code'];

	$website_title = get_bloginfo('name');
	$plugin_dir_url = plugin_dir_url( __FILE__ );

	$website_id = $_GET['id'];
	if (isset($website_id)) {
		$website_idURL = array(
			$page_code => get_posts_page_url()
		);
	}

	$approved_referers = array(get_landing_page_url());

?>
<!doctype html>
<html>
<head>
<meta charset="UTF-8">
<title><?php echo $website_title; ?></title>
<link href="<?php echo $plugin_dir_url . "iframe-page.css"; ?>" rel="stylesheet" type="text/css">
</head>

<body>
<?php
	$client_referer = $_SERVER['HTTP_REFERER'];
	if (isset($client_referer)) {
		$i = 0;
		$array_length = count($approved_referers);
		while ($i < $array_length) {
			if ($client_referer == $approved_referers[$i]) {
				echo '<iframe src="' . $website_idURL[$page_code] . '" title="' . $website_title .'" frameborder="0" marginheight="0" marginwidth="0" width="100%" height="100%" scrolling="auto"></iframe>';
				break;
			}
			$i++;
		}
	} 
?>
</body>
</html>