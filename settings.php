<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This file adds the settings pages to the navigation menu
 *
 * @package   mod_review
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/mod/review/adminlib.php');

$ADMIN->add('modsettings', new admin_category('modreviewfolder', new lang_string('pluginname', 'mod_review'), $module->is_enabled() === false));

$settings = new admin_settingpage($section, get_string('settings', 'mod_review'), 'moodle/site:config', $module->is_enabled() === false);

if ($ADMIN->fulltree) {
    $menu = array();
    foreach (core_component::get_plugin_list('reviewfeedback') as $type => $notused) {
        $visible = !get_config('reviewfeedback_' . $type, 'disabled');
        if ($visible) {
            $menu['reviewfeedback_' . $type] = new lang_string('pluginname', 'reviewfeedback_' . $type);
        }
    }

    // The default here is feedback_comments (if it exists).
    $name = new lang_string('feedbackplugin', 'mod_review');
    $description = new lang_string('feedbackpluginforgradebook', 'mod_review');
    $settings->add(new admin_setting_configselect('review/feedback_plugin_for_gradebook',
                                                  $name,
                                                  $description,
                                                  'reviewfeedback_comments',
                                                  $menu));

    $name = new lang_string('showrecentsubmissions', 'mod_review');
    $description = new lang_string('configshowrecentsubmissions', 'mod_review');
    $settings->add(new admin_setting_configcheckbox('review/showrecentsubmissions',
                                                    $name,
                                                    $description,
                                                    0));

    $name = new lang_string('sendsubmissionreceipts', 'mod_review');
    $description = new lang_string('sendsubmissionreceipts_help', 'mod_review');
    $settings->add(new admin_setting_configcheckbox('review/submissionreceipts',
                                                    $name,
                                                    $description,
                                                    1));

    $name = new lang_string('submissionstatement', 'mod_review');
    $description = new lang_string('submissionstatement_help', 'mod_review');
    $default = get_string('submissionstatementdefault', 'mod_review');
    $setting = new admin_setting_configtextarea('review/submissionstatement',
                                                    $name,
                                                    $description,
                                                    $default);
    $setting->set_force_ltr(false);
    $settings->add($setting);

    $name = new lang_string('submissionstatementteamsubmission', 'mod_review');
    $description = new lang_string('submissionstatement_help', 'mod_review');
    $default = get_string('submissionstatementteamsubmissiondefault', 'mod_review');
    $setting = new admin_setting_configtextarea('review/submissionstatementteamsubmission',
        $name,
        $description,
        $default);
    $setting->set_force_ltr(false);
    $settings->add($setting);

    $name = new lang_string('submissionstatementteamsubmissionallsubmit', 'mod_review');
    $description = new lang_string('submissionstatement_help', 'mod_review');
    $default = get_string('submissionstatementteamsubmissionallsubmitdefault', 'mod_review');
    $setting = new admin_setting_configtextarea('review/submissionstatementteamsubmissionallsubmit',
        $name,
        $description,
        $default);
    $setting->set_force_ltr(false);
    $settings->add($setting);

    $name = new lang_string('maxperpage', 'mod_review');
    $options = array(
        -1 => get_string('unlimitedpages', 'mod_review'),
        10 => 10,
        20 => 20,
        50 => 50,
        100 => 100,
    );
    $description = new lang_string('maxperpage_help', 'mod_review');
    $settings->add(new admin_setting_configselect('review/maxperpage',
                                                    $name,
                                                    $description,
                                                    -1,
                                                    $options));

    $name = new lang_string('defaultsettings', 'mod_review');
    $description = new lang_string('defaultsettings_help', 'mod_review');
    $settings->add(new admin_setting_heading('defaultsettings', $name, $description));

    $name = new lang_string('alwaysshowdescription', 'mod_review');
    $description = new lang_string('alwaysshowdescription_help', 'mod_review');
    $setting = new admin_setting_configcheckbox('review/alwaysshowdescription',
                                                    $name,
                                                    $description,
                                                    1);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('allowsubmissionsfromdate', 'mod_review');
    $description = new lang_string('allowsubmissionsfromdate_help', 'mod_review');
    $setting = new admin_setting_configduration('review/allowsubmissionsfromdate',
                                                    $name,
                                                    $description,
                                                    0);
    $setting->set_enabled_flag_options(admin_setting_flag::ENABLED, true);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('duedate', 'mod_review');
    $description = new lang_string('duedate_help', 'mod_review');
    $setting = new admin_setting_configduration('review/duedate',
                                                    $name,
                                                    $description,
                                                    604800);
    $setting->set_enabled_flag_options(admin_setting_flag::ENABLED, true);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('cutoffdate', 'mod_review');
    $description = new lang_string('cutoffdate_help', 'mod_review');
    $setting = new admin_setting_configduration('review/cutoffdate',
                                                    $name,
                                                    $description,
                                                    1209600);
    $setting->set_enabled_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('gradingduedate', 'mod_review');
    $description = new lang_string('gradingduedate_help', 'mod_review');
    $setting = new admin_setting_configduration('review/gradingduedate',
                                                    $name,
                                                    $description,
                                                    1209600);
    $setting->set_enabled_flag_options(admin_setting_flag::ENABLED, true);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('submissiondrafts', 'mod_review');
    $description = new lang_string('submissiondrafts_help', 'mod_review');
    $setting = new admin_setting_configcheckbox('review/submissiondrafts',
                                                    $name,
                                                    $description,
                                                    0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('requiresubmissionstatement', 'mod_review');
    $description = new lang_string('requiresubmissionstatement_help', 'mod_review');
    $setting = new admin_setting_configcheckbox('review/requiresubmissionstatement',
                                                    $name,
                                                    $description,
                                                    0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    // Constants from "locallib.php".
    $options = array(
        'none' => get_string('attemptreopenmethod_none', 'mod_review'),
        'manual' => get_string('attemptreopenmethod_manual', 'mod_review'),
        'untilpass' => get_string('attemptreopenmethod_untilpass', 'mod_review')
    );
    $name = new lang_string('attemptreopenmethod', 'mod_review');
    $description = new lang_string('attemptreopenmethod_help', 'mod_review');
    $setting = new admin_setting_configselect('review/attemptreopenmethod',
                                                    $name,
                                                    $description,
                                                    'none',
                                                    $options);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    // Constants from "locallib.php".
    $options = array(-1 => get_string('unlimitedattempts', 'mod_review'));
    $options += array_combine(range(1, 30), range(1, 30));
    $name = new lang_string('maxattempts', 'mod_review');
    $description = new lang_string('maxattempts_help', 'mod_review');
    $setting = new admin_setting_configselect('review/maxattempts',
                                                    $name,
                                                    $description,
                                                    -1,
                                                    $options);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('teamsubmission', 'mod_review');
    $description = new lang_string('teamsubmission_help', 'mod_review');
    $setting = new admin_setting_configcheckbox('review/teamsubmission',
                                                    $name,
                                                    $description,
                                                    0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('preventsubmissionnotingroup', 'mod_review');
    $description = new lang_string('preventsubmissionnotingroup_help', 'mod_review');
    $setting = new admin_setting_configcheckbox('review/preventsubmissionnotingroup',
        $name,
        $description,
        0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('requireallteammemberssubmit', 'mod_review');
    $description = new lang_string('requireallteammemberssubmit_help', 'mod_review');
    $setting = new admin_setting_configcheckbox('review/requireallteammemberssubmit',
                                                    $name,
                                                    $description,
                                                    0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('teamsubmissiongroupingid', 'mod_review');
    $description = new lang_string('teamsubmissiongroupingid_help', 'mod_review');
    $setting = new admin_setting_configempty('review/teamsubmissiongroupingid',
                                                    $name,
                                                    $description);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('sendnotifications', 'mod_review');
    $description = new lang_string('sendnotifications_help', 'mod_review');
    $setting = new admin_setting_configcheckbox('review/sendnotifications',
                                                    $name,
                                                    $description,
                                                    0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('sendlatenotifications', 'mod_review');
    $description = new lang_string('sendlatenotifications_help', 'mod_review');
    $setting = new admin_setting_configcheckbox('review/sendlatenotifications',
                                                    $name,
                                                    $description,
                                                    0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('sendstudentnotificationsdefault', 'mod_review');
    $description = new lang_string('sendstudentnotificationsdefault_help', 'mod_review');
    $setting = new admin_setting_configcheckbox('review/sendstudentnotifications',
                                                    $name,
                                                    $description,
                                                    1);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('blindmarking', 'mod_review');
    $description = new lang_string('blindmarking_help', 'mod_review');
    $setting = new admin_setting_configcheckbox('review/blindmarking',
                                                    $name,
                                                    $description,
                                                    0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('hidegrader', 'mod_review');
    $description = new lang_string('hidegrader_help', 'mod_review');
    $setting = new admin_setting_configcheckbox('review/hidegrader',
                                                    $name,
                                                    $description,
                                                    0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('markingworkflow', 'mod_review');
    $description = new lang_string('markingworkflow_help', 'mod_review');
    $setting = new admin_setting_configcheckbox('review/markingworkflow',
                                                    $name,
                                                    $description,
                                                    0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);

    $name = new lang_string('markingallocation', 'mod_review');
    $description = new lang_string('markingallocation_help', 'mod_review');
    $setting = new admin_setting_configcheckbox('review/markingallocation',
                                                    $name,
                                                    $description,
                                                    0);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $setting->set_locked_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);
}

$ADMIN->add('modreviewfolder', $settings);
// Tell core we already added the settings structure.
$settings = null;

$ADMIN->add('modreviewfolder', new admin_category('reviewsubmissionplugins',
    new lang_string('submissionplugins', 'review'), !$module->is_enabled()));
$ADMIN->add('reviewsubmissionplugins', new review_admin_page_manage_review_plugins('reviewsubmission'));
$ADMIN->add('modreviewfolder', new admin_category('reviewfeedbackplugins',
    new lang_string('feedbackplugins', 'review'), !$module->is_enabled()));
$ADMIN->add('reviewfeedbackplugins', new review_admin_page_manage_review_plugins('reviewfeedback'));

foreach (core_plugin_manager::instance()->get_plugins_of_type('reviewsubmission') as $plugin) {
    /** @var \mod_review\plugininfo\reviewsubmission $plugin */
    $plugin->load_settings($ADMIN, 'reviewsubmissionplugins', $hassiteconfig);
}

foreach (core_plugin_manager::instance()->get_plugins_of_type('reviewfeedback') as $plugin) {
    /** @var \mod_review\plugininfo\reviewfeedback $plugin */
    $plugin->load_settings($ADMIN, 'reviewfeedbackplugins', $hassiteconfig);
}
