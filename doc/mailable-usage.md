# Using Laravel Mailables with Bird Flock

**Bird Flock** provides seamless integration with Laravel's Mailable classes, allowing you to leverage the full power of Laravel's mail system while benefiting from Bird Flock's idempotency, retry logic, circuit breakers, and dead-letter queue support.

> **📁 Complete Examples**: Full working examples are available in the [`doc/examples/`](examples/) directory:
> - [`WelcomeEmail.php`](examples/WelcomeEmail.php) - Example Mailable class
> - [`welcome.blade.php`](examples/welcome.blade.php) - HTML email template
> - [`welcome-text.blade.php`](examples/welcome-text.blade.php) - Plain text template
> - [`usage-example.php`](examples/usage-example.php) - Various usage scenarios

---

## Overview

When you use Laravel Mailables with Bird Flock, you get:

- **Familiar API**: Use the same Mailable classes you already know
- **Blade Templates**: Full support for Blade view rendering
- **Automatic Conversion**: HTML views are automatically converted to plain text
- **Attachments**: File attachments are supported and encoded properly
- **Idempotency**: Prevent duplicate emails with idempotency keys
- **Retry Logic**: Automatic retries on transient failures
- **Dead-Letter Queue**: Failed messages are captured for manual review
- **Circuit Breakers**: Automatic provider failover on repeated failures
- **Scheduled Sending**: Queue emails for future delivery

---

## Basic Usage

### 1. Create a Standard Laravel Mailable

```php
<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WelcomeEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $userName,
        public readonly string $activationLink,
    ) {}

    public function build()
    {
        return $this->view('emails.welcome')
            ->subject('Welcome to Our Platform!')
            ->with([
                'userName' => $this->userName,
                'activationLink' => $this->activationLink,
            ]);
    }
}
```

### 2. Create Your Blade View

**resources/views/emails/welcome.blade.php**:

```blade
<!DOCTYPE html>
<html>
<head>
    <title>Welcome</title>
</head>
<body>
    <h1>Welcome, {{ $userName }}!</h1>
    <p>Thank you for joining our platform.</p>
    <p>
        <a href="{{ $activationLink }}">Activate your account</a>
    </p>
    <p>Best regards,<br>The Team</p>
</body>
</html>
```

### 3. Dispatch Through Bird Flock

```php
use Equidna\BirdFlock\BirdFlock;
use App\Mail\WelcomeEmail;

$mailable = new WelcomeEmail(
    userName: 'Alice Johnson',
    activationLink: 'https://example.com/activate/abc123'
);

$messageId = BirdFlock::dispatchMailable(
    mailable: $mailable,
    to: 'alice@example.com'
);

// Message is now queued with a unique ID
echo "Message queued: {$messageId}";
```

---

## Advanced Features

### Idempotency

Prevent duplicate emails by providing an idempotency key:

```php
use Equidna\BirdFlock\BirdFlock;
use App\Mail\OrderConfirmation;

$mailable = new OrderConfirmation($order);

$messageId = BirdFlock::dispatchMailable(
    mailable: $mailable,
    to: $order->customer->email,
    idempotencyKey: "order:{$order->id}:confirmation"
);
```

If you try to dispatch the same message again with the same idempotency key, Bird Flock will:
- Return the existing message ID
- Not send a duplicate email
- Log the duplicate attempt

### Scheduled Sending

Schedule emails for future delivery:

```php
use Equidna\BirdFlock\BirdFlock;
use App\Mail\Newsletter;

$mailable = new Newsletter($content);

// Send tomorrow at 9 AM
$sendAt = new \DateTimeImmutable('tomorrow 9:00:00');

$messageId = BirdFlock::dispatchMailable(
    mailable: $mailable,
    to: 'subscriber@example.com',
    sendAt: $sendAt
);
```

### Custom Metadata

Add custom metadata for tracking and analytics:

```php
use Equidna\BirdFlock\BirdFlock;
use App\Mail\CampaignEmail;

$mailable = new CampaignEmail($campaign);

$messageId = BirdFlock::dispatchMailable(
    mailable: $mailable,
    to: 'customer@example.com',
    metadata: [
        'campaign_id' => $campaign->id,
        'user_segment' => 'premium',
        'ab_test_variant' => 'A',
    ]
);
```

The metadata will be stored with the message and can be used for:
- Analytics and reporting
- Webhook payload enrichment
- Dead-letter queue analysis

---

## Text and HTML Views

### Automatic Text Generation

If you only provide an HTML view, Bird Flock will automatically generate a plain text version:

