<?php
	$website_title = get_bloginfo('name');
	$plugin_dir_url = plugin_dir_url( __FILE__ );

	$framewp_option = get_option('framewp_options');

	$scrollbars = $framewp_option['show_scrollbars'];
	$resizable = $framewp_option['resizable_window'];
	
	if (isset($scrollbars) && $scrollbars === 'show_scrollbars') {
		$scrollbars = 'yes';
	} else {
		$scrollbars = 'no';
	}

	if (isset($resizable) && $resizable === 'resizable_window') {
		$resizable = 'yes';
	} else {
		$resizable = 'no';
	}

	$img_url = $framewp_option['landing_page_img_url'];

	list($img_width, $img_height, $img_type, $attr) = getimagesize($img_url);

?>
<!doctype html>
<html>
<head>
<meta charset="UTF-8">
<title><?php echo $website_title; ?></title>
<link href="<?php echo $plugin_dir_url . "landing-page.css"; ?>" rel="stylesheet" type="text/css">
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
<script>
	$(document).ready(function() {
		$(document).bind("contextmenu",function(e) {
      		return false;
		});
	});

	var windowObjectReference = null;
	var windowFeatures = "resizable=<?php echo $resizable; ?>,scrollbars=<?php echo $scrollbars; ?>,status";
	
	function openRequestedPopup() {
		if (windowObjectReference == null || windowObjectReference.closed) {
			windowObjectReference = window.open(
				"<?php echo get_content_page_url(); ?>",
				"<?php echo $website_title; ?>",
				windowFeatures
			);
		} else {
			windowObjectReference.focus();
		};
	}
</script>
</head>

<body>

<div class="parent">
	<div class="child">
	<?php if (!isset($img_url) || $img_url ==='') : ?>
		<h1><?php echo $website_title; ?></h1><?php else : ?>
		<img src="<?php echo $img_url; ?>" width="<?php echo $img_width; ?>" height="<?php echo $img_height; ?>" alt="<?php echo $website_title; ?>" /><?php endif; ?>
		<a id="enter-button" href="#" onclick="openRequestedPopup(); return false;" title="<?php echo $website_title; ?>">Enter</a>
	</div>
</div>

</body>
</html>
