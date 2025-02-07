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
 * This file contains the reviewsubmission_user_provider interface.
 *
 * Review Sub plugins should implement this if they store personal information and can retrieve a userid.
 *
 * @package mod_review
 * @copyright 2018 Adrian Greeve <adrian@moodle.com>
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_review\privacy;

use core_privacy\local\request\userlist;

defined('MOODLE_INTERNAL') || die();

interface reviewsubmission_user_provider extends
        \core_privacy\local\request\plugin\subplugin_provider,
        \core_privacy\local\request\shared_userlist_provider
    {

    /**
     * If you have tables that contain userids and you can generate entries in your tables without creating an
     * entry in the review_submission table then please fill in this method.
     *
     * @param  userlist $userlist The userlist object
     */
    public static function get_userids_from_context(userlist $userlist);

    /**
     * Deletes all submissions for the submission ids / userids provided in a context.
     * review_plugin_request_data contains:
     * - context
     * - review object
     * - submission ids (pluginids)
     * - user ids
     * @param  review_plugin_request_data $deletedata A class that contains the relevant information required for deletion.
     */
    public static function delete_submissions(review_plugin_request_data $deletedata);

}
