<?php

/***************************************************************************
 *
 *    ougc File Profile Fields plugin (/inc/plugins/ougc/FileProfileFields/core.php)
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

use function ougc\FileProfileFields\Admin\pluginInformation;

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

function languageLoad(): bool
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
        $mybb->settings['ougcFileProfileFields_' . $settingKey] ?? false
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

function fileStore(array $fileData): int
{
    global $db, $plugins;

    $clean_data = [];

    $args = [
        'insert_data' => &$fileData,
        'clean_data' => &$clean_data
    ];

    foreach (['uid', 'fid', 'muid', 'filesize', 'downloads', 'uploaddate', 'updatedate', 'status'] as $key) {
        if (isset($fileData[$key])) {
            $clean_data[$key] = (int)$fileData[$key];
        }
    }

    foreach (['filename', 'filemime', 'name', 'thumbnail', 'dimensions', 'md5hash'] as $key) {
        if (isset($fileData[$key])) {
            $clean_data[$key] = $db->escape_string($fileData[$key]);
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

function fileGet(
    array $whereClauses,
    array $queryFields = [
        'uid',
        'muid',
        'fid',
        'filename',
        'filesize',
        'filemime',
        'name',
        'downloads',
        'thumbnail',
        'dimensions',
        'md5hash',
        'uploaddate',
        'updatedate',
        'status'
    ],
    array $queryOptions = []
): array {
    global $db;

    $queryFields[] = 'aid';

    $query = $db->simple_select(
        'ougc_fileprofilefields_files',
        implode(',', $queryFields),
        implode(' AND ', $whereClauses),
        $queryOptions
    );

    if ($db->num_rows($query)) {
        return (array)$db->fetch_array($query);
    }

    return [];
}

function fileGetMultiple(
    array $whereClauses,
    array $queryFields = [
        'uid',
        'muid',
        'fid',
        'filename',
        'filesize',
        'filemime',
        'name',
        'downloads',
        'thumbnail',
        'dimensions',
        'md5hash',
        'uploaddate',
        'updatedate',
        'status'
    ],
    array $queryOptions = []
): array {
    global $db;

    $queryFields[] = 'aid';

    $dbQuery = $db->simple_select(
        'ougc_fileprofilefields_files',
        implode(',', $queryFields),
        implode(' AND ', $whereClauses),
        $queryOptions
    );

    if (isset($queryOptions['limit']) && $queryOptions['limit'] === 1) {
        return (array)$db->fetch_array($dbQuery);
    }

    $filesObjects = [];

    while ($fileData = $db->fetch_array($dbQuery)) {
        $filesObjects[(int)$fileData['aid']] = $fileData;
    }

    return $filesObjects;
}

function fileUpload(int $userID, array $profileFieldData): array
{
    global $db, $mybb, $lang, $plugins;

    $ret = $ret_data = $valid_exts = $valid_mimes = $allowed_mime_types = [];

    $args = [
        'uid' => &$userID,
        'profilefield' => &$profileFieldData,
        'ret' => &$ret,
        'ret_data' => &$ret_data
    ];

    $args = $plugins->run_hooks('ougc_fileprofilefields_upload_file_start', $args);

    $userID = (int)$userID;

    $profileFieldData['fid'] = (int)$profileFieldData['fid'];

    languageLoad();

    $fieldIdentifier = "fid{$profileFieldData['fid']}";

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

    $userData = get_user($userID);

    foreach ($attachtypes as $ext => $attachtype) {
        if (
            $attachtype['ougc_fileprofilefields'] &&
            is_member(
                (string)$profileFieldData['ougc_fileprofilefields_types'],
                ['usergroup' => (int)$attachtype['atid'], 'additionalgroups' => '']
            ) &&
            ($attachtype['groups'] == -1 || is_member($attachtype['groups'], $userData))
        ) {
            $valid_exts[] = $ext;

            $valid_mimes[] = $attachtype['mimetype'];
            // TODO: Max file size should be separated from attach setting
            $allowed_mime_types[$attachtype['mimetype']] = (int)$profileFieldData['ougc_fileprofilefields_maxsize'] ?: (int)$attachtype['maxsize'];
        }
    }

    $valid_extensions = implode('|', $valid_exts);

    if (!preg_match("#^({$valid_extensions})$#i", $file_ext)) {
        $ret['error'] = $lang->sprintf(
            $lang->ougc_fileprofilefields_errors_invalid_type,
            htmlspecialchars_uni($profileFieldData['name']),
            implode(', ', $valid_exts)
        );

        return $ret;
    }

    $uploadpath = MYBB_ROOT . $profileFieldData['ougc_fileprofilefields_directory'];

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

    $fileName = "profilefieldfile_{$profileFieldData['fid']}_{$userID}_{$time_now}_{$random_md5}.attach";

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
            htmlspecialchars_uni($profileFieldData['name']),
            implode(', ', $valid_exts)
        );

        return $ret;
    }

    $ret_data['filemime'] = $uploaded_mime;

    $ret_data['uploadpath'] = $uploadpath;

    $ret_data['name'] = $fileName;

    if ($profileFieldData['ougc_fileprofilefields_imageonly']) {
        $img_dimensions = @getimagesize("{$uploadpath}/{$fileName}");

        if (!is_array($img_dimensions)) {
            delete_uploaded_file("{$uploadpath}/{$fileName}");

            $ret['error'] = $lang->ougc_fileprofilefields_errors_upload_failed_image_info;

            return $ret;
        }

        if (!empty($profileFieldData['ougc_fileprofilefields_imagemindims'])) {
            $minimum_dims = explode('|', $profileFieldData['ougc_fileprofilefields_imagemindims']);

            if (($minimum_dims[0] && $img_dimensions[0] < $minimum_dims[0]) || ($minimum_dims[1] && $img_dimensions[1] < $minimum_dims[1])) {
                delete_uploaded_file("{$uploadpath}/{$fileName}");

                $ret['error'] = $lang->sprintf(
                    $lang->ougc_fileprofilefields_errors_invalid_mindims,
                    htmlspecialchars_uni($profileFieldData['name']),
                    $minimum_dims[0],
                    $minimum_dims[1]
                );

                return $ret;
            }
        }

        if (!empty($profileFieldData['ougc_fileprofilefields_imagemaxdims'])) {
            $maximum_dims = explode('|', $profileFieldData['ougc_fileprofilefields_imagemaxdims']);

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
                            htmlspecialchars_uni($profileFieldData['name']),
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
                        htmlspecialchars_uni($profileFieldData['name']),
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

        if (!empty($profileFieldData['ougc_fileprofilefields_thumbnails']) && in_array(
                $img_type,
                [IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG]
            )) {
            $dims = explode('|', $profileFieldData['ougc_fileprofilefields_thumbnailsdimns']);

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
            htmlspecialchars_uni($profileFieldData['name']),
            get_friendly_size($allowed_mime_types[$file['type']]),
            my_strtoupper($file_ext)
        );

        return $ret;
    }

    $ret_data['md5hash'] = md5_file("{$uploadpath}/{$fileName}");

    $args = $plugins->run_hooks('ougc_fileprofilefields_upload_file_end', $args);

    return $ret_data;
}

function filesRemove(int $userID, int $fieldID, string $uploadsPath, array $excludedFiles = []): bool
{
    global $plugins;

    $profileFieldData = [];

    foreach (getProfileFieldsCache() as $profileField) {
        if ((int)$profileField['fid'] === $fieldID) {
            $profileFieldData = $profileField;

            break;
        }
    }

    $fileName = "profilefieldfile_{$fieldID}_{$userID}";

    $hook_arguments = [
        'uid' => &$userID,
        'fid' => &$fieldID,
        'uploadpath' => &$uploadsPath,
        'exclude' => &$excludedFiles,
        'profileFieldData' => &$profileFieldData,
        'filename' => &$fileName,
    ];

    $hook_arguments = $plugins->run_hooks('ougc_fileprofilefields_remove_files_start', $hook_arguments);

    $dir = opendir($uploadsPath);

    if ($dir) {
        is_array($excludedFiles) || $excludedFiles = [$excludedFiles];

        while ($file = @readdir($dir)) {
            if ($file == '.' or $file == '..') {
                continue;
            }

            if (preg_match("#{$fileName}#", $file) && is_file("{$uploadsPath}/{$file}") && !in_array(
                    $file,
                    $excludedFiles
                )) {
                require_once MYBB_ROOT . 'inc/functions_upload.php';

                delete_uploaded_file("{$uploadsPath}/{$file}");
            }
        }

        @closedir($dir);
    }

    $hook_arguments = $plugins->run_hooks('ougc_fileprofilefields_remove_files_end', $hook_arguments);

    return true;
}

function fileDelete(int $userID, array $profileFieldData): bool
{
    global $db, $mybb;

    $userID = (int)$userID;

    $fid = (int)$profileFieldData['fid'];

    $query = $db->simple_select('ougc_fileprofilefields_files', '*', "uid='{$userID}' AND fid='{$fid}'");

    while ($file = $db->fetch_array($query)) {
        filesRemove($userID, $fid, MYBB_ROOT . $profileFieldData['ougc_fileprofilefields_directory']);
    }

    $db->delete_query('ougc_fileprofilefields_files', "uid='{$userID}' AND fid='{$fid}'");

    $db->update_query('userfields', ["fid{$fid}" => ''], "ufid='{$userID}'");

    return true;
}

function fileReset(int $userID, array $profileFieldData): bool
{
    global $db, $mybb;

    $userID = (int)$userID;

    $fid = (int)$profileFieldData['fid'];

    $db->update_query('ougc_fileprofilefields_files', [
        'downloads' => 0,
        'uploaddate' => TIME_NOW,
        'updatedate' => TIME_NOW
    ], "uid='{$userID}' AND fid='{$fid}'");

    return true;
}

function userGetFields(int $userID): array
{
    static $user_cache = [];

    $userID = (int)$userID;

    if (!isset($user_cache[$userID])) {
        global $db;

        $query = $db->simple_select('userfields', '*', "ufid='{$userID}'");

        if ($db->num_rows($query)) {
            $user_cache[$userID] = (array)$db->fetch_array($query);
        } else {
            $user_cache[$userID] = [];
        }
    }

    return $user_cache[$userID];
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

    languageLoad();

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

    $fileID = (int)$fileData['aid'];

    urlHandlerSet(getSetting('fileName'));

    $fileUrl = urlHandlerBuild(['aid' => $fileID]);

    $thumbnailUrl = urlHandlerBuild(['thumbnail' => $fileID]);

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
        return eval(getTemplate("{$templatePrefix}Category{$categoryID}"));
    } elseif (customTemplateIsSet("{$templatePrefix}Field{$profileFieldID}")) {
        return eval(getTemplate("{$templatePrefix}Field{$profileFieldID}"));
    } else {
        return eval(getTemplate($templatePrefix));
    }
}

function buildFileFields(
    string $templatePrefix,
    array &$userData,
    array &$profileFieldData,
    string &$fileCode,
    bool $resetFieldCode = false
): bool {
    $fieldType = explode("\n", $profileFieldData['type'], 2)[0] ?? '';

    if ($fieldType !== 'file') {
        return false;
    }

    global $mybb, $user_fields, $customfield, $maxlength, $code, $lang, $ougc_fileprofilefields, $profilefields;
    global $ougcProfileFieldsCategoriesCurrentID, $ougcProfileFieldsCategoriesProfileContents;

    $categoryID = $ougcProfileFieldsCategoriesCurrentID ?? 0;

    isset($ougc_fileprofilefields) || $ougc_fileprofilefields = [];

    $removeRow = $updateRow = $statusCode = '';

    if ($templatePrefix == 'postbit') {
        //$fileID = (int)$hookArguments['post'][$fieldIdentifier];

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

    $fileID = isset($userData[$fieldIdentifier]) ? (int)$userData[$fieldIdentifier] : 0;

    $userID = (int)$userData['uid'];

    $ougc_fileprofilefields[$fieldIdentifier] = '';

    if (!empty($profileFieldData['ougc_fileprofilefields_customoutput'])) {
        $filePreview = &$ougc_fileprofilefields[$fieldIdentifier];
    } else {
        $filePreview = '';
    }

    $fieldLength = (int)$profileFieldData['length'];

    $styleCode = 'display: inherit;';

    $isModerator = is_member($mybb->settings['ougc_fileprofilefields_groups_moderators']);

    if (in_array($templatePrefix, ['profile', 'postbit'])) {
        $userData[$fieldIdentifier] = '';
    }

    $whereClauses = ["aid='{$fileID}'"];

    if (!is_member($mybb->settings['ougc_fileprofilefields_groups_moderators'])) {
        $whereClauses[] = "status='1'";
    }

    if ($fileData = fileGet(
        $whereClauses,
        [
            'filename',
            'filesize',
            'downloads',
            'md5hash',
            'uploaddate',
            'updatedate',
            'thumbnail',
            'status',
            'dimensions'
        ],
        ['limit' => 1]
    )) {
        urlHandlerSet(getSetting('fileName'));

        $fileUrl = urlHandlerBuild(['aid' => $fileID]);

        $thumbnailUrl = urlHandlerBuild(['thumbnail' => $fileID]);

        $fileStatus = (int)$fileData['status'];

        if (
            $fileStatus === 1 ||
            $isModerator ||
            !in_array($templatePrefix, ['profile', 'postbit'])
        ) {
            $styleCode = 'display: none;';

            languageLoad();

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

            if ($fileStatus !== 1) {
                $statusDescription = $lang->ougc_fileprofilefields_status_notification_onqueue;

                if ($fileStatus === FILE_STATUS_UNAPPROVED) {
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

    global $customFileProfileFields;

    isset($customFileProfileFields) || $customFileProfileFields = [];

    $customFileProfileFields[$fieldIdentifier] = $filePreview;

    if (!in_array($templatePrefix, ['profile', 'postbit'])) {
        languageLoad();

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

    $fileCode = $filePreview;

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
