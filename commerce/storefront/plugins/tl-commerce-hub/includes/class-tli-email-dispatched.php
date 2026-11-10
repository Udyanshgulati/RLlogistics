<?php
/**
 * "Order dispatched" customer email, with courier, tracking number and link. Appears in
 * WooCommerce → Settings → Emails, where its subject, heading and text can be changed or the email switched off.
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

class TLI_Email_Dispatched extends WC_Email
{
    public function __construct()
    {
        $this->id = 'tl_order_dispatched';
        $this->customer_email = true;
        $this->title = 'Order dispatched';
        $this->description = 'Sent to the customer when an order is marked as dispatched (TL Commerce Hub), with the courier and tracking number.';
        $this->placeholders = ['{order_date}' => '', '{order_number}' => ''];
        add_action('woocommerce_order_status_tl-dispatched_notification', [$this, 'trigger'], 10, 2);
        parent::__construct();
    }

    public function get_default_subject()
    {
        return 'Your {site_title} order #{order_number} is on its way';
    }

    public function get_default_heading()
    {
        return 'Your order is on its way';
    }

    public function get_default_additional_content()
    {
        return 'Thank you for shopping with us.';
    }

    public function trigger($order_id, $order = false): void
    {
        $this->setup_locale();
        if ($order_id && !$order instanceof WC_Order) {
            $order = wc_get_order($order_id);
        }
        if ($order instanceof WC_Order) {
            $this->object = $order;
            $this->recipient = $order->get_billing_email();
            $this->placeholders['{order_date}'] = wc_format_datetime($order->get_date_created());
            $this->placeholders['{order_number}'] = $order->get_order_number();
        }
        if ($this->is_enabled() && $this->get_recipient()) {
            $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
        }
        $this->restore_locale();
    }

    public function get_content_html()
    {
        ob_start();
        do_action('woocommerce_email_header', $this->get_heading(), $this);
        printf('<p>Hi %s,</p><p>Good news — your order #%s has left our warehouse and is with the courier.</p>', esc_html($this->object->get_billing_first_name() ?: 'there'), esc_html($this->object->get_order_number()));
        echo tli_tracking_html($this->object, true); // phpcs:ignore WordPress.Security.EscapeOutput
        if (function_exists('tli_invoice_link') && ($link = tli_invoice_link($this->object))) {
            printf('<p>Your GST invoice: <a href="%s">download / print</a></p>', esc_url($link));
        }
        do_action('woocommerce_email_order_details', $this->object, false, false, $this);
        do_action('woocommerce_email_customer_details', $this->object, false, false, $this);
        if ($extra = $this->get_additional_content()) {
            echo wp_kses_post(wpautop(wptexturize($extra)));
        }
        do_action('woocommerce_email_footer', $this);
        return ob_get_clean();
    }

    public function get_content_plain()
    {
        $t = tli_tracking($this->object);
        $out = sprintf("Your order #%s has left our warehouse and is with the courier.\n\nCourier: %s\nTracking number (AWB): %s\n", $this->object->get_order_number(), $t['courier'], $t['awb']);
        if ($t['url']) {
            $out .= 'Track your parcel: ' . $t['url'] . "\n";
        }
        return $out . "\n" . wp_strip_all_tags((string) $this->get_additional_content());
    }

    public function init_form_fields()
    {
        parent::init_form_fields();
        unset($this->form_fields['recipient']);
    }
}
