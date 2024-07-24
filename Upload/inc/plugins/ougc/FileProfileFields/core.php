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

use OUGC_ProfiecatsCache;

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

function load_language()
{
    global $lang;

    if (!isset($lang->setting_group_ougc_fileprofilefields)) {
        $lang->load('ougc_fileprofilefields');

        if (defined('IN_ADMINCP')) {
            $lang->load('ougc_fileprofilefields', true);
        }
    }
}

function load_pluginlibrary(bool $check = true)
{
    global $PL, $lang;

    load_language();

    if ($file_exists = file_exists(PLUGINLIBRARY)) {
        global $PL;

        $PL or require_once PLUGINLIBRARY;
    }

    if (!$check) {
        return;
    }

    $_info = _info();

    if (!$file_exists || $PL->version < $_info['pl']['version']) {
        flash_message(
            $lang->sprintf($lang->ougc_fileprofilefields_pluginlibrary, $_info['pl']['url'], $_info['pl']['version']),
            'error'
        );

        admin_redirect('index.php?module=config-plugins');
    }
}

function addHooks(string $namespace)
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

        $templateContents = file_get_contents($filePath);

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

function urlHandlerSet(string $newUrl)
{
    urlHandler($newUrl);
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

function store_file(array $insert_data)
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

        $aid = $db->insert_query('ougc_fileprofilefields_files', $clean_data);
    }

    return $aid;
}

function query_file(int $aid)
{
    global $db;

    $aid = (int)$aid;

    $query = $db->simple_select('ougc_fileprofilefields_files', '*', "aid='{$aid}'");

    return $db->fetch_array($query);
}

