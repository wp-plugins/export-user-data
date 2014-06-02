<?php

/*
Plugin Name: Export User Data
Plugin URI: http://qstudio.us/plugins/
Description: Export User data, metadata and BuddyPressX Profile data.
Version: 0.9.4
Author: Q Studio
Author URI: http://qstudio.us
License: GPL2
Text Domain: export-user-data
*/

// quick check :) ##
defined( 'ABSPATH' ) OR exit;

/* Check for Class */
if ( ! class_exists( 'Q_Export_User_Data' ) ) 
{
    
    // plugin version
    define( 'Q_EXPORT_USER_DATA_VERSION', '0.9.4' ); // version ##
    
    // instatiate class via hook, only if inside admin
    if ( is_admin() ) {
    
        // instatiate plugin via WP hook - not too early, not too late ##
        add_action( 'init', array ( 'Q_Export_User_Data', 'get_instance' ), 0 ); 

    }
    
    
    /**
     * Main plugin class
     *
     * @since 0.1
     **/
    class Q_Export_User_Data {
        
        
        // Refers to a single instance of this class. ##
        private static $instance = null;
                
        /* properties */
        public $text_domain = 'export-user-data'; // for translation ##
        #public $q_eud_options = array(); // export settings ##
        
        
        /**
         * Creates or returns an instance of this class.
         *
         * @return  Foo     A single instance of this class.
         */
        public static function get_instance() {

            if ( null == self::$instance ) {
                self::$instance = new self;
            }

            return self::$instance;

        }
        
        
        /**
         * Class contructor
         *
         * @since 0.1
         **/
        private function __construct() 
        {

            if (is_admin() ) {
                
                add_action( 'init', array( $this, 'load_plugin_textdomain' ) );  
                add_action( 'admin_menu', array( $this, 'add_admin_pages' ) );
                add_action( 'init', array( $this, 'generate_data' ) );
                add_filter( 'q_eud_exclude_data', array( $this, 'exclude_data' ) );
                add_action( 'admin_enqueue_scripts', array( $this, 'add_css_and_js' ), 1 );
                add_action( 'admin_footer', array( $this, 'jquery' ), 100000 );
                add_action( 'admin_footer', array( $this, 'css' ), 100000 );

            }

        }
        
        
        /**
         * Load plugin text-domain
         * 
         * @since       0.9.0
         * @return      void
         **/
        public function load_plugin_textdomain() 
        {
        
            load_plugin_textdomain( $this->text_domain, false, basename( dirname( __FILE__ ) ) . '/languages' );
            
        }

        /**
         * Add administration menus
         *
         * @since 0.1
         **/
        public function add_admin_pages() 
        {

            add_users_page( __( 'Export User Data', $this->text_domain ), __( 'Export User Data', $this->text_domain ), 'list_users', $this->text_domain, array( $this, 'users_page' ) );

        }


        /**
         * style and interaction 
         */
        public function add_css_and_js( $hook ) 
        {

            // load the scripts on only the plugin admin page 
            if (isset( $_GET['page'] ) && ( $_GET['page'] == $this->text_domain ) ) {

                wp_register_style('q_eud_multi_select_css', plugins_url('css/multi-select.css',__FILE__ ));
                wp_enqueue_style('q_eud_multi_select_css');
                wp_enqueue_script('q_eud_multi_select_js', plugins_url('js/jquery.multi-select.js',__FILE__ ), array('jquery'), '0.9.8', false );

            } 

        }


        /**
         * clean that stuff up
         */
        public function sanitize( $value ) 
        {

            $value = str_replace("\r", '', $value);
            $value = str_replace("\n", '', $value);
            $value = str_replace("\t", '', $value);
            return $value;

        }

        
        /**
         * Check for a retrieve stored user options
         * 
         * @since       0.9.3
         * @return      Mixed   false on error OR array of saved options
         */
        public function get_user_options()
        {
            
            // check if the user wants to, or has previously saved the export field list ##
            $user_ID = get_current_user_id();
            
            // no user - go return false ##
            if ( ! $user_ID ) { return false; }
            
            // return an empty array, if nothing found ##
            return get_option( 'q_eud_options_'.$user_ID, array() );
            
        }
        
        
        /**
         * method to store user options
         * 
         * @since       0.9.3
         * @return      Boolean
         */
        public function set_user_options( $value = null )
        {
            
            // nothing cooking ##
            if ( is_null( $value ) ) {
                
                return false;
                
            }
            
            // add value to property ##
            $this->q_eud_options[] = $value;
            
        }
        
        
        /**
         * method to save / update / delete user options
         * 
         * @since       0.9.3
         * @return      Boolean
         */
        public function manage_user_options( $action = null )
        {
            
            // nothing cooking ##
            if ( is_null( $action ) ) {
                
                return false;
                
            }
            
            // check if the user wants to, or has previously saved the export field list ##
            $user_ID = get_current_user_id();
            
            // no user - go return false ##
            if ( ! $user_ID ) { return false; }
            
            // what action to take ##
            switch ( $action ) {
                
                case ( 'save' ):
                    
                    update_option( 'q_eud_options_'.$user_ID, $this->q_eud_options );
                    
                break;
                
            }
            
        }

        
        
        /**
         * Process content of CSV file
         *
         * @since 0.1
         **/
        public function generate_data() 
        {
            
            #if ( ! current_user_can( 'list_users' ) ) {
                
                #wp_die( __( 'You do not have sufficient permissions to access this page.', $this->text_domain ) );
                
            #}
            
            if ( ! isset( $_POST['_wpnonce-q-eud-export-user-page_export'] ) ) { 
                
                return false;

            }
            
            // check admin referer ##
            check_admin_referer( 'q-eud-export-user-page_export', '_wpnonce-q-eud-export-user-page_export' );
            
            // check for a retrieve user's stored options ##
            #$q_eud_options = $this->get_user_options();
            
            // build argument array ##
            $args = array(
                #'fields'    => 'all_with_meta',
                'fields'    => 'all', // reduce initial data load and call up required fields inside the loop ##
                'role'      => sanitize_text_field( $_POST['role'] )
            );

            // did they request a specific program ? ##
            if ( isset( $_POST['program'] ) && $_POST['program'] != '' ) {

                $args['meta_key'] = 'member_of_club';
                $args['meta_value'] = (int)$_POST['program'];
                $args['meta_compare'] = '=';

            }

            // is there a range limit in place for the export ? ##
            if ( isset( $_POST['limit_offset'] ) && $_POST['limit_offset'] != '' && isset( $_POST['limit_total'] ) && $_POST['limit_total'] != '' ) {
                
                // let's just make sure they are integer values ##
                $limit_offset = (int)$_POST['limit_offset'];
                $limit_total = (int)$_POST['limit_total'];
                
                if ( is_int( $limit_offset ) && is_int( $limit_total ) ) {
                
                    $args['offset'] = $limit_offset;
                    $args['number'] = $limit_total - $limit_offset;

                    #wp_die(pr($args));
                
                }
                
            }
            
            /* pre_user query */
            add_action( 'pre_user_query', array( $this, 'pre_user_query' ) );
            $users = get_users( $args );
            remove_action( 'pre_user_query', array( $this, 'pre_user_query' ) );

            /* no users found, so chuck an error into the args array and exit the export */
            if ( ! $users ) {

                $referer = add_query_arg( 'error', 'empty', wp_get_referer() );
                wp_redirect( $referer );
                exit;

            }
            
            /* get sitename and clean it up */
            $sitename = sanitize_key( get_bloginfo( 'name' ) );
            if ( ! empty( $sitename ) ) {
                $sitename .= '.';
            }

            // export method ? ##
            $export_method = 'excel'; // default to Excel export ##
            if ( isset( $_POST['format'] ) && $_POST['format'] != '' ) {

                $export_method = sanitize_text_field( $_POST['format'] );

            }

            // set export filename structure ##
            $filename = $sitename . 'users.' . date( 'Y-m-d-H-i-s' );

            switch ( $export_method ) {

                case "csv":

                    // to csv ##
                    header( 'Content-Description: File Transfer' );
                    header( 'Content-Disposition: attachment; filename='.$filename.'.csv' );
                    header( 'Content-Type: text/csv; charset=' . get_option( 'blog_charset' ), true );

                    // set a csv check flag
                    $is_csv = true;

                    // nothing here
                    $doc_begin  = '';

                    //preformat
                    $pre        = '';

                    // how to seperate data ##
                    $seperator = ','; // comma for csv ##

                    // line break ##
                    $breaker = "\n";

                    // nothing here
                    $doc_end  = '';

                    break;

                case ('excel'):

                    // to xls ##
                    header( 'Content-Description: File Transfer' );
                    header("Content-Type: application/vnd.ms-excel");
                    header("Content-Disposition: attachment; filename=$filename.xls");  
                    header("Pragma: no-cache"); 
                    header("Expires: 0");

                    // set a csv check flag
                    $is_csv = false;

                    //grab the template file (for tidy formatting)
                    include( 'xml-template.php' );

                    // open xml
                    $doc_begin  = $xml_doc_begin;

                    //preformat
                    $pre        = $xml_pre;

                    // how to seperate data ##
                    $seperator  = $xml_seperator;

                    // line break ##
                    $breaker    = $xml_breaker;

                    // close xml
                    $doc_end    = $xml_doc_end;

                    break;

            }


            // function to exclude data ## 
            $exclude_data = apply_filters( 'q_eud_exclude_data', array() );

            // check for selected usermeta fields ##
            $usermeta = isset( $_POST['usermeta'] ) ? $_POST['usermeta']: '';
            $usermeta_fields = array();
           
            if ( $usermeta && is_array( $usermeta ) ) {
                foreach( $usermeta as $field ) {
                    $usermeta_fields[] = sanitize_text_field ( $field  );
                }
            }
            
            #pr($usermeta_fields);
            #exit;

            // check for selected x profile fields ##
            $bp_fields = isset( $_POST['bp_fields'] ) ? $_POST['bp_fields'] : '';
            $bp_fields_passed = array();
            if ( $bp_fields && is_array($bp_fields) ) {

                foreach( $bp_fields as $field ) {

                    // reverse tidy ##
                    $field = str_replace( '__', ' ', sanitize_text_field ( $field ) );

                    // add to array ##
                    $bp_fields_passed[] = $field;

                }

            }

            // cwjordan: check for x profile fields we want update time for ##
            $bp_fields_update = isset( $_POST['bp_fields_update_time'] ) ? $_POST['bp_fields_update_time'] : '';
            $bp_fields_update_passed = array();
            if ( $bp_fields_update && is_array( $bp_fields_update ) ) {

                foreach( $bp_fields_update as $field ) {

                    // reverse tidy ##
                    $field = str_replace( '__', ' ', sanitize_text_field ( $field ) );

                    // add to array ##
                    $bp_fields_update_passed[] = $field . " Update Date";

                }

            }

            // global wpdb object ##
            global $wpdb;

            // exportable user data ##
            $data_keys = array(
                    'ID'
                ,   'user_login'
                #,  'user_pass'
                ,   'user_nicename'
                ,   'user_email'
                ,   'user_url'
                ,   'user_registered'
                #,  'user_activation_key'
                #,  'user_status',
                ,   'display_name'
            );

            // compile final fields list ##
            $fields = array_merge( $data_keys, $usermeta_fields, $bp_fields_passed, $bp_fields_update_passed );

            // should we include the user's role in the export ##
            /*
            if ( isset( $_POST['q_eud_role'] ) && $_POST['q_eud_role'] != '' ) {
                
                // save this to stored options ( 'group', 'field', 'value' )##
                $this->set_user_options( array( 'role' => array ( 'q_eud_role' => array ( 'yes' ) ) ) );
                
                // save options ##
                $this->manage_user_options( 'save' );
                
                // add "Role" to the fields array ##
                $fields[]["role"] = _e( 'Role', $this->text_domain );
                
            }
            */
            
            // build the document headers ##
            $headers = array();

            foreach ( $fields as $key => $field ) {

                // rename programs field ##
                if ( $field == 'member_of_club' ){
                    $field = 'Program';
                }

                if ( in_array( $field, $exclude_data ) ) {

                    unset( $fields[$key] );

                } else {

                    if ( $is_csv ) {

                        $headers[] = '"' . $field . '"';

                    } else {

                        $headers[] = $field;
                        #echo '<script>console.log("Echoing header cell: '.$field.'")</script>';

                    }

                }

            }

            //open doc wrapper..
            echo $doc_begin;

            // echo headers ##
            echo $pre . implode( $seperator, $headers ) . $breaker;

            // build row values for each user ##
            foreach ( $users as $user ) {

                $data = array();

                // BP loaded ? ##
                if ( function_exists ('bp_is_active') ) {
                    $bp_data = BP_XProfile_ProfileData::get_all_for_user($user->ID);
                }

                foreach ( $fields as $field ) {

                    // check if this is a BP field ##
                    if ( isset( $bp_data ) && isset( $bp_data[$field] ) && in_array( $field, $bp_fields_passed ) ) {

                        $value = $bp_data[$field];

                        if ( is_array( $value ) ) {
                            $value = $value['field_data'];
                        }
                        $value = $this->sanitize($value);

                    // check if this is a BP field we want the updated date for ##
                    } elseif ( in_array( $field, $bp_fields_update_passed ) ) {

                        global $bp;

                        $real_field = str_replace(" Update Date", "", $field);
                        $field_id = xprofile_get_field_id_from_name( $real_field );
                        $value = $wpdb->get_var ($wpdb->prepare( "SELECT last_updated FROM {$bp->profile->table_name_data} WHERE user_id = %d AND field_id = %d", $user->ID, $field_id ) );

                    // include the user's role in the export ##
                    } elseif ( isset( $_POST['q_eud_role'] ) && $_POST['q_eud_role'] != '' && $field == 'role' ){
                        
                        // add "Role" as $value ##
                        $value = $user->roles[0] ? $user->roles[0] : '' ; // empty value if no role found ##
                        
                    // user data or usermeta ##
                    } else { 

                        $value = isset( $user->{$field} ) ? $user->{$field} : '';
                        $value = is_array( $value ) ? serialize( $value ) : $value; // maybe serialize the value ##

                    }

                    // correct program value to Program Name ##
                    if ( $field == 'member_of_club' ) {
                        $value = get_the_title($value);
                    }

                    if ( $is_csv ) {
                        $data[] = '"' . str_replace( '"', '""', $value ) . '"';
                    } else {
                        $data[] = $value;
                    }

                }

                // echo row data ##
                echo $pre . implode( $seperator, $data ) . $breaker;

            }

            // close doc wrapper..            
            echo $doc_end;

            // stop PHP, so file can export correctly ##
            exit;


        }

        
        /**
         * Content of the settings page
         *
         * @since 0.1
         **/
        public function users_page() 
        {

            if ( ! current_user_can( 'list_users' ) ) {
                wp_die( __( 'You do not have sufficient permissions to access this page.', $this->text_domain ) );
            }
            
            // check for a retrieve user's stroed options ##
            #$q_eud_options = $this->get_user_options();
            
?>
        <div class="wrap">
        <h2><?php _e( 'Export User Data', $this->text_domain ); ?></h2>
<?php

        // nothing happening? ##
        if ( isset( $_GET['error'] ) ) {
            echo '<div class="updated"><p><strong>' . __( 'No users found.', $this->text_domain ) . '</strong></p></div>';
        }

?>
        <form method="post" action="" enctype="multipart/form-data">
            <?php wp_nonce_field( 'q-eud-export-user-page_export', '_wpnonce-q-eud-export-user-page_export' ); ?>
            <table class="form-table">
<?php

                // allow admin to select user meta fields to export ##
                global $wpdb;
                $meta_keys = $wpdb->get_results( "SELECT distinct(meta_key) FROM $wpdb->usermeta" );

                // get meta_key value from object ##
                $meta_keys = wp_list_pluck( $meta_keys, 'meta_key' );

                // let's note some of them odd keys ##
                $meta_keys_system = array(
                    'metaboxhidden',
                    'activation',
                    'bp_',
                    'nav_',
                    'wp_',
                    'admin_color',
                    'wpmudev',
                    'screen_',
                    'show_',
                    'rich_',
                    'reward_',
                    'meta-box',
                    'manageedit',
                    'edit_',
                    'closedpostboxes_',
                    'dismissed_',
                    'manage',
                    'comment',
                    'current',
                    'incentive_',
                    '_wdp',
                    'ssl',
                    'wdfb',
                    'users_per_page',
                );

                // allow array to be filtered ##
                $meta_keys_system = apply_filters( 'export_user_data_meta_keys_system', $meta_keys_system );

                // test array ##
                #echo '<pre>'; var_dump($meta_keys); echo '</pre>'; 

                // check if we got anything ? ##
                if ( $meta_keys ) {

?>
                <tr valign="top">
                    <th scope="row">
                        <label for="q_eud_usermeta"><?php _e( 'User Meta Fields', $this->text_domain ); ?></label>
                        <p class="filter" style="margin: 10px 0 0;">
                            <?php _e('Filter', $this->text_domain); ?>: <a href="#" class="usermeta-all"><?php _e('All', $this->text_domain); ?></a> | <a href="#" class="usermeta-common"><?php _e('Common', $this->text_domain); ?></a>
                        </p>
                    </th>
                    <td>
                        <select multiple="multiple" id="usermeta" name="usermeta[]">
<?php

                            foreach ( $meta_keys as $key ) {

                                #echo "\n\t<option value='" . esc_attr( $role ) . "'>$name</option>";

                                // display $key ##
                                $display_key = $key;

                                // rename programs field ##
                                if ( $display_key == 'member_of_club' ){
                                    $display_key = 'program';
                                }

                                // tidy ##
                                $display_key = str_replace( "_", " ", ucwords( $display_key ) );

                                #echo "<label for='".esc_attr( $key )."' title='".esc_attr( $key )."'><input id='".esc_attr( $key )."' type='checkbox' name='usermeta[]' value='".esc_attr( $key )."'/> $display_key</label><br />";

                                // class ##
                                $usermeta_class = 'normal';

                                foreach ( $meta_keys_system as $drop ) {

                                    if ( strpos( $key, $drop ) !== false ) {

                                        #echo 'FOUND -- '.$drop.' in '.$key.'<br />';
                                        // https://wordpress.org/support/topic/bugfix-numbers-in-export-headers?replies=1
                                        // removed $key = assignment, as not required ##
                                        if ( ( array_search( $key, $meta_keys ) ) !== false ) {

                                            $usermeta_class = 'system';

                                        }

                                    }

                                }

                                // print key ##
                                echo "<option value='".esc_attr( $key )."' title='".esc_attr( $key )."' class='".$usermeta_class."'>$display_key</option>";

                            }
?>
                        </select>
                        <p class="description"><?php 
                            printf( 
                                __( 'Select the user meta keys to export, use the filters to simplify the list.', $this->text_domain )
                            ); 
                        ?></p>
                    </td>
                </tr>
<?php

                } // meta_keys found ##

?>
<?php

                // buddypress x profile data ##
                if ( function_exists ('bp_is_active') ) {

                // grab all buddypress x profile fields ##
                $bp_fields = $wpdb->get_results( "SELECT distinct(name) FROM ".$wpdb->base_prefix."bp_xprofile_fields WHERE parent_id = 0" );

                // get name value from object ##
                $bp_fields = wp_list_pluck( $bp_fields, 'name' );

                // test array ##
                #echo '<pre>'; var_dump($bp_fields); echo '</pre>'; 

                // allow array to be filtered ##
                $bp_fields = apply_filters( 'export_user_data_bp_fields', $bp_fields );

?>
                <tr valign="top">
                    <th scope="row"><label for="q_eud_xprofile"><?php _e( 'BP xProfile Fields', $this->text_domain ); ?></label></th>
                    <td>
                        <select multiple="multiple" id="bp_fields" name="bp_fields[]">
<?php

                        foreach ( $bp_fields as $key ) {

                            // tidy up key ##
                            $key_tidy = str_replace( ' ', '__', ($key));

                            #echo "<label for='".esc_attr( $key_tidy )."'><input id='".esc_attr( $key_tidy )."' type='checkbox' name='bp_fields[]' value='".esc_attr( $key_tidy )."'/> $key</label><br />";

                            // print key ##
                            echo "<option value='".esc_attr( $key )."' title='".esc_attr( $key )."'>$key</option>";

                        }

?>
                        </select>
                        <p class="description"><?php 
                            printf( 
                                __( 'Select the BuddyPress XProfile keys to export', $this->text_domain )
                            ); 
                        ?></p>
                    </td>
                </tr>
<?php

                // allow export of update times ##

?>
                <tr valign="top" class="toggleable">
                    <th scope="row">
                        <label for="q_eud_xprofile"><?php _e( 'BP xProfile Fields Update Time', $this->text_domain ); ?></label>
                    </th>
                    <td>
                        <select multiple="multiple" id="bp_fields_update_time" name="bp_fields_update_time[]">
<?php

                        foreach ( $bp_fields as $key ) {

                            echo "<option value='".esc_attr( $key )."' title='".esc_attr( $key )."'>$key</option>";

                        }

?>
                        </select>
                        <p class="description"><?php 
                            printf( 
                                __( 'Select the BuddyPress XProfile keys updated dates to export', $this->text_domain )
                            ); 
                        ?></p>
                    </td>
                </tr>
<?php		

                } // BP installed and active ##

?>
                <tr valign="top" class="toggleable">
                    <th scope="row"><label for="q_eud_users_role"><?php _e( 'Role', $this->text_domain ); ?></label></th>
                    <td>
                        <select name="role" id="q_eud_users_role">
<?php

                            echo '<option value="">' . __( 'All Roles', $this->text_domain ) . '</option>';
                            global $wp_roles;
                            foreach ( $wp_roles->role_names as $role => $name ) {
                                echo "\n\t<option value='" . esc_attr( $role ) . "'>$name</option>";
                            }

?>
                        </select>
                        <p class="description"><?php 
                            printf( 
                                __( 'Filter the exported users by a WordPress Role. <a href="%s" target="_blank">%s</a>', $this->text_domain )
                                ,   esc_html('http://codex.wordpress.org/Roles_and_Capabilities')
                                ,   'Codex'
                            ); 
                        ?></p>
                    </td>
                </tr>
<?php

                // clubs ? ##
                if ( post_type_exists( 'club' ) ) {

?>
                <tr valign="top" class="toggleable">
                    <th scope="row"><label for="q_eud_users_program"><?php _e( 'Programs', $this->text_domain ); ?></label></th>
                    <td>
                        <select name="program" id="q_eud_users_program">
<?php

                            echo '<option value="">' . __( 'All Programs', $this->text_domain ) . '</option>';

                            $clubs_array = get_posts(array( 'post_type'=> 'club', 'posts_per_page' => -1 )); // grab all posts of type "club" ##

                            foreach ( $clubs_array as $c ) { // loop over all clubs ## 

                                #$clubs[$c->ID] = $c; // grab club ID ##
                                echo "\n\t<option value='" . esc_attr( $c->ID ) . "'>$c->post_title</option>";

                            }

?>
                        </select>
                    </td>
                </tr>
<?php

                } // clubs ##

?>
                <tr valign="top" class="toggleable">
                    <th scope="row"><label><?php _e( 'Registered', $this->text_domain ); ?></label></th>
                    <td>
                        <select name="start_date" id="q_eud_users_start_date">
                            <option value="0"><?php _e( 'Start Date', $this->text_domain ); ?></option>
                            <?php $this->export_date_options(); ?>
                        </select>
                        <select name="end_date" id="q_eud_users_end_date">
                            <option value="0"><?php _e( 'End Date', $this->text_domain ); ?></option>
                            <?php $this->export_date_options(); ?>
                        </select>
                        <p class="description"><?php 
                            printf( 
                                __( 'Pick a start and end user registration date to limit the results.', $this->text_domain )
                            ); 
                        ?></p>
                    </td>
                </tr>
                
                <tr valign="top" class="toggleable">
                    <th scope="row"><label><?php _e( 'Limit Range', $this->text_domain ); ?></label></th>
                    <td>
                        <input name="limit_offset" type="text" id="q_eud_users_limit_offset" value="" class="regular-text code numeric" style="width: 136px;" placeholder="<?php _e( 'Offset', $this->text_domain ); ?>">
                        <input name="limit_total" type="text" id="q_eud_users_limit_total" value="" class="regular-text code numeric" style="width: 136px;" placeholder="<?php _e( 'Total', $this->text_domain ); ?>">
                        <p class="description"><?php 
                            printf( 
                                __( 'Enter an offset start number and a total number of users to export. <a href="%s" target="_blank">%s</a>', $this->text_domain )
                                ,   esc_html('http://codex.wordpress.org/Function_Reference/get_users#Parameters')
                                ,   'Codex'
                            ); 
                        ?></p>
                    </td>
                </tr>

                <tr valign="top" class="hidden">
                    <th scope="row"><label for="q_eud_role"><?php _e( 'Include Role', $this->text_domain ); ?></label></th>
                    <td>
                        <input id='q_eud_role' type='checkbox' name='q_eud_role' value=''/></label>
                        <p class="description"><?php 
                            printf( 
                                __( 'Checking this option will add a column that includes the user\'s role.', $this->text_domain )
                            ); 
                        ?></p>
                    </td>
                </tr>
                </tr>
                
                <tr valign="top" class="hidden">
                    <th scope="row">
                        <label for="q_eud_options"><?php _e( 'Remember Options', $this->text_domain ); ?>
                    </th>
                    <td>
                        <input id='q_eud_options' type='checkbox' name='q_eud_options' value=''/></label>
                        <p class="description"><?php 
                            printf( 
                                __( 'Checking this option will save and reload your selected options for future exports.', $this->text_domain )
                            ); 
                        ?></p>
                    </td>
                </tr>
                
                <tr valign="top">
                    <th scope="row"><label for="q_eud_users_format"><?php _e( 'Format', $this->text_domain ); ?></label></th>
                    <td>
                        <select name="format" id="q_eud_users_format">
<?php

                            echo '<option value="excel">' . __( 'Excel', $this->text_domain ) . '</option>';
                            echo '<option value="csv">' . __( 'CSV', $this->text_domain ) . '</option>';

?>
                        </select>
                        <p class="description"><?php 
                            printf( 
                                __( 'Select the format for the export file.', $this->text_domain )
                            ); 
                        ?></p>
                    </td>
                </tr>
                
                <tr valign="top">
                    <th scope="row">
                        <label for="q_eud_xprofile"><?php _e( 'Advanced Options', $this->text_domain ); ?></label>
                    </th>
                    <td>
                        <div class="toggle">
                            <a href="#"><?php _e( 'Show', $this->text_domain ); ?></a>
                        </div>
                    </td>
                </tr>
                
            </table>
            <p class="submit">
                <input type="hidden" name="_wp_http_referer" value="<?php echo $_SERVER['REQUEST_URI'] ?>" />
                <input type="submit" class="button-primary" value="<?php _e( 'Export', $this->text_domain ); ?>" />
            </p>
        </form>
        
<?php
        }

        
         /**
         * Inline jQuery
         * @since       0.8.2
         */
        public function jquery() 
        {

            // load the scripts on only the plugin admin page 
            if (isset( $_GET['page'] ) && ( $_GET['page'] == $this->text_domain ) ) {
?>
        <script>
            
        // lazy load in some jQuery validation ##
        jQuery(document).ready(function($) {

            // build super multiselect ##
            jQuery('#usermeta, #bp_fields, #bp_fields_update_time').multiSelect();

            // show only common ##
            jQuery('.usermeta-common').click(function(e){
                e.preventDefault();
                jQuery('#ms-usermeta .ms-selectable li.system').hide();
            });

            // show all ##
            jQuery('.usermeta-all').click(function(e){
                e.preventDefault();
                jQuery('#ms-usermeta .ms-selectable li').show();
            });

            // validate number inputs ##
            $("input.numeric").blur(function() {

                //console.log("you entered "+ $(this).val());

                if ( $(this).val() && ! $.isNumeric( $(this).val() ) ) {

                    //console.log("this IS NOT a number");
                    $(this).css({ 'background': 'red', 'color': 'white' }); // highlight error ##
                    $("p.submit .button-primary").attr('disabled','disabled'); // disable submit ##

                } else {

                    $(this).css({ 'background': 'white', 'color': '#333' }); // remove error highlighting ##
                    $("p.submit .button-primary").removeAttr('disabled'); // enable submit ##

                }

            });
            
            // toggle advanced options ##
            jQuery(".toggle a").click( function(e) {
                e.preventDefault();
                $toggleable = jQuery("tr.toggleable");
                $toggleable.toggle();
                if ( $toggleable.is(":visible") ) {
                    jQuery(this).text("<?php _e( 'Hide', $this->text_domain ); ?>");
                } else {
                    jQuery(this).text("<?php _e( 'Show', $this->text_domain ); ?>");
                }
            });

        });

        </script>
<?php
            }

        }
        
        
        /** 
         * Inline CSS
         * @since       0.8.2
         */
        public function css() 
        {

            // load the scripts on only the plugin admin page 
            if (isset( $_GET['page'] ) && ( $_GET['page'] == $this->text_domain ) ) {
?>
        <style>
            .toggleable { display: none; }
            .hidden { display: none; }
        </style>
<?php
            }

        }
        
        
        /**
         * Data to exclude from export
         */
        public function exclude_data() 
        {

            $exclude = array( 'user_pass', 'user_activation_key' );
            return $exclude;

        }


        /*
         * Pre User Query
         */
        public function pre_user_query( $user_search ) 
        {

            global $wpdb;

            $where = '';

            if ( ! empty( $_POST['start_date'] ) )
                $where .= $wpdb->prepare( " AND $wpdb->users.user_registered >= %s", date( 'Y-m-d', strtotime( sanitize_text_field ( $_POST['start_date'] ) ) ) );

            if ( ! empty( $_POST['end_date'] ) )
                $where .= $wpdb->prepare( " AND $wpdb->users.user_registered < %s", date( 'Y-m-d', strtotime( '+1 month', strtotime( sanitize_text_field ( $_POST['end_date'] ) ) ) ) );

            if ( ! empty( $where ) )
                $user_search->query_where = str_replace( 'WHERE 1=1', "WHERE 1=1 $where", $user_search->query_where );

            return $user_search;

        }


        /**
         * Export Date Options
         * 
         * @global type $wpdb
         * @global type $wp_locale
         * @return type
         */
        private function export_date_options() 
        {

            global $wpdb, $wp_locale;

            $months = $wpdb->get_results( "
                SELECT DISTINCT YEAR( user_registered ) AS year, MONTH( user_registered ) AS month
                FROM $wpdb->users
                ORDER BY user_registered DESC
            " );

            $month_count = count( $months );
            if ( !$month_count || ( 1 == $month_count && 0 == $months[0]->month ) )
                return;

            foreach ( $months as $date ) {
                if ( 0 == $date->year )
                    continue;

                $month = zeroise( $date->month, 2 );
                echo '<option value="' . $date->year . '-' . $month . '">' . $wp_locale->get_month( $month ) . ' ' . $date->year . '</option>';
            }

        }

    }

}