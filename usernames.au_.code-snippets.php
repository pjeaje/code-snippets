<?php

/**
 * filter_authors_comments_list
 */
function wpse_filter_authors_comments_list( $clauses, $wp_comment_query ) {
    global $pagenow, $wpdb;

    // Ensure we are in the admin area and on the edit-comments.php page
    if ( is_admin() && $pagenow == 'edit-comments.php' && !current_user_can( 'administrator' ) && current_user_can( 'author' ) ) {
        // Get the current user's ID
        $user_id = get_current_user_id();

        // Modify the join and where clauses
        $clauses['join'] = "
            LEFT JOIN $wpdb->posts ON $wpdb->posts.ID = $wpdb->comments.comment_post_ID
        ";
        $clauses['where'] .= $wpdb->prepare( " AND (
            $wpdb->posts.post_author = %d OR
            $wpdb->comments.user_id = %d
        )", $user_id, $user_id );
    }

    return $clauses;
}

// Hook the function into 'comments_clauses'
add_filter( 'comments_clauses', 'wpse_filter_authors_comments_list', 10, 2 );

/**
 * restrict_comments_view_to_authors
 */
add_filter( 'comments_array', 'gp_restrict_comments_view_to_authors', 10, 2 );

function gp_restrict_comments_view_to_authors( $comments, $post_id ) {
    // If the user is viewing their own post, show all comments
    if ( user_can( get_current_user_id(), 'edit_post', $post_id ) ) {
        return $comments;
    }

    // Otherwise, return an empty array so no comments are shown
    return array();
}

/**
 * remove_reply_link
 */
function remove_reply_link( $link, $args, $comment, $post ) {
    return ''; // This will replace the reply link with an empty string.
}
add_filter( 'comment_reply_link', 'remove_reply_link', 10, 4 );

/**
 * message_sent
 */
// Start a session to store a flag when a comment is posted
function start_session_if_none() {
    if (!session_id()) {
        session_start();
    }
}
add_action('init', 'start_session_if_none');

// Set a session variable when a comment is posted
function set_comment_posted_flag($comment_ID, $comment_approved) {
    if ($comment_approved) {
        // Set the session variable
        $_SESSION['comment_posted'] = true;
    }
}
add_action('comment_post', 'set_comment_posted_flag', 10, 2);

// Add an action to check for the session variable and display the popup message
function display_comment_thank_you() {
    // Check if the session variable is set
    if (isset($_SESSION['comment_posted']) && $_SESSION['comment_posted']) {
        // Display a thank you message
        echo '<div class="comment-thank-you-popup" style="display: none; position: fixed; top: 20%; left: 50%; transform: translateX(-50%); z-index: 9999; padding: 40px; border:solid 5px red; background: #fccfcf; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); border-radius: 5px;">';
        echo '<p><strong>Thanks, your message has been sent.</strong></p>';
        echo '<button onclick="this.parentNode.style.display=\'none\';">Close</button>';
        echo '</div>';
        echo "<script>document.querySelector('.comment-thank-you-popup').style.display='block';</script>";

        // Unset the session variable so the message doesn't appear again
        unset($_SESSION['comment_posted']);
    }
}
add_action('wp_footer', 'display_comment_thank_you');

/**
 * limit_user_comments_per_post
 *
 * function limit_user_comments_per_post( $comment_post_ID ) {// Check if the commenter is a logged-in userif ( is_user_logged_in() ) {// Get the current user's ID$current_user_id = get_current_user_id();
 * // Get all comments for the current post made by the user$args = array('user_id' =&gt; $current_user_id,'post_id' =&gt; $comment_post_ID,'count' =&gt; true // We only need the count);$user_comments_count = get_comments( $args );
 * // If the user has already made 2 comments on this post, stop them from commenting againif ( $user_comments_count &gt;= 2 ) {wp_die( 'You have reached the limit of 2 messages for this person. &lt;a href="javascript:history.back()"&gt;Go Back&lt;/a&gt;' );}}}
 * // Add the function to the pre_comment_on_post actionadd_action( 'pre_comment_on_post', 'limit_user_comments_per_post' );
 */
function limit_user_comments_per_post( $comment_post_ID ) {
    // Check if the commenter is a logged-in user who is not an administrator
    if ( is_user_logged_in() && !current_user_can('administrator') ) {
        // Get the post's author ID
        $post_author_id = get_post_field( 'post_author', $comment_post_ID );
        
        // Check if the author of the post has the 'administrator' role
        $post_author = get_user_by('id', $post_author_id);
        if ( isset($post_author->roles) && in_array('administrator', (array) $post_author->roles) ) {
            // If the post author is an administrator, do not limit comments
            return;
        }

        // Get the current user's ID
        $current_user_id = get_current_user_id();

        // Get all comments for the current post made by the user
        $args = array(
            'user_id' => $current_user_id,
            'post_id' => $comment_post_ID,
            'count'   => true // We only need the count
        );
        $user_comments_count = get_comments( $args );

        // If the user has already made 2 comments on this post, stop them from commenting again
        if ( $user_comments_count >= 2 ) {
            wp_die( 'You have reached the limit of 2 messages on this post. Your messages will be deleted when they are 7 days old (after which you will be able to send another message). <a href="javascript:history.back()">Go Back</a>' );
        }
    }
}

// Add the function to the pre_comment_on_post action
add_action( 'pre_comment_on_post', 'limit_user_comments_per_post' );

/**
 * disable_comments_for_author
 */
