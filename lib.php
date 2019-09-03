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
 * This file contains the moodle hooks for the review module.
 *
 * It delegates most functions to the review class.
 *
 * @package   mod_review
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Adds an review instance
 *
 * This is done by calling the add_instance() method of the review type class
 * @param stdClass $data
 * @param mod_review_mod_form $form
 * @return int The instance id of the new review
 */
function review_add_instance(stdClass $data, mod_review_mod_form $form = null) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/review/locallib.php');

    $review = new review(context_module::instance($data->coursemodule), null, null);
    return $review->add_instance($data, true);
}

/**
 * delete an review instance
 * @param int $id
 * @return bool
 */
function review_delete_instance($id) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/review/locallib.php');
    $cm = get_coursemodule_from_instance('review', $id, 0, false, MUST_EXIST);
    $context = context_module::instance($cm->id);

    $review = new review($context, null, null);
    return $review->delete_instance();
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 * This function will remove all review submissions and feedbacks in the database
 * and clean up any related data.
 *
 * @param stdClass $data the data submitted from the reset course.
 * @return array
 */
function review_reset_userdata($data) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/review/locallib.php');

    $status = array();
    $params = array('courseid'=>$data->courseid);
    $sql = "SELECT a.id FROM {review} a WHERE a.course=:courseid";
    $course = $DB->get_record('course', array('id'=>$data->courseid), '*', MUST_EXIST);
    if ($reviews = $DB->get_records_sql($sql, $params)) {
        foreach ($reviews as $review) {
            $cm = get_coursemodule_from_instance('review',
                                                 $review->id,
                                                 $data->courseid,
                                                 false,
                                                 MUST_EXIST);
            $context = context_module::instance($cm->id);
            $review = new review($context, $cm, $course);
            $status = array_merge($status, $review->reset_userdata($data));
        }
    }
    return $status;
}

/**
 * This standard function will check all instances of this module
 * and make sure there are up-to-date events created for each of them.
 * If courseid = 0, then every review event in the site is checked, else
 * only review events belonging to the course specified are checked.
 *
 * @param int $courseid
 * @param int|stdClass $instance Review module instance or ID.
 * @param int|stdClass $cm Course module object or ID (not used in this module).
 * @return bool
 */
function review_refresh_events($courseid = 0, $instance = null, $cm = null) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/review/locallib.php');

    // If we have instance information then we can just update the one event instead of updating all events.
    if (isset($instance)) {
        if (!is_object($instance)) {
            $instance = $DB->get_record('review', array('id' => $instance), '*', MUST_EXIST);
        }
        if (isset($cm)) {
            if (!is_object($cm)) {
                review_prepare_update_events($instance);
                return true;
            } else {
                $course = get_course($instance->course);
                review_prepare_update_events($instance, $course, $cm);
                return true;
            }
        }
    }

    if ($courseid) {
        // Make sure that the course id is numeric.
        if (!is_numeric($courseid)) {
            return false;
        }
        if (!$reviews = $DB->get_records('review', array('course' => $courseid))) {
            return false;
        }
        // Get course from courseid parameter.
        if (!$course = $DB->get_record('course', array('id' => $courseid), '*')) {
            return false;
        }
    } else {
        if (!$reviews = $DB->get_records('review')) {
            return false;
        }
    }
    foreach ($reviews as $review) {
        review_prepare_update_events($review);
    }

    return true;
}

/**
 * This actually updates the normal and completion calendar events.
 *
 * @param  stdClass $review Review object (from DB).
 * @param  stdClass $course Course object.
 * @param  stdClass $cm Course module object.
 */
function review_prepare_update_events($review, $course = null, $cm = null) {
    global $DB;
    if (!isset($course)) {
        // Get course and course module for the review.
        list($course, $cm) = get_course_and_cm_from_instance($review->id, 'review', $review->course);
    }
    // Refresh the review's calendar events.
    $context = context_module::instance($cm->id);
    $review = new review($context, $cm, $course);
    $review->update_calendar($cm->id);
    // Refresh the calendar events also for the review overrides.
    $overrides = $DB->get_records('review_overrides', ['reviewid' => $review->id], '',
                                  'id, groupid, userid, duedate, sortorder');
    foreach ($overrides as $override) {
        if (empty($override->userid)) {
            unset($override->userid);
        }
        if (empty($override->groupid)) {
            unset($override->groupid);
        }
        review_update_events($review, $override);
    }
}

/**
 * Removes all grades from gradebook
 *
 * @param int $courseid The ID of the course to reset
 * @param string $type Optional type of review to limit the reset to a particular review type
 */
function review_reset_gradebook($courseid, $type='') {
    global $CFG, $DB;

    $params = array('moduletype'=>'review', 'courseid'=>$courseid);
    $sql = 'SELECT a.*, cm.idnumber as cmidnumber, a.course as courseid
            FROM {review} a, {course_modules} cm, {modules} m
            WHERE m.name=:moduletype AND m.id=cm.module AND cm.instance=a.id AND a.course=:courseid';

    if ($reviews = $DB->get_records_sql($sql, $params)) {
        foreach ($reviews as $review) {
            review_grade_item_update($review, 'reset');
        }
    }
}

/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the review.
 * @param moodleform $mform form passed by reference
 */
function review_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'reviewheader', get_string('modulenameplural', 'review'));
    $name = get_string('deleteallsubmissions', 'review');
    $mform->addElement('advcheckbox', 'reset_review_submissions', $name);
    $mform->addElement('advcheckbox', 'reset_review_user_overrides',
        get_string('removealluseroverrides', 'review'));
    $mform->addElement('advcheckbox', 'reset_review_group_overrides',
        get_string('removeallgroupoverrides', 'review'));
}

/**
 * Course reset form defaults.
 * @param  object $course
 * @return array
 */
function review_reset_course_form_defaults($course) {
    return array('reset_review_submissions' => 1,
            'reset_review_group_overrides' => 1,
            'reset_review_user_overrides' => 1);
}

/**
 * Update an review instance
 *
 * This is done by calling the update_instance() method of the review type class
 * @param stdClass $data
 * @param stdClass $form - unused
 * @return object
 */
function review_update_instance(stdClass $data, $form) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/review/locallib.php');
    $context = context_module::instance($data->coursemodule);
    $review = new review($context, null, null);
    return $review->update_instance($data);
}

/**
 * This function updates the events associated to the review.
 * If $override is non-zero, then it updates only the events
 * associated with the specified override.
 *
 * @param review $review the review object.
 * @param object $override (optional) limit to a specific override
 */
