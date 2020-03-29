define(['jquery', 'core/yui', 'core/modal_factory', 'core/modal_events', 'core/ajax', 'core/fragment', "core/templates", "format_visualsections/utils", "core/str"],
    function($, Y, ModalFactory, ModalEvents, Ajax, Fragment, Templates, Utils, Str) {
    var consts = {
        SUBSECTION_FORM_CONT: 'subsectionformcontainer'
    };
    return {
        init: function(courseId, capUpdateCourse, defaultSection) {
            var modalSubsection = null;
            var sectionId = null;

            Y.use('dd-drop', function(Y) {
                $('.subsection').each(function() {
                    new Y.DD.Drop({
                        node: '#'+$(this).attr('id')
                    });
                });
            });

            var loadSubsectionForm = function(data) {
                if (typeof data !== 'string' && !(data instanceof String)) {
                    data = new URLSearchParams(data).toString();
                }
                const contextid = M.cfg.contextid;
                const params = data ? {formdata: data} : null;

                return Fragment.loadFragment('format_visualsections', 'subsection_form', contextid, params)
                    .done(function(html, js) {
                        if (!$("#" + consts.SUBSECTION_FORM_CONT).length) {
                            return;
                        }
                        Templates.replaceNodeContents(
                            $("#" + consts.SUBSECTION_FORM_CONT),
                            html,
                            js
                        );
                    });
            };

            var initModal = function() {
                var dfd = $.Deferred();
                var bodyHTML = '<div id="'+consts.SUBSECTION_FORM_CONT+'"></div>';

                if (!modalSubsection) {
                    ModalFactory.create({
                        title: '',
                        type: 'SAVE_CANCEL',
                        body: bodyHTML,
                        large: true,
                    }).then(function(modal) {
                        modalSubsection = modal;
                        modal.getRoot().on(ModalEvents.save, function(e) {
                            e.preventDefault(); // We don't want to close the modal yet.
                            var form = $('#' + consts.SUBSECTION_FORM_CONT + ' form');
                            $(form).trigger('save-form-state');

                            var data = $(form).serialize();
                            loadSubsectionForm(data)
                                .then(function() {
                                    const modalEl = modal.getModal();
                                    if (modalEl.find('.alert-success').length) {
                                        const subsectionId = modalEl.find('form input[name="id"]').val();
                                        window.onbeforeunload = null;
                                        window.location = M.cfg.wwwroot+'/course/view.php?id='+courseId+'#subsection'+subsectionId;
                                        modal.hide();
                                        window.location.reload();
                                    }
                                });

                        });
                        modal.getRoot().on(ModalEvents.cancel, function() {
                            modal.hide();
                        });
                        modal.getRoot().on(ModalEvents.hide, function() {
                            modal.setBody('');
                        });
                        dfd.resolve(modal);
                    });
                } else {
                    dfd.resolve(modalSubsection);
                }
                return dfd;
            };

            var addSubsectionModal = function(el) {
                sectionId = $(el).data('sectionid');
                var section = $(el).closest('.section');
                var subSectionCount = section.find('.subsections .subsection').length;

                if (subSectionCount >= 5) {
                    // TODO
                    alert ('too many sections');
                    return;
                }

                initModal()
                .then(function() {
                    return  Str.get_strings([
                        {key: 'addsubsection', component: 'format_visualsections'}
                    ]);
                })
                .then(function(strings) {
                    modalSubsection.setTitle(strings[0]);
                    modalSubsection.show();
                    $('#'+consts.SUBSECTION_FORM_CONT).html('<div class="spinner"></div>');
                    loadSubsectionForm({parentid: sectionId});
                });
            };

            $('body').on('click', '.js-add-subsection', function() {addSubsectionModal(this);});

            var editSubsectionModal = function(el) {
                var subSectionId = $(el).closest('.subsection').data('subsection-id');
                initModal().then(function() {
                    return  Str.get_strings([
                        {key: 'editsubsection', component: 'format_visualsections'}
                    ]);
                }).then(function(strings) {
                    modalSubsection.setTitle(strings[0]);
                    modalSubsection.show();
                    $('#'+consts.SUBSECTION_FORM_CONT).html('<div class="spinner"></div>');
                    loadSubsectionForm({id: subSectionId, course: courseId});
                });

            };

            $('body').on('click', '.js-edit-subsection', function() {editSubsectionModal(this);});

            var applySubsectionHash = function(hash) {
                var subsectionSel = hash+" > .section";
                Utils.whenTrue(function() {
                    return typeof(window.$(subsectionSel).collapse) === 'function';
                }).then(function() {
                    window.$(subsectionSel).collapse('show');
                    setTimeout(function() {
                        const nav = $('.fixed-top.navbar');
                        // We need to account for a fixed navbar.
                        // If there is one then scroll the page to the current hashed
                        // item but subtract the top by the height of the fixed nav bar.
                        var subtractTop = nav.length ? nav.outerHeight() : 40;
                        $('html, body').animate({
                            scrollTop: $(hash).offset().top - subtractTop
                        }, 200);
                    }, 200);

                });
            };

            var applySectionHash = function(hash) {
                $('ul.visualsections > li.section').removeClass('active');
                $(hash).addClass('active');
            };

            var applyHash = function(calledOnDomReady) {
                const hash = location.hash;
                if (hash.indexOf('#subsection') === 0) {
                    applySubsectionHash(hash);
                } else if (hash.indexOf('#section') === 0) {
                    applySectionHash(hash);
                } else if (calledOnDomReady && !capUpdateCourse) {
                    // This is a student, open the default section for this student.
                    location.hash = '#'+defaultSection;
                }
            };

            $('document').ready(function(e) {
                applyHash(true, e);
            });

            $(window).on('hashchange', function(e) {
                applyHash(false, e);
            });

            $('body').on('click', '.js-move-subsection', function(e) {
                const direction = $(this).data('direction');
                const src = $(this).closest('div.subsection');
                const srcSectionId = $(src).data('subsection-id');
                let target = null;

                if (direction === 'up') {
                    target = $(src).prev('div.subsection');
                } else {
                    target = $(src).next('div.subsection');
                }
                let parentSection = $(this).closest('li.section');
                let parentSectionId = $(parentSection).data('section-id');
                let targetSectionId = null;

                if (target.length) {
                    if (direction === 'up') {
                        $(src).after(target);
                    } else {
                        $(src).before(target);
                    }
                    targetSectionId = $(target).data('subsection-id');
                } else {
                    let targetParentSection = null;
                    if (direction === 'up') {
                        targetParentSection = $(parentSection).prev('li.section');
                    } else {
                        targetParentSection = $(parentSection).next('li.section');
                    }
                    if (targetParentSection.length) {
                        targetSectionId = srcSectionId; // We just want to dump it into parent section.
                    } else {
                        return; // Can't move any further.
                    }
                    parentSection = targetParentSection;
                    parentSectionId = parentSection.data('section-id');
                    parentSection.find('.subsections').append(src);
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
                            // TODO - localise.
                            alert ('Error: failed to move sub section');
                        }
                    });
            });
        }
    };
});