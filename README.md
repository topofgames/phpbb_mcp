# phpBB MCP

A native, open-source [Model Context Protocol](https://modelcontextprotocol.io/) endpoint for phpBB.

> **Status: 0.1.0-alpha — untested foundation, not production-ready.** This branch is intended for integration review against a real phpBB 3.3 install. Do not expose it publicly or grant tokens until all compatibility, permission and security checks pass.

## Purpose

Build an easy-to-install phpBB extension so MCP clients (ChatGPT, Codex, Hermes and other compatible clients) can safely interact with phpBB. The project deliberately does **not** modify phpBB core files or require Node.js, Docker or a daemon.

## Installation (after validation)

1. Back up the phpBB database and files.
2. Place this extension under **phpBB_ROOT/ext/topofgames/phpbbmcp/** (the repository root is the extension root, not an extra nested folder).
3. ACP → Customise → Manage extensions → enable **phpBB MCP**.
4. ACP → Extensions → **phpBB MCP**. Only a board founder may enable the endpoint or create a credential.
5. Require working HTTPS and configure any reverse proxy as trusted in phpBB.
6. Create a short-lived, least-privilege token. Copy the value exactly once.
7. Connect a compatible MCP client to **https://your-forum.example/app.php/mcp** using an Authorization: Bearer header. Route rewriting may change the visible URL.

The endpoint is disabled by default. Tokens expire after 1–90 days and can be revoked.

## Current implemented foundation

- PHP-only extension packaging, routing, ACP and a migration for credential storage.
- Stateless MCP-compatible JSON-RPC over POST, initialize/ping/tools/list/tools/call.
- Read-only tools for forums, topics, posts, search, and public user profiles.
- Token hashes in the database; per-token scopes, expiry and revocation.
- Enforced HTTPS, request-body limit, and basic per-token rate limiting.
- Anonymous/public forum ACL gating on forum and content reads.

## Known gaps to resolve before release

- Run integration tests on a clean phpBB 3.3 instance, including migrations, ACP routing, guest ACL checks, and API calls. The repository has **not** yet passed these tests.
- Verify PHP/phpBB compatibility; current code uses PHP 8's \`array_is_list\` and therefore **needs PHP 8.1+** unless polyfilled.
- Make rate limiting atomic across windows and add full audit logging and tool-level permissions.
- Harden search for large boards and use native phpBB search and content processing.
- Complete the authorization model and HTTP transport compliance review against the current MCP specification (including Origin validation and negotiation headers).
- Add safe write tools using phpBB's native posting/moderation APIs, never direct database writes for content.
- Automated release ZIP and CI, translated ACP strings, docs and browser tests.
- Confirm all phpBB compatibility and data-privacy requirements before declaring a stable release.

## Licensing

GPL-2.0-only, matching the project's existing license file.