function disable_comments_for_author($open, $post_id) {
    if (is_single() && is_user_logged_in()) {
        $post = get_post($post_id);
        $current_user = wp_get_current_user();

        if ($post->post_author == $current_user->ID) {
            // Disable the comments for the author
            $open = false;
            // Remove the "comments are closed" message
            add_filter('gettext', 'remove_comments_are_closed_message', 10, 3);
        }
    }
    return $open;
}

function remove_comments_are_closed_message($translated_text, $untranslated_text, $domain) {
    if ($untranslated_text === 'Comments are closed.') {
        $translated_text = ''; // Change this to whatever string you prefer, or leave it empty to remove the message
    }
    return $translated_text;
}

add_filter('comments_open', 'disable_comments_for_author', 10, 2);

/**
 * custom_remove_logged_in_as_text
 */
function custom_remove_logged_in_as_text($defaults) {
    // Check if user is logged in and the 'logged_in_as' parameter is set
    if (is_user_logged_in() && isset($defaults['logged_in_as'])) {
        // Empty the 'logged_in_as' parameter
        $defaults['logged_in_as'] = '';
    }
    return $defaults;
}
add_filter('comment_form_defaults', 'custom_remove_logged_in_as_text');

/**
 * wp_link_to_current_user_first_post
 */
function my_theme_enqueue_styles() {
    wp_enqueue_style('dashicons');
}
add_action('wp_enqueue_scripts', 'my_theme_enqueue_styles');

function wp_link_to_current_user_first_post() {
    // Check if the user is logged in
    if (is_user_logged_in()) {
        // Get the current user's ID
        $current_user_id = get_current_user_id();

        // Set up the arguments for retrieving the user's posts
        $args = array(
            'author'        => $current_user_id, // Only get posts from the current user
            'posts_per_page' => 1, // Limit to one post
            'orderby'       => 'date', // Order by post date
            'order'         => 'ASC', // Start with the earliest
        );

        // Get the posts
        $posts = get_posts($args);

        // Check if posts exist
        if (!empty($posts)) {
            // Get the permalink of the first post
            $first_post_url = get_permalink($posts[0]->ID);

            // Output the link to the first post
           echo '<a href="' . esc_url($first_post_url) . '" class="dashicons-before"><button><span style="font-size:20px;">Your Post</span><span style="font-size:15px;"><br />view messages, edit post</span></button></a>';
        } else {
            echo '<a href="https://usernames.au/wp-admin/" class="dashicons-before"><button><span style="font-size:20px;">Dashboard</span><span style="font-size:15px;"><br />edit post</span></button></a>';
        }
    } else {
        echo '';
    }
}

/**
 * replace_comment_with_message
 */
///////////////////////////////////////
is_admin() && add_filter('gettext', function ($translation, $text, $domain) {
    if (strpos($translation, 'comment') !== FALSE) {
        return str_replace('comment', 'message', $translation);
    }
    if (strpos($translation, 'Comment') !== FALSE) {
        return str_replace('Comment', 'Message', $translation);
    }

    return $translation;
}, 101, 88);


////////////////////////////////////////
function custom_gettext( $translated_text, $untranslated_text, $domain )
{       
    if( FALSE !== stripos( $untranslated_text, 'comment' ) )
    {
            $translated_text = str_ireplace( 'Comment', 'Message', $untranslated_text ) ;
    }
    return $translated_text;
}

is_admin() && add_filter( 'gettext', 'custom_gettext', 99, 93 );

/************ 
add_filter('comment_form_defaults', 'ocean_custom_comment_title', 20);
function ocean_custom_comment_title( $defaults ){
$defaults['title_reply'] = __('Leave a message...', 'customizr-child');
return $defaults;
}
****************/

////////////////////////////////
add_filter( 'generate_leave_comment','tu_custom_leave_comment' );
function tu_custom_leave_comment() {
    return 'Leave a message';
}

///////////////////// 
function change_comment_form_submit_label($arg) {
  $arg['label_submit'] = 'Send message';
  return $arg;
}
add_filter('comment_form_defaults', 'change_comment_form_submit_label', 11);

////////////////////////////
function my_custom_comment_text_above_fields() {
    echo '<p class="custom-text">Limit of 2 messages (Messages older than 7 days are deleted)</p>';
}
add_action('comment_form_top', 'my_custom_comment_text_above_fields');

/**
 * edit_post_link_for_author_shortcode
 */
function wpse_edit_post_link_for_author_shortcode() {
    // Make sure we are in the loop and the global $post variable is set
    if ( in_the_loop() && is_a( $GLOBALS['post'], 'WP_Post' ) ) {
        $post_id = get_the_ID();
        $post_type = get_post_type( $post_id );

        // Check if the current user has permission to edit the post
        if ( current_user_can( 'edit_post', $post_id ) ) {
            // Generate the edit post link
            $edit_link = get_edit_post_link( $post_id );
            if ( $edit_link ) {
                // Display the edit link
                return '<a href="' . esc_url( $edit_link ) . '"><button> Edit </button></a>';
            }
        }
    }

    // If the edit link is not available, return an empty string
    return '';
}

// Register the shortcode with WordPress
add_shortcode( 'edit_post_link_for_author', 'wpse_edit_post_link_for_author_shortcode' );

/**
 * CSS admin and hide stuff
 */
