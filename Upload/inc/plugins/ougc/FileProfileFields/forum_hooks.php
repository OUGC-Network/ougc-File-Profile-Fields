<?php

/***************************************************************************
 *
 *    ougc File Profile Fields plugin (/inc/plugins/ougc/FileProfileFields/forum_hooks.php)
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

use function ougc\FileProfileFields\Core\buildFileFields;
use function ougc\FileProfileFields\Core\control_object;
use function ougc\FileProfileFields\Core\getProfileFieldsCache;
use function ougc\FileProfileFields\Core\getSetting;
use function ougc\FileProfileFields\Core\urlHandlerBuild;
use function ougc\FileProfileFields\Core\delete_file;
use function ougc\FileProfileFields\Core\get_userfields;
use function ougc\FileProfileFields\Core\load_language;
use function ougc\FileProfileFields\Core\queryFilesMultiple;
use function ougc\FileProfileFields\Core\remove_files;
use function ougc\FileProfileFields\Core\renderUserFile;
use function ougc\FileProfileFields\Core\reset_file;
use function ougc\FileProfileFields\Core\urlHandlerSet;
use function ougc\FileProfileFields\Core\store_file;
use function ougc\FileProfileFields\Core\upload_file;
use function ougc\FileProfileFields\Core\getTemplate;

use const TIME_NOW;

function global_start09(): bool
{
    global $templatelist;

    if (!isset($templatelist)) {
        $templatelist = '';
    } else {
        $templatelist .= ',';
    }

    if (in_array(
        THIS_SCRIPT,
        ['showthread.php', 'private.php', 'newthread.php', 'newreply.php', 'editpost.php', 'member.php', 'usercp.php']
    )) {
        $mainPrefix = 'ougcfileprofilefields_';

        $templatePrefixes = ['profile', 'postBit', 'memberList', 'userControlPanel', 'moderatorControlPanel'];

        foreach (getProfileFieldsCache() as $profileFieldData) {
            if (my_strpos($profileFieldData['type'], 'file') !== false) {
                $profileFieldID = (int)$profileFieldData['fid'];

                foreach ($templatePrefixes as $templatePrefix) {
                    $templatelist .= ", {$mainPrefix}{$templatePrefix}, {$mainPrefix}{$templatePrefix}Status, {$mainPrefix}{$templatePrefix}StatusModerator, {$mainPrefix}{$templatePrefix}Thumbnail";

                    $templatelist .= ", {$mainPrefix}{$templatePrefix}{$profileFieldID}, {$mainPrefix}{$templatePrefix}Status{$profileFieldID}, {$mainPrefix}{$templatePrefix}StatusModerator{$profileFieldID}, {$mainPrefix}{$templatePrefix}Thumbnail{$profileFieldID}, {$mainPrefix}{$templatePrefix}Field{$profileFieldID}, {$mainPrefix}{$templatePrefix}StatusField{$profileFieldID}, {$mainPrefix}{$templatePrefix}StatusModeratorField{$profileFieldID}, {$mainPrefix}{$templatePrefix}ThumbnailField{$profileFieldID}";
                }
            }
        }
    }

    if (THIS_SCRIPT == 'modcp.php') {
        $templatelist .= 'ougcfileprofilefields_modcp_nav, attachment_icon, ougcfileprofilefields_modcp, ougcfileprofilefields_modcp_file, ougcfileprofilefields_modcp_status, ougcfileprofilefields_modcp_status_mod, ougcfileprofilefields_modcp_remove, ougcfileprofilefields_modcp_update, ougcfileprofilefields_modcp_filter_option, ougcfileprofilefields_modcp_multipage, ougcfileprofilefields_modcp_files_file, ougcfileprofilefields_modcp_files, ougcfileprofilefields_modcp_logs_log, ougcfileprofilefields_modcp_logs, ougcfileprofilefields_modcp_page';
    }

    return true;
}

function datahandler_user_validate(UserDataHandler &$dh): UserDataHandler
{
    if ($dh->method === 'insert') {
        //return $dh;
    }

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
    $pfcache = getProfileFieldsCache();

    if ($pfcache) {
        $remove_aids = array_filter(
            array_map('intval', $mybb->get_input('ougcfileprofilefields_remove', MyBB::INPUT_ARRAY))
        );

        $user_fields = &$dh->data['user_fields'];

        $original_values = get_userfields($userID);

        // Then loop through the profile fields.
        foreach ($pfcache as $profileFieldData) {
            $profileFieldData['fid'] = (int)$profileFieldData['fid'];

            if (isset($dh->data['profile_fields_editable']) || isset($dh->data['registration']) && ($profileFieldData['required'] == 1 || $profileFieldData['registration'] == 1)) {
                $profileFieldData['editableby'] = -1;
            }

            if (!is_member(
                $profileFieldData['editableby'],
                get_user($user['uid'])
            )) {
                continue;
            }

            // Does this field have a minimum post count?
            if (!isset($dh->data['profile_fields_editable']) && !empty($profileFieldData['postnum']) && $profileFieldData['postnum'] > $user['postnum']) {
                continue;
            }

            $profileFieldData['type'] = htmlspecialchars_uni($profileFieldData['type']);
            $profileFieldData['name'] = htmlspecialchars_uni($profileFieldData['name']);
            $thing = explode("\n", $profileFieldData['type'], 2);
            $type = trim($thing[0]);

            if ($type != 'file') {
                continue;
            }

            $field = "fid{$profileFieldData['fid']}";

            $ougcFileProfileFieldsObjects[$profileFieldData['fid']] = false;

            $user_fields[$field] = $original_values[$field] ?? '';

            unset($dh->errors['missing_required_profile_field']);

            $process_file = (
                !empty($_FILES['profile_fields']) &&
                !empty($_FILES['profile_fields']['name']) &&
                !empty($_FILES['profile_fields']['name'][$field])
            );

            // If the profile field is required, but not filled in, present error.
            if (
                (!$process_file && empty($user_fields[$field]) || isset($remove_aids[$profileFieldData['fid']]) && !$process_file) &&
                $profileFieldData['required'] &&
                !defined('IN_ADMINCP') &&
                THIS_SCRIPT != 'modcp.php'
            ) {
                if (isset($remove_aids[$profileFieldData['fid']])) {
                    load_language();

                    $dh->set_error(
                        $lang->sprintf(
                            $lang->ougc_fileprofilefields_errors_remove,
                            htmlspecialchars_uni($profileFieldData['name'])
                        )
                    );
                    // this might fail in ACP or because of get_user() not getting fields
                } elseif (empty($_user[$field])) {
                    $dh->set_error('missing_required_profile_field', [$profileFieldData['name']]);
                }
            }

            if ($process_file) {
                $ougcFileProfileFieldsObjects[$profileFieldData['fid']] = upload_file($userID, $profileFieldData);

                if (!empty($ougcFileProfileFieldsObjects[$profileFieldData['fid']]['error'])) {
                    $dh->set_error($ougcFileProfileFieldsObjects[$profileFieldData['fid']]['error']);
                }

                if ($dh->errors) {
                    unset($ougcFileProfileFieldsObjects[$profileFieldData['fid']]);
                }
            }

            if (!$dh->errors && isset($remove_aids[$profileFieldData['fid']])) {
                if ($process_file) {
                    reset_file($userID, $profileFieldData);
                } else {
                    delete_file($userID, $profileFieldData);
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

        $insert_data = [
            'uid' => (int)$user['uid'],
            'fid' => (int)$fid,
            'filename' => (string)$file['filename'],
            'filesize' => (int)$file['filesize'],
            'filemime' => (string)$file['filemime'],
            'name' => (string)$file['name'],
            'thumbnail' => $file['thumbnail'] ?? '',
            'dimensions' => $file['dimensions'] ?? '',
            'md5hash' => (string)$file['md5hash'] ?: '',
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

        $args = $plugins->run_hooks('ougc_fileprofilefields_user_update', $args);

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

    $pfcache = getProfileFieldsCache();

    $profilefields_cache = [];

    if (!empty($pfcache)) {
        foreach ($pfcache as $profileFieldData) {
            $profilefields_cache[$profileFieldData['fid']] = $profileFieldData;
        }
    }

    $delete_uids = implode("','", $dh->delete_uids);

    $query = $db->simple_select('ougc_fileprofilefields_files', '*', "uid IN ('{$delete_uids}')");

    while ($fileData = $db->fetch_array($query)) {
        delete_file((int)$fileData['uid'], $profilefields_cache[$fileData['fid']] ?? []);
    }

    $db->delete_query('ougc_fileprofilefields_files', "uid IN ('{$delete_uids}')");

    $db->delete_query('ougc_fileprofilefields_logs', "uid IN ('{$delete_uids}')");

    return $dh;
}

function usercp_profile_start()
{
    global $templates;

    control_object(
        $templates,
        'function get($title, $eslashes = 1, $htmlcomments = 1)
{
    if ($title === "usercp_profile_customfield") {
        global $mybb, $plugins;
        global $profilefield, $code, $type;

        $hookArguments = [
            "profileFieldData" => &$profilefield,
            "userData" => &$mybb->user,
            "fieldCode" => &$code
        ];
        
        $hookArguments = $plugins->run_hooks("ougc_file_profile_fields_user_control_panel", $hookArguments);
    }

    return parent::get($title, $eslashes, $htmlcomments);
}'
    );
}

function ougc_file_profile_fields_user_control_panel80(array &$hookArguments): array
{
    postbit_start($hookArguments);

    buildFileFields(
        'userControlPanel',
        $hookArguments['userData'],
        $hookArguments['profileFieldData'],
        $hookArguments['fieldCode']
    );

    return $hookArguments;
}

// profiles
function ougc_file_profile_fields_profile(array &$hookArguments): array
{
    asd5();
    postbit_start($hookArguments);

    if (empty($hookArguments['userData'])) {
        return $hookArguments;
    }

    buildFileFields(
        'profile',
        $hookArguments['userData'],
        $hookArguments['profileFieldData'],
        $hookArguments['fieldCode']
    );

    return $hookArguments;
}

function ougc_file_profile_fields_post_start(array &$hookArguments): array
{
    postbit_start($hookArguments);

    buildFileFields(
        'postBit',
        $hookArguments['userData'],
        $hookArguments['profileFieldData'],
        $hookArguments['userData']['profilefield']
    );

    return $hookArguments;
}

function postbit_start(array &$hookArguments): array
{
    static $currentUserID;

    $userID = (int)$hookArguments['userData'];

    if ($currentUserID !== $userID || 1) {
        $currentUserID = $userID;

        global $customFileProfileFields;

        isset($customFileProfileFields) || $customFileProfileFields = [];

        foreach (getProfileFieldsCache() as $profileField) {
            $profileFieldID = (int)$profileField['fid'];

            $fieldIdentifier = "fid{$profileFieldID}";

            $customFileProfileFields[$fieldIdentifier] = '';
        }
    }

    return $hookArguments;
}

function modcp_editprofile_start()
{
    global $templates;

    control_object(
        $templates,
        'function get($title, $eslashes = 1, $htmlcomments = 1)
{
    if ($title === "usercp_profile_customfield") {
        global $mybb, $plugins;
        global $user_fields, $code, $profilefield;

        $hookArguments = [
            "profileFieldData" => &$profilefield,
            "userData" => &$user_fields,
            "fieldCode" => &$code
        ];
        
        $hookArguments = $plugins->run_hooks("ougc_file_profile_fields_moderator_control_panel", $hookArguments);
    }

    return parent::get($title, $eslashes, $htmlcomments);
}'
    );
}

function ougc_file_profile_fields_moderator_control_panel(array &$hookArguments): array
{
    postbit_start($hookArguments);

    buildFileFields(
        'moderatorControlPanel',
        $hookArguments['userData'],
        $hookArguments['profileFieldData'],
        $hookArguments['fieldCode']
    );

    return $hookArguments;
}

function modcp_start()
{
    global $mybb, $modcp_nav, $templates, $lang, $plugins, $usercpnav, $headerinclude, $header, $theme, $footer, $db, $gobutton, $cache, $parser;

    load_language();

    $permission = is_member($mybb->settings['ougc_fileprofilefields_groups_moderators']);

    $errors = '';

    $uid = 0;

    $filter_options = $mybb->get_input('filter', MyBB::INPUT_ARRAY);

    if (isset($filter_options['fids']) && is_array($filter_options['fids'])) {
        $filter_options['fids'] = array_flip(array_map('intval', $filter_options['fids']));
    } else {
        $filter_options['fids'] = [];
    }

    $selectedElementProfileFieldsAll = '';

    if (isset($filter_options['fids'][-1])) {
        $filter_options['fids'] = [];

        $selectedElementProfileFieldsAll = ' selected="selected"';
    }

    if ($uid = $mybb->get_input('uid', MyBB::INPUT_INT)) {
        $user = get_user($mybb->get_input('uid', MyBB::INPUT_INT));

        if (!$user['uid']) {
            error($lang->ougc_fileprofilefields_errors_invalid_user);
        }

        $mybb->input['username'] = $user['username'];
    } elseif (!empty($filter_options['uid']) || !empty($filter_options['username'])) {
        if (!empty($filter_options['uid'])) {
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
        $nav = eval(getTemplate('moderatorControlPanelManagePageNav'));

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

    if (!empty($filter_options['date']) && !empty($filter_options['time'])) {
        $date = (array)explode('-', $filter_options['date']);

        $time = (array)explode(':', $filter_options['time']);

        $dateline = (int)gmmktime((int)$time[0], (int)$time[1], 0, (int)$date[1], (int)$date[2], (int)$date[0]);

        $where['date'] = "(a.uploaddate>'{$dateline}' OR a.updatedate>'{$dateline}')";

        $where2[] = "l.dateline>'{$dateline}'";

        $build_url['filter[date]'] = $filter_options['date'];

        $build_url['filter[time]'] = $filter_options['time'];
    }

    if (isset($filter_options['status'])) {
        (int)$status = $filter_options['status'];

        $where[] = "a.status='{$status}'";

        $build_url['filter[status]'] = $status;
    }

    if (!empty($filter_options['perpage'])) {
        $build_url['filter[perpage]'] = $perpage = (int)$filter_options['perpage'];
    }

    $order_by = 'a.aid';

    $order_by_logs = 'l.lid';

    $order_dir = 'desc';

    if (!empty($filter_options['order_by']) && in_array($filter_options['order_by'], ['username'])) {
        $order_by = "u.{$filter_options['order_by']}";

        $build_url['filter[order_by]'] = $filter_options['order_by'];
    }

    if (!empty($filter_options['order_by']) && in_array(
            $filter_options['order_by'],
            ['filemime', 'filename', 'filesize', 'downloads', 'uploaddate', 'updatedate']
        )) {
        $order_by = "a.{$filter_options['order_by']}";

        if ($filter_options['order_by'] == 'uploaddate') {
            $order_by_logs = 'l.dateline';
        }

        $build_url['filter[order_by]'] = $filter_options['order_by'];
    }

    if (!empty($filter_options['order_dir']) && $filter_options['order_dir'] == 'asc') {
        $order_dir = 'asc';

        $build_url['filter[order_dir]'] = $filter_options['order_by'];
    }

    if (empty($filter_options['order_dir'])) {
        $selected_order_dir['desc'] = ' selected="selected"';
    }

    if (!$perpage) {
        $perpage = 10;
    }

    global $profiecats;

    $profilefields_cache = [];

    $pfcache = getProfileFieldsCache();

    foreach ($pfcache as $profilefield) {
        if (my_strpos($profilefield['type'], 'file') !== false) {
            $profilefields_cache[$profilefield['fid']] = $profilefield;
        }
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

        $options .= eval(getTemplate('moderatorControlPanelManagePageFilterOption'));
    }

    $date = isset($filter_options['date']) ? htmlspecialchars_uni($filter_options['date']) : '';

    $time = isset($filter_options['time']) ? htmlspecialchars_uni($filter_options['time']) : '';

    $selected_status = [
        0 => '',
        1 => '',
        2 => '',
    ];

    if (isset($filter_options['status'])) {
        $selected_status[(int)$filter_options['status']] = ' checked="checked"';
    }

    $selectedElementUserName = $selectedElementFileMime = $selectedElementFileName = $selectedElementFileSize = $selectedElementFileDownloads = $selectedElementUploadDate = $selectedElementUpdateDate = '';

    if (!empty($filter_options['order_by'])) {
        if ($filter_options['order_by'] === 'username') {
            $selectedElementUserName = 'selected="selected"';
        }

        if ($filter_options['order_by'] === 'filemime') {
            $selectedElementFileMime = 'selected="selected"';
        }

        if ($filter_options['order_by'] === 'filename') {
            $selectedElementFileName = 'selected="selected"';
        }

        if ($filter_options['order_by'] === 'filesize') {
            $selectedElementFileSize = 'selected="selected"';
        }

        if ($filter_options['order_by'] === 'downloads') {
            $selectedElementFileDownloads = 'selected="selected"';
        }

        if ($filter_options['order_by'] === 'uploaddate') {
            $selectedElementUploadDate = 'selected="selected"';
        }

        if ($filter_options['order_by'] === 'updatedate') {
            $selectedElementUpdateDate = 'selected="selected"';
        }
    }

    $selectedElementOrderAscending = $selectedElementOrderDescending = '';

    if (!empty($filter_options['order_dir']) && $filter_options['order_dir'] === 'asc') {
        $selectedElementOrderAscending = 'selected="selected"';
    } else {
        $selectedElementOrderDescending = 'selected="selected"';
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

    urlHandlerSet(getSetting('fileName'));

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

        $multipage = eval(getTemplate('moderatorControlPanelManagePagePagination'));

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
            $fileID = (int)$file['aid'];

            $fileUrl = urlHandlerBuild(['aid' => $file['aid']]);

            $thumbnailUrl = urlHandlerBuild(['thumbnail' => $file['aid']]);

            $userName = htmlspecialchars_uni($file['username']);

            $fileName = htmlspecialchars_uni($file['name']);

            $fileThumbnail = htmlspecialchars_uni($file['thumbnail']);

            $md5hash = htmlspecialchars_uni($file['md5hash']);

            $moderatorUserName = htmlspecialchars_uni($file['mod_username']);

            $fileName = htmlspecialchars_uni($file['filename']);

            $fileMime = htmlspecialchars_uni($file['filemime']);

            $fileDownloads = my_number_format($file['downloads']);

            $fileSize = get_friendly_size($file['filesize']);

            $uploadDate = my_date('normal', $file['uploaddate']);

            $updateDate = my_date('normal', $file['updatedate']);

            $profileLink = build_profile_link(
                format_name($userName, $file['usergroup'], $file['displaygroup']),
                $file['uid']
            );

            $ext = get_extension(my_strtolower($file['filename']));

            $icon = get_attachment_icon($ext);

            $field = isset($profilefields_cache[$file['fid']]) ? htmlspecialchars_uni(
                $profilefields_cache[$file['fid']]['name']
            ) : '';

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

            $moderatorProfileLink = '';

            if ($file['muid']) {
                $moderatorUserName = format_name(
                    $moderatorUserName,
                    (int)$file['mod_usergroup'],
                    (int)$file['mod_displaygroup']
                );

                $moderatorProfileLink = build_profile_link($moderatorUserName, (int)$file['muid']);
            }

            $files_list .= eval(getTemplate('moderatorControlPanelManagePageFilesItem'));

            $trow = alt_trow();
        }
    }

    if (!$files_list) {
        $files_list = eval(getTemplate('moderatorControlPanelManagePageFilesEmpty'));
    }

    $files = eval(getTemplate('moderatorControlPanelManagePageFiles'));

    $multipage = '';

    // reset unwanted clauses
    //unset($where['date'], $where['uid']);

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

        $multipage = eval(getTemplate('moderatorControlPanelManagePagePagination'));

        $query = $db->simple_select(
            "ougc_fileprofilefields_logs l LEFT JOIN {$db->table_prefix}ougc_fileprofilefields_files a ON (a.aid=l.aid) LEFT JOIN {$db->table_prefix}users u ON (l.uid=u.uid)",
            'l.lid, l.uid, l.aid, l.ipaddress, l.dateline, a.filename, a.filesize, u.username, u.usergroup, u.displaygroup',
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
            $logID = (int)$log['lid'];

            $fileUrl = urlHandlerBuild(['aid' => $log['aid']]);

            $thumbnailUrl = urlHandlerBuild(['thumbnail' => $log['aid']]);

            $userName = htmlspecialchars_uni($log['username']);

            $fileName = htmlspecialchars_uni($log['filename']);

            $fileSize = get_friendly_size($log['filesize']);

            $dateline = my_date('normal', $log['dateline']);

            $profileLink = build_profile_link(
                format_name($userName, $log['usergroup'], $log['displaygroup']),
                $log['uid']
            );

            $ext = get_extension(my_strtolower($log['filename']));

            $icon = get_attachment_icon($ext);

            //$ipaddress = my_inet_ntop($db->unescape_binary($log['ipaddress']));

            $logs_list .= eval(getTemplate('moderatorControlPanelManagePageLogsRow'));

            $trow = alt_trow();
        }
    }

    if (!$logs_list) {
        $logs_list = eval(getTemplate('moderatorControlPanelManagePageLogsEmpty'));
    }

    $logs = eval(getTemplate('moderatorControlPanelManagePageLogs'));

    $page = eval(getTemplate('moderatorControlPanelManagePage'));

    output_page($page);

    exit;
}

function member_profile_start(): bool
{
    global $db;
    global $memprofile;

    $userID = (int)$memprofile['uid'];

    $dbQuery = $db->simple_select('userfields', '*', "ufid='{$userID}'");

    $userFields = (array)$db->fetch_array($dbQuery);

    $userFields = array_merge($memprofile, $userFields);

    memberlist_user($userFields);

    return true;
}

function memberlist_user(array &$userData): array
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
        $fileID = (int)$userDataFieldValue;

        if (my_strpos($userDataFieldKey, 'fid') === 0 && !empty($fileID)) {
            $fileIDs[] = $fileID;
        }
    }

    if (!empty($fileIDs)) {
        $fileIDs = implode("','", $fileIDs);

        $fileProfileFieldsCachedUsersData[$userID] = queryFilesMultiple(
            ["uid='{$userID}'", "aid IN ('{$fileIDs}')", "status='1'"]
        );
    }

    return $userData;
    // todo, handle non-category profile fields
}

function ougc_profile_fields_categories_build_fields_categories_end(array &$hookArguments): array
{
    global $fileProfileFieldsProcessedUsers;

    if (!isset($fileProfileFieldsProcessedUsers)) {
        $fileProfileFieldsProcessedUsers = [];
    }

    if ($hookArguments['fieldType'] !== 'file') {
        return $hookArguments;
    }

    global $fileProfileFieldsCachedUsersData;

    $userID = (int)$hookArguments['userData']['uid'];

    $fileID = is_numeric($hookArguments['userFieldValue']) ? (int)$hookArguments['userFieldValue'] : 0;

    if (empty($fileProfileFieldsCachedUsersData[$userID]) || empty($fileProfileFieldsCachedUsersData[$userID][$fileID])) {
        return $hookArguments;
    }

    $categoryID = (int)$hookArguments['categoryData']['cid'];

    $userFile = renderUserFile(
        $fileProfileFieldsCachedUsersData[$userID][$fileID],
        $hookArguments['profileFieldData'],
        $hookArguments['templatePrefix'],
        $categoryID
    );

    if (!isset($fileProfileFieldsProcessedUsers[$userID])) {
        $fileProfileFieldsProcessedUsers[$userID] = true;
    }

    if (!empty($userFile)) {
        $hookArguments['userFieldValue'] = $userFile;
    }

    return $hookArguments;
}