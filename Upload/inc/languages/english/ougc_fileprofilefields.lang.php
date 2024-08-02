<?php

/***************************************************************************
 *
 *    OUGC File Profile Fields plugin (/inc/languages/english/ougc_fileprofilefields.lang.php)
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

$l = [
    'setting_group_ougc_fileprofilefields' => 'File Profile Fields',

    'ougc_fileprofilefields_errors_upload_failed_upload_size' => 'There was an error uploading your file. It is be possible that your file exceed the server maximum upload size.',
    'ougc_fileprofilefields_errors_upload_failed_file_exists' => 'There was an error uploading your file. It is be possible that the destination directory is not writeable.',
    'ougc_fileprofilefields_errors_upload_failed_thumbnail_creation' => 'There was an error uploading your file. It was not possible to create a thumbnail of your file.',
    'ougc_fileprofilefields_errors_upload_failed' => 'There was an error uploading your profile file.',
    'ougc_fileprofilefields_errors_upload_failed_image_info' => 'There was an error uploading your profile file. It was not possible to get the image information of your file.',
    'ougc_fileprofilefields_errors_invalid_type' => 'The file type for {1} is invalid, only the following file types are valid: {2}.',
    'ougc_fileprofilefields_errors_invalid_mindims' => 'The minimum image dimensions for {1} are {2}x{3} (width x height).',
    'ougc_fileprofilefields_errors_invalid_maxdims' => 'The maximum image dimensions for {1} are {2}x{3} (width x height).',
    'ougc_fileprofilefields_errors_upload_size' => 'The maximum file size for {1} is {2} for {3} files.',
    'ougc_fileprofilefields_errors_file_name' => 'The file name for {1} exceeds the maximum file name length {2}. Please upload a file with a shorter file name.',
    'ougc_fileprofilefields_errors_remove' => 'The {1} field is required and thus you can not remove it completely, you need to replace it.',
    'ougc_fileprofilefields_errors_invalid_user' => 'The selected user seems to be invalid.',

    'ougc_fileprofilefields_errors_deactivated' => 'This feature is disabled.',
    'ougc_fileprofilefields_errors_invalid_thumbnail' => 'The selected thumbnail is invalid.',
    'ougc_fileprofilefields_errors_invalid_file' => 'The selected file is invalid.',

    'ougc_fileprofilefields_modcp_nav' => 'Profile Fields Files',
    'ougc_fileprofilefields_modcp_filter' => 'Filter',
    'ougc_fileprofilefields_modcp_filter_username' => 'Username',
    'ougc_fileprofilefields_modcp_filter_field' => 'Profile fields',
    'ougc_fileprofilefields_modcp_filter_date' => 'Date',
    'ougc_fileprofilefields_modcp_filter_date_desc' => 'This does affect both the upload and update time for files and logs dates.',
    'ougc_fileprofilefields_modcp_filter_status' => 'Status',
    'ougc_fileprofilefields_modcp_filter_status_queue' => 'Queue',
    'ougc_fileprofilefields_modcp_filter_status_approved' => 'Approved',
    'ougc_fileprofilefields_modcp_filter_status_unapproved' => 'Unapproved',
    'ougc_fileprofilefields_modcp_filter_perpage' => 'Items per page',
    'ougc_fileprofilefields_modcp_filter_order_by' => 'Sort by',
    'ougc_fileprofilefields_modcp_filter_order_by_username' => 'Username',
    'ougc_fileprofilefields_modcp_filter_order_by_mime' => 'Mime Type',
    'ougc_fileprofilefields_modcp_filter_order_by_filename' => 'File name',
    'ougc_fileprofilefields_modcp_filter_order_by_filesize' => 'File size',
    'ougc_fileprofilefields_modcp_filter_order_by_downloads' => 'Downloads',
    'ougc_fileprofilefields_modcp_filter_order_by_uploaddate' => 'Upload date / Log date',
    'ougc_fileprofilefields_modcp_filter_order_by_updatedate' => 'Update date',
    'ougc_fileprofilefields_modcp_filter_order_dir' => 'Sort direction',
    'ougc_fileprofilefields_modcp_filter_order_dir_asc' => 'Ascending',
    'ougc_fileprofilefields_modcp_filter_order_dir_desc' => 'Descending',
    'ougc_fileprofilefields_modcp_files' => 'Files',
    'ougc_fileprofilefields_modcp_files_empty' => 'There are no files to display.',
    'ougc_fileprofilefields_modcp_logs_empty' => 'There are no logs to display.',
    'ougc_fileprofilefields_modcp_files_username' => 'Username',
    'ougc_fileprofilefields_modcp_files_field' => 'Field',
    'ougc_fileprofilefields_modcp_files_details' => 'Details',
    'ougc_fileprofilefields_modcp_files_downloads' => 'Downloads',
    'ougc_fileprofilefields_modcp_files_uploaddate' => 'Upload Date',
    'ougc_fileprofilefields_modcp_files_updatedate' => 'Update Date',
    'ougc_fileprofilefields_modcp_files_status' => 'Status',
    'ougc_fileprofilefields_modcp_files_selectall' => 'Select All',
    'ougc_fileprofilefields_modcp_files_status_onqueue' => 'On Queue',
    'ougc_fileprofilefields_modcp_files_status_approved' => 'Approved',
    'ougc_fileprofilefields_modcp_files_status_unapproved' => 'Unapproved',
    'ougc_fileprofilefields_modcp_files_moderator' => 'Moderator',
    'ougc_fileprofilefields_modcp_files_button_approve' => 'Approve',
    'ougc_fileprofilefields_modcp_files_button_unapprove' => 'Unpprove',
    'ougc_fileprofilefields_modcp_files_button_delete' => 'Delete',
    'ougc_fileprofilefields_modcp_logs' => 'Download Logs',
    'ougc_fileprofilefields_modcp_logs_file' => 'File',
    'ougc_fileprofilefields_modcp_logs_filesize' => 'File size',
    'ougc_fileprofilefields_modcp_logs_dateline' => 'Date',
    'ougc_fileprofilefields_modcp_logs_ipaddress' => 'IP Address',

    'ougc_fileprofilefields_redirect_approved' => 'The selected files were approved successfully.',
    'ougc_fileprofilefields_redirect_unapproved' => 'The selected files were unapproved successfully.',
    'ougc_fileprofilefields_redirect_deleted' => 'The selected logs were deleted successfully.',

    'ougc_fileprofilefields_filesize' => 'File Size',
    'ougc_fileprofilefields_downloads' => 'Downloads',
    'ougc_fileprofilefields_uploaddate' => 'Upload Date',
    'ougc_fileprofilefields_updatedate' => 'Update Date',
    'ougc_fileprofilefields_md5' => 'MD5 Hash',
    'ougc_fileprofilefields_mime' => 'Mime Type',
    'ougc_fileprofilefields_update' => 'Update',
    'ougc_fileprofilefields_remove' => 'Remove',
    'ougc_fileprofilefields_info_types' => 'Allowed file types',
    'ougc_fileprofilefields_info_types_item' => '{1} ({2})',
    'ougc_fileprofilefields_info_unconfigured' => 'This field does not have any valid file type.',
    'ougc_fileprofilefields_status_notification_onqueue' => 'This file is on queue approval.',
    'ougc_fileprofilefields_status_notification_unapproved' => 'This file is unapproved.',
];