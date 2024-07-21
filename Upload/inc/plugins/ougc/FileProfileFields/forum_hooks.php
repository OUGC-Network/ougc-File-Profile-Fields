<?php

/***************************************************************************
 *
 *    OUGC File Profile Fields plugin (/inc/plugins/ougc/FileProfileFields/forum_hooks.php)
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

namespace ougc\FileProfileFields\Hooks\Forum;

use MyBB;

use UserDataHandler;

use function ougc\FileProfileFields\Core\urlHandlerBuild;
use function ougc\FileProfileFields\Core\delete_file;
use function ougc\FileProfileFields\Core\get_userfields;
use function ougc\FileProfileFields\Core\load_language;
use function ougc\FileProfileFields\Core\query_file;
use function ougc\FileProfileFields\Core\queryFilesMultiple;
use function ougc\FileProfileFields\Core\remove_files;
use function ougc\FileProfileFields\Core\renderUserFile;
use function ougc\FileProfileFields\Core\reset_file;
use function ougc\FileProfileFields\Core\urlHandlerSet;
use function ougc\FileProfileFields\Core\store_file;
use function ougc\FileProfileFields\Core\upload_file;
use function ougc\FileProfileFields\Core\getTemplate;

use const ougc\FileProfileFields\Core\TEMPLATE_SECTION_MEMBER_LIST;
use const TIME_NOW;

function global_start09(): bool
{
    global $templatelist, $cache;

    if (!isset($templatelist)) {
        $templatelist = '';
    } else {
        $templatelist .= ',';
    }

    if (!defined('THIS_SCRIPT')) {
        return false;
    }

    $load_custom = false;

    if (in_array(THIS_SCRIPT, ['showthread.php', 'private.php', 'newthread.php', 'newreply.php', 'editpost.php'])) {
        $load_custom = 'postbit';

        $templatelist .= 'attachment_icon, ougcfileprofilefields_postbit, ougcfileprofilefields_postbit_file, ougcfileprofilefields_postbit_file_thumbnail, ougcfileprofilefields_postbit_status, ougcfileprofilefields_postbit_status_mod';
    }

    if (THIS_SCRIPT == 'member.php') {
        $load_custom = 'profile';

        $templatelist .= 'attachment_icon, ougcfileprofilefields_profile, ougcfileprofilefields_profile_file, ougcfileprofilefields_profile_file_thumbnail, ougcfileprofilefields_profile_status, ougcfileprofilefields_profile_status_mod';
    }

    if ($load_custom) {
        $pfcache = $cache->read('profilefields');

        if (is_array($pfcache)) {
            foreach ($pfcache as $profilefield) {
                if (my_strpos($profilefield['type'], 'file') !== false) {
                    $fieldID = (int)$profilefield['fid'];

                    $templatelist .= ", ougcfileprofilefields_{$load_custom}_file_{$fieldID}, ougcfileprofilefields_{$load_custom}_file_thumbnail_{$fieldID}, ougcfileprofilefields_{$load_custom}_status_{$fieldID}, ougcfileprofilefields_{$load_custom}_status_mod_{$fieldID}";

                    $templatelist .= ", ougcfileprofilefields_memberListStatusModeratorField{$fieldID}, ougcfileprofilefields_memberListStatusField{$fieldID}, ougcfileprofilefields_memberListFileField{$fieldID}, ougcfileprofilefields_memberListFileThumbnailField{$fieldID}";
                }
            }
        }
    }

    if (THIS_SCRIPT == 'usercp.php') {
        $templatelist .= 'attachment_icon, ougcfileprofilefields_usercp, ougcfileprofilefields_usercp_file, ougcfileprofilefields_usercp_file_thumbnail, ougcfileprofilefields_usercp_status, ougcfileprofilefields_usercp_status_mod, ougcfileprofilefields_usercp_remove, ougcfileprofilefields_usercp_update';
    }

    if (THIS_SCRIPT == 'modcp.php') {
        $templatelist .= 'ougcfileprofilefields_modcp_nav, attachment_icon, ougcfileprofilefields_modcp, ougcfileprofilefields_modcp_file, ougcfileprofilefields_modcp_file_thumbnail, ougcfileprofilefields_modcp_status, ougcfileprofilefields_modcp_status_mod, ougcfileprofilefields_modcp_remove, ougcfileprofilefields_modcp_update, ougcfileprofilefields_modcp_filter_option, ougcinvitesystem_content_multipage, ougcfileprofilefields_modcp_files_file, ougcfileprofilefields_modcp_files, ougcfileprofilefields_modcp_logs_log, ougcfileprofilefields_modcp_logs, ougcfileprofilefields_modcp_page';
    }

    return true;
}

function datahandler_user_validate(UserDataHandler &$dh): UserDataHandler
{
    global $db, $cache, $mybb, $lang;
    global $ougcFileProfileFieldsObjects;

    if (!is_array($ougcFileProfileFieldsObjects)) {
        $ougcFileProfileFieldsObjects = [];
    }

    $user = &$dh->data;

    $userID = (int)$user['uid'];

    $profile_fields = &$dh->data['profile_fields'];

    // Loop through profile fields checking if they exist or not and are filled in.

    // Fetch all profile fields first.
    $pfcache = $cache->read('profilefields');

    if (is_array($pfcache)) {
        $remove_aids = array_filter(
            array_map('intval', $mybb->get_input('ougcfileprofilefields_remove', MyBB::INPUT_ARRAY))
        );

        $user_fields = &$dh->data['user_fields'];

        $original_values = get_userfields($userID);

        // Then loop through the profile fields.
        foreach ($pfcache as $profilefield) {
            $profilefield['fid'] = (int)$profilefield['fid'];

            if (isset($dh->data['profile_fields_editable']) || isset($dh->data['registration']) && ($profilefield['required'] == 1 || $profilefield['registration'] == 1)) {
                $profilefield['editableby'] = -1;
            }

            if (!is_member(
                $profilefield['editableby'],
                get_user($user['uid'])
            )) {
                continue;
            }

            // Does this field have a minimum post count?
            if (!isset($dh->data['profile_fields_editable']) && !empty($profilefield['postnum']) && $profilefield['postnum'] > $user['postnum']) {
                continue;
            }

            $profilefield['type'] = htmlspecialchars_uni($profilefield['type']);
            $profilefield['name'] = htmlspecialchars_uni($profilefield['name']);
            $thing = explode("\n", $profilefield['type'], 2);
            $type = trim($thing[0]);

            if ($type != 'file') {
                continue;
            }

            $field = "fid{$profilefield['fid']}";

            $ougcFileProfileFieldsObjects[$profilefield['fid']] = false;

            $user_fields[$field] = $original_values[$field];

            unset($dh->errors['missing_required_profile_field']);

            $process_file = (
                !empty($_FILES['profile_fields']) &&
                !empty($_FILES['profile_fields']['name']) &&
                !empty($_FILES['profile_fields']['name'][$field])
            );

            // If the profile field is required, but not filled in, present error.
            if (
                (!$process_file && empty($user_fields[$field]) || isset($remove_aids[$profilefield['fid']]) && !$process_file) &&
                $profilefield['required'] &&
                !defined('IN_ADMINCP') &&
                THIS_SCRIPT != 'modcp.php'
            ) {
                if (isset($remove_aids[$profilefield['fid']])) {
                    load_language();

                    $dh->set_error(
                        $lang->sprintf(
                            $lang->ougc_fileprofilefields_errors_remove,
                            htmlspecialchars_uni($profilefield['name'])
                        )
                    );
                    // this might fail in ACP or because of get_user() not getting fields
                } elseif (empty($_user[$field])) {
                    $dh->set_error('missing_required_profile_field', array($profilefield['name']));
                }
            }

            if ($process_file) {
                $ougcFileProfileFieldsObjects[$profilefield['fid']] = upload_file($userID, $profilefield);

                if (!empty($ougcFileProfileFieldsObjects[$profilefield['fid']]['error'])) {
                    $dh->set_error($ougcFileProfileFieldsObjects[$profilefield['fid']]['error']);
                }

                if ($dh->errors) {
                    unset($ougcFileProfileFieldsObjects[$profilefield['fid']]);
                }
            }

            if (!$dh->errors && isset($remove_aids[$profilefield['fid']])) {
                if ($process_file) {
                    reset_file($userID, $profilefield);
                } else {
                    delete_file($userID, $profilefield);
                }
            }
        }
    }

    return $dh;
}

function datahandler_user_update(UserDataHandler &$dh): UserDataHandler
{
    global $ougcFileProfileFieldsObjects;

    if (empty($ougcFileProfileFieldsObjects)) {
        return $dh;
    }

    global $db, $plugins, $mybb;

    $user = &$dh->data;

    $user_fields = &$dh->data['user_fields'];

    foreach ($ougcFileProfileFieldsObjects as $fid => $file) {
        if (empty($file)) {
            continue;
        }

        $md5 = md5_file("{$file['uploadpath']}/{$file['name']}");

        $insert_data = [
            'uid' => (int)$user['uid'],
            'fid' => (int)$fid,
            'filename' => (string)$file['filename'],
            'filesize' => (int)$file['filesize'],
            'filemime' => (string)$file['filemime'],
            'name' => (string)$file['name'],
            'thumbnail' => $file['thumbnail'] ?? '',
            'dimensions' => $file['dimensions'] ?? '',
            'md5hash' => (string)$md5 ?: '',
            'updatedate' => TIME_NOW
        ];

        if (is_member($mybb->settings['ougc_fileprofilefields_groups_moderate'])) {
            $insert_data['status'] = 0;
        }

        if (
            isset($insert_data['status']) &&
            (
                is_member($mybb->settings['ougc_fileprofilefields_groups_moderators']) ||
                defined('IN_ADMINCP')
            )
        ) {
            $insert_data['muid'] = $mybb->user['uid'];

            $insert_data['status'] = 1;
        }

        $args = [
            'insert_data' => &$insert_data,
            'dh' => &$dh
        ];

        $plugins->run_hooks('ougc_fileprofilefields_user_update', $args);

        if ($aid = store_file($insert_data)) {
            $user_fields["fid{$fid}"] = $db->escape_string($aid);

            remove_files(
                $user['uid'],
                $fid,
                $file['uploadpath'],
                [
                    $file['name'],
                    str_replace('.attach', "_thumb.{$file['filetype']}", $file['name'])
                ]
            );
        }
    }

    return $dh;
}

function datahandler_user_delete_start(UserDataHandler &$dh): UserDataHandler
{
    global $db, $cache;

    if (!$dh->delete_uids) {
        return $dh;
    }

    $pfcache = $cache->read('profilefields');

    $profilefields_cache = [];

    if (!empty($pfcache)) {
        foreach ($pfcache as $profilefield) {
            $profilefields_cache[$profilefield['fid']] = $profilefield;
        }
    }

    $delete_uids = implode("','", $dh->delete_uids);

    $query = $db->simple_select('ougc_fileprofilefields_files', '*', "uid IN ('1', '{$delete_uids}')");

    while ($file = $db->fetch_array($query)) {
        delete_file($file['uid'], $profilefields_cache[$file['fid']]);
    }

    $db->delete_query('ougc_fileprofilefields_files', "uid IN ('{$delete_uids}')");

    $db->delete_query('ougc_fileprofilefields_logs', "uid IN ('{$delete_uids}')");

    return $dh;
}

function ougc_plugins_customfields_usercp_end80(string $section = 'usercp', array &$args = []): bool
{
    if (!$section) {
        $section = 'usercp';
    }

    global $mybb, $user_fields, $customfield, $maxlength, $code, $templates, $lang, $ougc_fileprofilefields, $profilefields;
    global $field, $ougcProfileFieldsCategoriesCurrentID, $ougcProfileFieldsCategoriesProfileContents;

    isset($ougc_fileprofilefields) || $ougc_fileprofilefields = [];

    $preview = $remove = $update = $status = '';

    $user = &$mybb->user;

    if ($section == 'postbit') {
        $aid = (int)$args['post'][$field];

        if (isset($profilefields)) {
            $preview = &$profilefields;
        } elseif (isset($ougcProfileFieldsCategoriesProfileContents)) {
        } else {
            $preview = &$args['post']['profilefield'];
        }

        $profilefield = &$args['field'];

        $type = trim(explode("\n", $profilefield['type'], 2)[0]);

        $user = &$args['post'];
    } else {
        global $type, $field, $profilefield;

        if ($section == 'profile') {
            global $userfields, $customfieldval;

            $preview = &$customfieldval;

            $user = &$userfields;

            $profilefield = &$customfield;
        } elseif ($section == 'modcp' || defined('IN_ADMINCP')) {
            $user = &$user_fields;
        }
    }

    $aid = isset($user[$field]) ? (int)$user[$field] : 0;

    $fid = (int)$profilefield['fid'];

    $field = "fid{$profilefield['fid']}";

    if ($type != 'file') {
        return false;
    }

    $ougc_fileprofilefields[$field] = '';

    if ($profilefield['ougc_fileprofilefields_customoutput']) {
        $preview = &$ougc_fileprofilefields[$field];
    }

    $style = 'inherit';

    $ismod = is_member($mybb->settings['ougc_fileprofilefields_groups_moderators']);

    if ($aid && $file = query_file($aid)) {
        $file['status'] = (int)$file['status'];

        if (
            $file['status'] === 1 ||
            $ismod ||
            !in_array($section, ['profile', 'postbit'])
        ) {
            $style = 'none';

            load_language();

            $ext = get_extension(my_strtolower($file['filename']));

            $icon = get_attachment_icon($ext);

            $filename = htmlspecialchars_uni($file['filename']);

            $filesize = get_friendly_size($file['filesize']);

            $downloads = my_number_format($file['downloads']);

            $md5_hash = htmlspecialchars_uni($file['md5hash']);

            $upload_date = my_date('normal', $file['uploaddate']);

            $update_date = my_date('normal', $file['updatedate']);

            // TODO: add option to reset downloads and upload date

            $thumbnail = htmlspecialchars_uni($file['thumbnail']);

            if ($file['status'] !== 1) {
                $description = $lang->ougc_fileprofilefields_status_notification_onqueue;

                if ($file['status'] === -1) {
                    $description = $lang->ougc_fileprofilefields_status_notification_unapproved;
                }

                if ($ismod) {
                    if (!empty($ougcProfileFieldsCategoriesCurrentID) && isset($templates->cache["ougcfileprofilefields_{$section}_status_mod_category{$ougcProfileFieldsCategoriesCurrentID}"])) {
                        $status = eval(
                        getTemplate(
                            "{$section}_status_mod_category{$ougcProfileFieldsCategoriesCurrentID}"
                        )
                        );
                    } elseif (isset($templates->cache["ougcfileprofilefields_{$section}_status_mod_{$fid}"])) {
                        $status = eval(getTemplate("{$section}_status_mod_{$fid}"));
                    } else {
                        $status = eval(getTemplate("{$section}_status_mod"));
                    }
                } elseif (!empty($ougcProfileFieldsCategoriesCurrentID) && isset($templates->cache["ougcfileprofilefields_{$section}_status_category{$ougcProfileFieldsCategoriesCurrentID}"])) {
                    $status = eval(getTemplate("{$section}_status_category{$ougcProfileFieldsCategoriesCurrentID}"));
                } elseif (isset($templates->cache["ougcfileprofilefields_{$section}_status_{$fid}"])) {
                    $status = eval(getTemplate("{$section}_status_{$fid}"));
                } else {
                    $status = eval(getTemplate("{$section}_status"));
                }
            }

            if (!$profilefield['ougc_fileprofilefields_customoutput'] && in_array($section, ['postbit'])) {
                $name = htmlspecialchars_uni($profilefield['name']);

                $preview .= eval(getTemplate("{$section}"));
            }

            if (
                $file['thumbnail'] &&
                $profilefield['ougc_fileprofilefields_imageonly'] &&
                $profilefield['ougc_fileprofilefields_thumbnails'] &&
                file_exists(MYBB_ROOT . "{$profilefield['ougc_fileprofilefields_directory']}/{$file['thumbnail']}")
            ) {
                $thumbnail_width = explode('|', $file['dimensions'])[0];

                $thumbnail_height = explode('|', $file['dimensions'])[1];

                $maximum_width = explode('|', $profilefield['ougc_fileprofilefields_thumbnailsdimns'])[0];

                $maximum_height = explode('|', $profilefield['ougc_fileprofilefields_thumbnailsdimns'])[1];

                if (!empty($ougcProfileFieldsCategoriesCurrentID) && isset($templates->cache["ougcfileprofilefields_{$section}_file_thumbnail_category{$ougcProfileFieldsCategoriesCurrentID}"])) {
                    $preview .= eval(
                    getTemplate(
                        "{$section}_file_thumbnail_category{$ougcProfileFieldsCategoriesCurrentID}"
                    )
                    );
                } elseif (!defined(
                        'IN_ADMINCP'
                    ) && isset($templates->cache["ougcfileprofilefields_{$section}_file_thumbnail_{$fid}"])) {
                    $preview .= eval(getTemplate("{$section}_file_thumbnail_{$fid}"));
                } else {
                    $preview .= eval(getTemplate("{$section}_file_thumbnail"));
                }
            } elseif (!empty($ougcProfileFieldsCategoriesCurrentID) && isset($templates->cache["ougcfileprofilefields_{$section}_file_category{$ougcProfileFieldsCategoriesCurrentID}"])) {
                $preview .= eval(getTemplate("{$section}_file_category{$ougcProfileFieldsCategoriesCurrentID}"));
            } elseif (!defined(
                    'IN_ADMINCP'
                ) && isset($templates->cache["ougcfileprofilefields_{$section}_file_{$fid}"])) {
                $preview .= eval(getTemplate("{$section}_file_{$fid}"));
            } else {
                $preview .= eval(getTemplate("{$section}_file"));
            }

            if (!in_array($section, ['profile', 'postbit'])) {
                $update_aids = array_filter(
                    array_map('intval', $mybb->get_input('ougcfileprofilefields_update', MyBB::INPUT_ARRAY))
                );

                $checked = '';

                if (isset($update_aids[$profilefield['fid']])) {
                    $checked = ' checked="checked"';
                }

                $update = eval(getTemplate("{$section}_update"));

                $remove_aids = array_filter(
                    array_map('intval', $mybb->get_input('ougcfileprofilefields_remove', MyBB::INPUT_ARRAY))
                );

                $checked = '';

                if (isset($remove_aids[$profilefield['fid']])) {
                    $checked = ' checked="checked"';
                }

                $remove = eval(getTemplate("{$section}_remove"));
            }

            if (in_array($section, ['profile'])) {
                $user[$field] = null;
            }

            if (in_array($section, ['postbit'])) {
                $user[$field] = null;
            }
        } elseif (in_array($section, ['profile', 'postbit'])) {
            $user[$field] = null;
        }
    } elseif (in_array($section, ['profile', 'postbit'])) {
        $user[$field] = null;
    }

    if (!in_array($section, ['profile', 'postbit'])) {
        load_language();

        $attachcache = $mybb->cache->read('attachtypes');

        $exts = $valid_mimes = [];

        foreach ($attachcache as $ext => $attachtype) {
            if (
                $attachtype['ougc_fileprofilefields'] &&
                is_member(
                    $profilefield['ougc_fileprofilefields_types'],
                    ['usergroup' => (int)$attachtype['atid'], 'additionalgroups' => '']
                ) &&
                ($attachtype['groups'] == -1 || is_member($attachtype['groups'], get_user($user['ufid'])))
            ) {
                $valid_mimes[] = $attachtype['mimetype'];

                $exts[$ext] = $lang->sprintf(
                    $lang->ougc_fileprofilefields_info_types_item,
                    my_strtoupper($ext),
                    get_friendly_size(
                        (int)$profilefield['ougc_fileprofilefields_maxsize'] ?: (int)$attachtype['maxsize']
                    )
                );
            }
        }

        if ($exts) {
            $allowed_types = implode($lang->comma, array_keys($exts));

            $accepted_formats = '.' . implode(', .', array_keys($exts)) . ', ' . implode(', ', $valid_mimes);

            $code = eval(getTemplate("{$section}"));
        } else {
            $code = $lang->ougc_fileprofilefields_info_unconfigured;
        }
    }

    return true;
}

function ougc_plugins_customfields_profile_start(): bool
{
    ougc_plugins_customfields_usercp_end80('profile');

    return true;
}

function ougc_plugins_customfields_postbit_start(array &$post): array
{
    ougc_plugins_customfields_usercp_end80('postbit', $post);

    return $post;
}

function ougc_plugins_customfields_modcp_end(): bool
{
    ougc_plugins_customfields_usercp_end80('modcp');

    return true;
}

function modcp_start()
{
    global $mybb, $modcp_nav, $templates, $lang, $plugins, $usercpnav, $headerinclude, $header, $theme, $footer, $db, $gobutton, $cache, $parser;

    load_language();

    $permission = is_member($mybb->settings['ougc_fileprofilefields_groups_moderators']);

    $uid = 0;

    $filter_options = $mybb->get_input('filter', MyBB::INPUT_ARRAY);

    if (isset($filter_options['fids']) && is_array($filter_options['fids'])) {
        $filter_options['fids'] = array_flip(array_map('intval', $filter_options['fids']));
    } else {
        $filter_options['fids'] = [];
    }

    if (isset($filter_options['fids'][-1])) {
        $filter_options['fids'] = [];

        $selected_fields['all'] = ' selected="selected"';
    }

    if ($uid = $mybb->get_input('uid', MyBB::INPUT_INT)) {
        $user = get_user($mybb->get_input('uid', MyBB::INPUT_INT));

        if (!$user['uid']) {
            error($lang->ougc_fileprofilefields_errors_invalid_user);
        }

        $mybb->input['username'] = $user['username'];
    } elseif ($filter_options['uid'] || $filter_options['username']) {
        if ((int)$filter_options['uid']) {
            $user = get_user((int)$filter_options['uid']);
        } else {
            $user = get_user_by_username($filter_options['username']);
        }

        if (!$user['uid']) {
            error($lang->ougc_fileprofilefields_errors_invalid_user);
        }

        $uid = (int)$user['uid'];
    }

    $user = get_user($uid);

    $build_url = [];

    urlHandlerSet(urlHandlerBuild(['action' => 'ougc_fileprofilefields']));

    $url = urlHandlerBuild();

    $where = $where2 = [];

    if ($uid) {
        $where['uid'] = "a.uid='{$uid}'";
    }

    if ($permission) {
        $nav = eval(getTemplate('modcp_nav'));

        $modcp_nav = str_replace('<!--OUGC_FILEPROFILEFIELDS-->', $nav, $modcp_nav);

        $navigation = &$modcp_nav;

        $build_url['filter[uid]'] = $uid;

        $page_title = $lang->ougc_fileprofilefields_modcp_nav;
    }

    if ($mybb->get_input('action') != 'ougc_fileprofilefields') {
        return;
    }

    if (!$permission) {
        error_no_permission();
    }

    $perpage = (int)$mybb->settings['ougc_fileprofilefields_perpage'];

    if ($filter_options['fids']) {
        $keys = [];

        foreach ($filter_options['fids'] as $key => $v) {
            $keys[] = $key = (int)$key;

            $build_url["filter[fids][{$key}]"] = $key;
        }

        $keys = implode("','", $keys);

        $where['fids'] = "a.fid IN ('{$keys}')";
    }

    if ($filter_options['date'] && $filter_options['time']) {
        $date = (array)explode('-', $filter_options['date']);

        $time = (array)explode(':', $filter_options['time']);

        $dateline = (int)gmmktime((int)$time[0], (int)$time[1], 0, (int)$date[1], (int)$date[2], (int)$date[0]);

        $where['date'] = "(a.uploaddate>'{$dateline}' OR a.updatedate>'{$dateline}')";

        $where2[] = "l.dateline>'{$dateline}'";

        $build_url['filter[date]'] = $filter_options['date'];

        $build_url['filter[time]'] = $filter_options['time'];
    }

    if ($filter_options['status']) {
        (int)$status = $filter_options['status'];

        $where[] = "a.status='{$status}'";

        $build_url['filter[status]'] = $status;
    }

    if ($filter_options['perpage']) {
        $build_url['filter[perpage]'] = $perpage = (int)$filter_options['perpage'];
    }

    $order_by = 'a.aid';

    $order_by_logs = 'l.lid';

    $order_dir = 'desc';

    if (in_array($filter_options['order_by'], ['username'])) {
        $order_by = "u.{$filter_options['order_by']}";

        $build_url['filter[order_by]'] = $filter_options['order_by'];
    }

    if (in_array(
        $filter_options['order_by'],
        ['filemime', 'filename', 'filesize', 'downloads', 'uploaddate', 'updatedate']
    )) {
        $order_by = "a.{$filter_options['order_by']}";

        if ($filter_options['order_by'] == 'uploaddate') {
            $order_by_logs = 'l.dateline';
        }

        $build_url['filter[order_by]'] = $filter_options['order_by'];
    }

    if ($filter_options['order_dir'] == 'asc') {
        $order_dir = 'asc';

        $build_url['filter[order_dir]'] = $filter_options['order_by'];
    }

    if (!$filter_options['order_dir']) {
        $selected_order_dir['desc'] = ' selected="selected"';
    }

    if (!$perpage) {
        $perpage = 10;
    }

    $profilefields_cache = [];

    $pfcache = $cache->read('profilefields');

    foreach ($pfcache as $profilefield) {
        if (my_strpos($profilefield['type'], 'file') === false) {
            continue;
        }

        $profilefields_cache[$profilefield['fid']] = $profilefield;
    }

    add_breadcrumb($lang->nav_modcp, 'modcp.php');

    add_breadcrumb($page_title);

    if ($mybb->request_method == 'post') {
        $ids = implode("','", array_map('intval', $mybb->get_input('check', MyBB::INPUT_ARRAY)));

        if ($mybb->get_input('do') == 'files' && $mybb->get_input('approve')) {
            $db->update_query(
                'ougc_fileprofilefields_files',
                ['status' => 1, 'muid' => (int)$mybb->user['uid']],
                "aid IN ('{$ids}')"
            );

            redirect(urlHandlerBuild($build_url), $lang->ougc_fileprofilefields_redirect_approved);
        }

        if ($mybb->get_input('do') == 'files' && $mybb->get_input('unapprove')) {
            $db->update_query(
                'ougc_fileprofilefields_files',
                ['status' => -1, 'muid' => (int)$mybb->user['uid']],
                "aid IN ('{$ids}')"
            );

            redirect(urlHandlerBuild($build_url), $lang->ougc_fileprofilefields_redirect_unapproved);
        }

        if ($mybb->get_input('do') == 'logs' && $mybb->get_input('delete')) {
            $db->delete_query('ougc_fileprofilefields_logs', "lid IN ('{$ids}')");

            redirect(urlHandlerBuild($build_url), $lang->ougc_fileprofilefields_redirect_deleted);
        }

        error_no_permission();
    }

    $filter_username = '';

    if (!empty($user['username'])) {
        $filter_username = htmlspecialchars_uni($user['username']);
    }

    $options = '';

    foreach ($profilefields_cache as $fid => $profilefield) {
        $selected = '';

        if (isset($filter_options['fids'][$fid])) {
            $selected = ' selected="selected"';
        }

        $name = htmlspecialchars_uni($profilefield['name']);

        $options .= eval(getTemplate('modcp_filter_option'));
    }

    $date = htmlspecialchars_uni($filter_options['date']);

    $time = htmlspecialchars_uni($filter_options['time']);

    $selected_status = [
        0 => '',
        1 => '',
        2 => '',
    ];

    $selected_status[(int)$filter_options['status']] = ' checked="checked"';

    foreach (['username', 'filemime', 'filename', 'filesize', 'downloads', 'uploaddate', 'updatedate'] as $key) {
        if ($filter_options['order_by'] == $key) {
            $selected_order_by[$key] = ' selected="selected"';
        }
    }

    foreach (['asc', 'desc'] as $key) {
        if ($filter_options['order_dir'] == $key) {
            $selected_order_dir[$key] = ' selected="selected"';
        }
    }

    $total_files = 0;

    $content = '';

    $query = $db->simple_select(
        'ougc_fileprofilefields_files a',
        'COUNT(a.aid) AS total_files',
        implode(' AND ', $where)
    );

    $total_files = (int)$db->fetch_field($query, 'total_files');

    $files_list = $logs_list = $multipage = '';

    $form_url = urlHandlerBuild($build_url);

    if ($total_files) {
        $page = $mybb->get_input('page', MyBB::INPUT_INT);

        $pages = $total_files / $perpage;

        $pages = ceil($pages);

        if ($page > $pages || $page <= 0) {
            $page = 1;
        }

        if ($page) {
            $start = ($page - 1) * $perpage;
        } else {
            $start = 0;

            $page = 1;
        }

        $multipage = (string)multipage($total_files, $perpage, $page, urlHandlerBuild($build_url));

        $multipage = eval($templates->render('ougcinvitesystem_content_multipage'));

        $query = $db->simple_select(
            "ougc_fileprofilefields_files a LEFT JOIN {$db->table_prefix}users u ON (a.uid=u.uid) LEFT JOIN {$db->table_prefix}users m ON (a.muid=m.uid)",
            'a.*, u.username, u.usergroup, u.displaygroup, m.username AS mod_username, m.usergroup AS mod_usergroup, m.displaygroup AS mod_displaygroup',
            implode(' AND ', $where),
            [
                'limit' => $perpage,
                'limit_start' => $start,
                'order_by' => $order_by,
                'order_dir' => $order_dir,
            ]
        );

        $trow = alt_trow(true);

        while ($file = $db->fetch_array($query)) {
            foreach (
                [
                    'aid',
                    'uid',
                    'fid',
                    'filesize',
                    'downloads',
                    'uploaddate',
                    'updatedate',
                    'status',
                    'mod_usergroup',
                    'mod_displaygroup'
                ] as $key
            ) {
                $file[$key] = (int)$file[$key];
            }

            foreach (['username', 'filename', 'name', 'thumbnail', 'md5hash', 'filemime', 'mod_username'] as $key) {
                ${$key} = htmlspecialchars_uni((string)$file[$key]);
            }

            foreach (['downloads'] as $key) {
                ${$key} = my_number_format($file[$key]);
            }

            foreach (['filesize'] as $key) {
                ${$key} = get_friendly_size($file[$key]);
            }

            foreach (['uploaddate', 'updatedate'] as $key) {
                ${$key} = my_date('normal', $file[$key]);
            }

            $username = format_name($username, $file['usergroup'], $file['displaygroup']);

            $profilelink = build_profile_link($username, $file['uid']);

            $ext = get_extension(my_strtolower($file['filename']));

            $icon = get_attachment_icon($ext);

            $field = htmlspecialchars_uni($profilefields_cache[$file['fid']]['name']);

            switch ($file['status']) {
                case -1:
                    $status_class = 'unapproved';
                    $status = $lang->ougc_fileprofilefields_modcp_files_status_unapproved;
                    break;
                case 0:
                    $status_class = 'onqueue';
                    $status = $lang->ougc_fileprofilefields_modcp_files_status_onqueue;
                    break;
                case 1:
                    $status_class = 'approved';
                    $status = $lang->ougc_fileprofilefields_modcp_files_status_approved;
                    break;
            }

            $mod_profilelink = '';

            if ($file['muid']) {
                $mod_username = format_name($mod_username, (int)$file['mod_usergroup'], (int)$file['mod_displaygroup']);

                $mod_profilelink = build_profile_link($mod_username, (int)$file['muid']);
            }

            $files_list .= eval(getTemplate('modcp_files_file'));

            $trow = alt_trow();
        }
    }

    if (!$files_list) {
        $files_list = eval(getTemplate('modcp_files_empty'));
    }

    $files = eval(getTemplate('modcp_files'));

    $multipage = '';

    // reset unwanted clauses
    unset($where['date'], $where['uid']);

    if ($uid) {
        $where['uid'] = "l.uid='{$uid}'";
    }

    $query = $db->simple_select(
        "ougc_fileprofilefields_logs l LEFT JOIN {$db->table_prefix}ougc_fileprofilefields_files a ON (a.aid=l.aid)",
        'COUNT(l.lid) AS total_logs',
        implode(' AND ', array_merge($where, $where2))
    );

    $total_logs = (int)$db->fetch_field($query, 'total_logs');

    $logs_list = $multipage = '';

    if ($total_logs) {
        $page = $mybb->get_input('page', MyBB::INPUT_INT);

        $pages = $total_logs / $perpage;

        $pages = ceil($pages);

        if ($page > $pages || $page <= 0) {
            $page = 1;
        }

        if ($page) {
            $start = ($page - 1) * $perpage;
        } else {
            $start = 0;

            $page = 1;
        }

        $multipage = (string)multipage($total_logs, $perpage, $page, urlHandlerBuild($build_url));

        $multipage = eval($templates->render('ougcinvitesystem_content_multipage'));

        $query = $db->simple_select(
            "ougc_fileprofilefields_logs l LEFT JOIN {$db->table_prefix}ougc_fileprofilefields_files a ON (a.aid=l.aid) LEFT JOIN {$db->table_prefix}users u ON (l.uid=u.uid)",
            'l.*, a.filename, a.filesize, u.username, u.usergroup, u.displaygroup',
            implode(' AND ', array_merge($where, $where2)),
            [
                'limit' => $perpage,
                'limit_start' => $start,
                'order_by' => $order_by_logs,
                'order_dir' => $order_dir,
            ]
        );

        $trow = alt_trow(true);

        while ($log = $db->fetch_array($query)) {
            foreach (['aid', 'uid', 'fid', 'filesize', 'dateline'] as $key) {
                $log[$key] = (int)$log[$key];
            }

            foreach (['username', 'filename'] as $key) {
                ${$key} = htmlspecialchars_uni($log[$key]);
            }

            foreach (['filesize'] as $key) {
                ${$key} = get_friendly_size($log[$key]);
            }

            foreach (['dateline'] as $key) {
                ${$key} = my_date('normal', $log[$key]);
            }

            $username = format_name($username, $log['usergroup'], $log['displaygroup']);

            $profilelink = build_profile_link($username, $file['uid']);

            $ext = get_extension(my_strtolower($log['filename']));

            $icon = get_attachment_icon($ext);

            //$ipaddress = my_inet_ntop($db->unescape_binary($log['ipaddress']));

            $logs_list .= eval(getTemplate('modcp_logs_log'));

            $trow = alt_trow();
        }
    }

    if (!$logs_list) {
        $logs_list = eval(getTemplate('modcp_logs_empty'));
    }

    $logs = eval(getTemplate('modcp_logs'));

    $page = eval(getTemplate('modcp_page'));

    output_page($page);

    exit;
}

function memberlist_user(array $userData): array
{
    global $fileProfileFieldsCachedUsersData;

    if (!isset($fileProfileFieldsCachedUsersData)) {
        $fileProfileFieldsCachedUsersData = [];
    }

    $userID = (int)$userData['uid'];

    if (isset($fileProfileFieldsCachedUsersData[$userID])) {
        return $userData;
    }

    foreach ($userData as $userDataFieldKey => $userDataFieldValue) {
        $attachmentID = (int)$userDataFieldValue;

        if (mb_strpos($userDataFieldKey, 'fid') === 0 && !empty($attachmentID)) {
            $attachmentIDs[] = $attachmentID;
        }
    }

    if (!empty($attachmentIDs)) {
        $attachmentIDs = implode("','", $attachmentIDs);

        $fileProfileFieldsCachedUsersData[$userID] = queryFilesMultiple(
            ["uid='{$userID}'", "aid IN ('{$attachmentIDs}')"]
        );
    }

    return $userData;
    // todo, handle non-category profile fields
}

function ougc_profile_fields_categories_build_fields_categories_end(array &$pluginArguments): array
{
    if ($pluginArguments['fieldType'] !== 'file') {
        return $pluginArguments;
    }

    global $fileProfileFieldsCachedUsersData;

    $userID = (int)$pluginArguments['userData']['uid'];

    $attachmentID = is_numeric($pluginArguments['userFieldValue']) ? (int)$pluginArguments['userFieldValue'] : 0;

    if (empty($fileProfileFieldsCachedUsersData[$userID]) || empty($fileProfileFieldsCachedUsersData[$userID][$attachmentID])) {
        return $pluginArguments;
    }

    $categoryID = (int)$pluginArguments['categoryData']['cid'];

    $userFile = renderUserFile(
        $fileProfileFieldsCachedUsersData[$userID][$attachmentID],
        $pluginArguments['profileFieldData'],
        TEMPLATE_SECTION_MEMBER_LIST,
        $categoryID
    );

    if (!empty($userFile)) {
        $pluginArguments['userFieldValue'] = $userFile;
    }

    return $pluginArguments;
}