function wpse_custom_admin_css_for_non_admins() {
    // Check if the current user is not an administrator
    if ( !current_user_can( 'administrator' ) ) {
        // Output the custom CSS styles
        echo '<style type="text/css">
		
.custom-status-text {margin:0% 10% 5% 10%;}
		
#wp-content-wrap,#titlediv,#generate_layout_options_meta_box,#screen-meta-links,#misc-publishing-actionsxxx,#post-status-info,.user-description-wrap, .application-passwords, .user-rich-editing-wrap, .user-comment-shortcuts-wrap, 
 .misc-pub-visibility, .misc-pub-revisions, .curtime, .search-filter-notice-v3-coming-soon, #menu-tools, #menu-comments, #tagsdiv-gender, .search-box, .tablenav, .subsubsub, li#wp-admin-bar-new-content.menupop, #wp-admin-bar-comments, #wp-admin-bar-wp-logo, tr.user-first-name-wrap, tr.user-last-name-wrap, tr.user-nickname-wrap, tr.user-display-name-wrap, #formatdiv, #categorydiv, #tagsdiv-post_tag, #tagsdiv-gender, #postimagediv, #footer-thankyou, #footer-upgrade, .notice notice-warning, tr.user-admin-color-wrap, tr.show-admin-bar, tr.user-language-wrap, tr.user-url-wrap, .view, .row-actions, .comments, #menu-posts, #slim-seo, #minor-publishing-actions, #toplevel_page_slimview1
  { display: none;}

        </style>';
    }
}

// Hook into the 'admin_head' action
add_action( 'admin_head', 'wpse_custom_admin_css_for_non_admins' );
function remove_items_from_admin_bar($wp_admin_bar) {
    // Remove the WordPress logo and its submenus
    $wp_admin_bar->remove_node('wp-logo');
    // Remove the "New" menu (Quick Draft / New Post / New Page / New CPT)
    $wp_admin_bar->remove_node('new-content');
    // Remove the "Comments" menu
    $wp_admin_bar->remove_node('comments');
}

add_action('admin_bar_menu', 'remove_items_from_admin_bar', 999);

/**
 * author_posts_only
 */
function wpse_author_posts_only( $query ) {
    // Check if we're in the admin area, on the main query, and if the current user is not an administrator
    if ( is_admin() && $query->is_main_query() && !current_user_can( 'administrator' ) ) {
        // Get the current user's ID
        $user_id = get_current_user_id();

        // If the user is an author and not allowed to edit other's posts, modify the query to return only the user's posts
        if ( current_user_can( 'author' ) && !current_user_can( 'edit_others_posts' ) ) {
            // Set the author parameter to the current user's ID
            $query->set( 'author', $user_id );
        }
    }
}

// Hook the function into 'pre_get_posts'
add_action( 'pre_get_posts', 'wpse_author_posts_only' );

/**
 * link_comment_author_to_first_post
 */
function link_comment_author_to_first_post( $author_link, $author, $comment_ID ) {
    $comment = get_comment( $comment_ID );

    // Check if the comment has a valid user ID.
    if ( $comment->user_id > 0 ) {
        // Get the user by user ID.
        $user = get_userdata( $comment->user_id );

        // If the user exists, create a link to the author's first post.
        if ( $user ) {
            // Query for the user's posts.
            $args = array(
                'author' => $comment->user_id,
                'posts_per_page' => 1,
                'order' => 'ASC',
                'orderby' => 'date',
            );

            $first_post = get_posts( $args );

            // If there are posts, link to the first one.
            if ( !empty($first_post) ) {
                $first_post_url = get_permalink( $first_post[0]->ID );
                $author_user_login = $user->display_name; // Use the user's display name.
                $author_link = '<a href="' . esc_url( $first_post_url ) . '" rel="external nofollow ugc" class="url">' . esc_html( $author_user_login ) . '</a>';
            }
        }
    }

    return $author_link;
}
add_filter( 'get_comment_author_link', 'link_comment_author_to_first_post', 10, 3 );

/**
 * restrict_author_to_one_post
 */
// Hide the 'Add New' button on the Posts screen if the user already has one post including trashed posts
function wpse_restrict_author_to_one_post() {
    if (current_user_can('author') && !current_user_can('administrator')) {
        // Query the number of posts the author has including trashed posts
        $args = array(
            'author'        => get_current_user_id(),
            'post_type'     => 'post',
            'post_status'   => array('publish', 'pending', 'draft', 'trash'),
            'fields'        => 'ids', // We only need the IDs to count the posts
        );
        $user_posts = get_posts($args);

        if (count($user_posts) >= 1) {
            // Use CSS to hide the 'Add New' button
            echo '<style type="text/css">
                .page-title-action { display: none !important; }
            </style>';
            // Remove the "Add New" submenu item from the Posts menu
            remove_submenu_page('edit.php', 'post-new.php');
        }
    }
}
add_action('admin_head', 'wpse_restrict_author_to_one_post');

// Prevent authors from accessing the new post page if they already have one post including trashed posts
function wpse_prevent_new_post_access() {
    global $pagenow;

    // Check if we're on the 'Add New Post' page
    if ('post-new.php' === $pagenow) {
        if (current_user_can('author') && !current_user_can('administrator')) {
            // Query the number of posts the author has including trashed posts
            $args = array(
                'author'        => get_current_user_id(),
                'post_type'     => 'post',
                'post_status'   => array('publish', 'pending', 'draft', 'trash'),
                'fields'        => 'ids', // We only need the IDs to count the posts
            );
            $user_posts = get_posts($args);

            if (count($user_posts) >= 1) {
                // Check if the post is trashed
                $trashed_posts = get_posts(array_merge($args, ['post_status' => 'trash']));
                if (count($trashed_posts) > 0) {
                    // Redirect to the trashed posts list with an error message
                    $trash_url = admin_url('edit.php?post_status=trash&post_type=post');
                    wp_redirect($trash_url);
                    exit;
                } else {
                    wp_die('You are only allowed to have one active post. Please edit your existing post.');
                }
            }
        }
    }
}
add_action('admin_init', 'wpse_prevent_new_post_access');

