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
 * This file contains the forms to create and edit an instance of this module
 *
 * @package   mod_review
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/review/locallib.php');

/**
 * Review settings form.
 *
 * @package   mod_review
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_review_mod_form extends moodleform_mod {

    /**
     * Called to define this moodle form
     *
     * @return void
     */
    public function definition() {
        global $CFG, $COURSE, $DB, $PAGE;
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('reviewname', 'review'), array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements(get_string('description', 'review'));

        $mform->addElement('filemanager', 'introattachments',
                            get_string('introattachments', 'review'),
                            null, array('subdirs' => 0, 'maxbytes' => $COURSE->maxbytes) );
        $mform->addHelpButton('introattachments', 'introattachments', 'review');

        $ctx = null;
        if ($this->current && $this->current->coursemodule) {
            $cm = get_coursemodule_from_instance('review', $this->current->id, 0, false, MUST_EXIST);
            $ctx = context_module::instance($cm->id);
        }
        $review = new review($ctx, null, null);
        if ($this->current && $this->current->course) {
            if (!$ctx) {
                $ctx = context_course::instance($this->current->course);
            }
            $course = $DB->get_record('course', array('id'=>$this->current->course), '*', MUST_EXIST);
            $review->set_course($course);
        }

        $config = get_config('review');

        $mform->addElement('header', 'availability', get_string('availability', 'review'));
        $mform->setExpanded('availability', true);

        $name = get_string('allowsubmissionsfromdate', 'review');
        $options = array('optional'=>true);
        $mform->addElement('date_time_selector', 'allowsubmissionsfromdate', $name, $options);
        $mform->addHelpButton('allowsubmissionsfromdate', 'allowsubmissionsfromdate', 'review');

        $name = get_string('duedate', 'review');
        $mform->addElement('date_time_selector', 'duedate', $name, array('optional'=>true));
        $mform->addHelpButton('duedate', 'duedate', 'review');

        $name = get_string('cutoffdate', 'review');
        $mform->addElement('date_time_selector', 'cutoffdate', $name, array('optional'=>true));
        $mform->addHelpButton('cutoffdate', 'cutoffdate', 'review');

        $name = get_string('gradingduedate', 'review');
        $mform->addElement('date_time_selector', 'gradingduedate', $name, array('optional' => true));
        $mform->addHelpButton('gradingduedate', 'gradingduedate', 'review');

        $name = get_string('alwaysshowdescription', 'review');
        $mform->addElement('checkbox', 'alwaysshowdescription', $name);
        $mform->addHelpButton('alwaysshowdescription', 'alwaysshowdescription', 'review');
        $mform->disabledIf('alwaysshowdescription', 'allowsubmissionsfromdate[enabled]', 'notchecked');

        $review->add_all_plugin_settings($mform);

        $mform->addElement('header', 'submissionsettings', get_string('submissionsettings', 'review'));

        $name = get_string('submissiondrafts', 'review');
        $mform->addElement('selectyesno', 'submissiondrafts', $name);
        $mform->addHelpButton('submissiondrafts', 'submissiondrafts', 'review');

        $name = get_string('requiresubmissionstatement', 'review');
        $mform->addElement('selectyesno', 'requiresubmissionstatement', $name);
        $mform->addHelpButton('requiresubmissionstatement',
                              'requiresubmissionstatement',
                              'review');
        $mform->setType('requiresubmissionstatement', PARAM_BOOL);

        $options = array(
            REVIEW_ATTEMPT_REOPEN_METHOD_NONE => get_string('attemptreopenmethod_none', 'mod_review'),
            REVIEW_ATTEMPT_REOPEN_METHOD_MANUAL => get_string('attemptreopenmethod_manual', 'mod_review'),
            REVIEW_ATTEMPT_REOPEN_METHOD_UNTILPASS => get_string('attemptreopenmethod_untilpass', 'mod_review')
        );
        $mform->addElement('select', 'attemptreopenmethod', get_string('attemptreopenmethod', 'mod_review'), $options);
        $mform->addHelpButton('attemptreopenmethod', 'attemptreopenmethod', 'mod_review');

        $options = array(REVIEW_UNLIMITED_ATTEMPTS => get_string('unlimitedattempts', 'mod_review'));
        $options += array_combine(range(1, 30), range(1, 30));
        $mform->addElement('select', 'maxattempts', get_string('maxattempts', 'mod_review'), $options);
        $mform->addHelpButton('maxattempts', 'maxattempts', 'review');
        $mform->hideIf('maxattempts', 'attemptreopenmethod', 'eq', REVIEW_ATTEMPT_REOPEN_METHOD_NONE);

        $mform->addElement('header', 'groupsubmissionsettings', get_string('groupsubmissionsettings', 'review'));

        $name = get_string('teamsubmission', 'review');
        $mform->addElement('selectyesno', 'teamsubmission', $name);
        $mform->addHelpButton('teamsubmission', 'teamsubmission', 'review');
        if ($review->has_submissions_or_grades()) {
            $mform->freeze('teamsubmission');
        }

        $name = get_string('preventsubmissionnotingroup', 'review');
        $mform->addElement('selectyesno', 'preventsubmissionnotingroup', $name);
        $mform->addHelpButton('preventsubmissionnotingroup',
            'preventsubmissionnotingroup',
            'review');
        $mform->setType('preventsubmissionnotingroup', PARAM_BOOL);
        $mform->hideIf('preventsubmissionnotingroup', 'teamsubmission', 'eq', 0);

        $name = get_string('requireallteammemberssubmit', 'review');
        $mform->addElement('selectyesno', 'requireallteammemberssubmit', $name);
        $mform->addHelpButton('requireallteammemberssubmit', 'requireallteammemberssubmit', 'review');
        $mform->hideIf('requireallteammemberssubmit', 'teamsubmission', 'eq', 0);
        $mform->disabledIf('requireallteammemberssubmit', 'submissiondrafts', 'eq', 0);

        $groupings = groups_get_all_groupings($review->get_course()->id);
        $options = array();
        $options[0] = get_string('none');
        foreach ($groupings as $grouping) {
            $options[$grouping->id] = $grouping->name;
        }

        $name = get_string('teamsubmissiongroupingid', 'review');
        $mform->addElement('select', 'teamsubmissiongroupingid', $name, $options);
        $mform->addHelpButton('teamsubmissiongroupingid', 'teamsubmissiongroupingid', 'review');
        $mform->hideIf('teamsubmissiongroupingid', 'teamsubmission', 'eq', 0);
        if ($review->has_submissions_or_grades()) {
            $mform->freeze('teamsubmissiongroupingid');
        }

        $mform->addElement('header', 'notifications', get_string('notifications', 'review'));

        $name = get_string('sendnotifications', 'review');
        $mform->addElement('selectyesno', 'sendnotifications', $name);
        $mform->addHelpButton('sendnotifications', 'sendnotifications', 'review');

        $name = get_string('sendlatenotifications', 'review');
        $mform->addElement('selectyesno', 'sendlatenotifications', $name);
        $mform->addHelpButton('sendlatenotifications', 'sendlatenotifications', 'review');
        $mform->disabledIf('sendlatenotifications', 'sendnotifications', 'eq', 1);

        $name = get_string('sendstudentnotificationsdefault', 'review');
        $mform->addElement('selectyesno', 'sendstudentnotifications', $name);
        $mform->addHelpButton('sendstudentnotifications', 'sendstudentnotificationsdefault', 'review');

        // Plagiarism enabling form.
        if (!empty($CFG->enableplagiarism)) {
            require_once($CFG->libdir . '/plagiarismlib.php');
            plagiarism_get_form_elements_module($mform, $ctx->get_course_context(), 'mod_review');
        }

        $this->standard_grading_coursemodule_elements();
        $name = get_string('blindmarking', 'review');
        $mform->addElement('selectyesno', 'blindmarking', $name);
        $mform->addHelpButton('blindmarking', 'blindmarking', 'review');
        if ($review->has_submissions_or_grades() ) {
            $mform->freeze('blindmarking');
        }

        $name = get_string('hidegrader', 'review');
        $mform->addElement('selectyesno', 'hidegrader', $name);
        $mform->addHelpButton('hidegrader', 'hidegrader', 'review');

        $name = get_string('markingworkflow', 'review');
        $mform->addElement('selectyesno', 'markingworkflow', $name);
        $mform->addHelpButton('markingworkflow', 'markingworkflow', 'review');

        $name = get_string('markingallocation', 'review');
        $mform->addElement('selectyesno', 'markingallocation', $name);
        $mform->addHelpButton('markingallocation', 'markingallocation', 'review');
        $mform->hideIf('markingallocation', 'markingworkflow', 'eq', 0);

        $this->standard_coursemodule_elements();
        $this->apply_admin_defaults();

        $this->add_action_buttons();
    }

    /**
     * Perform minimal validation on the settings form
     * @param array $data
     * @param array $files
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (!empty($data['allowsubmissionsfromdate']) && !empty($data['duedate'])) {
            if ($data['duedate'] < $data['allowsubmissionsfromdate']) {
                $errors['duedate'] = get_string('duedatevalidation', 'review');
            }
        }
        if (!empty($data['cutoffdate']) && !empty($data['duedate'])) {
            if ($data['cutoffdate'] < $data['duedate'] ) {
                $errors['cutoffdate'] = get_string('cutoffdatevalidation', 'review');
            }
        }
        if (!empty($data['allowsubmissionsfromdate']) && !empty($data['cutoffdate'])) {
            if ($data['cutoffdate'] < $data['allowsubmissionsfromdate']) {
                $errors['cutoffdate'] = get_string('cutoffdatefromdatevalidation', 'review');
            }
        }
        if ($data['gradingduedate']) {
            if ($data['allowsubmissionsfromdate'] && $data['allowsubmissionsfromdate'] > $data['gradingduedate']) {
                $errors['gradingduedate'] = get_string('gradingduefromdatevalidation', 'review');
            }
            if ($data['duedate'] && $data['duedate'] > $data['gradingduedate']) {
                $errors['gradingduedate'] = get_string('gradingdueduedatevalidation', 'review');
            }
        }
        if ($data['blindmarking'] && $data['attemptreopenmethod'] == REVIEW_ATTEMPT_REOPEN_METHOD_UNTILPASS) {
            $errors['attemptreopenmethod'] = get_string('reopenuntilpassincompatiblewithblindmarking', 'review');
        }

        return $errors;
    }

    /**
     * Any data processing needed before the form is displayed
     * (needed to set up draft areas for editor and filemanager elements)
     * @param array $defaultvalues
     */
    public function data_preprocessing(&$defaultvalues) {
        global $DB;

        $ctx = null;
        if ($this->current && $this->current->coursemodule) {
            $cm = get_coursemodule_from_instance('review', $this->current->id, 0, false, MUST_EXIST);
            $ctx = context_module::instance($cm->id);
        }
        $review = new review($ctx, null, null);
        if ($this->current && $this->current->course) {
            if (!$ctx) {
                $ctx = context_course::instance($this->current->course);
            }
            $course = $DB->get_record('course', array('id'=>$this->current->course), '*', MUST_EXIST);
            $review->set_course($course);
        }

        $draftitemid = file_get_submitted_draft_itemid('introattachments');
        file_prepare_draft_area($draftitemid, $ctx->id, 'mod_review', REVIEW_INTROATTACHMENT_FILEAREA,
                                0, array('subdirs' => 0));
        $defaultvalues['introattachments'] = $draftitemid;

        $review->plugin_data_preprocessing($defaultvalues);
    }

    /**
     * Add any custom completion rules to the form.
     *
     * @return array Contains the names of the added form elements
     */
    public function add_completion_rules() {
        $mform =& $this->_form;

        $mform->addElement('advcheckbox', 'completionsubmit', '', get_string('completionsubmit', 'review'));
        // Enable this completion rule by default.
        $mform->setDefault('completionsubmit', 1);
        return array('completionsubmit');
    }

    /**
     * Determines if completion is enabled for this module.
     *
     * @param array $data
     * @return bool
     */
    public function completion_rule_enabled($data) {
        return !empty($data['completionsubmit']);
    }

}
