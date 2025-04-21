<?php

/***************************************************************************
 *
 *    ougc File Profile Fields plugin (/inc/plugins/ougc/FileProfileFields/admin.php)
 *    Author: Omar Gonzalez
 *    Copyright: © 2020 Omar Gonzalez
 *
 *    Website: https://ougc.network
 *
 *    Maximize your profile with custom file profile fields.
 *
 ***************************************************************************
 ****************************************************************************
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 ****************************************************************************/

declare(strict_types=1);

namespace ougc\FileProfileFields\Admin;

use DirectoryIterator;

use function ougc\FileProfileFields\Core\languageLoad;

use const ougc\FileProfileFields\Core\ROOT;

const TABLES_DATA = [
    'ougc_fileprofilefields_files' => [
        'aid' => [
            'type' => 'INT',
            'unsigned' => true,
            'auto_increment' => true,
            'primary_key' => true
        ],
        'uid' => [
            'type' => 'INT',
            'unsigned' => true
        ],
        'muid' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'fid' => [
            'type' => 'INT',
            'unsigned' => true
        ],
        'filename' => [
            'type' => 'VARCHAR',
            'size' => 255,
            'default' => ''
        ],
        'filesize' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'filemime' => [
            'type' => 'VARCHAR',
            'size' => 64,
            'default' => ''
        ],
        'name' => [
            'type' => 'VARCHAR',
            'size' => 255,
            'default' => ''
        ],
        'downloads' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'thumbnail' => [
            'type' => 'VARCHAR',
            'size' => 255,
            'default' => ''
        ],
        'dimensions' => [
            'type' => 'VARCHAR',
            'size' => 11,
            'default' => ''
        ],
        'md5hash' => [
            'type' => 'VARCHAR',
            'size' => 32,
            'default' => ''
        ],
        'uploaddate' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'updatedate' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'status' => [
            'type' => 'TINYINT',
            //'unsigned' => true,
            'default' => 1//all approved by default
        ],
        'unique_key' => ['uidfid' => 'uid,fid']
    ],
    'ougc_fileprofilefields_logs' => [
        'lid' => [
            'type' => 'INT',
            'unsigned' => true,
            'auto_increment' => true,
            'primary_key' => true
        ],
        'uid' => [
            'type' => 'INT',
            'unsigned' => true
        ],
        'aid' => [
            'type' => 'INT',
            'unsigned' => true
        ],
        'ipaddress' => [
            'type' => 'VARBINARY',
            'size' => 16,
            'default' => ''
        ],
        'dateline' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ]
    ]
];

const FIELDS_DATA = [
    'profilefields' => [
        'ougc_fileprofilefields_types' => [
            'type' => 'VARCHAR',
            'size' => 50,
            'default' => '-1'
        ],
        'ougc_fileprofilefields_maxsize' => [ // empty for attachment setting
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'ougc_fileprofilefields_directory' => [
            'type' => 'VARCHAR',
            'size' => 255,
            'default' => ''
        ],
        'ougc_fileprofilefields_customoutput' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'ougc_fileprofilefields_imageonly' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'ougc_fileprofilefields_imagemindims' => [
            'type' => 'VARCHAR',
            'size' => 11,
            'default' => ''
        ],
        'ougc_fileprofilefields_imagemaxdims' => [
            'type' => 'VARCHAR',
            'size' => 11,
            'default' => ''
        ],
        'ougc_fileprofilefields_thumbnails' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'ougc_fileprofilefields_thumbnailsdimns' => [
            'type' => 'VARCHAR',
            'size' => 11,
            'default' => ''
        ],
    ],
    'attachtypes' => [
        'ougc_fileprofilefields' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ]
    ]
];

function pluginInformation(): array
{
    global $lang;

    languageLoad();

    return [
        'name' => 'ougc File Profile Fields',
        'description' => $lang->setting_group_ougc_fileprofilefields_desc . coreEditsDescription(),
        'website' => 'https://ougc.network',
        'author' => 'Omar G.',
        'authorsite' => 'https://ougc.network',
        'version' => '1.8.5',
        'versioncode' => 1805,
        'compatibility' => '18*',
        'codename' => 'ougc_fileprofilefields',
        'pl' => [
            'version' => 13,
            'url' => 'https://community.mybb.com/mods.php?action=view&pid=573'
        ]
    ];
}

