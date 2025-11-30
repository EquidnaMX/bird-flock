# Artisan Commands

**Bird Flock** provides several custom Artisan commands for testing, dead-letter management, configuration validation, and monitoring.

All commands are automatically registered when the package is installed.

---

## Command Index

| Command                | Signature                       | Purpose                                    |
| ---------------------- | ------------------------------- | ------------------------------------------ |
| Config Validation      | `bird-flock:config-validate`    | Validate package configuration             |
| Dead-Letter Management | `bird-flock:dead-letter`        | List, replay, or purge dead-letter entries |
| Dead-Letter Statistics | `bird-flock:dead-letter-stats`  | Display dead-letter queue statistics       |
| Test SMS               | `bird-flock:send-test-sms`      | Send test SMS via configured provider      |
| Test WhatsApp          | `bird-flock:send-test-whatsapp` | Send test WhatsApp message via Twilio      |
| Test Email             | `bird-flock:send-test-email`    | Send test email via configured provider    |

---

## Command Details

### 1. Config Validation

**Command**: `bird-flock:config-validate`  
**Class**: `Equidna\BirdFlock\Console\Commands\ConfigValidateCommand` (`src/Console/Commands/ConfigValidateCommand.php`)

**Purpose**: Validates Bird Flock configuration for common issues (missing credentials, invalid retry policies, table name conflicts).

**Usage**:

```bash
php artisan bird-flock:config-validate
```

**Output Example**:

```
✓ Configuration validated successfully.

Configured Providers:
- Twilio (SMS, WhatsApp)
- SendGrid (Email)

Queue: default
Dead-Letter Queue: enabled
```

**Exit Codes**:

- `0` — Configuration valid
- `1` — Validation errors detected

**When to Run**:

- After initial installation
- Before deployment
- When troubleshooting configuration issues

---

### 2. Dead-Letter Management

**Command**: `bird-flock:dead-letter {action} {message_id?} {--limit=50}`  
**Class**: `Equidna\BirdFlock\Console\Commands\DeadLetterCommand` (`src/Console/Commands/DeadLetterCommand.php`)

**Purpose**: Inspect and manage messages in the dead-letter queue.

**Actions**:

- `list` — Display recent dead-letter entries
- `replay` — Re-queue a specific dead-letter entry for retry
- `purge` — Remove dead-letter entries

**Arguments**:

- `action` (required) — `list`, `replay`, or `purge`
- `message_id` (optional) — Required for `replay` and single-entry `purge`

**Options**:

- `--limit=N` — Number of entries to display (default: 50, only for `list`)

---

#### List Dead-Letter Entries

**Usage**:

```bash
php artisan bird-flock:dead-letter list
php artisan bird-flock:dead-letter list --limit=100
```

**Output Example**:

```
+---------------+------------------------------+---------+----------+------------+---------------------+
| Entry ID      | Message ID                   | Channel | Attempts | Error Code | Created             |
+---------------+------------------------------+---------+----------+------------+---------------------+
| 01HQRS...     | 01HQRS1234567890ABCDEF       | sms     | 3        | 21211      | 2025-11-30 10:23:45 |
| 01HQRT...     | 01HQRT9876543210FEDCBA       | email   | 3        | 550        | 2025-11-30 09:15:22 |
+---------------+------------------------------+---------+----------+------------+---------------------+
```

---

#### Replay a Dead-Letter Entry

**Usage**:

```bash
php artisan bird-flock:dead-letter replay 01HQRS1234567890ABCDEF
```

**What Happens**:

1. Retrieves the dead-letter entry by ID
2. Re-dispatches the original message to the queue
3. Removes the entry from the dead-letter table

**Output**:

```
Message 01HQRS1234567890ABCDEF dispatched back to queue.
```

**Use Case**:  
After fixing provider issues (e.g., expired credentials, service outage), replay failed messages.

---

#### Purge Dead-Letter Entries

**Single Entry**:

```bash
php artisan bird-flock:dead-letter purge 01HQRS1234567890ABCDEF
```

**All Entries** (requires confirmation):

```bash
php artisan bird-flock:dead-letter purge
```

**Output**:

```
Purge all dead-letter entries? (yes/no) [no]:
> yes

All dead-letter entries removed.
```

**Use Case**:  
Clean up old or unrecoverable failed messages.

---

### 3. Dead-Letter Statistics

**Command**: `bird-flock:dead-letter-stats`  
**Class**: `Equidna\BirdFlock\Console\Commands\DeadLetterStatsCommand` (`src/Console/Commands/DeadLetterStatsCommand.php`)

**Purpose**: Display summary statistics for the dead-letter queue.

**Usage**:

```bash
php artisan bird-flock:dead-letter-stats
```

**Output Example**:

