jQuery(document).ready(function ($) {
    $('.originalityai-activation-notice').on('click', '.notice-dismiss', function () {
        $.ajax({
            url: originalityAIDismiss.ajaxurl,
            type: 'POST',
            data: {
                action: 'dismiss_originalityai_notice',
                nonce: originalityAIDismiss.nonce,
            },
            success(response) {
                console.log('Notice dismissed successfully.');
            },
            error() {
                console.error('Failed to dismiss the notice.');
            },
        });
    });
});
