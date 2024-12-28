<?php

/***************************************************************************
 *
 *    OUGC File Profile Fields plugin (/inc/plugins/ougc/FileProfileFields/core.php)
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

namespace ougc\FileProfileFields\Core;

use AbstractPdoDbDriver;
use MyBB;
use OUGC_ProfiecatsCache;

use ReflectionProperty;

use function delete_uploaded_file;

use function generate_thumbnail;

use function ougc\FileProfileFields\Admin\_info;

use const IMAGETYPE_BMP;
use const IMAGETYPE_GIF;
use const IMAGETYPE_JPEG;
use const IMAGETYPE_PNG;
use const IMAGETYPE_WEBP;
use const MYBB_ROOT;

use const TIME_NOW;

const FILE_STATUS_UNAPPROVED = -1;

const FILE_STATUS_ON_QUEUE = 0;

const FILE_STATUS_APPROVED = 1;

const TEMPLATE_SECTION_MEMBER_LIST = 'memberList';

const URL = 'modcp.php';

function load_language(): bool
{
    global $lang;

    if (!isset($lang->setting_group_ougc_fileprofilefields)) {
        $lang->load('ougc_fileprofilefields');

        if (defined('IN_ADMINCP')) {
            $lang->load('ougc_fileprofilefields', true);
        }
    }

    return true;
}

function load_pluginlibrary(bool $check = true): bool
{
    global $PL, $lang;

    load_language();

    if ($file_exists = file_exists(PLUGINLIBRARY)) {
        global $PL;

        $PL or require_once PLUGINLIBRARY;
    }

    if (!$check) {
        return false;
    }

    $_info = _info();

    if (!$file_exists || $PL->version < $_info['pl']['version']) {
        flash_message(
            $lang->sprintf($lang->ougc_fileprofilefields_pluginlibrary, $_info['pl']['url'], $_info['pl']['version']),
            'error'
        );

        admin_redirect('index.php?module=config-plugins');
    }

    return true;
}

function addHooks(string $namespace): bool
{
    global $plugins;

    $namespaceLowercase = strtolower($namespace);
    $definedUserFunctions = get_defined_functions()['user'];

    foreach ($definedUserFunctions as $callable) {
        $namespaceWithPrefixLength = strlen($namespaceLowercase) + 1;

        if (substr($callable, 0, $namespaceWithPrefixLength) == $namespaceLowercase . '\\') {
            $hookName = substr_replace($callable, '', 0, $namespaceWithPrefixLength);

            $priority = substr($callable, -2);

            if (is_numeric(substr($hookName, -2))) {
                $hookName = substr($hookName, 0, -2);
            } else {
                $priority = 10;
            }

            $plugins->add_hook($hookName, $callable, $priority);
        }
    }

    return true;
}

function getSetting(string $settingKey = '')
{
    global $mybb;

    return SETTINGS[$settingKey] ?? (
        $mybb->settings['ougcCustomFieldsSearch_' . $settingKey] ?? false
    );
}

function getTemplateName(string $templateName = ''): string
{
    $templatePrefix = '';

    if ($templateName) {
        $templatePrefix = '_';
    }

    return "ougcfileprofilefields{$templatePrefix}{$templateName}";
}

function getTemplate(string $templateName = '', bool $enableHTMLComments = true): string
{
    global $templates;

    if (DEBUG) {
        $filePath = ROOT . "/templates/{$templateName}.html";

        $templateContents = '';

        if (file_exists($filePath)) {
            $templateContents = file_get_contents($filePath);
        }

        $templates->cache[getTemplateName($templateName)] = $templateContents;
    } elseif (my_strpos($templateName, '/') !== false) {
        $templateName = substr($templateName, strpos($templateName, '/') + 1);
    }

    return $templates->render(getTemplateName($templateName), true, $enableHTMLComments);
}

function urlHandler(string $newUrl = ''): string
{
    static $setUrl = URL;

    if (($newUrl = trim($newUrl))) {
        $setUrl = $newUrl;
    }

    return $setUrl;
}

function urlHandlerSet(string $newUrl): string
{
    return urlHandler($newUrl);
}

function urlHandlerGet(): string
{
    return urlHandler();
}

function urlHandlerBuild(array $urlAppend = [], bool $fetchImportUrl = false, bool $encode = true): string
{
    global $PL;

    if (!is_object($PL)) {
        $PL || require_once PLUGINLIBRARY;
    }

    return $PL->url_append(urlHandlerGet(), $urlAppend, '&amp;', $encode);
}

function store_file(array $insert_data): int
{
    global $db, $plugins;

    $clean_data = [];

    $args = [
        'insert_data' => &$insert_data,
        'clean_data' => &$clean_data
    ];

    foreach (['uid', 'fid', 'muid', 'filesize', 'downloads', 'uploaddate', 'updatedate', 'status'] as $key) {
        if (isset($insert_data[$key])) {
            $clean_data[$key] = (int)$insert_data[$key];
        }
    }

    foreach (['filename', 'filemime', 'name', 'thumbnail', 'dimensions', 'md5hash'] as $key) {
        if (isset($insert_data[$key])) {
            $clean_data[$key] = $db->escape_string($insert_data[$key]);
        }
    }

    $args = $plugins->run_hooks('ougc_fileprofilefields_store_file_end', $args);

    $aid = 0;

    if ($clean_data['uid'] && $clean_data['fid']) {
        $query = $db->simple_select(
            'ougc_fileprofilefields_files',
            'aid',
            "uid='{$clean_data['uid']}' AND fid='{$clean_data['fid']}'"
        );

        $aid = (int)$db->fetch_field($query, 'aid');
    }

    if ($aid) {
        $db->update_query('ougc_fileprofilefields_files', $clean_data, "aid='{$aid}'");
    } else {
        $clean_data['uploaddate'] = TIME_NOW;

        $aid = (int)$db->insert_query('ougc_fileprofilefields_files', $clean_data);
    }

    return $aid;
}

function query_file(int $aid): array
{
    global $db;

    $query = $db->simple_select('ougc_fileprofilefields_files', '*', "aid='{$aid}'");

    if ($db->num_rows($query)) {
        return (array)$db->fetch_array($query);
    }

    return [];
}

function queryFilesMultiple(
    array $whereClauses,
    string $queryFields = 'aid, uid, muid, fid, filename, filesize, filemime, name, downloads, thumbnail, dimensions, md5hash, uploaddate, updatedate, status',
    array $queryOptions = []
): array {
    global $db;

    $dbQuery = $db->simple_select(
        'ougc_fileprofilefields_files',
        $queryFields,
        implode(' AND ', $whereClauses),
        $queryOptions
    );

    $filesObjects = [];

    if ($db->num_rows($dbQuery)) {
        while ($fileData = $db->fetch_array($dbQuery)) {
            if (isset($fileData['aid'])) {
                $filesObjects[(int)$fileData['aid']] = $fileData;
                //unset($filesObjects[(int)$fileData['aid']]['aid']);
            } else {
                $filesObjects[] = $fileData;
            }
        }
    }

    return $filesObjects;
}

function upload_file(int $uid, array $profilefield): array
{
    global $db, $mybb, $lang, $plugins;

    $ret = $ret_data = $valid_exts = $valid_mimes = $allowed_mime_types = [];

    $args = [
        'uid' => &$uid,
        'profilefield' => &$profilefield,
        'ret' => &$ret,
        'ret_data' => &$ret_data
    ];

    $args = $plugins->run_hooks('ougc_fileprofilefields_upload_file_start', $args);

    $uid = (int)$uid;

    $profilefield['fid'] = (int)$profilefield['fid'];

    load_language();

    $fieldIdentifier = "fid{$profilefield['fid']}";

    if (!is_uploaded_file($_FILES['profile_fields']['tmp_name'][$fieldIdentifier])) {
        $ret['error'] = $lang->ougc_fileprofilefields_errors_upload_failed_upload_size;

        return $ret;
    }

    $ret_data['filename'] = $_FILES['profile_fields']['name'][$fieldIdentifier];

    $ret_data['filemime'] = $_FILES['profile_fields']['type'][$fieldIdentifier];

    $ret_data['filesize'] = $_FILES['profile_fields']['size'][$fieldIdentifier];

    $ret_data['filetype'] = $file_ext = get_extension(
        my_strtolower($_FILES['profile_fields']['name'][$fieldIdentifier])
    );

    $attachtypes = $mybb->cache->read('attachtypes');

    $userData = get_user($uid);

    foreach ($attachtypes as $ext => $attachtype) {
        if (
            $attachtype['ougc_fileprofilefields'] &&
            is_member(
                (string)$profilefield['ougc_fileprofilefields_types'],
                ['usergroup' => (int)$attachtype['atid'], 'additionalgroups' => '']
            ) &&
            ($attachtype['groups'] == -1 || is_member($attachtype['groups'], $userData))
        ) {
            $valid_exts[] = $ext;

            $valid_mimes[] = $attachtype['mimetype'];
            // TODO: Max file size should be separated from attach setting
            $allowed_mime_types[$attachtype['mimetype']] = (int)$profilefield['ougc_fileprofilefields_maxsize'] ?: (int)$attachtype['maxsize'];
        }
    }

    $valid_extensions = implode('|', $valid_exts);

    if (!preg_match("#^({$valid_extensions})$#i", $file_ext)) {
        $ret['error'] = $lang->sprintf(
            $lang->ougc_fileprofilefields_errors_invalid_type,
            htmlspecialchars_uni($profilefield['name']),
            implode(', ', $valid_exts)
        );

        return $ret;
    }

    $uploadpath = MYBB_ROOT . $profilefield['ougc_fileprofilefields_directory'];

    $maxfilenamelength = 255;

    if (my_strlen($_FILES['profile_fields']['name'][$fieldIdentifier]) > $maxfilenamelength) {
        $ret['error'] = $lang->sprintf(
            $lang->ougc_fileprofilefields_errors_file_name,
            htmlspecialchars_uni($_FILES['profile_fields']['name'][$fieldIdentifier]),
            $maxfilenamelength
        );

        return $ret;
    }

    $time_now = TIME_NOW;

    $random_md5 = md5(random_str());

    $fileName = "profilefieldfile_{$profilefield['fid']}_{$uid}_{$time_now}_{$random_md5}.attach";

    require_once MYBB_ROOT . 'inc/functions_upload.php';

    $file = \upload_file([
        'name' => $_FILES['profile_fields']['name'][$fieldIdentifier],
        'type' => $_FILES['profile_fields']['type'][$fieldIdentifier],
        'tmp_name' => $_FILES['profile_fields']['tmp_name'][$fieldIdentifier],
        'size' => $_FILES['profile_fields']['size'][$fieldIdentifier],
    ], $uploadpath, $fileName);

    if (isset($file['error']) || !file_exists("{$uploadpath}/{$fileName}")) {
        delete_uploaded_file("{$uploadpath}/{$fileName}");

        $ret['error'] = $lang->ougc_fileprofilefields_errors_upload_failed_file_exists;

        return $ret;
    }

    $uploaded_mime = @mime_content_type("{$uploadpath}/{$fileName}");

    if ($uploaded_mime && !in_array($uploaded_mime, $valid_mimes)) {
        $ret['error'] = $lang->sprintf(
            $lang->ougc_fileprofilefields_errors_invalid_type,
            htmlspecialchars_uni($profilefield['name']),
            implode(', ', $valid_exts)
        );

        return $ret;
    }

    $ret_data['filemime'] = $uploaded_mime;

    $ret_data['uploadpath'] = $uploadpath;

    $ret_data['name'] = $fileName;

    if ($profilefield['ougc_fileprofilefields_imageonly']) {
        $img_dimensions = @getimagesize("{$uploadpath}/{$fileName}");

        if (!is_array($img_dimensions)) {
            delete_uploaded_file("{$uploadpath}/{$fileName}");

            $ret['error'] = $lang->ougc_fileprofilefields_errors_upload_failed_image_info;

            return $ret;
        }

        if (!empty($profilefield['ougc_fileprofilefields_imagemindims'])) {
            $minimum_dims = explode('|', $profilefield['ougc_fileprofilefields_imagemindims']);

            if (($minimum_dims[0] && $img_dimensions[0] < $minimum_dims[0]) || ($minimum_dims[1] && $img_dimensions[1] < $minimum_dims[1])) {
                delete_uploaded_file("{$uploadpath}/{$fileName}");

                $ret['error'] = $lang->sprintf(
                    $lang->ougc_fileprofilefields_errors_invalid_mindims,
                    htmlspecialchars_uni($profilefield['name']),
                    $minimum_dims[0],
                    $minimum_dims[1]
                );

                return $ret;
            }
        }

        if (!empty($profilefield['ougc_fileprofilefields_imagemaxdims'])) {
            $maximum_dims = explode('|', $profilefield['ougc_fileprofilefields_imagemaxdims']);

            if (!isset($maximum_dims[0])) {
                $maximum_dims[0] = 0;
            }

            if (!isset($maximum_dims[1])) {
                $maximum_dims[1] = 0;
            }

            if ((!empty($maximum_dims[0]) && $img_dimensions[0] > $maximum_dims[0]) || (!empty($maximum_dims[1]) && $img_dimensions[1] > $maximum_dims[1])) {
                if ($mybb->settings['ougc_fileprofilefields_image_resize']) {
                    require_once MYBB_ROOT . 'inc/functions_image.php';

                    $thumbnail = generate_thumbnail(
                        "{$uploadpath}/{$fileName}",
                        $uploadpath,
                        $fileName,
                        $maximum_dims[1],
                        $maximum_dims[0]
                    );

                    if (empty($thumbnail['filename'])) {
                        delete_uploaded_file("{$uploadpath}/{$fileName}");

                        $ret['error'] = $lang->sprintf(
                            $lang->ougc_fileprofilefields_errors_invalid_maxdims,
                            htmlspecialchars_uni($profilefield['name']),
                            $maximum_dims[0],
                            $maximum_dims[1]
                        );

                        return $ret;
                    }

                    $ret_data['filesize'] = $file['size'] = filesize("{$uploadpath}/{$thumbnail['filename']}");

                    $img_dimensions = @getimagesize("{$uploadpath}/{$thumbnail['filename']}");

                    if (!is_array($img_dimensions)) {
                        delete_uploaded_file("{$uploadpath}/{$fileName}");

                        $ret['error'] = $lang->ougc_fileprofilefields_errors_upload_failed_image_info;

                        return $ret;
                    }
                } else {
                    delete_uploaded_file("{$uploadpath}/{$fileName}");

                    $ret['error'] = $lang->sprintf(
                        $lang->ougc_fileprofilefields_errors_invalid_maxdims,
                        htmlspecialchars_uni($profilefield['name']),
                        $maximum_dims[0],
                        $maximum_dims[1]
                    );

                    return $ret;
                }
            }
        }

        switch ($file['type']) {
            case 'image/gif':
                $img_type = IMAGETYPE_GIF;
                break;
            case 'image/jpeg':
            case 'image/x-jpg':
            case 'image/x-jpeg':
            case 'image/pjpeg':
            case 'image/jpg':
                $img_type = IMAGETYPE_JPEG;
                break;
            case 'image/png':
            case 'image/x-png':
                $img_type = IMAGETYPE_PNG;
                break;
            case 'image/bmp':
            case 'image/x-bmp':
            case 'image/x-windows-bmp':
                $img_type = IMAGETYPE_BMP;
                break;
            case 'image/webp':
                $img_type = IMAGETYPE_WEBP;
                break;
            default:
                $img_type = 0;
        }

        if (function_exists('finfo_open')) {
            $file_info = finfo_open(FILEINFO_MIME);

            $ret_data['filemime'] = explode(';', finfo_file($file_info, "{$uploadpath}/{$fileName}"))[0];

            finfo_close($file_info);
        } elseif (function_exists('mime_content_type')) {
            $ret_data['filemime'] = mime_content_type(MYBB_ROOT . "{$uploadpath}/{$fileName}");
        }

        if (empty($allowed_mime_types[$ret_data['filemime']]) || $img_dimensions[2] != $img_type || !$img_type) {
            delete_uploaded_file("{$uploadpath}/{$fileName}");

            $ret['error'] = $lang->ougc_fileprofilefields_errors_upload_failed_image_info;

            return $ret;
        }

        require_once MYBB_ROOT . 'inc/functions_image.php';

        if (!empty($profilefield['ougc_fileprofilefields_thumbnails']) && in_array(
                $img_type,
                [IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG]
            )) {
            $dims = explode('|', $profilefield['ougc_fileprofilefields_thumbnailsdimns']);

            $thumbnail = generate_thumbnail(
                "{$uploadpath}/{$fileName}",
                $uploadpath,
                str_replace('.attach', "_thumb.{$file_ext}", $fileName),
                $dims[1],
                $dims[0]
            );

            if (empty($thumbnail['filename'])) {
                delete_uploaded_file("{$uploadpath}/{$fileName}");

                $ret['error'] = $lang->ougc_fileprofilefields_errors_upload_failed_thumbnail_creation;

                return $ret;
            }

            $ret_data['thumbnail'] = $thumbnail['filename'];

            $thumbnail_dimensions = @getimagesize("{$uploadpath}/{$thumbnail['filename']}");

            if (!is_array($thumbnail_dimensions)) {
                delete_uploaded_file("{$uploadpath}/{$fileName}");

                $ret['error'] = $lang->ougc_fileprofilefields_errors_upload_failed_image_info;

                return $ret;
            }

            $ret_data['dimensions'] = "{$thumbnail_dimensions[0]}|{$thumbnail_dimensions[1]}";
        }
    }

    if ($file['size'] > ($allowed_mime_types[$file['type']] * 1024)) {
        delete_uploaded_file("{$uploadpath}/{$fileName}");

        $ret['error'] = $lang->sprintf(
            $lang->ougc_fileprofilefields_errors_upload_size,
            htmlspecialchars_uni($profilefield['name']),
            get_friendly_size($allowed_mime_types[$file['type']]),
            my_strtoupper($file_ext)
        );

        return $ret;
    }

    $ret_data['md5hash'] = md5_file("{$uploadpath}/{$fileName}");

    $args = $plugins->run_hooks('ougc_fileprofilefields_upload_file_end', $args);

    return $ret_data;
}

function remove_files(int $uid, int $fid, string $uploadpath, array $exclude = []): bool
{
    global $plugins;

    $profileFieldData = [];

    foreach (getProfileFieldsCache() as $profileField) {
        if ((int)$profileField['fid'] === $fid) {
            $profileFieldData = $profileField;

            break;
        }
    }

    $fileName = "profilefieldfile_{$fid}_{$uid}";

    $hook_arguments = [
        'uid' => &$uid,
        'fid' => &$fid,
        'uploadpath' => &$uploadpath,
        'exclude' => &$exclude,
        'profileFieldData' => &$profileFieldData,
        'filename' => &$fileName,
    ];

    $hook_arguments = $plugins->run_hooks('ougc_fileprofilefields_remove_files_start', $hook_arguments);

    $dir = opendir($uploadpath);

    if ($dir) {
        is_array($exclude) || $exclude = [$exclude];

        while ($file = @readdir($dir)) {
            if ($file == '.' or $file == '..') {
                continue;
            }

            if (preg_match("#{$fileName}#", $file) && is_file("{$uploadpath}/{$file}") && !in_array($file, $exclude)) {
                require_once MYBB_ROOT . 'inc/functions_upload.php';

                delete_uploaded_file("{$uploadpath}/{$file}");
            }
        }

        @closedir($dir);
    }

    $hook_arguments = $plugins->run_hooks('ougc_fileprofilefields_remove_files_end', $hook_arguments);

    return true;
}

function delete_file(int $uid, array $profilefield): bool
{
    global $db, $mybb;

    $uid = (int)$uid;

    $fid = (int)$profilefield['fid'];

    $query = $db->simple_select('ougc_fileprofilefields_files', '*', "uid='{$uid}' AND fid='{$fid}'");

    while ($file = $db->fetch_array($query)) {
        remove_files($uid, $fid, MYBB_ROOT . $profilefield['ougc_fileprofilefields_directory']);
    }

    $db->delete_query('ougc_fileprofilefields_files', "uid='{$uid}' AND fid='{$fid}'");

    $db->update_query('userfields', ["fid{$fid}" => ''], "ufid='{$uid}'");

    return true;
}

function reset_file(int $uid, array $profilefield): bool
{
    global $db, $mybb;

    $uid = (int)$uid;

    $fid = (int)$profilefield['fid'];

    $db->update_query('ougc_fileprofilefields_files', [
        'downloads' => 0,
        'uploaddate' => TIME_NOW,
        'updatedate' => TIME_NOW
    ], "uid='{$uid}' AND fid='{$fid}'");

    return true;
}

function get_userfields(int $uid): array
{
    static $user_cache = [];

    $uid = (int)$uid;

    if (!isset($user_cache[$uid])) {
        global $db;

        $query = $db->simple_select('userfields', '*', "ufid='{$uid}'");

        if ($db->num_rows($query)) {
            $user_cache[$uid] = (array)$db->fetch_array($query);
        } else {
            $user_cache[$uid] = [];
        }
    }

    return $user_cache[$uid];
}

function getProfileFieldsCache(): array
{
    global $mybb;
    global $profiecats;

    if (
        class_exists('OUGC_ProfiecatsCache') && $profiecats instanceof OUGC_ProfiecatsCache &&
        !empty($profiecats->cache['original'])
    ) {
        return $profiecats->cache['original'];
    }

    return (array)$mybb->cache->read('profilefields');
}

function customTemplateIsSet(string $templateName): bool
{
    global $templates;

    if (DEBUG) {
        $filePath = ROOT . "/templates/{$templateName}.html";

        if (file_exists($filePath)) {
            $templateContents = file_get_contents($filePath);

            $templates->cache["ougcfileprofilefields_{$templateName}"] = $templateContents;
        }
    }

    return isset($templates->cache["ougcfileprofilefields_{$templateName}"]);
}

function renderUserFile(
    array $fileData,
    array $profileFieldData,
    string $templatePrefix = TEMPLATE_SECTION_MEMBER_LIST,
    int $categoryID = 0
): string {
    global $mybb;

    $fileStatus = (int)$fileData['status'];

    $isModerator = (bool)is_member($mybb->settings['ougc_fileprofilefields_groups_moderators']);

    if ($fileStatus !== FILE_STATUS_APPROVED && !$isModerator) {
        return '';
    }

    global $lang;

    $profileFieldID = (int)$fileData['fid'];

    $userID = (int)$fileData['uid'];

    load_language();

    $fileExtension = get_extension(my_strtolower($fileData['filename']));

    $extensionIcon = get_attachment_icon($fileExtension);

    $fileName = htmlspecialchars_uni($fileData['filename']);

    $fileSize = get_friendly_size($fileData['filesize']);

    $fileDownloads = my_number_format($fileData['downloads']);

    $fileHash = htmlspecialchars_uni($fileData['md5hash']);

    $fileUploadDate = my_date('normal', $fileData['uploaddate']);

    $fileUpdateDate = my_date('normal', $fileData['updatedate']);

    $fileThumbnail = htmlspecialchars_uni($fileData['thumbnail']);

    $statusCode = '';

    if ($fileStatus !== FILE_STATUS_APPROVED) {
        $statusDescription = $lang->ougc_fileprofilefields_status_notification_unapproved;

        if ($fileStatus === FILE_STATUS_ON_QUEUE) {
            $queueUrl = urlHandlerBuild([
                'action' => 'ougc_fileprofilefields',
                'filter[uid]' => $userID,
                "filter[fids][{$profileFieldID}]" => $profileFieldID,
            ]);

            $statusDescription = $lang->ougc_fileprofilefields_status_notification_onqueue;
        }

        if ($isModerator && customTemplateIsSet("{$templatePrefix}StatusModeratorCategory{$categoryID}")) {
            $statusCode = eval(getTemplate("{$templatePrefix}StatusModeratorCategory{$categoryID}"));
        } elseif ($isModerator && customTemplateIsSet("{$templatePrefix}StatusModeratorField{$profileFieldID}")) {
            $statusCode = eval(getTemplate("{$templatePrefix}StatusModeratorField{$profileFieldID}"));
        } elseif ($isModerator) {
            $statusCode = eval(getTemplate("{$templatePrefix}StatusModerator"));
        } elseif (customTemplateIsSet("{$templatePrefix}StatusCategory{$categoryID}")) {
            $statusCode = eval(
            getTemplate(
                "{$templatePrefix}StatusCategory{$categoryID}"
            )
            );
        } elseif (customTemplateIsSet("{$templatePrefix}StatusField{$profileFieldID}")) {
            $statusCode = eval(getTemplate("{$templatePrefix}StatusField{$profileFieldID}"));
        } else {
            $statusCode = eval(getTemplate("{$templatePrefix}Status"));
        }
    }

    $attachmentID = (int)$fileData['aid'];

    urlHandlerSet(getSetting('fileName'));

    $attachmentUrl = urlHandlerBuild(['aid' => $attachmentID]);

    $thumbnailUrl = urlHandlerBuild(['thumbnail' => $attachmentID]);

    if (
        $fileData['thumbnail'] &&
        $profileFieldData['ougc_fileprofilefields_imageonly'] &&
        $profileFieldData['ougc_fileprofilefields_thumbnails']
    ) {
        $thumbnailDimensions = explode('|', $fileData['dimensions']);

        $thumbnailWidth = $thumbnailDimensions[0] ?? 0;

        $thumbnailHeight = $thumbnailDimensions[1] ?? 0;

        if (customTemplateIsSet("{$templatePrefix}ThumbnailCategory{$categoryID}")) {
            return eval(getTemplate("{$templatePrefix}ThumbnailCategory{$categoryID}"));
        } elseif (customTemplateIsSet("{$templatePrefix}ThumbnailField{$profileFieldID}")) {
            return eval(getTemplate("{$templatePrefix}ThumbnailField{$profileFieldID}"));
        } else {
            return eval(getTemplate("{$templatePrefix}Thumbnail"));
        }
    } elseif (customTemplateIsSet("{$templatePrefix}Category{$categoryID}")) {
        return eval(getTemplate("{$templatePrefix}Category{$categoryID}", false));
    } elseif (customTemplateIsSet("{$templatePrefix}Field{$profileFieldID}")) {
        return eval(getTemplate("{$templatePrefix}Field{$profileFieldID}", false));
    } else {
        return eval(getTemplate($templatePrefix, false));
    }
}

function buildFileFields(
    string $templatePrefix,
    array &$userData,
    array &$profileFieldData,
    string &$filePreview,
    bool $resetFieldCode = false
): bool {
    $fieldType = explode("\n", $profileFieldData['type'], 2)[0] ?? '';

    if ($fieldType !== 'file') {
        return false;
    }

    global $mybb, $user_fields, $customfield, $maxlength, $code, $templates, $lang, $ougc_fileprofilefields, $profilefields;
    global $ougcProfileFieldsCategoriesCurrentID, $ougcProfileFieldsCategoriesProfileContents;

    $categoryID = $ougcProfileFieldsCategoriesCurrentID ?? 0;

    isset($ougc_fileprofilefields) || $ougc_fileprofilefields = [];

    $removeRow = $updateRow = $statusCode = '';

    if ($templatePrefix == 'postbit') {
        //$attachmentID = (int)$hookArguments['post'][$fieldIdentifier];

        if (isset($profilefields)) {
            //$filePreview = &$profilefields;
        } elseif (isset($ougcProfileFieldsCategoriesProfileContents)) {
        } else {
            //$filePreview = &$hookArguments['post']['profilefield'];
        }
        //$type = trim(explode("\n", $profileFieldData['type'], 2)[0]);
    } else {
        //global $type, $field;

        if ($templatePrefix == 'profile') {
        } elseif (defined('IN_ADMINCP')) {
            //$userData = &$user_fields;
        }
    }

    $profileFieldID = (int)$profileFieldData['fid'];

    $fieldIdentifier = "fid{$profileFieldID}";

    $attachmentID = isset($userData[$fieldIdentifier]) ? (int)$userData[$fieldIdentifier] : 0;

    $userID = (int)$userData['uid'];

    $ougc_fileprofilefields[$fieldIdentifier] = '';

    if (!empty($profileFieldData['ougc_fileprofilefields_customoutput'])) {
        $filePreview = &$ougc_fileprofilefields[$fieldIdentifier];
    } elseif (!empty($filePreview)) {
        $filePreview = '';
    }

    $fieldLength = (int)$profileFieldData['length'];

    $styleCode = 'display: inherit;';

    $isModerator = is_member($mybb->settings['ougc_fileprofilefields_groups_moderators']);

    if (in_array($templatePrefix, ['profile', 'postbit'])) {
        $userData[$fieldIdentifier] = '';
    }

    if ($fileData = query_file($attachmentID)) {
        urlHandlerSet(getSetting('fileName'));

        $attachmentUrl = urlHandlerBuild(['aid' => $attachmentID]);

        $thumbnailUrl = urlHandlerBuild(['thumbnail' => $attachmentID]);

        $fileData['status'] = (int)$fileData['status'];

        if (
            $fileData['status'] === 1 ||
            $isModerator ||
            !in_array($templatePrefix, ['profile', 'postbit'])
        ) {
            $styleCode = 'display: none;';

            load_language();

            $fileExtension = get_extension(my_strtolower($fileData['filename']));

            $extensionIcon = get_attachment_icon($fileExtension);

            $fileName = htmlspecialchars_uni($fileData['filename']);

            $fileSize = get_friendly_size($fileData['filesize']);

            $fileDownloads = my_number_format((int)$fileData['downloads']);

            $fileHash = htmlspecialchars_uni($fileData['md5hash']);

            $fileUploadDate = my_date('normal', $fileData['uploaddate']);

            $fileUpdateDate = my_date('normal', $fileData['updatedate']);

            // TODO: add option to reset downloads and upload date

            $thumbnail = htmlspecialchars_uni($fileData['thumbnail']);

            if ($fileData['status'] !== 1) {
                $statusDescription = $lang->ougc_fileprofilefields_status_notification_onqueue;

                if ($fileData['status'] === -1) {
                    $statusDescription = $lang->ougc_fileprofilefields_status_notification_unapproved;
                }

                if ($isModerator && customTemplateIsSet("{$templatePrefix}StatusModeratorCategory{$categoryID}")) {
                    $statusCode = eval(
                    getTemplate(
                        "{$templatePrefix}StatusModeratorCategory{$categoryID}"
                    )
                    );
                } elseif ($isModerator && customTemplateIsSet(
                        "{$templatePrefix}StatusModeratorField{$profileFieldID}"
                    )) {
                    $statusCode = eval(getTemplate("{$templatePrefix}StatusModeratorField{$profileFieldID}"));
                } elseif ($isModerator) {
                    $statusCode = eval(getTemplate("{$templatePrefix}StatusModerator"));
                } elseif (customTemplateIsSet("{$templatePrefix}StatusCategory{$categoryID}")) {
                    $statusCode = eval(
                    getTemplate(
                        "{$templatePrefix}StatusCategory{$categoryID}"
                    )
                    );
                } elseif (customTemplateIsSet("{$templatePrefix}StatusField{$profileFieldID}")) {
                    $statusCode = eval(getTemplate("{$templatePrefix}StatusField{$profileFieldID}"));
                } else {
                    $statusCode = eval(getTemplate("{$templatePrefix}Status"));
                }
            }

            if (empty($profileFieldData['ougc_fileprofilefields_customoutput']) && in_array($templatePrefix, ['postbit']
                )) {
                $name = htmlspecialchars_uni($profileFieldData['name']);

                $filePreview .= eval(getTemplate("{$templatePrefix}"));
            }

            if (
                $fileData['thumbnail'] &&
                $profileFieldData['ougc_fileprofilefields_imageonly'] &&
                $profileFieldData['ougc_fileprofilefields_thumbnails'] &&
                file_exists(
                    MYBB_ROOT . "{$profileFieldData['ougc_fileprofilefields_directory']}/{$fileData['thumbnail']}"
                )
            ) {
                $thumbnailDimensions = explode('|', $fileData['dimensions']);

                $thumbnailWidth = $thumbnailDimensions[0] ?? 0;

                $thumbnailHeight = $thumbnailDimensions[1] ?? 0;

                $maximumDimensions = explode('|', $profileFieldData['ougc_fileprofilefields_thumbnailsdimns']);

                $maximumWidth = $maximumDimensions[0] ?? 0;

                $maximumHeight = $maximumDimensions[1] ?? 0;

                if (customTemplateIsSet("{$templatePrefix}ThumbnailCategory{$categoryID}")) {
                    $filePreview .= eval(
                    getTemplate(
                        "{$templatePrefix}ThumbnailCategory{$categoryID}"
                    )
                    );
                } elseif (customTemplateIsSet("{$templatePrefix}ThumbnailField{$profileFieldID}")) {
                    $filePreview .= eval(getTemplate("{$templatePrefix}ThumbnailField{$profileFieldID}"));
                } else {
                    $filePreview .= eval(getTemplate("{$templatePrefix}Thumbnail"));
                }
            } elseif (customTemplateIsSet("{$templatePrefix}Category{$categoryID}")) {
                $filePreview .= eval(
                getTemplate(
                    "{$templatePrefix}Category{$categoryID}"
                )
                );
            } elseif (customTemplateIsSet("{$templatePrefix}{$profileFieldID}")) {
                $filePreview .= eval(getTemplate("{$templatePrefix}{$profileFieldID}"));
            } else {
                $filePreview .= eval(getTemplate($templatePrefix));
            }

            if (!in_array($templatePrefix, ['profile', 'postbit'])) {
                $update_aids = array_filter(
                    array_map('intval', $mybb->get_input('ougcfileprofilefields_update', MyBB::INPUT_ARRAY))
                );

                $checkedElement = '';

                if (isset($update_aids[$profileFieldData['fid']])) {
                    $checkedElement = ' checked="checked"';
                }

                $updateRow = eval(getTemplate("{$templatePrefix}Update"));

                $remove_aids = array_filter(
                    array_map('intval', $mybb->get_input('ougcfileprofilefields_remove', MyBB::INPUT_ARRAY))
                );

                $checkedElement = '';

                if (isset($remove_aids[$profileFieldData['fid']])) {
                    $checkedElement = ' checked="checked"';
                }

                $removeRow = eval(getTemplate("{$templatePrefix}Remove"));
            }
        }
    }

    if (!in_array($templatePrefix, ['profile', 'postbit'])) {
        load_language();

        $fileExtensions = $validMimeTypes = [];

        foreach ($mybb->cache->read('attachtypes') as $fileExtension => $attachType) {
            if (
                $attachType['ougc_fileprofilefields'] && is_member(
                    $profileFieldData['ougc_fileprofilefields_types'],
                    ['usergroup' => (int)$attachType['atid'], 'additionalgroups' => '']
                ) && is_member($attachType['groups'], get_user($userData['ufid']))
            ) {
                $validMimeTypes[] = $attachType['mimetype'];

                $fileExtensions[$fileExtension] = $lang->sprintf(
                    $lang->ougc_fileprofilefields_info_types_item,
                    my_strtoupper($fileExtension),
                    get_friendly_size(
                        (int)$profileFieldData['ougc_fileprofilefields_maxsize'] ?: (int)$attachType['maxsize']
                    )
                );
            }
        }

        if ($fileExtensions) {
            $allowedTypes = implode($lang->comma, array_keys($fileExtensions));

            $allowedFileFormats = '.' . implode(', .', array_keys($fileExtensions)) . ', ' . implode(
                    ', ',
                    $validMimeTypes
                );

            $filePreview = eval(getTemplate("{$templatePrefix}FieldWrapper"));
        } else {
            $filePreview = $lang->ougc_fileprofilefields_info_unconfigured;
        }
    }

    return true;
}

// control_object by Zinga Burga from MyBBHacks ( mybbhacks.zingaburga.com )
function control_object(&$obj, $code)
{
    static $cnt = 0;
    $newname = '_objcont_ougc_file_profile_fields_' . (++$cnt);
    $objserial = serialize($obj);
    $classname = get_class($obj);
    $checkstr = 'O:' . strlen($classname) . ':"' . $classname . '":';
    $checkstr_len = strlen($checkstr);
    if (substr($objserial, 0, $checkstr_len) == $checkstr) {
        $vars = [];
        // grab resources/object etc, stripping scope info from keys
        foreach ((array)$obj as $k => $v) {
            if ($p = strrpos($k, "\0")) {
                $k = substr($k, $p + 1);
            }
            $vars[$k] = $v;
        }
        if (!empty($vars)) {
            $code .= '
					function ___setvars(&$a) {
						foreach($a as $k => &$v)
							$this->$k = $v;
					}
				';
        }
        eval('class ' . $newname . ' extends ' . $classname . ' {' . $code . '}');
        $obj = unserialize('O:' . strlen($newname) . ':"' . $newname . '":' . substr($objserial, $checkstr_len));
        if (!empty($vars)) {
            $obj->___setvars($vars);
        }
    }
    // else not a valid object or PHP serialize has changed
}

// explicit workaround for PDO, as trying to serialize it causes a fatal error (even though PHP doesn't complain over serializing other resources)
if ($GLOBALS['db'] instanceof AbstractPdoDbDriver) {
    $GLOBALS['AbstractPdoDbDriver_lastResult_prop'] = new ReflectionProperty('AbstractPdoDbDriver', 'lastResult');
    $GLOBALS['AbstractPdoDbDriver_lastResult_prop']->setAccessible(true);
    function control_db($code)
    {
        global $db;
        $linkvars = [
            'read_link' => $db->read_link,
            'write_link' => $db->write_link,
            'current_link' => $db->current_link,
        ];
        unset($db->read_link, $db->write_link, $db->current_link);
        $lastResult = $GLOBALS['AbstractPdoDbDriver_lastResult_prop']->getValue($db);
        $GLOBALS['AbstractPdoDbDriver_lastResult_prop']->setValue($db, null); // don't let this block serialization
        control_object($db, $code);
        foreach ($linkvars as $k => $v) {
            $db->$k = $v;
        }
        $GLOBALS['AbstractPdoDbDriver_lastResult_prop']->setValue($db, $lastResult);
    }
} elseif ($GLOBALS['db'] instanceof DB_SQLite) {
    function control_db($code)
    {
        global $db;
        $oldLink = $db->db;
        unset($db->db);
        control_object($db, $code);
        $db->db = $oldLink;
    }
} else {
    function control_db($code)
    {
        control_object($GLOBALS['db'], $code);
    }
}
