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
 * Unit tests for reviewfeedback_comments
 *
 * @package    reviewfeedback_comments
 * @copyright  2016 Adrian Greeve <adrian@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/review/tests/generator.php');

/**
 * Unit tests for reviewfeedback_comments
 *
 * @copyright  2016 Adrian Greeve <adrian@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reviewfeedback_comments_testcase extends advanced_testcase {

    // Use the generator helper.
    use mod_review_test_generator;

    /**
     * Test the is_feedback_modified() method for the comments feedback.
     */
    public function test_is_feedback_modified() {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $review = $this->create_instance($course, [
                'reviewsubmission_onlinetext_enabled' => 1,
                'reviewfeedback_comments_enabled' => 1,
            ]);

        // Create an online text submission.
        $this->add_submission($student, $review);

        $this->setUser($teacher);

        // Create formdata.
        $grade = $review->get_user_grade($student->id, true);
        $data = (object) [
            'reviewfeedbackcomments_editor' => [
                'text' => '<p>first comment for this test</p>',
                'format' => 1,
            ]
        ];

        // This is the first time that we are submitting feedback, so it is modified.
        $plugin = $review->get_feedback_plugin_by_type('comments');
        $this->assertTrue($plugin->is_feedback_modified($grade, $data));

        // Save the feedback.
        $plugin->save($grade, $data);

        // Try again with the same data.
        $this->assertFalse($plugin->is_feedback_modified($grade, $data));

        // Change the data.
        $data->reviewfeedbackcomments_editor = [
                'text' => '<p>Altered comment for this test</p>',
                'format' => 1,
            ];
        $this->assertTrue($plugin->is_feedback_modified($grade, $data));
    }
}