/**
 * Display an admin notice if the author has trashed posts
 */
function wpse_author_trashed_post_admin_notice() {
    if (current_user_can('author') && !current_user_can('administrator')) {
        // Get the trashed posts for the current user
        $user_trashed_posts = get_posts(array(
            'author'        => get_current_user_id(),
            'post_type'     => 'post',
            'post_status'   => 'trash',
            'posts_per_page'=> -1 // Retrieve all trashed posts
        ));

        // Check if there are any trashed posts
        if (count($user_trashed_posts) > 0) {
            // Define the URL to the trashed posts
            $trash_url = admin_url('edit.php?post_status=trash&post_type=post');

            // Display the notice
            echo '<div class="notice notice-warning is-dismissible">
                <p><strong>Attention:</strong> You have trashed posts. Please <a href="' . esc_url($trash_url) . '">review and restore or permanently delete</a> them if you wish to publish new content.</p>
            </div>';
        }
    }
}
add_action('admin_notices', 'wpse_author_trashed_post_admin_notice');

/**
 * prevent_post_deletion
 */
function wpse_prevent_post_deletion($post_id) {
    // Check if the current user is an author and not an admin
    if(current_user_can('author') && !current_user_can('administrator')) {
        // Check if the post type is 'post'
        $post = get_post($post_id);
        if($post->post_type === 'post') {
            // Prevent authors from trashing or deleting posts
            // and redirect them with an error message.
            wp_die(
                __('You are not allowed to delete posts. Please change the post status to draft instead.', 'text-domain'),
                __('Action not allowed', 'text-domain'),
                array(
                    'response' => 403,
                    'back_link' => true // Allow user to go back to the previous page
                )
            );
        }
    }
}

add_action('wp_trash_post', 'wpse_prevent_post_deletion');
add_action('before_delete_post', 'wpse_prevent_post_deletion');

/**
 * no media
 */
function wpse_disable_media_library_for_authors() {
    // Check if the current user has the 'author' role and not higher capabilities
    if (current_user_can('author') && !current_user_can('edit_others_posts')) {
        // Get the current screen object
        $screen = get_current_screen();

        // Check if the current screen is the media library
        if ($screen->base == 'upload') {
            // Redirect to the dashboard with a little query argument to prevent direct linking
            wp_redirect(admin_url('?no_access=true'));
            exit;
        }
    }
}

// Action hook for admin screens
add_action('admin_init', 'wpse_disable_media_library_for_authors');


function wpse_remove_media_menu_for_authors() {
    if (current_user_can('author') && !current_user_can('edit_others_posts')) {
        remove_menu_page('upload.php'); // Remove Media Management SubPanel
    }
}

add_action('admin_menu', 'wpse_remove_media_menu_for_authors');


function wpse_disable_upload_for_authors($mime_types) {
    // Only modify the mime types if the user is an author and cannot edit other's posts
    if (current_user_can('author') && !current_user_can('edit_others_posts')) {
        // Return an empty array of mime types to disallow uploading
        return [];
    }

    // Return the original mime types if the user is not an author
    return $mime_types;
}

add_filter('upload_mimes', 'wpse_disable_upload_for_authors', 1, 1);


function wpse_hide_admin_bar_new_media($wp_admin_bar) {
    // Check if the current user is an author and does not have higher capabilities
    if (current_user_can('author') && !current_user_can('edit_others_posts')) {
        // Remove the 'Add New' media link from the admin bar
        $wp_admin_bar->remove_node('new-media');
    }
}

add_action('admin_bar_menu', 'wpse_hide_admin_bar_new_media', 999);

/**
 * custom_fields_list_shortcode
 */
function wpse_custom_fields_list_shortcode( $atts ) {
    // You can pass specific post ID through shortcode attributes if needed.
    $atts = shortcode_atts( array(
        'post_id' => get_the_ID(), // Default to the current post ID.
    ), $atts );

    $post_id = $atts['post_id'];
    
    // The custom fields you want to show: 'custom_field_key' => 'Custom Field Label'
    $custom_fields = array(
        'pof' => 'POF',
        'zoosk' => 'Zoosk',
        // Add more fields as needed.
    );

    // Start with an empty string to collect the list items.
    $output = '<ul class="wpse-custom-fields-list">';

    // Loop through the custom fields and get their values for the specified post.
    foreach ( $custom_fields as $key => $label ) {
        $value = get_post_meta( $post_id, $key, true );

        // Only add the list item if the custom field has a value.
        if ( !empty( $value ) ) {
            $output .= sprintf( '<li><strong>%s:</strong> %s</li>', esc_html( $label ), esc_html( $value ) );
        }
    }

    // Close the unordered list.
    $output .= '</ul>';

    // Return the list, which will replace the shortcode when it's used.
    return $output;
}

// Register the shortcode with WordPress.
add_shortcode( 'custom_fields_list', 'wpse_custom_fields_list_shortcode' );

/**
 * disable_quick_edit
 */
