define(
    [
        "jquery",
        "core/yui",
        "core/modal_factory",
        "core/modal_events",
        "core/ajax",
        "core/fragment",
        "core/templates",
        "core/str",
        "core/event",
        "format_visualsections/utils",
        "format_visualsections/sectioncircle",
        "format_visualsections/carousel",
        "format_visualsections/dialog"
    ],
    function($, Y, ModalFactory, ModalEvents, Ajax, Fragment, Templates, Str, Event, Utils, SectionCircle, Carousel, Dialog) {
    var consts = {
        SUBSECTION_FORM_CONT: "subsectionformcontainer",
        CAROUSEL_CONT: "section-carousel-content",
        FOOTER: "format-footer"
    };
    return {
        init: function(courseId, capViewAllGrades, defaultSection) {
            var modalSubsection = null;
            var sectionId = null;

            SectionCircle.applySegments(courseId);

            Y.use("dd-drop", function(Y) {
                $(".subsection").each(function() {
                    new Y.DD.Drop({
                        node: "#"+$(this).attr("id")
                    });
                });
            });

            var loadSubsectionForm = function(data) {
                if (typeof data !== "string" && !(data instanceof String)) {
                    data = new URLSearchParams(data).toString();
                }
                const contextid = M.cfg.contextid;
                const params = data ? {formdata: data} : null;

                return Fragment.loadFragment("format_visualsections", "subsection_form", contextid, params)
                    .done(function(html, js) {
                        if (!$("#" + consts.SUBSECTION_FORM_CONT).length) {
                            return;
                        }
                        Templates.replaceNodeContents(
                            $("#" + consts.SUBSECTION_FORM_CONT),
                            html,
                            js
                        );
                        const form = "#" + consts.SUBSECTION_FORM_CONT + " form";
                        $(form).on("submit", function(e) {
                           e.preventDefault();
                           return false;
                        });
                    });
            };

            var initModal = function() {
                var dfd = $.Deferred();
                var bodyHTML = `<div id="${consts.SUBSECTION_FORM_CONT}"></div>`;

                if (!modalSubsection) {
                    ModalFactory.create({
                        title: "",
                        type: "SAVE_CANCEL",
                        body: bodyHTML,
                        large: true,
                    }).then(function(modal) {
                        modalSubsection = modal;
                        modal.getRoot().on(ModalEvents.save, function(e) {
                            e.preventDefault(); // We don"t want to close the modal yet.
                            var form = $("#" + consts.SUBSECTION_FORM_CONT + " form");
                            $(form).trigger("save-form-state");

                            var data = $(form).serialize();
                            loadSubsectionForm(data)
                                .then(function() {
                                    const modalEl = modal.getModal();
                                    if (modalEl.find(".alert-success").length) {
                                        const subsectionId = modalEl.find("form input[name='id']").val();
                                        window.onbeforeunload = null;
                                        window.location = M.cfg.wwwroot+"/course/view.php?id="+courseId+"#subsection"+subsectionId;
                                        modal.hide();
                                        window.location.reload();
                                    }
                                });

                        });
                        modal.getRoot().on(ModalEvents.cancel, function() {
                            modal.hide();
                        });
                        modal.getRoot().on(ModalEvents.hide, function() {
                            modal.setBody("");
                        });
                        dfd.resolve(modal);
                    });
                } else {
                    dfd.resolve(modalSubsection);
                }
                return dfd;
            };

            var addSubsectionModal = function(el) {
                sectionId = $(el).data("sectionid");
                var section = $(el).closest(".section");
                var subSectionCount = section.find(".subsections .subsection").length;

                if (subSectionCount >= 5) {
                    Str.get_string("toomanysubsections", "format_visualsections")
                        .then(function(str) {
                            Dialog.error(
                                str,
                                ''
                            );
                        });
                    return;
                }

                initModal()
                .then(function() {
                    return  Str.get_strings([
                        {key: "addsubsection", component: "format_visualsections"}
                    ]);
                })
                .then(function(strings) {
                    modalSubsection.setTitle(strings[0]);
                    modalSubsection.show();
                    $("#"+consts.SUBSECTION_FORM_CONT).html(`<div class="spinner"></div>`);
                    loadSubsectionForm({parentid: sectionId});
                });
            };

            $("body").on("click", ".js-add-subsection", function() {addSubsectionModal(this);});

            var editSubsectionModal = function(el) {
                var subSectionId = $(el).closest(".subsection").data("subsection-id");
                initModal().then(function() {
                    return  Str.get_strings([
                        {key: "editsubsection", component: "format_visualsections"}
                    ]);
                }).then(function(strings) {
                    modalSubsection.setTitle(strings[0]);
                    modalSubsection.show();
                    $("#"+consts.SUBSECTION_FORM_CONT).html(`<div class="spinner"></div>`);
                    loadSubsectionForm({id: subSectionId, course: courseId});
                });

            };

            $("body").on("click", ".js-edit-subsection", function() {editSubsectionModal(this);});

            var applySubsectionHash = function(hash) {
                $("#section-carousel").addClass("carousel-ready");
                var subsectionSel = hash+" > .section";
                Utils.whenTrue(function() {
                    return typeof(window.$(subsectionSel).collapse) === "function";
                }).then(function() {
                    window.$(subsectionSel).collapse("show");
                    setTimeout(function() {
                        const nav = $(".fixed-top.navbar");
                        // We need to account for a fixed navbar.
                        // If there is one then scroll the page to the current hashed
                        // item but subtract the top by the height of the fixed nav bar.
                        var subtractTop = nav.length ? nav.outerHeight() : 40;
                        $("html, body").animate({
                            scrollTop: $(hash).offset().top - subtractTop
                        }, 200);
                    }, 200);

                });
            };

            var applySectionHash = function(hash) {
                let section = $(hash);
                $("ul.visualsections > li.section").removeClass("active");
                $(hash).addClass("active");

                let sectionHash;
                const subSectionCont = $(section).parents('div.subsection');
                if (subSectionCont.length) {
                    // This is a subsection. We want the parent section to be active too.
                    var parent =  $(subSectionCont).parents('li.section');
                    $(parent).addClass("active");
                    // Also the carousel item needs to be the parent section.
                    sectionHash = $(parent).attr('id');
                } else {
                    sectionHash = hash;
                }

                // Make carousel active.
                const regex = /(?:section)-(\d+)$/;
                const match = sectionHash.match(regex);
                const sectionNum = match[1];
                const carouselItem = $("#section-circle-" + sectionNum).closest(".carousel-item");

                $("#section-carousel .carousel-item").removeClass("active");

                carouselItem.addClass("active");
                let c = 0;
                let pos = 0;
                $("#section-carousel .carousel-item").each(function() {
                    c++;
                    if ($(this).hasClass("active")) {
                        pos = c;
                    }
                });

                $("#section-carousel .carousel-indicators li").removeClass("active");
                $("#section-carousel .carousel-indicators li:nth-child(" + pos + ")").addClass("active");

                $("#section-carousel").addClass("carousel-ready");

                const params = {courseid: courseId, section: sectionNum};
                const contextId = M.cfg.contextid;
                Fragment.loadFragment("format_visualsections", "footer", contextId, params)
                    .done(function(html, js) {
                        if (!$("#" + consts.FOOTER).length) {
                            return;
                        }
                        Templates.replaceNode (
                            $("#" + consts.FOOTER),
                            html,
                            js
                        );
                        $(`#${consts.FOOTER} button[data-toggle="tooltip"]`).tooltip();
                    });
            };

            var applyHash = function(calledOnDomReady) {
                const hash = location.hash;
                if (hash.indexOf("#subsection") === 0) {
                    applySubsectionHash(hash);
                } else if (hash.indexOf("#section") === 0) {
                    applySectionHash(hash);
                } else if (calledOnDomReady && !capViewAllGrades) {
                    // This is a student, open the default section for this student.
                    location.hash = "#"+defaultSection;
                } else {
                    $("#section-carousel").addClass("carousel-ready");
                }
            };

            $("document").ready(function(e) {
                applyHash(true, e);
            });

            $(window).on("hashchange", function(e) {
                applyHash(false, e);
            });

            // Track activity completion toggles.
            let activityCompletionToggled = false;
            $("body").on("click", ".togglecompletion", function() {
                activityCompletionToggled = true;
            });

            Event.getLegacyEvents().done(function(events) {
                $(document).on(events.FILTER_CONTENT_UPDATED, function() {
                    // Note: replaceNodeContents triggers FILTER_CONTENT_UPDATED,
                    // so we have to use activityCompletionToggled to be more specific.
                    if (!activityCompletionToggled) {
                        return;
                    }
                    activityCompletionToggled = false;

                    // Reload carousel.
                    $('#section-carousel').removeClass('carousel-ready');
                    const params = {courseid: courseId};
                    const contextId = M.cfg.contextid;

                    Fragment.loadFragment("format_visualsections", "carousel", contextId, params)
                        .done(function(html, js) {
                            if (!$("#" + consts.CAROUSEL_CONT).length) {
                                return;
                            }
                            Templates.replaceNodeContents(
                                $("#" + consts.CAROUSEL_CONT),
                                html,
                                js
                            );
                            SectionCircle.applySegments(courseId);
                            Carousel.init();
                            applyHash();
                        });
                });
            });

            $("body").on("click", `#${consts.FOOTER} .js-nav`, function(e) {
                e.preventDefault();
                if ($(this).attr('disabled')) {
                    return;
                }
                const section = $(this).data('section');
                location.hash = "#section-" + section;
            });

            $("body").on("click", ".js-move-subsection", function() {
                const direction = $(this).data("direction");
                const src = $(this).closest("div.subsection");
                const srcSectionId = $(src).data("subsection-id");
                let target = null;

                if (direction === "up") {
                    target = $(src).prev("div.subsection");
                } else {
                    target = $(src).next("div.subsection");
                }
                let parentSection = $(this).closest("li.section");
                let parentSectionId = $(parentSection).data("section-id");
                let targetSectionId = null;

                if (target.length) {
                    if (direction === "up") {
                        $(src).after(target);
                    } else {
                        $(src).before(target);
                    }
                    targetSectionId = $(target).data("subsection-id");
                } else {
                    let targetParentSection = null;
                    if (direction === "up") {
                        targetParentSection = $(parentSection).prev("li.section");
                    } else {
                        targetParentSection = $(parentSection).next("li.section");
                    }
                    if (targetParentSection.length) {
                        targetSectionId = srcSectionId; // We just want to dump it into parent section.
                    } else {
                        return; // Can"t move any further.
                    }
                    parentSection = targetParentSection;
                    parentSectionId = parentSection.data("section-id");
                    parentSection.find(".subsections").append(src);
                }

                Ajax.call([
                    {
                        methodname: "format_visualsections_move_subtopic",
                        args:{
                            "request": {
                                "parentsectionid": parentSectionId,
                                "srcsectionid": srcSectionId,
                                "targetsectionid": targetSectionId
                            }
                        }
                    }
                ])[0]
                    .then(function(result) {
                        if (!result.success) {
                            Str.get_string("failedtomovesubsection", "format_visualsections")
                                .then(function(str) {
                                    Dialog.error(
                                        str,
                                        ''
                                    );
                                });
                        }
                    });
            });
        }
    };
});