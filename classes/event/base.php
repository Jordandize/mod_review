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
 * The mod_review abstract base event.
 *
 * @package    mod_review
 * @copyright  2014 Mark Nelson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_review\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_review abstract base event class.
 *
 * Most mod_review events can extend this class.
 *
 * @package    mod_review
 * @since      Moodle 2.7
 * @copyright  2014 Mark Nelson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base extends \core\event\base {

    /** @var \review */
    protected $review;

    /**
     * Legacy log data.
     *
     * @var array
     */
    protected $legacylogdata;

    /**
     * Set review instance for this event.
     * @param \review $review
     * @throws \coding_exception
     */
    public function set_review(\review $review) {
        if ($this->is_triggered()) {
            throw new \coding_exception('set_review() must be done before triggerring of event');
        }
        if ($review->get_context()->id != $this->get_context()->id) {
            throw new \coding_exception('Invalid review isntance supplied!');
        }
        if ($review->is_blind_marking()) {
            $this->data['anonymous'] = 1;
        }
        $this->review = $review;
    }

    /**
     * Get review instance.
     *
     * NOTE: to be used from observers only.
     *
     * @throws \coding_exception
     * @return \review
     */
    public function get_review() {
        if ($this->is_restored()) {
            throw new \coding_exception('get_review() is intended for event observers only');
        }
        if (!isset($this->review)) {
            debugging('review property should be initialised in each event', DEBUG_DEVELOPER);
            global $CFG;
            require_once($CFG->dirroot . '/mod/review/locallib.php');
            $cm = get_coursemodule_from_id('review', $this->contextinstanceid, 0, false, MUST_EXIST);
            $course = get_course($cm->course);
            $this->review = new \review($this->get_context(), $cm, $course);
        }
        return $this->review;
    }


    /**
     * Returns relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/review/view.php', array('id' => $this->contextinstanceid));
    }

    /**
     * Sets the legacy event log data.
     *
     * @param string $action The current action
     * @param string $info A detailed description of the change. But no more than 255 characters.
     * @param string $url The url to the review module instance.
     */
    public function set_legacy_logdata($action = '', $info = '', $url = '') {
        $fullurl = 'view.php?id=' . $this->contextinstanceid;
        if ($url != '') {
            $fullurl .= '&' . $url;
        }

        $this->legacylogdata = array($this->courseid, 'review', $action, $fullurl, $info, $this->contextinstanceid);
    }

    /**
     * Return legacy data for add_to_log().
     *
     * @return array
     */
    protected function get_legacy_logdata() {
        if (isset($this->legacylogdata)) {
            return $this->legacylogdata;
        }

        return null;
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     */
    protected function validate_data() {
        parent::validate_data();

        if ($this->contextlevel != CONTEXT_MODULE) {
            throw new \coding_exception('Context level must be CONTEXT_MODULE.');
        }
    }
}