```
Dead-Letter Queue Statistics
=============================

Total Entries: 42

By Channel:
- sms: 25
- whatsapp: 10
- email: 7

By Error Code:
- 21211 (Twilio invalid phone): 15
- 550 (Email bounce): 7
- timeout: 20

Most Recent Entries:
+---------------+------------------------------+---------+---------------------+
| Entry ID      | Message ID                   | Channel | Created             |
+---------------+------------------------------+---------+---------------------+
| 01HQRS...     | 01HQRS1234567890ABCDEF       | sms     | 2025-11-30 10:23:45 |
+---------------+------------------------------+---------+---------------------+
```

**Use Case**:  
Monitor dead-letter queue health and identify recurring failure patterns.

---

### 4. Send Test SMS

**Command**: `bird-flock:send-test-sms {recipient}`  
**Class**: `Equidna\BirdFlock\Console\Commands\SendTestSmsCommand` (`src/Console/Commands/SendTestSmsCommand.php`)

**Purpose**: Send a test SMS to verify Twilio or Vonage integration.

**Arguments**:

- `recipient` (required) — Phone number in E.164 format (e.g., `+1234567890`)

**Usage**:

```bash
php artisan bird-flock:send-test-sms +1234567890
```

**Output**:

```
Dispatching test SMS to +1234567890...
Message ID: 01HQRS1234567890ABCDEF
Test SMS queued successfully.
```

**What Happens**:

1. Creates a `FlightPlan` with channel `sms`
2. Dispatches via `BirdFlock::dispatch()`
3. Returns message ID

**Requirements**:

- Twilio or Vonage credentials configured in `.env`
- Queue worker running

---

### 5. Send Test WhatsApp

**Command**: `bird-flock:send-test-whatsapp {recipient}`  
**Class**: `Equidna\BirdFlock\Console\Commands\SendTestWhatsappCommand` (`src/Console/Commands/SendTestWhatsappCommand.php`)

**Purpose**: Send a test WhatsApp message via Twilio.

**Arguments**:

- `recipient` (required) — Phone number in E.164 format (e.g., `+1234567890`)

**Usage**:

```bash
php artisan bird-flock:send-test-whatsapp +1234567890
```

**Output**:

```
Dispatching test WhatsApp message to +1234567890...
Message ID: 01HQRT9876543210FEDCBA
Test WhatsApp message queued successfully.
```

**Requirements**:

- Twilio credentials configured
- WhatsApp number approved and configured in Twilio Console
- Queue worker running

---

### 6. Send Test Email

**Command**: `bird-flock:send-test-email {recipient}`  
**Class**: `Equidna\BirdFlock\Console\Commands\SendTestEmailCommand` (`src/Console/Commands/SendTestEmailCommand.php`)

**Purpose**: Send a test email via SendGrid or Mailgun.

**Arguments**:

- `recipient` (required) — Email address (e.g., `test@example.com`)

**Usage**:

```bash
php artisan bird-flock:send-test-email test@example.com
```

**Output**:

```
Dispatching test email to test@example.com...
Message ID: 01HQRU1111111111AAAAAA
Test email queued successfully.
```

**Requirements**:

- SendGrid or Mailgun credentials configured
- Queue worker running

---

## Scheduled Commands

**Bird Flock does not include built-in scheduled commands.**

If your application uses scheduled message sends (via `FlightPlan::sendAt`), ensure Laravel's scheduler is running:

**Crontab Entry**:

```cron
* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1
```

---

## Command Registration

Commands are automatically registered in the `BirdFlockServiceProvider`:

```php
// File: src/BirdFlockServiceProvider.php
private function registerCommands(): void
{
    if ($this->app->runningInConsole()) {
        $this->commands([
            DeadLetterCommand::class,
            SendTestSmsCommand::class,
            SendTestWhatsappCommand::class,
            SendTestEmailCommand::class,
            ConfigValidateCommand::class,
            DeadLetterStatsCommand::class,
        ]);
    }
}
```

No additional setup required; commands are available after package installation.

---

## Testing Commands

To verify command availability:

```bash
php artisan list bird-flock
```

**Output**:

```
bird-flock
  bird-flock:config-validate        Validate Bird Flock configuration
  bird-flock:dead-letter            Inspect and manage dead-letter messages
  bird-flock:dead-letter-stats      Display dead-letter queue statistics
  bird-flock:send-test-email        Send a test email
  bird-flock:send-test-sms          Send a test SMS
  bird-flock:send-test-whatsapp     Send a test WhatsApp message
```

---

## Assumptions

- **Queue Worker Required**: Test send commands (`send-test-*`) require an active queue worker; messages are dispatched asynchronously.
- **Console Environment**: Commands are only registered when running in console (`runningInConsole()`).
- **Exit Codes**: Commands return standard Laravel exit codes (`0` = success, `1` = failure, `2` = invalid).

For unresolved command questions, see [Open Questions & Assumptions](open-questions-and-assumptions.md).
