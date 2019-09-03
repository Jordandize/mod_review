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
 * This file contains the upgrade code to upgrade from mod_review to mod_review
 *
 * @package   mod_review
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/mod/review/locallib.php');
require_once($CFG->libdir.'/accesslib.php');
require_once($CFG->dirroot.'/course/lib.php');

/*
 * The maximum amount of time to spend upgrading a single review.
 * This is intentionally generous (5 mins) as the effect of a timeout
 * for a legitimate upgrade would be quite harsh (roll back code will not run)
 */
define('REVIEW_MAX_UPGRADE_TIME_SECS', 300);

/**
 * Class to manage upgrades from mod_review to mod_review
 *
 * @package   mod_review
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class review_upgrade_manager {

    /**
     * This function converts all of the base settings for an instance of
     * the old review to the new format. Then it calls each of the plugins
     * to see if they can help upgrade this review.
     * @param int $oldreviewid (don't rely on the old review type even being installed)
     * @param string $log This string gets appended to during the conversion process
     * @return bool true or false
     */
    public function upgrade_review($oldreviewid, & $log) {
        global $DB, $CFG, $USER;
        // Steps to upgrade an review.

        core_php_time_limit::raise(REVIEW_MAX_UPGRADE_TIME_SECS);

        // Get the module details.
        $oldmodule = $DB->get_record('modules', array('name'=>'review'), '*', MUST_EXIST);
        $params = array('module'=>$oldmodule->id, 'instance'=>$oldreviewid);
        $oldcoursemodule = $DB->get_record('course_modules',
                                           $params,
                                           '*',
                                           MUST_EXIST);
        $oldcontext = context_module::instance($oldcoursemodule->id);
        // We used to check for admin capability, but since Moodle 2.7 this is called
        // during restore of a mod_review module.
        // Also note that we do not check for any mod_review capabilities, because they can
        // be removed so that users don't add new instances of the broken old thing.
        if (!has_capability('mod/review:addinstance', $oldcontext)) {
            $log = get_string('couldnotcreatenewreviewinstance', 'mod_review');
            return false;
        }

        // First insert an review instance to get the id.
        $oldreview = $DB->get_record('review', array('id'=>$oldreviewid), '*', MUST_EXIST);

        $oldversion = get_config('review_' . $oldreview->reviewtype, 'version');

        $data = new stdClass();
        $data->course = $oldreview->course;
        $data->name = $oldreview->name;
        $data->intro = $oldreview->intro;
        $data->introformat = $oldreview->introformat;
        $data->alwaysshowdescription = 1;
        $data->sendnotifications = $oldreview->emailteachers;
        $data->sendlatenotifications = $oldreview->emailteachers;
        $data->duedate = $oldreview->timedue;
        $data->allowsubmissionsfromdate = $oldreview->timeavailable;
        $data->grade = $oldreview->grade;
        $data->submissiondrafts = $oldreview->resubmit;
        $data->requiresubmissionstatement = 0;
        $data->markingworkflow = 0;
        $data->markingallocation = 0;
        $data->cutoffdate = 0;
        $data->gradingduedate = 0;
        // New way to specify no late submissions.
        if ($oldreview->preventlate) {
            $data->cutoffdate = $data->duedate;
        }
        $data->teamsubmission = 0;
        $data->requireallteammemberssubmit = 0;
        $data->teamsubmissiongroupingid = 0;
        $data->blindmarking = 0;
        $data->attemptreopenmethod = 'none';
        $data->maxattempts = REVIEW_UNLIMITED_ATTEMPTS;

        $newreview = new review(null, null, null);

        if (!$newreview->add_instance($data, false)) {
            $log = get_string('couldnotcreatenewreviewinstance', 'mod_review');
            return false;
        }

        // Now create a new coursemodule from the old one.
        $newmodule = $DB->get_record('modules', array('name'=>'review'), '*', MUST_EXIST);
        $newcoursemodule = $this->duplicate_course_module($oldcoursemodule,
                                                          $newmodule->id,
                                                          $newreview->get_instance()->id);
        if (!$newcoursemodule) {
            $log = get_string('couldnotcreatenewcoursemodule', 'mod_review');
            return false;
        }

        // Convert the base database tables (review, submission, grade).

        // These are used to store information in case a rollback is required.
        $gradingarea = null;
        $gradingdefinitions = null;
        $gradeidmap = array();
        $completiondone = false;
        $gradesdone = false;

        // From this point we want to rollback on failure.
        $rollback = false;
        try {
            $newreview->set_context(context_module::instance($newcoursemodule->id));

            // The course module has now been created - time to update the core tables.

            // Copy intro files.
            $newreview->copy_area_files_for_upgrade($oldcontext->id, 'mod_review', 'intro', 0,
                                            $newreview->get_context()->id, 'mod_review', 'intro', 0);

            // Get the plugins to do their bit.
            foreach ($newreview->get_submission_plugins() as $plugin) {
                if ($plugin->can_upgrade($oldreview->reviewtype, $oldversion)) {
                    $plugin->enable();
                    if (!$plugin->upgrade_settings($oldcontext, $oldreview, $log)) {
                        $rollback = true;
                    }
                } else {
                    $plugin->disable();
                }
            }
            foreach ($newreview->get_feedback_plugins() as $plugin) {
                if ($plugin->can_upgrade($oldreview->reviewtype, $oldversion)) {
                    $plugin->enable();
                    if (!$plugin->upgrade_settings($oldcontext, $oldreview, $log)) {
                        $rollback = true;
                    }
                } else {
                    $plugin->disable();
                }
            }

            // See if there is advanced grading upgrades required.
            $gradingarea = $DB->get_record('grading_areas',
                                           array('contextid'=>$oldcontext->id, 'areaname'=>'submission'),
                                           '*',
                                           IGNORE_MISSING);
            if ($gradingarea) {
                $params = array('id'=>$gradingarea->id,
                                'contextid'=>$newreview->get_context()->id,
                                'component'=>'mod_review',
                                'areaname'=>'submissions');
                $DB->update_record('grading_areas', $params);
                $gradingdefinitions = $DB->get_records('grading_definitions',
                                                       array('areaid'=>$gradingarea->id));
            }

            // Upgrade availability data.
            \core_availability\info::update_dependency_id_across_course(
                    $newcoursemodule->course, 'course_modules', $oldcoursemodule->id, $newcoursemodule->id);

            // Upgrade completion data.
            $DB->set_field('course_modules_completion',
                           'coursemoduleid',
                           $newcoursemodule->id,
                           array('coursemoduleid'=>$oldcoursemodule->id));
            $allcriteria = $DB->get_records('course_completion_criteria',
                                            array('moduleinstance'=>$oldcoursemodule->id));
            foreach ($allcriteria as $criteria) {
                $criteria->module = 'review';
                $criteria->moduleinstance = $newcoursemodule->id;
                $DB->update_record('course_completion_criteria', $criteria);
            }
            $completiondone = true;

            // Migrate log entries so we don't lose them.
            $logparams = array('cmid' => $oldcoursemodule->id, 'course' => $oldcoursemodule->course);
            $DB->set_field('log', 'module', 'review', $logparams);
            $DB->set_field('log', 'cmid', $newcoursemodule->id, $logparams);

            // Copy all the submission data (and get plugins to do their bit).
            $oldsubmissions = $DB->get_records('review_submissions',
                                               array('review'=>$oldreviewid));

            foreach ($oldsubmissions as $oldsubmission) {
                $submission = new stdClass();
                $submission->review = $newreview->get_instance()->id;
                $submission->userid = $oldsubmission->userid;
                $submission->timecreated = $oldsubmission->timecreated;
                $submission->timemodified = $oldsubmission->timemodified;
                $submission->status = REVIEW_SUBMISSION_STATUS_SUBMITTED;
                // Because in mod_review there could only be one submission per student, it is always the latest.
                $submission->latest = 1;
                $submission->id = $DB->insert_record('review_submission', $submission);
                if (!$submission->id) {
                    $log .= get_string('couldnotinsertsubmission', 'mod_review', $submission->userid);
                    $rollback = true;
                }
                foreach ($newreview->get_submission_plugins() as $plugin) {
                    if ($plugin->can_upgrade($oldreview->reviewtype, $oldversion)) {
                        if (!$plugin->upgrade($oldcontext,
                                              $oldreview,
                                              $oldsubmission,
                                              $submission,
                                              $log)) {
                            $rollback = true;
                        }
                    }
                }
                if ($oldsubmission->timemarked) {
                    // Submission has been graded - create a grade record.
                    $grade = new stdClass();
                    $grade->review = $newreview->get_instance()->id;
                    $grade->userid = $oldsubmission->userid;
                    $grade->grader = $oldsubmission->teacher;
                    $grade->timemodified = $oldsubmission->timemarked;
                    $grade->timecreated = $oldsubmission->timecreated;
                    $grade->grade = $oldsubmission->grade;
                    if ($oldsubmission->mailed) {
                        // The mailed flag goes in the flags table.
                        $flags = new stdClass();
                        $flags->userid = $oldsubmission->userid;
                        $flags->review = $newreview->get_instance()->id;
                        $flags->mailed = 1;
                        $DB->insert_record('review_user_flags', $flags);
                    }
                    $grade->id = $DB->insert_record('review_grades', $grade);
                    if (!$grade->id) {
                        $log .= get_string('couldnotinsertgrade', 'mod_review', $grade->userid);
                        $rollback = true;
                    }

                    // Copy any grading instances.
                    if ($gradingarea) {

                        $gradeidmap[$grade->id] = $oldsubmission->id;

                        foreach ($gradingdefinitions as $definition) {
                            $params = array('definitionid'=>$definition->id,
                                            'itemid'=>$oldsubmission->id);
                            $DB->set_field('grading_instances', 'itemid', $grade->id, $params);
                        }

                    }
                    foreach ($newreview->get_feedback_plugins() as $plugin) {
                        if ($plugin->can_upgrade($oldreview->reviewtype, $oldversion)) {
                            if (!$plugin->upgrade($oldcontext,
                                                  $oldreview,
                                                  $oldsubmission,
                                                  $grade,
                                                  $log)) {
                                $rollback = true;
                            }
                        }
                    }
                }
            }

            $newreview->update_calendar($newcoursemodule->id);

            // Reassociate grade_items from the old review instance to the new review instance.
            // This includes outcome linked grade_items.
            $params = array('review', $newreview->get_instance()->id, 'review', $oldreview->id);
            $sql = 'UPDATE {grade_items} SET itemmodule = ?, iteminstance = ? WHERE itemmodule = ? AND iteminstance = ?';
            $DB->execute($sql, $params);

            // Create a mapping record to map urls from the old to the new review.
            $mapping = new stdClass();
            $mapping->oldcmid = $oldcoursemodule->id;
            $mapping->oldinstance = $oldreview->id;
            $mapping->newcmid = $newcoursemodule->id;
            $mapping->newinstance = $newreview->get_instance()->id;
            $mapping->timecreated = time();
            $DB->insert_record('review_upgrade', $mapping);

            $gradesdone = true;

        } catch (Exception $exception) {
            $rollback = true;
            $log .= get_string('conversionexception', 'mod_review', $exception->getMessage());
        }

        if ($rollback) {
            // Roll back the grades changes.
            if ($gradesdone) {
                // Reassociate grade_items from the new review instance to the old review instance.
                $params = array('review', $oldreview->id, 'review', $newreview->get_instance()->id);
                $sql = 'UPDATE {grade_items} SET itemmodule = ?, iteminstance = ? WHERE itemmodule = ? AND iteminstance = ?';
                $DB->execute($sql, $params);
            }
            // Roll back the completion changes.
            if ($completiondone) {
                $DB->set_field('course_modules_completion',
                               'coursemoduleid',
                               $oldcoursemodule->id,
                               array('coursemoduleid'=>$newcoursemodule->id));

                $allcriteria = $DB->get_records('course_completion_criteria',
                                                array('moduleinstance'=>$newcoursemodule->id));
                foreach ($allcriteria as $criteria) {
                    $criteria->module = 'review';
                    $criteria->moduleinstance = $oldcoursemodule->id;
                    $DB->update_record('course_completion_criteria', $criteria);
                }
            }
            // Roll back the log changes.
            $logparams = array('cmid' => $newcoursemodule->id, 'course' => $newcoursemodule->course);
            $DB->set_field('log', 'module', 'review', $logparams);
            $DB->set_field('log', 'cmid', $oldcoursemodule->id, $logparams);
            // Roll back the advanced grading update.
            if ($gradingarea) {
                foreach ($gradeidmap as $newgradeid => $oldsubmissionid) {
                    foreach ($gradingdefinitions as $definition) {
                        $DB->set_field('grading_instances',
                                       'itemid',
                                       $oldsubmissionid,
                                       array('definitionid'=>$definition->id, 'itemid'=>$newgradeid));
                    }
                }
                $params = array('id'=>$gradingarea->id,
                                'contextid'=>$oldcontext->id,
                                'component'=>'mod_review',
                                'areaname'=>'submission');
                $DB->update_record('grading_areas', $params);
            }
            $newreview->delete_instance();

            return false;
        }
        // Delete the old review (use object delete).
        $cm = get_coursemodule_from_id('', $oldcoursemodule->id, $oldcoursemodule->course);
        if ($cm) {
            course_delete_module($cm->id);
        }
        rebuild_course_cache($oldcoursemodule->course);
        return true;
    }


    /**
     * Create a duplicate course module record so we can create the upgraded
     * review module alongside the old review module.
     *
     * @param stdClass $cm The old course module record
     * @param int $moduleid The id of the new review module
     * @param int $newinstanceid The id of the new instance of the review module
     * @return mixed stdClass|bool The new course module record or FALSE
     */
    private function duplicate_course_module(stdClass $cm, $moduleid, $newinstanceid) {
        global $DB, $CFG;

        $newcm = new stdClass();
        $newcm->course           = $cm->course;
        $newcm->module           = $moduleid;
        $newcm->instance         = $newinstanceid;
        $newcm->visible          = $cm->visible;
        $newcm->section          = $cm->section;
        $newcm->score            = $cm->score;
        $newcm->indent           = $cm->indent;
        $newcm->groupmode        = $cm->groupmode;
        $newcm->groupingid       = $cm->groupingid;
        $newcm->completion                = $cm->completion;
        $newcm->completiongradeitemnumber = $cm->completiongradeitemnumber;
        $newcm->completionview            = $cm->completionview;
        $newcm->completionexpected        = $cm->completionexpected;
        if (!empty($CFG->enableavailability)) {
            $newcm->availability = $cm->availability;
        }
        $newcm->showdescription = $cm->showdescription;

        $newcmid = add_course_module($newcm);
        $newcm = get_coursemodule_from_id('', $newcmid, $cm->course);
        if (!$newcm) {
            return false;
        }
        $section = $DB->get_record("course_sections", array("id"=>$newcm->section));
        if (!$section) {
            return false;
        }

        $newcm->section = course_add_cm_to_section($newcm->course, $newcm->id, $section->section, $cm->id);

        // Make sure visibility is set correctly (in particular in calendar).
        // Note: Allow them to set it even without moodle/course:activityvisibility.
        set_coursemodule_visible($newcm->id, $newcm->visible);

        return $newcm;
    }
}

/**
 * Determines if the review as any null grades that were rescaled.
 *
 * Null grades are stored as -1 but should never be rescaled.
 *
 * @return int[] Array of the ids of all the reviews with rescaled null grades.
 */
function get_reviews_with_rescaled_null_grades() {
    global $DB;

    $query = 'SELECT id, review FROM {review_grades}
              WHERE grade < 0 AND grade <> -1';

    $reviews = array_values($DB->get_records_sql($query));

    $getreviewid = function ($review) {
        return $review->review;
    };

    $reviews = array_map($getreviewid, $reviews);

    return $reviews;
}
