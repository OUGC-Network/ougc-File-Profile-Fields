<?php

/***************************************************************************
 *
 *    OUGC File Profile Fields plugin (/inc/languages/english/admin/ougc_fileprofilefields.lang.php)
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
    'setting_group_ougc_fileprofilefields' => 'OUGC File Profile Fields',
    'setting_group_ougc_fileprofilefields_desc' => 'Maximize your profile with custom file profile fields.',

    'setting_ougc_fileprofilefields_perpage' => 'Items Per Page',
    'setting_ougc_fileprofilefields_perpage_desc' => 'Default files and logs to display per page in the ModCP.',
    'setting_ougc_fileprofilefields_groups_moderators' => 'Moderator Groups',
    'setting_ougc_fileprofilefields_groups_moderators_desc' => 'Select which groups are allowed to manage files approval status and logs from the ModCP.',
    'setting_ougc_fileprofilefields_groups_moderate' => 'Moderate Groups',
    'setting_ougc_fileprofilefields_groups_moderate_desc' => 'You can moderate the files of specific groups, so their files will be visible only after they have been approved.',
    'setting_ougc_fileprofilefields_download_interval' => 'Download Count Interval',
    'setting_ougc_fileprofilefields_download_interval_desc' => 'Set the amount of seconds between download increase from the same users (not guests). Set to <code>0</code> to always count.',
    'setting_ougc_fileprofilefields_image_resize' => 'Image Auto Resize',
    'setting_ougc_fileprofilefields_image_resize_desc' => 'Turn this on to automatically resize image files to fit their maximum dimensions setting.',
    'setting_ougc_fileprofilefields_author_downloads' => 'Count Author Downloads',
    'setting_ougc_fileprofilefields_author_downloads_desc' => 'You can skip authors from increasing the download count of files. Please note that download logs are always stored for non thumbnails regardless of this setting.',
    'setting_ougc_fileprofilefields_force_downloads' => 'Force File Downloads',
    'setting_ougc_fileprofilefields_force_downloads_desc' => 'By default specific file types (png, pdf, txt, etc.) are rendered in browser. If you enable this files will be forced to be downloaded instead.',

    'ougc_fileprofilefields_attachments_fields' => 'Profile Fields File',
    'ougc_fileprofilefields_attachments_fields_desc' => 'Do you want to allow this attachment type to be used for profile fields?',
    'ougc_fileprofilefields_profilefields_type' => 'File',
    'ougc_fileprofilefields_profilefields_types' => 'File Types',
    'ougc_fileprofilefields_profilefields_types_desc' => 'Selec which attachment types to allow for this profile field.',
    'ougc_fileprofilefields_profilefields_types_all' => 'Allow all types',
    'ougc_fileprofilefields_profilefields_maxsize' => 'Maximum File Size (Kilobytes)',
    'ougc_fileprofilefields_profilefields_maxsize_desc' => 'The maximum file size for uploads of this profile field in Kilobytes (1 MB = 1024 KB). Leave empty or set to <code>0</code> for attachment type fallback.',
    'ougc_fileprofilefields_profilefields_maxsize_desc_intro' => '<br ><br >Please ensure the maximum file size is below the smallest of the following PHP limits:',
    'ougc_fileprofilefields_profilefields_maxsize_desc_max_size' => '<br >Upload Max File Size: {1}',
    'ougc_fileprofilefields_profilefields_maxsize_desc_post_size' => '<br >Max Post Size: {1}',
    'ougc_fileprofilefields_profilefields_directory' => 'Uploads Path',
    'ougc_fileprofilefields_profilefields_directory_desc' => 'The path used for file uploads. It <strong>must be chmod 777</strong> (on *nix servers).',
    'ougc_fileprofilefields_profilefields_customoutput' => 'Custom Output',
    'ougc_fileprofilefields_profilefields_customoutput_desc' => 'If you turn this on, files will be hidden from profiles and posts, you will need to paste <code>{$GLOBALS[\'ougc_fileprofilefields\'][\'fid{1}\']}</code> anywhere in both the <code>member_profile</code> and <code>postbit</code> or <code>postbit_classic</code> templates respectively.<br />This is stil dependent to the <code>Display on profile?</code> and <code>Display on postbit?</code> settings below.<br />You can also create <code>ougcfileprofilefields_profile_file_{1}</code> and <code>ougcfileprofilefields_profile_file_thumbnail_{1}</code> as additional custom templates for this specific profile field that will be used in both profiles and posts.',
    'ougc_fileprofilefields_profilefields_imageonly' => 'Only Image Files',
    'ougc_fileprofilefields_profilefields_imageonly_desc' => 'If yes, will require the uploaded file for this field to be an image.',
    'ougc_fileprofilefields_profilefields_imagemindims' => 'Minimum Image Dimensions',
    'ougc_fileprofilefields_profilefields_imagemindims_desc' => 'Enter the minimum dimensions for image files separated by a pipe (vertical line). Example <code>100|100</code>.',
    'ougc_fileprofilefields_profilefields_imagemaxdims' => 'Maximum Image Dimensions',
    'ougc_fileprofilefields_profilefields_imagemaxdims_desc' => 'Enter the maximum dimensions for image files separated by a pipe (vertical line). Example <code>500|500</code>.',
    'ougc_fileprofilefields_profilefields_thumbnails' => 'Generate Image Thumbnail',
    'ougc_fileprofilefields_profilefields_thumbnails_desc' => 'Enable this to generate image thumbnails for image files.',
    'ougc_fileprofilefields_profilefields_thumbnailsdimns' => 'Thumbnail Dimensions',
    'ougc_fileprofilefields_profilefields_thumbnailsdimns_desc' => 'Enter the dimensions for image thumbnails separated by a pipe (vertical line). Example <code>500|500</code>.',

    'ougc_fileprofilefields_edits_apply' => 'Click to <a href="{1}">Apply</a> changes to core files.',
    'ougc_fileprofilefields_edits_revert' => 'Click to <a href="{1}">Revert</a> changes to core files.',
    'ougc_fileprofilefields_edits_apply_success' => 'File changes were applied successfully to core files.',
    'ougc_fileprofilefields_edits_apply_error' => 'It was not possible to apply file changes to core files.',
    'ougc_fileprofilefields_edits_revert_success' => 'File changes were reverted successfully to core files.',
    'ougc_fileprofilefields_edits_revert_error' => 'It was not possible to revert file changes to core files.',
];