function review_update_events($review, $override = null) {
    global $CFG, $DB;

    require_once($CFG->dirroot . '/calendar/lib.php');

    $reviewinstance = $review->get_instance();

    // Load the old events relating to this review.
    $conds = array('modulename' => 'review', 'instance' => $reviewinstance->id);
    if (!empty($override)) {
        // Only load events for this override.
        if (isset($override->userid)) {
            $conds['userid'] = $override->userid;
        } else if (isset($override->groupid)) {
            $conds['groupid'] = $override->groupid;
        } else {
            // This is not a valid override, it may have been left from a bad import or restore.
            $conds['groupid'] = $conds['userid'] = 0;
        }
    }
    $oldevents = $DB->get_records('event', $conds, 'id ASC');

    // Now make a to-do list of all that needs to be updated.
    if (empty($override)) {
        // We are updating the primary settings for the review, so we need to add all the overrides.
        $overrides = $DB->get_records('review_overrides', array('reviewid' => $reviewinstance->id), 'id ASC');
        // It is necessary to add an empty stdClass to the beginning of the array as the $oldevents
        // list contains the original (non-override) event for the module. If this is not included
        // the logic below will end up updating the wrong row when we try to reconcile this $overrides
        // list against the $oldevents list.
        array_unshift($overrides, new stdClass());
    } else {
        // Just do the one override.
        $overrides = array($override);
    }

    if (!empty($review->get_course_module())) {
        $cmid = $review->get_course_module()->id;
    } else {
        $cmid = get_coursemodule_from_instance('review', $reviewinstance->id, $reviewinstance->course)->id;
    }

    foreach ($overrides as $current) {
        $groupid   = isset($current->groupid) ? $current->groupid : 0;
        $userid    = isset($current->userid) ? $current->userid : 0;
        $duedate = isset($current->duedate) ? $current->duedate : $reviewinstance->duedate;

        // Only add 'due' events for an override if they differ from the review default.
        $addclose = empty($current->id) || !empty($current->duedate);

        $event = new stdClass();
        $event->type = CALENDAR_EVENT_TYPE_ACTION;
        $event->description = format_module_intro('review', $reviewinstance, $cmid);
        // Events module won't show user events when the courseid is nonzero.
        $event->courseid    = ($userid) ? 0 : $reviewinstance->course;
        $event->groupid     = $groupid;
        $event->userid      = $userid;
        $event->modulename  = 'review';
        $event->instance    = $reviewinstance->id;
        $event->timestart   = $duedate;
        $event->timeduration = 0;
        $event->timesort    = $event->timestart + $event->timeduration;
        $event->visible     = instance_is_visible('review', $reviewinstance);
        $event->eventtype   = REVIEW_EVENT_TYPE_DUE;
        $event->priority    = null;

        // Determine the event name and priority.
        if ($groupid) {
            // Group override event.
            $params = new stdClass();
            $params->review = $reviewinstance->name;
            $params->group = groups_get_group_name($groupid);
            if ($params->group === false) {
                // Group doesn't exist, just skip it.
                continue;
            }
            $eventname = get_string('overridegroupeventname', 'review', $params);
            // Set group override priority.
            if (isset($current->sortorder)) {
                $event->priority = $current->sortorder;
            }
        } else if ($userid) {
            // User override event.
            $params = new stdClass();
            $params->review = $reviewinstance->name;
            $eventname = get_string('overrideusereventname', 'review', $params);
            // Set user override priority.
            $event->priority = CALENDAR_EVENT_USER_OVERRIDE_PRIORITY;
        } else {
            // The parent event.
            $eventname = $reviewinstance->name;
        }

        if ($duedate && $addclose) {
            if ($oldevent = array_shift($oldevents)) {
                $event->id = $oldevent->id;
            } else {
                unset($event->id);
            }
            $event->name      = $eventname.' ('.get_string('duedate', 'review').')';
            calendar_event::create($event, false);
        }
    }

    // Delete any leftover events.
    foreach ($oldevents as $badevent) {
        $badevent = calendar_event::load($badevent);
        $badevent->delete();
    }
}

/**
 * Return the list if Moodle features this module supports
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 */
function review_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_ADVANCED_GRADING:
            return true;
        case FEATURE_PLAGIARISM:
            return true;
        case FEATURE_COMMENT:
            return true;

        default:
            return null;
    }
}

/**
 * Lists all gradable areas for the advanced grading methods gramework
 *
 * @return array('string'=>'string') An array with area names as keys and descriptions as values
 */
function review_grading_areas_list() {
    return array('submissions'=>get_string('submissions', 'review'));
}


/**
 * extend an assigment navigation settings
 *
 * @param settings_navigation $settings
 * @param navigation_node $navref
 * @return void
 */
function review_extend_settings_navigation(settings_navigation $settings, navigation_node $navref) {
    global $PAGE, $DB;

    // We want to add these new nodes after the Edit settings node, and before the
    // Locally reviewed roles node. Of course, both of those are controlled by capabilities.
    $keys = $navref->get_children_key_list();
    $beforekey = null;
    $i = array_search('modedit', $keys);
    if ($i === false and array_key_exists(0, $keys)) {
        $beforekey = $keys[0];
    } else if (array_key_exists($i + 1, $keys)) {
        $beforekey = $keys[$i + 1];
    }

    $cm = $PAGE->cm;
    if (!$cm) {
        return;
    }

    $context = $cm->context;
    $course = $PAGE->course;

    if (!$course) {
        return;
    }

    if (has_capability('mod/review:manageoverrides', $PAGE->cm->context)) {
        $url = new moodle_url('/mod/review/overrides.php', array('cmid' => $PAGE->cm->id));
        $node = navigation_node::create(get_string('groupoverrides', 'review'),
            new moodle_url($url, array('mode' => 'group')),
            navigation_node::TYPE_SETTING, null, 'mod_review_groupoverrides');
        $navref->add_node($node, $beforekey);

        $node = navigation_node::create(get_string('useroverrides', 'review'),
            new moodle_url($url, array('mode' => 'user')),
            navigation_node::TYPE_SETTING, null, 'mod_review_useroverrides');
        $navref->add_node($node, $beforekey);
    }

    // Link to gradebook.
    if (has_capability('gradereport/grader:view', $cm->context) &&
            has_capability('moodle/grade:viewall', $cm->context)) {
        $link = new moodle_url('/grade/report/grader/index.php', array('id' => $course->id));
        $linkname = get_string('viewgradebook', 'review');
        $node = $navref->add($linkname, $link, navigation_node::TYPE_SETTING);
    }

    // Link to download all submissions.
    if (has_any_capability(array('mod/review:grade', 'mod/review:viewgrades'), $context)) {
        $link = new moodle_url('/mod/review/view.php', array('id' => $cm->id, 'action'=>'grading'));
        $node = $navref->add(get_string('viewgrading', 'review'), $link, navigation_node::TYPE_SETTING);

        $link = new moodle_url('/mod/review/view.php', array('id' => $cm->id, 'action'=>'downloadall'));
        $node = $navref->add(get_string('downloadall', 'review'), $link, navigation_node::TYPE_SETTING);
    }

    if (has_capability('mod/review:revealidentities', $context)) {
        $dbparams = array('id'=>$cm->instance);
        $review = $DB->get_record('review', $dbparams, 'blindmarking, revealidentities');

        if ($review && $review->blindmarking && !$review->revealidentities) {
            $urlparams = array('id' => $cm->id, 'action'=>'revealidentities');
            $url = new moodle_url('/mod/review/view.php', $urlparams);
            $linkname = get_string('revealidentities', 'review');
            $node = $navref->add($linkname, $url, navigation_node::TYPE_SETTING);
        }
    }
}

/**
 * Add a get_coursemodule_info function in case any review type wants to add 'extra' information
 * for the course (see resource).
 *
 * Given a course_module object, this function returns any "extra" information that may be needed
 * when printing this activity in a course listing.  See get_array_of_activities() in course/lib.php.
 *
 * @param stdClass $coursemodule The coursemodule object (record).
 * @return cached_cm_info An object on information that the courses
 *                        will know about (most noticeably, an icon).
 */
function review_get_coursemodule_info($coursemodule) {
    global $CFG, $DB;

    $dbparams = array('id'=>$coursemodule->instance);
    $fields = 'id, name, alwaysshowdescription, allowsubmissionsfromdate, intro, introformat, completionsubmit';
    if (! $review = $DB->get_record('review', $dbparams, $fields)) {
        return false;
    }

    $result = new cached_cm_info();
    $result->name = $review->name;
    if ($coursemodule->showdescription) {
        if ($review->alwaysshowdescription || time() > $review->allowsubmissionsfromdate) {
            // Convert intro to html. Do not filter cached version, filters run at display time.
            $result->content = format_module_intro('review', $review, $coursemodule->id, false);
        }
    }

    // Populate the custom completion rules as key => value pairs, but only if the completion mode is 'automatic'.
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $result->customdata['customcompletionrules']['completionsubmit'] = $review->completionsubmit;
    }

    return $result;
}

/**
 * Callback which returns human-readable strings describing the active completion custom rules for the module instance.
 *
 * @param cm_info|stdClass $cm object with fields ->completion and ->customdata['customcompletionrules']
 * @return array $descriptions the array of descriptions for the custom rules.
 */
