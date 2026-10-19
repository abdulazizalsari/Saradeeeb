# Phase 4 — Auth, writers, community

**Status: NOT COMPLETE.**

Existing Next.js login/register/logout/me endpoints were identified in previous audits. No claim is made that they are compatible with original PHP password hashes, 2FA, Google OAuth, writer workflows, or community data.

Outstanding: secure original-hash compatibility, reset/activation, OAuth, impersonation, CSRF/RBAC, writer and community workflows, media uploads, subscriptions and comments.

Tests: Playwright authorized/unauthorized end-to-end functional scenarios **not run**. Existing account flows must not be certified without tests.
