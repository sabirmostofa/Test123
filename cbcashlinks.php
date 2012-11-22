<?php

/*
Plugin Name: CB Cashlinks
Plugin URI: http://www.cbcashlinks.com/
Version: 2.3
Author: Scott Inman
Description: The Best Clickbank WordPress Plugin On The Market.
*/
$cb_cashlinks_plugin_version = 2.3;
register_activation_hook( __FILE__, 'cbcashlinks_install' );
register_deactivation_hook( __FILE__, 'cbcashlinks_uninstall' );

function cbcashlinks_install() {
  global $wpdb;

  // Setup the ClickBank plugin
  $options = cbcashlinks_options();

  foreach ($options as $option => $value) {
    if (get_option('cbcash_' . $option) === false) {
      update_option('cbcash_' . $option, $value);
    }
  }
  
  // Setup the Banners add-on
  $ads_table = $wpdb->prefix . 'cbcash_ads';
  $cmp_table = $wpdb->prefix . 'cbcash_campaigns';
  $cblinks_data_table = $wpdb->prefix . 'cblinks_feed_data';

  $sql1 = "
    CREATE TABLE $ads_table (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `adname` VARCHAR( 100 ) NOT NULL,
    `adtop` TINYINT UNSIGNED NOT NULL,
    `adbottom` TINYINT UNSIGNED NOT NULL,
    `adcode` TEXT NOT NULL,
    `adalign` ENUM( 'left', 'right', 'center' ) NOT NULL DEFAULT 'center',
    `adcats` TEXT NOT NULL,
    `adpadding` varchar(255) NOT NULL,
    `isactive` tinyint(1) NOT NULL default '0'
    ) ENGINE = InnoDB;
  ";

  $sql2 = "
    CREATE TABLE $cmp_table (
      `id` int(10) unsigned NOT NULL auto_increment,
      `cmpname` varchar(100) NOT NULL,
      `tid` varchar(20) NOT NULL,
      `keywords` varchar(255) NOT NULL,
      `xclude_keywords` varchar(255) NOT NULL default '',
      `replace_keywords` varchar(255) NOT NULL default '',
      `replace_texts` text NOT NULL default '',
      `cmptags` tinyint(1) NOT NULL,
      `cmptop` tinyint(1) NOT NULL,
      `cmpbottom` tinyint(1) NOT NULL,
      `cmpwidth` smallint unsigned NOT NULL,
      `cmpgravity` smallint unsigned NOT NULL default '0',
      `cmpreferral` smallint unsigned NOT NULL default '0',
      `show_cats` text NOT NULL,
      `is_active` tinyint(1) NOT NULL default '0',
      `xclude_des` tinyint(1) NOT NULL default '0',
      `cmp_recurr` tinyint(1) NOT NULL default '0',
      PRIMARY KEY  (`id`)
    ) ENGINE=InnoDB;
  ";
  
  $data = array('Id', 'PopularityRank','Title','Description', 
  'HasRecurringProducts', 'Gravity', 'PercentPerSale',
   'PercentPerRebill','AverageEarningsPerSale','InitialEarningsPerSale',
   'TotalRebillAmt', 'Referred' , 'Commission', 'ActivateDate');
   
   // using MYSIAM, InnoDB insertion was hell
  $sql3 = "
    CREATE TABLE $cblinks_data_table (
      `Id` varchar(15) NOT NULL,
      `PopularityRank` int(5) NOT NULL,
      `Title` varchar(255) NOT NULL,
      `Description` text NOT NULL,
      `HasRecurringProducts` varchar(7) NOT NULL,
      `Gravity` float(9,5) NOT NULL, 
      `Referred` float(5,1) NOT NULL,
      PRIMARY KEY  (`Id`)
    ) ENGINE=MYISAM;
  ";

  require_once ABSPATH . 'wp-admin/includes/upgrade.php';
  global $wpdb;
  $wpdb->query("drop table if exists `$cblinks_data_table`");
  dbDelta($sql1);
  dbDelta($sql2);
  dbDelta($sql3);
  
  //initialize cron
	 if (!wp_get_schedule('cbcashlinks_cron_hook'))
		wp_schedule_event(time()-120, 'daily', 'cbcashlinks_cron_hook');
}

function cbcashlinks_uninstall(){
	 if(wp_get_schedule('cbcashlinks_cron_hook'))
		wp_clear_scheduled_hook('cbcashlinks_cron_hook');
}

function add_cbcashlinks_scripts() {
  global $pagenow;

  if($pagenow == 'admin.php' && isset($_GET['page']) && stristr($_GET['page'], 'cbcashlinks')) {
    $cbcashlinks_plugin_url = trailingslashit(get_bloginfo('wpurl')) . PLUGINDIR . '/' . dirname(plugin_basename(__FILE__));
	
	wp_enqueue_style('pbar-css', plugins_url('css/jquery-ui.css', __FILE__));
	wp_enqueue_style('cb-admin-css', plugins_url('css/css-admin.css', __FILE__));
    wp_enqueue_script('jquery');
    wp_enqueue_script("myUi","https://ajax.googleapis.com/ajax/libs/jqueryui/1.8.8/jquery-ui.min.js");
    //wp_enqueue_script('jquery-ui-core');
    //wp_enqueue_script('jquery-ui-progressbar');
    //wp_enqueue_script('jquery-ui-widget');
    wp_enqueue_script('color_js', $cbcashlinks_plugin_url . '/jscolor/jscolor.js', array('jquery'));
    wp_enqueue_script('cb-admin-script', $cbcashlinks_plugin_url . '/js/script-admin.js');
  }
}

function add_cbcashlinks_panel() {
  $icon_path = get_option('siteurl').'/wp-content/plugins/'.basename(dirname(__FILE__));

  add_menu_page('ClickBank Cashlinks Settings', 'CB Cashlinks', 8, basename(__FILE__), 'cbcashlinks_settings_panel', $icon_path . '/icon.png');
	add_submenu_page(basename(__FILE__), 'Settings', 'Settings', 8, basename(__FILE__), 'cbcashlinks_settings_panel');
	add_submenu_page(basename(__FILE__), 'Campaigns', 'Campaigns', 8, basename(__FILE__) . '-campaigns', 'cbcashlinks_campaigns_panel');
  add_submenu_page(basename(__FILE__), 'Banners', 'Banners', 8, basename(__FILE__) . '-banners', 'cbcashlinks_banners_panel');
}

function cbcashlinks_settings_panel() {
  if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $options = cbcashlinks_options();
    
    foreach ($options as $option => $value) {
      if(isset($_POST['cb_' . $option])) {
        $d = $_POST['cb_' . $option];
        
        if(is_array($d)) {
          update_option('cbcash_' . $option, implode(',', $d));
        } else {
          update_option('cbcash_' . $option, $d);
        }
      } else {
        update_option('cbcash_' . $option, null);
      }
    }
    
    $message = '<div class="updated">' . 'Settings Saved!' . '</div>';
    
    //if cron changed
    $sched = wp_get_schedule('cbcashlinks_cron_hook');
    $post_sched=$_POST['cb_cron_interval'];
    if(!$sched)
		wp_schedule_event(time()-120, $post_sched, 'cbcashlinks_cron_hook');
	if($post_sched != $sched){
		wp_clear_scheduled_hook('cbcashlinks_cron_hook');
		wp_schedule_event(time()-120, $post_sched, 'cbcashlinks_cron_hook');
	}
	
	//
	update_option('cb_replace_keywords',trim($_POST['cb_replace_keywords'], ', '));
	update_option('cb_replace_texts', trim($_POST['cb_replace_texts']));
  }
	$replace_keywords = get_option('cb_replace_keywords');
	$replace_texts =  get_option('cb_replace_texts');
	
  $fonts   = array('Theme Font', 'Verdana', 'Arial', 'Helvetica', 'Sans-Serif');
  $fsizes  = array('Theme Size', 10, 11, 12, 13, 14, 15, 16, 17, 18);
  $bstyles = array('None', 'Dotted', 'Dashed', 'Solid');

  $showmsg = false;

  if (! file_exists(ABSPATH . '/goto.php')) {
    $showmsg = true;
  }
  
  $cbpages    = get_pages();
  $cbselected = explode(',', get_option('cbcash_ad_spages'));

?>
	<div class="wrap">
    <h2><?php _e('CB Cashlinks Settings', 'cbcashlinks_settings'); ?></h2>

<?php if($message): ?>
    <?php echo $message; ?>
<?php endif;?>
    <form method="post">
    <h3>ClickBank</h3>
    
    <label for="cbcash_cb">
      <table class="form-table">
        <tr valign="top">
          <th scope="row"><label for="cb_nickname">ClickBank Nickname</label></th>
          <td>
            <input name="cb_nickname" type="text" id="cb_nickname" value="<?php echo get_option('cbcash_nickname'); ?>" class="medium-text" />
            <br /><span class="description">Enter your ClickBank Nickname here. If you want to disable the ads then leave the field blank.</span>
          </td>
        </tr>
      </table>
    </label>
    

    
    <h3>Link Cloaking</h3>

    <label for="cbcash_seo">
      <table class="form-table">
        <tr valign="top">
          <th scope="row"><label for="cb_cloak">Link Cloaking</label></th>
          <td>
            <input type="checkbox" name="cb_seo" value="1"<?php if(get_option('cbcash_seo')): ?> checked="checked"<?php endif; ?> />
            <span class="description">Please check the box if you want to enable link cloaking.</span>
<?php if($showmsg): ?>
            <p style="color:#e85c00; border: 1px solid #ccc; width: 550px; padding: 4px;">By ticking the box above, your HopLinks will be masked</p>
<?php endif;?>
          </td>
        </tr>
      </table>
    </label>

    <h3>Style Your Link Ads</h3>

    <label for="cbcash_style">
      <table class="form-table">
        <tr valign="top">
          <th scope="row"><label for="cb_ad_title">Link Unit Title</label></th>
          <td>
            <input name="cb_ad_title" type="text" id="cb_ad_title" value="<?php echo get_option('cbcash_ad_title'); ?>" class="regular-text" />
          </td>
        </tr>

        <tr valign="top">
          <th scope="row"><label for="cb_ad_num">Number of Ads</label></th>
          <td>
            <input name="cb_ad_number" type="text" id="cb_ad_num" value="<?php echo get_option('cbcash_ad_number'); ?>" class="small-text" />
          </td>
        </tr>

        <tr valign="top">
          <th scope="row"><label for="cb_ad_font">Ad Font &amp; Size</label></th>
          <td>
            <select name="cb_ad_font">
<?php foreach ($fonts as $font): ?>
              <option value="<?php echo $font; ?>"<?php if($font == get_option('cbcash_ad_font')): ?> selected="selected"<?php endif;?>><?php echo $font; ?></option>
<?php endforeach; ?>
            </select>

            <select name="cb_ad_font_size">
<?php foreach ($fsizes as $fsize): ?>
              <option value="<?php echo $fsize; ?>"<?php if($fsize == get_option('cbcash_ad_font_size')): ?> selected="selected"<?php endif;?>><?php echo $fsize; ?></option>
