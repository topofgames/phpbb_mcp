<?php
namespace topofgames\phpbbmcp\service;

/**
 * Transport security independent of phpBB request::is_secure(), which
 * unconditionally trusts an incoming X-Forwarded-Proto header.
 */
class tls_policy
{
    public function is_allowed($board_protocol, $https, $remote_ip, $forwarded_proto, $trusted_proxies)
    {
        if (strtolower((string) $board_protocol) !== 'https://') {
            return false;
        }
        if (in_array(strtolower((string) $https), ['on', '1', 'https'], true)) {
            return true;
        }
        $trusted = array_filter(array_map('trim', explode(',', (string) $trusted_proxies)));
        return filter_var($remote_ip, FILTER_VALIDATE_IP) !== false &&
            in_array($remote_ip, $trusted, true) &&
            strtolower(trim((string) $forwarded_proto)) === 'https';
    }
}