function wpse_disable_quick_edit( $actions = array(), $post = null ) {
    // Remove the Quick Edit link
    if (isset($actions['inline hide-if-no-js'])) {
        unset($actions['inline hide-if-no-js']);
    }
    return $actions;
}

// Filter the post row actions
add_filter( 'post_row_actions', 'wpse_disable_quick_edit', 10, 2 );

// Filter the page row actions
add_filter( 'page_row_actions', 'wpse_disable_quick_edit', 10, 2 );

/**
 * update_post_content_with_acf_field
 */
function update_post_content_with_acf_field($post_id) {
    // Check if this is a revision or an autosave.
    if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
        return;
    }

    // Check the user's permissions.
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Get the value of the ACF field.
    $acf_text_value = get_field('message', $post_id);

    // If the ACF field has a value, update the post content.
    if ($acf_text_value) {
        // Remove the action to prevent infinite loops.
        remove_action('save_post', 'update_post_content_with_acf_field');

        // Update the post content.
        wp_update_post(array(
            'ID'           => $post_id,
            'post_content' => $acf_text_value
        ));

        // Re-add the action for future save events.
        add_action('save_post', 'update_post_content_with_acf_field');
    }
}
add_action('save_post', 'update_post_content_with_acf_field');

/**
 * hide_menu_item_if_user_has_posts
 */
function hide_menu_item_if_user_has_posts($items, $menu, $args) {
    // The ID of the menu item you want to hide
    $target_menu_item_id = 60; // Replace with your menu item ID

    // Get the current user
    $current_user = wp_get_current_user();

    if ($current_user instanceof WP_User) {
        // Check if the user has any posts
        $user_posts = get_posts(array(
            'author' => $current_user->ID,
            'post_status' => 'any',
            'numberposts' => 1
        ));

        // If the user has posts, we remove the target menu item
        if ($user_posts) {
            foreach ($items as $key => $item) {
                if ($item->ID == $target_menu_item_id) {
                    unset($items[$key]);
                }
            }
        }
    }

    return $items;
}
add_filter('wp_get_nav_menu_items', 'hide_menu_item_if_user_has_posts', 10, 3);

/**
 * create_post_for_new_author
 */
function create_post_for_new_author( $user_id ) {
    // Get the user object
    $user = get_userdata( $user_id );

    // Check if the user has the 'author' role
    if ( in_array( 'author', (array) $user->roles ) ) {
        // Set up the new post array
        $new_post = array(
            'post_title'    => $user->user_login, // The title of the post is the username
            'post_content'  => 'Please be nice. I am looking forward to reading your message.', // Default content for the post
            'post_status'   => 'draft', // Set the status of the new post to 'draft'
            'post_author'   => $user_id, // Set the author of the post to the new user
            'post_type'     => 'post', // Set the type of the new post to 'post'
        );

        // Insert the post into the database
        wp_insert_post( $new_post );
    }
}

// Add the action that hooks the above function to user_register
add_action( 'user_register', 'create_post_for_new_author' );

/**
 * redirect_relevanssi_admin_search_to_dashboard
 */
function hide_relevanssi_admin_search_menu() {
    // Use the parent slug for the Dashboard and the slug of the sub-menu page to remove it
    remove_submenu_page('index.php', 'relevanssi_admin_search');
}

add_action('admin_menu', 'hide_relevanssi_admin_search_menu', 999);

add_action('admin_menu', 'hide_relevanssi_admin_search_menu', 999);

function redirect_relevanssi_admin_search_to_dashboard() {
    if (is_admin() && isset($_GET['page']) && $_GET['page'] === 'relevanssi_admin_search') {
        wp_redirect(admin_url());
        exit;
    }
}

add_action('admin_init', 'redirect_relevanssi_admin_search_to_dashboard');

/**
 * custom_registration_honeypot_field
 */
// Add a honeypot field to the registration form
function custom_registration_honeypot_field() {
    ?>
    <p style="display:none;">
        <label for="confirm_email">Please leave this field empty</label>
        <input type="text" name="confirm_email" id="confirm_email" class="input" value="" size="25" autocomplete="off" />
    </p>
    <?php
}
add_action('register_form', 'custom_registration_honeypot_field');

// Check the honeypot field on registration
function custom_check_registration_honeypot_field($errors, $sanitized_user_login, $user_email) {
    if (!empty($_POST['confirm_email'])) {
        $errors->add('spam_error', __('<strong>ERROR</strong>: Spambot detected. Registration rejected.', 'your-textdomain'));
    }
    return $errors;
}
add_filter('registration_errors', 'custom_check_registration_honeypot_field', 10, 3);

/**
 * redirect_non_logged_in_users_to_login
 */
function redirect_non_logged_in_users_to_login() {
    // Check if we're on a single post and the user is not logged in
    if (is_single() && !is_user_logged_in()) {
        // Get the login URL and redirect to it
        wp_redirect(wp_login_url(get_permalink()));
        exit;
    }
}
add_action('template_redirect', 'redirect_non_logged_in_users_to_login');

/**
 * block users [CLONE]
 */
function redirect_blocked_users() {
    if (is_single()) {
        global $post;
        
        // Check if the user is logged in.
        if (is_user_logged_in()) {
            $current_user_id = get_current_user_id();
            $post_author_id = $post->post_author;
            
            // Get the blocked users from the author's ACF field.
            $blocked_users = get_field('blocked_members', 'user_' . $post_author_id);

            // Check if the current user is in the list of blocked users.
            if (is_array($blocked_users) && in_array($current_user_id, array_column($blocked_users, 'ID'))) {
                // Specify the URL or Page ID to redirect to.
               // $redirect_url = home_url(); // Redirect to the home page.
                $redirect_url = get_permalink(22); // Or redirect to a specific page by ID.

                // Perform the redirect.
                wp_redirect($redirect_url);
                exit; // Always call exit after wp_redirect.
            }
        }
    }
}

