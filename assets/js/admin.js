jQuery(function ($) {
    'use strict';

    const couponExpiryWrapper = $('.expiry_date_field'),
        allowSubscriptionsWrapper = $(
            '.brc_allow_subscriptions_wrapper'
        ),
        allowSubscriptionsField = $(
            '#brc_allow_subscriptions'
        )

    $('#discount_type').on('change', function () {
        updateStatus();
    });

    allowSubscriptionsField.change(function () {
        updateStatus();
    });

    function updateStatus() {
        const selectedOption = $('#discount_type').find(':selected').val();
        if (
            ['fixed_product', 'percent'].indexOf(
                selectedOption
            ) >= 0
        ) {
            allowSubscriptionsWrapper.show();
        } else {
            allowSubscriptionsField.prop('checked', false);
            allowSubscriptionsWrapper.hide();
        }
    }

    allowSubscriptionsWrapper.insertAfter(couponExpiryWrapper);
    updateStatus();
});
