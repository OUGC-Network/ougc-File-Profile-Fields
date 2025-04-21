<?php

/***************************************************************************
 *
 *    ougc File Profile Fields plugin (/ougc_fileprofilefields.php)
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

use function ougc\FileProfileFields\Core\getProfileFieldsCache;
use function ougc\FileProfileFields\Core\languageLoad;
use function ougc\FileProfileFields\Core\fileGet;

use const ougc\FileProfileFields\LOAD_FULL_LOGIC;

// Set to true to load the full global.php file
define('ougc\FileProfileFields\LOAD_FULL_LOGIC', false);

const IN_MYBB = true;

const THIS_SCRIPT = 'profilefile.php';

$working_dir = dirname(__FILE__);

if (!$working_dir) {
    $working_dir = '.';
}

global $cache, $mybb, $db, $lang, $plugins;

$currentUserID = (int)$mybb->user['uid'];

if (LOAD_FULL_LOGIC) {
    require_once $working_dir . '/global.php';

    $thumbnail = $mybb->get_input('thumbnail', MyBB::INPUT_INT);

    $errorFunction = 'error';
} else {
    $shutdown_queries = $shutdown_functions = [];

    require_once $working_dir . '/inc/init.php';

    $thumbnail = $mybb->get_input('thumbnail', MyBB::INPUT_INT);

    $groupscache = $cache->read('usergroups');

    if (!is_array($groupscache)) {
        $cache->update_usergroups();

        $groupscache = $cache->read('usergroups');
    }

    $current_page = my_strtolower(basename(THIS_SCRIPT));

    if ($thumbnail && !defined('NO_ONLINE')) {
        define('NO_ONLINE', 1);
    }

    require_once MYBB_ROOT . 'inc/class_session.php';

    $session = new session();

    $session->init();

    $mybb->session = &$session;

    $mybb->user['ismoderator'] = is_moderator();

    $mybb->post_code = generate_post_check();

    if ($mybb->get_input('language') && $lang->language_exists($mybb->get_input('language')) && verify_post_check(
            $mybb->get_input('my_post_key'),
            true
        )) {
        $mybb->settings['bblanguage'] = $mybb->get_input('language');
        // If user is logged in, update their language selection with the new one
        if ($currentUserID) {
            if (isset($mybb->cookies['mybblang'])) {
                my_unsetcookie('mybblang');
            }

            $db->update_query(
                'users',
                ['language' => $db->escape_string($mybb->settings['bblanguage'])],
                "uid = '{$currentUserID}'"
            );
        } // Guest = cookie
        else {
            my_setcookie('mybblang', $mybb->settings['bblanguage']);
        }
        $mybb->user['language'] = $mybb->settings['bblanguage'];
    } elseif (!$currentUserID && !empty($mybb->cookies['mybblang']) && $lang->language_exists(
            $mybb->cookies['mybblang']
        )) {
        $mybb->settings['bblanguage'] = $mybb->cookies['mybblang'];
    } elseif (!isset($mybb->settings['bblanguage'])) {
        $mybb->settings['bblanguage'] = 'english';
    }

    $lang->set_language($mybb->settings['bblanguage']);

    $lang->load('global');

    $lang->load('messages');

    if (!empty($mybb->cookies['lockoutexpiry']) && $mybb->cookies['lockoutexpiry'] < TIME_NOW) {
        my_unsetcookie('lockoutexpiry');
    }

    $errorFunction = 'errorCustom';
}

languageLoad();

if (!function_exists('ougc_fileprofilefields_info')) {
    $errorFunction($lang->ougc_fileprofilefields_errors_deactivated);
}

// Find the AID we're looking for
if ($thumbnail) {
    $fileID = $mybb->get_input('thumbnail', MyBB::INPUT_INT);
} else {
    $fileID = $mybb->get_input('aid', MyBB::INPUT_INT);
}

$whereClauses = ["aid='{$fileID}'"];

if (!is_member($mybb->settings['ougc_fileprofilefields_groups_moderators'])) {
    $whereClauses[] = "status='1'";
}

$fileData = fileGet(
    $whereClauses,
    ['thumbnail', 'fid', 'name', 'filename', 'filemime', 'filesize', 'uid', 'status'],
    ['limit' => 1]
);

if (!$fileData) {
    $errorFunction($lang->ougc_fileprofilefields_errors_invalid_file);
}

$plugins->run_hooks('ougc_fileprofilefields_download_start');

if (!$fileData['thumbnail'] && $thumbnail) {
    $errorFunction($lang->ougc_fileprofilefields_errors_invalid_thumbnail);
}

$attachmentTypes = $cache->read('attachtypes');

$fileName = ltrim(basename(' ' . $fileData['filename']));

$ext = get_extension($fileName);

if (empty($attachmentTypes[$ext])) {
    $errorFunction($lang->ougc_fileprofilefields_errors_invalid_file);
}

$attachmentTypeData = $attachmentTypes[$ext];

if (empty($attachmentTypeData['ougc_fileprofilefields']) || !is_member($attachmentTypeData['groups'])) {
    $errorFunction($lang->ougc_fileprofilefields_errors_invalid_file);
}

$profileFieldData = null;

foreach (getProfileFieldsCache() as $pf) {
    if ($pf['fid'] == $fileData['fid']) {
        $profileFieldData = $pf;

        break;
    }
}

if (
    empty($profileFieldData) ||
    !is_member($profileFieldData['viewableby']) ||
    !is_member(
        $profileFieldData['ougc_fileprofilefields_types'],
        ['usergroup' => (int)$attachmentTypeData['atid'], 'additionalgroups' => '']
    ) ||
    $thumbnail && (
        empty($profileFieldData['ougc_fileprofilefields_imageonly']) ||
        empty($profileFieldData['ougc_fileprofilefields_thumbnails'])
    )
) {
    $errorFunction($lang->ougc_fileprofilefields_errors_invalid_file);
}

$fileStatus = (int)$fileData['status'];

$plugins->run_hooks('ougc_fileprofilefields_download_end');

// TODO: Maybe don't count author downloads?
if (!$thumbnail) {
    $last_download = 0;

    $fileUserID = (int)$fileData['uid'];

    $update_downloads = $fileStatus === 1 && (
            $currentUserID && $mybb->settings['ougc_fileprofilefields_author_downloads'] || ($fileUserID !== $currentUserID)
        );

    if ($update_downloads && !empty($mybb->settings['ougc_fileprofilefields_download_interval'])) {
        $timecut = TIME_NOW - (int)$mybb->settings['ougc_fileprofilefields_download_interval'];

        $query = $db->simple_select(
            'ougc_fileprofilefields_logs',
            'lid',
            "uid='{$currentUserID}' AND aid='{$fileID}' AND dateline>='{$timecut}'",
            ['limit' => 1]
        );

        $update_downloads = !$db->num_rows($query);
    }

    if ($update_downloads) {
        $db->update_query('ougc_fileprofilefields_files', [
            'downloads' => '`downloads`+1'
        ], "aid='{$fileID}'", '', true);
    }

    $db->insert_query('ougc_fileprofilefields_logs', [
        'uid' => $currentUserID,
        'aid' => $fileID,
        //'ipaddress' => $db->escape_binary(my_inet_pton(get_ip())),
        'dateline' => TIME_NOW,
    ]);
    /*$lid = (int)$db->insert_query('ougc_fileprofilefields_logs', [
        'uid' => $currentUserID,
        'aid' => $fileID,
        'dateline' => TIME_NOW,
    ]);

    $db->update_query('ougc_fileprofilefields_logs', ['ipaddress' => $db->escape_binary(my_inet_pton(get_ip()))], "lid='{$lid}'");*/
}

