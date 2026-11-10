<?php
/**
 * Message queue on WooCommerce's Action Scheduler. Every message has an idempotency key (event + order/item +
 * channel + recipient), stored with a UNIQUE index, so replayed webhooks, retries or repeated status changes never
 * queue a second copy. Sending happens in the background with up to 5 attempts (backoff 1, 5, 30, 120 min).
 * The log keeps event, channel, purpose, template + version, recipient, result and attempts — not payment data.
 */

defined('ABSPATH') || exit;

/**
 * Queue a message. $vars are the values used to render it (order number, product name, tracking link, review link…).
 * Returns the message id, or 0 if a message with the same key already exists or it was refused (no consent).
 */
function scm_queue(string $event, string $channel, string $purpose, string $recipient, array $vars, string $idem, int $order_id = 0): int
{
    global $wpdb;
    $recipient = scm_contact($channel, $recipient);
    if ($recipient === '') {
        return 0;
    }
    // Consent gate: WhatsApp needs opt-in for everything; marketing needs opt-in on every channel.
    $needs = $channel === 'whatsapp' ? ($purpose === 'marketing' ? 'marketing' : 'updates') : ($purpose === 'marketing' ? 'marketing' : null);
    if ($needs && !scm_consent_ok($channel, $needs, $recipient)) {
        return 0;
    }
    $tpl = scm_template($event, $channel);
    $now = scm_now();
    $ok = $wpdb->query($wpdb->prepare(
        "INSERT IGNORE INTO {$wpdb->prefix}scm_messages (idem, event, channel, purpose, template, template_version, recipient, order_id, vars, status, attempts, created_at, updated_at) VALUES (%s,%s,%s,%s,%s,%s,%s,%d,%s,'queued',0,%s,%s)",
        substr($idem, 0, 190), $event, $channel, $purpose, $tpl['id'], $tpl['version'], $recipient, $order_id, wp_json_encode($vars), $now, $now
    )); // phpcs:ignore
    if (!$ok) {
        return 0; // duplicate key: this message was already queued or sent
    }
    $id = (int) $wpdb->insert_id;
    if (function_exists('as_enqueue_async_action')) {
        as_enqueue_async_action('scm_send', [$id], 'scm');
    }
    return $id;
}

add_action('scm_send', 'scm_process', 10, 1);

function scm_process(int $id): void
{
    global $wpdb;
    $t = $wpdb->prefix . 'scm_messages';
    $m = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id = %d", $id), ARRAY_A); // phpcs:ignore
    if (!$m || !in_array($m['status'], ['queued', 'retry'], true)) {
        return; // already handled (idempotent worker)
    }
    // Consent can be withdrawn between queueing and sending: check again.
    $needs = $m['channel'] === 'whatsapp' ? ($m['purpose'] === 'marketing' ? 'marketing' : 'updates') : ($m['purpose'] === 'marketing' ? 'marketing' : null);
    if ($needs && !scm_consent_ok($m['channel'], $needs, $m['recipient'])) {
        $wpdb->update($t, ['status' => 'cancelled', 'result' => 'consent withdrawn before sending', 'updated_at' => scm_now()], ['id' => $id]);
        return;
    }
    $vars = json_decode((string) $m['vars'], true) ?: [];
    $vars['_to'] = $m['recipient'];
    $msg = scm_render($m['event'], $m['channel'], $vars) + ['recipient' => $m['recipient'], 'purpose' => $m['purpose']];
    $attempts = (int) $m['attempts'] + 1;
    $flow = scm_flow_on($m['event'], $m['channel']);
    if (!scm_live($m['channel'])) {
        // Dry run (or provider not connected): record exactly what would be sent; nothing leaves the site.
        $why = scm_settings()['mode'] === 'live' ? 'provider not configured' : 'dry-run';
        $wpdb->update($t, ['status' => 'simulated', 'attempts' => $attempts, 'result' => $why . ($flow ? '' : ' (flow off)') . ': ' . wp_trim_words(wp_strip_all_tags($msg['preview'] ?? ''), 60), 'updated_at' => scm_now()], ['id' => $id]);
        return;
    }
    if (!$flow) {
        $wpdb->update($t, ['status' => 'skipped', 'result' => 'flow switched off for this channel', 'updated_at' => scm_now()], ['id' => $id]);
        return;
    }
    $r = $m['channel'] === 'email' ? scm_send_email($msg) : scm_send_whatsapp($msg);
    if ($r['ok']) {
        $wpdb->update($t, ['status' => 'sent', 'attempts' => $attempts, 'provider_id' => $r['id'], 'result' => '', 'updated_at' => scm_now()], ['id' => $id]);
        return;
    }
    $delays = [60, 300, 1800, 7200];
    if ($r['retry'] && $attempts <= count($delays) && function_exists('as_schedule_single_action')) {
        $wpdb->update($t, ['status' => 'retry', 'attempts' => $attempts, 'result' => $r['error'], 'updated_at' => scm_now()], ['id' => $id]);
        as_schedule_single_action(time() + $delays[$attempts - 1], 'scm_send', [$id], 'scm');
        return;
    }
    $wpdb->update($t, ['status' => 'failed', 'attempts' => $attempts, 'result' => $r['error'], 'updated_at' => scm_now()], ['id' => $id]);
    error_log("[schermo-comms] message $id ({$m['event']}/{$m['channel']}) failed: {$r['error']}"); // phpcs:ignore
}
