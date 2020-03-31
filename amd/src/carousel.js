define (['jquery', 'format_visualsections/utils'], function($, Utils) {
    return {
        init: function() {
            Utils.whenTrue(function() {
                return typeof($('#section-carousel').carousel) === 'function';
            }).then(function() {
                $('#section-carousel').carousel('pause');
                $('#section-carousel').on('slide.bs.carousel', function () {
                    window.$('#section-carousel').carousel('pause');
                });
            });
        }
    };
});