if ($thumbnail) {
    $filepath = MYBB_ROOT . "{$profileFieldData['ougc_fileprofilefields_directory']}/{$fileData['thumbnail']}";

    if (!file_exists($filepath)) {
        $errorFunction($lang->ougc_fileprofilefields_errors_invalid_file);
    }

    $ext = get_extension($fileData['thumbnail']);

    header("Content-disposition: filename=\"{$fileName}\"");

    header("Content-type: {$fileData['filemime']}");

    // TODO: store thumbnail size in DB
    header('Content-length: ' . @filesize($filepath));

    $handle = fopen($filepath, 'rb');

    while (!feof($handle)) {
        echo fread($handle, 8192);
    }

    fclose($handle);
} else {
    $filepath = MYBB_ROOT . "{$profileFieldData['ougc_fileprofilefields_directory']}/{$fileData['name']}";

    if (!file_exists($filepath)) {
        $errorFunction($lang->ougc_fileprofilefields_errors_invalid_thumbnail);
    }

    $ext = get_extension($fileData['thumbnail']);

    $filetype = $fileData['filemime'];

    // TODO: Add a setting to force download

    $disposition = 'attachment';

    if (!$mybb->settings['ougc_fileprofilefields_force_downloads']) {
        switch ($fileData['filemime']) {
            case 'application/pdf':
            case 'image/bmp':
            case 'image/gif':
            case 'image/jpeg':
            case 'image/pjpeg':
            case 'image/png':
            case 'text/plain':
                $disposition = 'inline';
                break;

            default:
                if (!$filetype) {
                    $filetype = 'application/force-download';
                }
                break;
        }
    }

    header("Content-type: {$filetype}");

    if (strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'msie') !== false) {
        header("Content-disposition: attachment; filename=\"{$fileName}\"");
    } else {
        header("Content-disposition: {$disposition}; filename=\"{$fileName}\"");
    }

    if (strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'msie 6.0') !== false) {
        header('Expires: -1');
    }

    header("Content-length: {$fileData['filesize']}");

    header('Content-range: bytes=0-' . ($fileData['filesize'] - 1) . "/{$fileData['filesize']}");

    $handle = fopen($filepath, 'rb');

    while (!feof($handle)) {
        echo fread($handle, 8192);
    }

    fclose($handle);
}

function errorCustom(string $errorMessage)
{
    http_response_code(404);

    echo $errorMessage;

    exit;
}