add_action('template_redirect', 'redirect_blocked_users');



function exclude_current_user_from_blocked_members_field($args, $field, $post_id) {
    // Get the current user's ID to exclude it from the User field.
    $current_user_id = get_current_user_id();

    // Add an 'exclude' argument to the query.
    $args['exclude'] = array($current_user_id);

    return $args;
}

add_filter('acf/fields/user/query/name=blocked_members', 'exclude_current_user_from_blocked_members_field', 10, 3);

/**
 * matching 3
 *
 * display_custom_field_if_user_has_same(get_the_ID(), 'your_custom_field_key', 'specific-term-slug');
 */
function display_custom_fields_if_user_has_same_and_term_matches($current_post_id, $custom_fields_with_terms) {
    // Check if the user is logged in
    if (!is_user_logged_in()) {
        return;
    }

    // Get the current logged-in user's ID
    $user_id = get_current_user_id();
    
    // Get all posts by the user
    $user_posts = get_posts(array(
        'author'        => $user_id,
        'post_type'     => 'post',
        'posts_per_page'=> -1, // to get all user posts
        'fields'        => 'ids', // only get post IDs to optimize the query
    ));

    // Flag to check if there is at least one match
    $has_match = false;

    // Start an unordered list
    echo "<ul>";

    // Check the user's posts for each of the custom fields
    foreach ($custom_fields_with_terms as $custom_field => $term_and_label) {
        $user_has_custom_field = false;
        foreach ($user_posts as $user_post_id) {
            if (get_post_meta($user_post_id, $custom_field, true) != '') {
                $user_has_custom_field = true;
                break;
            }
        }

        // If the user has the custom field, check the current post for the term
        if ($user_has_custom_field && has_term($term_and_label['term'], 'post_tag', $current_post_id)) {
            // Get the custom field value of the current post
            $value = get_post_meta($current_post_id, $custom_field, true);
            if (!empty($value)) {
                // Display the custom field value with label in a list item
                echo "<li><strong>" . esc_html($term_and_label['label']) . ":</strong> " . esc_html($value) . "</li>";
                $has_match = true;
            }
        }
    }

    // If no match was found, display "No match"
    if (!$has_match) {
        echo "<li><em>No matching websites/apps</em></li>";
        // If we are on a single post page, redirect
        if (is_single()) {
            $redirect_url = home_url(); // Modify this to your desired URL
            wp_redirect($redirect_url);
            exit; // Always call exit immediately after wp_redirect()
        }
    }

    // End the unordered list
    echo "</ul>";
}

/**
 * Shortcode to display current author's comments
 *
 * https://poe.com/s/PpPURD1cnUbV9i81XQWc
 */
/**
 * Shortcode to display current author's comments with post title and date/time
 */
function current_author_comments_shortcode( $atts ) {
    // Get the current user's ID
    $current_user_id = get_current_user_id();

    // Query comments by the current author
    $comments = get_comments( array(
        'user_id' => $current_user_id,
        'status'  => 'approve',
    ) );

    // Initialize the output variable
    $output = '';

    // Loop through each comment
    foreach ( $comments as $comment ) {
        // Get the post ID
        $post_id = $comment->comment_post_ID;

        // Get the post title and link
        $post_title = '<a href="' . get_permalink( $post_id ) . '">' . get_the_title( $post_id ) . '</a>';

        // Get the comment date and time
        $comment_date = get_comment_date( get_option( 'date_format' ), $comment );
        $comment_time = date_i18n( get_option( 'time_format' ), strtotime( $comment->comment_date ) );

        // Append the comment details to the output
        $output .= '<li>';
        $output .= sprintf( '<strong>%s:</strong> %s', $post_title, $comment->comment_content );
        $output .= sprintf( ' <em>(%s at %s)</em>', $comment_date, $comment_time );
        $output .= '</li>';
    }

    return $output;
}
add_shortcode( 'current_author_comments', 'current_author_comments_shortcode' );

/**
 * customize_dashboard_widgets
 */
// Hook into the 'wp_dashboard_setup' action to register our custom functions
add_action('wp_dashboard_setup', 'customize_dashboard_widgets');

function customize_dashboard_widgets() {

    // First, remove all existing dashboard widgets
    global $wp_meta_boxes;
    unset($wp_meta_boxes['dashboard']['normal']['core']);
    unset($wp_meta_boxes['dashboard']['side']['core']);
    unset($wp_meta_boxes['dashboard']['column3']['core']);
    unset($wp_meta_boxes['dashboard']['column4']['core']);

    // Next, add our custom dashboard widget
    wp_add_dashboard_widget(
        'custom_dashboard_widget', // Widget slug.
        'Welcome', // Title.
        'custom_dashboard_widget_content' // Display function.
    );
}

