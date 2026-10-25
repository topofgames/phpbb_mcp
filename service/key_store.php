<?php
namespace topofgames\phpbbmcp\service;

/**
 * Tokens are high-entropy credentials; only SHA-256 hashes are persisted.
 * All keys are issued by phpBB founders, and founders are revalidated on use.
 */
class key_store
{
    private $db;
    private $table;

    public function __construct($db, $table_prefix)
    {
        $this->db = $db;
        $this->table = $table_prefix . 'phpbbmcp_keys';
    }

    public function issue($label, $founder_id, array $scopes, $days)
    {
        $allowed = ['forums:read', 'topics:read', 'posts:read', 'users:read'];
        $scopes = array_values(array_intersect($allowed, array_unique($scopes)));
        if (!$scopes) {
            throw new \InvalidArgumentException('Select at least one read scope.');
        }
        $label = trim((string) $label);
        if ($label === '' || strlen($label) > 80) {
            throw new \InvalidArgumentException('Label must be 1-80 bytes.');
        }
        $days = max(1, min(90, (int) $days));
        $secret = 'pmcp_' . rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $time = time();
        $row = [
            'key_hash' => hash('sha256', $secret),
            'key_prefix' => substr($secret, 0, 16),
            'key_label' => $label,
            'created_by' => (int) $founder_id,
            'scope_list' => implode(',', $scopes),
            'created_at' => $time,
            'expires_at' => $time + 86400 * $days,
            'revoked_at' => 0,
            'last_used_at' => 0,
            'window_start' => $time,
            'window_count' => 0,
        ];
        $this->db->sql_query('INSERT INTO ' . $this->table . ' ' . $this->db->sql_build_array('INSERT', $row));
        return $secret;
    }

    public function list_keys()
    {
        $result = $this->db->sql_query_limit('SELECT key_id, key_prefix, key_label, created_by,
                    scope_list, created_at, expires_at, revoked_at, last_used_at
                    FROM ' . $this->table . ' ORDER BY key_id DESC', 100);
        $rows = [];
        while ($row = $this->db->sql_fetchrow($result)) {
            $rows[] = $row;
        }
        $this->db->sql_freeresult($result);
        return $rows;
    }

    public function revoke($key_id)
    {
        $this->db->sql_query('UPDATE ' . $this->table . ' SET revoked_at = ' . time() .
            ' WHERE key_id = ' . (int) $key_id . ' AND revoked_at = 0');
    }

    public function authorize($token)
    {
        if (!is_string($token) || !preg_match('/^pmcp_[A-Za-z0-9_-]{43}$/D', $token)) {
            return null;
        }
        $hash = hash('sha256', $token);
        $sql = 'SELECT k.*, u.user_type FROM ' . $this->table . ' k
                INNER JOIN ' . USERS_TABLE . ' u ON u.user_id = k.created_by
                WHERE k.key_hash = \'' . $this->db->sql_escape($hash) . '\'
                AND k.revoked_at = 0 AND k.expires_at > ' . time() .
                ' AND u.user_type = 3';
        $result = $this->db->sql_query_limit($sql, 1);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);
        if (!$row) {
            return null;
        }

        // Database-enforced sliding fixed-minute budget; checked on every call.
        $id = (int) $row['key_id'];
        $now = time();
        if ((int) $row['window_start'] <= $now - 60) {
            $this->db->sql_query('UPDATE ' . $this->table .
                ' SET window_start = ' . $now . ', window_count = 0 WHERE key_id = ' . $id .
                ' AND window_start <= ' . ($now - 60));
        }
        $this->db->sql_query('UPDATE ' . $this->table .
            ' SET window_count = window_count + 1, last_used_at = ' . $now .
            ' WHERE key_id = ' . $id . ' AND window_count < 60 AND revoked_at = 0');
        if ($this->db->sql_affectedrows() < 1) {
            return ['rate_limited' => true];
        }
        $row['scopes'] = explode(',', $row['scope_list']);
        return $row;
    }
}
