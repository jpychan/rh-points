// Redeem button on cart and checkout pages
jQuery(document).ready(function($) {
    let update_redeem_button = false;

    $('#redeem-points-button').on('click', function(e) {
        e.preventDefault();

        var points_to_redeem = $(this).data('points-to-redeem');
        $.ajax({
            url: wc_add_to_cart_params.wc_ajax_url.toString().replace('%%endpoint%%', 'apply_points_discount'),
            type: 'POST',
            dataType: 'json',
            data: {
                points: points_to_redeem,
                security: rogershood_ajax.redeem_points_nonce,
            },
            success: function(response) {
                if (response.success) {
                    update_redeem_button = true;
                }

                if ($('body').hasClass('woocommerce-cart')) {
                    $('body').trigger('wc_update_cart');
                }
                else if ($('body').hasClass('woocommerce-checkout')) {
                    $('body').trigger('update_checkout');
                }
            },
            error: function(error) {
            }
        });
    });

    $('body').on('applied_coupon_in_checkout', function() {
        update_redeem_button = true;
    });

    $('body').on('updated_wc_div updated_checkout', function(data) {

        if (!update_redeem_button) {
            return;
        }

        let cartContentTotal = $('tr.cart-subtotal .woocommerce-Price-amount').text().replace('$', '');
        let discount = $('tr.cart-discount .woocommerce-Price-amount').text().replace('$', '');
        let redeemedPointsAmount = $('tr.fee .woocommerce-Price-amount bdi').text().replace('–$', '');
        let totalAfterCoupon = cartContentTotal - discount;
        let subTotal = totalAfterCoupon - redeemedPointsAmount;
        let totalPointsRequired = subTotal * 500;
        let pointsRequired = totalPointsRequired - redeemedPointsAmount;
        let userPoints = $('#redeem-points-button').data('user-points');
        let remainingUserPoints = userPoints - (redeemedPointsAmount * 500);

        if (remainingUserPoints < 500) {
            $('#redeem-points-container').hide();
            update_redeem_button = false;
            return;
        }

        let pointsToRedeem = userPoints > totalPointsRequired ? pointsRequired : userPoints;
        let discountAmount = userPoints > totalPointsRequired ? subTotal : userPoints / 500;

       $('#redeem-points-button').text(`Redeem ${pointsToRedeem} Points for $${discountAmount} off`);
       $('#redeem-points-button').data('points-to-redeem', pointsToRedeem);

       if (subTotal > 0) {
          $('#redeem-points-container').show();
       }
       else {
          $('#redeem-points-container').hide();
       }

       update_redeem_button = false;
    });

    $('body').on('removed_coupon_in_checkout', function() {
        update_redeem_button = true;
    });

    $(document).on('click', '#remove_points_discount', function(e) {
        e.preventDefault();

        // Make an AJAX request to remove the points discount
        $.ajax({
            url: wc_add_to_cart_params.wc_ajax_url.toString().replace('%%endpoint%%', 'remove_points_discount'),
            type: 'POST',
            dataType: 'json',
            data: {
                security: rogershood_ajax.remove_points_nonce,
            },
            success: function(response) {
                if (response.success) {
                    update_redeem_button = true;
                } else {
                }

                // Trigger WooCommerce to recalculate the cart/checkout totals
                if ($('body').hasClass('woocommerce-cart')) {
                    $('body').trigger('wc_update_cart');
                }
                else if ($('body').hasClass('woocommerce-checkout')) {
                    $('body').trigger('update_checkout');
                }
            },
            error: function() {
            }
        });
    });
});
