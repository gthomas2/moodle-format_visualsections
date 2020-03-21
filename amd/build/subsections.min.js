define(['jquery', 'core/modal_factory', 'core/modal_events', 'core/ajax', 'core/fragment', "core/templates", "format_visualsections/utils"],
    function($, ModalFactory, ModalEvents, Ajax, Fragment, Templates, Utils) {
    var consts = {
        SUBSECTION_FORM_CONT: 'subsectionformcontainer'
    };
    return {
        init: function(courseId) {
            var modalAddSubsection = null;
            var sectionId = null;

            var loadSubsectionForm = function(data) {
                if (typeof data !== 'string' && !(data instanceof String)) {
                    data = new URLSearchParams(data).toString();
                }
                const rx = new RegExp('(?:context-)(\\S)');
                const result = rx.exec($('body').attr('class'));
                const contextid = parseInt(result[1]);
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

            var addSubsectionModal = function(el) {
                sectionId = $(el).data('sectionid');
                var section = $(el).closest('.section');
                var subSectionCount = section.find('.subsections .subsection').length;

                if (subSectionCount >= 5) {
                    // TODO
                    alert ('too many sections');
                    return;
                }

                var bodyHTML = '<div id="'+consts.SUBSECTION_FORM_CONT+'"></div>';

                var showModal = function() {
                    modalAddSubsection.show();
                    loadSubsectionForm({parentid: sectionId});
                };

                if (!modalAddSubsection) {
                    ModalFactory.create({
                        title: 'Add subsection',
                        type: 'SAVE_CANCEL',
                        body: bodyHTML,
                        large: true,
                    }).then(function(modal) {
                        modalAddSubsection = modal;
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
                        showModal();
                    });
                } else {
                    showModal();
                }
            };

            $('body').on('click', '.js-add-subsection', function() {addSubsectionModal(this);});

            $('document').ready(function() {
                const hash = window.location.hash;
                if (hash.indexOf('subsection') > -1) {
                    var subsectionSel = hash+" > .section";
                    Utils.whenTrue(function() {
                        return typeof(window.$(subsectionSel).collapse) === 'function';
                    }).then(function() {
                        window.$(subsectionSel).collapse('show');
                    });
                }
            });
        }
    };
});