function mod_review_get_completion_active_rule_descriptions($cm) {
    // Values will be present in cm_info, and we assume these are up to date.
    if (empty($cm->customdata['customcompletionrules'])
        || $cm->completion != COMPLETION_TRACKING_AUTOMATIC) {
        return [];
    }

    $descriptions = [];
    foreach ($cm->customdata['customcompletionrules'] as $key => $val) {
        switch ($key) {
            case 'completionsubmit':
                if (!empty($val)) {
                    $descriptions[] = get_string('completionsubmit', 'review');
                }
                break;
            default:
                break;
        }
    }
    return $descriptions;
}

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function review_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $modulepagetype = array(
        'mod-review-*' => get_string('page-mod-review-x', 'review'),
        'mod-review-view' => get_string('page-mod-review-view', 'review'),
    );
    return $modulepagetype;
}

/**
 * Print an overview of all reviews
 * for the courses.
 *
 * @deprecated since 3.3
 * @todo The final deprecation of this function will take place in Moodle 3.7 - see MDL-57487.
 * @param mixed $courses The list of courses to print the overview for
 * @param array $htmlarray The array of html to return
 * @return true
 */
function review_print_overview($courses, &$htmlarray) {
    global $CFG, $DB;

    debugging('The function review_print_overview() is now deprecated.', DEBUG_DEVELOPER);

    if (empty($courses) || !is_array($courses) || count($courses) == 0) {
        return true;
    }

    if (!$reviews = get_all_instances_in_courses('review', $courses)) {
        return true;
    }

    $reviewids = array();

    // Do review_base::isopen() here without loading the whole thing for speed.
    foreach ($reviews as $key => $review) {
        $time = time();
        $isopen = false;
        if ($review->duedate) {
            $duedate = false;
            if ($review->cutoffdate) {
                $duedate = $review->cutoffdate;
            }
            if ($duedate) {
                $isopen = ($review->allowsubmissionsfromdate <= $time && $time <= $duedate);
            } else {
                $isopen = ($review->allowsubmissionsfromdate <= $time);
            }
        }
        if ($isopen) {
            $reviewids[] = $review->id;
        }
    }

    if (empty($reviewids)) {
        // No reviews to look at - we're done.
        return true;
    }

    // Definitely something to print, now include the constants we need.
    require_once($CFG->dirroot . '/mod/review/locallib.php');

    $strduedate = get_string('duedate', 'review');
    $strcutoffdate = get_string('nosubmissionsacceptedafter', 'review');
    $strnolatesubmissions = get_string('nolatesubmissions', 'review');
    $strduedateno = get_string('duedateno', 'review');
    $strreview = get_string('modulename', 'review');

    // We do all possible database work here *outside* of the loop to ensure this scales.
    list($sqlreviewids, $reviewidparams) = $DB->get_in_or_equal($reviewids);

    $mysubmissions = null;
    $unmarkedsubmissions = null;

    foreach ($reviews as $review) {

        // Do not show reviews that are not open.
        if (!in_array($review->id, $reviewids)) {
            continue;
        }

        $context = context_module::instance($review->coursemodule);

        // Does the submission status of the review require notification?
        if (has_capability('mod/review:submit', $context, null, false)) {
            // Does the submission status of the review require notification?
            $submitdetails = review_get_mysubmission_details_for_print_overview($mysubmissions, $sqlreviewids,
                    $reviewidparams, $review);
        } else {
            $submitdetails = false;
        }

        if (has_capability('mod/review:grade', $context, null, false)) {
            // Does the grading status of the review require notification ?
            $gradedetails = review_get_grade_details_for_print_overview($unmarkedsubmissions, $sqlreviewids,
                    $reviewidparams, $review, $context);
        } else {
            $gradedetails = false;
        }

        if (empty($submitdetails) && empty($gradedetails)) {
            // There is no need to display this review as there is nothing to notify.
            continue;
        }

        $dimmedclass = '';
        if (!$review->visible) {
            $dimmedclass = ' class="dimmed"';
        }
        $href = $CFG->wwwroot . '/mod/review/view.php?id=' . $review->coursemodule;
        $basestr = '<div class="review overview">' .
               '<div class="name">' .
               $strreview . ': '.
               '<a ' . $dimmedclass .
                   'title="' . $strreview . '" ' .
                   'href="' . $href . '">' .
               format_string($review->name) .
               '</a></div>';
        if ($review->duedate) {
            $userdate = userdate($review->duedate);
            $basestr .= '<div class="info">' . $strduedate . ': ' . $userdate . '</div>';
        } else {
            $basestr .= '<div class="info">' . $strduedateno . '</div>';
        }
        if ($review->cutoffdate) {
            if ($review->cutoffdate == $review->duedate) {
                $basestr .= '<div class="info">' . $strnolatesubmissions . '</div>';
            } else {
                $userdate = userdate($review->cutoffdate);
                $basestr .= '<div class="info">' . $strcutoffdate . ': ' . $userdate . '</div>';
            }
        }

        // Show only relevant information.
        if (!empty($submitdetails)) {
            $basestr .= $submitdetails;
        }

        if (!empty($gradedetails)) {
            $basestr .= $gradedetails;
        }
        $basestr .= '</div>';

        if (empty($htmlarray[$review->course]['review'])) {
            $htmlarray[$review->course]['review'] = $basestr;
        } else {
            $htmlarray[$review->course]['review'] .= $basestr;
        }
    }
    return true;
}

/**
 * This api generates html to be displayed to students in print overview section, related to their submission status of the given
 * review.
 *
 * @deprecated since 3.3
 * @todo The final deprecation of this function will take place in Moodle 3.7 - see MDL-57487.
 * @param array $mysubmissions list of submissions of current user indexed by review id.
 * @param string $sqlreviewids sql clause used to filter open reviews.
 * @param array $reviewidparams sql params used to filter open reviews.
 * @param stdClass $review current review
 *
 * @return bool|string html to display , false if nothing needs to be displayed.
 * @throws coding_exception
 */
