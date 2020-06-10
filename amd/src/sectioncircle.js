define(['jquery'], function($) {
    return {
        applySegments: function() {
            $(function() {

                $('.section-circle svg').each(function() {
                    const subtopics = $(this).data('subtopicsjson');
                    const progress = $(this).data('progress');
                    const strokeColor = $(this).data('stroke');
                    setProgress(this, progress);
                    createSegments(this, subtopics, strokeColor);
                });

                function setProgress(sectionCircle, perc) {
                    var progCircle = $(sectionCircle).find('.progress');
                    var radius = $(progCircle).attr('r');
                    var dasharr = 2 * Math.PI * radius;
                    var dashoffset = dasharr * (1 - (perc / 100));
                    $(progCircle).attr('stroke-dasharray', dasharr)
                        .attr('stroke-dashoffset', dashoffset);
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
                    ].join(" ") + " Z";
                }

                function imageHtml(x, y, rectSize, typeCode, link) {
                    let output = `<rect x="${x}" y="${y}" width="${rectSize}" height="${rectSize}" fill="url(#${typeCode})" />`;
                    if (link) {
                        output = `<a href="${link}">${output}</a>`;
                    }
                    return output;
                }

                function createSegments(sectionCircle, segments, strokeColor) {
                    let percMult = 100 / segments.length;
                    let r = $(sectionCircle).find('> circle').attr('r');
                    let rectSize = r;
                    let startAngle = 0;
                    let perc = 0;
                    let arcsEl = $(sectionCircle).find(".arcs");

                    arcsEl.empty();

                    if (segments.length > 1) {
                        for (let s = 0; s < segments.length; s++) {
                            perc += percMult;
                            let endAngle = (Math.PI * 2) / (100 / perc);
                            $("<path />")
                                .attr("d", createSvgArc(0, 0, r, startAngle, endAngle * 2))
                                .attr("stroke", strokeColor)
                                .attr("stroke-width", "6")
                                .attr("fill", "none")
                                //.attr("fill-opacity", "0.4")
                                .appendTo($(arcsEl));
                            $(arcsEl).html($(arcsEl).html());
                            startAngle += endAngle;
                        }
                    }

                    switch (segments.length) {
                        case 1: rectSize = r*1.3; break;
                        case 2: rectSize = r/1.25; break;
                        case 3: rectSize = r/1.5; break;
                        case 4: rectSize = r/1.75; break;
                        case 5: rectSize = r/1.75; break;
                    }

                    if (segments.length > 1) {
                        for (let s = 0; s < segments.length; s++) {
                            var angle = Math.PI / segments.length;
                            var phi = Math.PI / (r) + angle * s * 2;
                            phi += Math.PI / segments.length;

                            var x = (r/2) * Math.cos(phi);
                            var y = (r/2) * Math.sin(phi);

                            if (segments.length === 5) {
                                x = (r/1.75) * Math.cos(phi);
                                y = (r/1.75) * Math.sin(phi);
                            }

                            // Rectangles start from the top left corner.
                            // This code centers the rectangle.
                            x-=rectSize / 2;
                            y-=rectSize / 2;

                            // Deal with divided by 2 nicer.
                            if (segments.length === 2) {
                                if (s === 1) {
                                    y = - (rectSize + 20);
                                } else {
                                    y = 20;
                                }
                            }
                            const typeCode = segments[s].typecode;
                            const link = segments[s].link ? segments[s].link : null;

                            $(imageHtml(x, y, rectSize, typeCode, link))
                                .appendTo($(arcsEl));
                            $(arcsEl).html($(arcsEl).html());
                        }
                    } else if (segments.length === 1) {
                        const typeCode = segments[0].typecode;
                        var x = -rectSize/2;
                        var y = -rectSize/2;
                        const link = segments[0].link ? segments[0].link : null;
                        $(imageHtml(x, y, rectSize, typeCode, link))
                            .appendTo($(arcsEl));
                        $(arcsEl).html($(arcsEl).html());
                    }
                }
            });
        }
    };

});