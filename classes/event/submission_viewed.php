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
 * The mod_review submission viewed event.
 *
 * @package    mod_review
 * @copyright  2014 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_review\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_review submission viewed event class.
 *
 * @property-read array $other {
 *      Extra information about the event.
 *
 *      - int reviewid: the id of the review.
 * }
 *
 * @package    mod_review
 * @since      Moodle 2.7
 * @copyright  2014 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submission_viewed extends base {
    /**
     * Create instance of event.
     *
     * @param \review $review
     * @param \stdClass $submission
     * @return submission_viewed
     */
    public static function create_from_submission(\review $review, \stdClass $submission) {
        $data = array(
            'objectid' => $submission->id,
            'relateduserid' => $submission->userid,
            'context' => $review->get_context(),
            'other' => array(
                'reviewid' => $review->get_instance()->id,
            ),
        );
        /** @var submission_viewed $event */
        $event = self::create($data);
        $event->set_review($review);
        $event->add_record_snapshot('review_submission', $submission);
        return $event;
    }

    /**
     * Init method.
     */
    protected function init() {
        $this->data['objecttable'] = 'review_submission';
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventsubmissionviewed', 'mod_review');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' viewed the submission for the user with id '$this->relateduserid' for the " .
            "review with course module id '$this->contextinstanceid'.";
    }

    /**
     * Return legacy data for add_to_log().
     *
     * @return array
     */
    protected function get_legacy_logdata() {
        $logmessage = get_string('viewsubmissionforuser', 'review', $this->relateduserid);
        $this->set_legacy_logdata('view submission', $logmessage);
        return parent::get_legacy_logdata();
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }

        if (!isset($this->other['reviewid'])) {
            throw new \coding_exception('The \'reviewid\' value must be set in other.');
        }
    }

    public static function get_objectid_mapping() {
        return array('db' => 'review_submission', 'restore' => 'submission');
    }

    public static function get_other_mapping() {
        $othermapped = array();
        $othermapped['reviewid'] = array('db' => 'review', 'restore' => 'review');

        return $othermapped;
    }
}
