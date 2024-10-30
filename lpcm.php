<?php
/*
Plugin Name: LP Content Manager
Plugin URI: http://luminaryplugins.com
Description: LP Content Manager makes it easy to create and manage custom content on your website by allowing you to create content templates and insert them onto pages using a shortcode. The entire creation process is straight-forward and completed through the WordPress dashboard, so no programming knowledge is required.
Version: 2.0.3
Author: Luminary Plugins
Author URI: http://luminaryplugins.com
*/

global $lpcm_db_version;
$lpcm_db_version = "1.1";
global $template_table_name;
global $item_table_name;
global $attribute_table_name;
global $attributeValue_table_name;
$template_table_name = $wpdb->prefix . "lpcm_templates";
$item_table_name = $wpdb->prefix . "lpcm_items";
$attribute_table_name = $wpdb->prefix . "lpcm_attributes";
$attributeValue_table_name = $wpdb->prefix . "lpcm_attributeValues";

function lpcm_install() {
   global $wpdb;
   global $lpcm_db_version;
   $template_table_name = $wpdb->prefix . "lpcm_templates";
   $item_table_name = $wpdb->prefix . "lpcm_items";
   $attribute_table_name = $wpdb->prefix . "lpcm_attributes";
   $attributeValue_table_name = $wpdb->prefix . "lpcm_attributeValues";
   
   
      
   $sql = "CREATE TABLE $template_table_name (
  id INT NOT NULL AUTO_INCREMENT,
  name text,
  slug text,
  pre_loop text,
  item_loop text,
  post_loop text,
  item_html text,
  item_page_on int,
  PRIMARY KEY (id)
	) ENGINE=InnoDB;";

   require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
   dbDelta( $sql );
   
	  
   $sql = "CREATE TABLE $item_table_name (
  id INT NOT NULL AUTO_INCREMENT,
  name text,
  slug text,
  template int,
  sort int NOT NULL DEFAULT '9999',
  PRIMARY KEY (id),
  FOREIGN KEY (template) 
		REFERENCES $template_table_name(id)
		ON DELETE CASCADE
	) ENGINE=InnoDB;";

   require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
   dbDelta( $sql );
   
	  
   $sql = "CREATE TABLE $attribute_table_name (
  id INT NOT NULL AUTO_INCREMENT,
  name text,
  slug text,
  template int,
  type int,
  PRIMARY KEY (id),
  FOREIGN KEY (template) 
		REFERENCES $template_table_name(id)
		ON DELETE CASCADE
	) ENGINE=InnoDB;";

   require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
   dbDelta( $sql );
   
	  
   $sql = "CREATE TABLE $attributeValue_table_name (
  attribute int NOT NULL,
  item int NOT NULL,
  value text,
  PRIMARY KEY (attribute, item),
  FOREIGN KEY (attribute) 
		REFERENCES $attribute_table_name(id)
		ON DELETE CASCADE,
  FOREIGN KEY (item) 
		REFERENCES $item_table_name(id)
		ON DELETE CASCADE
	) ENGINE=InnoDB;";

   require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
   dbDelta( $sql );
 
   update_option( "lpcm_db_version", $lpcm_db_version );
   
    $attributesToUpdate = $wpdb->get_results("SELECT * FROM `".$wpdb->prefix . "lpcm_attributes`;");
	foreach($attributesToUpdate as $attribute){
		if($attribute->slug[0] == "$"){
			$wpdb->update($wpdb->prefix . "lpcm_attributes", array('slug' => "{" . substr($attribute->slug, 1) . "}"), array('id' => $attribute->id));
		}
	}
	$templatesToUpdate = $wpdb->get_results("SELECT * FROM `".$wpdb->prefix . "lpcm_templates`;");
	foreach($templatesToUpdate as $template){
		$prewords = explode(" ", $template->pre_loop);
		for($index = 0; $index < count($prewords); $index++){
			if($prewords[$index][0] == "$"){
				$prewords[$index] = "{" . substr($prewords[$index], 1) . "}";
			}
		}
		$template->pre_loop = implode(" ", $prewords);
		
		$itemwords = explode(" ", $template->item_loop);
		for($index = 0; $index < count($itemwords); $index++){
			if($itemwords[$index][0] == "$"){
				$itemwords[$index] = "{" . substr($itemwords[$index], 1) . "}";
			}
		}
		$template->item_loop = implode(" ", $itemwords);
		
		$postwords = explode(" ", $template->post_loop);
		for($index = 0; $index < count($postwords); $index++){
			if($postwords[$index][0] == "$"){
				$postwords[$index] = "{" . substr($postwords[$index], 1) . "}";
			}
		}
		$template->post_loop = implode(" ", $postwords);
		
		$wpdb->update($wpdb->prefix . "lpcm_templates", array('pre_loop' => $template->pre_loop, 'item_loop' => $template->item_loop, 'post_loop' => $template->post_loop), array('id' => $template->id));
	}
	
}

register_activation_hook( __FILE__, 'lpcm_install' );

function lpcm_handler($atts) {
	extract( shortcode_atts( array(
		'id' => '',
	), $atts ) );
	
	if(!empty($_GET['i'])){
		$output = "";
		global $wpdb;
		$template = $wpdb->get_row("SELECT * FROM  `".$wpdb->prefix . "lpcm_templates"."` WHERE slug = '".$id."'");
		$item = $wpdb->get_row("SELECT * FROM  `".$wpdb->prefix . "lpcm_items"."` WHERE template = '".$template->id."' AND slug = '".$_GET['i']."'");
		
		$attributeValues = $wpdb->get_results("SELECT * FROM `".$wpdb->prefix . "lpcm_attributes"."` INNER JOIN `".$wpdb->prefix . "lpcm_attributeValues"."` ON `attribute` = `id` WHERE `template` = '".$template->id."' AND `item` = '".$item->id."'", ARRAY_A);
		
		$strToAdd = stripslashes($template->item_html);
		foreach($attributeValues as $row){
			$strToAdd = str_replace($row['slug'], $row['value'], $strToAdd);
		}
		//$strToAdd = preg_replace('#[\b]?(\$([^ ]*))[\b]?#', '', $strToAdd);
		$output .= $strToAdd;
		
		return nl2br(do_shortcode(stripslashes($output)));
	}
	else{
	
		$output = "";
		global $wpdb;
		$wpdb->show_errors();
		$template = $wpdb->get_row("SELECT * FROM  `".$wpdb->prefix . "lpcm_templates"."` WHERE slug = '".$id."'");
		$items = $wpdb->get_results("SELECT * FROM  `".$wpdb->prefix . "lpcm_items"."` WHERE template = '".$template->id."' ORDER BY sort", ARRAY_A);
		
		$attributeValues = $wpdb->get_results("SELECT * FROM `".$wpdb->prefix . "lpcm_attributes"."` INNER JOIN `".$wpdb->prefix . "lpcm_attributeValues"."` ON `attribute` = `id` WHERE `template` = '".$template->id."' ORDER BY item", ARRAY_A);
		
		$output .= stripslashes($template->pre_loop);
		
		foreach($items as $item){
			$strToAdd = stripslashes($template->item_loop);
			$strToAdd = str_replace('$item-url', "?i=".$item['slug'], $strToAdd);
			/*
for($index = 0; $index < sizeof($attributes); $index++){
				$strToAdd = str_replace($attributes[$index], $itemAttributes[$index], $strToAdd);
			}
*/
			foreach($attributeValues as $row){
				if($item['id'] == $row['item']){
					$strToAdd = str_replace($row['slug'], $row['value'], $strToAdd);
				}
			}
			//$strToAdd = preg_replace('#[\b]?(\$([^ ]*))[\b]?#', '', $strToAdd);
			$output .= $strToAdd;
		}
		
		$output .= stripslashes($template->post_loop);
		
		return nl2br(do_shortcode(stripslashes($output)));
	}
}

add_shortcode("content-manager", "lpcm_handler");

