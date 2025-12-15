# Release v1.2.0 "Condor"

Bird Flock now officially supports Laravel 12.x while remaining compatible with Laravel 10.x and 11.x. This release widens Composer constraints and updates documentation to reflect broader framework support. No breaking changes.

**Release Date**: December 15, 2025  
**Codename**: Condor  
**Version**: 1.2.0

---

## Highlights

- Laravel 12 compatibility
- Composer constraints widened for `laravel/framework` and `illuminate/database`
- README updated to state Laravel 10–12 support

---

## Added

- Official compatibility with Laravel 12.x.

---

## Changed

- Composer constraints: `laravel/framework ^10 || ^11 || ^12`, `illuminate/database ^10 || ^11 || ^12`.
- Documentation updated for supported versions.

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

## Fixed

- Consistency in documentation around framework versions.

---

## Security

No changes.

---

## Links

- See CHANGELOG for full history: CHANGELOG.md
- Migration guidance (breaking changes): BREAKING_CHANGES.md

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
