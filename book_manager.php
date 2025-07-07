<?php
/**
 * Plugin Name: Book Manager
 * Description: Custom plugin to manage books with authors, genres, ratings, AJAX filtering, and notifications.
 * Version: 1.0
 * Author: Ruchi
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action('wp_enqueue_scripts', function() {
   
    wp_enqueue_style('bookcss', plugin_dir_url(__FILE__) . 'book.css');
    wp_enqueue_script('book-js', plugin_dir_url(__FILE__) . 'book.js', ['jquery'], null, true);
    wp_localize_script('book-js', 'book_ajax', ['ajaxurl' => admin_url('admin-ajax.php')]);
   
});


// Register CPT and Taxonomies
add_action('init', function() {
    register_post_type('book', [
        'labels' => ['name' => 'Books', 'singular_name' => 'Book'],
        'public' => true,
        'has_archive' => true,
        'supports' => ['title', 'editor'],
        'rewrite' => ['slug' => 'books'],
        'show_in_rest' => true,
    ]);

    register_taxonomy('genre', 'book', [
        'label' => 'Genre',
        'hierarchical' => true,
        'public' => true,
        'rewrite' => ['slug' => 'genre'],
        'show_in_rest' => true,
    ]);

    register_taxonomy('author', 'book', [
        'label' => 'Author',
        'hierarchical' => false,
        'public' => true,
        'rewrite' => ['slug' => 'author'],
        'show_in_rest' => true,
    ]);
});

// Meta Boxes
add_action('add_meta_boxes', function() {
    add_meta_box('book_details', 'Book Details', function($post) {
        $year = get_post_meta($post->ID, '_publication_year', true);
        $rating = get_post_meta($post->ID, '_book_rating', true);
        echo '<p><label>Publication Year: <input type="number" name="publication_year" value="'.esc_attr($year).'"></label></p>';
        echo '<p><label>Rating (1-5): <input type="number" min="1" max="5" name="book_rating" value="'.esc_attr($rating).'"></label></p>';
    }, 'book', 'normal');
});

// Save Meta
add_action('save_post_book', function($post_id) {
    if (isset($_POST['publication_year'])) {
        update_post_meta($post_id, '_publication_year', sanitize_text_field($_POST['publication_year']));
    }
    if (isset($_POST['book_rating'])) {
        update_post_meta($post_id, '_book_rating', sanitize_text_field($_POST['book_rating']));
    }
});

// Author Term Meta Field (email)
add_action('author_edit_form_fields', function($term) {
    $email = get_term_meta($term->term_id, 'author_email', true);
    echo '<tr><th><label>Email</label></th><td><input type="email" name="author_email" value="'.esc_attr($email).'"></td></tr>';
});
add_action('edited_author', function($term_id) {
    if (isset($_POST['author_email'])) {
        update_term_meta($term_id, 'author_email', sanitize_email($_POST['author_email']));
    }
});

// Email Notification on Book Publish
add_action('publish_book', function($post_id) {
    $author_terms = wp_get_post_terms($post_id, 'author');
    foreach ($author_terms as $term) {
        $email = get_term_meta($term->term_id, 'author_email', true);
        if ($email) {
            wp_mail($email, 'Book Published', 'Your book "'.get_the_title($post_id).'" is published.');
        }
    }
});

// Shortcode with Meta Query
add_shortcode('book_info', function($atts) {
    $a = shortcode_atts(['years' => 5], $atts);
    $year_threshold = date('Y') - intval($a['years']);

    ob_start();
    $query = new WP_Query([
        'post_type' => 'book',
        'meta_query' => [[
            'key' => '_publication_year',
            'value' => $year_threshold,
            'compare' => '>=',
            'type' => 'NUMERIC',
        ]]
    ]);
    
    if ($query->have_posts()) {
      
        echo '<div class="book-info">';
      
          echo '<div id="books">';
        $all_genres = get_terms(['taxonomy' => 'genre', 'hide_empty' => false]);
        if (!empty($all_genres)) {
            echo '<div id="genre-filters">';
            foreach ($all_genres as $genre) {
                echo '<button class="genre-filter-btn" data-genre="' . esc_attr($genre->slug) . '">' . esc_html($genre->name) . '</button>';
            }
            echo '</div>';
        }

        echo '</div><select id="rating-filter"><option value="">All Ratings</option><option value="5">5</option><option value="4">4+</option><option value="3">3+</option><option value="2">2+</option><option value="1">1+</option></select>';
        echo '</div>';
        echo '<div id="book-filter-container">';
        echo '</div>';
          echo '<div id="original-book-list">';
        while ($query->have_posts()) {
            $query->the_post();
            $year = get_post_meta(get_the_ID(), '_publication_year', true);
            $rating = get_post_meta(get_the_ID(), '_book_rating', true);
            $authors = wp_get_post_terms(get_the_ID(), 'author', ['fields' => 'names']);
            $genres = wp_get_post_terms(get_the_ID(), 'genre', ['fields' => 'all']);
           
            echo '<div class="book">';
            echo '<h3>' . get_the_title() . '</h3>';
            echo '<p>Author: ' . implode(', ', $authors) . '</p>';
            echo '<p>Genre: ';
            foreach ($genres as $genre) {
                    $genre_link = get_term_link($genre);
                    if (!is_wp_error($genre_link)) {
                        echo '<a href="' . esc_url($genre_link) . '" class="genre-link" data-genre="' . esc_attr($genre->slug) . '">' . esc_html($genre->name) . '</a> ';
                    }
                }
            echo '</p>';
            echo '<p>Rating: <span class="book-rating" data-id="'.get_the_ID().'">' . esc_html($rating ?: '0') . '</span> / 5</p>';
            echo '<select class="rate-book" data-id="'.get_the_ID().'"><option>Rate</option>';
            for ($i = 1; $i <= 5; $i++) echo "<option value='$i'>$i</option>";
            echo '</select>';
            echo '<p>Published: ' . esc_html($year) . '</p>';
            echo '</div>';
        }
        echo '</div>';
        echo '</div>';

      
    }
    wp_reset_postdata();
    return ob_get_clean();
});


// AJAX Load by Genre
add_action('wp_ajax_filter_books_by_genre', 'cbp_filter_books_by_genre');
add_action('wp_ajax_nopriv_filter_books_by_genre', 'cbp_filter_books_by_genre');

function cbp_filter_books_by_genre() {
    $genre = isset($_POST['genre']) ? sanitize_text_field($_POST['genre']) : '';

 

      $args = [
    'post_type' => 'book',
    'posts_per_page' => -1,
    'tax_query' => [[
      'taxonomy' => 'genre',
      'field' => 'slug',
      'terms' => $genre
    ]]
  ];
   $query = new WP_Query($args);
   
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $year = get_post_meta(get_the_ID(), '_publication_year', true);
            $rating = get_post_meta(get_the_ID(), '_book_rating', true);
            $authors = wp_get_post_terms(get_the_ID(), 'author', ['fields' => 'names']);
            $genres = wp_get_post_terms(get_the_ID(), 'genre', ['fields' => 'all']);
             echo '<div class="book">';
            echo '<h3>' . get_the_title() . '</h3>';
            echo '<p>Author: ' . implode(', ', $authors) . '</p>';
            echo '<p>Genre: ';
            foreach ($genres as $genre) {
                $genre_link = get_term_link($genre);
                if (!is_wp_error($genre_link)) {
                    echo '<a href="' . esc_url($genre_link) . '" class="genre-link" data-genre="' . esc_attr($genre->slug) . '">' . esc_html($genre->name) . '</a> ';
                }
            }
            echo '</p>';
            echo '<p>Rating: ' . esc_html($rating ?: '0') . ' / 5</p>';
            echo '<p>Published: ' . esc_html($year) . '</p>';
            echo '</div>';

        }
    } else {
        echo '<p>No books found.</p>';
    }
    wp_die();
}

// AJAX Save Rating
add_action('wp_ajax_rate_book', 'rate_book_handler');
add_action('wp_ajax_nopriv_rate_book', 'rate_book_handler');

function rate_book_handler() {
    $book_id = intval($_POST['book_id']);
    $rating  = intval($_POST['rating']);
    $post_id = $book_id;

    if ($book_id && $rating >= 1 && $rating <= 5) {
        update_post_meta($post_id, '_book_rating', $rating);
        echo $rating;
    } else {
        echo 'Invalid';
    }
    wp_die();
}



add_action('wp_ajax_filter_books_by_rating', 'filter_books_by_rating');
add_action('wp_ajax_nopriv_filter_books_by_rating', 'filter_books_by_rating');

function filter_books_by_rating() {
    $rating = intval($_POST['rating']);

    $query = new WP_Query([
        'post_type' => 'book',
        'meta_query' => [
            [
                'key' => '_book_rating',
                'value' => $rating,
                'compare' => '>=',
                'type' => 'NUMERIC',
            ]
        ]
    ]);

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $year = get_post_meta(get_the_ID(), '_publication_year', true);
            $rating = get_post_meta(get_the_ID(), '_book_rating', true);
            $authors = wp_get_post_terms(get_the_ID(), 'author', ['fields' => 'names']);
            $genres = wp_get_post_terms(get_the_ID(), 'genre', ['fields' => 'all']);

            echo '<div class="book">';
            echo '<h3>' . get_the_title() . '</h3>';
            echo '<p>Author: ' . implode(', ', $authors) . '</p>';
            echo '<p>Genre: ';
            foreach ($genres as $genre) {
                $genre_link = get_term_link($genre);
                if (!is_wp_error($genre_link)) {
                    echo '<a href="' . esc_url($genre_link) . '" class="genre-link" data-genre="' . esc_attr($genre->slug) . '">' . esc_html($genre->name) . '</a> ';
                }
            }
            echo '</p>';
            echo '<p>Rating: ' . esc_html($rating ?: '0') . ' / 5</p>';
            echo '<p>Published: ' . esc_html($year) . '</p>';
            echo '</div>';
        }
    } else {
        echo '<p>No books found for this rating.</p>';
    }

    wp_reset_postdata();
    wp_die();
}