<?php endforeach; ?>
            </select>px
          </td>
        </tr>

        <tr valign="top">
          <th scope="row"><label for="cb_ad_font_color">Font Color</label></th>
          <td>
            <input name="cb_ad_font_color" type="text" id="cb_ad_font_color" value="<?php echo get_option('cbcash_ad_font_color'); ?>" class="medium-text color {required:false}" />
          </td>
        </tr>
        <tr valign="top">
          <th scope="row"><label for="cb_ad_bg_color">Background Color</label></th>
          <td>
            <input name="cb_ad_bg_color" type="text" id="cb_ad_bg_color" value="<?php echo get_option('cbcash_ad_bg_color'); ?>" class="color" />
          </td>
        </tr>
        <tr valign="top">
          <th scope="row"><label for="cb_ad_widget_bg_color">Widget Background Color</label></th>
          <td>
            <input name="cb_ad_widget_bg_color" type="text" id="cb_ad_widget_bg_color" value="<?php echo get_option('cbcash_ad_widget_bg_color'); ?>" class="color" />
          </td>
        </tr>
        <tr valign="top">
          <th scope="row"><label for="cb_ad_link_font_color">Link Font Color</label></th>
          <td>
            <input name="cb_ad_link_color" type="text" id="cb_ad_link_font_color" value="<?php echo get_option('cbcash_ad_link_color'); ?>" class="medium-text color {required:false}" />
          </td>
        </tr>
        <tr valign="top">
          <th scope="row"><label for="cb_ad_link_hover_color">Link Hover Color</label></th>
          <td>
            <input name="cb_ad_link_hover" type="text" id="cb_ad_link_hover_color" value="<?php echo get_option('cbcash_ad_link_hover'); ?>" class="medium-text color {required:false}" />
          </td>
        </tr>
        <tr valign="top">
          <th scope="row"><label for="cb_ad_border_size">Ad Border Size &amp; Style</label></th>
          <td>
            <input name="cb_ad_border_size" type="text" id="cb_ad_border_size" value="<?php echo get_option('cbcash_ad_border_size'); ?>" class="small-text" /> px

            <select name="cb_ad_border_style">
<?php foreach ($bstyles as $bstyle): ?>
              <option value="<?php echo $bstyle; ?>"<?php if($bstyle == get_option('cbcash_ad_border_style')): ?> selected="selected"<?php endif;?>><?php echo $bstyle; ?></option>
<?php endforeach; ?>
            </select>
          </td>
        </tr>
        <tr valign="top">
          <th scope="row"><label for="cb_ad_border_color">Ad Border Color</label></th>
          <td>
            <input name="cb_ad_border_color" type="text" id="cb_ad_border_color" value="<?php echo get_option('cbcash_ad_border_color'); ?>" class="medium-text color {required:false}" />
          </td>
        </tr>
        <tr valign="top">
          <th scope="row"><label for="cb_ad_size">Ad Box Width (default)</label></th>
          <td>
            <input name="cb_ad_width" type="text" id="cb_ad_width" value="<?php echo get_option('cbcash_ad_width'); ?>" class="small-text" /> px
          </td>
        </tr>
        <tr valign="top">
          <th scope="row"><label for="cb_ad_lpad">Left Padding</label></th>
          <td>
            <input name="cb_ad_lpad" type="text" id="cb_ad_lpad" value="<?php echo get_option('cbcash_ad_lpad'); ?>" class="small-text" /> px
          </td>
        </tr>
        <tr valign="top">
          <th scope="row"><label for="cb_ad_widget_lpad">Left Padding for widget Ads</label></th>
          <td>
            <input name="cb_ad_widget_lpad" type="text" id="cb_ad_widget_lpad" value="<?php echo get_option('cbcash_ad_widget_lpad'); ?>" class="small-text" /> px
          </td>
        </tr>
    		<tr valign="top">
          <th scope="row"><label for="cb_ad_pages">Show Ads On Pages?</label></th>
          <td>
            <input type="checkbox" name="cb_ad_pages" id="cb_ad_pages"<?php if(get_option('cbcash_ad_pages')):?> checked="checked"<?php else:?><?php endif;?> /> <span style="font-size:12px;color:#e85c00;">(By ticking this box, CB Cashlinks will <em style="font-weight:bold;">show</em> on <u style="font-weight:bold;">ALL</u> pages with the <em style="font-weight:bold;">default settings</em> above!<br />Or you can <b>exclude</b> certain pages by selecting which pages <b>not</b> to have ads appear.)</span>
          </td>
        </tr>
    		<tr valign="top">
          <th scope="row"><label for="cb_ad_spages">Exclude Pages</label></th>
          <td>
            <select name="cb_ad_spages[]" multiple="multiple" size="10" style="height: 100px; width: 200px;">
    <?php foreach ($cbpages as $page): ?>
              <option value="<?php echo $page->ID; ?>"<?php if(is_array($cbselected) && in_array($page->ID, $cbselected)):?> selected="selected"<?php endif; ?>><?php echo $page->post_title; ?></option>
    <?php endforeach; ?>
            </select>
             <br /><span class="description">Please select the pages where you don't want to show ads.<br />The selection is ignored if "Show Ads On Pages?" is unchecked.</span>
          </td>
        </tr>
        <tr valign="top">
          <th scope="row"><label for="cb_ad_homepage">Show Ads On Homepage?</label></th>
          <td>
            <input type="checkbox" name="cb_ad_homepage" id="cb_ad_homepage"<?php if(get_option('cbcash_ad_homepage')):?> checked="checked"<?php else:?><?php endif;?> /> <span style="font-size:12px;color:#e85c00;">(If your site is showing <em style="font-weight:bold;">excerpts</em> on the homepage, please <em style="font-weight:bold;text-decoration:underline;">do not</em> tick this box!)</span>
          </td>
        </tr>
        <tr valign="top">
          <th scope="row"><label for="cb_ad_short">Ignore Ads with Shortcode</label></th>
          <td>
            <input type="checkbox" name="cb_ad_short" id="cb_ad_short"<?php if(get_option('cbcash_ad_short')):?> checked="checked"<?php else:?><?php endif;?> />
            <br /><span class="description">Don't show top/bottom ads when shortcode is used inside post or page.</span>
          </td>
        </tr>     
        
      </table>
      
        <h3>Database Settings</h3>
		<?php
		
		$num = cbcashlinks_get_num();
		 
		?>
    <label for="cbcash_dbupdate">
      <table class="form-table">
        <tr valign="top">
          <th scope="row"><label for="cb_db">Last updated:</th>
          <td>
			<b><span id="cb_last"><?php echo get_option('cb_cron_last_done'); ?></span></b> 			
			
          </td>
          </tr>
          
          <tr>          
          <th scope="row"><label for="cb_db">Total records in DB:</th>
          <td>
			<b><span id="cb_tot"><?php echo $num; ?> </span></b>  
			<a id="cbdbupdate" style="margin-left:10px;" href="?cbdbupdate=true" target="new">(Update Database) </a>
			<div id="db-pbar">
			</div>
			
          </td>
    
        </tr>
        
        <tr valign="top">
          <th scope="row"><label for="cb_db">Automatic Update Frequency:</th>
          <td>
			 
			 <?php $sched = wp_get_schedule('cbcashlinks_cron_hook'); ?>
					 
		 <select name="cb_cron_interval">
			 <option <?php if($sched == 'hourly')echo 'selected="selected"'; ?> value="hourly">Hourly</option>
			 <option <?php if($sched == 'twicedaily')echo 'selected="selected"'; ?> value="twicedaily">Twice Daily</option>
			 <option <?php if($sched == 'daily')echo 'selected="selected"'; ?> value="daily">Daily</option>
		</select>
			
          </td>
    
        </tr>
      </table>
    </label>
    </label>
    
        <h3>Replace Ad Description:(Can be overridden in the campaign page)</h3>

    <label for="cbcash_seo">
      <table class="form-table">
                    <tr>
            	<td style="width: 200px;">
                <label for="cb_replace_keywords">Replace if contains Keywords  :</label>
                <p><span style="font-size: x-small; color: gray;">Ad description having any of the keywords will be replaced by the following texts. Seperate the keywords with a comma.</span></p>
              </td>
            	<td>
            		<input type="text" style="width: 200px;" name="cb_replace_keywords" value="<?php echo $replace_keywords; ?>">
            	</td>
            </tr>
            
            <tr>
            	<td style="width: 200px;">
                <label for="replace_texts">Replacement Texts:</label>
                <p><span style="font-size: x-small; color: gray;">Ad description will be replaced by the any of the following texts randomly. Separate the texts by a new line. Tag available: %title%</span></p>
              </td>
            	<td>
            		<textarea rows=6 cols=60 name="cb_replace_texts"><?php echo $replace_texts; ?></textarea>
            	</td>
            </tr>
      </table>
    </label>

    <p class="submit">
      <input type="submit" value="Save Changes" class="button-primary" name="Submit">
    </p>

    </form>
    

  </div>
<?php


}

