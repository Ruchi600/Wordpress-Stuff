jQuery(document).ready(function($) {
    // $('.genre-link').click(function(e) {
    //     e.preventDefault();
    //     var genre = $(this).data('genre');
    //     $.post(book_ajax.ajaxurl, { action: 'filter_books_by_genre', genre }, function(response) {
    //         $('.book-info').html(response);
    //     });
    // });

    $('.rate-book').change(function() {
        var bookId = $(this).data('id');
        var rating = $(this).val();
        $.post(book_ajax.ajaxurl, { action: 'rate_book', book_id: bookId, rating }, function(response) {
            $('[data-id="'+bookId+'"].book-rating').text(response);
        });
    });

    $('#rating-filter').change(function() {
        var rating = $(this).val();
        $.post(book_ajax.ajaxurl, { action: 'filter_books_by_rating', rating }, function(response) {
            $('.book-info').html(response);
        });
    });
});

jQuery(document).ready(function($) {
    $(document).on('click', '.genre-filter-btn', function(e) {
        e.preventDefault();
        const genre = $(this).data('genre');
        console.log(genre);

        $.ajax({
            url: book_ajax.ajaxurl, // ✅ use the localized object
            type: 'POST',
            data: {
                action: 'filter_books_by_genre',
                genre_slug: genre
            },
            success: function(response) {
                $('#book-filter-container').html(response);
            }
        });
    });
});
