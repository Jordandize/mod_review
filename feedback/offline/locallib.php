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
 * This file contains the definition for the library class for file feedback plugin
 *
 *
 * @package   reviewfeedback_offline
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/grade/grading/lib.php');

/**
 * library class for file feedback plugin extending feedback plugin base class
 *
 * @package   reviewfeedback_offline
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class review_feedback_offline extends review_feedback_plugin {

    /** @var boolean|null $enabledcache Cached lookup of the is_enabled function */
    private $enabledcache = null;

    /**
     * Get the name of the file feedback plugin
     * @return string
     */
    public function get_name() {
        return get_string('pluginname', 'reviewfeedback_offline');
    }

    /**
     * Get form elements for grading form
     *
     * @param stdClass $grade
     * @param MoodleQuickForm $mform
     * @param stdClass $data
     * @return bool true if elements were added to the form
     */
    public function get_form_elements($grade, MoodleQuickForm $mform, stdClass $data) {
        return false;
    }

    /**
     * Return true if there are no feedback files
     * @param stdClass $grade
     */
    public function is_empty(stdClass $grade) {
        return true;
    }

    /**
     * This plugin does not save through the normal interface so this returns false.
     *
     * @param stdClass $grade The grade.
     * @param stdClass $data Form data from the feedback form.
     * @return boolean - False
     */
    public function is_feedback_modified(stdClass $grade, stdClass $data) {
        return false;
    }

    /**
     * Loop through uploaded grades and update the grades for this review
     *
     * @param int $draftid - The unique draft item id for this import
     * @param int $importid - The unique import ID for this csv import operation
     * @param bool $ignoremodified - Ignore the last modified date when checking fields
     * @param string $encoding - Encoding of the file being processed.
     * @param string $separator - The character used to separate the information.
     * @return string - The html response
     */
    public function process_import_grades($draftid, $importid, $ignoremodified, $encoding = 'utf-8', $separator = 'comma') {
        global $USER, $DB;

        require_sesskey();
        require_capability('mod/review:grade', $this->review->get_context());

        $gradeimporter = new reviewfeedback_offline_grade_importer($importid, $this->review, $encoding, $separator);

        $context = context_user::instance($USER->id);
        $fs = get_file_storage();
        if (!$files = $fs->get_area_files($context->id, 'user', 'draft', $draftid, 'id DESC', false)) {
            redirect(new moodle_url('view.php',
                                array('id'=>$this->review->get_course_module()->id,
                                      'action'=>'grading')));
            return;
        }
        $file = reset($files);

        $csvdata = $file->get_content();

        if ($csvdata) {
            $gradeimporter->parsecsv($csvdata);
        }
        if (!$gradeimporter->init()) {
            $thisurl = new moodle_url('/mod/review/view.php', array('action'=>'viewpluginpage',
                                                                     'pluginsubtype'=>'reviewfeedback',
                                                                     'plugin'=>'offline',
                                                                     'pluginaction'=>'uploadgrades',
                                                                     'id' => $this->review->get_course_module()->id));
            print_error('invalidgradeimport', 'reviewfeedback_offline', $thisurl);
            return;
        }
        // Does this review use a scale?
        $scaleoptions = null;
        if ($this->review->get_instance()->grade < 0) {
            if ($scale = $DB->get_record('scale', array('id'=>-($this->review->get_instance()->grade)))) {
                $scaleoptions = make_menu_from_list($scale->scale);
            }
        }
        // We may need to upgrade the gradebook comments after this update.
        $adminconfig = $this->review->get_admin_config();
        $gradebookplugin = $adminconfig->feedback_plugin_for_gradebook;

        $updatecount = 0;
        while ($record = $gradeimporter->next()) {
            $user = $record->user;
            $modified = $record->modified;
            $userdesc = fullname($user);
            $usergrade = $this->review->get_user_grade($user->id, false);

            if (!empty($scaleoptions)) {
                // This is a scale - we need to convert any grades to indexes in the scale.
                $scaleindex = array_search($record->grade, $scaleoptions);
                if ($scaleindex !== false) {
                    $record->grade = $scaleindex;
                } else {
                    $record->grade = '';
                }
            } else {
                $record->grade = unformat_float($record->grade);
            }

            // Note: Do not count the seconds when comparing modified dates.
            $skip = false;
            $stalemodificationdate = ($usergrade && $usergrade->timemodified > ($modified + 60));

            if ($usergrade && $usergrade->grade == $record->grade) {
                // Skip - grade not modified.
                $skip = true;
            } else if (!isset($record->grade) || $record->grade === '' || $record->grade < 0) {
                // Skip - grade has no value.
                $skip = true;
            } else if (!$ignoremodified && $stalemodificationdate) {
                // Skip - grade has been modified.
                $skip = true;
            } else if ($this->review->grading_disabled($record->user->id)) {
                // Skip grade is locked.
                $skip = true;
            } else if (($this->review->get_instance()->grade > -1) &&
                      (($record->grade < 0) || ($record->grade > $this->review->get_instance()->grade))) {
                // Out of range.
                $skip = true;
            }

            if (!$skip) {
                $grade = $this->review->get_user_grade($record->user->id, true);

                $grade->grade = $record->grade;
                $grade->grader = $USER->id;
                if ($this->review->update_grade($grade)) {
                    $this->review->notify_grade_modified($grade);
                    $updatecount += 1;
                }
            }

            if ($ignoremodified || !$stalemodificationdate) {
                foreach ($record->feedback as $feedback) {
                    $plugin = $feedback['plugin'];
                    $field = $feedback['field'];
                    $newvalue = $feedback['value'];
                    $description = $feedback['description'];
                    $oldvalue = '';
                    if ($usergrade) {
                        $oldvalue = $plugin->get_editor_text($field, $usergrade->id);
                        if (empty($oldvalue)) {
                            $oldvalue = '';
                        }
                    }
                    if ($newvalue != $oldvalue) {
                        $updatecount += 1;
                        $grade = $this->review->get_user_grade($record->user->id, true);
                        $this->review->notify_grade_modified($grade);
                        $plugin->set_editor_text($field, $newvalue, $grade->id);

                        // If this is the gradebook comments plugin - post an update to the gradebook.
                        if (($plugin->get_subtype() . '_' . $plugin->get_type()) == $gradebookplugin) {
                            $grade->feedbacktext = $plugin->text_for_gradebook($grade);
                            $grade->feedbackformat = $plugin->format_for_gradebook($grade);
                            $this->review->update_grade($grade);
                        }
                    }
                }
            }
        }
        $gradeimporter->close(true);

        $renderer = $this->review->get_renderer();
        $o = '';

        $o .= $renderer->render(new review_header($this->review->get_instance(),
                                                  $this->review->get_context(),
                                                  false,
                                                  $this->review->get_course_module()->id,
                                                  get_string('importgrades', 'reviewfeedback_offline')));
        $o .= $renderer->box(get_string('updatedgrades', 'reviewfeedback_offline', $updatecount));
        $url = new moodle_url('view.php',
                              array('id'=>$this->review->get_course_module()->id,
                                    'action'=>'grading'));
        $o .= $renderer->continue_button($url);
        $o .= $renderer->render_footer();
        return $o;
    }

    /**
     * Display upload grades form
     *
     * @return string The response html
     */
    public function upload_grades() {
        global $CFG, $USER;

        require_capability('mod/review:grade', $this->review->get_context());
        require_once($CFG->dirroot . '/mod/review/feedback/offline/uploadgradesform.php');
        require_once($CFG->dirroot . '/mod/review/feedback/offline/importgradesform.php');
        require_once($CFG->dirroot . '/mod/review/feedback/offline/importgradeslib.php');
        require_once($CFG->libdir . '/csvlib.class.php');

        $mform = new reviewfeedback_offline_upload_grades_form(null,
                                                              array('context'=>$this->review->get_context(),
                                                                    'cm'=>$this->review->get_course_module()->id));

        $o = '';

        $confirm = optional_param('confirm', 0, PARAM_BOOL);
        $renderer = $this->review->get_renderer();

        if ($mform->is_cancelled()) {
            redirect(new moodle_url('view.php',
                                    array('id'=>$this->review->get_course_module()->id,
                                          'action'=>'grading')));
            return;
        } else if (($data = $mform->get_data()) &&
                   ($csvdata = $mform->get_file_content('gradesfile'))) {

            $importid = csv_import_reader::get_new_iid('reviewfeedback_offline');
            $gradeimporter = new reviewfeedback_offline_grade_importer($importid, $this->review,
                    $data->encoding, $data->separator);
            // File exists and was valid.
            $ignoremodified = !empty($data->ignoremodified);

            $draftid = $data->gradesfile;

            // Preview import.

            $mform = new reviewfeedback_offline_import_grades_form(null, array('review'=>$this->review,
                                                                       'csvdata'=>$csvdata,
                                                                       'ignoremodified'=>$ignoremodified,
                                                                       'gradeimporter'=>$gradeimporter,
                                                                       'draftid'=>$draftid));

            $o .= $renderer->render(new review_header($this->review->get_instance(),
                                                            $this->review->get_context(),
                                                            false,
                                                            $this->review->get_course_module()->id,
                                                            get_string('confirmimport', 'reviewfeedback_offline')));
            $o .= $renderer->render(new review_form('confirmimport', $mform));
            $o .= $renderer->render_footer();
        } else if ($confirm) {
            $importid = optional_param('importid', 0, PARAM_INT);
            $draftid = optional_param('draftid', 0, PARAM_INT);
            $encoding = optional_param('encoding', 'utf-8', PARAM_ALPHANUMEXT);
            $separator = optional_param('separator', 'comma', PARAM_ALPHA);
            $ignoremodified = optional_param('ignoremodified', 0, PARAM_BOOL);
            $gradeimporter = new reviewfeedback_offline_grade_importer($importid, $this->review, $encoding, $separator);
            $mform = new reviewfeedback_offline_import_grades_form(null, array('review'=>$this->review,
                                                                       'csvdata'=>'',
                                                                       'ignoremodified'=>$ignoremodified,
                                                                       'gradeimporter'=>$gradeimporter,
                                                                       'draftid'=>$draftid));
            if ($mform->is_cancelled()) {
                redirect(new moodle_url('view.php',
                                        array('id'=>$this->review->get_course_module()->id,
                                              'action'=>'grading')));
                return;
            }

            $o .= $this->process_import_grades($draftid, $importid, $ignoremodified, $encoding, $separator);
        } else {

            $o .= $renderer->render(new review_header($this->review->get_instance(),
                                                            $this->review->get_context(),
                                                            false,
                                                            $this->review->get_course_module()->id,
                                                            get_string('uploadgrades', 'reviewfeedback_offline')));
            $o .= $renderer->render(new review_form('batchuploadfiles', $mform));
            $o .= $renderer->render_footer();
        }

        return $o;
    }

    /**
     * Download a marking worksheet
     *
     * @return string The response html
     */
    public function download_grades() {
        global $CFG;

        require_capability('mod/review:grade', $this->review->get_context());
        require_once($CFG->dirroot . '/mod/review/gradingtable.php');

        $groupmode = groups_get_activity_groupmode($this->review->get_course_module());
        // All users.
        $groupid = 0;
        $groupname = '';
        if ($groupmode) {
            $groupid = groups_get_activity_group($this->review->get_course_module(), true);
            $groupname = groups_get_group_name($groupid) . '-';
        }
        $filename = clean_filename(get_string('offlinegradingworksheet', 'reviewfeedback_offline') . '-' .
                                   $this->review->get_course()->shortname . '-' .
                                   $this->review->get_instance()->name . '-' .
                                   $groupname .
                                   $this->review->get_course_module()->id);

        $table = new review_grading_table($this->review, 0, '', 0, false, $filename);

        $table->out(0, false);
        return;
    }

    /**
     * Print a sub page in this plugin
     *
     * @param string $action - The plugin action
     * @return string The response html
     */
    public function view_page($action) {
        if ($action == 'downloadgrades') {
            return $this->download_grades();
        } else if ($action == 'uploadgrades') {
            return $this->upload_grades();
        }

        return '';
    }

    /**
     * Return a list of the grading actions performed by this plugin
     * This plugin supports upload zip
     *
     * @return array The list of grading actions
     */
    public function get_grading_actions() {
        return array('uploadgrades'=>get_string('uploadgrades', 'reviewfeedback_offline'),
                    'downloadgrades'=>get_string('downloadgrades', 'reviewfeedback_offline'));
    }

    /**
     * Override the default is_enabled to disable this plugin if advanced grading is active
     *
     * @return bool
     */
    public function is_enabled() {
        if ($this->enabledcache === null) {
            $gradingmanager = get_grading_manager($this->review->get_context(), 'mod_review', 'submissions');
            $controller = $gradingmanager->get_active_controller();
            $active = !empty($controller);

            if ($active) {
                $this->enabledcache = false;
            } else {
                $this->enabledcache = parent::is_enabled();
            }
        }
        return $this->enabledcache;
    }

    /**
     * Do not show this plugin in the grading table or on the front page
     *
     * @return bool
     */
    public function has_user_summary() {
        return false;
    }

    /**
     * Return the plugin configs for external functions.
     *
     * @return array the list of settings
     * @since Moodle 3.2
     */
    public function get_config_for_external() {
        return (array) $this->get_config();
    }
}
