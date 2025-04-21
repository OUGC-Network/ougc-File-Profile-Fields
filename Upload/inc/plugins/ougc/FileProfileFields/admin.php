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

use function ougc\FileProfileFields\Core\load_language;
use function ougc\FileProfileFields\Core\load_pluginlibrary;

use const ougc\FileProfileFields\Core\ROOT;

function _info(): array
{
    global $lang;

    load_language();

    return [
        'name' => 'ougc File Profile Fields',
        'description' => $lang->setting_group_ougc_fileprofilefields_desc . _edits_description(),
        'website' => 'https://ougc.network',
        'author' => 'Omar G.',
        'authorsite' => 'https://ougc.network',
        'version' => '1.8.4',
        'versioncode' => 1804,
        'compatibility' => '18*',
        'codename' => 'ougc_fileprofilefields',
        'pl' => [
            'version' => 13,
            'url' => 'https://community.mybb.com/mods.php?action=view&pid=573'
        ]
    ];
}

function _activate(): bool
{
    global $PL, $lang, $cache, $db;

    load_pluginlibrary();

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

    $_info = _info();

    if (!isset($plugins['fileprofilefields'])) {
        $plugins['fileprofilefields'] = $_info['versioncode'];
    }

    _db_verify_tables();

    _db_verify_columns();

    /*~*~* RUN UPDATES START *~*~*/

    /*~*~* RUN UPDATES END *~*~*/

    $plugins['fileprofilefields'] = $_info['versioncode'];

    $cache->update('ougc_plugins', $plugins);

    return true;
}

function _install(): bool
{
    _db_verify_tables();

    _db_verify_columns();

    return true;
}

function _is_installed(): bool
{
    static $installed = null;

    if ($installed === null) {
        global $db;

        foreach (_db_tables() as $name => $table) {
            $installed = $db->table_exists($name);

            break;
        }
    }

    return $installed;
}