function pluginActivation(): bool
{
    global $PL, $lang, $cache, $db;

    pluginLibraryLoad();

    // TODO: Maybe add some approval system
    $PL->settings(
        'ougc_fileprofilefields',
        $lang->setting_group_ougc_fileprofilefields,
        $lang->setting_group_ougc_fileprofilefields_desc,
        [
            'groups_moderators' => [
                'title' => $lang->setting_ougc_fileprofilefields_groups_moderators,
                'description' => $lang->setting_ougc_fileprofilefields_groups_moderators_desc,
                'optionscode' => 'groupselect',
                'value' => 4,
            ],
            'perpage' => [
                'title' => $lang->setting_ougc_fileprofilefields_perpage,
                'description' => $lang->setting_ougc_fileprofilefields_perpage_desc,
                'optionscode' => 'numeric',
                'value' => 10,
            ],
            'groups_moderate' => [
                'title' => $lang->setting_ougc_fileprofilefields_groups_moderate,
                'description' => $lang->setting_ougc_fileprofilefields_groups_moderate_desc,
                'optionscode' => 'groupselect',
                'value' => -1,
            ],
            'image_resize' => [
                'title' => $lang->setting_ougc_fileprofilefields_image_resize,
                'description' => $lang->setting_ougc_fileprofilefields_image_resize_desc,
                'optionscode' => 'onoff',
                'value' => 1,
            ],
            'download_interval' => [
                'title' => $lang->setting_ougc_fileprofilefields_download_interval,
                'description' => $lang->setting_ougc_fileprofilefields_download_interval_desc,
                'optionscode' => 'numeric',
                'value' => 5,
            ],
            'author_downloads' => [
                'title' => $lang->setting_ougc_fileprofilefields_author_downloads,
                'description' => $lang->setting_ougc_fileprofilefields_author_downloads_desc,
                'optionscode' => 'yesno',
                'value' => 1,
            ],
            'force_downloads' => [
                'title' => $lang->setting_ougc_fileprofilefields_force_downloads,
                'description' => $lang->setting_ougc_fileprofilefields_force_downloads_desc,
                'optionscode' => 'yesno',
                'value' => 0,
            ],
        ]
    );

    // Add templates
    $templatesDirIterator = new DirectoryIterator(ROOT . '/templates');

    $templates = [];

    foreach ($templatesDirIterator as $template) {
        if (!$template->isFile()) {
            continue;
        }

        $pathName = $template->getPathname();

        $pathInfo = pathinfo($pathName);

        if ($pathInfo['extension'] === 'html') {
            $templates[$pathInfo['filename']] = file_get_contents($pathName);
        }
    }

    if ($templates) {
        $PL->templates('ougcfileprofilefields', 'ougc File Profile Fields', $templates);
    }

    // Insert/update version into cache
    $plugins = $cache->read('ougc_plugins');

    if (!$plugins) {
        $plugins = [];
    }

    $_info = pluginInformation();

    if (!isset($plugins['fileprofilefields'])) {
        $plugins['fileprofilefields'] = $_info['versioncode'];
    }

    dbVerifyTables();

    dbVerifyColumns();

    /*~*~* RUN UPDATES START *~*~*/

    /*~*~* RUN UPDATES END *~*~*/

    $plugins['fileprofilefields'] = $_info['versioncode'];

    $cache->update('ougc_plugins', $plugins);

    return true;
}

function pluginInstallation(): bool
{
    dbVerifyTables();

    dbVerifyColumns();

    return true;
}

function pluginIsInstalled(): bool
{
    static $isInstalled = null;

    if ($isInstalled === null) {
        global $db;

        $isInstalledEach = true;

        foreach (TABLES_DATA as $tableName => $tableColumns) {
            $isInstalledEach = $db->table_exists($tableName) && $isInstalledEach;
        }

        $isInstalled = $isInstalledEach;
    }

    return $isInstalled;
}

function pluginUninstallation(): bool
{
    global $db, $PL, $cache;

    pluginLibraryLoad();

    // Drop DB entries
    foreach (TABLES_DATA as $name => $table) {
        $db->drop_table($name);
    }

    foreach (FIELDS_DATA as $table => $columns) {
        foreach ($columns as $name => $definition) {
            !$db->field_exists($name, $table) || $db->drop_column($table, $name);
        }
    }

    $PL->settings_delete('ougc_fileprofilefields');

    $PL->templates_delete('ougcfileprofilefields');

    // Delete version from cache
    $plugins = (array)$cache->read('ougc_plugins');

    if (isset($plugins['fileprofilefields'])) {
        unset($plugins['fileprofilefields']);
    }

    if (!empty($plugins)) {
        $cache->update('ougc_plugins', $plugins);
    } else {
        $cache->delete('ougc_plugins');
    }

    return true;
}

