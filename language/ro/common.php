<?php
if (!defined('IN_PHPBB')) { exit; }
$lang = array_merge($lang, [
    'ACP_PHPBBMCP_TITLE' => 'phpBB MCP',
    'ACP_PHPBBMCP_EXPLAIN' => 'Configurează accesul pentru clienții AI. Cheile sunt afișate o singură dată. Folosește HTTPS și permisiuni minime. Endpointul MCP este dezactivat implicit.',
    'ACP_PHPBBMCP_ONETIME' => 'Copiază această cheie acum. Nu va mai fi afișată.',
    'ACP_PHPBBMCP_ENABLE' => 'Activează endpointul MCP',
    'ACP_PHPBBMCP_CREATE' => 'Creează o cheie de acces',
    'ACP_PHPBBMCP_LABEL' => 'Eticheta cheii',
    'ACP_PHPBBMCP_DAYS' => 'Valabilitate în zile (1–90)',
    'ACP_PHPBBMCP_SCOPES' => 'Permisiuni de acces',
    'ACP_PHPBBMCP_KEYS' => 'Chei de acces',
    'ACP_PHPBBMCP_TRUSTED_PROXIES' => 'Adresele IP ale proxy-urilor de încredere',
    'ACP_PHPBBMCP_PROXY_HELP' => 'IP-uri exacte separate prin virgulă. Lasă gol pentru HTTPS direct. Configurează doar proxy-uri controlate de tine care elimină antetele Forwarded nesigure.',
]);