add_action( 'admin_menu', 'register_lpcm_menu_page' );

function register_lpcm_menu_page(){
    add_menu_page( 'LP Content Management', 'Content Manager', 'manage_options', 'lpcm', 'lpcm_admin_item_content', null, 28 ); 
    add_submenu_page('lpcm', 'Items', 'Items', 'manage_options', 'lpcm', 'lpcm_admin_item_content');
    add_submenu_page('lpcm', 'New Item', 'New Item', 'manage_options', 'nitem', 'lpcm_admin_nitem_content');
    add_submenu_page('lpcm', 'Templates', 'Templates', 'manage_options', 'templates', 'lpcm_admin_templates_content');
    add_submenu_page('lpcm', 'New Template', 'New Template', 'manage_options', 'ntemplate', 'lpcm_admin_ntemplate_content');
    add_submenu_page('lpcm', 'Upgrade', 'Upgrade', 'manage_options', 'upgrade', 'lpcm_admin_licensing_content');
}

add_action('media_buttons_context', 'lpcm_media_button');

function lpcm_media_button($context){
	$context .= '<a href="#TB_inline?width=400&inlineId=lpcm_container" class="button thickbox">Content Manager</a>';
	
	return $context;
}

add_action( 'admin_footer',  'lpcm_inline_popup_content' );

function lpcm_inline_popup_content() {
	?>
		<script type="text/javascript">
			function lpcm_insert(){
				var template_id=jQuery("#template").val();
				if(template_id==""){alert("<?php _e('Please select a template.', 'lpcm') ?>");return;}
				var win = window.dialogArguments || opener || parent || top;
				win.send_to_editor("[content-manager id="+template_id+"]");
			}
		</script>
		<div id="lpcm_container" style="display:none;">
			<table class="form-table">
	    		<tbody>
	    			<tr valign="top">
	    				<th scope="row">
	    					<label for="template">Choose Template</label>
	    				</th>
	    				<td>
	    					<select name="template" id="template">
	    						<?php
	    							global $wpdb;
	    							$templates = $wpdb->get_results("SELECT  `name` ,  `slug` FROM  `".$wpdb->prefix . "lpcm_templates"."`");
	    							foreach($templates as $template){
	    								echo "<option value=\"".$template->slug."\">".htmlspecialchars(stripslashes($template->name), ENT_QUOTES)."</option>\n";
	    							}
	    						?>
	    					</select>
	    				</td>
	    			</tr>
	    		</tbody>
	    	</table>
			<p><input type="button" class="button-primary" value="Insert Template" onclick="lpcm_insert();" /></p>
		</div>
	<?php
}


