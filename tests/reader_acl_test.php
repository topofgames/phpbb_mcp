<?php
namespace {
    if (!defined('USERS_TABLE')) { define('USERS_TABLE', 'phpbb_users'); }
}
namespace topofgames\phpbbmcp\tests {
    class FakeResult {
        public $rows;
        public $position = 0;
        public function __construct($rows) { $this->rows = $rows; }
    }
    class FakeDb {
        public $forums;
        public function __construct(array $forums) { $this->forums = $forums; }
        public function sql_query_limit($sql, $limit, $offset = 0) {
            $rows = [];
            if (strpos($sql, 'INNER JOIN') !== false) {
                if (preg_match('/child\.forum_id = (\d+)/', $sql, $m)) {
                    $child = $this->forums[(int) $m[1]] ?? null;
                    if ($child && $child['forum_type'] == 1) {
                        foreach ($this->forums as $f) {
                            if ($f['left_id'] <= $child['left_id'] &&
                                $f['right_id'] >= $child['right_id']) {
                                $rows[] = [
                                    'forum_id' => $f['forum_id'],
                                    'forum_password' => $f['forum_password'],
                                    'forum_type' => $f['forum_type']
                                ];
                            }
                        }
                    }
                }
            }
            if (strpos($sql, 'INNER JOIN') === false && preg_match('/WHERE forum_id = (\d+)/', $sql, $m)) {
                $f = $this->forums[(int) $m[1]] ?? null;
                if ($f) {
                    $rows[] = [
                        'forum_id' => $f['forum_id'],
                        'parent_id' => 2,
                        'forum_name' => 'Public test forum',
                        'forum_desc' => '',
                        'forum_topics_approved' => 0,
                        'forum_posts_approved' => 0,
                    ];
                }
            }
            return new FakeResult(array_slice($rows, $offset, $limit));
        }
        public function sql_fetchrow($result) {
            return $result->rows[$result->position++] ?? false;
        }
        public function sql_freeresult($result) {}
    }
    class FakeGuestAuth {
        public $permissions;
        public function __construct(array $permissions) { $this->permissions = $permissions; }
        public function acl_get($permission, $forum_id) {
            return (bool) ($this->permissions[$forum_id][$permission] ?? false);
        }
    }
}
namespace {
    require_once __DIR__ . '/../service/reader.php';
    $forum = function ($id, $left, $right, $password = '', $type = 0) {
        return ['forum_id' => $id, 'left_id' => $left, 'right_id' => $right,
            'forum_password' => $password, 'forum_type' => $type];
    };
    $base = [
        1 => $forum(1, 1, 8),
        2 => $forum(2, 2, 7),
        3 => $forum(3, 3, 4, '', 1),
    ];
    $perm = [
        1 => ['f_list' => true],
        2 => ['f_list' => true],
        3 => ['f_list' => true, 'f_read' => true],
        0 => ['u_viewprofile' => false]
    ];
    $check = function ($forums, $permissions, $expected, $label) {
        $db = new \topofgames\phpbbmcp\tests\FakeDb($forums);
        $auth = new \topofgames\phpbbmcp\tests\FakeGuestAuth($permissions);
        $reader = new \topofgames\phpbbmcp\service\reader($db, 'phpbb_', $auth);
        $result = $reader->run('forums.get', ['forum_id' => 3]);
        $actual = $result['item'] !== null;
        if ($actual !== $expected) {
            fwrite(STDERR, "FAIL: " . $label . PHP_EOL);
            exit(1);
        }
    };
    $check($base, $perm, true, 'public forum');
    $locked = $base;
    $locked[2]['forum_password'] = '$2y$something';
    $check($locked, $perm, false, 'password-protected ancestor');
    $denied = $perm;
    $denied[2]['f_list'] = false;
    $check($base, $denied, false, 'hidden ancestor');
    $denied = $perm;
    $denied[3]['f_read'] = false;
    $check($base, $denied, false, 'no reading permission');
    $locked = $base;
    $locked[3]['forum_password'] = 'password';
    $check($locked, $perm, false, 'password-protected child');
    echo 'PASS: 5 public-forum policy test cases' . PHP_EOL;
}