// The output of our custom dashboard widget
function custom_dashboard_widget_content() {
    // Get the current user
    $user = wp_get_current_user();

    // Query for the first post or draft of the user
    $args = array(
        'author' => $user->ID,
        'post_type' => 'post',
        'post_status' => array('publish', 'draft'),
        'posts_per_page' => 1,
        'orderby' => 'date',
        'order' => 'ASC'
    );
    $user_posts = new WP_Query($args);

    // Check if the user has posts or drafts and output the edit link for the first one
    if ($user_posts->have_posts()) {
        $user_posts->the_post();
        echo '<p>Hello ' . esc_html($user->display_name) . '.<br />Your first step is to complete (and publish) your post with all your website/app details. After that you can go searching for others on the same sites/apps and send them a free message. </p>';
        echo '<p><a href="' . esc_url(get_edit_post_link()) . '" class="button button-primary">Edit Your Post</a> <a href="https://usernames.au/wp-admin/profile.php" class="button button-secondary">Profile</a> &bull; <a href="' . esc_url(home_url('/')) . '" class="button button-secondary">Home</a> <a href="' . esc_url(home_url('/?page_id=162')) . '" class="button button-secondary">FAQs</a> <a href="' . esc_url(home_url('/?p=324')) . '" class="button button-secondary">Contact</a></p>';
        wp_reset_postdata();
    } else {
        // If no posts or drafts, provide a link to create a new post
        echo '<p>Hello ' . esc_html($user->display_name) . '! It looks like you don’t have a post yet. Your first step is to create your post with all your website details. After that you can go searching for others on the same  sites and send them a free message. </p>';
        echo '<p><a href="' . esc_url(admin_url('post-new.php')) . '" class="button button-primary">Create Your First Post</a> <a href="' . esc_url(home_url('/')) . '" class="button button-secondary">Home</a></p>';
    }
}

/**
 * delete_old_comments_exclude_admin_and_posts
 */
function delete_old_comments_exclude_admin_and_posts() {
    global $wpdb;
    $days = 7;
    $admin_user_id = 1; // Replace with the actual admin user ID

    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM $wpdb->comments 
            WHERE comment_date < %s 
            AND user_id <> %d 
            AND comment_post_ID NOT IN (SELECT ID FROM $wpdb->posts WHERE post_author = %d)",
            date_i18n('Y-m-d H:i:s', strtotime("-$days days")),
            $admin_user_id,
            $admin_user_id
        )
    );
}

// Schedule the function to run daily, if it's not already scheduled
if (!wp_next_scheduled('delete_old_comments_exclude_admin_and_posts_cron')) {
    wp_schedule_event(time(), 'daily', 'delete_old_comments_exclude_admin_and_posts_cron');
}
add_action('delete_old_comments_exclude_admin_and_posts_cron', 'delete_old_comments_exclude_admin_and_posts');

/**
 * add_class_to_users_posts
 */
function add_class_to_users_posts($classes, $class, $post_id) {
    if (is_user_logged_in()) { // Check if user is logged in
        $current_user = wp_get_current_user();
        $post = get_post($post_id);
        if ($post->post_author == $current_user->ID) {
            // Add custom class if the logged-in user is the author of the post
            $classes[] = 'current-user-post';
        }
    }
    return $classes;
}
add_filter('post_class', 'add_class_to_users_posts', 10, 3);

/**
 * exclude_admin_post_from_queries
 */
function exclude_specific_post_from_queries( $query ) {
    if ( ! is_admin() && $query->is_main_query() ) {
        $post_id = 324; // Replace with the ID of the post to exclude.
        $query->set( 'post__not_in', array( $post_id ) );
    }
}
add_action( 'pre_get_posts', 'exclude_specific_post_from_queries' );

/**
 * custom_login_logo
 */
function custom_login_branding() {
    ?>
    <style type="text/css">
        /* Hide the existing logo */
        #login h1 a, .login h1 a {
            display: none;
        }
        /* Style the custom title and message */
        #login h1.custom-branding {
            text-align: center;
            margin-bottom: 20px;
        }
        #login h1.custom-branding h1 {
            color: #555; /* Change the color to suit your theme */
            font-size: 24px; /* Adjust the size as needed */
        }
        #login h1.custom-branding p {
            color: #777; /* Change the color to suit your theme */
            margin-top: 10px;
        }
    </style>
    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function () {
            var loginForm = document.getElementById('login');
            var customBranding = document.createElement('div');
            customBranding.innerHTML = '<a href="https://usernames.au" style="color:#333; text-decoration:none;"><h1>USERNAMES.AU</h1></a><p style="text-align:center;margin-bottom:10px;">free messaging for paid websites</p>';
            customBranding.classList.add('custom-branding');

            // Insert the custom branding before the login form
            loginForm.insertBefore(customBranding, loginForm.firstChild);
        });
    </script>
    <?php
}
add_action('login_enqueue_scripts', 'custom_login_branding');

/**
 * get_current_user_first_post_link in menu
 */
// Function to replace a placeholder URL with the first post link in menu items
function replace_placeholder_with_first_post_link($items, $args) {
    // Only run this on the front end
    if (is_admin()) {
        return $items;
    }

    foreach ($items as &$item) {
        // Check for the specific placeholder in the menu item URL
        if (strpos($item->url, '#firstpostlink') !== false) {
            $first_post_link = get_current_user_first_post_link();
            if (!empty($first_post_link)) {
                // Replace the placeholder with the actual URL
                $item->url = $first_post_link;
            } else {
                // Optionally hide the menu item if there is no first post
                $item->url = '';
                $item->_invalid = true;
            }
        }
    }

    return $items;
}

// Add the custom filter to wp_get_nav_menu_items
add_filter('wp_get_nav_menu_items', 'replace_placeholder_with_first_post_link', 10, 2);

