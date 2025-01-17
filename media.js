
jQuery(document).ready(function ($) {
    var mediaUploader;

    $('#select_image_button').click(function (e) {
        e.preventDefault();

        if (mediaUploader) {
            mediaUploader.open();
            return;
        }
        mediaUploader = wp.media({
            title: 'Select Image',
            button: {
                text: 'Use This Image',
            },
            multiple: false,
            library: {
                type: 'image',
            },
        });
        mediaUploader.on('select', function () {
            var attachment = mediaUploader.state().get('selection').first().toJSON();

            $('#product_image_id').val(attachment.id);

            $('#product_image_preview').html(
                '<img src="' + attachment.url + '" style="max-width: 100px; height: auto;" />'
            );
        });
        mediaUploader.open();
    });
});