function lpcm_admin_item_content(){
    ?>
    <div class="wrap">
    	<div class="icon32" id="icon-edit"><br></div>
	    <h2>Items
	    <a href="?page=nitem" class="add-new-h2">Add New</a></h2>
	    <?php
	    if(!empty($_GET['delete'])){
		    global $wpdb;
		    $wpdb->delete($wpdb->prefix . "lpcm_items", array('slug' => $_GET['delete']));
		    ?>
		    <div class="updated fade"><p>Item removed.</p></div>
		    <?php
	    }
	    global $wpdb;
		$template = $wpdb->get_results("SELECT `id`, `name` FROM  `".$wpdb->prefix . "lpcm_templates"."`", OBJECT_K);
	    ?>
	    <br>
	    <form action="?page=lpcm" method="post">
	    Filter: <select name="filter" id="filter" onchange="this.form.submit()"><option value="">All</option>
	    	<?php
	    	foreach($template as $info){
	    	echo "<option value='$info->id'>".stripslashes($info->name)."</option>";
	    	}
	    	?>
	    </select>
	    </form>
	    <br>
	    <table class="widefat page fixed">
	    	<thead>
	    		<tr>
		    		<th id="cb" class="manage-column column-cb check-column" style="" scope="col">
			    		<input type="checkbox"/>
			    	</th>
		    		<th class="manage-column">Title</th>
		    		<th class="manage-column">Template</th>
	    		</tr>
	    	</thead>
	    	<tfoot>
	    		<tr>
		    		<th id="cb" class="manage-column column-cb check-column" style="" scope="col">
			    		<input type="checkbox"/>
			    	</th>
		    		<th class="manage-column">Title</th>
		    		<th class="manage-column">Template</th>
	    		</tr>
	    	</tfoot>
	    	<tbody>
	    		<?php
		    		
		    		if(!empty($_POST['filter'])){
			    		$items = $wpdb->get_results("SELECT  `name` ,  `slug` ,  `template` FROM  `".$wpdb->prefix . "lpcm_items"."` WHERE `template` = '".$_POST['filter']."'");
		    		}else{
		    			$items = $wpdb->get_results("SELECT  `name` ,  `slug` ,  `template` FROM  `".$wpdb->prefix . "lpcm_items"."`");
		    		}
		    		
		    		foreach ($items as $item){
		    			echo "				<tr>\n";
						echo "		    		<td>\n";
						echo "		    			<input type=\"checkbox\" />\n";
						echo "		    		</td>\n";
						echo "		    		<td>\n";
						echo "		    			<strong>".htmlspecialchars(stripslashes($item->name), ENT_QUOTES)."</strong>\n";
						echo "		    			<div class=\"row-actions-visible\">\n";
						echo "			    			<span class=\"edit\"><a href=\"?page=nitem&edit=".$item->slug."\">Edit</a> | </span>\n";
						echo "			    			<span class=\"delete\"><a href=\"?page=lpcm&delete=".$item->slug."\" onclick=\"return confirm('Are you sure you want to delete this item?')\">Delete</a></span>\n";
						echo "			    		</div>\n";
						echo "		    		</td>\n";
						echo "		    		<td>\n";
						echo "		    			<strong>".stripslashes($template[$item->template]->name)."</strong>\n";
						echo "		    		</td>\n";
						echo "	    		</tr>";

		    		}
	    		?>
	    	</tbody>
	    </table>
	    <script type="text/javascript">
	    	var option = document.getElementById("filter");
	    	option.selectedIndex = 0;
	    	for(var i = 0; i < option.options.length; i++){
		    	if(option.options[i].value == "<?php echo $_POST['filter']; ?>"){
			    	option.selectedIndex = i;
			    	break;
		    	}
	    	}
	    </script>
    </div>
    <?php	
}
function lpcm_admin_nitem_content(){
    ?>
    <div class="wrap">
    	<div class="icon32" id="icon-edit"><br></div>
    	<h2>Add/Edit Item</h2>
    
    <?php
    if(!empty($_GET['updated'])){
    	?><div class="updated fade"><p>Complete.</p></div><?php
    }
    if(!empty($_GET['edit'])){
    	wp_register_script('jquery-validation-plugin', 'http://ajax.aspnetcdn.com/ajax/jquery.validate/1.11.1/jquery.validate.min.js');
		wp_enqueue_script('jquery-validation-plugin');
    	wp_enqueue_media();
    	global $wpdb;
	    $item = $wpdb->get_row("SELECT * FROM  `".$wpdb->prefix . "lpcm_items"."` WHERE slug = '".$_GET['edit']."'", ARRAY_A);
	    $title = $item['name'];
	    $item = array_map('stripslashes', $item);
	    ?>
	    <div style="text-align:right;"><small>Template: <?php echo $item['template']; ?></small></div>
    	<script type="text/javascript">
    		var form_clean;

			// serialize clean form
			jQuery(function() { 
				jQuery("#newItem").validate();
			    form_clean = jQuery("form").serialize();  
			});
			
			// compare clean and dirty form before leaving
			window.onbeforeunload = function (e) {
			    var form_dirty = jQuery("form").serialize();
			    if(form_clean != form_dirty) {
			        return 'The changes you made will be lost if you navigate away from this page.';
			    }
			};
    		function generateSlug(){
	    		document.getElementById("slug").value = document.getElementById("title").value.replace(/\s+/g, '-').toLowerCase();
    		}
    		
    		var file_frame;
			
			function openMedia(button){
				// If the media frame already exists, reopen it.
			    if ( file_frame ) {
			      file_frame.open();
			      return;
			    }
			 
			    // Create the media frame.
			    file_frame = wp.media.frames.file_frame = wp.media({
			      title: jQuery( this ).data( 'uploader_title' ),
			      button: {
			        text: jQuery( this ).data( 'uploader_button_text' ),
			      },
			      multiple: false  // Set to true to allow multiple files to be selected
			    });
			 
			    // When an image is selected, run a callback.
			    file_frame.on( 'select', function() {
			      // We set multiple to false so only get one image from the uploader
			      attachment = file_frame.state().get('selection').first().toJSON();
			 
			      // Do something with attachment.id and/or attachment.url here
			      document.getElementById("attributes"+button.id).value = attachment.url;
			    });
			 
			    // Finally, open the modal
			    file_frame.open();
			}
			
			function generateTitle(){
				document.getElementById("title").value = document.getElementById("title").value.replace(/[^a-zA-Z0-9., \"\']+/g, '');
			}
    	</script>
    	<form method="post" id="newItem" action="?page=nitem&update=<?php echo $item['slug']; ?>" onsubmit="window.onbeforeunload=null;">
    		<input type="hidden" name="itemID" value="<?php echo $item['id']; ?>" />
    		<h3>Information</h3>
	    	<table class="form-table">
	    		<tbody>
	    			<tr valign="top">
	    				<th scope="row">
	    					<label for="title">Title</label>
	    				</th>
	    				<td>
	    					<input type="text" name="title" id="title" value="<?php echo htmlspecialchars($item['name'], ENT_QUOTES); ?>" required/>
	    				</td>
	    			</tr>
	    			<tr valign="top">
	    				<th scope="row">
	    					<label for="slug">Slug</label>
	    				</th>
	    				<td>
	    					<input type="text" name="slug" id="slug" value="<?php echo $item['slug']; ?>" readonly="true" required/>
	    				</td>
	    			</tr>
	    		</tbody>
	    	</table>
	    	<h3>Attributes</h3>
	    	<table class="form-table">
	    		<tbody>
    						<?php
    							global $wpdb;
    							/*
$template = $wpdb->get_row("SELECT  `name`, `attributeNames` FROM  `wp_lpcm_templates` WHERE slug = '".$item['template']."'");
    							$attributes = split('~', $template->attributeNames);
    							$attrValues = split('~', $item['attributes']);
*/
								$attributes = $wpdb->get_results("SELECT  `".$wpdb->prefix . "lpcm_attributes"."`.`id`, `".$wpdb->prefix . "lpcm_attributes"."`.`name`, `".$wpdb->prefix . "lpcm_attributes"."`.`slug`, `".$wpdb->prefix . "lpcm_attributes"."`.`type`
FROM  `".$wpdb->prefix . "lpcm_attributes"."` ,  `".$wpdb->prefix . "lpcm_templates"."` 
WHERE  `".$wpdb->prefix . "lpcm_templates"."`.id =  '".$item['template']."' AND `".$wpdb->prefix . "lpcm_attributes"."`.`template` = `".$wpdb->prefix . "lpcm_templates"."`.`id`", ARRAY_A);
								$attributeValues = $wpdb->get_results("SELECT name, type, value FROM `".$wpdb->prefix . "lpcm_attributes"."` INNER JOIN `".$wpdb->prefix . "lpcm_attributeValues"."` ON `attribute` = `id` WHERE `item` = '".$item['id']."'", ARRAY_A);
								
    							for($index = 0; $index < sizeof($attributeValues); $index++){
	    							echo "<tr valign='top'><input type=\"hidden\" name=\"attributeID[]\" value=\"".$attributes[$index]['id']."\" />";
	    							echo "<th scope='row'><label for='attributes$index'>".stripslashes($attributes[$index]['name']).":</label></th><td><input type=\"text\" name=\"attributes[]\" id=\"attributes".$index."\" value=\"".htmlspecialchars(stripslashes($attributeValues[$index]['value']), ENT_QUOTES)."\" /></td></tr>";
	    							
	    						}
	    						for($index2 = $index; $index2 < sizeof($attributes); $index2++){
	    							echo "<tr valign='top'><input type=\"hidden\" name=\"attributeID[]\" value=\"".$attributes[$index2]['id']."\" />";
	    							echo "<th scope='row'><label for='attributes$index'>".stripslashes($attributes[$index2]['name']).":</label></th><td><input type=\"text\" name=\"attributes[]\" id=\"attributes".$index2."\" /></td></tr>";
    									    							
	    						}
    						?>	
	    		</tbody>
	    	</table>
	    	<input type="hidden" name="template" value="<?php echo $item['template']; ?>" />
	    	<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Save Item"></p>
	    </form>
	    
	    <?php
    }
    else if(!empty($_POST['Ntemplate'])){
    	wp_register_script('jquery-validation-plugin', 'http://ajax.aspnetcdn.com/ajax/jquery.validate/1.11.1/jquery.validate.min.js');
		wp_enqueue_script('jquery-validation-plugin');
    	?>
    	<div style="text-align:right;"><small>Template: <?php echo $_POST['Ntemplate']; ?></small></div>
    	<script type="text/javascript">
    		<?php
    			wp_enqueue_media();
    			global $wpdb;
    			$slugs = json_encode($wpdb->get_col("SELECT `slug` FROM  `".$wpdb->prefix . "lpcm_items"."`"));
    			echo "var slugs = ". $slugs . ";\n";
    		?>
    		var form_clean;

			// serialize clean form
			jQuery(function() { 
				jQuery("#newItem").validate();
			    form_clean = jQuery("form").serialize();  
			});
			
			// compare clean and dirty form before leaving
			window.onbeforeunload = function (e) {
			    var form_dirty = jQuery("form").serialize();
			    if(form_clean != form_dirty) {
			        return 'The changes you made will be lost if you navigate away from this page.';
			    }
			};
    		function generateSlug(){
	    		//document.getElementById("title").value = document.getElementById("title").value.replace(/[^a-zA-Z0-9., \"\']+/g, '');
	    		document.getElementById("slug").value = document.getElementById("title").value.replace(/\s+/g, '-').replace(/[^a-zA-Z0-9\-]+/g, '').toLowerCase();
	    		document.getElementById("slug").value = checkSlug(document.getElementById("slug").value);
    		}
    		
    		function checkSlug(slug){
	    		if(slugs.contains(slug)){
		    		return checkSlug(slug + "-2");
	    		}else{
		    		return slug;
	    		}
    		}
    		
    		Array.prototype.contains = function(obj) {
			    var i = this.length;
			    while (i--) {
			        if (this[i] === obj) {
			            return true;
			        }
			    }
			    return false;
			}
			
			
			
			var file_frame;
			
			function openMedia(button){
				// If the media frame already exists, reopen it.
			    if ( file_frame ) {
			      file_frame.open();
			      return;
			    }
			 
			    // Create the media frame.
			    file_frame = wp.media.frames.file_frame = wp.media({
			      title: jQuery( this ).data( 'uploader_title' ),
			      button: {
			        text: jQuery( this ).data( 'uploader_button_text' ),
			      },
			      multiple: false  // Set to true to allow multiple files to be selected
			    });
			 
			    // When an image is selected, run a callback.
			    file_frame.on( 'select', function() {
			      // We set multiple to false so only get one image from the uploader
			      attachment = file_frame.state().get('selection').first().toJSON();
			 
			      // Do something with attachment.id and/or attachment.url here
			      document.getElementById("attributes"+button.id).value = attachment.url;
			    });
			 
			    // Finally, open the modal
			    file_frame.open();
			}
			  
    	</script>
    	<style>
    	.editor{
    		display:inline-block;
    	}
    	</style>
    	<form method="post" action="?page=nitem" id="newItem" onsubmit="window.onbeforeunload=null;">
    		<h3>Information</h3>
	    	<table class="form-table">
	    		<tbody>
	    			<tr valign="top">
	    				<th scope="row">
	    					<label for="title">Title</label>
	    				</th>
	    				<td>
	    					<input type="text" name="title" id="title" onkeyup="generateSlug()" required/>
	    				</td>
	    			</tr>
	    			<tr valign="top">
	    				<th scope="row">
	    					<label for="slug">Slug</label>
	    				</th>
	    				<td>
	    					<input type="text" name="slug" id="slug" onkeyup="this.value = this.value.replace(/[^a-z0-9-]+/g, ''); this.value = checkSlug(this.value);" required/>
	    				</td>
	    			</tr>
	    		</tbody>
	    	</table>
	    	<h3>Attributes</h3>
	    	<table class="form-table">
	    		<tbody>
    						<?php
    							global $wpdb;
    							$attributes = $wpdb->get_results("SELECT  `".$wpdb->prefix . "lpcm_attributes"."`.`id`, `".$wpdb->prefix . "lpcm_attributes"."`.`name`, `".$wpdb->prefix . "lpcm_attributes"."`.`slug`, `".$wpdb->prefix . "lpcm_attributes"."`.`type`
FROM  `".$wpdb->prefix . "lpcm_attributes"."` ,  `".$wpdb->prefix . "lpcm_templates"."` 
WHERE  `".$wpdb->prefix . "lpcm_templates"."`.slug =  '".$_POST['Ntemplate']."' AND `".$wpdb->prefix . "lpcm_attributes"."`.`template` = `".$wpdb->prefix . "lpcm_templates"."`.`id`", ARRAY_A);
    							for($index = 0; $index < sizeof($attributes); $index++){
    								echo "<tr valign='top'><input type=\"hidden\" name=\"attributeID[]\" value=\"".$attributes[$index]['id']."\" />";
    								echo "<th scope='row'><label for='attributes$index'>".stripslashes($attributes[$index]['name']).":</label></th><td><input type=\"text\" name=\"attributes[]\" id=\"attributes".$index."\" /></td></tr>";
    								
	    						}
	    						
    						?>
	    		</tbody>
	    	</table>
	    	<input type="hidden" name="template" value="<?php echo $_POST['Ntemplate']; ?>" />
	    	<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Save Item"></p>
	    </form>
    	
    	<?php
	    
    }
    else if(!empty($_POST['template'])){
	    global $wpdb;
    	/*
$attr = $_POST['attributes'];
    	$attrStr = implode('~',$attr);
    	$rows_affected = $wpdb->insert('wp_lpcm_items', array('name' => $_POST['title'], 'slug' => $_POST['slug'], 'template' => $_POST['template'], 'attributes' => $attrStr));
*/
		if(!empty($_GET['update'])){
			$wpdb->show_errors();
			$wpdb->update($wpdb->prefix . "lpcm_items", array('name' => $_POST['title']), array('slug' => $_POST['slug']));
			$itemID = $_POST['itemID'];
			$attributeValues = $wpdb->get_results("SELECT id, name, type, value FROM `".$wpdb->prefix . "lpcm_attributes"."` INNER JOIN `".$wpdb->prefix . "lpcm_attributeValues"."` ON `attribute` = `id` WHERE `item` = '".$itemID."'", ARRAY_K);
			$attributeIDs = $wpdb->get_col("SELECT id, name, type, value FROM `".$wpdb->prefix . "lpcm_attributes"."` INNER JOIN `".$wpdb->prefix . "lpcm_attributeValues"."` ON `attribute` = `id` WHERE `item` = '".$itemID."'", 0);
			for($index = 0; $index < sizeof($_POST['attributeID']); $index++){
				if(in_array($_POST['attributeID'][$index], $attributeIDs)){
					$attrID = $_POST['attributeID'][$index];
					if($attributeValues['$attrID']['value'] != $_POST['attributes'][$index]){
						$wpdb->update($wpdb->prefix . "lpcm_attributeValues", array('value' => $_POST['attributes'][$index]), array('attribute' => $attrID, 'item' => $itemID));
					}
				}else{
					$wpdb->insert($wpdb->prefix . "lpcm_attributeValues", array('attribute' => $_POST['attributeID'][$index], 'item' => $itemID, 'value' => $_POST['attributes'][$index]));
				}
			}
		}else{
			$templateID = $wpdb->get_var("SELECT id FROM ".$wpdb->prefix . "lpcm_templates"." WHERE slug = '".$_POST['template']."'");
			$wpdb->insert($wpdb->prefix . "lpcm_items", array('name' => $_POST['title'], 'slug' => $_POST['slug'], 'template' => $templateID));
			$itemID = $wpdb->insert_id;
			$values = array();
			$place_holders = array();
			$sql = "INSERT INTO ".$wpdb->prefix . "lpcm_attributeValues"." (attribute, item, value) VALUES ";
			for($index = 0; $index < sizeof($_POST['attributeID']); $index++){
				array_push($values, $_POST['attributeID'][$index], $itemID, $_POST['attributes'][$index]);
				//$place_holders[] = "('".$_POST['attributeID'][$index]."', '$itemID', '".$_POST['attributes'][$index]."')";
				$place_holders[] = "('%d', '%d', '%s')";
			}
			$sql .= implode(', ', $place_holders);

/*
			echo $sql;
			echo $wpdb->prepare("$sql ", $values);
*/

			$wpdb->query($wpdb->prepare("$sql ", $values));
		}
    	?>
    	<script type="text/javascript">
    		function post_to_url(path, params, method) {
    		    method = method || "post"; // Set method to post by default if not specified.
    		
    		    // The rest of this code assumes you are not using a library.
    		    // It can be made less wordy if you use one.
    		    var form = document.createElement("form");
    		    form.setAttribute("method", method);
    		    form.setAttribute("action", path);
    		
    		    for(var key in params) {
    		        if(params.hasOwnProperty(key)) {
    		            var hiddenField = document.createElement("input");
    		            hiddenField.setAttribute("type", "hidden");
    		            hiddenField.setAttribute("name", key);
    		            hiddenField.setAttribute("value", params[key]);
    		
    		            form.appendChild(hiddenField);
    		         }
    		    }
    		
    		    document.body.appendChild(form);
    		    form.submit();
    		}
    		<?php
    			echo "window.location = \"?page=nitem&updated=1&edit=$_POST[slug]\";";
    		?>
    		
    	</script>
    	<?php
    }
    else{
	    ?>
	    <form method="post" action="?page=nitem">
	    	<table class="form-table">
	    		<tbody>
	    			<tr valign="top">
	    				<th scope="row">
	    					<label for="template">Choose Template</label>
	    				</th>
	    				<td>
	    					<select name="Ntemplate" id="Ntemplate">
	    						<?php
	    							global $wpdb;
	    							$templates = $wpdb->get_results("SELECT  `name` ,  `slug` FROM  `".$wpdb->prefix . "lpcm_templates"."`");
	    							foreach($templates as $template){
	    								echo "<option value=\"".$template->slug."\">".htmlspecialchars(stripslashes($template->name), ENT_QUOTES)."</option>\n";
	    							}
	    						?>
	    					</select>
	    				</td>
	    			</tr>
	    		</tbody>
	    	</table>
	    	<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Submit"></p>
	    </form>
	    <?php
    }
    ?></div><?php
}
function lpcm_admin_templates_content(){
	wp_enqueue_script('jquery-ui-sortable');
    ?>
    <div class="wrap">
    	<div class="icon32" id="icon-edit"><br></div>
    	<?php if(!isset($_GET['sort'])){ ?>
	    <h2>Templates
	    <a href="?page=ntemplate" class="add-new-h2">Add New</a></h2>
	    <?php
	    if(!empty($_GET['delete'])){
		    global $wpdb;
		    $wpdb->delete($wpdb->prefix . "lpcm_templates", array('slug' => $_GET['delete']));
		    ?>
		    <div class="updated fade"><p>Template removed.</p></div>
		    <?php
	    }
	    ?>
	    <br>
	    <table class="widefat page fixed">
	    	<thead>
	    		<tr>
		    		<th id="cb" class="manage-column column-cb check-column" style="" scope="col">
			    		<input type="checkbox"/>
			    	</th>
		    		<th class="manage-column">Name</th>
		    		<th class="manage-column">Shortcode</th>
	    		</tr>
	    	</thead>
	    	<tfoot>
	    		<tr>
		    		<th id="cb" class="manage-column column-cb check-column" style="" scope="col">
			    		<input type="checkbox"/>
			    	</th>
		    		<th class="manage-column">Name</th>
		    		<th class="manage-column">Shortcode</th>
	    		</tr>
	    	</tfoot>
	    	<tbody>
	    		<?php
	    		global $wpdb;
	    		$templates = $wpdb->get_results("SELECT  `name` ,  `slug` FROM  `".$wpdb->prefix . "lpcm_templates"."`");
	    		foreach ($templates as $template){
	    			echo "	    			<tr>\n";
		    		echo "			    		<td>\n";
			    	echo "			    			<input type=\"checkbox\" />\n";
			    	echo "			    		</td>\n";
			    	echo "			    		<td>\n";
			    	echo "			    			<strong>".stripslashes($template->name)."</strong>\n";
			    	echo "			    			<div class=\"row-actions-visible\">\n";
			    	echo "				    			<span class=\"edit\"><a href=\"?page=ntemplate&edit=".$template->slug."\">Edit</a> | </span>\n";
			    	echo "				    			<span class=\"sort\"><a href=\"?page=templates&sort=".$template->slug."\">Sort</a> | </span>\n";
			    	echo "				    			<span class=\"delete\"><a href=\"?page=templates&delete=".$template->slug."\" onclick=\"return confirm('Are you sure you want to delete this template? Items will also be deleted.')\">Delete</a></span>\n";
			    	echo "				    		</div>\n";
			    	echo "			    		</td>\n";
			    	echo "			    		<td>\n";
			    	echo "			    			<input type='text' readonly='true' value=\"[content-manager id=".$template->slug."]\" />\n";
			    	echo "			    		</td>\n";
			    	echo "		    		</tr>";
	    		}
	    		?>
	    	</tbody>
	    </table>
	    <?php }else{  
		    
	    ?>
	    	<script type="text/javascript">
				jQuery(document).ready( function($) {
var widths = [];
				    var fixHelper = function(e, ui) {
						ui.children().each(function() {
jQuery(this).css("width", jQuery(this).width() + "px");
});
jQuery("#sort thead th").each(function() { widths.push(jQuery(this).width()); });
jQuery("#sort thead th").each(function(i,e) { jQuery(e).css("width", widths[i] + "px"); });

						return ui;
					};
					 
					jQuery("tbody").sortable({
						helper: fixHelper
					}).disableSelection();
				});
	    	</script>
	    	<h2>Item Sorting</h2>
	    	<?php
	    	global $wpdb;
		    if(isset($_POST['itemOrder'])){
			    for($index = 0; $index < sizeof($_POST['itemOrder']); $index++){
			    	$wpdb->update($wpdb->prefix . "lpcm_items", array('sort' => $index), array('slug' => $_POST['itemOrder'][$index]));
			    }
			    echo "<div class='updated fade'><p>Completed.</p></div>";
		    }
		    ?>
	    	<br>
	    	<form action="?page=templates&sort=<?php echo $_GET['sort']; ?>" method="post">
		    <table id="sort" class="widefat page fixed">
		    	<thead>
		    		<tr>
			    		<th class="manage-column">Title</th>
			    		<th class="manage-column">Slug</th>
		    		</tr>
		    	</thead>
		    	<tfoot>
		    		<tr>
			    		<th class="manage-column">Title</th>
			    		<th class="manage-column">Slug</th>
		    		</tr>
		    	</tfoot>
		    	<tbody>
		    		<?php
			    		
			    		$tempID = $wpdb->get_var("SELECT  `id` FROM  `".$wpdb->prefix . "lpcm_templates"."` WHERE `slug` = '".$_GET['sort']."'");
			    		$items = $wpdb->get_results("SELECT  `name` ,  `slug`, `sort` FROM  `".$wpdb->prefix . "lpcm_items"."` WHERE `template` = '".$tempID."' ORDER BY `sort`");
			    		foreach ($items as $item){
			    			echo "				<tr>\n";
							echo "		    		<td>\n";
							echo "		    			<strong>".$item->name."</strong>\n";
							echo "		    			<div class=\"row-actions-visible\">&nbsp;\n";
							echo "			    		</div>\n";
							echo "		    		</td>\n";
							echo "		    		<td>\n";
							echo "		    			<strong>".$item->slug."</strong>\n";
							echo "		    		</td>\n";
							echo "					<input type='hidden' name='itemOrder[]' value='$item->slug' />";
							echo "	    		</tr>";
	
			    		}
		    		?>
		    	</tbody>
		    </table>
		    <p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Submit"></p>
	    	</form>
	    <?php } ?>
    </div>
    <?php	
}
function lpcm_admin_ntemplate_content(){
    ?>
    <div class="wrap">
    	<div class="icon32" id="icon-edit"><br></div>
    	<h2>Add/Edit Template</h2>
    	<br>
    <?php
    if(!empty($_GET['updated'])){
    	?><div class="updated fade"><p>Complete.</p></div><?php
    }
    
    if(!empty($_POST)){
    	global $wpdb;
    	if(!empty($_GET['update'])){
    		if(!empty($_POST['itemPages'])){
				$itempages = 1;
			}else{
				$itempages = 0;
			}
			$rows_affected = $wpdb->update($wpdb->prefix . "lpcm_templates", array('name' => $_POST['name'], 'pre_loop' => $_POST['preloop'], 'item_loop' => $_POST['loop'], 'post_loop' => $_POST['postloop'], 'item_html' => $_POST['itemhtml'], 'item_page_on' => $itempages), array('slug' => $_POST['slug']));
			
			$attributes = $wpdb->get_results("SELECT * FROM  `".$wpdb->prefix . "lpcm_attributes"."` WHERE template = '$_POST[tempID]'", ARRAY_A);
			
			for($index = 0; $index < sizeof($attributes); $index++){
				$preSlug = $attributes[$index]['slug'];
				$preName = $attributes[$index]['name'];
				$preType = $attributes[$index]['type'];
				$preID = $attributes[$index]['id'];
				$postIndex = array_search("$preID", $_POST['attributeIDs']);
				if($postIndex === FALSE){
					$wpdb->delete($wpdb->prefix . "lpcm_attributes", array('id' => $preID));
				}
				else if($_POST['attributeValues'][$postIndex] != $preSlug || $_POST['attributeNames'][$postIndex] != $preSlug || $_POST['attributeTypes'][$postIndex] != $preType){
					$wpdb->update($wpdb->prefix . "lpcm_attributes", array('name' => $_POST['attributeNames'][$postIndex], 'slug' => $_POST['attributeValues'][$postIndex], 'type' => $_POST['attributeTypes'][$postIndex]), array('id' => $preID));
				}
			}
			
			for($index = 0; $index < sizeof($_POST['attributeIDs']); $index++){
				if($_POST['attributeIDs'][$index] == "n"){
					$wpdb->insert($wpdb->prefix . "lpcm_attributes", array('name' => $_POST['attributeNames'][$index], 'slug' => $_POST['attributeValues'][$index], 'template' => $_POST['tempID'], 'type' => $_POST['attributeTypes'][$index]));
				}
			}
			
    	}else{
	    	if(!empty($_POST['itemPages'])){
				$itempages = 1;
			}else{
				$itempages = 0;
			}
			$rows_affected = $wpdb->insert($wpdb->prefix . "lpcm_templates", array('name' => $_POST['name'], 'slug' => $_POST['slug'], 'pre_loop' => $_POST['preloop'], 'item_loop' => $_POST['loop'], 'post_loop' => $_POST['postloop'], 'item_html' => $_POST['itemhtml'], 'item_page_on' => $itempages));
			$tempID = $wpdb->insert_id;
			
/*
			$sql = "INSERT INTO ".$wpdb->prefix . "lpcm_attributes"." (name, slug, template, type) VALUES ";
			for($index = 0; $index < sizeof($_POST['attributeValues']); $index++){
				if($index < sizeof($_POST['attributeValues'])-1){
					$sql .= "('".$_POST['attributeNames'][$index]."','".$_POST['attributeValues'][$index]."','".$tempID."','".$_POST['attributeTypes'][$index]."'), ";
				}else{
					$sql .= "('".$_POST['attributeNames'][$index]."','".$_POST['attributeValues'][$index]."','".$tempID."','".$_POST['attributeTypes'][$index]."');";
				}
			}
			$wpdb->query($sql);
*/
			
			$values = array();
			$place_holders = array();
			$sql = "INSERT INTO ".$wpdb->prefix . "lpcm_attributes"." (name, slug, template, type) VALUES ";
			for($index = 0; $index < sizeof($_POST['attributeValues']); $index++){
				array_push($values, $_POST['attributeNames'][$index], $_POST['attributeValues'][$index], $tempID, $_POST['attributeTypes'][$index]);
				//$place_holders[] = "('".$_POST['attributeID'][$index]."', '$itemID', '".$_POST['attributes'][$index]."')";
				//$place_holders[] = "('%d', '%d', '%s')";
				$place_holders[] = "('%s', '%s', '%d', '%d')";
			}
			$sql .= implode(', ', $place_holders);

/*
			echo $sql;
			echo $wpdb->prepare("$sql ", $values);
*/

			$wpdb->query($wpdb->prepare("$sql ", $values));
    	}
		
    	?>
    	<script type="text/javascript">
    		window.location = "?page=ntemplate&updated=1&edit=<?php echo $_POST['slug'] ?>";
    	</script>
    	<?php
    }
    else if(!empty($_GET['edit'])){
    
		wp_enqueue_script('jQuery');
	    global $wpdb;
	    $template = $wpdb->get_row("SELECT * FROM  `".$wpdb->prefix . "lpcm_templates"."` WHERE slug = '$_GET[edit]'", ARRAY_A);
	    $template = array_map('stripslashes', $template);
	    $attributes = $wpdb->get_results("SELECT * FROM  `".$wpdb->prefix . "lpcm_attributes"."` WHERE template = '$template[id]'", ARRAY_A);
	    ?>
	    <script type="text/javascript">
	    	var form_clean;

			// serialize clean form
			jQuery(function() { 
			    form_clean = jQuery("form").serialize();  
			});
			
			// compare clean and dirty form before leaving
			window.onbeforeunload = function (e) {
			    var form_dirty = jQuery("form").serialize();
			    if(form_clean != form_dirty) {
			        return 'The changes you made will be lost if you navigate away from this page.';
			    }
			};
	    	Element.prototype.remove = function() {
			    this.parentElement.removeChild(this);
			}
			NodeList.prototype.remove = HTMLCollection.prototype.remove = function() {
			    for(var i = 0, len = this.length; i < len; i++) {
			        if(this[i] && this[i].parentElement) {
			            this[i].parentElement.removeChild(this[i]);
			        }
			    }
			}
	    	function showItemBox(){
	    		if(document.getElementById('itemPages').checked){
		    		document.getElementById('itemhtmlRow').style.display = 'table-row';
		    		document.getElementById('checkBoxText').style.display = 'block';
	    		}else{
		    		document.getElementById('itemhtmlRow').style.display = 'none';
		    		document.getElementById('checkBoxText').style.display = 'none';
		    		document.getElementById('itemhtml').value = '';
	    		}
    		}
    		var attributes = <?php echo sizeof($attributes); ?>;
    		
    		function addAttributeBox(){
	    		var element = document.createElement("input");
	    		element.setAttribute("type", "text");
	    		element.setAttribute("id", "attributeValues"+attributes.toString());
	    		element.setAttribute("name", "attributeValues[]");
	    		element.setAttribute("readonly", "true");
	    		
	    		var nameElement = document.createElement("input");
	    		nameElement.setAttribute("type", "text");
	    		nameElement.setAttribute("placeholder", "Attribute title");
	    		nameElement.setAttribute("id", "attributeNames"+attributes.toString());
	    		nameElement.setAttribute("name", "attributeNames[]");
	    		nameElement.setAttribute("onkeyup", "generateAttribute("+attributes.toString()+")");
	    		
	    		var dropDown = document.createElement("input");
	    		dropDown.setAttribute("type", "hidden");
	    		dropDown.setAttribute("id", "attributeTypes"+attributes.toString());
	    		dropDown.setAttribute("name", "attributeTypes[]");
	    		dropDown.setAttribute("value", "0");
	    		
	    		var removeBut = document.createElement("a");
	    		removeBut.setAttribute("href", "#");
	    		removeBut.setAttribute("onclick", "this.parentElement.parentElement.removeChild(this.parentElement);");
	    		removeBut.innerHTML = "Remove";
	    		
	    		var IDElement = document.createElement("input");
	    		IDElement.setAttribute("type", "hidden");
	    		IDElement.setAttribute("id", "attributeIDs"+attributes.toString());
	    		IDElement.setAttribute("name", "attributeIDs[]");
	    		IDElement.setAttribute("value", "n");
	    		
	    		var spacer = document.createElement("div");
	    		spacer.setAttribute("style", "width:10px;display:inline-block;");
	    		
	    		var enter = document.createElement("br");
	    		
	    		var holder = document.getElementById("attributeBoxes");
	    		
	    		var place = document.createElement("span");
	    		place.setAttribute("id", "holder");
	    		place.appendChild(nameElement);
	    		place.appendChild(element);
	    		place.appendChild(dropDown);
	    		place.appendChild(spacer);
	    		place.appendChild(removeBut);
	    		place.appendChild(IDElement);
	    		place.appendChild(enter);
	    		
	    		holder.appendChild(place);
	    		
	    		attributes++;
    		}
    		function generateSlug(){
	    		document.getElementById("name").value = document.getElementById("name").value.replace(/[^a-zA-Z0-9 ]+/g, '');
    		}
    		function generateAttribute(attribute){
	    		document.getElementsByName('attributeValues[]')[attribute].value = "{" + document.getElementsByName('attributeNames[]')[attribute].value.replace(/[^a-zA-Z0-9 ]+/g, '').replace(/\s+/g, '-').toLowerCase() + "}";
	    		/* document.getElementsByName('attributeNames[]')[attribute].value = document.getElementsByName('attributeNames[]')[attribute].value; */
	    		document.getElementById('attributeValues'+attribute.toString()).value = checkAttr(document.getElementById('attributeValues'+attribute.toString()));
    		}
    		function checkForValue(obj, obj2) {
			    var i = obj2.length;
			    while (i--) {
			        if (obj2[i].value === obj.value && obj2[i] != obj) {
			            return true;
			        }
			    }
			    return false;
			}
			function checkAttr(attr){
				if(attr.value.length > 1){
					var attrs = document.getElementsByName('attributeValues[]');
					if(checkForValue(attr, attrs)){
						attr.value += "-2";
			    		return checkAttr(attr);
		    		}else{
			    		return attr.value;
		    		}
		    	}else{
			    	return attr.value;
		    	}
			}
    		
	    </script>
	    <div style="text-align:right;"><input type="text" readonly="true" value="[content-manager id=<?php echo $template['slug']; ?>]"></div>
	    <form id="form" action="?page=ntemplate&update=<?php echo $template['slug']; ?>" method="POST" onsubmit="window.onbeforeunload=null;">
	    	<input type="hidden" name="tempID" id="tempID" value="<?php echo $template['id']; ?>" />
	    		<table class="form-table">
	    			<tbody>
	    				<tr valign="top">
	    					<th scope="row">
				    			<label for="name">Template Name</label>
	    					</th>
	    					<td>
				    			<input type="text" id="name" onkeyup="this.value = this.value.replace(/[^a-zA-Z0-9 ]+/g, '')" autocomplete="off" value="<?php echo htmlspecialchars($template['name'], ENT_QUOTES); ?>" name="name" />
	    					</td>
	    				</tr>
				    	<tr valign="top">
	    					<th scope="row">
				    			<label for="slug">Template Slug</label>
	    					</th>
	    					<td>
				    			<input type="text" id="slug" value="<?php echo $template['slug']; ?>" name="slug" readonly="true" />
	    					</td>
	    				</tr>
				    	<tr valign="top">
	    					<th scope="row">
				    			<label for="attributes">Attributes</label>
	    					</th>
	    					<td>
				    			<!-- <span id="attributeBoxes"><input type="text" onchange="generateAttribute(0)" id="attributeNames[]" name="attributeNames[]" /><input type="text" id="attributeValues[]" name="attributeValues[]" readonly="true" value=""/></span> -->
				    			
				    			<span id="attributeBoxes">
					    			<?php
					    				for($index = 0; $index < sizeof($attributes); $index++){
						    				echo "<span id=\"holder\"><input type=\"text\" placeholder=\"Attribute title\" onkeyup=\"generateAttribute(".$index.")\" value=\"".htmlspecialchars(stripslashes($attributes[$index]['name']), ENT_QUOTES)."\" id=\"attributeNames".$index."\" name=\"attributeNames[]\" /><input type=\"text\" id=\"attributeValues".$index."\" name=\"attributeValues[]\" readonly=\"true\" value=\"".$attributes[$index]['slug']."\" /><input type=\"hidden\" id=\"attributeTypes0\" name=\"attributeTypes[]\" value=\"".$attributes[$index]['type']."\" /><div style=\"width:10px;display:inline-block;\"></div><a href=\"#\" onclick=\"this.parentElement.parentElement.removeChild(this.parentElement);\">Remove</a><input type=\"hidden\" id=\"attributeIDs".$index."\" name=\"attributeIDs[]\" value=\"".$attributes[$index]['id']."\" /><br></span>";
					    				}
					    			?>
				    			</span>
				    			<a href="#" onclick="addAttributeBox()">Add Additional Attribute</a>
	    					</td>
	    				</tr>
	    				<tr valign="top">
	    					<th scope="row">
				    			<label for="preloop">Main Page Pre-Loop HTML</label>
	    					</th>
	    					<td>
				    			<?php
				    				$args = array(
				    					'textarea_name' => 'preloop',
				    					'textarea_rows' => 5
				    				);
				    				wp_editor($template['pre_loop'], 'preloop', $args);
				    			?>
	    					</td>
	    				</tr>
	    				<tr valign="top">
	    					<th scope="row">
				    			<label for="loop">Main Page Item HTML<br><small>(looped per item)</small></label>
	    					</th>
	    					<td>
				    			<?php
				    				$args = array(
				    					'textarea_name' => 'loop',
				    					'textarea_rows' => 5
				    				);
				    				wp_editor($template['item_loop'], 'loop', $args);
				    			?>
	    					</td>
	    				</tr>
	    				<tr valign="top">
	    					<th scope="row">
				    			<label for="postloop">Main Page Post-Loop HTML</label>
	    					</th>
	    					<td>
				    			<?php
				    				$args = array(
				    					'textarea_name' => 'postloop',
				    					'textarea_rows' => 5
				    				);
				    				wp_editor($template['post_loop'], 'postloop', $args);
				    			?>
	    					</td>
	    				</tr>
	    				<tr valign="top">
	    					<th scope="row">
				    			<label for="itemPages">Item Pages</label>
	    					</th>
	    					<td>
				    			<input type="checkbox" id="itemPages" name="itemPages" onclick="showItemBox()" /> Create Item Pages<br><div id="checkBoxText" style="display:none;"><small>Use $item-url for the item's URL</small></div>
	    					</td>
	    				</tr>
	    				<tr valign="top" id="itemhtmlRow" style="display:none;">
	    					<th scope="row">
				    			<label for="itemhtml">Item Page HTML</label>
	    					</th>
	    					<td>
				    			<?php
				    				$args = array(
				    					'textarea_name' => 'itemhtml',
				    					'textarea_rows' => 5
				    				);
				    				wp_editor($template['item_html'], 'itemhtml', $args);
				    			?>
	    					</td>
	    				</tr>
	    			</tbody>
	    		</table>
	    		<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Save Template"></p>
	    	</form>
	    	<script type="text/javascript">
		    	if(<?php echo $template['item_page_on']; ?> == 1){
			    	document.getElementById('itemPages').checked = true;
			    	showItemBox();
		    	}else{
			    	document.getElementById('itemPages').checked = false;
			    	showItemBox();
		    	}
		    	
	    	</script>
	    <?php
    }
    else{
	    ?>
	    	<script type="text/javascript">
		    	<?php
		    		wp_enqueue_script('jQuery');
	    			global $wpdb;
	    			$slugs = json_encode($wpdb->get_col("SELECT slug FROM `".$wpdb->prefix . "lpcm_templates"."`"));
	    			echo "var slugs = ". $slugs . ";\n";
	    		?>
		    	
		    	var form_clean;

				// serialize clean form
				jQuery(function() { 
				    form_clean = jQuery("form").serialize();  
				});
				
				// compare clean and dirty form before leaving
				window.onbeforeunload = function (e) {
				    var form_dirty = jQuery("form").serialize();
				    if(form_clean != form_dirty) {
				        return 'The changes you made will be lost if you navigate away from this page.';
				    }
				};
	    		Element.prototype.remove = function() {
				    this.parentElement.removeChild(this);
				}
				NodeList.prototype.remove = HTMLCollection.prototype.remove = function() {
				    for(var i = 0, len = this.length; i < len; i++) {
				        if(this[i] && this[i].parentElement) {
				            this[i].parentElement.removeChild(this[i]);
				        }
				    }
				}
	    		function showItemBox(){
		    		if(document.getElementById('itemPages').checked){
			    		document.getElementById('itemhtmlRow').style.display = 'table-row';
			    		document.getElementById('checkBoxText').style.display = 'block';
		    		}else{
			    		document.getElementById('itemhtmlRow').style.display = 'none';
			    		document.getElementById('checkBoxText').style.display = 'none';
			    		document.getElementById('itemhtmlRow').value = '';
		    		}
	    		}
	    		var attributes = 1;
	    		function addAttributeBox(){
		    		var element = document.createElement("input");
		    		element.setAttribute("type", "text");
		    		element.setAttribute("id", "attributeValues"+attributes.toString());
		    		element.setAttribute("name", "attributeValues[]");
		    		element.setAttribute("readonly", "true");
		    		
		    		var nameElement = document.createElement("input");
		    		nameElement.setAttribute("type", "text");
		    		nameElement.setAttribute("placeholder", "Attribute title");
		    		nameElement.setAttribute("id", "attributeNames"+attributes.toString());
		    		nameElement.setAttribute("name", "attributeNames[]");
		    		nameElement.setAttribute("onkeyup", "generateAttribute("+attributes.toString()+")");
		    		
		    		var dropDown = document.createElement("input");
		    		dropDown.setAttribute("type", "hidden");
		    		dropDown.setAttribute("id", "attributeTypes"+attributes.toString());
		    		dropDown.setAttribute("name", "attributeTypes[]");
		    		dropDown.setAttribute("value", "0");
		    		
		    		var removeBut = document.createElement("a");
		    		removeBut.setAttribute("href", "#");
		    		removeBut.setAttribute("onclick", "this.parentElement.parentElement.removeChild(this.parentElement);");
		    		removeBut.innerHTML = "Remove";
		    		
		    		var spacer = document.createElement("div");
		    		spacer.setAttribute("style", "width:10px;display:inline-block;");
		    		
		    		var enter = document.createElement("br");
		    		
		    		var holder = document.getElementById("attributeBoxes");
		    		
		    		var place = document.createElement("span");
		    		place.setAttribute("id", "holder");
		    		place.appendChild(nameElement);
		    		place.appendChild(element);
		    		place.appendChild(dropDown);
		    		place.appendChild(spacer);
		    		place.appendChild(removeBut);
		    		place.appendChild(enter);
		    		
		    		holder.appendChild(place);
		    		
		    		attributes++;
	    		}
	    		function generateSlug(){
		    		document.getElementById("slug").value = document.getElementById("name").value.replace(/[^a-zA-Z0-9 ]+/g, '').replace(/\s+/g, '-').toLowerCase();
		    		/* document.getElementById("name").value = document.getElementById("name").value.replace(/[^a-zA-Z0-9 ]+/g, ''); */
		    		document.getElementById("slug").value = checkSlug(document.getElementById("slug").value);
	    		}
	    		function generateAttribute(attribute){
		    		document.getElementById('attributeValues'+attribute.toString()).value = "{" + document.getElementById('attributeNames'+attribute.toString()).value.replace(/[^a-zA-Z0-9 ]+/g, '').replace(/\s+/g, '-').toLowerCase() + "}";
		    		/* document.getElementById('attributeNames'+attribute.toString()).value = document.getElementById('attributeNames'+attribute.toString()).value.replace(/[^a-zA-Z0-9 ]+/g, ''); */
		    		document.getElementById('attributeValues'+attribute.toString()).value = checkAttr(document.getElementById('attributeValues'+attribute.toString()));
	    		}
	    		function removeAttribute(attribute){
	    			alert("test");
		    		document.getElementById('attributeValues'+attribute.toString()).remove();
		    		document.getElementById('attributeNames'+attribute.toString()).remove();
		    		document.getElementById('attributeTypes'+attribute.toString()).remove();
	    		}
	    		function checkSlug(slug){
		    		if(slugs.contains(slug)){
			    		return checkSlug(slug + "-2");
		    		}else{
			    		return slug;
		    		}
	    		}
	    		Array.prototype.contains = function(obj) {
				    var i = this.length;
				    while (i--) {
				        if (this[i] === obj) {
				            return true;
				        }
				    }
				    return false;
				}
				function checkForValue(obj, obj2) {
				    var i = obj2.length;
				    while (i--) {
				        if (obj2[i].value === obj.value && obj2[i] != obj) {
				            return true;
				        }
				    }
				    return false;
				}
				function checkAttr(attr){
					if(attr.value.length > 1){
						var attrs = document.getElementsByName('attributeValues[]');
						if(checkForValue(attr, attrs)){
							attr.value += "-2";
				    		return checkAttr(attr);
			    		}else{
				    		return attr.value;
			    		}
			    	}else{
				    	return attr.value;
			    	}
				}
	    	</script>
	    	<form id="form" action="?page=ntemplate" method="POST" onsubmit="window.onbeforeunload=null;">
	    		<table class="form-table">
	    			<tbody>
	    				<tr valign="top">
	    					<th scope="row">
				    			<label for="name">Template Name</label>
	    					</th>
	    					<td>
				    			<input type="text" id="name" autocomplete="off" onkeyup="generateSlug()" name="name" />
	    					</td>
	    				</tr>
				    	<tr valign="top">
	    					<th scope="row">
				    			<label for="slug">Template Slug</label>
	    					</th>
	    					<td>
				    			<input type="text" id="slug" name="slug" onkeyup="this.value = this.value.replace(/[^a-z0-9-]+/g, ''); this.value = checkSlug(this.value);" />
	    					</td>
	    				</tr>
				    	<tr valign="top">
	    					<th scope="row">
				    			<label for="attributes">Attributes</label>
	    					</th>
	    					<td>
				    			<!--
<input type="hidden" name="attributeValue" id="attributeValue" />
				    			<input type="hidden" name="attributeNameValue" id="attributeNameValue" />
-->
				    			<span id="attributeBoxes"><span id="holder"><input type="text" placeholder="Attribute title" onkeyup="generateAttribute(0);" id="attributeNames0" name="attributeNames[]" /><input type="text" id="attributeValues0" name="attributeValues[]" readonly="true" value=""/><input type="hidden" id="attributeTypes0" name="attributeTypes[]" value="0" /><div style="width:10px;display:inline-block;"></div><a href="#" onclick="this.parentElement.parentElement.removeChild(this.parentElement);">Remove</a><br></span></span>
				    			<a href="#" onclick="addAttributeBox()">Add Additional Attribute</a>
	    					</td>
	    				</tr>
	    				<tr valign="top">
	    					<th scope="row">
				    			<label for="preloop">Main Page Pre-Loop HTML</label>
	    					</th>
	    					<td>
				    			<?php
				    				$args = array(
				    					'textarea_name' => 'preloop',
				    					'textarea_rows' => 5
				    				);
				    				wp_editor('', 'preloop', $args);
				    			?>
	    					</td>
	    				</tr>
	    				<tr valign="top">
	    					<th scope="row">
				    			<label for="loop">Main Page Item HTML<br><small>(looped per item)</small></label>
	    					</th>
	    					<td>
				    			<?php
				    				$args = array(
				    					'textarea_name' => 'loop',
				    					'textarea_rows' => 5
				    				);
				    				wp_editor('', 'loop', $args);
				    			?>
	    					</td>
	    				</tr>
	    				<tr valign="top">
	    					<th scope="row">
				    			<label for="postloop">Main Page Post-Loop HTML</label>
	    					</th>
	    					<td>
				    			<?php
				    				$args = array(
				    					'textarea_name' => 'postloop',
				    					'textarea_rows' => 5
				    				);
				    				wp_editor('', 'postloop', $args);
				    			?>
	    					</td>
	    				</tr>
	    				<tr valign="top">
	    					<th scope="row">
				    			<label for="itemPages">Item Pages</label>
	    					</th>
	    					<td>
				    			<input type="checkbox" value="1" id="itemPages" name="itemPages" onclick="showItemBox()" /> Create Item Pages<br><div id="checkBoxText" style="display:none;"><small>Use $item-url for the item's URL</small></div>
	    					</td>
	    				</tr>
	    				<tr valign="top" id="itemhtmlRow" style="display:none;">
	    					<th scope="row">
				    			<label for="itemhtml">Item Page HTML</label>
	    					</th>
	    					<td>
				    			<?php
				    				$args = array(
				    					'textarea_name' => 'itemhtml',
				    					'textarea_rows' => 5
				    				);
				    				wp_editor('', 'itemhtml', $args);
				    			?>
	    					</td>
	    				</tr>
	    			</tbody>
	    		</table>
	    		<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Save Template"></p>
	    	</form>
	    <?php
    }
    ?></div><?php
}

function lpcm_admin_licensing_content(){
	?>
	<div class="wrap">
		<h2><?php _e('Upgrade'); ?></h2>
		<div style="width:640px;">
			<p><img alt="" src="<?php echo plugins_url( 'lpcmhero.jpg' , __FILE__ ); ?>" /></p>
	
			<p>Want to get more out of LP Content Manager? The premium version of the plugin offers a huge amount of additional functionality and priority support for your website.</p>
			<ul>
			<li>- Item Sorting</li>
			<li>- Choose attribute types (upload media, rich text, plaintext)</li>
			<li>- Mass Adding</li>
			<li>- Attribute placeholders</li>
			<li>- Priority email support</li>
			</ul>
			<p>The personal license, which covers up to 2 websites, is just $20/year. For developers, we offer a developer license for $200/year, which covers unlimited sites.</p>
			
			<p><a href="http://luminaryplugins.com/wordpress-plugins/lp-content-manager/" target="_blank">Learn more about LPCM Premium or buy your license here</a></p>
		</div>
	<?php
}
?>