<?php
/*
Plugin Name: New Blog Templates: Exclude Custom Post Type
Description: Choose which post types should not be copied from the template
Version: 1.0.0
Author: 10up, Thaicloud
Author URI: http://knowmike.com/
*/

class MJ_Blog_Templates_Exclude_Post_Type {
	
	/**
	 * @var List of post_types not permitted to copy
	 */
	private $exclude_cpt_settings;

	/**
	 * @var Check if we are on settings page
	 */
	private $exclude_cpt_settings_check;
	
	/**
	 * Constructor 
	 */
	public function __construct() {
		
		// Add the super admin page
		add_action( 'network_admin_menu', array( $this, 'network_admin_page' ) );

		// Workaround for WP Settings API bug with multisite
		add_action( 'network_admin_edit_exclude-cpt-settings', array( $this, 'save_exclude_cpt_settings') );

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

		add_action( 'blog_templates-copying_table', array( $this, 'check_source_blog_pre_copy' ), 10, 2 );

		// Ajax - when template selected, show checkbox inputs for all CPT on that source blog
		add_action( 'wp_ajax_exclude_cpt_ajax_populate', array( $this, 'exclude_cpt_ajax_populate' ) );

		// Hook into blog template and skip copying of post types
		add_filter( 'blog_templates-process_row' , array( $this, 'skip_post_types' ), 11, 2 );

	}

   /**
    * Enqueue scripts for ajax handling / nonce
    *
    */
	function admin_enqueue_scripts( $hook ){		
		if ( $hook != $this->exclude_cpt_settings_check )
			return;
		wp_enqueue_script( 'blog-templates-exclude-cpt-ajax', plugin_dir_url( __FILE__  ). 'js/blog-templates-exclude-cpt.js', array( 'jquery' ) );
		wp_localize_script( 'blog-templates-exclude-cpt-ajax', 'bte_vars', array(
				'bte_nonce' => wp_create_nonce( 'bte_nonce' )
			) 
		);
	}
	
   /**
    * Adds the network admin menu item
    *
    */
    function network_admin_page() {
		$this->exclude_cpt_settings_check = add_submenu_page( 'settings.php', __( 'Exclude CPT', $this->localization_domain ), __( 'Exclude CPT', $this->localization_domain ), 'administrator', basename(__FILE__), array(&$this,'admin_options_page'));
    }

	/**
    * 	Display settings page
    *
    */
    function admin_options_page() {

    	global $pagenow;
		if ( $pagenow != 'settings.php' && $_GET['page'] != 'blog-templates-exclude-cpt.php') {
			return;
		}

    	echo '<div class="wrap">';
		echo '<h2>Exclude Custom Post Types</h2>';

		echo 'This plugin extends upon the incredible <a href="https://premium.wpmudev.org/project/new-blog-template/" target="_blank">New Blog Template</a>
		plugin by <a href="https://premium.wpmudev.org/" target="_blank">WPMU Dev</a>. ';

		echo '<form name="exclude_cpt_form" method="post" action="edit.php?action=exclude-cpt-settings">';

		// Get 'New Blog Template' plugin options
		$new_blog_templates = get_site_option('blog_templates_options');

		$templates = array();
		foreach ($new_blog_templates['templates'] as $key=>$template) {
			// Only list templates that are set to copy posts
			if ( in_array( 'posts', $template['to_copy'] ) ){
				$blog_id = $template['blog_id'];
				$templates[$blog_id] = $template['name'];
			}
        }

        if ( $templates != array() ) {
			echo "<p><select name='template-exclude-cpt-settings' id='template-exclude-cpt-settings'>";
        	echo "<option value=''>Please select a template</option>" ;
        	foreach ( $templates as $key=>$value ) {
        	    echo "<option value='$key'>$value</option>";
        	}
        	echo '</select> ';
        	echo '<img src="'.admin_url( '/images/wpspin_light.gif' ).'" class="waiting" id="bte-loading" style="display: none;">';
        	echo '</p>';
        }else{
        	echo '<p>Please ensure that you have created one or more blog templates (that are setup to copy posts) using the "New Blog Template" plugin.</p>';
        }
		?>
		<div id="exclude-cpt-ajax">

		</div>
		<?php
		echo '</form>';
		echo '</div>';
    }

	/**
    * 	Called via Ajax- using new source blog id, display all post types as checkboxes
    *
    */
	function exclude_cpt_ajax_populate( ){

		if( !isset( $_POST['bte_nonce'] ) || !wp_verify_nonce( $_POST['bte_nonce'], 'bte_nonce' ) )
			die( 'Permissions check failed' );

		$blog_id = $_POST['blog_id'];
		$settings = (array) get_site_option( 'exclude-cpt-settings-'.$blog_id );

		echo '<p>Please choose which post types you DO NOT wish to be included in the copy.</p>';		
		echo '<input type="hidden" name="bte-option-name" value="exclude-cpt-settings-'.$blog_id.'" >';

		global $switched;
   		switch_to_blog( $blog_id );
   		global  $wpdb;

   		$post_types = 'SELECT post_type
             FROM ' . $wpdb->posts . '                
             GROUP BY post_type';
        $post_types = $wpdb->get_results($post_types, ARRAY_A);

    	restore_current_blog();
    	echo '<table>';
    	foreach ( $post_types as $post_type ){
    		$post_type = $post_type['post_type'];
    		echo "<tr><td> $post_type </td><td> <input type='checkbox' id='exclude-cpt-settings-.$post_type' name='exclude-cpt-settings-$post_type' ". checked( $settings[$post_type], 'on', false ) ."></td></tr>";
    	}
    	echo '</table>';

    	submit_button( 'Save Changes' );

    	die();
	}

	/**
	 * Save options form on submission
	 *
	 */
	function save_exclude_cpt_settings( ){

		$allowed_post_types = array();
		foreach ( $_POST as $key => $val ){
			$count = 0;
			$post_type = str_replace("exclude-cpt-settings-", "", $key, $count);

			if ( $count == 1 ){
				$allowed_post_types[$post_type] = 'on';
			}
		}
		
		if ( count( $allowed_post_types ) != 0 ){
			$this->exclude_cpt_settings = $allowed_post_types;
			update_site_option( $_POST['bte-option-name'], $allowed_post_types );
		}

		wp_redirect(
    		add_query_arg(
       		 array( 'page' => 'blog-templates-exclude-cpt.php', 'updated' => 'true' ),
        	(is_multisite() ? network_admin_url( 'admin.php' ) : admin_url( 'admin.php' ))
 			)
		);
		exit;
	}

	/**
	 * Run just before copy process starts; check which template is running & set variable
	 *
	 */
	function check_source_blog_pre_copy( $table, $blog_id ){
		$this->exclude_cpt_settings = (array) get_site_option( 'exclude-cpt-settings-'.$blog_id );
	}

    /**
	 * The magic happens here. 
	 * Filter for 'New Blog Template' plugin allows us to allow/disallow copying at the last minute.
	 *
	 */
	function skip_post_types( $row, $table ){
		if ( isset( $row['post_type'] ) ) {
			if( array_key_exists( $row['post_type'] , $this->exclude_cpt_settings) ){
				return false;
			}
		}
		return true;
	}

}

$MJ_Blog_Templates_Exclude_Post_Type = new MJ_Blog_Templates_Exclude_Post_Type();