function review_get_mysubmission_details_for_print_overview(&$mysubmissions, $sqlreviewids, $reviewidparams,
                                                            $review) {
    global $USER, $DB;

    debugging('The function review_get_mysubmission_details_for_print_overview() is now deprecated.', DEBUG_DEVELOPER);

    if ($review->nosubmissions) {
        // Offline review. No need to display alerts for offline reviews.
        return false;
    }

    $strnotsubmittedyet = get_string('notsubmittedyet', 'review');

    if (!isset($mysubmissions)) {

        // Get all user submissions, indexed by review id.
        $dbparams = array_merge(array($USER->id), $reviewidparams, array($USER->id));
        $mysubmissions = $DB->get_records_sql('SELECT a.id AS review,
                                                      a.nosubmissions AS nosubmissions,
                                                      g.timemodified AS timemarked,
                                                      g.grader AS grader,
                                                      g.grade AS grade,
                                                      s.status AS status
                                                 FROM {review} a, {review_submission} s
                                            LEFT JOIN {review_grades} g ON
                                                      g.review = s.review AND
                                                      g.userid = ? AND
                                                      g.attemptnumber = s.attemptnumber
                                                WHERE a.id ' . $sqlreviewids . ' AND
                                                      s.latest = 1 AND
                                                      s.review = a.id AND
                                                      s.userid = ?', $dbparams);
    }

    $submitdetails = '';
    $submitdetails .= '<div class="details">';
    $submitdetails .= get_string('mysubmission', 'review');
    $submission = false;

    if (isset($mysubmissions[$review->id])) {
        $submission = $mysubmissions[$review->id];
    }

    if ($submission && $submission->status == REVIEW_SUBMISSION_STATUS_SUBMITTED) {
        // A valid submission already exists, no need to notify students about this.
        return false;
    }

    // We need to show details only if a valid submission doesn't exist.
    if (!$submission ||
        !$submission->status ||
        $submission->status == REVIEW_SUBMISSION_STATUS_DRAFT ||
        $submission->status == REVIEW_SUBMISSION_STATUS_NEW
    ) {
        $submitdetails .= $strnotsubmittedyet;
    } else {
        $submitdetails .= get_string('submissionstatus_' . $submission->status, 'review');
    }
    if ($review->markingworkflow) {
        $workflowstate = $DB->get_field('review_user_flags', 'workflowstate', array('review' =>
                $review->id, 'userid' => $USER->id));
        if ($workflowstate) {
            $gradingstatus = 'markingworkflowstate' . $workflowstate;
        } else {
            $gradingstatus = 'markingworkflowstate' . REVIEW_MARKING_WORKFLOW_STATE_NOTMARKED;
        }
    } else if (!empty($submission->grade) && $submission->grade !== null && $submission->grade >= 0) {
        $gradingstatus = REVIEW_GRADING_STATUS_GRADED;
    } else {
        $gradingstatus = REVIEW_GRADING_STATUS_NOT_GRADED;
    }
    $submitdetails .= ', ' . get_string($gradingstatus, 'review');
    $submitdetails .= '</div>';
    return $submitdetails;
}

/**
 * This api generates html to be displayed to teachers in print overview section, related to the grading status of the given
 * review's submissions.
 *
 * @deprecated since 3.3
 * @todo The final deprecation of this function will take place in Moodle 3.7 - see MDL-57487.
 * @param array $unmarkedsubmissions list of submissions of that are currently unmarked indexed by review id.
 * @param string $sqlreviewids sql clause used to filter open reviews.
 * @param array $reviewidparams sql params used to filter open reviews.
 * @param stdClass $review current review
 * @param context $context context of the review.
 *
 * @return bool|string html to display , false if nothing needs to be displayed.
 * @throws coding_exception
 */
function review_get_grade_details_for_print_overview(&$unmarkedsubmissions, $sqlreviewids, $reviewidparams,
                                                     $review, $context) {
    global $DB;

    debugging('The function review_get_grade_details_for_print_overview() is now deprecated.', DEBUG_DEVELOPER);

    if (!isset($unmarkedsubmissions)) {
        // Build up and array of unmarked submissions indexed by review id/ userid
        // for use where the user has grading rights on review.
        $dbparams = array_merge(array(REVIEW_SUBMISSION_STATUS_SUBMITTED), $reviewidparams);
        $rs = $DB->get_recordset_sql('SELECT s.review as review,
                                             s.userid as userid,
                                             s.id as id,
                                             s.status as status,
                                             g.timemodified as timegraded
                                        FROM {review_submission} s
                                   LEFT JOIN {review_grades} g ON
                                             s.userid = g.userid AND
                                             s.review = g.review AND
                                             g.attemptnumber = s.attemptnumber
                                   LEFT JOIN {review} a ON
                                             a.id = s.review
                                       WHERE
                                             ( g.timemodified is NULL OR
                                             s.timemodified >= g.timemodified OR
                                             g.grade IS NULL OR
                                             (g.grade = -1 AND
                                             a.grade < 0)) AND
                                             s.timemodified IS NOT NULL AND
                                             s.status = ? AND
                                             s.latest = 1 AND
                                             s.review ' . $sqlreviewids, $dbparams);

        $unmarkedsubmissions = array();
        foreach ($rs as $rd) {
            $unmarkedsubmissions[$rd->review][$rd->userid] = $rd->id;
        }
        $rs->close();
    }

    // Count how many people can submit.
    $submissions = 0;
    if ($students = get_enrolled_users($context, 'mod/review:view', 0, 'u.id')) {
        foreach ($students as $student) {
            if (isset($unmarkedsubmissions[$review->id][$student->id])) {
                $submissions++;
            }
        }
    }

    if ($submissions) {
        $urlparams = array('id' => $review->coursemodule, 'action' => 'grading');
        $url = new moodle_url('/mod/review/view.php', $urlparams);
        $gradedetails = '<div class="details">' .
                '<a href="' . $url . '">' .
                get_string('submissionsnotgraded', 'review', $submissions) .
                '</a></div>';
        return $gradedetails;
    } else {
        return false;
    }

}

/**
 * Print recent activity from all reviews in a given course
 *
 * This is used by the recent activity block
 * @param mixed $course the course to print activity for
 * @param bool $viewfullnames boolean to determine whether to show full names or not
 * @param int $timestart the time the rendering started
 * @return bool true if activity was printed, false otherwise.
 */
function review_print_recent_activity($course, $viewfullnames, $timestart) {
    global $CFG, $USER, $DB, $OUTPUT;
    require_once($CFG->dirroot . '/mod/review/locallib.php');

    // Do not use log table if possible, it may be huge.

    $dbparams = array($timestart, $course->id, 'review', REVIEW_SUBMISSION_STATUS_SUBMITTED);
    $namefields = user_picture::fields('u', null, 'userid');
    if (!$submissions = $DB->get_records_sql("SELECT asb.id, asb.timemodified, cm.id AS cmid, um.id as recordid,
                                                     $namefields
                                                FROM {review_submission} asb
                                                     JOIN {review} a      ON a.id = asb.review
                                                     JOIN {course_modules} cm ON cm.instance = a.id
                                                     JOIN {modules} md        ON md.id = cm.module
                                                     JOIN {user} u            ON u.id = asb.userid
                                                LEFT JOIN {review_user_mapping} um ON um.userid = u.id AND um.review = a.id
                                               WHERE asb.timemodified > ? AND
                                                     asb.latest = 1 AND
                                                     a.course = ? AND
                                                     md.name = ? AND
                                                     asb.status = ?
                                            ORDER BY asb.timemodified ASC", $dbparams)) {
         return false;
    }

    $modinfo = get_fast_modinfo($course);
    $show    = array();
    $grader  = array();

    $showrecentsubmissions = get_config('review', 'showrecentsubmissions');

    foreach ($submissions as $submission) {
        if (!array_key_exists($submission->cmid, $modinfo->get_cms())) {
            continue;
        }
        $cm = $modinfo->get_cm($submission->cmid);
        if (!$cm->uservisible) {
            continue;
        }
        if ($submission->userid == $USER->id) {
            $show[] = $submission;
            continue;
        }

        $context = context_module::instance($submission->cmid);
        // The act of submitting of review may be considered private -
        // only graders will see it if specified.
        if (empty($showrecentsubmissions)) {
            if (!array_key_exists($cm->id, $grader)) {
                $grader[$cm->id] = has_capability('moodle/grade:viewall', $context);
            }
            if (!$grader[$cm->id]) {
                continue;
            }
        }

        $groupmode = groups_get_activity_groupmode($cm, $course);

        if ($groupmode == SEPARATEGROUPS &&
                !has_capability('moodle/site:accessallgroups',  $context)) {
            if (isguestuser()) {
                // Shortcut - guest user does not belong into any group.
                continue;
            }

            // This will be slow - show only users that share group with me in this cm.
            if (!$modinfo->get_groups($cm->groupingid)) {
                continue;
            }
            $usersgroups =  groups_get_all_groups($course->id, $submission->userid, $cm->groupingid);
            if (is_array($usersgroups)) {
                $usersgroups = array_keys($usersgroups);
                $intersect = array_intersect($usersgroups, $modinfo->get_groups($cm->groupingid));
                if (empty($intersect)) {
                    continue;
                }
            }
        }
        $show[] = $submission;
    }

    if (empty($show)) {
        return false;
    }

    echo $OUTPUT->heading(get_string('newsubmissions', 'review').':', 3);

    foreach ($show as $submission) {
        $cm = $modinfo->get_cm($submission->cmid);
        $context = context_module::instance($submission->cmid);
        $review = new review($context, $cm, $cm->course);
        $link = $CFG->wwwroot.'/mod/review/view.php?id='.$cm->id;
        // Obscure first and last name if blind marking enabled.
        if ($review->is_blind_marking()) {
            $submission->firstname = get_string('participant', 'mod_review');
            if (empty($submission->recordid)) {
                $submission->recordid = $review->get_uniqueid_for_user($submission->userid);
            }
            $submission->lastname = $submission->recordid;
        }
        print_recent_activity_note($submission->timemodified,
                                   $submission,
                                   $cm->name,
                                   $link,
                                   false,
                                   $viewfullnames);
    }

    return true;
}

/**
 * Returns all reviews since a given time.
 *
 * @param array $activities The activity information is returned in this array
 * @param int $index The current index in the activities array
 * @param int $timestart The earliest activity to show
 * @param int $courseid Limit the search to this course
 * @param int $cmid The course module id
 * @param int $userid Optional user id
 * @param int $groupid Optional group id
 * @return void
 */
function review_get_recent_mod_activity(&$activities,
                                        &$index,
                                        $timestart,
                                        $courseid,
                                        $cmid,
                                        $userid=0,
                                        $groupid=0) {
    global $CFG, $COURSE, $USER, $DB;

    require_once($CFG->dirroot . '/mod/review/locallib.php');

    if ($COURSE->id == $courseid) {
        $course = $COURSE;
    } else {
        $course = $DB->get_record('course', array('id'=>$courseid));
    }

    $modinfo = get_fast_modinfo($course);

    $cm = $modinfo->get_cm($cmid);
    $params = array();
    if ($userid) {
        $userselect = 'AND u.id = :userid';
        $params['userid'] = $userid;
    } else {
        $userselect = '';
    }

    if ($groupid) {
        $groupselect = 'AND gm.groupid = :groupid';
        $groupjoin   = 'JOIN {groups_members} gm ON  gm.userid=u.id';
        $params['groupid'] = $groupid;
    } else {
        $groupselect = '';
        $groupjoin   = '';
    }

    $params['cminstance'] = $cm->instance;
    $params['timestart'] = $timestart;
    $params['submitted'] = REVIEW_SUBMISSION_STATUS_SUBMITTED;

    $userfields = user_picture::fields('u', null, 'userid');

    if (!$submissions = $DB->get_records_sql('SELECT asb.id, asb.timemodified, ' .
                                                     $userfields .
                                             '  FROM {review_submission} asb
                                                JOIN {review} a ON a.id = asb.review
                                                JOIN {user} u ON u.id = asb.userid ' .
                                          $groupjoin .
                                            '  WHERE asb.timemodified > :timestart AND
                                                     asb.status = :submitted AND
                                                     a.id = :cminstance
                                                     ' . $userselect . ' ' . $groupselect .
                                            ' ORDER BY asb.timemodified ASC', $params)) {
         return;
    }

    $groupmode       = groups_get_activity_groupmode($cm, $course);
    $cmcontext      = context_module::instance($cm->id);
    $grader          = has_capability('moodle/grade:viewall', $cmcontext);
    $accessallgroups = has_capability('moodle/site:accessallgroups', $cmcontext);
    $viewfullnames   = has_capability('moodle/site:viewfullnames', $cmcontext);


    $showrecentsubmissions = get_config('review', 'showrecentsubmissions');
    $show = array();
    foreach ($submissions as $submission) {
        if ($submission->userid == $USER->id) {
            $show[] = $submission;
            continue;
        }
        // The act of submitting of review may be considered private -
        // only graders will see it if specified.
        if (empty($showrecentsubmissions)) {
            if (!$grader) {
                continue;
            }
        }

        if ($groupmode == SEPARATEGROUPS and !$accessallgroups) {
            if (isguestuser()) {
                // Shortcut - guest user does not belong into any group.
                continue;
            }

            // This will be slow - show only users that share group with me in this cm.
            if (!$modinfo->get_groups($cm->groupingid)) {
                continue;
            }
            $usersgroups =  groups_get_all_groups($course->id, $submission->userid, $cm->groupingid);
            if (is_array($usersgroups)) {
                $usersgroups = array_keys($usersgroups);
                $intersect = array_intersect($usersgroups, $modinfo->get_groups($cm->groupingid));
                if (empty($intersect)) {
                    continue;
                }
            }
        }
        $show[] = $submission;
    }

    if (empty($show)) {
        return;
    }

    if ($grader) {
        require_once($CFG->libdir.'/gradelib.php');
        $userids = array();
        foreach ($show as $id => $submission) {
            $userids[] = $submission->userid;
        }
        $grades = grade_get_grades($courseid, 'mod', 'review', $cm->instance, $userids);
    }

    $aname = format_string($cm->name, true);
    foreach ($show as $submission) {
        $activity = new stdClass();

        $activity->type         = 'review';
        $activity->cmid         = $cm->id;
        $activity->name         = $aname;
        $activity->sectionnum   = $cm->sectionnum;
        $activity->timestamp    = $submission->timemodified;
        $activity->user         = new stdClass();
        if ($grader) {
            $activity->grade = $grades->items[0]->grades[$submission->userid]->str_long_grade;
        }

        $userfields = explode(',', user_picture::fields());
        foreach ($userfields as $userfield) {
            if ($userfield == 'id') {
                // Aliased in SQL above.
                $activity->user->{$userfield} = $submission->userid;
            } else {
                $activity->user->{$userfield} = $submission->{$userfield};
            }
        }
        $activity->user->fullname = fullname($submission, $viewfullnames);

        $activities[$index++] = $activity;
    }

    return;
}

/**
 * Print recent activity from all reviews in a given course
 *
 * This is used by course/recent.php
 * @param stdClass $activity
 * @param int $courseid
 * @param bool $detail
 * @param array $modnames
 */
function review_print_recent_mod_activity($activity, $courseid, $detail, $modnames) {
    global $CFG, $OUTPUT;

    echo '<table border="0" cellpadding="3" cellspacing="0" class="review-recent">';

    echo '<tr><td class="userpicture" valign="top">';
    echo $OUTPUT->user_picture($activity->user);
    echo '</td><td>';

    if ($detail) {
        $modname = $modnames[$activity->type];
        echo '<div class="title">';
        echo $OUTPUT->image_icon('icon', $modname, 'review');
        echo '<a href="' . $CFG->wwwroot . '/mod/review/view.php?id=' . $activity->cmid . '">';
        echo $activity->name;
        echo '</a>';
        echo '</div>';
    }

    if (isset($activity->grade)) {
        echo '<div class="grade">';
        echo get_string('grade').': ';
        echo $activity->grade;
        echo '</div>';
    }

    echo '<div class="user">';
    echo "<a href=\"$CFG->wwwroot/user/view.php?id={$activity->user->id}&amp;course=$courseid\">";
    echo "{$activity->user->fullname}</a>  - " . userdate($activity->timestamp);
    echo '</div>';

    echo '</td></tr></table>';
}

/**
 * Checks if a scale is being used by an review.
 *
 * This is used by the backup code to decide whether to back up a scale
 * @param int $reviewid
 * @param int $scaleid
 * @return boolean True if the scale is used by the review
 */
function review_scale_used($reviewid, $scaleid) {
    global $DB;

    $return = false;
    $rec = $DB->get_record('review', array('id'=>$reviewid, 'grade'=>-$scaleid));

    if (!empty($rec) && !empty($scaleid)) {
        $return = true;
    }

    return $return;
}

/**
 * Checks if scale is being used by any instance of review
 *
 * This is used to find out if scale used anywhere
 * @param int $scaleid
 * @return boolean True if the scale is used by any review
 */
function review_scale_used_anywhere($scaleid) {
    global $DB;

    if ($scaleid and $DB->record_exists('review', array('grade'=>-$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * List the actions that correspond to a view of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = 'r' and edulevel = LEVEL_PARTICIPATING will
 *       be considered as view action.
 *
 * @return array
 */
function review_get_view_actions() {
    return array('view submission', 'view feedback');
}

/**
 * List the actions that correspond to a post of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = ('c' || 'u' || 'd') and edulevel = LEVEL_PARTICIPATING
 *       will be considered as post action.
 *
 * @return array
 */
function review_get_post_actions() {
    return array('upload', 'submit', 'submit for grading');
}

/**
 * Returns all other capabilities used by this module.
 * @return array Array of capability strings
 */
function review_get_extra_capabilities() {
    return ['gradereport/grader:view', 'moodle/grade:viewall'];
}

/**
 * Create grade item for given review.
 *
 * @param stdClass $review record with extra cmidnumber
 * @param array $grades optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise
 */
function review_grade_item_update($review, $grades=null) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    if (!isset($review->courseid)) {
        $review->courseid = $review->course;
    }

    $params = array('itemname'=>$review->name, 'idnumber'=>$review->cmidnumber);

    // Check if feedback plugin for gradebook is enabled, if yes then
    // gradetype = GRADE_TYPE_TEXT else GRADE_TYPE_NONE.
    $gradefeedbackenabled = false;

    if (isset($review->gradefeedbackenabled)) {
        $gradefeedbackenabled = $review->gradefeedbackenabled;
    } else if ($review->grade == 0) { // Grade feedback is needed only when grade == 0.
        require_once($CFG->dirroot . '/mod/review/locallib.php');
        $mod = get_coursemodule_from_instance('review', $review->id, $review->courseid);
        $cm = context_module::instance($mod->id);
        $review = new review($cm, null, null);
        $gradefeedbackenabled = $review->is_gradebook_feedback_enabled();
    }

    if ($review->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $review->grade;
        $params['grademin']  = 0;

    } else if ($review->grade < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid']   = -$review->grade;

    } else if ($gradefeedbackenabled) {
        // $review->grade == 0 and feedback enabled.
        $params['gradetype'] = GRADE_TYPE_TEXT;
    } else {
        // $review->grade == 0 and no feedback enabled.
        $params['gradetype'] = GRADE_TYPE_NONE;
    }

    if ($grades  === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    return grade_update('mod/review',
                        $review->courseid,
                        'mod',
                        'review',
                        $review->id,
                        0,
                        $grades,
                        $params);
}

/**
 * Return grade for given user or all users.
 *
 * @param stdClass $review record of review with an additional cmidnumber
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none
 */
function review_get_user_grades($review, $userid=0) {
    global $CFG;

    require_once($CFG->dirroot . '/mod/review/locallib.php');

    $cm = get_coursemodule_from_instance('review', $review->id, 0, false, MUST_EXIST);
    $context = context_module::instance($cm->id);
    $review = new review($context, null, null);
    $review->set_instance($review);
    return $review->get_user_grades_for_gradebook($userid);
}

/**
 * Update activity grades.
 *
 * @param stdClass $review database record
 * @param int $userid specific user only, 0 means all
 * @param bool $nullifnone - not used
 */
function review_update_grades($review, $userid=0, $nullifnone=true) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    if ($review->grade == 0) {
        review_grade_item_update($review);

    } else if ($grades = review_get_user_grades($review, $userid)) {
        foreach ($grades as $k => $v) {
            if ($v->rawgrade == -1) {
                $grades[$k]->rawgrade = null;
            }
        }
        review_grade_item_update($review, $grades);

    } else {
        review_grade_item_update($review);
    }
}

/**
 * List the file areas that can be browsed.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @return array
 */
function review_get_file_areas($course, $cm, $context) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/review/locallib.php');

    $areas = array(REVIEW_INTROATTACHMENT_FILEAREA => get_string('introattachments', 'mod_review'));

    $review = new review($context, $cm, $course);
    foreach ($review->get_submission_plugins() as $plugin) {
        if ($plugin->is_visible()) {
            $pluginareas = $plugin->get_file_areas();

            if ($pluginareas) {
                $areas = array_merge($areas, $pluginareas);
            }
        }
    }
    foreach ($review->get_feedback_plugins() as $plugin) {
        if ($plugin->is_visible()) {
            $pluginareas = $plugin->get_file_areas();

            if ($pluginareas) {
                $areas = array_merge($areas, $pluginareas);
            }
        }
    }

    return $areas;
}

/**
 * File browsing support for review module.
 *
 * @param file_browser $browser
 * @param object $areas
 * @param object $course
 * @param object $cm
 * @param object $context
 * @param string $filearea
 * @param int $itemid
 * @param string $filepath
 * @param string $filename
 * @return object file_info instance or null if not found
 */
function review_get_file_info($browser,
                              $areas,
                              $course,
                              $cm,
                              $context,
                              $filearea,
                              $itemid,
                              $filepath,
                              $filename) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/review/locallib.php');

    if ($context->contextlevel != CONTEXT_MODULE) {
        return null;
    }

    $urlbase = $CFG->wwwroot.'/pluginfile.php';
    $fs = get_file_storage();
    $filepath = is_null($filepath) ? '/' : $filepath;
    $filename = is_null($filename) ? '.' : $filename;

    // Need to find where this belongs to.
    $review = new review($context, $cm, $course);
    if ($filearea === REVIEW_INTROATTACHMENT_FILEAREA) {
        if (!has_capability('moodle/course:managefiles', $context)) {
            // Students can not peak here!
            return null;
        }
        if (!($storedfile = $fs->get_file($review->get_context()->id,
                                          'mod_review', $filearea, 0, $filepath, $filename))) {
            return null;
        }
        return new file_info_stored($browser,
                        $review->get_context(),
                        $storedfile,
                        $urlbase,
                        $filearea,
                        $itemid,
                        true,
                        true,
                        false);
    }

    $pluginowner = null;
    foreach ($review->get_submission_plugins() as $plugin) {
        if ($plugin->is_visible()) {
            $pluginareas = $plugin->get_file_areas();

            if (array_key_exists($filearea, $pluginareas)) {
                $pluginowner = $plugin;
                break;
            }
        }
    }
    if (!$pluginowner) {
        foreach ($review->get_feedback_plugins() as $plugin) {
            if ($plugin->is_visible()) {
                $pluginareas = $plugin->get_file_areas();

                if (array_key_exists($filearea, $pluginareas)) {
                    $pluginowner = $plugin;
                    break;
                }
            }
        }
    }

    if (!$pluginowner) {
        return null;
    }

    $result = $pluginowner->get_file_info($browser, $filearea, $itemid, $filepath, $filename);
    return $result;
}

/**
 * Prints the complete info about a user's interaction with an review.
 *
 * @param stdClass $course
 * @param stdClass $user
 * @param stdClass $coursemodule
 * @param stdClass $review the database review record
 *
 * This prints the submission summary and feedback summary for this student.
 */
function review_user_complete($course, $user, $coursemodule, $review) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/review/locallib.php');

    $context = context_module::instance($coursemodule->id);

    $review = new review($context, $coursemodule, $course);

    echo $review->view_student_summary($user, false);
}

/**
 * Rescale all grades for this activity and push the new grades to the gradebook.
 *
 * @param stdClass $course Course db record
 * @param stdClass $cm Course module db record
 * @param float $oldmin
 * @param float $oldmax
 * @param float $newmin
 * @param float $newmax
 */
function review_rescale_activity_grades($course, $cm, $oldmin, $oldmax, $newmin, $newmax) {
    global $DB;

    if ($oldmax <= $oldmin) {
        // Grades cannot be scaled.
        return false;
    }
    $scale = ($newmax - $newmin) / ($oldmax - $oldmin);
    if (($newmax - $newmin) <= 1) {
        // We would lose too much precision, lets bail.
        return false;
    }

    $params = array(
        'p1' => $oldmin,
        'p2' => $scale,
        'p3' => $newmin,
        'a' => $cm->instance
    );

    // Only rescale grades that are greater than or equal to 0. Anything else is a special value.
    $sql = 'UPDATE {review_grades} set grade = (((grade - :p1) * :p2) + :p3) where review = :a and grade >= 0';
    $dbupdate = $DB->execute($sql, $params);
    if (!$dbupdate) {
        return false;
    }

    // Now re-push all grades to the gradebook.
    $dbparams = array('id' => $cm->instance);
    $review = $DB->get_record('review', $dbparams);
    $review->cmidnumber = $cm->idnumber;

    review_update_grades($review);

    return true;
}

/**
 * Print the grade information for the review for this user.
 *
 * @param stdClass $course
 * @param stdClass $user
 * @param stdClass $coursemodule
 * @param stdClass $review
 */
function review_user_outline($course, $user, $coursemodule, $review) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');
    require_once($CFG->dirroot.'/grade/grading/lib.php');

    $gradinginfo = grade_get_grades($course->id,
                                        'mod',
                                        'review',
                                        $review->id,
                                        $user->id);

    $gradingitem = $gradinginfo->items[0];
    $gradebookgrade = $gradingitem->grades[$user->id];

    if (empty($gradebookgrade->str_long_grade)) {
        return null;
    }
    $result = new stdClass();
    if (!$gradingitem->hidden || has_capability('moodle/grade:viewhidden', context_course::instance($course->id))) {
        $result->info = get_string('outlinegrade', 'review', $gradebookgrade->str_long_grade);
    } else {
        $result->info = get_string('grade') . ': ' . get_string('hidden', 'grades');
    }
    $result->time = $gradebookgrade->dategraded;

    return $result;
}

/**
 * Obtains the automatic completion state for this module based on any conditions
 * in review settings.
 *
 * @param object $course Course
 * @param object $cm Course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not, $type if conditions not set.
 */
function review_get_completion_state($course, $cm, $userid, $type) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/review/locallib.php');

    $review = new review(null, $cm, $course);

    // If completion option is enabled, evaluate it and return true/false.
    if ($review->get_instance()->completionsubmit) {
        if ($review->get_instance()->teamsubmission) {
            $submission = $review->get_group_submission($userid, 0, false);
        } else {
            $submission = $review->get_user_submission($userid, false);
        }
        return $submission && $submission->status == REVIEW_SUBMISSION_STATUS_SUBMITTED;
    } else {
        // Completion option is not enabled so just return $type.
        return $type;
    }
}

/**
 * Serves intro attachment files.
 *
 * @param mixed $course course or id of the course
 * @param mixed $cm course module or id of the course module
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - just send the file
 */
function review_pluginfile($course,
                $cm,
                context $context,
                $filearea,
                $args,
                $forcedownload,
                array $options=array()) {
    global $CFG;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_login($course, false, $cm);
    if (!has_capability('mod/review:view', $context)) {
        return false;
    }

    require_once($CFG->dirroot . '/mod/review/locallib.php');
    $review = new review($context, $cm, $course);

    if ($filearea !== REVIEW_INTROATTACHMENT_FILEAREA) {
        return false;
    }
    if (!$review->show_intro()) {
        return false;
    }

    $itemid = (int)array_shift($args);
    if ($itemid != 0) {
        return false;
    }

    $relativepath = implode('/', $args);

    $fullpath = "/{$context->id}/mod_review/$filearea/$itemid/$relativepath";

    $fs = get_file_storage();
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, $forcedownload, $options);
}