```php
class NewsletterEmail extends Mailable
{
    public function build()
    {
        return $this->view('emails.newsletter.html')
            ->subject('Monthly Newsletter');
    }
}
```

The HTML will be converted to plain text with:
- HTML tags stripped
- Line breaks preserved
- Scripts and styles removed
- HTML entities decoded

### Explicit Text View

For better control, provide both HTML and text views:

```php
class OrderConfirmation extends Mailable
{
    public function build()
    {
        return $this->view('emails.order.html')
            ->text('emails.order.text')
            ->subject('Order Confirmation');
    }
}
```

**resources/views/emails/order.html.blade.php**:
```blade
<h1>Order #{{ $order->id }}</h1>
<p>Your order has been confirmed.</p>
```

**resources/views/emails/order.text.blade.php**:
```blade
Order #{{ $order->id }}

Your order has been confirmed.
```

---

## Attachments

Bird Flock supports file attachments from Mailables:

```php
class InvoiceEmail extends Mailable
{
    public function __construct(
        private readonly Invoice $invoice
    ) {}

    public function build()
    {
        return $this->view('emails.invoice')
            ->subject("Invoice #{$this->invoice->number}")
            ->attach($this->invoice->pdfPath(), [
                'as' => "invoice-{$this->invoice->number}.pdf",
                'mime' => 'application/pdf',
            ]);
    }
}
```

**Important Notes**:
- Attachments are base64-encoded and stored in the message payload
- Maximum attachment size: 10 MB per file (SendGrid limit)
- Total payload size limit: 256 KB (configurable)
- Large attachments may impact queue performance

---

## Error Handling and Retries

### Automatic Retries

Bird Flock automatically retries failed email sends with exponential backoff:

```php
// Default retry configuration (can be customized in config)
'email' => [
    'max_attempts' => 3,
    'base_delay_ms' => 1000,
    'max_delay_ms' => 60000,
]
```

### Dead-Letter Queue

If all retries fail, the message is moved to the dead-letter queue:

```bash
# View failed messages
php artisan bird-flock:dlq:list --channel=email

# Replay a failed message
php artisan bird-flock:dlq:replay <message-id>
```

### Circuit Breakers

If a provider repeatedly fails, Bird Flock opens a circuit breaker to prevent cascading failures:

```php
// Check circuit breaker status
GET /bird-flock/health/circuit-breakers
```

Response:
```json
{
  "circuit_breakers": {
    "sendgrid_email": {
      "state": "open",
      "failure_count": 5,
      "last_failure_at": "2025-12-10T10:23:45Z"
    }
  }
}
```

---

## Comparison with Standard Laravel Mail

### Standard Laravel Mail

```php
use Illuminate\Support\Facades\Mail;
use App\Mail\WelcomeEmail;

Mail::to('user@example.com')
    ->send(new WelcomeEmail($user));
```

**Limitations**:
- No built-in idempotency
- No dead-letter queue
- No circuit breakers
- Limited retry control
- No centralized message tracking

### Bird Flock with Mailables

```php
use Equidna\BirdFlock\BirdFlock;
use App\Mail\WelcomeEmail;

$messageId = BirdFlock::dispatchMailable(
    mailable: new WelcomeEmail($user),
    to: 'user@example.com',
    idempotencyKey: "user:{$user->id}:welcome"
);
```

**Benefits**:
- ✅ Built-in idempotency prevents duplicates
- ✅ Dead-letter queue captures failures
- ✅ Circuit breakers prevent cascading failures
- ✅ Configurable retry logic with backoff
- ✅ Centralized message tracking and auditing
- ✅ Same familiar Mailable API
- ✅ Multi-provider support (SendGrid, Mailgun)

---

## Best Practices

### 1. Use Idempotency Keys

Always provide idempotency keys for transactional emails:

```php
// Good: Unique per user and email type
$idempotencyKey = "user:{$user->id}:password-reset:{$token}";

// Bad: Too generic, not unique enough
$idempotencyKey = "password-reset";
```

### 2. Keep Mailables Lightweight

Avoid storing large objects in Mailable constructors:

```php
// Good: Pass only IDs
class OrderEmail extends Mailable
{
    public function __construct(
        private readonly int $orderId
    ) {}

    public function build()
    {
        $order = Order::find($this->orderId);
        return $this->view('emails.order')->with('order', $order);
    }
}

// Bad: Storing entire Eloquent model
class OrderEmail extends Mailable
{
    public function __construct(
        private readonly Order $order  // May fail serialization
    ) {}
}
```

