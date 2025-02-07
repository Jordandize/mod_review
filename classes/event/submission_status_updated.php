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
 * The mod_review submission status updated event.
 *
 * @package    mod_review
 * @copyright  2013 Frédéric Massart
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_review\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_review submission status updated event class.
 *
 * @property-read array $other {
 *      Extra information about event.
 *
 *      - string newstatus: status of submission.
 * }
 *
 * @package    mod_review
 * @since      Moodle 2.6
 * @copyright  2013 Frédéric Massart
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submission_status_updated extends base {
    /**
     * Create instance of event.
     *
     * @since Moodle 2.7
     *
     * @param \review $review
     * @param \stdClass $submission
     * @return submission_status_updated
     */
    public static function create_from_submission(\review $review, \stdClass $submission) {
        $data = array(
            'context' => $review->get_context(),
            'objectid' => $submission->id,
            'relateduserid' => ($review->get_instance()->teamsubmission) ? null : $submission->userid,
            'other' => array(
                'newstatus' => $submission->status
            )
        );
        /** @var submission_status_updated $event */
        $event = self::create($data);
        $event->set_review($review);
        $event->add_record_snapshot('review_submission', $submission);
        return $event;
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' has updated the status of the submission with id '$this->objectid' for " .
            "the review with course module id '$this->contextinstanceid' to the status '{$this->other['newstatus']}'.";
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventsubmissionstatusupdated', 'mod_review');
    }

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'review_submission';
    }

    /**
     * Return legacy data for add_to_log().
     *
     * @return array
     */
    protected function get_legacy_logdata() {
        $submission = $this->get_record_snapshot('review_submission', $this->objectid);
        $user = $this->get_record_snapshot('user', $submission->userid);
        $logmessage = get_string('reverttodraftforstudent', 'review', array('id' => $user->id, 'fullname' => fullname($user)));
        $this->set_legacy_logdata('revert submission to draft', $logmessage);
        return parent::get_legacy_logdata();
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->other['newstatus'])) {
            throw new \coding_exception('The \'newstatus\' value must be set in other.');
        }
    }

    public static function get_objectid_mapping() {
        return array('db' => 'review_submission', 'restore' => 'submission');
    }

    public static function get_other_mapping() {
        // Nothing to map.
        return false;
    }
}