function queryFilesMultiple(
    array $whereClauses,
    string $queryFields = 'aid, uid, muid, fid, filename, filesize, filemime, name, downloads, thumbnail, dimensions, md5hash, uploaddate, updatedate, status'
) {
    global $db;

    $dbQuery = $db->simple_select('ougc_fileprofilefields_files', $queryFields, implode(' AND ', $whereClauses));

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

function upload_file(int $uid, array $profilefield)
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

    $field = "fid{$profilefield['fid']}";

    if (!is_uploaded_file($_FILES['profile_fields']['tmp_name'][$field])) {
        $ret['error'] = $lang->ougc_fileprofilefields_errors_upload_failed_upload_size;

        return $ret;
    }

    $ret_data['filename'] = $_FILES['profile_fields']['name'][$field];

    $ret_data['filemime'] = $_FILES['profile_fields']['type'][$field];

    $ret_data['filesize'] = $_FILES['profile_fields']['size'][$field];

    $ret_data['filetype'] = $file_ext = get_extension(my_strtolower($_FILES['profile_fields']['name'][$field]));

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

    if (my_strlen($_FILES['profile_fields']['name'][$field]) > $maxfilenamelength) {
        $ret['error'] = $lang->sprintf(
            $lang->ougc_fileprofilefields_errors_file_name,
            htmlspecialchars_uni($_FILES['profile_fields']['name'][$field]),
            $maxfilenamelength
        );

        return $ret;
    }

    $time_now = TIME_NOW;

    $random_md5 = md5(random_str());

    $filename = "profilefieldfile_{$profilefield['fid']}_{$uid}_{$time_now}_{$random_md5}.attach";

    require_once MYBB_ROOT . 'inc/functions_upload.php';

    $file = \upload_file([
        'name' => $_FILES['profile_fields']['name'][$field],
        'type' => $_FILES['profile_fields']['type'][$field],
        'tmp_name' => $_FILES['profile_fields']['tmp_name'][$field],
        'size' => $_FILES['profile_fields']['size'][$field],
    ], $uploadpath, $filename);

    if (isset($file['error']) || !file_exists("{$uploadpath}/{$filename}")) {
        delete_uploaded_file("{$uploadpath}/{$filename}");

        $ret['error'] = $lang->ougc_fileprofilefields_errors_upload_failed_file_exists;

        return $ret;
    }

    $uploaded_mime = @mime_content_type("{$uploadpath}/{$filename}");

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

    $ret_data['name'] = $filename;

    if ($profilefield['ougc_fileprofilefields_imageonly']) {
        $img_dimensions = @getimagesize("{$uploadpath}/{$filename}");

        if (!is_array($img_dimensions)) {
            delete_uploaded_file("{$uploadpath}/{$filename}");

            $ret['error'] = $lang->ougc_fileprofilefields_errors_upload_failed_image_info;

            return $ret;
        }

        if (!empty($profilefield['ougc_fileprofilefields_imagemindims'])) {
            $minimum_dims = explode('|', $profilefield['ougc_fileprofilefields_imagemindims']);

            if (($minimum_dims[0] && $img_dimensions[0] < $minimum_dims[0]) || ($minimum_dims[1] && $img_dimensions[1] < $minimum_dims[1])) {
                delete_uploaded_file("{$uploadpath}/{$filename}");

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
                        "{$uploadpath}/{$filename}",
                        $uploadpath,
                        $filename,
                        $maximum_dims[1],
                        $maximum_dims[0]
                    );

                    if (empty($thumbnail['filename'])) {
                        delete_uploaded_file("{$uploadpath}/{$filename}");

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
                        delete_uploaded_file("{$uploadpath}/{$filename}");

                        $ret['error'] = $lang->ougc_fileprofilefields_errors_upload_failed_image_info;

                        return $ret;
                    }
                } else {
                    delete_uploaded_file("{$uploadpath}/{$filename}");

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

            $ret_data['filemime'] = explode(';', finfo_file($file_info, "{$uploadpath}/{$filename}"))[0];

            finfo_close($file_info);
        } elseif (function_exists('mime_content_type')) {
            $ret_data['filemime'] = mime_content_type(MYBB_ROOT . "{$uploadpath}/{$filename}");
        }

        if (empty($allowed_mime_types[$ret_data['filemime']]) || $img_dimensions[2] != $img_type || !$img_type) {
            delete_uploaded_file("{$uploadpath}/{$filename}");

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
                "{$uploadpath}/{$filename}",
                $uploadpath,
                str_replace('.attach', "_thumb.{$file_ext}", $filename),
                $dims[1],
                $dims[0]
            );

            if (empty($thumbnail['filename'])) {
                delete_uploaded_file("{$uploadpath}/{$filename}");

                $ret['error'] = $lang->ougc_fileprofilefields_errors_upload_failed_thumbnail_creation;

                return $ret;
            }

            $ret_data['thumbnail'] = $thumbnail['filename'];

            $thumbnail_dimensions = @getimagesize("{$uploadpath}/{$thumbnail['filename']}");

            if (!is_array($thumbnail_dimensions)) {
                delete_uploaded_file("{$uploadpath}/{$filename}");

                $ret['error'] = $lang->ougc_fileprofilefields_errors_upload_failed_image_info;

                return $ret;
            }

            $ret_data['dimensions'] = "{$thumbnail_dimensions[0]}|{$thumbnail_dimensions[1]}";
        }
    }

    if ($file['size'] > ($allowed_mime_types[$file['type']] * 1024)) {
        delete_uploaded_file("{$uploadpath}/{$filename}");

        $ret['error'] = $lang->sprintf(
            $lang->ougc_fileprofilefields_errors_upload_size,
            htmlspecialchars_uni($profilefield['name']),
            get_friendly_size($allowed_mime_types[$file['type']]),
            my_strtoupper($file_ext)
        );

        return $ret;
    }

    $ret_data['md5hash'] = md5_file("{$uploadpath}/{$filename}");

    $args = $plugins->run_hooks('ougc_fileprofilefields_upload_file_end', $args);

    return $ret_data;
}

