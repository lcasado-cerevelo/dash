
$(document).ready(function() {
    $('.icon-repeater').each(function() {
        $(this).repeater({
            show: function () {
                $(this).slideDown();
                const newId = 'icon_' + Date.now() + '_' + Math.floor(Math.random() * 1000);
                const item = $(this);

                // Update all IDs
                item.find('[id*="icon_"]').each(function() {
                    const oldId = this.id;
                    const newFullId = oldId.replace(/icon_[^_]*_[^_]*/, newId);
                    this.id = newFullId;
                });

                // Update onclick
                item.find('button[onclick*="openIconModal"]').attr('onclick', 'openIconModal("' + newId + '")');

                // Reset image preview for new items
                item.find('.image-preview').hide().find('img').attr('src', '');

            },
            hide: function (deleteElement) {
                $(this).slideUp(deleteElement);
            }
        });
    });
});

function previewImage(input) {
    const preview = $(input).closest('[data-repeater-item]').find('.image-preview').first();
    const img = preview.find('img');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            img.attr('src', e.target.result);
            preview.show();
        };
        reader.readAsDataURL(input.files[0]);
    } else {
        preview.hide();
        img.attr('src', '');
    }
}
