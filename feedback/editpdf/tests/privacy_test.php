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
 * Unit tests for reviewfeedback_editpdf.
 *
 * @package    reviewfeedback_editpdf
 * @copyright  2018 Adrian Greeve <adrian@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/review/locallib.php');
require_once($CFG->dirroot . '/mod/review/tests/privacy_test.php');

use \reviewfeedback_editpdf\page_editor;
use \mod_review\privacy\review_plugin_request_data;

/**
 * Unit tests for mod/review/feedback/editpdf/classes/privacy/
 *
 * @copyright  2018 Adrian Greeve <adrian@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reviewfeedback_editpdf_privacy_testcase extends \mod_review\tests\mod_review_privacy_testcase {

    public function setUp() {
        // Skip this test if ghostscript is not supported.
        $result = \reviewfeedback_editpdf\pdf::test_gs_path(false);
        if ($result->status !== \reviewfeedback_editpdf\pdf::GSPATH_OK) {
            $this->markTestSkipped('Ghostscript not setup');
            return;
        }
        parent::setUp();
    }

    /**
     * Convenience function for creating feedback data.
     *
     * @param  object   $review         review object
     * @param  stdClass $student        user object
     * @param  stdClass $teacher        user object
     * @return array   Feedback plugin object and the grade object.
     */
    protected function create_feedback($review, $student, $teacher) {
        global $CFG;

        // Create a file submission with the test pdf.
        $submission = $review->get_user_submission($student->id, true);

        $this->setUser($student->id);

        $fs = get_file_storage();
        $pdfsubmission = (object) array(
            'contextid' => $review->get_context()->id,
            'component' => 'reviewsubmission_file',
            'filearea' => REVIEWSUBMISSION_FILE_FILEAREA,
            'itemid' => $submission->id,
            'filepath' => '/',
            'filename' => 'submission.pdf'
        );
        $sourcefile = $CFG->dirroot.'/mod/review/feedback/editpdf/tests/fixtures/submission.pdf';
        $fi = $fs->create_file_from_pathname($pdfsubmission, $sourcefile);

        $data = new \stdClass();
        $plugin = $review->get_submission_plugin_by_type('file');
        $plugin->save($submission, $data);

        $this->setUser($teacher->id);

        $plugin = $review->get_feedback_plugin_by_type('editpdf');

        $grade = $review->get_user_grade($student->id, true);

        $comment = new \reviewfeedback_editpdf\comment();

        $comment->rawtext = 'Comment text';
        $comment->width = 100;
        $comment->x = 100;
        $comment->y = 100;
        $comment->colour = 'red';
        page_editor::set_comments($grade->id, 0, [$comment]);

        $annotation = new \reviewfeedback_editpdf\annotation();

        $annotation->path = '';
        $annotation->x = 100;
        $annotation->y = 100;
        $annotation->endx = 200;
        $annotation->endy = 200;
        $annotation->type = 'line';
        $annotation->colour = 'red';

        page_editor::set_annotations($grade->id, 0, [$annotation]);

        $comments = page_editor::get_comments($grade->id, 0, true);
        $annotations = page_editor::get_annotations($grade->id, 0, false);
        page_editor::release_drafts($grade->id);
        $storedfile = \reviewfeedback_editpdf\document_services::generate_feedback_document($review->get_instance()->id, $student->id,
                $grade->attemptnumber);

        return [$plugin, $grade, $storedfile];
    }

    /**
     * Quick test to make sure that get_metadata returns something.
     */
    public function test_get_metadata() {
        $collection = new \core_privacy\local\metadata\collection('reviewfeedback_editpdf');
        $collection = \reviewfeedback_editpdf\privacy\provider::get_metadata($collection);
        $this->assertNotEmpty($collection);
    }

    /**
     * Test that feedback comments are exported for a user.
     */
    public function test_export_feedback_user_data() {
        $this->resetAfterTest();
        // Create course, review, submission, and then a feedback comment.
        $course = $this->getDataGenerator()->create_course();
        // Student.
        $user1 = $this->getDataGenerator()->create_user();
        // Teacher.
        $user2 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user1->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, 'editingteacher');
        $review = $this->create_instance(['course' => $course,
                'reviewsubmission_file_enabled' => 1,
                'reviewsubmission_file_maxfiles' => 1,
                'reviewfeedback_editpdf_enabled' => 1,
                'reviewsubmission_file_maxsizebytes' => 1000000]);

        $context = $review->get_context();

        list($plugin, $grade, $storedfile) = $this->create_feedback($review, $user1, $user2);

        // Check that we have data.
        $this->assertFalse($plugin->is_empty($grade));

        $writer = \core_privacy\local\request\writer::with_context($context);
        $this->assertFalse($writer->has_any_data());

        // The student should be able to see the teachers feedback.
        $exportdata = new \mod_review\privacy\review_plugin_request_data($context, $review, $grade, [], $user1);
        \reviewfeedback_editpdf\privacy\provider::export_feedback_user_data($exportdata);
        // print_object($writer->get_files([get_string('privacy:path', 'reviewfeedback_editpdf')]));
        // print_object($writer->get_files(['PDF feedback', $storedfile->get_filename()]));
        $pdffile = $writer->get_files([get_string('privacy:path', 'reviewfeedback_editpdf')])[$storedfile->get_filename()];
        // The writer should have returned a stored file.
        $this->assertInstanceOf('stored_file', $pdffile);
    }

    /**
     * Test that all feedback is deleted for a context.
     */
    public function test_delete_feedback_for_context() {
        $this->resetAfterTest();
        // Create course, review, submission, and then a feedback comment.
        $course = $this->getDataGenerator()->create_course();
        // Students.
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        // Teacher.
        $user3 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user1->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($user3->id, $course->id, 'editingteacher');
        $review = $this->create_instance(['course' => $course,
                'reviewsubmission_file_enabled' => 1,
                'reviewsubmission_file_maxfiles' => 1,
                'reviewfeedback_editpdf_enabled' => 1,
                'reviewsubmission_file_maxsizebytes' => 1000000]);

        $context = $review->get_context();

        list($plugin1, $grade1, $storedfile1) = $this->create_feedback($review, $user1, $user3);
        list($plugin2, $grade2, $storedfile2) = $this->create_feedback($review, $user2, $user3);

        // Check that we have data.
        $this->assertFalse($plugin1->is_empty($grade1));
        $this->assertFalse($plugin2->is_empty($grade2));

        $requestdata = new review_plugin_request_data($context, $review);
        \reviewfeedback_editpdf\privacy\provider::delete_feedback_for_context($requestdata);

        // Check that we now have no data.
        $this->assertTrue($plugin1->is_empty($grade1));
        $this->assertTrue($plugin2->is_empty($grade2));
    }

    /**
     * Test that a grade item is deleted for a user.
     */
    public function test_delete_feedback_for_grade() {
        $this->resetAfterTest();
        // Create course, review, submission, and then a feedback comment.
        $course = $this->getDataGenerator()->create_course();
        // Students.
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        // Teacher.
        $user3 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user1->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($user3->id, $course->id, 'editingteacher');
        $review = $this->create_instance(['course' => $course,
                'reviewsubmission_file_enabled' => 1,
                'reviewsubmission_file_maxfiles' => 1,
                'reviewfeedback_editpdf_enabled' => 1,
                'reviewsubmission_file_maxsizebytes' => 1000000]);

        $context = $review->get_context();

        list($plugin1, $grade1, $storedfile1) = $this->create_feedback($review, $user1, $user3);
        list($plugin2, $grade2, $storedfile2) = $this->create_feedback($review, $user2, $user3);

        // Check that we have data.
        $this->assertFalse($plugin1->is_empty($grade1));
        $this->assertFalse($plugin2->is_empty($grade2));

        $requestdata = new review_plugin_request_data($context, $review, $grade1, [], $user1);
        \reviewfeedback_editpdf\privacy\provider::delete_feedback_for_grade($requestdata);

        // Check that we now have no data for user 1.
        $this->assertTrue($plugin1->is_empty($grade1));
        // Check that user 2 data is still there.
        $this->assertFalse($plugin2->is_empty($grade2));
    }

    /**
     * Test that a grade item is deleted for a user.
     */
    public function test_delete_feedback_for_grades() {
        global $DB;

        $this->resetAfterTest();
        // Create course, review, submission, and then a feedback comment.
        $course = $this->getDataGenerator()->create_course();
        // Students.
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        $user4 = $this->getDataGenerator()->create_user();
        // Teacher.
        $user5 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user1->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($user3->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($user4->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($user5->id, $course->id, 'editingteacher');
        $review1 = $this->create_instance(['course' => $course,
                'reviewsubmission_file_enabled' => 1,
                'reviewsubmission_file_maxfiles' => 1,
                'reviewfeedback_editpdf_enabled' => 1,
                'reviewsubmission_file_maxsizebytes' => 1000000]);

        $review2 = $this->create_instance(['course' => $course,
                'reviewsubmission_file_enabled' => 1,
                'reviewsubmission_file_maxfiles' => 1,
                'reviewfeedback_editpdf_enabled' => 1,
                'reviewsubmission_file_maxsizebytes' => 1000000]);

        $context = $review1->get_context();

        list($plugin1, $grade1, $storedfile1) = $this->create_feedback($review1, $user1, $user5);
        list($plugin2, $grade2, $storedfile2) = $this->create_feedback($review1, $user2, $user5);
        list($plugin3, $grade3, $storedfile3) = $this->create_feedback($review1, $user3, $user5);
        list($plugin4, $grade4, $storedfile4) = $this->create_feedback($review2, $user3, $user5);
        list($plugin5, $grade5, $storedfile5) = $this->create_feedback($review2, $user4, $user5);

        // Check that we have data.
        $this->assertFalse($plugin1->is_empty($grade1));
        $this->assertFalse($plugin2->is_empty($grade2));
        $this->assertFalse($plugin3->is_empty($grade3));
        $this->assertFalse($plugin4->is_empty($grade4));
        $this->assertFalse($plugin5->is_empty($grade5));

        // Check that there are also files generated.
        $files = $DB->get_records('files', ['component' => 'reviewfeedback_editpdf', 'filearea' => 'download']);
        $this->assertCount(10, $files);

        $deletedata = new review_plugin_request_data($context, $review1);
        $deletedata->set_userids([$user1->id, $user3->id]);
        $deletedata->populate_submissions_and_grades();
        \reviewfeedback_editpdf\privacy\provider::delete_feedback_for_grades($deletedata);

        // Check that we now have no data for user 1.
        $this->assertTrue($plugin1->is_empty($grade1));
        // Check that user 2 data is still there.
        $this->assertFalse($plugin2->is_empty($grade2));
        // User 3 in review 1 should be gone.
        $this->assertTrue($plugin3->is_empty($grade3));
        // User 3 in review 2 should still be here.
        $this->assertFalse($plugin4->is_empty($grade4));
        // User 4 in review 2 should also still be here.
        $this->assertFalse($plugin5->is_empty($grade5));

        // Check the files as well.
        $files = $DB->get_records('files', ['component' => 'reviewfeedback_editpdf', 'filearea' => 'download']);
        // We should now only have six records here.
        $this->assertCount(6, $files);
    }
}