### 3. Test Your Mailables

Test mailable rendering before dispatching:

```php
// In your tests
public function testWelcomeEmailRenders(): void
{
    $mailable = new WelcomeEmail('Alice', 'https://example.com/activate');
    
    $mailable->assertSeeInHtml('Welcome, Alice!');
    $mailable->assertSeeInHtml('Activate your account');
}
```

### 4. Monitor Dead-Letter Queue

Set up monitoring for dead-letter messages:

```bash
# Check for failed messages regularly
php artisan bird-flock:dlq:list --limit=100

# Set up alerts when DLQ size exceeds threshold
```

---

## Configuration

### Email Provider Configuration

Bird Flock uses your configured email provider (SendGrid or Mailgun):

```env
# SendGrid
SENDGRID_API_KEY=your_api_key
SENDGRID_FROM_EMAIL=noreply@example.com
SENDGRID_FROM_NAME="Example App"

# Mailgun
MAILGUN_API_KEY=your_api_key
MAILGUN_DOMAIN=mg.example.com
MAILGUN_FROM_EMAIL=noreply@example.com
```

### Retry Configuration

Customize retry behavior in `config/bird-flock.php`:

```php
'retry' => [
    'channels' => [
        'email' => [
            'max_attempts' => 3,        // Number of retry attempts
            'base_delay_ms' => 1000,    // Initial delay (1 second)
            'max_delay_ms' => 60000,    // Maximum delay (1 minute)
        ],
    ],
],
```

---

## Troubleshooting

### Message Not Sending

1. **Check queue worker is running**:
   ```bash
   php artisan queue:work --queue=default
   ```

2. **Verify provider credentials**:
   ```bash
   php artisan bird-flock:test:email --provider=sendgrid
   ```

3. **Check circuit breaker status**:
   ```bash
   curl https://yourdomain.com/bird-flock/health/circuit-breakers
   ```

### Large Attachments Failing

If messages with attachments fail:

1. Check attachment size is under 10 MB
2. Verify total payload size is under 256 KB
3. Increase payload limit in config if needed:
   ```php
   'max_payload_size' => 524288, // 512 KB
   ```

### Duplicate Messages

If duplicates are still being sent:

1. Verify idempotency key is unique and consistent
2. Check database logs for conflict resolution
3. Review logs for `bird-flock.dispatch.duplicate_skipped` entries

---

## Examples

### Example 1: Password Reset Email

```php
use Equidna\BirdFlock\BirdFlock;
use App\Mail\PasswordResetEmail;

$token = Str::random(64);

$mailable = new PasswordResetEmail($user, $token);

$messageId = BirdFlock::dispatchMailable(
    mailable: $mailable,
    to: $user->email,
    idempotencyKey: "user:{$user->id}:password-reset:{$token}"
);
```

### Example 2: Bulk Newsletter Campaign

```php
use Equidna\BirdFlock\BirdFlock;
use App\Mail\NewsletterEmail;

$newsletter = Newsletter::latest()->first();
$subscribers = Subscriber::active()->get();

foreach ($subscribers as $subscriber) {
    $mailable = new NewsletterEmail($newsletter, $subscriber);
    
    BirdFlock::dispatchMailable(
        mailable: $mailable,
        to: $subscriber->email,
        idempotencyKey: "newsletter:{$newsletter->id}:subscriber:{$subscriber->id}",
        metadata: [
            'newsletter_id' => $newsletter->id,
            'subscriber_segment' => $subscriber->segment,
        ]
    );
}
```

### Example 3: Order Confirmation with Invoice

```php
use Equidna\BirdFlock\BirdFlock;
use App\Mail\OrderConfirmationEmail;

$mailable = new OrderConfirmationEmail($order);

$messageId = BirdFlock::dispatchMailable(
    mailable: $mailable,
    to: $order->customer->email,
    idempotencyKey: "order:{$order->id}:confirmation",
    metadata: [
        'order_id' => $order->id,
        'customer_id' => $order->customer_id,
        'order_total' => $order->total,
    ]
);

// Track the message ID for later reference
$order->update(['confirmation_email_message_id' => $messageId]);
```

---

## Conclusion

Bird Flock's Mailable integration provides the best of both worlds:

- **Familiar Laravel API** you already know
- **Production-ready reliability** with idempotency, retries, and DLQ
- **Easy migration** from standard Laravel Mail
- **Zero code changes** to your existing Mailable classes

Start using Mailables with Bird Flock today to improve your email delivery reliability!