// Function to get the permalink of the first post of the logged-in user
function get_current_user_first_post_link() {
    if (is_user_logged_in()) {
        $current_user_id = get_current_user_id(); // Get the current user ID

        $args = array(
            'author' => $current_user_id, // Get posts from current user
            'orderby' => 'date', // Order by date
            'order' => 'ASC', // Start with the oldest post
            'posts_per_page' => 1, // Only fetch the first post
            'post_status' => 'publish', // Only get published posts
        );

        $user_posts = get_posts($args); // Fetch the posts

        if (!empty($user_posts)) {
            return get_permalink($user_posts[0]->ID); // Return the permalink of the first post
        }
    }

    return ''; // Return empty string if no posts or not logged in
}

/**
 * menu_item_description
 */
add_filter( 'walker_nav_menu_start_el', 'tu_menu_item_description', 10, 4 );
function tu_menu_item_description( $item_output, $item, $depth, $args ) 
{
	// If we're working with the primary or secondary theme locations
	if ( 'primary' == $args->theme_location || 'secondary' == $args->theme_location || 'slideout' == $args->theme_location ) {
		$item_output = str_replace( $args->link_after . '</a>', $args->link_after . '</span><span class="description">' . $item->description . '</span></a>', $item_output );
	}
	
	// Return the output
	return $item_output;
}

/**
 * restrict_author_backend_comments
 */
function restrict_author_backend_comments() {
    // Check if the current user is an author
    if (current_user_can('author')) {
        // Check if we are in the admin area and on the comments page or attempting to access comments-related pages
        global $pagenow;
        if ($pagenow === 'edit-comments.php' || $pagenow === 'comment.php') {
            wp_die(__('You do not have permission to access this page.')); // Display a message and terminate execution
        }
        
        // Remove comments menu item for authors
        function remove_comments_menu_item() {
            remove_menu_page('edit-comments.php');
        }
        add_action('admin_menu', 'remove_comments_menu_item');
        
        // Optionally hide comments from the dashboard
        function remove_dashboard_comments_widget() {
            remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
        }
        add_action('wp_dashboard_setup', 'remove_dashboard_comments_widget');
    }
}
add_action('admin_init', 'restrict_author_backend_comments');

/**
 * post edit status text
 */
function my_custom_admin_footer_text() {
    global $post;
    // Check if we're on the post edit page and if the post is published. https://poe.com/s/NMn92bl4oIG6tSOymI4S
    if (get_current_screen()->id === 'post' && $post->post_status === 'publish') {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // This selector finds the "Status: Published" text.
                // You may need to adjust the selector to target the specific element correctly.
                var statusElement = $('#misc-publishing-actions .misc-pub-section.misc-pub-post-status');
                
                if (statusElement.length > 0) {
                    // Insert your custom text below the "Status: Published" text.
                    $('<div class="custom-status-text">To hide your post from everyone, click edit and change your status from <strong>Published</strong> to <strong>Draft</strong>, then click Update.</div>').insertAfter(statusElement);
                }
            });
        </script>
        <?php
    }
}

// Hook into the admin footer.
add_action('admin_footer', 'my_custom_admin_footer_text');







function my_custom_admin_footer_text2() {
    global $post;
    // Check if we're on the post edit page and if the post is published. https://poe.com/s/NMn92bl4oIG6tSOymI4S
    if (get_current_screen()->id === 'post' && $post->post_status === 'draft') {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // This selector finds the "Status: Published" text.
                // You may need to adjust the selector to target the specific element correctly.
                var statusElement = $('#misc-publishing-actions .misc-pub-section.misc-pub-post-status');
                
                if (statusElement.length > 0) {
                    // Insert your custom text below the "Status: Published" text.
                    $('<div class="custom-status-text">To <strong>UNhide</strong> your post (and make it visible, just click <strong>Publish</strong>.</div>').insertAfter(statusElement);
                }
            });
        </script>
        <?php
    }
}

// Hook into the admin footer.
add_action('admin_footer', 'my_custom_admin_footer_text2');




function remove_pending_review_option() {
    global $pagenow;
    // Check if we're on the post edit page - https://poe.com/s/EzRYyfhUKBKa4avZYs6L
    if ($pagenow == 'post.php' || $pagenow == 'post-new.php') {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Remove the "Pending Review" option from the "Status" dropdown.
                $("#post_status option[value='pending']").remove();
                
                // If you also want to remove it from the "Quick Edit" on the posts list, uncomment the following line:
                // $('select[name="_status"] option[value="pending"]').remove();
            });
        </script>
        <?php
    }
}

// Hook into the admin footer to output the script.
add_action('admin_footer', 'remove_pending_review_option');

/**
 * custom text to the registration form
 */
// Hook that adds custom text to the registration form
function my_custom_registration_text() {
    $custom_text = "<p class='custom-text'>Your username can't be changed and will be visible to everyone. Your email will be private.<br /><br /></p>";
    echo $custom_text;
}

// Add the above function to the 'register_form' action hook
add_action('register_form', 'my_custom_registration_text');

// Optional: Use this section to enqueue styles if you want to style your custom text.
function my_custom_registration_text_styles() {
    wp_enqueue_style( 'my-custom-registration-styles', plugins_url( 'css/custom-registration-styles.css', __FILE__ ) );
}

// Hook the styles function onto the login_enqueue_scripts action
add_action( 'login_enqueue_scripts', 'my_custom_registration_text_styles' );