function cbcashlinks_banners_panel() {
  global $wpdb;

  $table_name = $wpdb->prefix . 'cbcash_ads';
  $error      = false;
  $saved      = false;

  if (isset($_GET['id']) && $_GET['id'] > 0) {
    $id = (int)$_GET['id'];
  }

  if(isset($_POST['action']) && ($_POST['action'] == 'newad' || $_POST['action'] == 'editad')) {
    // Create a new ad
    $adname   = $_POST['adname'];
    $adtop    = $_POST['adtop'];
    $adbottom = $_POST['adbottom'];
    $adcode   = $_POST['adcode'];
    $adalign  = $_POST['adalign'];
    $adcats   = serialize($_POST['adcats']);
    $adpads   = serialize($_POST['adpads']);
    $isactive = (int)$_POST['isactive'];

    if (! $adname) {
      $adname = 'Untitled Banner';
    }

    if (! $adcode) {
      $error = true;
    } else {
      if ($_POST['action'] == 'newad') {
        $wpdb->insert($table_name, array(
          'adname'   => $adname,
          'adtop'    => $adtop,
          'adbottom' => $adbottom,
          'adcode'   => $adcode,
          'adalign'  => $adalign,
          'adcats'   => $adcats,
          'adpadding' => $adpads,
          'isactive' => $isactive
        ));
      } else {
        $wpdb->update($table_name, array(
          'adname'   => $adname,
          'adtop'    => $adtop,
          'adbottom' => $adbottom,
          'adcode'   => $adcode,
          'adalign'  => $adalign,
          'adcats'   => $adcats,
          'adpadding' => $adpads,
          'isactive' => $isactive
        ), array('id' => $id));

        $saved = true;
      }
    }

    if (! $error) {
      unset($adname, $adtop, $adbottom, $adcode, $adalign, $isactive);
    }
  }

  if(isset($_POST['adaction']) && $_POST['adaction'] == 'delete') {
    if (isset($_POST['ads']) && is_array($_POST['ads']) && !empty($_POST['ads'])) {
      $adids = array();

      foreach($_POST['ads'] as $k => $v) {
        $v = (int)$v;

        if ($v && $v > 0)
          $adids[] = $v;
      }

      if (! empty($adids)) {
        $wpdb->query('DELETE FROM ' . $table_name . ' WHERE id IN (' . implode(',', $adids) . ')');
      }
    }
  }

  if (! $id) {
    $sql = 'SELECT * FROM ' . $table_name;
    $ads = $wpdb->get_results($sql, ARRAY_A);
  } else {
    $sql = 'SELECT * FROM ' . $table_name . ' WHERE id = ' . $id;
    $getad = $wpdb->get_results($sql, ARRAY_A);
    $getad = $getad[0];

    $adname    = $getad['adname'];
    $adtop     = $getad['adtop'];
    $adbottom  = $getad['adbottom'];
    $adcode    = $getad['adcode'];
    $adalign   = $getad['adalign'];
    $adcats    = unserialize($getad['adcats']);
    $adpads    = unserialize($getad['adpadding']);
    $isactive  = (int)$getad['isactive'];
  }

  $catdrop = wp_dropdown_categories(
    array(
      'show_count' => false,
      'hide_empty' => false,
      'name' => 'adcats[]',
      'echo' => false,
      'hierarchical' => true,
    )
  );

  $catdrop = str_replace('<select ', '<select multiple="multiple" size="10" style="width: 300px; height: 100px;" ', $catdrop);

  if(is_array($adcats) && ! empty($adcats)) {
    foreach($adcats as $cat) {
      $catdrop = str_replace(' value="'.(int)$cat.'">', ' value="'.(int)$cat.'" selected="selected">', $catdrop);
    }
  }

?>
	<div class="wrap">
  <h2>CB Cashlinks Banners</h2>
  <div id="newad"<?php if (! $error && ! $id): ?> style="display: none;"<?php endif; ?>>
    <form method="post">
    <input type="hidden" name="action" value="<?php if (! $id): ?>newad<?php else: ?>editad<?php endif; ?>">
    <div class="metabox-holder" id="poststuff">
    	<div id="post-body">
    	 <div id="post-body-content">
        <div class="meta-box-sortables ui-sortable" id="main-sortables">
          <div class="postbox">
          <h3><span>Banner Details</span></h3>
          <div class="inside">

<?php if ($error): ?>
          <span style="color: red;">Error! Please double check your fields.</span>
<?php endif; ?>

<?php if ($id && $saved): ?>
          <div class="updated">Banner Saved!</div>
<?php endif; ?>

          <div style="font-size: small;">
            <table class="form-table">
            <tbody>
            <tr>
            	<td style="width: 200px;">
                <label for="adname">Banner Name:</label>
                <p><span style="font-size: x-small; color: gray;">Enter your banner name.</span></p>
              </td>
            	<td>
            		<input type="text" style="width: 200px;" name="adname" value="<?php echo $adname; ?>">
            	</td>
            </tr>

            <tr>
            	<td style="width: 200px;">
                <label for="adname">Banner Code:</label>
                <p><span style="font-size: x-small; color: gray;">Copy paste the banner HTML/JS code here.</span></p>
              </td>
            	<td>
            		<textarea name="adcode" style="width: 400px; height: 200px;"><?php echo stripslashes($adcode); ?></textarea>
            	</td>
            </tr>

            <tr>
            	<td>
                <label for="adheight">Banner Position:</label>
                <p><span style="font-size: x-small; color: gray;">Where should the banner appear.</span></p>
             </td>
            	<td>
            		<input type="checkbox" name="adtop" value="1"<?php if ($adtop): ?> checked="checked"<?php endif;?>> Above content<br />
                	<input type="checkbox" name="adbottom" value="1"<?php if ($adbottom): ?> checked="checked"<?php endif;?>> Below content
            	</td>
            </tr>

            <tr>
            	<td>
                <label for="adheight">Banner Targeting:</label>
                <p><span style="font-size: x-small; color: gray;">Select the categories where to show the banner. Hold CTRL if you would like to unselect/select several categories.</span></p>
             </td>
            	<td>
                <?php echo $catdrop; ?>
            	</td>
            </tr>

            <tr>
            	<td>
                <label for="adheight">Banner Alignment:</label>
                <p><span style="font-size: x-small; color: gray;">Align your banner to left, center or right.</span></p>
             </td>
            	<td>
            		<select name="adalign" style="width: 100px;">
                  <option value="left"<?php if($adalign == 'left'): ?> selected="selected"<?php endif; ?>>Left</option>
                  <option value="center"<?php if($adalign == 'center'): ?> selected="selected"<?php endif; ?>>Center</option>
                  <option value="right"<?php if($adalign == 'right'): ?> selected="selected"<?php endif; ?>>Right</option>
                </select>
            	</td>
            </tr>

            <tr>
            	<td>
                <label for="adpads">Banner Padding:</label>
                <p><span style="font-size: x-small; color: gray;">Here you can change the ad padding.</span></p>
              </td>
            	<td>
                <table border="0" cellspacing="0">
                  <tr>
                		<td>Top:</td><td><input type="text" style="width: 50px;" name="adpads[top]" value="<?php echo ((isset($adpads['top'])) ? $adpads['top'] : ''); ?>" /> px</td>
              		</tr>
                  <tr>
                		<td>Bottom:</td><td><input type="text" style="width: 50px;" name="adpads[bottom]" value="<?php echo ((isset($adpads['bottom'])) ? $adpads['bottom'] : ''); ?>" /> px</td>
              		</tr>
              		<tr>
                		<td>Right:</td><td><input type="text" style="width: 50px;" name="adpads[right]" value="<?php echo ((isset($adpads['right'])) ? $adpads['top'] : ''); ?>" /> px</td>
              		</tr>
              		<tr>
                		<td>Left:</td><td><input type="text" style="width: 50px;" name="adpads[left]" value="<?php echo ((isset($adpads['left'])) ? $adpads['left'] : ''); ?>" /> px</td>
              		</tr>
            		</table>
            	</td>
            </tr>


            <tr>
            	<td>
                <label for="adheight">Active:</label>
                <p><span style="font-size: x-small; color: gray;">Activate your banner.</span></p>
             </td>
            	<td>
            		<input type="checkbox" name="isactive" value="1"<?php if ($isactive): ?> checked="checked"<?php endif;?>>
            	</td>
            </tr>

            </tbody>
            </table>
          </div>
        </div>
      </div>
        <p><input type="submit" value="<?php if(!$id):?>Add Banner<?php else:?>Save Banner<?php endif;?>" class="button-primary"></p>
      </div>
      </div>
      </div>
    </div>
    </form>
  </div>

<?php if (!empty($ads)): ?>
    <form method="post" id="listform">
    <input type="hidden" name="adaction" value="" id="adaction">
    <div class="tablenav">
      <div class="alignleft actions">
        <select name="action" id="action">
          <option selected="selected" value="">Bulk Actions</option>
          <option value="delete">Delete</option>
        </select>

        <input type="submit" onclick="document.getElementById('adaction').value = document.getElementById('action').value;" class="button-secondary action" id="doaction" name="doaction" value="Apply">
        <input type="button" class="button-secondary action" value="Create New Banner" onclick="jQuery('#newad').show();">
      </div>

      <div class="clear"></div>
    </div>

    <table cellspacing="0" class="widefat post fixed">
    	<thead>
    	<tr>
    	<th style="" class="manage-column column-cb check-column" scope="col"><input type="checkbox"></th>
    	<th style="" class="manage-column" scope="col">Banner Name</th>
    	<th style="" class="manage-column" scope="col">Position</th>
    	<th style="" class="manage-column" scope="col">Active</th>
    	</tr>
    	</thead>

    	<tfoot>
    	<tr>
    	<th style="" class="manage-column column-cb check-column" scope="col"><input type="checkbox"></th>
    	<th style="" class="manage-column" scope="col">Banner Name</th>
    	<th style="" class="manage-column" scope="col">Position</th>
    	<th style="" class="manage-column" scope="col">Active</th>
    	</tr>
    	</tfoot>

    	<tbody>
<?php foreach ($ads as $ad): ?>
    	<tr valign="top" class="alternate author-self status-publish iedit">
    		<th class="check-column" scope="row">
          <input type="checkbox" value="<?php echo $ad['id']; ?>" name="ads[]" id="iad-<?php echo $ad['id']; ?>">
        </th>
    		<td class="post-title column-title">
    			<strong><a title="Edit Banner" href="?page=cbcashlinks.php-banners&id=<?php echo $ad['id']; ?>" class="row-title"><?php echo $ad['adname']; ?></a></strong>
    			<div class="row-actions">
    				<span class="edit"><a href="?page=cbcashlinks.php-banners&id=<?php echo $ad['id']; ?>" title="Edit Banner">Edit</a> | </span>
    				<span class="edit"><a href="#" title="Delete Banner" onclick="var confirm=window.confirm('This item will be deleted. Are you sure?');if(confirm==false)return;jQuery('#adaction').val('delete'); jQuery('#iad-<?php echo $ad['id']; ?>').attr('checked', true); jQuery('#listform').submit(); return false;">Delete</a></span>
    			</div>
    		</td>
    		<td><span style="color: gray;">Above Content: <b><?php if($ad['adtop']): ?>Yes<?php else: ?>No<?php endif; ?></b><br />Below Content: <b><?php if($ad['adbottom']): ?>Yes<?php else: ?>No<?php endif; ?></b></span></td>
    		<td><span style="color: gray;"><?php if($ad['isactive']): ?>Yes<?php else: ?>No<?php endif; ?></span></td>
    	</tr>
<?php endforeach; ?>
    	</tbody>
    </table>

    <div class="tablenav">
      <div class="alignleft actions">
        <select name="action" id="action">
          <option selected="selected" value="">Bulk Actions</option>
          <option value="delete">Delete</option>
        </select>

        <input type="submit" onclick="document.getElementById('adaction').value = document.getElementById('action').value;" class="button-secondary action" id="doaction" name="doaction" value="Apply">
        <input type="button" class="button-secondary action" value="Create New Banner" onclick="jQuery('#newad').show();">
      </div>

      <div class="clear"></div>
    </div>
    </form>
<?php else: ?>
<?php if(! $id): ?>
<h3>Please create your first banner by clicking 'Create New Banner'.</h3>

<input type="button" class="button-secondary action" value="Create New Banner" onclick="jQuery('#newad').show();">
<?php endif; ?>
<?php endif; ?>
</div>
<?php
}

