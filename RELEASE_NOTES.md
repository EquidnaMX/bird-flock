# Release v1.3.0 "Falcon"

Bird Flock v1.3.0 focuses on platform compatibility and email rendering flexibility. This release introduces Laravel 13 support, adds inline email attachment handling for Mailgun and SendGrid flows, and refreshes core operational documentation.

**Release Date**: 2026-06-05  
**Codename**: Falcon  
**Version**: 1.3.0

## Highlights

- Official Laravel 13 compatibility while preserving Laravel 10-12 support.
- Inline email attachment support (`inline` disposition + CID) in sender and mailable conversion flows.
- Documentation refresh across deployment, API/routes, commands, architecture, monitoring, and testing guides.

## Added

- Laravel 13 support in Composer constraints.
- Inline attachment capabilities for Mailgun and SendGrid sender payloads.
- Embedded-asset collection support in `MailableConverter` for inline email rendering workflows.

## Changed

- Composer constraints expanded to allow Laravel/Illuminate 10, 11, 12, and 13.
- Removed direct `symfony/uid` dependency.
- Relaxed `psr/log` requirement from exact `3.0` to `^3.0`.
- Root and package docs were rewritten for implementation-aligned guidance.

## Fixed

- Attachment validation now rejects invalid dispositions and inline attachments without `content_id`.
- Test support fakes were adjusted for Laravel 13 contract compatibility.

## Security

- No security-related changes in this release.

## Compatibility

- PHP: `>=8.3`
- Laravel/Illuminate: `^10 || ^11 || ^12 || ^13`

## References

- Full change history: [CHANGELOG.md](CHANGELOG.md)
- Breaking changes and migration guidance: [BREAKING_CHANGES.md](BREAKING_CHANGES.md)
