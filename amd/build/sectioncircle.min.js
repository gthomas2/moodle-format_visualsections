define(['jquery'], function($) {
    return {
        applySegments: function() {
            $(function() {

                $('.sectionCircle').each(function() {
                    var segments = $(this).data('segments');
                    var progress = $(this).data('progress');
                    setProgress(this, progress);
                    createSegments(this, segments);
                });

                function setProgress(sectionCircle, perc) {
                    var progCircle = $(sectionCircle).find('.progress');
                    var radius = $(progCircle).attr('r');
                    var dasharr = 2 * Math.PI * radius;
                    var dashoffset = dasharr * (1 - (perc / 100));
                    $(progCircle).attr('stroke-dasharray', dasharr)
                        .attr('stroke-dashoffset', dashoffset);
                }

                function createSegments(sectionCircle, segments) {

                    var percMult = 100 / segments;

                    var startAngle = 0;

                    var perc = 0;

                    var arcsEl = $(sectionCircle).find(".arcs");

                    arcsEl.empty();

                    for (var s = 0; s < segments; s++) {
                        perc += percMult;
                        var endAngle = (Math.PI * 2) / (100 / perc);



                        $("<path />")
                            .attr("d", createSvgArc(0, 0, 300, startAngle, endAngle * 2))
                            .attr("fill", "none")
                            .attr("stroke", "red")
                            .attr("stroke-width", "4")
                            .appendTo($(arcsEl));



                        $(arcsEl).html($(arcsEl).html());
                        startAngle += endAngle;

                    }



                }

                function createSvgArc(x, y, r, startAngle, endAngle) {
                    if (startAngle > endAngle) {
                        var s = startAngle;
                        startAngle = endAngle;
                        endAngle = s;
                    }
                    if (endAngle - startAngle > Math.PI * 2) {
                        endAngle = Math.PI * 1.99999;
                    }

                    var largeArc = endAngle - startAngle <= Math.PI ? 0 : 1;

                    return [
                        "M",
                        x,
                        y,
                        "L",
                        x + Math.cos(startAngle) * r,
                        y - Math.sin(startAngle) * r,
                        "A",
                        r,
                        r,
                        0,
                        largeArc,
                        0,
                        x + Math.cos(endAngle) * r,
                        y - Math.sin(endAngle) * r,
                        "L",
                        x,
                        y
                    ].join(" ");
                }


            });

        }
    };

});