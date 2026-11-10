<?php
/**
 * Order received — the close of the shopping journey. Everything shown comes from the real order: number, date,
 * total, payment method and the payment state as WooCommerce records it. WooCommerce's and the payment gateway's
 * own hooks run unchanged (they print the item table and customer details, and let gateways finish their work).
 *
 * @var WC_Order|false $order
 */

defined('ABSPATH') || exit;
?>
<div class="woocommerce-order ty">
<?php if ($order): do_action('woocommerce_before_thankyou', $order->get_id()); ?>

  <?php if ($order->has_status('failed')): ?>
    <section class="ty-head is-failed" role="alert">
      <p class="ty-kicker"><?php esc_html_e('Payment not completed', 'brandhouse'); ?></p>
      <h2 class="ty-title"><?php esc_html_e('Your order is not confirmed yet', 'brandhouse'); ?></h2>
      <p class="ty-line"><?php echo wp_kses_post(apply_filters('woocommerce_thankyou_order_failed_text', __('The payment was declined or interrupted, so nothing has been charged for this attempt. Your bag and details are kept — you can try again.', 'brandhouse'), $order)); ?></p>
      <p class="ty-actions">
        <a href="<?php echo esc_url($order->get_checkout_payment_url()); ?>" class="btn pay"><?php esc_html_e('Try the payment again', 'brandhouse'); ?></a>
        <?php if (is_user_logged_in()): ?><a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>" class="btn btn-outline"><?php esc_html_e('My account', 'brandhouse'); ?></a><?php endif; ?>
      </p>
    </section>
  <?php else:
      $paid = $order->is_paid();
      $state = $paid ? __('Payment received', 'brandhouse')
          : ($order->get_payment_method() === 'cod' ? __('Pay on delivery', 'brandhouse')
          : ($order->has_status(['on-hold', 'pending']) ? __('Awaiting payment confirmation', 'brandhouse') : wc_get_order_status_name($order->get_status())));
      ?>
    <section class="ty-head">
      <p class="ty-kicker"><?php esc_html_e('Schermo · The art of dressing', 'brandhouse'); ?></p>
      <h2 class="ty-title"><?php echo esc_html($paid || $order->get_payment_method() === 'cod' ? __('Grazie.', 'brandhouse') : __('Order received.', 'brandhouse')); ?></h2>
      <p class="ty-line">
        <?php
        echo esc_html($paid || $order->get_payment_method() === 'cod'
            ? sprintf(__('Your order %s is confirmed. A confirmation is on its way to your email.', 'brandhouse'), $order->get_order_number())
            : sprintf(__('We have your order %s. It is confirmed as soon as the payment is confirmed.', 'brandhouse'), $order->get_order_number()));
        ?>
      </p>
      <dl class="ty-facts">
        <div><dt><?php esc_html_e('Order', 'brandhouse'); ?></dt><dd>#<?php echo esc_html($order->get_order_number()); ?></dd></div>
        <div><dt><?php esc_html_e('Date', 'brandhouse'); ?></dt><dd><?php echo esc_html(wc_format_datetime($order->get_date_created())); ?></dd></div>
        <div><dt><?php esc_html_e('Total', 'brandhouse'); ?></dt><dd><?php echo wp_kses_post($order->get_formatted_order_total()); ?></dd></div>
        <?php if ($order->get_payment_method_title()): ?><div><dt><?php esc_html_e('Payment', 'brandhouse'); ?></dt><dd><?php echo wp_kses_post($order->get_payment_method_title()); ?></dd></div><?php endif; ?>
        <div><dt><?php esc_html_e('Status', 'brandhouse'); ?></dt><dd><span class="ty-state<?php echo $paid ? ' is-paid' : ''; ?>"><?php echo esc_html($state); ?></span></dd></div>
      </dl>
      <ol class="ty-next" aria-label="<?php esc_attr_e('What happens next', 'brandhouse'); ?>">
        <li class="is-done"><span><?php esc_html_e('Order placed', 'brandhouse'); ?></span></li>
        <li><span><?php esc_html_e('Packed at our warehouse', 'brandhouse'); ?></span></li>
        <li><span><?php esc_html_e('Dispatched — we email the tracking details', 'brandhouse'); ?></span></li>
        <li><span><?php esc_html_e('Delivered', 'brandhouse'); ?></span></li>
      </ol>
      <p class="ty-actions">
        <?php if (is_user_logged_in() && $order->get_user_id() === get_current_user_id()): ?>
          <a class="btn" href="<?php echo esc_url($order->get_view_order_url()); ?>"><?php esc_html_e('View order details', 'brandhouse'); ?></a>
        <?php elseif ($track = get_page_by_path('track-order')): ?>
          <a class="btn" href="<?php echo esc_url(get_permalink($track)); ?>"><?php esc_html_e('Track your order', 'brandhouse'); ?></a>
        <?php endif; ?>
        <a class="btn btn-outline" href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>"><?php esc_html_e('Continue shopping', 'brandhouse'); ?></a>
      </p>
    </section>
  <?php endif; ?>

  <div class="ty-details">
    <?php do_action('woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id()); ?>
    <?php do_action('woocommerce_thankyou', $order->get_id()); ?>
  </div>

<?php else: ?>
  <section class="ty-head"><h2 class="ty-title"><?php esc_html_e('Thank you.', 'brandhouse'); ?></h2><p class="ty-line"><?php esc_html_e('Your order has been received.', 'brandhouse'); ?></p></section>
<?php endif; ?>
</div>
