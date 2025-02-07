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
 * A scheduled task.
 *
 * @package    reviewfeedback_editpdf
 * @copyright  2016 Damyon Wiese
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace reviewfeedback_editpdf\task;

use core\task\scheduled_task;
use reviewfeedback_editpdf\document_services;
use reviewfeedback_editpdf\combined_document;
use context_module;
use review;

/**
 * Simple task to convert submissions to pdf in the background.
 * @copyright  2016 Damyon Wiese
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class convert_submissions extends scheduled_task {

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('preparesubmissionsforannotation', 'reviewfeedback_editpdf');
    }

    /**
     * Do the job.
     * Throw exceptions on errors (the job will be retried).
     */
    public function execute() {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/review/locallib.php');

        $records = $DB->get_records('reviewfeedback_editpdf_queue');

        $reviewcache = array();

        $conversionattemptlimit = !empty($CFG->conversionattemptlimit) ? $CFG->conversionattemptlimit : 3;
        foreach ($records as $record) {
            $submissionid = $record->submissionid;
            $submission = $DB->get_record('review_submission', array('id' => $submissionid), '*', IGNORE_MISSING);
            if (!$submission || $record->attemptedconversions >= $conversionattemptlimit) {
                // Submission no longer exists; or we've exceeded the conversion attempt limit.
                $DB->delete_records('reviewfeedback_editpdf_queue', array('id' => $record->id));
                continue;
            }

            // Record that we're attempting the conversion ahead of time.
            // We can't do this afterwards as its possible for the conversion process to crash the script entirely.
            $DB->set_field('reviewfeedback_editpdf_queue', 'attemptedconversions',
                    $record->attemptedconversions + 1, ['id' => $record->id]);

            $reviewid = $submission->review;
            $attemptnumber = $record->submissionattempt;

            if (empty($reviewcache[$reviewid])) {
                $cm = get_coursemodule_from_instance('review', $reviewid, 0, false, MUST_EXIST);
                $context = context_module::instance($cm->id);

                $review = new review($context, null, null);
                $reviewcache[$reviewid] = $review;
            } else {
                $review = $reviewcache[$reviewid];
            }

            $users = array();
            if ($submission->userid) {
                array_push($users, $submission->userid);
            } else {
                $members = $review->get_submission_group_members($submission->groupid, true);

                foreach ($members as $member) {
                    array_push($users, $member->id);
                }
            }

            mtrace('Convert ' . count($users) . ' submission attempt(s) for review ' . $reviewid);

            foreach ($users as $userid) {
                try {
                    $combineddocument = document_services::get_combined_pdf_for_attempt($review, $userid, $attemptnumber);
                    switch ($combineddocument->get_status()) {
                        case combined_document::STATUS_READY:
                        case combined_document::STATUS_READY_PARTIAL:
                        case combined_document::STATUS_PENDING_INPUT:
                            // The document has not been converted yet or is somehow still ready.
                            continue 2;
                    }
                    document_services::get_page_images_for_attempt(
                            $review,
                            $userid,
                            $attemptnumber,
                            false
                        );
                    document_services::get_page_images_for_attempt(
                            $review,
                            $userid,
                            $attemptnumber,
                            true
                        );
                } catch (\moodle_exception $e) {
                    mtrace('Conversion failed with error:' . $e->errorcode);
                }
            }

            // Remove from queue.
            $DB->delete_records('reviewfeedback_editpdf_queue', array('id' => $record->id));

        }
    }

}
