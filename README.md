# Wordpress-Stuff

Create Book PLugin in whcih add books , retrive last 5 years bokk and user can instal update the rating also filter in rating.

we use custom post type, custom texnomies, custom meta box, admin ajax.



Custom post types

To register a custom post type, use the WordPress register_post_type function.
function takes two parameters: the name of the custom post type, and an array of arguments that define the custom post type.

example of creating the arguments array, and calling the register_post_type

$args = array(
'labels' => array(
'name' => 'Books',
'singular_name' => 'Book',
'menu_name' => 'Books',
'add_new' => 'Add New Book',
'add_new_item' => 'Add New Book',
'new_item' => 'New Book',
'edit_item' => 'Edit Book',
'view_item' => 'View Book',
'all_items' => 'All Books',
),
'public' => true,
'has_archive' => true,
'show_in_rest' => true,
'supports' => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt' ),
);

register_post_type( 'book', $args );

if ( ! defined( 'ABSPATH' ) ) {
exit; // Exit if accessed directly
}

ABSPATH check to prevent direct access to the file.

"bookstore_register" prefixing function names to avoid conflicts with other plugins or themes.

Array Arguments :

public bool

    Makes the post type publicly visible.

    Whether a post type is intended for use publicly either via the admin interface or by front-end users. While the default settings of $exclude_from_search, $publicly_queryable, $show_ui, and $show_in_nav_menus are inherited from $public, each does not rely on this relationship and controls a very specific intention.
    Default false.

'has_archive' => true,
hierarchical bool
Whether the post type is hierarchical (e.g. page). Default false.

    What: Enables an archive page at /book/ or /books/.

    Why: Shows a list of all books on a single URL like:
    https://example.com/book/

show_ui bool

    Whether to generate and allow a UI for managing this post type in the admin. Default is value of $public.

show_in_menu bool|string

    Where to show the post type in the admin menu. To work, $show_ui must be true. If true, the post type is shown in its own top level menu. If false, no menu is shown. If a string of an existing top level menu ('tools.php' or 'edit.php?post_type=page', for example), the post type will be placed as a sub-menu of that.
    Default is value of $show_ui.

show_in_rest bool
custom post type to be compatible with the WordPress Block Editor (Gutenberg) and accessible via the REST API.
Makes book posts available at:
https://example.com/wp-json/wp/v2/book

        Allows AJAX/JavaScript frameworks (React/Vue) to fetch/post data.

'supports' => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt' ),

    Specifies which features the post type supports.
    Why: You want each book to have a title, body content (editor), and a featured image (thumbnail).
    How: These options enable the corresponding UI components in the WordPress admin.

Custom taxonomies IN WP

| Taxonomy | Key           | Hierarchical | Behavior                                                       |
| -------- | ------------- | ------------ | -------------------------------------------------------------- |
| Author   | `book_author` | `false`      | Acts like **tags** (multiple, flat structure)                  |
| Genre    | `book_genre`  | `true`       | Acts like **categories** (can have parent/child relationships) |

REST API Access:
These taxonomies will also be available via the REST API:

/wp-json/wp/v2/book_author

/wp-json/wp/v2/book_genre

Create meta box for post

add_meta_box(
'bookstore_publish_year',// 1. ID A unique internal ID for your meta box,used for targeting styling  
 'Publish Year', // 2. Title (shown in the admin UI)
'bookstore_publish_year_callback', // 3. Callback (renders HTML input)
'book', // 4. Post type (only show for "book")
'side', // 5. Location (sidebar)
'default' // 6. Priority (default position)
);

add_shortcode( 'bookstore_books', 'bookstore_books_shortcode' );

THis shortcode use in page or post

// Parse shortcode attributes
$atts = shortcode_atts( array(
'year' => '5', // Default to last 5 years
), $atts, 'bookstore_books' );

`shortcode_atts()`
A built-in WordPress function that handles shortcode attributes. It merges user-defined values with your defaults.

`array('year' => '5')`
This is your **default attribute** — it means: if the user does **not** pass a `year`, then use `'5'` (last 5 years) as default.

`$atts`
This is the **actual user input** — it contains whatever was passed in the shortcode, like `year="3"`.

`'bookstore_books'`  
 This is the **shortcode name**. It helps with internal debugging and filters.

Explain how wp query works

$meta_query = array(
array(
'key' => '\_bookstore_publish_year',
'value' => $year_range,
'compare' => 'IN',
'type' => 'NUMERIC'
)
);

    // Query the books

$query = new WP_Query( array(
'post_type' => 'book',
'posts_per_page' => -1,
'meta_query' => $meta_query
)
);

if ( $query->have_posts() ) {}

This creates a meta query array, which is passed to WP_Query to filter posts by custom field values (like publish year).

`'key' => '_bookstore_publish_year'`

     The **meta key** you stored in your meta box (this must match exactly the key used in `update_post_meta()`)

`'value' => $year_range`  
 The **value(s)** you want to match — `$year_range` is an array of years like `[2023, 2024, 2025]`  
 `'compare' => 'IN'`
Tells WordPress to find posts where the meta value is **in this list**. It’s like `WHERE value IN (2023, 2024, 2025)` in SQL.

| `'type' => 'NUMERIC'`  
 | Ensures the comparison treats the value as a number (not a string), which avoids type issues. |


| Function              | Role                                                |
| --------------------- | --------------------------------------------------- |
| `have_posts()`        | Checks if there are posts left in the query         |
| `the_post()`          | Loads the next post’s data into WordPress functions |
| `wp_reset_postdata()` | Safely ends the loop and restores global context Restores 
                          the original global $post variable (important when using custom queries).   |


wp_enqueue_scripts

wp_enqueue_scripts is the proper hook to use when enqueuing scripts and styles that are meant to appear on the front end. Despite the name, it is used for enqueuing both scripts and styles.

wp_enqueue_script( 'bookstore-rating', plugin_dir_url(__FILE__) . 'rating.js', array('jquery'), '1.0', true );

wp_enqueue_script( 'unique name', File path, dependecies, 'script version', script placed);

true = footer , false = header

'bookstore-rating' = is the script handle (a unique name).

plugin_dir_url(__FILE__) . 'rating.js'  = builds the full URL to your rating.js file inside the plugin directory.

array('jquery')=  makes jQuery a dependency—WordPress will make sure it loads before your script.

'1.0' = is the script version (for cache busting).

true = means the script will be placed in the footer of the HTML (just before </body>) for better performance.




wp_localize_script( 
    'bookstore-rating', 
    'bookstore_ajax', 
    array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'rate_book_nonce' )
    ) 
);

  Parameter

    'bookstore-rating'
    	The same script handle you just enqueued
    'bookstore_ajax'	
        The JavaScript object name available in the browser (you’ll use it in JS like bookstore_ajax.ajax_url)
    array(...)	
        This array becomes data passed from PHP to JS



wp_create_nonce()

What It Does
wp_create_nonce( 'action_name' ) generates a unique token (called a nonce, short for "number used once") tied to a specific action and user session.

Why We Use It
It helps protect your site against Cross-Site Request Forgery (CSRF) attacks. Essentially, it ensures that requests (like form submissions or AJAX calls) come from authorized users and not malicious sources.


admin_url( $path = '', $scheme = 'admin' )

The admin_url() function in WordPress is used to retrieve the URL of the admin area (also called the dashboard) of your WordPress site. It's particularly useful when you need to direct users or scripts to specific admin pages — or, in the context of your code, to point JavaScript to admin-ajax.php for backend AJAX handling.
