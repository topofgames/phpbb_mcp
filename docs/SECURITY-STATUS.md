# Installation and security status

This is a development alpha, not a stable phpBB release. It is intended for validation on a disposable phpBB test installation before internet exposure.

## Changes in this iteration

- Public forum visibility checks now include ancestor forum passwords and anonymous f_list permissions; target forum f_read remains required.
- Unbounded SQL LIKE body-text searching is removed until the native phpBB search backend is integrated.
- The per-token rate budget uses a single conditional SQL UPDATE so simultaneous requests cannot exceed the same window through a reset race.
- No content write or delete endpoints are enabled.

## Remaining release blockers

- Validate the complete extension enable/disable lifecycle, ACP panel, migration and HTTP endpoint in a real phpBB 3.3.19 installation.
- Validate anonymous ACL and password-protected forum cases with real database fixtures.
- Review authentication, reverse-proxy trust, error handling and MCP client compatibility.
- Add native phpBB search and native posting/moderation APIs under explicitly enabled write permissions.

Maintained by TOP OF GAMES. License GPL-2.0-only.