function cbcashlinks_campaigns_panel() {
  global $wpdb;

  $table_name = $wpdb->prefix . 'cbcash_campaigns';
  $error      = false;
  $saved      = false;

  if (isset($_GET['id']) && $_GET['id'] > 0) {
    $id = (int)$_GET['id'];
  }

  if(isset($_POST['action']) && ($_POST['action'] == 'newcmp' || $_POST['action'] == 'editcmp')) {
    // Create a new ad
    $cmpname     = $_POST['cmpname'];
    $cmptid      = $_POST['cmptid'];
    $cmpkeywords = $_POST['cmpkeywords'];
    $cmptags     = $_POST['cmptags'];
    $cmptop      = $_POST['cmptop'];
    $cmpbottom   = $_POST['cmpbottom'];
    $cmpcats     = serialize($_POST['cmpcats']);
    $cmpwidth    = $_POST['cmpwidth'];
    $isactive    = (int)$_POST['isactive'];
    $cmpgravity  = (int)$_POST['cmpgravity'];
    $cmpreferral =	(int)$_POST['cmpreferral'];
    $xclude_des =  (int)$_POST['xclude_des'];
    $cmp_recurr =  (int)$_POST['cmp_recurr'];
    $xclude_keywords =  stripcslashes(trim($_POST['xclude_keywords'], ', '));
    $replace_keywords =  stripcslashes(trim($_POST['replace_keywords'], ', '));
    $replace_texts =  stripslashes(trim($_POST['replace_texts'], ', '));

    if (! $cmpname) {
      $cmpname = 'Untitled Campaign';
    }

    if (! $cmpkeywords && ! $cmptags) {
      $error = true;
    } else {
      if ($_POST['action'] == 'newcmp') {
        $wpdb->insert($table_name, array(
          'cmpname'   => $cmpname,
          'tid'       => $cmptid,
          'keywords'  => $cmpkeywords,
          'cmptags'   => $cmptags,
          'cmptop'    => $cmptop,
          'cmpbottom' => $cmpbottom,
          'show_cats' => $cmpcats,
          'cmpwidth'  => $cmpwidth,
          'is_active' => $isactive,
          'cmpgravity' => $cmpgravity,
          'cmpreferral' => $cmpreferral,
          'xclude_des' => $xclude_des,
          'cmp_recurr' => $cmp_recurr,
          'xclude_keywords' => $xclude_keywords,
          'replace_keywords' => $replace_keywords,
          'replace_texts' => $replace_texts
        ));
      } else {
        $wpdb->update($table_name, array(
          'cmpname'   => $cmpname,
          'tid'       => $cmptid,
          'keywords'  => $cmpkeywords,
          'cmptags'   => $cmptags,
          'cmptop'    => $cmptop,
          'cmpbottom' => $cmpbottom,
          'show_cats' => $cmpcats,
          'cmpwidth'  => $cmpwidth,
          'is_active' => $isactive,
          'cmpgravity' => $cmpgravity,
          'cmpreferral' => $cmpreferral,
          'xclude_des' => $xclude_des,
          'cmp_recurr' => $cmp_recurr,
          'xclude_keywords' => $xclude_keywords,
          'replace_keywords' => $replace_keywords,
          'replace_texts' => $replace_texts
        ), array('id' => $id));

        $saved = true;
      }
    }

    if (! $error) {
      unset($cmpname, $cmptop, $cmpbottom, $cmptid, $cmpkeywords, $cmpcats, $cmpwidth, $isactive, $cmptags);
    }
  }

  if(isset($_POST['cmpaction']) && $_POST['cmpaction'] == 'delete') {
    if (isset($_POST['cmps']) && is_array($_POST['cmps']) && !empty($_POST['cmps'])) {
      $cmpids = array();

      foreach($_POST['cmps'] as $k => $v) {
        $v = (int)$v;

        if ($v && $v > 0)
          $cmpids[] = $v;
      }

      if (! empty($cmpids)) {
        $wpdb->query('DELETE FROM ' . $table_name . ' WHERE id IN (' . implode(',', $cmpids) . ')');
      }
    }
  }

  if (! $id) {
    $sql = 'SELECT * FROM ' . $table_name;
    $cmps = $wpdb->get_results($sql, ARRAY_A);
  } else {
    $sql = 'SELECT * FROM ' . $table_name . ' WHERE id = ' . $id;
    $getcmp = $wpdb->get_results($sql, ARRAY_A);
    $getcmp = $getcmp[0];

    $cmpname     = $getcmp['cmpname'];
    $cmptid      = $getcmp['tid'];
    $cmpkeywords = $getcmp['keywords'];
    $cmptags     = $getcmp['cmptags'];
    $cmptop      = $getcmp['cmptop'];
    $cmpbottom   = $getcmp['cmpbottom'];
    $cmpcats     = unserialize($getcmp['show_cats']);
    $cmpwidth    = $getcmp['cmpwidth'];
    $isactive    = (int)$getcmp['is_active'];
    $cmpgravity  = $getcmp['cmpgravity'];
    $cmpreferral = $getcmp['cmpreferral'];
    $xclude_des  =  (int)$getcmp['xclude_des'];
    $cmp_recurr  =  (int)$getcmp['cmp_recurr'];
    $xclude_keywords =  stripslashes( $getcmp['xclude_keywords']);
    $replace_keywords =  stripslashes( $getcmp['replace_keywords']);
    $replace_texts =  stripslashes($getcmp['replace_texts']);
  }

  $catdrop = wp_dropdown_categories(
    array(
      'show_count' => false,
      'hide_empty' => false,
      'name' => 'cmpcats[]',
      'echo' => false,
      'hierarchical' => true,
    )
  );

  $catdrop = str_replace('<select ', '<select multiple="multiple" size="10" style="width: 300px; height: 100px;" ', $catdrop);

  if(is_array($cmpcats) && ! empty($cmpcats)) {
    foreach($cmpcats as $cat) {
      $catdrop = str_replace(' value="'.(int)$cat.'">', ' value="'.(int)$cat.'" selected="selected">', $catdrop);
    }
  }

?>
	<div class="wrap">
  <h2>CB Cashlinks Campaigns</h2>
  <div id="newcmp"<?php if (! $error && ! $id): ?> style="display: none;"<?php endif; ?>>
    <form method="post">
    <input type="hidden" name="action" value="<?php if (! $id): ?>newcmp<?php else: ?>editcmp<?php endif; ?>">
    <div class="metabox-holder" id="poststuff">
    	<div id="post-body">
    	 <div id="post-body-content">
        <div class="meta-box-sortables ui-sortable" id="main-sortables">
          <div class="postbox">
          <h3><span>Campaign Details</span></h3>
          <div class="inside">

<?php if ($error): ?>
          <span style="color: red;">Error! Please double check your fields.</span>
<?php endif; ?>

<?php if ($id && $saved): ?>
          <div class="updated">Campaign Saved!</div>
<?php endif; ?>

          <div style="font-size: small;">
            <table class="form-table">
            <tbody>
            <tr>
            	<td style="width: 200px;">
                <label for="cmpname">Campaign Name:</label>
                <p><span style="font-size: x-small; color: gray;">Enter a name for your campaign.</span></p>
              </td>
            	<td>
            		<input type="text" style="width: 200px;" name="cmpname" value="<?php echo $cmpname; ?>">
            	</td>
            </tr>

            <tr>
            	<td style="width: 200px;">
                <label for="cmptid">Tracking ID:</label>
                <p><span style="font-size: x-small; color: gray;">Your tracking ID will help you track sales.</span></p>
              </td>
            	<td>
            		<input type="text" style="width: 200px;" name="cmptid" value="<?php echo $cmptid; ?>">
            	</td>
            </tr>

            <tr>
            	<td style="width: 200px;">
                <label for="cmpkeywords">Keywords:</label>
                <p><span style="font-size: x-small; color: gray;">The keywords will be used to find related ads. Seperate the keywords with a comma.</span></p>
              </td>
            	<td>
            		<input type="text" style="width: 200px;" name="cmpkeywords" value="<?php echo $cmpkeywords; ?>">
            	</td>
            </tr>
            
            <tr>
            	<td style="width: 200px;">
                <label for="xclude_keywords">Exclude if contains Keyword:</label>
                <p><span style="font-size: x-small; color: gray;">Ad description having any of the keywords will be excluded. Seperate the keywords with a comma.</span></p>
              </td>
            	<td>
            		<input type="text" style="width: 200px;" name="xclude_keywords" value="<?php echo $xclude_keywords; ?>">
            	</td>
            </tr>
            
            <tr>
            	<td style="width: 200px;">
                <label for="replace_keywords">Replace if contains Keywords  :</label>
                <p><span style="font-size: x-small; color: gray;">Ad description having any of the keywords will be replaced by the following texts. Seperate the keywords with a comma.</span></p>
              </td>
            	<td>
            		<input type="text" style="width: 200px;" name="replace_keywords" value="<?php echo $replace_keywords; ?>">
            	</td>
            </tr>
            
            <tr>
            	<td style="width: 200px;">
                <label for="replace_texts">Replacement Texts:</label>
                <p><span style="font-size: x-small; color: gray;">Ad description will be replaced by the any of the following texts randomly. Separate the texts by a new line. Tag available: %title%</span></p>
              </td>
            	<td>
            		<textarea rows=6 cols=60 name="replace_texts"><?php echo $replace_texts; ?></textarea>
            	</td>
            </tr>
            
            <tr>
            	<td style="width: 200px;">
                <label for="cmpgravity">Minimum gravity the products must have:</label>
                <p><span style="font-size: x-small; color: gray;">The value should be an integer</span></p>
              </td>
            	<td>
            		<input type="text" style="width: 200px;" name="cmpgravity" value="<?php echo $cmpgravity; ?>">
            	</td>
            </tr>
            
            <tr>
            	<td style="width: 200px;">
                <label for="cmpreferral">Minimum %referred the clickbank products must have:</label>
                <p><span style="font-size: x-small; color: gray;">The value should be an integer</span></p>
              </td>
            	<td>
            		<input type="text" style="width: 200px;" name="cmpreferral" value="<?php echo $cmpreferral; ?>">
            	</td>
            </tr>

            <tr>
            	<td>
                <label for="xclude_des">Exclude products if keywords not in Description:</label>
                <p><span style="font-size: x-small; color: gray;">If checked, the products will be ignored in which the keywords are not in product description</span></p>
             </td>
            	<td>
            		<input type="checkbox" name="xclude_des" value="1"<?php if ($xclude_des): ?> checked="checked"<?php endif;?>>
            	</td>
            </tr>
            
            <tr>
            	<td>
                <label for="cmp_recurr">show products only with recurring commission:</label>
                <p><span style="font-size: x-small; color: gray;">if checked, only the products which have recurring commission will be shown.</span></p>
             </td>
            	<td>
            		<input type="checkbox" name="cmp_recurr" value="1"<?php if ($cmp_recurr): ?> checked="checked"<?php endif;?>>
            	</td>
            </tr>
            
            <tr>
            	<td>
                <label for="cmptags">Use Post Tags as Keywords:</label>
                <p><span style="font-size: x-small; color: gray;">If checked the keywords above will be ignored and post tags will be used as keywords.</span></p>
             </td>
            	<td>
            		<input type="checkbox" name="cmptags" value="1"<?php if ($cmptags): ?> checked="checked"<?php endif;?>>
            	</td>
            </tr>

            <tr>
            	<td>
                <label for="adheight">Ad Location:</label>
                <p><span style="font-size: x-small; color: gray;">Location of the ad in the content.</span></p>
             </td>
            	<td>
            		<input type="checkbox" name="cmptop" value="1"<?php if ($cmptop): ?> checked="checked"<?php endif;?>> Above content<br />
                <input type="checkbox" name="cmpbottom" value="1"<?php if ($cmpbottom): ?> checked="checked"<?php endif;?>> Below content
            	</td>
            </tr>

            <tr>
            	<td>
                <label for="adheight">Category Targeting:</label>
                <p><span style="font-size: x-small; color: gray;">Select the categories where to show the ads. Hold CTRL if you would like to unselect/select several categories.</span></p>
             </td>
            	<td>
                <?php echo $catdrop; ?>
            	</td>
            </tr>

            <tr>
            	<td style="width: 200px;">
                <label for="adwidth">Ad Width:</label>
                <p><span style="font-size: x-small; color: gray;">The width of the ad box.<br />Default: 250px</span></p>
              </td>
            	<td>
            		<input type="text" style="width: 35px;" name="cmpwidth" value="<?php echo (($cmpwidth) ? $cmpwidth : 250); ?>" />px
            	</td>
            </tr>

            <tr>
            	<td>
                <label for="adheight">Active:</label>
                <p><span style="font-size: x-small; color: gray;">Activate your campaign.</span></p>
             </td>
            	<td>
            		<input type="checkbox" name="isactive" value="1"<?php if ($isactive): ?> checked="checked"<?php endif;?>>
            	</td>
            </tr>

            <tr>
            	<td style="width: 200px;">
                <label for="adshort">Ad Shortcode:</label>
                <p><span style="font-size: x-small; color: gray;">You can copy paste the following shortcode inside posts and pages.</span></p>
              </td>
            	<td>
            		<?php echo htmlentities('[cbcash camp="'.$cmpname.'"]');?>
            	</td>
            </tr>

            </tbody>
            </table>
          </div>
        </div>
      </div>
        <p><input type="submit" value="<?php if(!$id):?>Add Campaign<?php else:?>Save Campaign<?php endif;?>" class="button-primary"></p>
      </div>
      </div>
      </div>
    </div>
    </form>
  </div>

<?php if (!empty($cmps)): ?>
    <form method="post" id="listform">
    <input type="hidden" name="cmpaction" value="" id="cmpaction">
    <div class="tablenav">
      <div class="alignleft actions">
        <select name="action" id="action">
          <option selected="selected" value="">Bulk Actions</option>
          <option value="delete">Delete</option>
        </select>

        <input type="submit" onclick="document.getElementById('cmpaction').value = document.getElementById('action').value;" class="button-secondary action" id="doaction" name="doaction" value="Apply">
        <input type="button" class="button-secondary action" value="Create New Campaign" onclick="jQuery('#newcmp').show();">
      </div>

      <div class="clear"></div>
    </div>

    <table cellspacing="0" class="widefat post fixed">
    	<thead>
    	<tr>
    	<th style="" class="manage-column column-cb check-column" scope="col"><input type="checkbox"></th>
    	<th style="" class="manage-column" scope="col">Campaign Name</th>
    	<th style="" class="manage-column" scope="col">Position</th>
    	<th style="" class="manage-column" scope="col">Active</th>
    	</tr>
    	</thead>

    	<tfoot>
    	<tr>
    	<th style="" class="manage-column column-cb check-column" scope="col"><input type="checkbox"></th>
    	<th style="" class="manage-column" scope="col">Campaign Name</th>
    	<th style="" class="manage-column" scope="col">Position</th>
    	<th style="" class="manage-column" scope="col">Active</th>
    	</tr>
    	</tfoot>

    	<tbody>
<?php foreach ($cmps as $cmp): ?>
    	<tr valign="top" class="alternate author-self status-publish iedit">
    		<th class="check-column" scope="row">
          <input type="checkbox" value="<?php echo $cmp['id']; ?>" name="cmps[]" id="iad-<?php echo $cmp['id']; ?>">
        </th>
    		<td class="post-title column-title">
    			<strong><a title="Edit Campaign" href="?page=cbcashlinks.php-campaigns&id=<?php echo $cmp['id']; ?>" class="row-title"><?php echo $cmp['cmpname']; ?></a></strong>
    			<div class="row-actions">
    				<span class="edit"><a href="?page=cbcashlinks.php-campaigns&id=<?php echo $cmp['id']; ?>" title="Edit Campaign">Edit</a> | </span>
    				<span class="edit"><a href="#" title="Delete Campaign" onclick="var confirm=window.confirm('This item will be deleted. Are you sure?');if(confirm==false)return;jQuery('#cmpaction').val('delete'); jQuery('#iad-<?php echo $cmp['id']; ?>').attr('checked', true); jQuery('#listform').submit(); return false;">Delete</a></span>
    			</div>
    		</td>
    		<td><span style="color: gray;">Above Content: <b><?php if($cmp['cmptop']): ?>Yes<?php else: ?>No<?php endif; ?></b><br />Below Content: <b><?php if($cmp['cmpbottom']): ?>Yes<?php else: ?>No<?php endif; ?></b></span></td>
    		<td><span style="color: gray;"><?php if($cmp['is_active']): ?>Yes<?php else: ?>No<?php endif; ?></span></td>
    	</tr>
<?php endforeach; ?>
    	</tbody>
    </table>

    <div class="tablenav">
      <div class="alignleft actions">
        <select name="action" id="action">
          <option selected="selected" value="">Bulk Actions</option>
          <option value="delete">Delete</option>
        </select>

        <input type="submit" onclick="document.getElementById('cmpaction').value = document.getElementById('action').value;" class="button-secondary action" id="doaction" name="doaction" value="Apply">
        <input type="button" class="button-secondary action" value="Create New Campaign" onclick="jQuery('#newcmp').show();">
      </div>

      <div class="clear"></div>
    </div>
    </form>
<?php else: ?>
<?php if(! $id): ?>
<h3>Please create your first campaign by clicking 'Create New Campaign'.</h3>

<input type="button" class="button-secondary action" value="Create New Campaign" onclick="jQuery('#newcmp').show();">
<?php endif; ?>
<?php endif; ?>
</div>
<?php
}

