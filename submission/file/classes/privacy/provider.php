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
 * Privacy class for requesting user data.
 *
 * @package    reviewsubmission_file
 * @copyright  2018 Adrian Greeve <adrian@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace reviewsubmission_file\privacy;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/review/locallib.php');

use \core_privacy\local\metadata\collection;
use \core_privacy\local\request\writer;
use \core_privacy\local\request\contextlist;
use \mod_review\privacy\review_plugin_request_data;

/**
 * Privacy class for requesting user data.
 *
 * @package    reviewsubmission_file
 * @copyright  2018 Adrian Greeve <adrian@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \mod_review\privacy\reviewsubmission_provider,
        \mod_review\privacy\reviewsubmission_user_provider {

    /**
     * Return meta data about this plugin.
     *
     * @param  collection $collection A list of information to add to.
     * @return collection Return the collection after adding to it.
     */
    public static function get_metadata(collection $collection) : collection {
        $collection->link_subsystem('core_files', 'privacy:metadata:filepurpose');
        return $collection;
    }

    /**
     * This is covered by mod_review provider and the query on review_submissions.
     *
     * @param  int $userid The user ID that we are finding contexts for.
     * @param  contextlist $contextlist A context list to add sql and params to for contexts.
     */
    public static function get_context_for_userid_within_submission(int $userid, contextlist $contextlist) {
        // This is already fetched from mod_review.
    }

    /**
     * This is also covered by the mod_review provider and it's queries.
     *
     * @param  \mod_review\privacy\useridlist $useridlist An object for obtaining user IDs of students.
     */
    public static function get_student_user_ids(\mod_review\privacy\useridlist $useridlist) {
        // No need.
    }

    /**
     * If you have tables that contain userids and you can generate entries in your tables without creating an
     * entry in the review_submission table then please fill in this method.
     *
     * @param  userlist $userlist The userlist object
     */
    public static function get_userids_from_context(\core_privacy\local\request\userlist $userlist) {
        // Not required.
    }

    /**
     * Export all user data for this plugin.
     *
     * @param  review_plugin_request_data $exportdata Data used to determine which context and user to export and other useful
     * information to help with exporting.
     */
    public static function export_submission_user_data(review_plugin_request_data $exportdata) {
        // We currently don't show submissions to teachers when exporting their data.
        $context = $exportdata->get_context();
        if ($exportdata->get_user() != null) {
            return null;
        }
        $user = new \stdClass();
        $review = $exportdata->get_review();
        $plugin = $review->get_plugin_by_type('reviewsubmission', 'file');
        $files = $plugin->get_files($exportdata->get_pluginobject(), $user);
        foreach ($files as $file) {
            $userid = $exportdata->get_pluginobject()->userid;
            writer::with_context($exportdata->get_context())->export_file($exportdata->get_subcontext(), $file);

            // Plagiarism data.
            $coursecontext = $context->get_course_context();
            \core_plagiarism\privacy\provider::export_plagiarism_user_data($userid, $context, $exportdata->get_subcontext(), [
                'cmid' => $context->instanceid,
                'course' => $coursecontext->instanceid,
                'userid' => $userid,
                'file' => $file
            ]);
        }
    }

    /**
     * Any call to this method should delete all user data for the context defined in the deletion_criteria.
     *
     * @param  review_plugin_request_data $requestdata Information useful for deleting user data.
     */
    public static function delete_submission_for_context(review_plugin_request_data $requestdata) {
        global $DB;

        \core_plagiarism\privacy\provider::delete_plagiarism_for_context($requestdata->get_context());

        $fs = get_file_storage();
        $fs->delete_area_files($requestdata->get_context()->id, 'reviewsubmission_file', REVIEWSUBMISSION_FILE_FILEAREA);

        // Delete records from reviewsubmission_file table.
        $DB->delete_records('reviewsubmission_file', ['review' => $requestdata->get_review()->get_instance()->id]);
    }

    /**
     * A call to this method should delete user data (where practical) using the userid and submission.
     *
     * @param  review_plugin_request_data $deletedata Details about the user and context to focus the deletion.
     */
    public static function delete_submission_for_userid(review_plugin_request_data $deletedata) {
        global $DB;

        \core_plagiarism\privacy\provider::delete_plagiarism_for_user($deletedata->get_user()->id, $deletedata->get_context());

        $submissionid = $deletedata->get_pluginobject()->id;

        $fs = get_file_storage();
        $fs->delete_area_files($deletedata->get_context()->id, 'reviewsubmission_file', REVIEWSUBMISSION_FILE_FILEAREA,
                $submissionid);

        $DB->delete_records('reviewsubmission_file', ['review' => $deletedata->get_reviewid(), 'submission' => $submissionid]);
    }

    /**
     * Deletes all submissions for the submission ids / userids provided in a context.
     * review_plugin_request_data contains:
     * - context
     * - review object
     * - submission ids (pluginids)
     * - user ids
     * @param  review_plugin_request_data $deletedata A class that contains the relevant information required for deletion.
     */
    public static function delete_submissions(review_plugin_request_data $deletedata) {
        global $DB;

        \core_plagiarism\privacy\provider::delete_plagiarism_for_users($deletedata->get_userids(), $deletedata->get_context());

        if (empty($deletedata->get_submissionids())) {
            return;
        }
        $fs = get_file_storage();
        list($sql, $params) = $DB->get_in_or_equal($deletedata->get_submissionids(), SQL_PARAMS_NAMED);
        $fs->delete_area_files_select($deletedata->get_context()->id, 'reviewsubmission_file', REVIEWSUBMISSION_FILE_FILEAREA,
                $sql, $params);

        $params['reviewid'] = $deletedata->get_reviewid();
        $DB->delete_records_select('reviewsubmission_file', "review = :reviewid AND submission $sql", $params);
    }
}
