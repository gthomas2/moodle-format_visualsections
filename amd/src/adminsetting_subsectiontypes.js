define(["jquery",
    "core/templates",
    "core/modal_factory",
    "core/modal_events",
    "core/fragment",
    "core/ajax",
    "format_visualsections/dialog",
    "core/str"
], function($, Templates, ModalFactory, ModalEvents, Fragment, Ajax, Dialog, Str) {
    return {
        init: function() {
            var rewriteDataField = function() {
                var data = [];
                $('.subsectiontypes ul li').each(function() {
                    var code = $(this).find('[name*="code"]').val();
                    var name = $(this).find('[name*="name"]').val();
                    var image = $(this).find('button.imagethumb').data('imgsrc');
                    if (typeof(image) === 'undefined') {
                        image = null;
                    }
                    code = code.replace('|', ' '); // No pipes allowed.
                    name = name.replace('|', ' '); // No pipes allowed.
                    data.push(code+'|'+name+'|'+image);
                });
                var dataField = $('.subsectiontypes .js-data-field');
                $(dataField).val(data.join("\n"));
            };

            // Listen for add sub section.
            $('.subsectiontypes').on('click', '.js-addsubsection', function(e) {
                e.preventDefault();
                var greatestPos = 0;

                $('.subsectiontypes .subsectiontyperow').each(function() {
                    var pos = $(this).data('pos');
                    if (pos && pos > greatestPos) {
                        greatestPos = pos;
                    }
                });

                var data = {
                    pos: greatestPos + 1,
                    code: null,
                    name: null,
                    image: null
                };
                Templates.render('format_visualsections/subsectiontyperow', data)
                    .then(function(html) {
                        $('.subsectiontypes ul').append(html);
                    });
            });

            // Listen for field value changes.
            $('.subsectiontypes').on('keyup change', 'input', function(e) {
                e.preventDefault();
                rewriteDataField();
            });

            // Listen remove sub section.
            $('.subsectiontypes').on('click', '.js-removesubsection', function(e) {
                e.preventDefault();
                $(this).parent('li').remove();
                rewriteDataField();
            });

            var imageModal = null;

            var showImageModal = function() {
                $('#image-upload').html('<div class="spinner"></div>');
                imageModal.show();
                return Fragment.loadFragment("format_visualsections", "filepicker", M.cfg.contextid, [])
                    .done(function(html, js) {
                        if (!$("#image-upload").length) {
                            return;
                        }
                        Templates.replaceNodeContents(
                            $("#image-upload"),
                            html,
                            js
                        );
                    });
            };

            var addingModal = false;

            var addImageModal = function(rowEl, rowData) {
                if (imageModal) {
                    imageModal.rowEl = rowEl;
                    imageModal.rowData = rowData;
                    showImageModal();
                    return;
                }
                if (addingModal) {
                    return;
                }
                addingModal = true;
                ModalFactory.create({
                    type: ModalFactory.types.SAVE_CANCEL,
                    title: "Add image",
                    body: `<div id="image-upload"></div>`
                })
                .then(function(modal) {
                    imageModal = modal;
                    imageModal.setSmall();
                    imageModal.rowEl = rowEl;
                    imageModal.rowData = rowData;
                    showImageModal();

                    // Handle save event.
                    imageModal.getRoot().on(ModalEvents.save, function() {
                        const draft = $(".filepicker-filename a").attr("href");
                        const regex = /(?:draftfile.php\/)(.*)$/;
                        const found = draft.match(regex);
                        const filecomps = found[1].split('/');
                        const draftItemId = filecomps[3];
                        const fileName = filecomps[4];

                        return Ajax.call([
                            {
                                methodname: "format_visualsections_subtopic_addimage",
                                args:{
                                    "request": {"draftitemid": draftItemId, "filename": fileName}
                                }
                            }
                        ])[0]
                            .then(function(result) {
                                if (!result.success) {
                                    Str.get_string("failedtouploadimage", "format_visualsections")
                                        .then(function(str) {
                                            Dialog.error(
                                                str,
                                                ''
                                            );
                                        });
                                }
                                var imgSrc = result.imagefile;
                                modal.rowData.image = imgSrc;
                                Templates.render('format_visualsections/subsectiontyperow', modal.rowData)
                                    .then(function(html) {
                                        $(modal.rowEl).replaceWith(html);
                                        rewriteDataField();
                                    });
                            });
                    });

                    modal.getRoot().on(ModalEvents.hidden, function() {
                        $("#image-upload").html("");
                    });
                });
            };

            // Listen add image.
            $('.subsectiontypes').on('click', '.js-setimage', function(e) {
                e.preventDefault();
                const row = $(this).parent('.subsectiontyperow');
                const code = $(row).find('[name*="code"]').val();
                const name = $(row).find('[name*="name"]').val();
                const image = $(this).data('imgsrc');
                const rowData = {
                    code: code,
                    name: name,
                    image: image
                };

                addImageModal(row, rowData);
            });

            $('.subsectiontypes').addClass('jsloaded');
        }
    };
});