function pluginLibraryLoad(bool $check = true): bool
{
    global $PL, $lang;

    languageLoad();

    if ($file_exists = file_exists(PLUGINLIBRARY)) {
        global $PL;

        $PL or require_once PLUGINLIBRARY;
    }

    if (!$check) {
        return false;
    }

    $_info = pluginInformation();

    if (!$file_exists || $PL->version < $_info['pl']['version']) {
        flash_message(
            $lang->sprintf($lang->ougc_fileprofilefields_pluginlibrary, $_info['pl']['url'], $_info['pl']['version']),
            'error'
        );

        admin_redirect('index.php?module=config-plugins');
    }

    return true;
}

function dbTables(): array
{
    $tables_data = [];

    foreach (TABLES_DATA as $tableName => $tableColumns) {
        foreach ($tableColumns as $fieldName => $fieldData) {
            if (!isset($fieldData['type'])) {
                continue;
            }

            $tables_data[$tableName][$fieldName] = dbBuildFieldDefinition($fieldData);
        }

        foreach ($tableColumns as $fieldName => $fieldData) {
            if (isset($fieldData['primary_key'])) {
                $tables_data[$tableName]['primary_key'] = $fieldName;
            }

            if ($fieldName === 'unique_key') {
                $tables_data[$tableName]['unique_key'] = $fieldData;
            }
        }
    }

    return $tables_data;
}

function dbVerifyTables(): bool
{
    global $db;

    $collation = $db->build_create_table_collation();

    foreach (dbTables() as $tableName => $tableColumns) {
        if ($db->table_exists($tableName)) {
            foreach ($tableColumns as $fieldName => $fieldData) {
                if ($fieldName == 'primary_key' || $fieldName == 'unique_key') {
                    continue;
                }

                if ($db->field_exists($fieldName, $tableName)) {
                    $db->modify_column($tableName, "`{$fieldName}`", $fieldData);
                } else {
                    $db->add_column($tableName, $fieldName, $fieldData);
                }
            }
        } else {
            $query_string = "CREATE TABLE IF NOT EXISTS `{$db->table_prefix}{$tableName}` (";

            foreach ($tableColumns as $fieldName => $fieldData) {
                if ($fieldName == 'primary_key') {
                    $query_string .= "PRIMARY KEY (`{$fieldData}`)";
                } elseif ($fieldName != 'unique_key') {
                    $query_string .= "`{$fieldName}` {$fieldData},";
                }
            }

            $query_string .= ") ENGINE=MyISAM{$collation};";

            $db->write_query($query_string);
        }
    }

    dbVerifyIndexes();

    return true;
}

function dbVerifyIndexes(): bool
{
    global $db;

    foreach (dbTables() as $tableName => $tableColumns) {
        if (!$db->table_exists($tableName)) {
            continue;
        }

        if (isset($tableColumns['unique_key'])) {
            foreach ($tableColumns['unique_key'] as $key_name => $key_value) {
                if ($db->index_exists($tableName, $key_name)) {
                    continue;
                }

                $db->write_query(
                    "ALTER TABLE {$db->table_prefix}{$tableName} ADD UNIQUE KEY {$key_name} ({$key_value})"
                );
            }
        }
    }

    return true;
}

function dbVerifyColumns(array $fieldsData = FIELDS_DATA): bool
{
    global $db;

    foreach ($fieldsData as $tableName => $tableColumns) {
        if (!$db->table_exists($tableName)) {
            continue;
        }

        foreach ($tableColumns as $fieldName => $fieldData) {
            if (!isset($fieldData['type'])) {
                continue;
            }

            if ($db->field_exists($fieldName, $tableName)) {
                $db->modify_column($tableName, "`{$fieldName}`", dbBuildFieldDefinition($fieldData));
            } else {
                $db->add_column($tableName, $fieldName, dbBuildFieldDefinition($fieldData));
            }
        }
    }

    return true;
}

