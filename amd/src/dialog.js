define(["core/modal_factory", "core/modal_events"], function(ModalFactory, ModalEvents) {
    return {
        error: function(title, body, onClose) {
            ModalFactory.create({
                type: ModalFactory.types.DEFAULT,
                title: title,
                body: body
            })
            .then(function(modal) {
                modal.setSmall();
                modal.getRoot().on(ModalEvents.hidden, function() {
                    modal.destroy();
                    if (typeof(onClose) === 'function') {
                        onClose();
                    }
                });
                modal.show();
            });
        }
    };
});