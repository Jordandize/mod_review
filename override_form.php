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
 * Settings form for overrides in the review module.
 *
 * @package    mod_review
 * @copyright  2016 Ilya Tregubov
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/mod/review/mod_form.php');


/**
 * Form for editing settings overrides.
 *
 * @copyright  2016 Ilya Tregubov
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class review_override_form extends moodleform {

    /** @var object course module object. */
    protected $cm;

    /** @var object the review settings object. */
    protected $review;

    /** @var context the review context. */
    protected $context;

    /** @var bool editing group override (true) or user override (false). */
    protected $groupmode;

    /** @var int groupid, if provided. */
    protected $groupid;

    /** @var int userid, if provided. */
    protected $userid;

    /** @var int sortorder, if provided. */
    protected $sortorder;

    /**
     * Constructor.
     * @param moodle_url $submiturl the form action URL.
     * @param object $cm course module object.
     * @param object $review the review settings object.
     * @param object $context the review context.
     * @param bool $groupmode editing group override (true) or user override (false).
     * @param object $override the override being edited, if it already exists.
     */
    public function __construct($submiturl, $cm, $review, $context, $groupmode, $override) {

        $this->cm = $cm;
        $this->review = $review;
        $this->context = $context;
        $this->groupmode = $groupmode;
        $this->groupid = empty($override->groupid) ? 0 : $override->groupid;
        $this->userid = empty($override->userid) ? 0 : $override->userid;
        $this->sortorder = empty($override->sortorder) ? null : $override->sortorder;

        parent::__construct($submiturl, null, 'post');

    }

    /**
     * Define this form - called by the parent constructor
     */
    protected function definition() {
        global $CFG, $DB;

        $cm = $this->cm;
        $mform = $this->_form;

        $mform->addElement('header', 'override', get_string('override', 'review'));

        if ($this->groupmode) {
            // Group override.
            if ($this->groupid) {
                // There is already a groupid, so freeze the selector.
                $groupchoices = array();
                $groupchoices[$this->groupid] = groups_get_group_name($this->groupid);
                $mform->addElement('select', 'groupid',
                        get_string('overridegroup', 'review'), $groupchoices);
                $mform->freeze('groupid');
                // Add a sortorder element.
                $mform->addElement('hidden', 'sortorder', $this->sortorder);
                $mform->setType('sortorder', PARAM_INT);
                $mform->freeze('sortorder');
            } else {
                // Prepare the list of groups.
                $groups = groups_get_all_groups($cm->course);
                if (empty($groups)) {
                    // Generate an error.
                    $link = new moodle_url('/mod/review/overrides.php', array('cmid' => $cm->id));
                    print_error('groupsnone', 'review', $link);
                }

                $groupchoices = array();
                foreach ($groups as $group) {
                    $groupchoices[$group->id] = $group->name;
                }
                unset($groups);

                if (count($groupchoices) == 0) {
                    $groupchoices[0] = get_string('none');
                }

                $mform->addElement('select', 'groupid',
                        get_string('overridegroup', 'review'), $groupchoices);
                $mform->addRule('groupid', get_string('required'), 'required', null, 'client');
            }
        } else {
            // User override.
            if ($this->userid) {
                // There is already a userid, so freeze the selector.
                $user = $DB->get_record('user', array('id' => $this->userid));
                $userchoices = array();
                $userchoices[$this->userid] = fullname($user);
                $mform->addElement('select', 'userid',
                        get_string('overrideuser', 'review'), $userchoices);
                $mform->freeze('userid');
            } else {
                // Prepare the list of users.
                $users = get_enrolled_users($this->context, '', 0,
                        'u.id, u.email, ' . get_all_user_name_fields(true, 'u'));

                // Filter users based on any fixed restrictions (groups, profile).
                $info = new \core_availability\info_module($cm);
                $users = $info->filter_user_list($users);

                if (empty($users)) {
                    // Generate an error.
                    $link = new moodle_url('/mod/review/overrides.php', array('cmid' => $cm->id));
                    print_error('usersnone', 'review', $link);
                }

                $userchoices = array();
                $canviewemail = in_array('email', get_extra_user_fields($this->context));
                foreach ($users as $id => $user) {
                    if (empty($invalidusers[$id]) || (!empty($override) &&
                            $id == $override->userid)) {
                        if ($canviewemail) {
                            $userchoices[$id] = fullname($user) . ', ' . $user->email;
                        } else {
                            $userchoices[$id] = fullname($user);
                        }
                    }
                }
                unset($users);

                if (count($userchoices) == 0) {
                    $userchoices[0] = get_string('none');
                }
                $mform->addElement('searchableselector', 'userid',
                        get_string('overrideuser', 'review'), $userchoices);
                $mform->addRule('userid', get_string('required'), 'required', null, 'client');
            }
        }

        $users = $DB->get_fieldset_select('groups_members', 'userid', 'groupid = ?', array($this->groupid));
        array_push($users, $this->userid);
        $extensionmax = 0;
        foreach ($users as $value) {
            $extension = $DB->get_record('review_user_flags', array('review' => $this->review->get_instance()->id,
                'userid' => $value));
            if ($extension) {
                if ($extensionmax < $extension->extensionduedate) {
                    $extensionmax = $extension->extensionduedate;
                }
            }
        }

        if ($extensionmax) {
            $this->review->get_instance()->extensionduedate = $extensionmax;
        }

        // Open and close dates.
        $mform->addElement('date_time_selector', 'allowsubmissionsfromdate',
            get_string('allowsubmissionsfromdate', 'review'), array('optional' => true));
        $mform->setDefault('allowsubmissionsfromdate', $this->review->get_instance()->allowsubmissionsfromdate);

        $mform->addElement('date_time_selector', 'duedate', get_string('duedate', 'review'), array('optional' => true));
        $mform->setDefault('duedate', $this->review->get_instance()->duedate);

        $mform->addElement('date_time_selector', 'cutoffdate', get_string('cutoffdate', 'review'), array('optional' => true));
        $mform->setDefault('cutoffdate', $this->review->get_instance()->cutoffdate);

        if (isset($this->review->get_instance()->extensionduedate)) {
            $mform->addElement('static', 'extensionduedate', get_string('extensionduedate', 'review'),
                userdate($this->review->get_instance()->extensionduedate));
        }

        // Submit buttons.
        $mform->addElement('submit', 'resetbutton',
                get_string('reverttodefaults', 'review'));

        $buttonarray = array();
        $buttonarray[] = $mform->createElement('submit', 'submitbutton',
                get_string('save', 'review'));
        $buttonarray[] = $mform->createElement('submit', 'againbutton',
                get_string('saveoverrideandstay', 'review'));
        $buttonarray[] = $mform->createElement('cancel');

        $mform->addGroup($buttonarray, 'buttonbar', '', array(' '), false);
        $mform->closeHeaderBefore('buttonbar');

    }

    /**
     * Validate the submitted form data.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors
     */
    public function validation($data, $files) {
        global $COURSE, $DB;
        $errors = parent::validation($data, $files);

        $mform =& $this->_form;
        $review = $this->review;

        if ($mform->elementExists('userid')) {
            if (empty($data['userid'])) {
                $errors['userid'] = get_string('required');
            }
        }

        if ($mform->elementExists('groupid')) {
            if (empty($data['groupid'])) {
                $errors['groupid'] = get_string('required');
            }
        }

        // Ensure that the dates make sense.
        if (!empty($data['allowsubmissionsfromdate']) && !empty($data['cutoffdate'])) {
            if ($data['cutoffdate'] < $data['allowsubmissionsfromdate']) {
                $errors['cutoffdate'] = get_string('cutoffdatefromdatevalidation', 'review');
            }
        }

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

        // Ensure that override duedate/allowsubmissionsfromdate are before extension date if exist.
        if (!empty($review->get_instance()->extensionduedate) && !empty($data['duedate'])) {
            if ($review->get_instance()->extensionduedate < $data['duedate']) {
                $errors['duedate'] = get_string('extensionnotafterduedate', 'review');
            }
        }
        if (!empty($review->get_instance()->extensionduedate) && !empty($data['allowsubmissionsfromdate'])) {
            if ($review->get_instance()->extensionduedate < $data['allowsubmissionsfromdate']) {
                $errors['allowsubmissionsfromdate'] = get_string('extensionnotafterfromdate', 'review');
            }
        }

        // Ensure that at least one review setting was changed.
        $changed = false;
        $keys = array('duedate', 'cutoffdate', 'allowsubmissionsfromdate');
        foreach ($keys as $key) {
            if ($data[$key] != $review->get_instance()->{$key}) {
                $changed = true;
                break;
            }
        }

        if (!$changed) {
            $errors['allowsubmissionsfromdate'] = get_string('nooverridedata', 'review');
        }

        return $errors;
    }
}