function cbcashlinks_show_ad($content) {
  // Do we have the nickname? No? Don't show anything.
  if(trim(get_option('cbcash_nickname')) == '') {
    return cbcashlinks_get_banners($content);
  }

  $newContent = $content;

  // Load up CB ads
  
  $newContent = cbcashlinks_get_ads($newContent);

  // Load up banners
  $newContent = cbcashlinks_get_banners($newContent);

  return $newContent;
}

function cbcashlinks_get_banners($content) {
  global $wpdb;

  if(is_single() || is_page()) {
    $table_name = $wpdb->prefix . 'cbcash_ads';

    // Get ads for top

    $tops = $wpdb->get_results('SELECT adcode, adalign, adcats, adpadding FROM ' . $table_name . ' WHERE isactive = 1 AND adtop = 1');

    if($tops) {
      $topads = array();

      foreach($tops as $top) {
        if($top->adcode) {
          $xcats = unserialize($top->adcats);

          if(is_array($xcats) && ! empty($xcats)) {
            foreach ($xcats as $cat) {
              if(is_category($cat) || in_category($cat)) {
                $margin = null;
                $areas = array('top', 'bottom', 'right', 'left');
                
                $tmpmar = unserialize($top->adpadding);

                foreach($areas as $area) {
                  if(isset($tmpmar[$area]) && $tmpmar[$area] != '') {
                    $margin .= 'margin-' . $area . ': ' . $tmpmar[$area] . 'px; ';
                  }
                }
                
                $topads[] = "\r\n" . '<div style="' . $margin . 'text-align: ' . $top->adalign . '; display: block; clear: both;">' . stripslashes($top->adcode) . '</div>' . "\r\n";
              }
            }
          }
        }
      }

      if(is_array($topads) && ! empty($topads)) {
        shuffle($topads);
        $r = array_rand($topads, 1);
        $content = $topads[$r] . $content;
      }

      unset($tops, $topads, $newcode, $xcats, $cat, $top);
    }

    // Get ads for bottom

    $tops = $wpdb->get_results('SELECT adcode, adalign, adcats FROM ' . $table_name . ' WHERE isactive = 1 AND adbottom = 1');

    if($tops) {
      $topads = array();

      foreach($tops as $top) {
        if($top->adcode) {
          $xcats = unserialize($top->adcats);

          if(is_array($xcats) && ! empty($xcats)) {
            foreach ($xcats as $cat) {
              if(is_category($cat) || in_category($cat)) {
                $topads[] =  "\r\n" . '<div style="text-align: ' . $top->adalign . '; margin: 15px 0; clear:both;">' . stripslashes($top->adcode) . '</div>' . "\r\n";
              }
            }
          }
        }
      }

      if(is_array($topads) && ! empty($topads)) {
        shuffle($topads);
        $r = array_rand($topads, 1);
        $content = $content . $topads[$r];
      }

      unset($tops, $topads, $newcode, $xcats, $cat, $top);
    }
  }
  
  return $content;
}

function cbcashlinks_get_ads($content) {
  if(is_single() || (get_option('cbcash_ad_pages') && is_page()) || (get_option('cbcash_ad_homepage') && is_home())) {
    if(get_option('cbcash_ad_pages') && is_page()) {
      $cbpages = explode(',', get_option('cbcash_ad_spages'));

      if(is_array($cbpages) && ! empty($cbpages)) {
        foreach($cbpages as $cbpage) {
          if(is_page($cbpage)) {
            return $content;
          }
        }
      }
    }
    
    if(get_option('cbcash_ad_short')) {
      if(stristr($content, '[cbcash')) {
        return $content;
      }
    }

    // Pick a campaign by category
    $cmp = cbcashlinks_pick_campaign();

    if(is_array($cmp) && ! empty($cmp)) {
      // Ad HTML/CSS Code
      $ad     = cbcashlinks_ad_html($cmp['keywords'], $cmp['tid'], null, false,
 'cbcashads', $cmp['cmpgravity'], $cmp['cmpreferral'], $cmp['xclude_des'], 
 $cmp['xclude_keywords'], $cmp['replace_keywords'], $cmp['replace_texts'], $cmp['cmp_recurr']);
 
      $ad_css = cbcashlinks_ad_css($cmp['cmpwidth']);

      if(get_option('cbcash_seo')) {
        $ad_seo = cbcashlinks_ad_seo();
      } else {
        $ad_seo = '';
      }

      if($cmp['cmptop'] && $cmp['cmpbottom']) {
        $content = $ad . "\r\n" . $content . "\r\n" . $ad;
      } elseif ($cmp['cmpbottom']) {
        $content = $content . "\r\n" . $ad;
      } else {
        $content = $ad . "\r\n" . $content;
      }

      $content = $ad_css . "\r\n" . $content . "\r\n" . $ad_seo;
    }
  }
  
  return $content;
}

function cbcashlinks_get_widget_ads($keywords, $tid, $links = 5, $width = 200,
$widg_id=0,$wid_gravity, $wid_referral, $wid_xclude, $wid_xclude_keywords, $wid_replace_keywords, $wid_replace_texts, $cmp_recurr) {
  $content = null;

  $ad     = cbcashlinks_ad_html($tid, $keywords, $links, true, 'cbcash_widget_ads'.$widg_id,
  $wid_gravity, $wid_referral, $wid_xclude, $wid_xclude_keywords, $wid_replace_keywords, $wid_replace_texts, $cmp_recurr);
 
  $ad_css = cbcashlinks_ad_css($width, 'cbcash_widget_ads'.$widg_id);

  if(get_option('cbcash_seo')) {
    $ad_seo = cbcashlinks_ad_seo();
  } else {
    $ad_seo = '';
  }

  $content = $ad;
  $content = $ad_css . "\r\n" . $content . "\r\n" . $ad_seo;
  
  return $content;
}

function cbcashlinks_pick_campaign($camp_name = null) {
  global $wpdb, $post;

  $table_name = $wpdb->prefix . 'cbcash_campaigns';
  
  $cmps = $wpdb->get_results('SELECT keywords, tid, cmptop, cmpbottom, show_cats, cmpwidth, cmptags,cmpgravity,cmpreferral,xclude_des,xclude_keywords, replace_keywords, replace_texts, cmp_recurr FROM ' . $table_name . ' WHERE is_active = 1' . (($camp_name) ? ' AND LOWER(cmpname) = \'' . $camp_name . '\'' : null), ARRAY_A);
  $return = array();

  if($cmps) {
    foreach ($cmps as $cmp) {
      if( (isset($cmp['keywords']) && $cmp['keywords']) || (isset($cmp['cmptags']) && $cmp['cmptags'])) {
        $cats = unserialize($cmp['show_cats']);

        if(isset($cmp['cmptags']) && $cmp['cmptags']) {
          $tags = wp_get_post_tags($post->ID);
          
          if(! empty($tags)) {
            $cmp['keywords'] = '';

            foreach($tags as $tag) {
              $cmp['keywords'] .= $tag->name . ', ';
            }
            
            if($cmp['keywords'] != '') {
              $cmp['keywords'] = substr($cmp['keywords'], 0, strlen($cmp['keywords']) - 2);
            }
          }
        }

        if(is_array($cats) && ! empty($cats)) {
          foreach ($cats as $cat) {
            if(is_category($cat) || in_category($cat) || is_page()) {
              $return = $cmp;
            }
          }
        } elseif ($camp_name) {
          $return = $cmp;
        }
      }
    }
  }
  
  return $return;
}

