<?php

/***************************************************************************
 *
 *    OUGC File Profile Fields plugin (/inc/plugins/ougc_fileprofilefields.php)
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

// Die if IN_MYBB is not defined, for security reasons.
use function ougc\FileProfileFields\Admin\_activate;

use function ougc\FileProfileFields\Admin\_deactivate;

use function ougc\FileProfileFields\Admin\_info;

use function ougc\FileProfileFields\Admin\_install;

use function ougc\FileProfileFields\Admin\_is_installed;

use function ougc\FileProfileFields\Admin\_uninstall;

use function ougc\FileProfileFields\Core\addHooks;

use const ougc\FileProfileFields\Core\ROOT;

defined('IN_MYBB') || die('This file cannot be accessed directly.');

define('ougc\FileProfileFields\Core\ROOT', MYBB_ROOT . 'inc/plugins/ougc/FileProfileFields');

require_once ROOT . '/core.php';

// PLUGINLIBRARY
defined('PLUGINLIBRARY') or define('PLUGINLIBRARY', MYBB_ROOT . 'inc/plugins/pluginlibrary.php');

// Add our hooks
if (defined('IN_ADMINCP')) {
    require_once ROOT . '/admin.php';

    require_once ROOT . '/admin_hooks.php';

    addHooks('ougc\FileProfileFields\Hooks\Admin');
}

require_once ROOT . '/forum_hooks.php';

addHooks('ougc\FileProfileFields\Hooks\Forum');

// Plugin API
function ougc_fileprofilefields_info()
{
    return _info();
}

// Activate the plugin.
function ougc_fileprofilefields_activate()
{
    _activate();
}

// Deactivate the plugin.
function ougc_fileprofilefields_deactivate()
{
    _deactivate();
}

// Install the plugin.
function ougc_fileprofilefields_install()
{
    _install();
}

// Check if installed.
function ougc_fileprofilefields_is_installed()
{
    return _is_installed();
}

// Unnstall the plugin.
function ougc_fileprofilefields_uninstall()
{
    _uninstall();
}