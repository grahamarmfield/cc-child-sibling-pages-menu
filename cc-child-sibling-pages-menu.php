<?php
/*
Plugin Name: Child and Sibling Pages from Menu
Author: Graham Armfield
Author URI: http://www.coolfields.co.uk
Description: Lists child and sibling pages as appropriate, but uses information from Custom Menu. Used as a widget.
Version:      0.4

*/
class cc_child_sibling_pages_menu extends WP_Widget {
   public function __construct() {
		parent::__construct(
	 		'cc_child_sibling_pages_menu', // Base ID
			'Child Sibling Pages from Menu', // Name
			array( 'description' => __( 'v0.1 Show secondary navigation. Shows parent page, sibling pages and child pages. Uses information from a chosen custom menu.', 'text_domain' ), ) // Args
		);
   }

   ///////////////////////////////////////////////////////////

   function widget($args, $instance) {
      //echo 'v0.2';
      // This function outputs the widget content
      // Only process on pages or front page
      if (!(is_page() or is_front_page() or is_page_template())) return;

   	// Check that custom menus present or supported
      $locations = get_nav_menu_locations();
      if (empty($locations)) return;
      
      // Check that user has set a custom menu
      if (empty( $instance['nav_menu'] )) return;
      
      // It's set so retrieve menu object into $menuObj
   	$menuObj = wp_get_nav_menu_object( $instance['nav_menu'] );
         
      // Check something in that object and bail if not
      if (!$menuObj) return;
		

      // OK so now we've got to do some processing
      extract( $args ); // Extract any arguments

		$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? '' : $instance['title'], $instance, $this->id_base );


      // Initialise strings to hold query responses
      $siblings = '';
      $children = '';
      
      $curPageMenuId = '';
      $curPageMenuTitle = '';
      $curPageParentMenuId = '';
      $curPageParentMenuTitle = '';

      //Initialise arrays
      $parentDetails = array();
      $siblingDetails = array();
      $childDetails = array();

      // Retrieve values from stored instance
      $showChildren = $instance['showchildren'];
      $showSiblings = $instance['showsiblings'];
      $showMe = $instance['showme'];
      $showParent = $instance['showparent'];
      $hdrLevel = $instance['hlev'];
      
      // Get the individual items from the menu object into an array
   	$menu_items = wp_get_nav_menu_items($menuObj->term_id);
      //echo '<pre>';
      //print_r($menu_items);
      //echo '</pre>';
      

   	// First loop round array to pull out children of current page
      foreach ( (array) $menu_items as $key => $menu_item ) {
          $menuId = $menu_item->ID;
          $menuTitle = $menu_item->title;
          $objId = $menu_item->object_id;
          $parentId = $menu_item->menu_item_parent; // Item's parent
          
          //Diagnostics
          //echo '<p>Title='.$menu_item->title.'<br>(MenuID = '.$menuId.' - MenuParent = '.$parentId.')';
   
   	    // Check if we're on this page
          if ($objId == get_the_ID() ) {
            $curPageMenuId = $menuId; // Store the menu ID for this page
            $curPageMenuTitle = $menuTitle; // Store the menu ID for this page
            
            // So any other links with the same parent are my siblings
            // Note: this variable is used in the second loop
            $curPageParentMenuId = $parentId; 
          } 
          
          // Current page's children will
          // be just after it if there are any. So check if post_parent = stored ID
         if ($showChildren and ($curPageMenuId == $parentId)) {
            // We've got a child page so store details
            $childDetail = array (
               'menutitle' => $menu_item->title,
               'url' => $menu_item->url,
               'menuid' => $menuId,
               'menuparentid' => $parentId
            );
            
            // Give the child detail to the full result set
            $childDetails[] = $childDetail; 
         }
          
      } // End foreach
      
