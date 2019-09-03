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
 * This file is the entry point to the review module. All pages are rendered from here
 *
 * @package   mod_review
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/review/locallib.php');

$id = required_param('id', PARAM_INT);

list ($course, $cm) = get_course_and_cm_from_cmid($id, 'review');

require_login($course, true, $cm);

$context = context_module::instance($cm->id);

require_capability('mod/review:view', $context);

$review = new review($context, $cm, $course);
$urlparams = array('id' => $id,
                  'action' => optional_param('action', '', PARAM_ALPHA),
                  'rownum' => optional_param('rownum', 0, PARAM_INT),
                  'useridlistid' => optional_param('useridlistid', $review->get_useridlist_key_id(), PARAM_ALPHANUM));

$url = new moodle_url('/mod/review/view.php', $urlparams);
$PAGE->set_url($url);

// Update module completion status.
$review->set_module_viewed();

// Apply overrides.
$review->update_effective_access($USER->id);

// Get the review class to
// render the page.
echo $review->view(optional_param('action', '', PARAM_ALPHA));
