<?php
if (!defined('IN_PHPBB')) { exit; }
$lang = array_merge($lang, [
    'ACP_PHPBBMCP_TITLE' => 'phpBB MCP',
    'ACP_PHPBBMCP_EXPLAIN' => 'Control access for AI clients. Tokens are displayed once. Use HTTPS and minimum read scopes. The MCP endpoint is disabled by default.',
    'ACP_PHPBBMCP_ONETIME' => 'Copy this token now. It will not be displayed again.',
    'ACP_PHPBBMCP_ENABLE' => 'Enable MCP endpoint',
    'ACP_PHPBBMCP_CREATE' => 'Create access token',
    'ACP_PHPBBMCP_LABEL' => 'Token label',
    'ACP_PHPBBMCP_DAYS' => 'Expiry in days (1–90)',
    'ACP_PHPBBMCP_SCOPES' => 'Access scopes',
    'ACP_PHPBBMCP_KEYS' => 'Access tokens',
    'ACP_PHPBBMCP_TRUSTED_PROXIES' => 'Trusted reverse proxy IP addresses',
    'ACP_PHPBBMCP_PROXY_HELP' => 'Comma-separated exact IP addresses. Leave empty on direct HTTPS hosting. Only list proxies you control that remove untrusted forwarded headers.',
]);
