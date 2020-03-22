define (['jquery'], function($) {
    return {
        init: function() {
            $('#section-carousel').carousel('pause');
            $('#section-carousel').on('slide.bs.carousel', function () {
                $('#section-carousel').carousel('pause');
            });
        }
    };
})