/**
 * Serve the grading panel as a fragment.
 *
 * @param array $args List of named arguments for the fragment loader.
 * @return string
 */
function mod_review_output_fragment_gradingpanel($args) {
    global $CFG;

    $context = $args['context'];

    if ($context->contextlevel != CONTEXT_MODULE) {
        return null;
    }
    require_once($CFG->dirroot . '/mod/review/locallib.php');
    $review = new review($context, null, null);

    $userid = clean_param($args['userid'], PARAM_INT);
    $attemptnumber = clean_param($args['attemptnumber'], PARAM_INT);
    $formdata = array();
    if (!empty($args['jsonformdata'])) {
        $serialiseddata = json_decode($args['jsonformdata']);
        parse_str($serialiseddata, $formdata);
    }
    $viewargs = array(
        'userid' => $userid,
        'attemptnumber' => $attemptnumber,
        'formdata' => $formdata
    );

    return $review->view('gradingpanel', $viewargs);
}

/**
 * Check if the module has any update that affects the current user since a given time.
 *
 * @param  cm_info $cm course module data
 * @param  int $from the time to check updates from
 * @param  array $filter  if we need to check only specific updates
 * @return stdClass an object with the different type of areas indicating if they were updated or not
 * @since Moodle 3.2
 */
function review_check_updates_since(cm_info $cm, $from, $filter = array()) {
    global $DB, $USER, $CFG;
    require_once($CFG->dirroot . '/mod/review/locallib.php');

    $updates = new stdClass();
    $updates = course_check_module_updates_since($cm, $from, array(REVIEW_INTROATTACHMENT_FILEAREA), $filter);

    // Check if there is a new submission by the user or new grades.
    $select = 'review = :id AND userid = :userid AND (timecreated > :since1 OR timemodified > :since2)';
    $params = array('id' => $cm->instance, 'userid' => $USER->id, 'since1' => $from, 'since2' => $from);
    $updates->submissions = (object) array('updated' => false);
    $submissions = $DB->get_records_select('review_submission', $select, $params, '', 'id');
    if (!empty($submissions)) {
        $updates->submissions->updated = true;
        $updates->submissions->itemids = array_keys($submissions);
    }

    $updates->grades = (object) array('updated' => false);
    $grades = $DB->get_records_select('review_grades', $select, $params, '', 'id');
    if (!empty($grades)) {
        $updates->grades->updated = true;
        $updates->grades->itemids = array_keys($grades);
    }

    // Now, teachers should see other students updates.
    if (has_capability('mod/review:viewgrades', $cm->context)) {
        $params = array('id' => $cm->instance, 'since1' => $from, 'since2' => $from);
        $select = 'review = :id AND (timecreated > :since1 OR timemodified > :since2)';

        if (groups_get_activity_groupmode($cm) == SEPARATEGROUPS) {
            $groupusers = array_keys(groups_get_activity_shared_group_members($cm));
            if (empty($groupusers)) {
                return $updates;
            }
            list($insql, $inparams) = $DB->get_in_or_equal($groupusers, SQL_PARAMS_NAMED);
            $select .= ' AND userid ' . $insql;
            $params = array_merge($params, $inparams);
        }

        $updates->usersubmissions = (object) array('updated' => false);
        $submissions = $DB->get_records_select('review_submission', $select, $params, '', 'id');
        if (!empty($submissions)) {
            $updates->usersubmissions->updated = true;
            $updates->usersubmissions->itemids = array_keys($submissions);
        }

        $updates->usergrades = (object) array('updated' => false);
        $grades = $DB->get_records_select('review_grades', $select, $params, '', 'id');
        if (!empty($grades)) {
            $updates->usergrades->updated = true;
            $updates->usergrades->itemids = array_keys($grades);
        }
    }

    return $updates;
}

