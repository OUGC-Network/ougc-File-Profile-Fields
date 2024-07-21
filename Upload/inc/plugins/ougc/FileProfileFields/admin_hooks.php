<?php

/***************************************************************************
 *
 *    OUGC File Profile Fields plugin (/inc/plugins/ougc/FileProfileFields/admin_hooks.php)
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

namespace ougc\FileProfileFields\Hooks\Admin;

use MyBB;

use function ougc\FileProfileFields\Admin\_db_columns;
use function ougc\FileProfileFields\Admin\_edits_apply;
use function ougc\FileProfileFields\Admin\_edits_revert;
use function ougc\FileProfileFields\Core\get_userfields;
use function ougc\FileProfileFields\Core\load_language;
use function ougc\FileProfileFields\Core\query_file;
use function ougc\FileProfileFields\Core\getTemplate;

use const MYBB_ROOT;

function admin_config_plugins_begin(): bool
{
    global $mybb, $lang;

    if (!$mybb->get_input('ougc_fileprofilefields')) {
        return false;
    }

    verify_post_check($mybb->get_input('my_post_key'));

    if ($mybb->get_input('ougc_fileprofilefields') == 'apply') {
        if (_edits_apply(true) === true) {
            flash_message($lang->ougc_fileprofilefields_edits_apply_success, 'success');
        } else {
            flash_message($lang->ougc_fileprofilefields_edits_apply_error, 'error');
        }

        admin_redirect('index.php?module=config-plugins');
    }

    if ($mybb->get_input('ougc_fileprofilefields') == 'revert') {
        if (_edits_revert(true) === true) {
            flash_message($lang->ougc_fileprofilefields_edits_revert_success, 'success');
        } else {
            flash_message($lang->ougc_fileprofilefields_edits_revert_error, 'error');
        }

        admin_redirect('index.php?module=config-plugins');
    }

    return true;
}

function admin_config_plugins_deactivate(): bool
{
    global $mybb, $page;

    if (
        $mybb->get_input('action') != 'deactivate' ||
        $mybb->get_input('plugin') != 'ougc_fileprofilefields' ||
        !$mybb->get_input('uninstall', MyBB::INPUT_INT)
    ) {
        return false;
    }

    if ($mybb->request_method != 'post') {
        $page->output_confirm_action(
            'index.php?module=config-plugins&amp;action=deactivate&amp;uninstall=1&amp;plugin=ougc_fileprofilefields'
        );
    }

    if ($mybb->get_input('no')) {
        admin_redirect('index.php?module=config-plugins');
    }

    return true;
}

function admin_config_attachment_types_delete_commit(): bool
{
    // todo, aybe deactivate file profile fields that use this attach type
    return true;
}

function admin_formcontainer_output_row(array &$args): array
{
    global $lang, $form_container, $form, $mybb, $select_list, $profile_fields, $cache, $input;

    if (empty($args['title'])) {
        return $args;
    }

    static $profile_fields_cache = null;

    if (!empty($profile_fields) && $profile_fields_cache == null) {
        $profile_fields_cache = [];

        if (!empty($profile_fields['optional'])) {
            foreach ($profile_fields['optional'] as $field) {
                $fid = (int)$field['fid'];

                $profile_fields_cache["profile_field_fid{$fid}"] = $fid;
            }
        }

        if (!empty($profile_fields['required'])) {
            foreach ($profile_fields['required'] as $field) {
                $fid = (int)$field['fid'];

                $profile_fields_cache["profile_field_fid{$fid}"] = $fid;
            }
        }

        $profile_fields_cache = array_filter($profile_fields_cache);
    }

    if (!empty($args['options']['id']) && !empty($profile_fields_cache[$args['options']['id']])) {
        global $templates;

        load_language();

        $pfcache = $cache->read('profilefields');

        if (is_array($pfcache)) {
            $fid = $profile_fields_cache[$args['options']['id']];

            $field = "fid{$fid}";

            $profilefield = false;

            foreach ($pfcache as $pf) {
                if ($pf['fid'] == $fid) {
                    $profilefield = $pf;

                    break;
                }
            }

            $preview = '';

            if ($profilefield) {
                $seloptions = [];

                $thing = explode("\n", $profilefield['type'], 2);

                $type = $thing[0];

                if ($type != 'file') {
                    return $args;
                }

                if ($mybb->get_input('action') == 'add') {
                    $args['options']['style'] = 'display: none !important;';

                    return $args;
                }

                static $user_fields = null;

                if ($user_fields === null) {
                    $user_fields = get_userfields($mybb->get_input('uid', MyBB::INPUT_INT));
                }

                $aid = (int)$user_fields[$field];

                $style = $accepted_formats = $update = $remove = '';

                if ($file = query_file($aid)) {
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

                    $status = '';

                    if ($file['status'] !== 1) {
                        $description = $lang->ougc_fileprofilefields_status_notification_onqueue;

                        if ($file['status'] === -1) {
                            $description = $lang->ougc_fileprofilefields_status_notification_unapproved;
                        }

                        $status = eval(getTemplate('adminControlPanelStatus'));
                    }

                    if (
                        $file['thumbnail'] &&
                        $profilefield['ougc_fileprofilefields_imageonly'] &&
                        $profilefield['ougc_fileprofilefields_thumbnails'] &&
                        file_exists(
                            MYBB_ROOT . "{$profilefield['ougc_fileprofilefields_directory']}/{$file['thumbnail']}"
                        )
                    ) {
                        // TODO: store thumbnail dimensions in DB
                        $dims = explode('|', $profilefield['ougc_fileprofilefields_thumbnailsdimns']);

                        $width = (int)$dims[0];

                        $height = (int)$dims[1];

                        $preview = eval(getTemplate('modcp_file_thumbnail'));
                    } else {
                        $preview = eval(getTemplate('modcp_file'));
                    }

                    $update_aids = array_filter(
                        array_map('intval', $mybb->get_input('ougcfileprofilefields_update', MyBB::INPUT_ARRAY))
                    );

                    $checked = '';

                    if (isset($update_aids[$profilefield['fid']])) {
                        $checked = ' checked="checked"';
                    }

                    $update = eval(getTemplate('modcp_update'));

                    $remove_aids = array_filter(
                        array_map('intval', $mybb->get_input('ougcfileprofilefields_remove', MyBB::INPUT_ARRAY))
                    );

                    $checked = '';

                    if (isset($remove_aids[$profilefield['fid']])) {
                        $checked = ' checked="checked"';
                    }

                    $remove = eval(getTemplate('modcp_remove'));
                }

                global $user;

                $attachcache = $mybb->cache->read('attachtypes');

                $exts = $valid_mimes = [];

                foreach ($attachcache as $ext => $attachtype) {
                    if (
                        $attachtype['ougc_fileprofilefields'] &&
                        is_member(
                            $profilefield['ougc_fileprofilefields_types'],
                            ['usergroup' => (int)$attachtype['atid'], 'additionalgroups' => '']
                        ) &&
                        ($attachtype['groups'] == -1 || is_member($attachtype['groups'], $user))
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
                }

                $code = eval(getTemplate('adminControlPanel'));

                $args['content'] = $code;
            }
        }
    }

    if (!empty($lang->avatar_file) && $args['title'] == $lang->avatar_file) {
        load_language();

        $form_container->output_row(
            $lang->ougc_fileprofilefields_attachments_fields,
            $lang->ougc_fileprofilefields_attachments_fields_desc,
            $form->generate_yes_no_radio(
                'ougc_fileprofilefields',
                $mybb->get_input('ougc_fileprofilefields', MyBB::INPUT_INT)
            ),
            'ougc_fileprofilefields'
        );
    }

    if (!empty($lang->field_type) && $args['title'] == $lang->field_type . ' <em>*</em>') {
        load_language();

        $select_list['file'] = $lang->ougc_fileprofilefields_profilefields_type;

        $args['content'] = $form->generate_select_box(
            'fieldtype',
            $select_list,
            $mybb->get_input('fieldtype'),
            array('id' => 'fieldtype')
        );
    }

    if (!empty($lang->show_on_registration) && $args['title'] == $lang->show_on_registration . ' <em>*</em>') {
        load_language();

        $attachtypes = $mybb->cache->read('attachtypes');

        $select_list = [-1 => $lang->ougc_fileprofilefields_profilefields_types_all];

        foreach ($attachtypes as $ext => $attachment) {
            if (empty($attachment['ougc_fileprofilefields'])) {
                continue;
            }

            $select_list[(int)$attachment['atid']] = htmlspecialchars_uni("{$attachment['name']} ({$ext})");
        }

        $args['row_options'] = ['id' => 'row_registration'];

        if ($mybb->request_method != 'post') {
            global $profile_field;

            if (isset($profile_field['ougc_fileprofilefields_types'])) {
                $mybb->input['ougc_fileprofilefields_types'] = explode(
                    ',',
                    $profile_field['ougc_fileprofilefields_types']
                );
            }
        }

        $atids = implode(
            ',',
            array_filter(array_map('intval', $mybb->get_input('ougc_fileprofilefields_types', MyBB::INPUT_ARRAY)))
        );

        if (!$atids || my_strpos($atids, '-1') !== false) {
            $mybb->input['ougc_fileprofilefields_types'] = [-1];
        }

        $form_container->output_row(
            $lang->ougc_fileprofilefields_profilefields_types,
            $lang->ougc_fileprofilefields_profilefields_types_desc,
            $form->generate_select_box(
                'ougc_fileprofilefields_types[]',
                $select_list,
                $mybb->get_input('ougc_fileprofilefields_types', MyBB::INPUT_ARRAY),
                ['multiple' => true, 'size' => 5]
            ),
            'ougc_fileprofilefields_types',
            [],
            ['id' => 'row_ougc_fileprofilefields_types']
        );

        // PHP settings
        $upload_max_filesize = @ini_get('upload_max_filesize');

        $post_max_size = @ini_get('post_max_size');

        $limit_string = '';

        if ($upload_max_filesize || $post_max_size) {
            $limit_string = $lang->ougc_fileprofilefields_profilefields_maxsize_desc_intro;

            if ($upload_max_filesize) {
                $limit_string .= $lang->sprintf(
                    $lang->ougc_fileprofilefields_profilefields_maxsize_desc_max_size,
                    $upload_max_filesize
                );
            }

            if ($post_max_size) {
                $limit_string .= $lang->sprintf(
                    $lang->ougc_fileprofilefields_profilefields_maxsize_desc_post_size,
                    $post_max_size
                );
            }
        }

        $form_container->output_row(
            $lang->ougc_fileprofilefields_profilefields_maxsize,
            $lang->ougc_fileprofilefields_profilefields_maxsize_desc . $limit_string,
            $form->generate_numeric_field(
                'ougc_fileprofilefields_maxsize',
                $mybb->get_input('ougc_fileprofilefields_maxsize', MyBB::INPUT_INT)
            ),
            'ougc_fileprofilefields_maxsize',
            [],
            ['id' => 'row_ougc_fileprofilefields_maxsize']
        );

        $form_container->output_row(
            $lang->ougc_fileprofilefields_profilefields_directory,
            $lang->ougc_fileprofilefields_profilefields_directory_desc,
            $form->generate_text_box(
                'ougc_fileprofilefields_directory',
                $mybb->get_input('ougc_fileprofilefields_directory')
            ),
            'ougc_fileprofilefields_directory',
            [],
            ['id' => 'row_ougc_fileprofilefields_directory']
        );

        $form_container->output_row(
            $lang->ougc_fileprofilefields_profilefields_customoutput,
            $lang->sprintf(
                $lang->ougc_fileprofilefields_profilefields_customoutput_desc,
                $mybb->get_input('fid', MyBB::INPUT_INT) ?: 'X'
            ),
            $form->generate_yes_no_radio(
                'ougc_fileprofilefields_customoutput',
                $mybb->get_input('ougc_fileprofilefields_customoutput', MyBB::INPUT_INT)
            ),
            'ougc_fileprofilefields_customoutput',
            [],
            ['id' => 'row_ougc_fileprofilefields_customoutput']
        );

        $form_container->output_row(
            $lang->ougc_fileprofilefields_profilefields_imageonly,
            $lang->ougc_fileprofilefields_profilefields_imageonly_desc,
            $form->generate_yes_no_radio(
                'ougc_fileprofilefields_imageonly',
                $mybb->get_input('ougc_fileprofilefields_imageonly', MyBB::INPUT_INT)
            ),
            'ougc_fileprofilefields_imageonly',
            [],
            ['id' => 'row_ougc_fileprofilefields_imageonly']
        );

        $form_container->output_row(
            $lang->ougc_fileprofilefields_profilefields_imagemindims,
            $lang->ougc_fileprofilefields_profilefields_imagemindims_desc,
            $form->generate_text_box(
                'ougc_fileprofilefields_imagemindims',
                $mybb->get_input('ougc_fileprofilefields_imagemindims')
            ),
            'ougc_fileprofilefields_imagemindims',
            [],
            ['id' => 'row_ougc_fileprofilefields_imagemindims']
        );

        $form_container->output_row(
            $lang->ougc_fileprofilefields_profilefields_imagemaxdims,
            $lang->ougc_fileprofilefields_profilefields_imagemaxdims_desc,
            $form->generate_text_box(
                'ougc_fileprofilefields_imagemaxdims',
                $mybb->get_input('ougc_fileprofilefields_imagemaxdims')
            ),
            'ougc_fileprofilefields_imagemaxdims',
            [],
            ['id' => 'row_ougc_fileprofilefields_imagemaxdims']
        );

        $form_container->output_row(
            $lang->ougc_fileprofilefields_profilefields_thumbnails,
            $lang->ougc_fileprofilefields_profilefields_thumbnails_desc,
            $form->generate_yes_no_radio(
                'ougc_fileprofilefields_thumbnails',
                $mybb->get_input('ougc_fileprofilefields_thumbnails', MyBB::INPUT_INT)
            ),
            'ougc_fileprofilefields_thumbnails',
            [],
            ['id' => 'row_ougc_fileprofilefields_thumbnails']
        );

        $form_container->output_row(
            $lang->ougc_fileprofilefields_profilefields_thumbnailsdimns,
            $lang->ougc_fileprofilefields_profilefields_thumbnailsdimns_desc,
            $form->generate_text_box(
                'ougc_fileprofilefields_thumbnailsdimns',
                $mybb->get_input('ougc_fileprofilefields_thumbnailsdimns')
            ),
            'ougc_fileprofilefields_thumbnailsdimns',
            [],
            ['id' => 'row_ougc_fileprofilefields_thumbnailsdimns']
        );
    }

    return $args;
}

function admin_config_attachment_types_add_commit(): bool
{
    global $atid, $db, $mybb;

    $atid = (int)$atid;

    $db->update_query('attachtypes', [
        'ougc_fileprofilefields' => $mybb->get_input('ougc_fileprofilefields', MyBB::INPUT_INT)
    ], "atid='{$atid}'");

    return true;
}

function admin_config_attachment_types_edit_commit(): bool
{
    global $updated_type, $mybb;

    $updated_type['ougc_fileprofilefields'] = $mybb->get_input('ougc_fileprofilefields', MyBB::INPUT_INT);

    return true;
}

function admin_config_profile_fields_add_commit(): bool
{
    global $fid, $mybb, $new_profile_field, $db;

    if (my_strpos($new_profile_field['type'], 'file') === false) {
        return false;
    }

    $fid = (int)$fid;

    $inserted_profile_field = [
        'type' => 'file',
        'registration' => 0,
        'length' => 0,
        'maxlength' => 0,
        'regex' => ''
    ];

    $atids = implode(
        ',',
        array_filter(array_map('intval', $mybb->get_input('ougc_fileprofilefields_types', MyBB::INPUT_ARRAY)))
    );

    $mybb->input['ougc_fileprofilefields_types'] = $atids && my_strpos($atids, '-1') === false ? $atids : -1;

    foreach (_db_columns()['profilefields'] as $key => $val) {
        if (isset($mybb->input[$key])) {
            $inserted_profile_field[$key] = $db->escape_string($mybb->get_input($key));
        }
    }

    $db->update_query('profilefields', $inserted_profile_field, "fid='{$fid}'");

    return true;
}

function admin_config_profile_fields_edit_commit(): bool
{
    global $mybb, $db, $updated_profile_field, $profile_field;

    $atids = implode(
        ',',
        array_filter(array_map('intval', $mybb->get_input('ougc_fileprofilefields_types', MyBB::INPUT_ARRAY)))
    );

    $mybb->input['ougc_fileprofilefields_types'] = $atids && my_strpos($atids, '-1') === false ? $atids : -1;

    foreach (_db_columns()['profilefields'] as $key => $val) {
        if (isset($mybb->input[$key])) {
            $updated_profile_field[$key] = $db->escape_string($mybb->get_input($key));
        }
    }

    $db->update_query('profilefields', [
        'type' => 'file',
        'registration' => 0
    ], "fid='{$profile_field['fid']}'");

    return true;
}

function admin_page_output_footer(): bool
{
    global $run_module, $page;

    if (!($run_module == 'config' && $page->active_action == 'profile_fields')) {
        return false;
    }

    echo '
	<script type="text/javascript">
		$(function() {
				new Peeker($("#fieldtype"), $("#row_registration"), /text|textarea|select|multiselect|radio|checkbox/, false);
				new Peeker($("#fieldtype"), $("#row_ougc_fileprofilefields_types, #row_ougc_fileprofilefields_maxsize, #row_ougc_fileprofilefields_directory, #row_ougc_fileprofilefields_customoutput, #row_ougc_fileprofilefields_imageonly, #row_ougc_fileprofilefields_imagemindims, #row_ougc_fileprofilefields_imagemaxdims, #row_ougc_fileprofilefields_thumbnails, #row_ougc_fileprofilefields_thumbnailsdimns"), /file/, false);
				new Peeker($("#row_ougc_fileprofilefields_imageonly input"), $("#row_ougc_fileprofilefields_imagemindims, #row_ougc_fileprofilefields_imagemaxdims, #row_ougc_fileprofilefields_thumbnails, #row_ougc_fileprofilefields_thumbnailsdimns"), 1, true);
				new Peeker($("#row_ougc_fileprofilefields_thumbnails input"), $("#row_ougc_fileprofilefields_thumbnailsdimns"), 1, true);
		});
	</script>';

    return true;
}