      // Now run through and get any  parent and siblings - including this page
   	foreach ( (array) $menu_items as $key => $menu_item ) {
          
          // Check for parent page of current page
          if ($curPageParentMenuId == $menu_item->ID) {
            
            // Store the title, we may need it later
            $curPageParentMenuTitle = $menu_item->title;
            
            // If I'm showing parent links store details
            if ($showParent) {
               $parentDetail = array (
                  'menutitle' => $menu_item->title,
                  'url' => $menu_item->url,
                  'menuid' => $menu_item->ID,
                  'menuparentid' => $menu_item->menu_item_parent,
               );
               $parentDetails[] = $parentDetail; // Give details to the full result set
             }
          }
          // Check for current page first
          if (!$showMe and (get_the_ID() == $menu_item->object_id) ) {
            // Nothing to do here
          } else {
            // Check for sibling of current page
            // Exclude where $curPageParentMenuId = 0 ie top-level page
             if (($curPageParentMenuId != 0) and ($curPageParentMenuId == $menu_item->menu_item_parent)) {
               
               // Collect sibling details
               $siblingDetail = array (
                  'menutitle' => $menu_item->title,
                  'url' => $menu_item->url,
                  'menuid' => $menu_item->ID,
                  'menuparentid' => $menu_item->menu_item_parent,
               );
               
               // Give details to the full result set
               $siblingDetails[] = $siblingDetail; 
             } // End of check for sibling pages that excludes top-level pages.
          }
      }


      // Replace any tokens in the headers
      $parentHdr = _cc_child_sibling_pages_menu_tokens($instance['parenthdr'], $curPageMenuTitle, $curPageParentMenuTitle);
      $siblingHdr = _cc_child_sibling_pages_menu_tokens($instance['siblinghdr'], $curPageMenuTitle, $curPageParentMenuTitle);
      $childrenHdr = _cc_child_sibling_pages_menu_tokens($instance['childrenhdr'], $curPageMenuTitle, $curPageParentMenuTitle);
      
      // ready to output - so initialise output string
         $strHtml = '';
      
      // Check we're required to show parent, and that there is anything
      if ($showParent and (!empty($parentDetails))) {
         $strHtml .= '<div class="cc-cs-parent">';
         // Retrieve instance section header if present
         if (!empty($parentHdr)) {
            $strHtml .= '<h'.$hdrLevel.' class="widget-title">'.$parentHdr.'</h'.$hdrLevel.'>';
         }
         $strHtml .= '<ul class="menu">'; // start list
         // Loop round the array and pull out details
         foreach($parentDetails as $parentDetail) {
            $strHtml .= '<li class="menu-item menu-item-type-post_type menu-item-'.$parentDetail['menuid'].'">';            
            $strHtml .= '<a href="'.$parentDetail['url'].'">'.$parentDetail['menutitle'].'</a></li>';
         }
         $strHtml .= '</ul></div>';
      }
      
      // Check we're required to show siblings, and that there is anything
      
      if ($showSiblings and (!empty($siblingDetails))) {
         $strHtml .= '<div class="cc-cs-siblings">';

         if (!empty($siblingHdr)) {
            $strHtml .= '<h'.$hdrLevel.' class="widget-title">'.$siblingHdr.'</h'.$hdrLevel.'>';
         }
         $strHtml .= '<ul class="menu">'; // start list
         // Loop round the projects array and pull out details
         foreach($siblingDetails as $sibling) {
            // Check if current menu item
            if ($sibling['menuid'] == $curPageMenuId) {
               $strFrag = ' current-menu-item';
            } else {
               $strFrag = '';
            }
            $strHtml .= '<li class="menu-item menu-item-type-post_type menu-item-'.$sibling['menuid'].$strFrag.'">';
            $strHtml .= '<a href="'.$sibling['url'].'">'.$sibling['menutitle'].'</a></li>';
         }
         $strHtml .= '</ul></div>';
      }
      if ($showChildren and (!empty($childDetails))) {
         $strHtml .= '<div class="cc-cs-children">';
         if (!empty($childrenHdr)) {
            $strHtml .= '<h'.$hdrLevel.' class="widget-title">'.$childrenHdr.'</h'.$hdrLevel.'>';
         }
         $strHtml .= '<ul class="menu">'; // start list
         // Loop round the child details array and pull out details
         foreach($childDetails as $child) {
            $strHtml .= '<li class="menu-item menu-item-type-post_type menu-item-'.$child['menuid'].'">';
            $strHtml .= '<a href="'.$child['url'].'">'.$child['menutitle'].'</a></li>';
         }
         $strHtml .= '</ul></div>';
      }
      