function _uninstall(): bool
{
    global $db, $PL, $cache;

    load_pluginlibrary();

    // Drop DB entries
    foreach (_db_tables() as $name => $table) {
        $db->drop_table($name);
    }

    foreach (_db_columns() as $table => $columns) {
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

function _db_tables(): array
{
    return [
        'ougc_fileprofilefields_files' => [
            'aid' => 'int UNSIGNED NOT NULL AUTO_INCREMENT',
            'uid' => 'int UNSIGNED NOT NULL',
            'muid' => 'int UNSIGNED NOT NULL DEFAULT 0',
            'fid' => 'int UNSIGNED NOT NULL',
            'filename' => "varchar(255) NOT NULL DEFAULT ''",
            'filesize' => "int(10) NOT NULL DEFAULT '0'",
            'filemime' => "varchar(64) NOT NULL DEFAULT ''",
            'name' => "varchar(255) NOT NULL DEFAULT ''",
            'downloads' => "int(10) NOT NULL DEFAULT '0'",
            'thumbnail' => "varchar(255) NOT NULL DEFAULT ''",
            'dimensions' => "varchar(11) NOT NULL DEFAULT ''",
            'md5hash' => "varchar(32) NOT NULL DEFAULT ''",
            'uploaddate' => "int(10) NOT NULL DEFAULT '0'",
            'updatedate' => "int(10) NOT NULL DEFAULT '0'",
            'status' => "tinyint(10) NOT NULL DEFAULT '1'",//all approved by default
            'primary_key' => 'aid',
            'unique_key' => ['uidfid' => 'uid,fid']
        ],
        'ougc_fileprofilefields_logs' => [
            'lid' => 'int UNSIGNED NOT NULL AUTO_INCREMENT',
            'uid' => 'int UNSIGNED NOT NULL',
            'aid' => 'int UNSIGNED NOT NULL',
            'ipaddress' => "varbinary(16) NOT NULL DEFAULT ''",
            'dateline' => "int(10) NOT NULL DEFAULT '0'",
            'primary_key' => 'lid'
        ],
    ];
}

function _db_columns(): array
{
    return [
        'profilefields' => [
            'ougc_fileprofilefields_types' => "varchar(50) NOT NULL default '-1'",
            'ougc_fileprofilefields_maxsize' => "int(15) NOT NULL DEFAULT '0'", // empty for attachment setting
            'ougc_fileprofilefields_directory' => "varchar(255) NOT NULL DEFAULT ''",
            'ougc_fileprofilefields_customoutput' => "tinyint(1) NOT NULL DEFAULT '0'",
            'ougc_fileprofilefields_imageonly' => "tinyint(1) NOT NULL default '0'",
            'ougc_fileprofilefields_imagemindims' => "varchar(11) NOT NULL DEFAULT ''",
            'ougc_fileprofilefields_imagemaxdims' => "varchar(11) NOT NULL DEFAULT ''",
            'ougc_fileprofilefields_thumbnails' => "tinyint(1) NOT NULL default '0'",
            'ougc_fileprofilefields_thumbnailsdimns' => "varchar(11) NOT NULL DEFAULT ''",
        ],
        'attachtypes' => [
            'ougc_fileprofilefields' => "tinyint(1) NOT NULL default '0'",//empty for all
        ],
    ];
}

function _db_verify_indexes(): bool
{
    global $db;

    foreach (_db_tables() as $table => $fields) {
        if (!$db->table_exists($table)) {
            continue;
        }

        if (isset($fields['unique_key'])) {
            foreach ($fields['unique_key'] as $k => $v) {
                if ($db->index_exists($table, $k)) {
                    continue;
                }

                $db->write_query("ALTER TABLE {$db->table_prefix}{$table} ADD UNIQUE KEY {$k} ({$v})");
            }
        }
    }

    return true;
}

function _db_verify_tables(): bool
{
    global $db;

    $collation = $db->build_create_table_collation();

    foreach (_db_tables() as $table => $fields) {
        if ($db->table_exists($table)) {
            foreach ($fields as $field => $definition) {
                if ($field == 'primary_key' || $field == 'unique_key') {
                    continue;
                }

                if ($db->field_exists($field, $table)) {
                    $db->modify_column($table, "`{$field}`", $definition);
                } else {
                    $db->add_column($table, $field, $definition);
                }
            }
        } else {
            $query = "CREATE TABLE IF NOT EXISTS `{$db->table_prefix}{$table}` (";

            foreach ($fields as $field => $definition) {
                if ($field == 'primary_key') {
                    $query .= "PRIMARY KEY (`{$definition}`)";
                } elseif ($field != 'unique_key') {
                    $query .= "`{$field}` {$definition},";
                }
            }

            $query .= ") ENGINE=MyISAM{$collation};";

            $db->write_query($query);
        }
    }

    _db_verify_indexes();

    return true;
}

function _db_verify_columns(): bool
{
    global $db;

    foreach (_db_columns() as $table => $columns) {
        foreach ($columns as $field => $definition) {
            if ($db->field_exists($field, $table)) {
                $db->modify_column($table, "`{$field}`", $definition);
            } else {
                $db->add_column($table, $field, $definition);
            }
        }
    }

    return true;
}

function _edits_description(): string
{
    global $cache;

    $plugins = $cache->read('plugins');

    $edits_desc = '';

    if (!_is_installed() || empty($plugins['active']['ougc_fileprofilefields'])) {
        return $edits_desc;
    }

    global $PL, $mybb, $page, $lang;

    load_pluginlibrary(false);

    $_edits_apply = $_edits_revert = false;

    // Check edits to core files.
    if (_edits_apply() !== true) {
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
    if (_edits_revert() !== true) {
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

function _edits_apply(bool $apply = false): bool
{
    global $PL;

    load_pluginlibrary(false);

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

function _edits_revert(bool $apply = false): bool
{
    global $PL;

    load_pluginlibrary(false);

    return $PL->edit_core('ougc_plugins_customfields_start', 'member.php', [], $apply) === true &&
        $PL->edit_core('ougc_plugins_customfields_start', 'inc/functions_post.php', [], $apply) === true;
}