/**
 * Is the event visible?
 *
 * This is used to determine global visibility of an event in all places throughout Moodle. For example,
 * the REVIEW_EVENT_TYPE_GRADINGDUE event will not be shown to students on their calendar.
 *
 * @param calendar_event $event
 * @param int $userid User id to use for all capability checks, etc. Set to 0 for current user (default).
 * @return bool Returns true if the event is visible to the current user, false otherwise.
 */
function mod_review_core_calendar_is_event_visible(calendar_event $event, $userid = 0) {
    global $CFG, $USER;

    require_once($CFG->dirroot . '/mod/review/locallib.php');

    if (empty($userid)) {
        $userid = $USER->id;
    }

    $cm = get_fast_modinfo($event->courseid, $userid)->instances['review'][$event->instance];
    $context = context_module::instance($cm->id);

    $review = new review($context, $cm, null);

    if ($event->eventtype == REVIEW_EVENT_TYPE_GRADINGDUE) {
        return $review->can_grade($userid);
    } else {
        return true;
    }
}

/**
 * This function receives a calendar event and returns the action associated with it, or null if there is none.
 *
 * This is used by block_myoverview in order to display the event appropriately. If null is returned then the event
 * is not displayed on the block.
 *
 * @param calendar_event $event
 * @param \core_calendar\action_factory $factory
 * @param int $userid User id to use for all capability checks, etc. Set to 0 for current user (default).
 * @return \core_calendar\local\event\entities\action_interface|null
 */
