<?php

/**
 * Example usage of Bird Flock with Laravel Mailables.
 *
 * This file demonstrates various ways to send emails using Bird Flock
 * with Laravel Mailables.
 */

use Equidna\BirdFlock\BirdFlock;
use App\Mail\WelcomeEmail;
use App\Mail\OrderConfirmation;
use App\Mail\PasswordResetEmail;

// ============================================================================
// Example 1: Simple Welcome Email
// ============================================================================

$user = Auth::user();
$activationToken = Str::random(64);

$mailable = new WelcomeEmail(
    userName: $user->name,
    activationLink: route('activate', ['token' => $activationToken])
);

$messageId = BirdFlock::dispatchMailable(
    mailable: $mailable,
    to: $user->email
);

echo "Welcome email queued: {$messageId}\n";

// ============================================================================
// Example 2: Order Confirmation with Idempotency
// ============================================================================

$order = Order::find(123);

$mailable = new OrderConfirmation($order);

// Idempotency key ensures this email is sent only once per order
$messageId = BirdFlock::dispatchMailable(
    mailable: $mailable,
    to: $order->customer->email,
    idempotencyKey: "order:{$order->id}:confirmation"
);

// Store the message ID for tracking
$order->update(['confirmation_email_message_id' => $messageId]);

// ============================================================================
// Example 3: Scheduled Newsletter
// ============================================================================

$newsletter = Newsletter::latest()->first();
$sendAt = new DateTimeImmutable('tomorrow 9:00:00');

foreach (Subscriber::active()->get() as $subscriber) {
    $mailable = new NewsletterEmail($newsletter, $subscriber);
    
    BirdFlock::dispatchMailable(
        mailable: $mailable,
        to: $subscriber->email,
        idempotencyKey: "newsletter:{$newsletter->id}:subscriber:{$subscriber->id}",
        sendAt: $sendAt,
        metadata: [
            'newsletter_id' => $newsletter->id,
            'subscriber_segment' => $subscriber->segment,
        ]
    );
}

echo "Newsletter scheduled for {$sendAt->format('Y-m-d H:i:s')}\n";

// ============================================================================
// Example 4: Password Reset with Custom Metadata
// ============================================================================

$user = User::where('email', 'user@example.com')->first();
$token = Str::random(64);

$mailable = new PasswordResetEmail($user, $token);

$messageId = BirdFlock::dispatchMailable(
    mailable: $mailable,
    to: $user->email,
    idempotencyKey: "user:{$user->id}:password-reset:{$token}",
    metadata: [
        'user_id' => $user->id,
        'reset_token' => $token,
        'ip_address' => request()->ip(),
        'user_agent' => request()->userAgent(),
    ]
);

echo "Password reset email sent: {$messageId}\n";

// ============================================================================
// Example 5: Bulk Campaign with Rate Limiting
// ============================================================================

$campaign = Campaign::find(456);
$recipients = $campaign->recipients()->whereNull('sent_at')->get();

foreach ($recipients->chunk(100) as $chunk) {
    foreach ($chunk as $recipient) {
        $mailable = new CampaignEmail($campaign, $recipient);
        
        $messageId = BirdFlock::dispatchMailable(
            mailable: $mailable,
            to: $recipient->email,
            idempotencyKey: "campaign:{$campaign->id}:recipient:{$recipient->id}",
            metadata: [
                'campaign_id' => $campaign->id,
                'recipient_id' => $recipient->id,
                'segment' => $recipient->segment,
            ]
        );
        
        // Mark as queued
        $recipient->update([
            'message_id' => $messageId,
            'queued_at' => now(),
        ]);
    }
    
    // Small delay between chunks to avoid overwhelming the queue
    usleep(100000); // 100ms
}

echo "Campaign queued for {$recipients->count()} recipients\n";

// ============================================================================
// Example 6: Transactional Email with Immediate Sending
// ============================================================================

// For critical transactional emails, you can dispatch without delay
$mailable = new TwoFactorCodeEmail($user, $code);

$messageId = BirdFlock::dispatchMailable(
    mailable: $mailable,
    to: $user->email,
    idempotencyKey: "user:{$user->id}:2fa:{$code}",
    metadata: [
        'type' => '2fa',
        'code' => $code,
        'expires_at' => now()->addMinutes(5)->toIso8601String(),
    ]
);

echo "2FA code sent: {$messageId}\n";