function cbcashlinks_ad_seo() {
  //$hop     = 'http://' . get_option('cbcash_nickname') . '.hopfeed.com';
  $hop     = 'http://' . get_option('cbcash_nickname');
  $hop_len = strlen($hop);
  $site    = get_bloginfo('siteurl');
  $plugin_url = plugins_url('',__FILE__);

  $code = <<<EOT
<script type="text/javascript">
  //var TargetUrl = "$plugin_url/goto.php?r=";
  var TargetUrl = {target}
  var FeedLinks = document.getElementsByTagName("a");

  for(x=0; x<FeedLinks.length; x++){
    if(FeedLinks[x].getAttribute("href").substr(0,$hop_len) == "$hop") {
		var matches = /.*?\.(.*?)\..*?=(.*)/.exec(FeedLinks[x].getAttribute("href"));
     // FeedLinks[x].setAttribute("href", TargetUrl + FeedLinks[x].href.split("=")[1]+"&vendor="+matches[1]);
     {replace}
     // FeedLinks[x].setAttribute("href", TargetUrl + matches[1]+"/"+FeedLinks[x].href.split("=")[1]);
    }
  }
</script>
EOT;

if(!get_option('permalink_structure')){
	$target = "\"$site?r=\";";
	$replace = 'FeedLinks[x].setAttribute("href", TargetUrl + FeedLinks[x].href.split("=")[1]+"&vendor="+matches[1]);';
}
else{
	$target = "\"$site/recommends/\";";
	$replace = 'FeedLinks[x].setAttribute("href", TargetUrl + matches[1]+"/"+FeedLinks[x].href.split("=")[1]);';
}

$code = str_replace(array('{target}', '{replace}'), array($target, $replace), $code);
  return $code;
}

function cbcashlinks_ad_css($width, $cssshort = 'cbcashads') {
  $border = $family = $bg = $link_color = $link_hover = $ad_width = $ad_lpad = '';
  $is_widget = False;

  $cssleft = 'float: left;';

  if(stripos($cssshort, 'cbcash_widget_adscbcash') !==false) {
	$is_widget = True;
    $cssleft = '';
  }

  $code = <<<EOT
<style type="text/css">
  .$cssshort{
  {border}
  {family}
  {background}
  {ad_width}
    height:auto;
    {ad_lpad}
    padding-top: 10px;
    padding-right: 10px;
    padding-bottom: 0;
    margin: 0 20px 10px 0;
    position: relative;
    $cssleft
    overflow:hidden;
  }

  .$cssshort li {
    list-style-type: none !Important;
	margin:0 10px 5px 0;
  }

  .$cssshort a {
    font-weight: bold;
    text-decoration: underline;
  }

  .$cssshort a:href {
    text-decoration: underline;
  }
  
{link_color}
{link_hover}
  
  .hopfeed_div {
    margin: 0 0 0 -20px;
    padding: 0;
    float: left;
    clear:both;
  }

</style>
EOT;

  $border_size = get_option('cbcash_ad_border_size');
  $border_style = strtolower(get_option('cbcash_ad_border_style'));
  $border_color = get_option('cbcash_ad_border_color');
  $lpad = get_option('cbcash_ad_lpad');
  $lpad_widget = get_option('cbcash_ad_widget_lpad');

  $ad_box_width = get_option('cbcash_ad_width');

  if($ad_box_width > 0) {
    $ad_width = '    width: ' . $ad_box_width . 'px;';
  }

  if($width > 0) {
    $ad_width = '    width: ' . $width . 'px;';
  }

  if($border_size > 0) {
    $border = '    border: ' . $border_size . 'px '.$border_style.' #'.$border_color.' !important;';
  }

  if($lpad > 0) {
    $ad_lpad = '    padding-left: ' . $lpad . 'px ;';
  }
  if($is_widget)
	$ad_lpad = '    padding-left: ' . $lpad_widget . 'px !important;';

  $font = get_option('cbcash_ad_font');
  $font_size = get_option('cbcash_ad_font_size');
  $font_color = get_option('cbcash_ad_font_color');
  
  if($font && $font != 'Theme Font') {
    $family = '    font-family: ' . $font . ';';
  }

  if($font_color) {
    $family .= "\r\n" . '    color: #' . strtoupper($font_color) . ' !important;';
  }
  
  if($font_size && $font_size != 'Theme Size') {
    $family .= "\r\n" . '    font-size: ' . $font_size . 'px;';
  }

  $bgcolor = get_option('cbcash_ad_bg_color');
  
  if($is_widget)
	$bgcolor = get_option('cbcash_ad_widget_bg_color')?get_option('cbcash_ad_widget_bg_color'):0;
  
  if($bgcolor) {
    $bg = '    background-color: #' . strtoupper($bgcolor) . ' !important;';
  }

  $lcolor = get_option('cbcash_ad_link_color');
  $hcolor = get_option('cbcash_ad_link_hover');

  if($lcolor) {
    $link_color = '.'.$cssshort.' a:link, .'.$cssshort.' a:active, .'.$cssshort.' a:visited { color: #'.strtoupper($lcolor).' !important; }';
  }

  if($hcolor) {
    $link_hover = '.'.$cssshort.' a:hover { color: #'.strtoupper($hcolor).' !important; }';
  }

  $code = str_replace(
            array('{border}', '{family}', '{background}', '{ad_width}', '{ad_lpad}', '{link_color}', '{link_hover}'),
            array($border, trim($family), $bg, $ad_width, $ad_lpad, $link_color, $link_hover),
            $code
          );

  return $code;
}

function cbcashlinks_build_regex_string($keys, $preg=false){
	if(strlen(trim($keys)) == 0)return;
	$keys = split(',', $keys);	
	array_walk($keys, create_function('&$a','$a = trim($a);'));
	if(!is_array($keys))
		return;
	  $w_str = '(';
	foreach($keys as $key):
	if(!$preg)		
		$w_str .=  ($key . '|');
	else
		$w_str .=  (preg_quote($key) . '|');
		
	endforeach;
   if(!$preg)
		$w_str = mysql_real_escape_string( trim($w_str, '|') . ')' );
	else
		$w_str = trim($w_str, '|') . ')';
	
 return $w_str;
	
}

function cbcashlinks_get_replacement_texts($replace_texts){
	  //replacement texts
  $r_texts = split("\n", $replace_texts);  
  foreach($r_texts as $key=>$val){
	if(strlen(trim($val)) < 1 )
		unset($r_texts[$key]);
  }
  array_walk($r_texts, create_function('&$a','$a = trim($a);'));  
  if(stripos($replace_texts, "\n") === false && trim(strlen($replace_texts)) != 0 )
   $r_texts = array($replace_texts);
   
   return $r_texts;

}

function cbcashlinks_ad_html($keywords, $tid, $links = null, $ignore_title = false,	
$cssshort = 'cbcashads', $min_gravity=0, $min_referral=0, $xclude_des = 0, 
$xclude_keywords, $replace_keywords, $replace_texts, $cmp_recurr=false) {
  global $wpdb;
  $cblinks_data_table = $wpdb->prefix . 'cblinks_feed_data';
  
  $adtitle = '';
  $cb_aff_base = "http://%s.%s.hop.clickbank.net";
  $aff_id = trim(get_option('cbcash_nickname'));
  $vendor = '';
  $cb_aff_base = sprintf("http://%s.%s.hop.clickbank.net/?tid=%s", $aff, $vendor, $tid);

  
  $link_number = ($links) ? $links : get_option('cbcash_ad_number') ;
  
  $r_texts = cbcashlinks_get_replacement_texts($replace_texts); 
 

  
  $w_str = cbcashlinks_build_regex_string($keywords);
  $x_str = cbcashlinks_build_regex_string($xclude_keywords);
  $r_str = cbcashlinks_build_regex_string($replace_keywords,true);
 
  if(!$r_str){
	  
	$r_str = cbcashlinks_build_regex_string(get_option('cb_replace_keywords'));
	//var_dump($r_str);
	$r_texts = cbcashlinks_get_replacement_texts(get_option('cb_replace_texts')); 
	
}
  
   //making query string
   $query = "select * from $cblinks_data_table ";
  
  if( $w_str ){
		if($xclude_des){
			$query .= "where Description REGEXP \"$w_str\" ";
			if($x_str)
				$query .="and Title NOT REGEXP \"$x_str\" and Description NOT REGEXP \"$x_str\" ";
			if($cmp_recurr)
				$query .="and HasRecurringProducts='true' ";
		}else{
			$query .= "where (Title REGEXP \"$w_str\" or Description REGEXP \"$w_str\") ";
			if($x_str)
				$query .="and Title NOT REGEXP \"$x_str\" and Description NOT REGEXP \"$x_str\" ";
			if($cmp_recurr)
				$query .="and HasRecurringProducts='true' ";
		}
		$query .= "and Gravity>$min_gravity and Referred>$min_referral ";
	}else{
		$query .= "where Gravity>$min_gravity and Referred>$min_referral ";
		if($x_str)
			$query .="and Title NOT REGEXP \"$x_str\" and Description NOT REGEXP \"$x_str\" ";
		if($cmp_recurr)
				$query .="and HasRecurringProducts='true' ";		
	}
	
  $order = "Rand()";
 $query .= "order by $order ";
 $query .= "limit $link_number ";
 
 //var_dump($query);
 
 $results = $wpdb->get_results($query);
/*
 var_dump($results);
 exit;
*/
 
 $adText = '';
 foreach($results as $res){
	 $des = $res->Description;
	if(get_option('cbcash_seo'))
	 $vendor = urlencode(base64_encode($res->Id));
	else
	 $vendor = $res->Id;
	$link = sprintf("http://%s.%s.hop.clickbank.net/?tid=%s",$aff_id, $vendor, $tid) ;
	
	if($r_str){				
		if(preg_match("?{$r_str}?i", $des)){		
			$des = str_replace('%title%', $res->Title, $r_texts[array_rand($r_texts)]);			
		}
	}
	
	$adText .= "<div style='margin:5px 0'><a href=\"$link\" target='_blank'>{$res->Title}</a><br/>{$des}</div>";
	
 }
 
 

  $code = <<<EOT
  <div class="$cssshort">
    {title}
    
    {adtext}
    


    <script type="text/javascript">
    /*
    <!--
    hopfeed_affiliate = '{nickname}';
    hopfeed_affiliate_tid = '{tid}';
    hopfeed_fill_slots = true;
    hopfeed_rows = {ads};
    hopfeed_cols = 1;
    hopfeed_keywords = '{keywords}';
    hopfeed_width = '{ad_width}';
    hopfeed_type = 'LIST';
    hopfeed_path = 'http://www.hopfeed.com';
    -->
    */
    </script>
<!--
    <script type="text/javascript" src="http://www.hopfeed.com/script/hopfeed.js"></script>
-->
  </div>
EOT;

  if(trim(get_option('cbcash_ad_title')) != '' && ! $ignore_title) {
    $adtitle = '<div style="text-align: left; font-weight: bold; margin: 0 0 15px 0; clear:both;">'.get_option('cbcash_ad_title').'</div>';
  }

/*
  $code = str_replace(
            array('{nickname}', '{tid}', '{ads}', '{keywords}', '{ad_width}', '{title}'),
            array(get_option('cbcash_nickname'), $tid,
            (($links) ? $links : get_option('cbcash_ad_number')),
            $keywords, get_option('cbcash_ad_width'), $adtitle),
            $code
          );
*/

  $code = str_replace(
            array('{adtext}',  '{ad_width}', '{title}'),
            array( $adText, get_option('cbcash_ad_width'), $adtitle),
            $code
          );


  return $code;
}