function mod_review_core_calendar_provide_event_action(calendar_event $event,
                                                       \core_calendar\action_factory $factory,
                                                       $userid = 0) {

    global $CFG, $USER;

    require_once($CFG->dirroot . '/mod/review/locallib.php');

    if (empty($userid)) {
        $userid = $USER->id;
    }

    $cm = get_fast_modinfo($event->courseid, $userid)->instances['review'][$event->instance];
    $context = context_module::instance($cm->id);

    $review = new review($context, $cm, null);

    // Apply overrides.
    $review->update_effective_access($userid);

    if ($event->eventtype == REVIEW_EVENT_TYPE_GRADINGDUE) {
        $name = get_string('grade');
        $url = new \moodle_url('/mod/review/view.php', [
            'id' => $cm->id,
            'action' => 'grader'
        ]);
        $itemcount = $review->count_submissions_need_grading();
        $actionable = $review->can_grade($userid) && (time() >= $review->get_instance()->allowsubmissionsfromdate);
    } else {
        $usersubmission = $review->get_user_submission($userid, false);
        if ($usersubmission && $usersubmission->status === REVIEW_SUBMISSION_STATUS_SUBMITTED) {
            // The user has already submitted.
            // We do not want to change the text to edit the submission, we want to remove the event from the Dashboard entirely.
            return null;
        }

        $participant = $review->get_participant($userid);

        if (!$participant) {
            // If the user is not a participant in the review then they have
            // no action to take. This will filter out the events for teachers.
            return null;
        }

        // The user has not yet submitted anything. Show the addsubmission link.
        $name = get_string('addsubmission', 'review');
        $url = new \moodle_url('/mod/review/view.php', [
            'id' => $cm->id,
            'action' => 'editsubmission'
        ]);
        $itemcount = 1;
        $actionable = $review->is_any_submission_plugin_enabled() && $review->can_edit_submission($userid, $userid);
    }

    return $factory->create_instance(
        $name,
        $url,
        $itemcount,
        $actionable
    );
}

