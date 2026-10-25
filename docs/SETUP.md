# Administrator setup guide

## Requirements
- Supported phpBB 3.3 installation on PHP 7.4 or later; phpBB 3.3.19 is the compatibility target, **not yet integration-tested**.
- A valid HTTPS board URL and the ability to place files in phpBB's ext/ folder.
- Administrator account with founder privileges to create and revoke access keys.
- An MCP client capable of remote HTTP requests with a manually supplied bearer token.

## Installation on a test board
1. Back up both the database and the existing phpBB installation.
2. Download the **phpbb-mcp-extension** artifact from a passing GitHub Actions run.
3. Unzip into the phpBB **ext/** directory. Confirm the paths:
   - ext/topofgames/phpbbmcp/composer.json
   - ext/topofgames/phpbbmcp/ext.php
   - ext/topofgames/phpbbmcp/config/services.yml
4. Administration Control Panel → Customise → Manage extensions → enable phpBB MCP.
5. ACP → Extensions → phpBB MCP. It is disabled by default.
6. Confirm the board's HTTPS server settings. If TLS terminates at a proxy, enter the **actual server-facing IP addresses** of trusted reverse proxies; each proxy must strip user-supplied X-Forwarded-Proto headers.
7. Enable the endpoint, create a read-only token and copy it once.
8. Point a remote Streamable HTTP MCP client to https://forum.example/app.php/mcp with Authorization: Bearer TOKEN.
9. Try initialize or server/discover depending on client protocol version; then tools/list and a public forum read.
10. Create a private forum and verify it is **never visible** to the token.

## Diagnosing errors

| Response | Meaning |
| --- | --- |
| 503 MCP endpoint disabled | Enable in ACP only after configuring HTTPS. |
| 403 HTTPS required | Verify board SSL settings; check reverse-proxy IPs, or remove untrusted forwarding headers. |
| 403 Origin not allowed | Browser Origin must equal the board HTTPS origin. |
| 401 Unauthorized | Missing, malformed, revoked, or expired bearer token. |
| 429 Rate limited | A token exceeded its one-minute request allowance. |
| 400 header mismatch | Client request headers disagree with MCP metadata. |

## Safeguards
- Do not share tokens in screenshots, logs, public bug reports or tickets.
- Revoke credentials immediately after exposure.
- The current alpha supports only publicly readable, approved board content. A founder credential is **not** permission to extract private forum content.
- Posting, moderating, deleting, administration, attachments and user changes are intentionally not active until integration and security testing.
- This alpha has not been validated on an actual installation of phpBB 3.3.19 and must not be offered as stable.