function remove_files(int $uid, int $fid, string $uploadpath, array $exclude = [])
{
    $filename = "profilefieldfile_{$fid}_{$uid}";

    $dir = opendir($uploadpath);

    if ($dir) {
        is_array($exclude) || $exclude = [$exclude];

        while ($file = @readdir($dir)) {
            if ($file == '.' or $file == '..') {
                continue;
            }

            if (preg_match("#{$filename}#", $file) && is_file("{$uploadpath}/{$file}") && !in_array($file, $exclude)) {
                require_once MYBB_ROOT . 'inc/functions_upload.php';

                delete_uploaded_file("{$uploadpath}/{$file}");
            }
        }

        @closedir($dir);
    }
}

function delete_file(int $uid, array $profilefield)
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
}

function reset_file(int $uid, array $profilefield)
{
    global $db, $mybb;

    $uid = (int)$uid;

    $fid = (int)$profilefield['fid'];

    $db->update_query('ougc_fileprofilefields_files', [
        'downloads' => 0,
        'uploaddate' => TIME_NOW,
        'updatedate' => TIME_NOW
    ], "uid='{$uid}' AND fid='{$fid}'");
}

function get_userfields(int $uid)
{
    static $user_cache = null;

    $uid = (int)$uid;

    if (!isset($user_cache[$uid])) {
        global $db;

        $query = $db->simple_select('userfields', '*', "ufid='{$uid}'");

        $user_cache[$uid] = $db->fetch_array($query);
    }

    return $user_cache[$uid];
}

function getProfileFieldsCache(): array
{
    global $mybb;
    global $profiecats;

    if (class_exists('OUGC_ProfiecatsCache') && $profiecats instanceof OUGC_ProfiecatsCache) {
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
    string $templateSection = TEMPLATE_SECTION_MEMBER_LIST,
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

        if ($isModerator && customTemplateIsSet("{$templateSection}StatusModeratorCategory{$categoryID}")) {
            $statusCode = eval(getTemplate("{$templateSection}StatusModeratorCategory{$categoryID}"));
        } elseif ($isModerator && customTemplateIsSet("{$templateSection}StatusModeratorField{$profileFieldID}")) {
            $statusCode = eval(getTemplate("{$templateSection}StatusModeratorField{$profileFieldID}"));
        } elseif ($isModerator) {
            $statusCode = eval(getTemplate("{$templateSection}StatusModerator"));
        } elseif (customTemplateIsSet("{$templateSection}StatusCategory{$categoryID}")) {
            $statusCode = eval(
            getTemplate(
                "{$templateSection}StatusCategory{$categoryID}"
            )
            );
        } elseif (customTemplateIsSet("{$templateSection}StatusField{$profileFieldID}")) {
            $statusCode = eval(getTemplate("{$templateSection}StatusField{$profileFieldID}"));
        } else {
            $statusCode = eval(getTemplate("{$templateSection}Status"));
        }
    }

    $attachmentID = (int)$fileData['aid'];

    if (
        $fileData['thumbnail'] &&
        $profileFieldData['ougc_fileprofilefields_imageonly'] &&
        $profileFieldData['ougc_fileprofilefields_thumbnails']
    ) {
        $thumbnailDimensions = explode('|', $fileData['dimensions']);

        $thumbnailWidth = $thumbnailDimensions[0] ?? 0;

        $thumbnailHeight = $thumbnailDimensions[1] ?? 0;

        if (customTemplateIsSet("{$templateSection}FileThumbnailCategory{$categoryID}")) {
            return eval(getTemplate("{$templateSection}FileThumbnailCategory{$categoryID}"));
        } elseif (customTemplateIsSet("{$templateSection}FileThumbnailField{$profileFieldID}")) {
            return eval(getTemplate("{$templateSection}FileThumbnailField{$profileFieldID}"));
        } else {
            return eval(getTemplate("{$templateSection}FileThumbnail"));
        }
    } elseif (customTemplateIsSet("{$templateSection}FileCategory{$categoryID}")) {
        return eval(getTemplate("{$templateSection}FileCategory{$categoryID}"));
    } elseif (customTemplateIsSet("{$templateSection}FileField{$profileFieldID}")) {
        return eval(getTemplate("{$templateSection}FileField{$profileFieldID}"));
    } else {
        return eval(getTemplate("{$templateSection}File"));
    }
}