function dbBuildFieldDefinition(array $fieldData): string
{
    $field_definition = '';

    $field_definition .= $fieldData['type'];

    if (isset($fieldData['size'])) {
        $field_definition .= "({$fieldData['size']})";
    }

    if (isset($fieldData['unsigned'])) {
        if ($fieldData['unsigned'] === true) {
            $field_definition .= ' UNSIGNED';
        } else {
            $field_definition .= ' SIGNED';
        }
    }

    if (!isset($fieldData['null'])) {
        $field_definition .= ' NOT';
    }

    $field_definition .= ' NULL';

    if (isset($fieldData['auto_increment'])) {
        $field_definition .= ' AUTO_INCREMENT';
    }

    if (isset($fieldData['default'])) {
        $field_definition .= " DEFAULT '{$fieldData['default']}'";
    }

    return $field_definition;
}

function coreEditsDescription(): string
{
    global $cache;

    $plugins = $cache->read('plugins');

    $edits_desc = '';

    if (!pluginIsInstalled() || empty($plugins['active']['ougc_fileprofilefields'])) {
        return $edits_desc;
    }

    global $PL, $mybb, $page, $lang;

    pluginLibraryLoad(false);

    $_edits_apply = $_edits_revert = false;

    // Check edits to core files.
    if (coreEditsApply() !== true) {
        $_edits_apply = $lang->sprintf(
            $lang->ougc_fileprofilefields_edits_apply,
            $PL->url_append('index.php', [
                'module' => 'config-plugins',
                'ougc_fileprofilefields' => 'apply',
                'my_post_key' => $mybb->post_code
            ])
        );
    }

    if ($_edits_apply) {
        $edits_desc .= "<ul><li style=\"list-style-image: url('styles/{$page->style}/images/icons/error.png')\">{$_edits_apply}</li></ul>";
    }

    // Check edits to core files.
    if (coreEditsRevert() !== true) {
        $_edits_revert = $lang->sprintf(
            $lang->ougc_fileprofilefields_edits_revert,
            $PL->url_append('index.php', [
                'module' => 'config-plugins',
                'ougc_fileprofilefields' => 'revert',
                'my_post_key' => $mybb->post_code
            ])
        );
    }

    if ($_edits_revert) {
        $edits_desc .= "<ul><li style=\"list-style-image: url('styles/{$page->style}/images/icons/success.png')\">{$_edits_revert}</li></ul>";
    }

    return $edits_desc;
}

function coreEditsApply(bool $apply = false): bool
{
    global $PL;

    pluginLibraryLoad(false);

    if ($PL->edit_core('ougc_plugins_customfields_start', 'member.php', [
            'search' => ['if(isset($userfields[$field]))'],
            'before' => [
                '$hookArguments = [
				\'userData\' => &$userfields,
				\'profileFieldData\' => &$customfield,
				\'fieldCode\' => &$customfieldval
			];
			
			$plugins->run_hooks(\'ougc_file_profile_fields_profile\', $hookArguments);'
            ],
        ], $apply) !== true) {
        return false;
    }

    if ($PL->edit_core('ougc_plugins_customfields_start', 'inc/functions_post.php', [
            'search' => ['eval("\$post[\'profilefield\'] .= \"".$templates->get("postbit_profilefield")."\";");'],
            'before' => [
                '$hookArguments = [
				\'userData\' => &$post,
				\'profileFieldData\' => &$field
			];
			
			$plugins->run_hooks(\'ougc_file_profile_fields_post_start\', $hookArguments);',
            ],
        ], $apply) !== true) {
        return false;
    }

    if ($PL->edit_core('ougc_plugins_customfields_begin', 'inc/functions_post.php', [
            'search' => ['$hascustomtitle = 0;'],
            'before' => [
                '$hookArguments = [
				\'userData\' => &$post,
				\'postType\' => &$post_type,
			];
			
			$plugins->run_hooks(\'postbit_start\', $hookArguments);',
            ],
        ], $apply) !== true) {
        return false;
    }

    return true;
}

function coreEditsRevert(bool $apply = false): bool
{
    global $PL;

    pluginLibraryLoad(false);

    return $PL->edit_core('ougc_plugins_customfields_start', 'member.php', [], $apply) === true &&
        $PL->edit_core('ougc_plugins_customfields_start', 'inc/functions_post.php', [], $apply) === true;
}