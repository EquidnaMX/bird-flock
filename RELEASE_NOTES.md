# 🎉 Release v1.1.0 - "Albatross"

## Mailables Support Release

This release adds first-class support for **Laravel Mailables**, enabling teams to use familiar Mailable classes while benefiting from Bird Flock's reliability features (idempotency, retries, circuit breakers, DLQ).

**Release Date**: December 11, 2025  
**Codename**: Albatross  
**Version**: 1.1.0

---

## 🚀 What is Bird Flock?

Bird Flock is a comprehensive Laravel package that orchestrates reliable outbound messaging across SMS, WhatsApp, and Email channels. It provides enterprise-grade features including idempotency, circuit breakers, dead-letter queues, and automatic retry handling.

### Key Highlights

✅ **Mailables Support**: Dispatch Laravel Mailables via `BirdFlock::dispatchMailable()`  
✅ **Multi-Channel Support**: SMS (Twilio, Vonage), WhatsApp (Twilio), Email (SendGrid, Mailgun)  
✅ **Production-Ready**: Circuit breakers, DLQ, exponential backoff, comprehensive error handling  
✅ **Developer-Friendly**: Simple API, extensive documentation, CLI commands for testing  
✅ **Observable**: Structured logging, metrics collection, health check endpoints  
✅ **Secure**: Webhook signature validation, PII masking, HTTPS enforcement  
✅ **Well-Tested**: 75-85% unit test coverage with PHPUnit 10.x

---

## 📦 Installation

```bash
composer require equidna/bird-flock
php artisan vendor:publish --tag=bird-flock-config
php artisan migrate
```

---

## 🎯 Core Features

### Messaging Capabilities

- **Laravel Mailables**: Use Mailables with full Bird Flock reliability
- **Idempotency**: Prevent duplicate sends with unique keys
- **Batch Dispatch**: Send up to thousands of messages efficiently
- **Scheduled Delivery**: Schedule messages for future delivery
- **Multi-Provider**: Seamlessly switch between Twilio, SendGrid, Vonage, Mailgun

### Reliability Features

- **Circuit Breakers**: Automatic provider failure detection and fail-fast
- **Dead-Letter Queue**: Capture failed messages for manual replay
- **Exponential Backoff**: Intelligent retry with jitter (1s–60s)
- **Webhook Processing**: Automatic status updates from provider callbacks

### Developer Tools

- 6 Artisan commands for testing and management
- 2 health check endpoints
- 8 webhook endpoints with rate limiting
- Comprehensive event system for extensibility

### Observability

- PII-masked structured logging
- Metrics collection interface
- Circuit breaker status monitoring
- Dead-letter queue statistics

---

## 📝 Documentation

This release includes **comprehensive documentation** (10+ detailed guides):

- **[CHANGELOG.md](CHANGELOG.md)** - Complete project history
- **[BREAKING_CHANGES.md](BREAKING_CHANGES.md)** - Breaking changes guide
- [Deployment Instructions](doc/deployment-instructions.md)
- [API Documentation](doc/api-documentation.md)
- [Routes Documentation](doc/routes-documentation.md)
- [Artisan Commands](doc/artisan-commands.md)
- [Architecture Diagrams](doc/architecture-diagrams.md)
- [Business Logic & Core Processes](doc/business-logic-and-core-processes.md)
- [Monitoring Guide](doc/monitoring.md)
- [Tests Documentation](doc/tests-documentation.md)
- [Open Questions & Assumptions](doc/open-questions-and-assumptions.md)
- **[Mailable Usage](doc/mailable-usage.md)** ✨ NEW — End-to-end guide for Mailables, with examples in `doc/examples/`

---

## 🔧 What's Included

### Files Added/Updated in This Release

- ✨ `doc/mailable-usage.md` — Comprehensive Mailables guide
- ✨ `doc/examples/` — Working example Mailable, templates, usage script
- ✨ `CHANGELOG.md` — Updated for v1.1.0 (Albatross)
- ✨ `RELEASE_NOTES.md` — This file updated for v1.1.0

### Version Updates

- ✨ `composer.json` — Version remains aligned to tags (update when tagging)

### Recent Additions

- ✨ Mailables conversion pipeline and dispatch API

---

## 🎨 Code Quality Improvements

### PHPDoc Standardization

- ✅ Continued adherence to PHPDocStyle.instructions.md across new files

### Code Style

- ✅ Consistent trailing commas in multi-line constructs
- ✅ Anonymous class spacing normalization
- ✅ Empty constructor body formatting fixes

---

## 🔐 Security Enhancements

No changes since v1.0.0.

---

## 📋 System Requirements

- **PHP**: 8.3 or higher
- **Laravel**: 11.x
- **Database**: MySQL 5.7+, PostgreSQL 11+, or SQLite 3.26+
- **Cache**: Any Laravel-supported driver (Redis recommended)
- **Queue**: Any Laravel queue driver (Redis recommended)

---

## 🚦 Testing

### Unit Test Coverage

- ✅ Core dispatch logic and idempotency
- ✅ Circuit breaker behavior (including concurrency tests)
- ✅ Job processing and retry logic
- ✅ All provider sender implementations
- ✅ Webhook processing and signature validation
- ✅ Support utilities (backoff, normalization, validation)

**Test Framework**: PHPUnit 10.x  
**Coverage**: ~75-85%  
**Test Speed**: < 100ms per test

Run tests:

```bash
./vendor/bin/phpunit
```

---

## 📦 Dependencies

### Production Dependencies

- Laravel Framework 11.x (Illuminate 10.x–12.x supported)
- Symfony UID 7.x
- Guzzle HTTP 7.x
- Twilio SDK 6.x
- SendGrid 7.x
- Vonage Client 4.2+
- Mailgun PHP 4.3+

### Development Dependencies

- PHPUnit 10.x
- PHPStan 1.10+
- PHP_CodeSniffer 4.x

---

## 🔮 Future Roadmap

See [Open Questions & Assumptions](doc/open-questions-and-assumptions.md) for planned features:

- Multi-tenancy support
- Multi-provider routing and failover
- Message cancellation for scheduled sends
- Rate limiting per recipient
- Idempotency key expiration policies
- Template management system
- GDPR/PII compliance helpers

---

## 📄 License

MIT License - See [LICENSE](LICENSE) file

---

## 👤 Author

**Gabriel Ruelas**  
Email: gruelas@gruelas.com  
Organization: Equidna

---

## 🙏 Thank You

Thank you for using Bird Flock! We're excited to see what you build with it.

For issues, questions, or contributions:

- GitHub Issues: https://github.com/EquidnaMX/bird-flock/issues
- Email: gruelas@gruelas.com

---

## 🎯 Next Steps

1. ⭐ Star this repository
2. 📖 Read the [Deployment Instructions](doc/deployment-instructions.md)
3. 🚀 Deploy to production
4. 📊 Set up monitoring using the [Monitoring Guide](doc/monitoring.md)
5. 🐛 Report issues or request features on GitHub

---

**Happy Messaging! 🚀**