function cbcashlinks_load_widget() {
  register_widget('CBCashlinks_Widget');
}

function cbcashlinks_options() {
  return array(
    'nickname' => 'YourNick',
    'seo' => true,
    'ad_title' => 'Sponsored By',
    'ad_number' => '3',
    'ad_font' => 'Arial',
    'ad_font_size' => 10,
    'ad_font_color' => '000000',
    'ad_bg_color' => 'ffffff',
    'ad_widget_bg_color' => 'ffffff',
    'ad_link_color' => '000000',
    'ad_link_hover' => '000000',
    'ad_border_size' => '1',
    'ad_border_style' => 'Dotted',
    'ad_border_color' => 'cccccc',
    'ad_width' => '300',
    'ad_lpad' => '10',
    'ad_widget_lpad'=> '0',
    'ad_pages' => false,
    'ad_homepage' => false,
    'ad_spages' => array(),
    'ad_short' => true

  );
}

class CBCashlinks_Widget extends WP_Widget {
  function CBCashlinks_Widget() {
    $widget_ops = array('classname' => 'cbcashlinks', 'description' => 'CB Cashlinks Ads Widget', 'cbcashlinks');
    $this->WP_Widget('cbcashlinks', 'CB Cashlinks Ads', $widget_ops);
  }

  function widget($args, $instance) {
    extract($args);

    $wid_title    = $instance['title'];
    $wid_trackid  = $instance['trackid'];
    $wid_keywords = $instance['keywords'];
    $wid_links    = $instance['links'];
    $wid_width    = $instance['width'];
    $wid_gravity    = (int)$instance['gravity'];
    $wid_referral  = (int)$instance['referral'];
    $wid_xclude    = (int)$instance['xclude'];
    $cmp_recurr    = (int)$instance['cmp_recurr'];
    $wid_xclude_keywords = $instance['xclude_keywords'];
    $wid_replace_keywords = $instance['replace_keywords'];
    $wid_replace_texts = $instance['replace_texts'];

    echo $before_widget;

    if($wid_title) {
      echo $before_title . $wid_title . $after_title;
    }

    echo cbcashlinks_get_widget_ads($wid_trackid, $wid_keywords, $wid_links,
    $wid_width,$this->id,$wid_gravity, $wid_referral, $wid_xclude, 
    $wid_xclude_keywords, $wid_replace_keywords, $wid_replace_texts, $cmp_recurr);
    echo $after_widget;    
  }

  function update($new_instance, $old_instance) {
  	$instance = $old_instance;

    $instance['title']    = $new_instance['title'];
  	$instance['trackid']  = $new_instance['trackid'];
  	$instance['keywords'] = $new_instance['keywords'];
  	$instance['links']    = (int)$new_instance['links'];
  	$instance['width']    = (int)$new_instance['width'];
  	$instance['gravity']    = (int)$new_instance['gravity'];
  	$instance['referral']    = (int)$new_instance['referral'];
  	$instance['xclude']    = (int)$new_instance['xclude'];
  	$instance['cmp_recurr']    = (int)$new_instance['cmp_recurr'];
  	$instance['xclude_keywords'] = trim($new_instance['xclude_keywords'], ', ') ;
  	$instance['replace_keywords'] = trim($new_instance['replace_keywords'], ', ') ;
  	$instance['replace_texts'] = trim($new_instance['replace_texts'], ', ') ;
	
    return $instance;
  }

  function form($instance) {
    $wid_title    = $instance['title'];
    $wid_trackid  = $instance['trackid'];
    $wid_keywords = $instance['keywords'];
    $wid_links    = $instance['links'];
    $wid_width    = $instance['width'];
    $wid_gravity   = (int)$instance['gravity'];
    $wid_referral  = (int)$instance['referral'];
    $wid_xclude    = (int)$instance['xclude'];
    $cmp_recurr    = (int)$instance['cmp_recurr'];
    $wid_xclude_keywords = $instance['xclude_keywords'];
    $wid_replace_keywords = $instance['replace_keywords'];
    $wid_replace_texts = $instance['replace_texts'];

    ?>
        <p>
          <label for="<?php echo $this->get_field_id('title'); ?>">
            <?php _e('Title:'); ?><br />
            <input type="text" value="<?php echo $wid_title; ?>" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" style="width: 225px;" />
          </label>
        </p>
        
        <p>
          <label for="<?php echo $this->get_field_id('trackid'); ?>">
            <?php _e('Tracking ID:'); ?><br />
            <input type="text" value="<?php echo $wid_trackid; ?>" id="<?php echo $this->get_field_id('trackid'); ?>" name="<?php echo $this->get_field_name('trackid'); ?>" style="width: 225px;" />
          </label>
        </p>

        <p>
          <label for="<?php echo $this->get_field_id('keywords'); ?>">
            <?php _e('Keywords:'); ?><br />
            <input type="text" value="<?php echo $wid_keywords; ?>" id="<?php echo $this->get_field_id('keywords'); ?>" name="<?php echo $this->get_field_name('keywords'); ?>" style="width: 225px;" />
          </label>
        </p>
        
        <p>
          <label for="<?php echo $this->get_field_id('xclude_keywords'); ?>">
            <?php _e('Exclude if has Keywords:'); ?><br />
            <input type="text" value="<?php echo $wid_xclude_keywords; ?>" id="<?php echo $this->get_field_id('xclude_keywords'); ?>" name="<?php echo $this->get_field_name('xclude_keywords'); ?>" style="width: 225px;" />
          </label>
        </p>
        <p>
			
          <label for="<?php echo $this->get_field_id('replace_keywords'); ?>">
            <?php _e('Replace if has Keywords:'); ?><br />
            <input type="text" value="<?php echo $wid_replace_keywords; ?>" id="<?php echo $this->get_field_id('replace_keywords'); ?>" name="<?php echo $this->get_field_name('replace_keywords'); ?>" style="width: 225px;" />
          </label>
        </p>
          <label for="<?php echo $this->get_field_id('replace_texts'); ?>">
            <?php _e('Replacement Texts(separate by newline): Available Tag: %title%'); ?><br />
            <textarea rows=5 cols=40  id="<?php echo $this->get_field_id('replace_texts'); ?>" name="<?php echo $this->get_field_name('replace_texts'); ?>" style="width: 225px;"><?php echo $wid_replace_texts; ?></textarea>
          </label>
        </p>
        
        <p>
          <label for="<?php echo $this->get_field_id('gravity'); ?>">
            <?php _e('Minimum gravity:(Integer value)'); ?><br />
            <input type="text" value="<?php echo $wid_gravity; ?>" id="<?php echo $this->get_field_id('gravity'); ?>" name="<?php echo $this->get_field_name('gravity'); ?>" style="width: 225px;" />
          </label>
        </p>
        
        <p>
          <label for="<?php echo $this->get_field_id('referral'); ?>">
            <?php _e('minimum % referral:(Integer value)'); ?><br />
            <input type="text" value="<?php echo $wid_referral; ?>" id="<?php echo $this->get_field_id('referral'); ?>" name="<?php echo $this->get_field_name('referral'); ?>" style="width: 225px;" />
          </label>
        </p>
        <p>
          <label for="<?php echo $this->get_field_id('xclude'); ?>">
            <?php _e('Exclude Products if not in description'); ?><br />
            <input type="checkbox" value="1" <?php if($wid_xclude): ?> checked="checked"<?php endif;?>  id="<?php echo $this->get_field_id('xclude'); ?>" name="<?php echo $this->get_field_name('xclude'); ?>" style="width: 15px;" />
          </label>
        </p>
        
        <p>
          <label for="<?php echo $this->get_field_id('cmp_recurr'); ?>">
            <?php _e('Include products only with recurring comission'); ?><br />
            <input type="checkbox" value="1" <?php if($cmp_recurr): ?> checked="checked"<?php endif;?>  id="<?php echo $this->get_field_id('cmp_recurr'); ?>" name="<?php echo $this->get_field_name('cmp_recurr'); ?>" style="width: 15px;" />
          </label>
        </p>

        <p>
          <label for="<?php echo $this->get_field_id('links'); ?>">
            <?php _e('Number of Links:'); ?><br />
            <input type="text" value="<?php echo $wid_links; ?>" id="<?php echo $this->get_field_id('links'); ?>" name="<?php echo $this->get_field_name('links'); ?>" style="width: 35px;" />
          </label>
        </p>

        <p>
          <label for="<?php echo $this->get_field_id('width'); ?>">
            <?php _e('Ad Box Width:'); ?><br />
            <input type="text" value="<?php echo $wid_width; ?>" id="<?php echo $this->get_field_id('width'); ?>" name="<?php echo $this->get_field_name('width'); ?>" style="width: 35px;" /> px
          </label>
        </p>

    <?php
  }
}

function cbcashlinks_shortcode($atts) {
	extract( shortcode_atts( array(
		'camp' => null
	), $atts ));

  $content = null;
  $smp = cbcashlinks_pick_campaign(strtolower($camp));

  if(is_array($smp) && ! empty($smp)) {
    // Ad HTML/CSS Code
    $ad     = cbcashlinks_ad_html($smp['keywords'], $smp['tid'], null,
     false, 'short', $smp['cmpgravity'], $smp['cmpreferral'], 
     $smp['xclude_des'], $smp['xclude_keywords'], $smp['replace_keywords'], $smp['replace_texts'], $smp['cmp_recurr'] );
     
    $ad_css = cbcashlinks_ad_css($smp['cmpwidth'], 'short');

    if(get_option('cbcash_seo')) {
      $ad_seo = cbcashlinks_ad_seo();
    } else {
      $ad_seo = '';
    }

    $content = $ad . "\r\n" . $content;
    $content = $ad_css . "\r\n" . $content . "\r\n" . $ad_seo;
  }

  return $content;
}

function cbcashlinks_api_check($transient) {
  if( empty( $transient->checked ) )
      return $transient;

  $plugin_slug = plugin_basename( __FILE__ );
  

  $args = array(
      'action' => 'update-check',
      'plugin_name' => $plugin_slug,
      'version' => $transient->checked[$plugin_slug],
  );

  $response = cbcashlinks_api_request( $args );

  if( false !== $response ) {
      $transient->response[$plugin_slug] = $response;
  }

  return $transient;
}