/**
 * Callback function that determines whether an action event should be showing its item count
 * based on the event type and the item count.
 *
 * @param calendar_event $event The calendar event.
 * @param int $itemcount The item count associated with the action event.
 * @return bool
 */
function mod_review_core_calendar_event_action_shows_item_count(calendar_event $event, $itemcount = 0) {
    // List of event types where the action event's item count should be shown.
    $eventtypesshowingitemcount = [
        REVIEW_EVENT_TYPE_GRADINGDUE
    ];
    // For mod_review, item count should be shown if the event type is 'gradingdue' and there is one or more item count.
    return in_array($event->eventtype, $eventtypesshowingitemcount) && $itemcount > 0;
}

/**
 * This function calculates the minimum and maximum cutoff values for the timestart of
 * the given event.
 *
 * It will return an array with two values, the first being the minimum cutoff value and
 * the second being the maximum cutoff value. Either or both values can be null, which
 * indicates there is no minimum or maximum, respectively.
 *
 * If a cutoff is required then the function must return an array containing the cutoff
 * timestamp and error string to display to the user if the cutoff value is violated.
 *
 * A minimum and maximum cutoff return value will look like:
 * [
 *     [1505704373, 'The due date must be after the sbumission start date'],
 *     [1506741172, 'The due date must be before the cutoff date']
 * ]
 *
 * If the event does not have a valid timestart range then [false, false] will
 * be returned.
 *
 * @param calendar_event $event The calendar event to get the time range for
 * @param stdClass $instance The module instance to get the range from
 * @return array
 */
function mod_review_core_calendar_get_valid_event_timestart_range(\calendar_event $event, \stdClass $instance) {
    global $CFG;

    require_once($CFG->dirroot . '/mod/review/locallib.php');

    $courseid = $event->courseid;
    $modulename = $event->modulename;
    $instanceid = $event->instance;
    $coursemodule = get_fast_modinfo($courseid)->instances[$modulename][$instanceid];
    $context = context_module::instance($coursemodule->id);
    $review = new review($context, null, null);
    $review->set_instance($instance);

    return $review->get_valid_calendar_event_timestart_range($event);
}

/**
 * This function will update the review module according to the
 * event that has been modified.
 *
 * @throws \moodle_exception
 * @param \calendar_event $event
 * @param stdClass $instance The module instance to get the range from
 */
function mod_review_core_calendar_event_timestart_updated(\calendar_event $event, \stdClass $instance) {
    global $CFG, $DB;

    require_once($CFG->dirroot . '/mod/review/locallib.php');

    if (empty($event->instance) || $event->modulename != 'review') {
        return;
    }

    if ($instance->id != $event->instance) {
        return;
    }

    if (!in_array($event->eventtype, [REVIEW_EVENT_TYPE_DUE, REVIEW_EVENT_TYPE_GRADINGDUE])) {
        return;
    }

    $courseid = $event->courseid;
    $modulename = $event->modulename;
    $instanceid = $event->instance;
    $modified = false;
    $coursemodule = get_fast_modinfo($courseid)->instances[$modulename][$instanceid];
    $context = context_module::instance($coursemodule->id);

    // The user does not have the capability to modify this activity.
    if (!has_capability('moodle/course:manageactivities', $context)) {
        return;
    }

    $review = new review($context, $coursemodule, null);
    $review->set_instance($instance);

    if ($event->eventtype == REVIEW_EVENT_TYPE_DUE) {
        // This check is in here because due date events are currently
        // the only events that can be overridden, so we can save a DB
        // query if we don't bother checking other events.
        if ($review->is_override_calendar_event($event)) {
            // This is an override event so we should ignore it.
            return;
        }

        $newduedate = $event->timestart;

        if ($newduedate != $instance->duedate) {
            $instance->duedate = $newduedate;
            $modified = true;
        }
    } else if ($event->eventtype == REVIEW_EVENT_TYPE_GRADINGDUE) {
        $newduedate = $event->timestart;

        if ($newduedate != $instance->gradingduedate) {
            $instance->gradingduedate = $newduedate;
            $modified = true;
        }
    }

    if ($modified) {
        $instance->timemodified = time();
        // Persist the review instance changes.
        $DB->update_record('review', $instance);
        $review->update_calendar($coursemodule->id);
        $event = \core\event\course_module_updated::create_from_cm($coursemodule, $context);
        $event->trigger();
    }
}

/**
 * Return a list of all the user preferences used by mod_review.
 *
 * @return array
 */
function mod_review_user_preferences() {
    $preferences = array();
    $preferences['review_filter'] = array(
        'type' => PARAM_ALPHA,
        'null' => NULL_NOT_ALLOWED,
        'default' => ''
    );
    $preferences['review_workflowfilter'] = array(
        'type' => PARAM_ALPHA,
        'null' => NULL_NOT_ALLOWED,
        'default' => ''
    );
    $preferences['review_markerfilter'] = array(
        'type' => PARAM_ALPHANUMEXT,
        'null' => NULL_NOT_ALLOWED,
        'default' => ''
    );

    return $preferences;
}
