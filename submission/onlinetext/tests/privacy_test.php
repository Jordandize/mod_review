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
 * Unit tests for reviewsubmission_onlinetext.
 *
 * @package    reviewsubmission_onlinetext
 * @copyright  2018 Adrian Greeve <adrian@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/review/tests/privacy_test.php');

/**
 * Unit tests for mod/review/submission/onlinetext/classes/privacy/
 *
 * @copyright  2018 Adrian Greeve <adrian@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reviewsubmission_online_privacy_testcase extends \mod_review\tests\mod_review_privacy_testcase {

    /**
     * Convenience function for creating feedback data.
     *
     * @param  object   $review         review object
     * @param  stdClass $student        user object
     * @param  string   $text           Submission text.
     * @return array   Submission plugin object and the submission object.
     */
    protected function create_online_submission($review, $student, $text) {
        global $CFG;

        $this->setUser($student->id);
        $submission = $review->get_user_submission($student->id, true);
        $data = new stdClass();
        $data->onlinetext_editor = array(
            'itemid' => file_get_unused_draft_itemid(),
            'text' => $text,
            'format' => FORMAT_PLAIN
        );

        $submission = $review->get_user_submission($student->id, true);

        $plugin = $review->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        return [$plugin, $submission];
    }

    /**
     * Quick test to make sure that get_metadata returns something.
     */
    public function test_get_metadata() {
        $collection = new \core_privacy\local\metadata\collection('reviewsubmission_onlinetext');
        $collection = \reviewsubmission_onlinetext\privacy\provider::get_metadata($collection);
        $this->assertNotEmpty($collection);
    }

    /**
     * Test that submission files and text are exported for a user.
     */
    public function test_export_submission_user_data() {
        $this->resetAfterTest();
        // Create course, review, submission, and then a feedback comment.
        $course = $this->getDataGenerator()->create_course();
        // Student.
        $user1 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user1->id, $course->id, 'student');
        $review = $this->create_instance(['course' => $course]);

        $context = $review->get_context();

        $submissiontext = 'Just some text';
        list($plugin, $submission) = $this->create_online_submission($review, $user1, $submissiontext);

        $writer = \core_privacy\local\request\writer::with_context($context);
        $this->assertFalse($writer->has_any_data());

        // The student should have some text submitted.
        $exportdata = new \mod_review\privacy\review_plugin_request_data($context, $review, $submission, ['Attempt 1']);
        \reviewsubmission_onlinetext\privacy\provider::export_submission_user_data($exportdata);
        $this->assertEquals($submissiontext, $writer->get_data(['Attempt 1',
                get_string('privacy:path', 'reviewsubmission_onlinetext')])->text);
    }

    /**
     * Test that all submission files are deleted for this context.
     */
    public function test_delete_submission_for_context() {
        $this->resetAfterTest();
        // Create course, review, submission, and then a feedback comment.
        $course = $this->getDataGenerator()->create_course();
        // Student.
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($user1->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, 'student');

        $review = $this->create_instance(['course' => $course]);

        $context = $review->get_context();

        $studenttext = 'Student one\'s text.';
        list($plugin, $submission) = $this->create_online_submission($review, $user1, $studenttext);
        $studenttext2 = 'Student two\'s text.';
        list($plugin2, $submission2) = $this->create_online_submission($review, $user2, $studenttext2);

        // Only need the context and review object in this plugin for this operation.
        $requestdata = new \mod_review\privacy\review_plugin_request_data($context, $review);
        \reviewsubmission_onlinetext\privacy\provider::delete_submission_for_context($requestdata);
        // This checks that there is no content for these submissions.
        $this->assertTrue($plugin->is_empty($submission));
        $this->assertTrue($plugin2->is_empty($submission2));
    }

    /**
     * Test that the comments for a user are deleted.
     */
    public function test_delete_submission_for_userid() {
        $this->resetAfterTest();
        // Create course, review, submission, and then a feedback comment.
        $course = $this->getDataGenerator()->create_course();
        // Student.
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($user1->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, 'student');

        $review = $this->create_instance(['course' => $course]);

        $context = $review->get_context();

        $studenttext = 'Student one\'s text.';
        list($plugin, $submission) = $this->create_online_submission($review, $user1, $studenttext);
        $studenttext2 = 'Student two\'s text.';
        list($plugin2, $submission2) = $this->create_online_submission($review, $user2, $studenttext2);

        // Need more data for this operation.
        $requestdata = new \mod_review\privacy\review_plugin_request_data($context, $review, $submission, [], $user1);
        \reviewsubmission_onlinetext\privacy\provider::delete_submission_for_userid($requestdata);
        // This checks that there is no content for the first submission.
        $this->assertTrue($plugin->is_empty($submission));
        // But there is for the second submission.
        $this->assertFalse($plugin2->is_empty($submission2));
    }

    public function test_delete_submissions() {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        // Only makes submissions in the second review.
        $user4 = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($user1->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($user3->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($user4->id, $course->id, 'student');

        $review1 = $this->create_instance(['course' => $course]);
        $review2 = $this->create_instance(['course' => $course]);

        $context1 = $review1->get_context();
        $context2 = $review2->get_context();

        $student1text = 'Student one\'s text.';
        list($plugin1, $submission1) = $this->create_online_submission($review1, $user1, $student1text);
        $student2text = 'Student two\'s text.';
        list($plugin2, $submission2) = $this->create_online_submission($review1, $user2, $student2text);
        $student3text = 'Student two\'s text.';
        list($plugin3, $submission3) = $this->create_online_submission($review1, $user3, $student3text);
        // Now for submissions in review two.
        $student3text2 = 'Student two\'s text for the second review.';
        list($plugin4, $submission4) = $this->create_online_submission($review2, $user3, $student3text2);
        $student4text = 'Student four\'s text.';
        list($plugin5, $submission5) = $this->create_online_submission($review2, $user4, $student4text);

        $data = $DB->get_records('reviewsubmission_onlinetext', ['review' => $review1->get_instance()->id]);
        $this->assertCount(3, $data);
        // Delete the submissions for user 1 and 3.
        $requestdata = new \mod_review\privacy\review_plugin_request_data($context1, $review1);
        $requestdata->set_userids([$user1->id, $user2->id]);
        $requestdata->populate_submissions_and_grades();
        \reviewsubmission_onlinetext\privacy\provider::delete_submissions($requestdata);

        // There should only be one record left for review one.
        $data = $DB->get_records('reviewsubmission_onlinetext', ['review' => $review1->get_instance()->id]);
        $this->assertCount(1, $data);

        // Check that the second review has not been touched.
        $data = $DB->get_records('reviewsubmission_onlinetext', ['review' => $review2->get_instance()->id]);
        $this->assertCount(2, $data);
    }
}