function cbcashlinks_api_request($args) {
  $request = wp_remote_post('http://localhost/w/api/index.php', array( 'body' => $args ) );

var_dump( $request['body']);
exit;



  if( is_wp_error( $request )
  or
  wp_remote_retrieve_response_code( $request ) != 200
  ) {
      return false;
  }

  $response = unserialize( wp_remote_retrieve_body( $request ) );
  if( is_object( $response ) ) {
      return $response;
  } else {
      // Unexpected response
      return false;
  }
}

function cbcashlinks_api_info($false, $action, $args) {
  $plugin_slug = plugin_basename( __FILE__ );

  if( $args->slug != $plugin_slug ) {
      return false;
  }

  $args = array(
      'action' => 'plugin_information',
      'plugin_name' => $plugin_slug,
      'version' => $transient->checked[$plugin_slug],
  );

  $response = cbcashlinks_api_request( $args );
  $request = wp_remote_post('http://localhost/w/api/index.php', array( 'body' => $args ) );

  return $response;
}

function cbcashlinks_load_data(){
	ignore_user_abort(true);
	set_time_limit (60*30);
	
	global $wpdb;
	$doing_cron = get_option('doing_cb_cron');
	if( ($doing_cron && ($doing_cron+120) < time()) || $doing_cron >time() ){
		delete_option('doing_cb_cron');
	}elseif($doing_cron)
		return;
	else
	 update_option('doing_cb_cron', time());
	

	
	$t_ini = time();
/*
	if(defined('DOING_CRON'))
		return;
*/
	//ini_set('memory_limit', '32M');


	$cblinks_data_table = $wpdb->prefix . 'cblinks_feed_data';
	$cblinks_data_table_temp = $wpdb->prefix . 'cblinks_feed_data_temp';
	$cblinks_data_table_zap = $wpdb->prefix . 'cblinks_feed_data_zap';
	
	$uploads = wp_upload_dir();
	$this_dir = $uploads['basedir'] . DIRECTORY_SEPARATOR ;
	
	$link = 'http://www.clickbank.com/feeds/marketplace_feed_v2.xml.zip';
	$newfilename = $this_dir . 'cb.zip';
	
	$out = fopen($newfilename, 'wb'); 
    if ($out == FALSE){ 
     update_option('cbcashlinks_error_msg_file', 'File creation failed');
    } 
    
    //getting zip file

    $data = wp_remote_get($link, array('timeout'=>300));
    if($data['response']['code'] == 200){
		fwrite($out, $data['body']);
		fclose($out);
	}else{
		delete_option('doing_cb_cron');
	exit("Couldn't get the data from remote server");
	}




	
	if(file_exists($newfilename))
	$zip = new ZipArchive;

	if ($zip->open($newfilename) === TRUE) {
	$zip->extractTo($this_dir);
	$zip->close();

	}else
		delete_option('doing_cb_cron');
	

//performing db operations
   


	
	//truncating table
	$wpdb->query("SET FOREIGN_KEY_CHECKS = 0;");
	$wpdb->query("CREATE TABLE $cblinks_data_table_temp LIKE $cblinks_data_table;");
	$wpdb->query("ALTER TABLE $cblinks_data_table RENAME $cblinks_data_table_zap;");
	$wpdb->query("ALTER TABLE $cblinks_data_table_temp RENAME $cblinks_data_table;");
	$wpdb->query("DROP TABLE $cblinks_data_table_zap;");
	$wpdb->query("SET FOREIGN_KEY_CHECKS = 1;");
	
	//In case
	$wpdb->query("Delete  FROM $cblinks_data_table WHERE 1");
	
	
	//Analyzing and inserting data	
	$xml_file = $this_dir . 'marketplace_feed_v2.xml';

	$xml = new XMLReader();
	if(!$xml->open($xml_file))
		return;
   
  
  $tags_total = array('Id', 'PopularityRank','Title','Description', 
  'HasRecurringProducts', 'Gravity', 'PercentPerSale',
   'PercentPerRebill','AverageEarningsPerSale','InitialEarningsPerSale',
   'TotalRebillAmt', 'Referred' , 'Commission','ActivateDate');
   
  $tags = array('Id', 'PopularityRank','Title','Description', 'HasRecurringProducts','Gravity','Referred');
   $string_tags = array('Id', 'Title', 'Description', 'HasRecurringProducts');
   $id_array=array();
   //$wpdb->show_errors();
/*
   $con = mysql_connect('localhost', 'root', '11235813');
   mysql_select_db('w');
*/
   
   
	//using xmlreader
    while($xml->read()){		
		if($xml->name == 'Site' && $xml->nodeType == XMLREADER::ELEMENT){		 
			$x++;
			$node = $xml->expand();
			$dom = new DomDocument();
			$dom->formatOutput = true;
			$n = $dom->importNode($node, true);
			$dom->appendChild($n);
			$query = "INSERT IGNORE INTO  $cblinks_data_table values (";
			
			foreach($tags as $tag){					
				$$tag = $dom->getElementsByTagName($tag)->item(0)->nodeValue;
				
				if(in_array($tag, $string_tags))
					$query .=  "\"" . mysql_real_escape_string($$tag) . '", ';
				else
					$query .=  $$tag . ', ';
			}
			$query = trim($query,', ') . ')';
			$wpdb->query($query);		

				              
						
		}

    }
    delete_option('doing_cb_cron');
	update_option('cb_cron_last_done', current_time('mysql'));
	
}

function cbcashlinks_check_if_db(){
	//if(current_user_can('activate_plugins') && isset($_GET['cbdbupdate'])){
	if(isset($_GET['cbdbupdate'])){
	cbcashlinks_load_data();
	exit("Database Updated Successfully");
}
	}
function cbcashlinks_db_ajax(){
	
	if( isset( $_POST['id']) && $_POST['id'] == 'ini'){
		$url = admin_url('edit-tags.php?cbdbupdate=true');
		$num = cbcashlinks_get_num();
		wp_remote_get($url, array('timeout'=>0.01, 'blocking' => false ));
		
		echo $num;
		exit();
		
	}elseif($_POST['id'] == 'check'){
		if(!get_option('doing_cb_cron'))
			exit('done');
		$init = (int) $_POST['init_val'];
		
		
		$num = cbcashlinks_get_num();
		
		$per = floor( (($num/$init)*100));
		if($per == 100)
			exit("0");
		echo $per;
		exit();
	}
	
}

function cbcashlinks_get_num(){
		global $wpdb;
		$cblinks_data_table = $wpdb->prefix . 'cblinks_feed_data';
		$num = (int) $wpdb->get_var("select count(*) from $cblinks_data_table");
		return $num;
	}
	
function cbcashlinks_return_results(){
	header("Content-type: application/json");
	echo json_encode( array (
		'tot' => cbcashlinks_get_num(),
		'last' => get_option('cb_cron_last_done')
	));
	exit;
	
}

function cbcashlinks_redirect_to_add(){
	$uri = $_SERVER['REQUEST_URI'];	
	$nick = trim(get_option('cbcash_nickname'));
	if(!get_option('permalink_structure') && isset($_GET['r']) && isset($_GET['vendor'])){		
		if($nick != '' && isset($_GET['r']) && $_GET['r']) {
		$link = sprintf("http://%s.%s.hop.clickbank.net/?tid=%s", $nick, $_GET['vendor'], $_GET['r']);
		header("Location: $link");
		exit;
	} else {
  header('Location: /');
	}

	}
		

if(stripos($uri, '/recommends/') === false)
		return;
	preg_match_all('~/recommends/(.*)?/(.*)~', $uri, $matches);
	$vendor = base64_decode(urldecode( $matches[1][0]));
	$tid = $matches[2][0];
	
	$tid = trim($tid, '/');


if($nick != '') {
	$link = sprintf("http://%s.%s.hop.clickbank.net/?tid=%s", $nick, $vendor, $tid);	
  header("Location: $link");
  exit;

} else {
  header('Location: /');

	
	}
}

add_action('init', 'cbcashlinks_check_if_db');
add_action('plugins_loaded', 'cbcashlinks_redirect_to_add', -5);

add_action('admin_menu', 'add_cbcashlinks_panel');
add_action('cbcashlinks_cron_hook', 'cbcashlinks_load_data');
add_action('wp_print_scripts', 'add_cbcashlinks_scripts');
add_action('widgets_init', 'cbcashlinks_load_widget');
add_filter('the_content', 'cbcashlinks_show_ad');
add_action('wp_ajax_db_progress', 'cbcashlinks_db_ajax');
add_action('wp_ajax_db_update_done', 'cbcashlinks_return_results');

add_filter('pre_set_site_transient_update_plugins', 'cbcashlinks_check_plugin');

//add_filter('site_transient_update_plugins', 'cbcashlinks_api_check');

//add_filter('plugins_api', 'cbcashlinks_api_info', 10, 3);
add_filter('plugins_api_result', 'wptuts_api_info', 10, 3);

add_shortcode('cbcash', 'cbcashlinks_shortcode');


//add_action('site_transient_update_plugins', 'wptuts_activate_au');  

function wptuts_api_info($res, $action, $args){
	$slug = plugin_basename( __FILE__ );
	if($args->slug == $slug){
	$info = new stdClass(); 
	$info->tested = '3.5.1';  
	return $info;
}
return $res;
	
	}

function cbcashlinks_check_plugin($transient)  
{  
	$slug = plugin_basename( __FILE__ );
	if(isset($transient->response[$slug]))
		return $transient;
	
	$options = array(
	'timeout' => ( ( defined('DOING_CRON') && DOING_CRON ) ? 30 : 3),
	'body' => array('action' => 'get_version')
	);

	$upgrade_link = 'http://www.cbcashlinks.com/api/upgrade.php';
	//$upgrade_link = 'http://localhost/w/api/upgrade.php';
	$raw_response = wp_remote_post($upgrade_link, $options);
	
	if ( is_wp_error( $raw_response ) || 200 != wp_remote_retrieve_response_code( $raw_response ) )
		return $transient;
	$obj = (Object)maybe_unserialize($raw_response['body']);
	$obj->slug = plugin_basename( __FILE__ ); 
	

	
	
/*
	$obj = new stdClass();  
      $obj->slug = plugin_basename( __FILE__ ); 
      $obj->plugin_name = 'CB Cashlinks';  
      $obj->new_version = '2.3';  
      $obj->requires = '3.0';  
      $obj->tested = '3.5.1';  
      $obj->downloaded = 12540;  
      $obj->last_updated = '2012-01-12';
      $obj->upgrade_notice = 'New Featurese Added. It\'s a must to edit the plugin';  

      //$obj->download_link = 'http://localhost/update.php';  
      $obj->package = 'http://localhost/update.php';   
*/
   
	
	$plugins = get_plugins();
	
	
	foreach ( $plugins as $file => $p ) {
		if($file == $slug)
			if($p['Version'] !== $obj->new_version){
				
				$transient->response[$slug] = $obj; 
				return $transient;
			}

	}




	
	return $transient;

}

//plugin upgrade hook
function cbcashlinks_update_db_check() {
    global $cb_cashlinks_plugin_version;
    if ( (float)get_option('cb_cashlinks_plugin_version') < $cb_cashlinks_plugin_version) {
        cbcashlinks_install();
        cbcashlinks_load_data();
        update_option('cb_cashlinks_plugin_version', $cb_cashlinks_plugin_version);
    }
}
add_action('plugins_loaded', 'cbcashlinks_update_db_check');



?>
