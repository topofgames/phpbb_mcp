<?php
namespace topofgames\phpbbmcp\migrations;

class v_0_1_0 extends \phpbb\db\migration\migration
{
    public function update_schema()
    {
        return [
            'add_tables' => [
                $this->table_prefix . 'phpbbmcp_keys' => [
                    'COLUMNS' => [
                        'key_id' => ['UINT', null, 'auto_increment'],
                        'key_hash' => ['VCHAR:64', ''],
                        'key_prefix' => ['VCHAR:20', ''],
                        'key_label' => ['VCHAR:80', ''],
                        'created_by' => ['UINT', 0],
                        'scope_list' => ['VCHAR:255', ''],
                        'created_at' => ['UINT:11', 0],
                        'expires_at' => ['UINT:11', 0],
                        'revoked_at' => ['UINT:11', 0],
                        'last_used_at' => ['UINT:11', 0],
                        'window_start' => ['UINT:11', 0],
                        'window_count' => ['UINT', 0],
                    ],
                    'PRIMARY_KEY' => 'key_id',
                    'KEYS' => [
                        'key_hash' => ['UNIQUE', 'key_hash'],
                        'key_active' => ['INDEX', ['revoked_at', 'expires_at']],
                    ],
                ],
            ],
        ];
    }

    public function revert_schema()
    {
        return ['drop_tables' => [$this->table_prefix . 'phpbbmcp_keys']];
    }

    public function update_data()
    {
        return [
            ['config.add', ['topofgames_phpbbmcp_enabled', 0]],
            ['module.add', ['acp', 'ACP_CAT_DOT_MODS', 'ACP_PHPBBMCP_TITLE']],
            ['module.add', ['acp', 'ACP_PHPBBMCP_TITLE', [
                'module_basename' => '\topofgames\phpbbmcp\acp\main_module',
                'modes' => ['settings'],
            ]]],
        ];
    }
}
