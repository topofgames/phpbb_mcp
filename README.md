# phpBB MCP — native extension

Open-source, native [Model Context Protocol](https://modelcontextprotocol.io/) integration for phpBB 3.3, designed for ordinary PHP hosting and ACP installation.

> **0.1.0-alpha / DRAFT — NOT PRODUCTION READY.** No completed phpBB integration tests exist yet. Do not expose this alpha to the internet until security and integration gates below have passed.

## How users will install (after validation)

1. Back up the forum database and files.
2. Download the phpbb-mcp-extension ZIP artifact from a passing GitHub Actions build.
3. Extract ZIP into the phpBB **ext/** folder, resulting in **ext/topofgames/phpbbmcp/**.
4. ACP → Customise → Manage extensions → enable phpBB MCP.
5. ACP → Extensions → phpBB MCP. A **board founder** activates the endpoint and creates a scoped access token.
6. Make sure the phpBB server URL is HTTPS. Reverse proxies must sanitize forwarded headers and prevent direct origin access.
7. Connect an HTTP-based MCP client to **https://your-forum.example/app.php/mcp** (URL rewriting may shorten the path), with **Authorization: Bearer TOKEN**.

No Node.js, Docker, special process, or command-line access is needed for the phpBB extension itself. Credentials are shown once and can be revoked.

## Included in the alpha source

- Native phpBB extension manifests, routes, services, migrations and founder-only ACP controls.
- Stateless HTTP POST endpoint with JSON-RPC and modern **2026-07-28** MCP request format, including server/discover, mirrored request headers, request metadata, resultType and cache hints.
- Compatibility with legacy MCP **2025-06-18** and **2025-11-25** initialize clients.
- HTTPS and strict same-origin enforcement; arbitrary cross-origin browser clients are intentionally not supported.
- Bearer tokens: SHA-256 hashes at rest, scoped grants, 1–90 day expiry, revocation, per-token rate budget.
- Read-only tools: forums.list, forums.get, topics.list, topics.get, posts.list, posts.get, posts.search, users.get_public.
- English and Romanian ACP strings.
- CI lints the extension for PHP 7.4, 8.1 and 8.4; pure transport smoke tests and a ZIP build are provided.
- ZIP generation alone does **not** establish phpBB compatibility or security.

## Required before beta/stable

1. Integrate with a clean phpBB **3.3.19** instance to validate service wiring, ACP, SQL migration, URL routing and PHP compatibility.
2. Run privacy and ACL negative tests for hidden/passworded forums, moderators, restricted categories, unapproved posts/topics, private profiles and newly changed permissions.
3. Replace slow SQL LIKE post-text searching with phpBB's native search index.
4. Verify HTTPS reverse-proxy cases, token expiry/revocation, CSRF and rate-limiter concurrency. Add audit logging and secure diagnostics.
5. Add separate founder-controlled **write scopes** and native phpBB posting/moderation API integrations. Dangerous operations require confirmations, audit trails and backup guidance.
6. Validate MCP Inspector/clients, add documentation/screenshots and tagged release checksums.

## Security principles

The MCP endpoint is **disabled by default**. No tokens exist until a founder creates one. All currently advertised tools are read-only and intended to reveal only **public anonymous-readable** content. No credential can bypass forum privacy. Runtime permission tests remain a release-blocker.

This alpha uses static Bearer headers rather than a full OAuth flow. OAuth-only clients will require additional integration in a later milestone.

## License

GPL-2.0-only. See LICENSE.