      // Finalise output if there is any
      if (!empty($strHtml)) {
         echo $before_widget; 
		   if ( $title ) echo $before_title . $title . $after_title;

         //echo $before_title.$instance['title'].$after_title;
         echo $strHtml;
         echo $after_widget;
      }
   ////      
   }

   ///////////////////////////////////////////////////////////
   function update( $new_instance, $old_instance ) {
       // Process and save the widget options
		$instance = $old_instance;
		$new_instance = wp_parse_args( 
         (array) $new_instance, 
         array( 
            'nav_menu' => '',
            'title' => '',
            'showparent' => 0,
            'showsiblings' => 0,
            'showchildren' => 0,
            'showme' => 0,
            'hlev' => '3',
            'parenthdr' => '',
            'siblinghdr' => '',
            'childrenhdr' => '',
         ) 
      );
		$instance['nav_menu'] = (int) $new_instance['nav_menu'];
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['parenthdr'] = strip_tags($new_instance['parenthdr']);
		$instance['siblinghdr'] = strip_tags($new_instance['siblinghdr']);
		$instance['childrenhdr'] = strip_tags($new_instance['childrenhdr']);
		
      // Handle the ones from checkboxes
      $instance['showparent'] = $new_instance['showparent'] ? 1 : 0;
		$instance['showsiblings'] = $new_instance['showsiblings'] ? 1 : 0;
		$instance['showchildren'] = $new_instance['showchildren'] ? 1 : 0;
		$instance['showme'] = $new_instance['showme'] ? 1 : 0;

      // Handle the ones from dropdown boxes
      if (in_array($new_instance['hlev'], array( '2','3','4','5','6' ))) {
			$instance['hlev'] = $new_instance['hlev'];
		} else {
			$instance['hlev'] = '3';
		}

		return $instance;
	}


   
   /////////////////////////////////////////////////////////////

   function form($instance) {
       // Retrieve options
       $title = esc_attr($instance['title']);
       $parentHdr = esc_attr($instance['parenthdr']);
       $siblingHdr = esc_attr($instance['siblinghdr']);
       $childrenHdr = esc_attr($instance['childrenhdr']);

       
       // Present menu location options as dropdown
		$nav_menu = isset( $instance['nav_menu'] ) ? $instance['nav_menu'] : '';
		// Get menus
		$menus = get_terms( 'nav_menu', array( 'hide_empty' => false ) );

		// If no menus exists, direct the user to go and create some.
		if ( !$menus ) {
			echo '<p>'. sprintf( __('No menus have been created yet. <a href="%s">Create some</a>.'), admin_url('nav-menus.php') ) .'</p>';
			return;
		}

    ?>

		<p>
			<label for="<?php echo $this->get_field_id('nav_menu'); ?>"><?php _e('Select Menu:'); ?></label>
			<select id="<?php echo $this->get_field_id('nav_menu'); ?>" name="<?php echo $this->get_field_name('nav_menu'); ?>">
		<?php
			foreach ( $menus as $menu ) {
				echo '<option value="' . $menu->term_id . '"'
					. selected( $nav_menu, $menu->term_id, false )
					. '>'. $menu->name . '</option>';
			}
		?>
			</select>
		</p>
    
    
    
      <p>
      <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Widget title:'); ?></label>
      <input class="widefat" id="<?php echo $this->
          get_field_id('title'); ?>" name="<?php echo $this->
          get_field_name('title'); ?>" type="text" value="<?php 
          echo $title; ?>" />
      </p>
      <p>
		<input class="checkbox" type="checkbox" <?php checked($instance['showparent'], true) ?> id="<?php echo $this->get_field_id('showparent'); ?>" name="<?php echo $this->get_field_name('showparent'); ?>" />
		<label for="<?php echo $this->get_field_id('showparent'); ?>"><?php _e('Show parent page'); ?></label>
      <br />
		<input class="checkbox" type="checkbox" <?php checked($instance['showsiblings'], true) ?> id="<?php echo $this->get_field_id('showsiblings'); ?>" name="<?php echo $this->get_field_name('showsiblings'); ?>" />
		<label for="<?php echo $this->get_field_id('showsiblings'); ?>"><?php _e('Show sibling pages'); ?></label>
      <br />
		<input class="checkbox" type="checkbox" <?php checked($instance['showchildren'], true) ?> id="<?php echo $this->get_field_id('showchildren'); ?>" name="<?php echo $this->get_field_name('showchildren'); ?>" />
		<label for="<?php echo $this->get_field_id('showchildren'); ?>"><?php _e('Show child pages'); ?></label>
      <br />
		<input class="checkbox" type="checkbox" <?php checked($instance['showme'], true) ?> id="<?php echo $this->get_field_id('showme'); ?>" name="<?php echo $this->get_field_name('showme'); ?>" />
		<label for="<?php echo $this->get_field_id('showme'); ?>"><?php _e('Show current page in sibling list'); ?></label>
      </p>
		<p>
		<label for="<?php echo $this->get_field_id('hlev'); ?>"><?php _e( 'Header Levels:' ); ?></label>
		<select name="<?php echo $this->get_field_name('hlev'); ?>" id="<?php echo $this->get_field_id('hlev'); ?>" class="widefat">
			<option value="2"<?php selected( $instance['hlev'], '2' ); ?>>2</option>
			<option value="3"<?php selected( $instance['hlev'], '3' ); ?>>3</option>
			<option value="4"<?php selected( $instance['hlev'], '4' ); ?>>4</option>
			<option value="5"<?php selected( $instance['hlev'], '5' ); ?>>5</option>
			<option value="6"<?php selected( $instance['hlev'], '6' ); ?>>6</option>
		</select>
		</p>
      <p><strong>Headings and Tokens: </strong><br>
      Use the follwing text boxes to add optional headers for each section of the widget output.</p>
      <p>To allow for greater flexibility two tokens are allowed in these boxes - %title% and %parenttitle%. They are replaced with the appropriate page titles.</p>
      <p>
      <label for="<?php echo $this->get_field_id('parenthdr'); ?>">Parent heading:</label>
      <input class="widefat" id="<?php echo $this->
          get_field_id('parenthdr'); ?>" name="<?php echo $this->
          get_field_name('parenthdr'); ?>" type="text" value="<?php 
          echo $parentHdr; ?>" />

      </p>
      <p>
      <label for="<?php echo $this->get_field_id('siblinghdr'); ?>">Siblings heading:</label>
      <input class="widefat" id="<?php echo $this->
          get_field_id('siblinghdr'); ?>" name="<?php echo $this->
          get_field_name('siblinghdr'); ?>" type="text" value="<?php 
          echo $siblingHdr; ?>" />

      </p>
      <p>
      <label for="<?php echo $this->get_field_id('childrenhdr'); ?>">Children heading:</label>
      <input class="widefat" id="<?php echo $this->
          get_field_id('childrenhdr'); ?>" name="<?php echo $this->
          get_field_name('childrenhdr'); ?>" type="text" value="<?php 
          echo $childrenHdr; ?>" />

      </p>
    <?php 
    }
} /* End of class */


add_action('widgets_init', 
  create_function('', 'return register_widget("cc_child_sibling_pages_menu");'));


function _cc_child_sibling_pages_menu_tokens($str, $curPageMenuTitle, $curPageParentMenuTitle) {
///////////////////////////////////////////////////////////////////////////
/* 
   Search for valid tokens in string and replace with a page title.
   Valid tokens are:
      %title% - returns menu title of current page
      %parenttitle% - returns menu title of this page's parent page 
                     (according to chosen menu)
*/
///////////////////////////////////////////////////////////////////////////////

   // %title% 
   $str = str_replace("%title%", $curPageMenuTitle, $str);
   
   // %parenttitle% 
   $str = str_replace("%parenttitle%", $curPageParentMenuTitle, $str);
   return $str;
}

/* EOF */