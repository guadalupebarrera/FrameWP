<?php
	$website_title = get_bloginfo('name');
	$plugin_dir_url = plugin_dir_url( __FILE__ );

	$framewp_option = get_option('framewp_options');

	$toolbar = $framewp_option['show_toolbar'];
	$menubar = $framewp_option['show_menubar'];
	$scrollbars = $framewp_option['show_scrollbars'];
	$resizable = $framewp_option['resizable_window'];
	
	if (isset($toolbar) && $toolbar === 'show_toolbar') {
		$toolbar = 'yes';
	} else {
		$toolbar = 'no';
	}

	if (isset($menubar) && $menubar === 'show_menubar') {
		$menubar = 'yes';
	} else {
		$menubar = 'no';
	}

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
$(document).ready(function(){
   $(document).bind("contextmenu",function(e){
      return false;
   });
});
</script>
</head>

<body>

<div class="parent">
	<div class="child">
	<?php 
		if (!isset($img_url) || $img_url === '') { 
			echo "<h1>" . $website_title . "</h1>";
		} else { 
			echo "<a href=\"link\" onclick=\"javascript:window.open('" . get_content_page_url() . "','Windows','width=+screen.availWidth+,height=+screen.availHeight,toolbar=" . esc_attr($toolbar) . ",menubar=" . esc_attr($menubar) . ",scrollbars=" . esc_attr($scrollbars) . ",resizable=" . esc_attr($resizable) . ",location=no,directories=no,status=no');return false\" )\"=\"\"><img src=\"" . $img_url . "\" width=\"" . $img_width . "\" height=\"" . $img_height . "\" alt=\"Enter\" title=\"Enter " . $website_title . "\" /></a>";
		} 
	?>
	<?php echo "<a id=\"enter-button\" href=\"link\" onclick=\"javascript:window.open('" . get_content_page_url() . "','Windows','width=+screen.availWidth+,height=+screen.availHeight,toolbar=" . esc_attr($toolbar) . ",menubar=" . esc_attr($menubar) . ",scrollbars=" . esc_attr($scrollbars) . ",resizable=" . esc_attr($resizable) . ",location=no,directories=no,status=no');return false\" )\"=\"\">Enter</a>"; ?>
	</div>
</div>

</body>
</html>
