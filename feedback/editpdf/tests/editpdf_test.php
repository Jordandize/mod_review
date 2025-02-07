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
 * Unit tests for reviewfeedback_editpdf\comments_quick_list
 *
 * @package    reviewfeedback_editpdf
 * @category   phpunit
 * @copyright  2013 Damyon Wiese
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use \reviewfeedback_editpdf\comments_quick_list;
use \reviewfeedback_editpdf\document_services;
use \reviewfeedback_editpdf\page_editor;
use \reviewfeedback_editpdf\pdf;
use \reviewfeedback_editpdf\comment;
use \reviewfeedback_editpdf\annotation;

global $CFG;
require_once($CFG->dirroot . '/mod/review/tests/generator.php');

/**
 * Unit tests for reviewfeedback_editpdf\comments_quick_list
 *
 * @copyright  2013 Damyon Wiese
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reviewfeedback_editpdf_testcase extends advanced_testcase {

    // Use the generator helper.
    use mod_review_test_generator;

    /**
     * Ensure that GS is available.
     */
    protected function require_ghostscript() {
        // Skip this test if ghostscript is not supported.
        $result = pdf::test_gs_path(false);
        if ($result->status !== reviewfeedback_editpdf\pdf::GSPATH_OK) {
            $this->markTestSkipped('Ghostscript not setup');
        }
    }

    protected function add_file_submission($student, $review) {
        global $CFG;

        $this->setUser($student);

        // Create a file submission with the test pdf.
        $submission = $review->get_user_submission($student->id, true);

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
        $fs->create_file_from_pathname($pdfsubmission, $sourcefile);

        $data = new stdClass();
        $plugin = $review->get_submission_plugin_by_type('file');
        $plugin->save($submission, $data);
    }

    public function test_comments_quick_list() {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');

        $this->setUser($teacher);

        $this->assertEmpty(comments_quick_list::get_comments());

        $comment = comments_quick_list::add_comment('test', 45, 'red');
        $comments = comments_quick_list::get_comments();
        $this->assertEquals(count($comments), 1);
        $first = reset($comments);
        $this->assertEquals($comment, $first);

        $commentbyid = comments_quick_list::get_comment($comment->id);
        $this->assertEquals($comment, $commentbyid);

        $this->assertTrue(comments_quick_list::remove_comment($comment->id));

        $comments = comments_quick_list::get_comments();
        $this->assertEmpty($comments);
    }

    public function test_page_editor() {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $review = $this->create_instance($course, [
                'reviewsubmission_onlinetext_enabled' => 1,
                'reviewsubmission_file_enabled' => 1,
                'reviewsubmission_file_maxfiles' => 1,
                'reviewfeedback_editpdf_enabled' => 1,
                'reviewsubmission_file_maxsizebytes' => 1000000,
            ]);

        // Add the standard submission.
        $this->add_file_submission($student, $review);

        $this->setUser($teacher);

        $grade = $review->get_user_grade($student->id, true);

        $notempty = page_editor::has_annotations_or_comments($grade->id, false);
        $this->assertFalse($notempty);

        $comment = new comment();
        $comment->rawtext = 'Comment text';
        $comment->width = 100;
        $comment->x = 100;
        $comment->y = 100;
        $comment->colour = 'red';

        $comment2 = new comment();
        $comment2->rawtext = 'Comment text 2';
        $comment2->width = 100;
        $comment2->x = 200;
        $comment2->y = 100;
        $comment2->colour = 'clear';

        page_editor::set_comments($grade->id, 0, array($comment, $comment2));

        $annotation = new annotation();
        $annotation->path = '';
        $annotation->x = 100;
        $annotation->y = 100;
        $annotation->endx = 200;
        $annotation->endy = 200;
        $annotation->type = 'line';
        $annotation->colour = 'red';

        $annotation2 = new annotation();
        $annotation2->path = '';
        $annotation2->x = 100;
        $annotation2->y = 100;
        $annotation2->endx = 200;
        $annotation2->endy = 200;
        $annotation2->type = 'rectangle';
        $annotation2->colour = 'yellow';

        page_editor::set_annotations($grade->id, 0, array($annotation, $annotation2));

        // Still empty because all edits are still drafts.
        $this->assertFalse(page_editor::has_annotations_or_comments($grade->id, false));

        $comments = page_editor::get_comments($grade->id, 0, false);
        $this->assertEmpty($comments);

        $comments = page_editor::get_comments($grade->id, 0, true);
        $this->assertEquals(count($comments), 2);

        $annotations = page_editor::get_annotations($grade->id, 0, false);
        $this->assertEmpty($annotations);

        $annotations = page_editor::get_annotations($grade->id, 0, true);
        $this->assertEquals(count($annotations), 2);

        $comment = reset($comments);
        $annotation = reset($annotations);

        page_editor::remove_comment($comment->id);
        page_editor::remove_annotation($annotation->id);

        $comments = page_editor::get_comments($grade->id, 0, true);
        $this->assertEquals(count($comments), 1);

        $annotations = page_editor::get_annotations($grade->id, 0, true);
        $this->assertEquals(count($annotations), 1);

        // Release the drafts.
        page_editor::release_drafts($grade->id);

        $notempty = page_editor::has_annotations_or_comments($grade->id, false);
        $this->assertTrue($notempty);

        // Unrelease the drafts.
        page_editor::unrelease_drafts($grade->id);

        $notempty = page_editor::has_annotations_or_comments($grade->id, false);
        $this->assertFalse($notempty);
    }

    public function test_document_services() {
        $this->require_ghostscript();
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $review = $this->create_instance($course, [
                'reviewsubmission_onlinetext_enabled' => 1,
                'reviewsubmission_file_enabled' => 1,
                'reviewsubmission_file_maxfiles' => 1,
                'reviewfeedback_editpdf_enabled' => 1,
                'reviewsubmission_file_maxsizebytes' => 1000000,
            ]);

        // Add the standard submission.
        $this->add_file_submission($student, $review);

        $this->setUser($teacher);

        $grade = $review->get_user_grade($student->id, true);

        $contextid = $review->get_context()->id;
        $component = 'reviewfeedback_editpdf';
        $filearea = document_services::COMBINED_PDF_FILEAREA;
        $itemid = $grade->id;
        $filepath = '/';
        $filename = document_services::COMBINED_PDF_FILENAME;
        $fs = \get_file_storage();

        // Generate a blank combined pdf.
        $record = new \stdClass();
        $record->contextid = $contextid;
        $record->component = $component;
        $record->filearea = $filearea;
        $record->itemid = $itemid;
        $record->filepath = $filepath;
        $record->filename = $filename;
        $fs->create_file_from_string($record, base64_decode(document_services::BLANK_PDF_BASE64));

        // Verify that the blank combined pdf has the expected hash.
        $combinedpdf = $fs->get_file($contextid, $component, $filearea, $itemid, $filepath, $filename);
        $this->assertEquals($combinedpdf->get_contenthash(), document_services::BLANK_PDF_HASH);

        // Generate page images and verify that the combined pdf has been replaced.
        document_services::get_page_images_for_attempt($review, $student->id, -1);
        $combinedpdf = $fs->get_file($contextid, $component, $filearea, $itemid, $filepath, $filename);
        $this->assertNotEquals($combinedpdf->get_contenthash(), document_services::BLANK_PDF_HASH);

        $notempty = page_editor::has_annotations_or_comments($grade->id, false);
        $this->assertFalse($notempty);

        $comment = new comment();

        // Use some different charset in the comment text.
        $comment->rawtext = 'Testing example: בקלות ואמנות';
        $comment->width = 100;
        $comment->x = 100;
        $comment->y = 100;
        $comment->colour = 'red';

        page_editor::set_comments($grade->id, 0, array($comment));

        $annotations = array();

        $annotation = new annotation();
        $annotation->path = '';
        $annotation->x = 100;
        $annotation->y = 100;
        $annotation->endx = 200;
        $annotation->endy = 200;
        $annotation->type = 'line';
        $annotation->colour = 'red';
        array_push($annotations, $annotation);

        $annotation = new annotation();
        $annotation->path = '';
        $annotation->x = 100;
        $annotation->y = 100;
        $annotation->endx = 200;
        $annotation->endy = 200;
        $annotation->type = 'rectangle';
        $annotation->colour = 'yellow';
        array_push($annotations, $annotation);

        $annotation = new annotation();
        $annotation->path = '';
        $annotation->x = 100;
        $annotation->y = 100;
        $annotation->endx = 200;
        $annotation->endy = 200;
        $annotation->type = 'oval';
        $annotation->colour = 'green';
        array_push($annotations, $annotation);

        $annotation = new annotation();
        $annotation->path = '';
        $annotation->x = 100;
        $annotation->y = 100;
        $annotation->endx = 200;
        $annotation->endy = 116;
        $annotation->type = 'highlight';
        $annotation->colour = 'blue';
        array_push($annotations, $annotation);

        $annotation = new annotation();
        $annotation->path = '100,100:105,105:110,100';
        $annotation->x = 100;
        $annotation->y = 100;
        $annotation->endx = 110;
        $annotation->endy = 105;
        $annotation->type = 'pen';
        $annotation->colour = 'black';
        array_push($annotations, $annotation);
        page_editor::set_annotations($grade->id, 0, $annotations);

        page_editor::release_drafts($grade->id);

        $notempty = page_editor::has_annotations_or_comments($grade->id, false);

        $this->assertTrue($notempty);

        $file = document_services::generate_feedback_document($review->get_instance()->id, $grade->userid, $grade->attemptnumber);
        $this->assertNotEmpty($file);

        $file2 = document_services::get_feedback_document($review->get_instance()->id, $grade->userid, $grade->attemptnumber);

        $this->assertEquals($file, $file2);

        document_services::delete_feedback_document($review->get_instance()->id, $grade->userid, $grade->attemptnumber);
        $file3 = document_services::get_feedback_document($review->get_instance()->id, $grade->userid, $grade->attemptnumber);

        $this->assertEmpty($file3);
    }

    public function test_conversion_task() {
        global $DB;
        $this->require_ghostscript();
        $this->resetAfterTest();
        cron_setup_user();

        $task = new \reviewfeedback_editpdf\task\convert_submissions;

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $reviewopts = [
            'reviewsubmission_file_enabled' => 1,
            'reviewsubmission_file_maxfiles' => 1,
            'reviewfeedback_editpdf_enabled' => 1,
            'reviewsubmission_file_maxsizebytes' => 1000000,
        ];
        $review = $this->create_instance($course, $reviewopts);

        // Add the standard submission.
        $this->add_file_submission($student, $review);

        // Run the conversion task.
        ob_start();
        $task->execute();
        $output = ob_get_clean();

        // Verify it acted on both submissions in the queue.
        $this->assertContains("Convert 1 submission attempt(s) for review {$review->get_instance()->id}", $output);
        $this->assertEquals(0, $DB->count_records('reviewfeedback_editpdf_queue'));

        // Set a known limit.
        set_config('conversionattemptlimit', 3);

        // Trigger a re-queue by 'updating' a submission.
        $submission = $review->get_user_submission($student->id, true);
        $plugin = $review->get_submission_plugin_by_type('file');
        $plugin->save($submission, (new stdClass));

        // Verify that queued a conversion task.
        $this->assertEquals(1, $DB->count_records('reviewfeedback_editpdf_queue'));

        // Fake some failed attempts for it.
        $queuerecord = $DB->get_record('reviewfeedback_editpdf_queue', ['submissionid' => $submission->id]);
        $queuerecord->attemptedconversions = 3;
        $DB->update_record('reviewfeedback_editpdf_queue', $queuerecord);

        ob_start();
        $task->execute();
        $output = ob_get_clean();

        // Verify that the cron task skipped the submission.
        $this->assertNotContains("Convert 1 submission attempt(s) for review {$review->get_instance()->id}", $output);
        // And it removed it from the queue.
        $this->assertEquals(0, $DB->count_records('reviewfeedback_editpdf_queue'));

    }

    /**
     * Test that modifying the annotated pdf form return true when modified
     * and false when not modified.
     */
    public function test_is_feedback_modified() {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $review = $this->create_instance($course, [
                'reviewsubmission_onlinetext_enabled' => 1,
                'reviewsubmission_file_enabled' => 1,
                'reviewsubmission_file_maxfiles' => 1,
                'reviewfeedback_editpdf_enabled' => 1,
                'reviewsubmission_file_maxsizebytes' => 1000000,
            ]);

        // Add the standard submission.
        $this->add_file_submission($student, $review);

        $this->setUser($teacher);
        $grade = $review->get_user_grade($student->id, true);

        $notempty = page_editor::has_annotations_or_comments($grade->id, false);
        $this->assertFalse($notempty);

        $comment = new comment();

        $comment->rawtext = 'Comment text';
        $comment->width = 100;
        $comment->x = 100;
        $comment->y = 100;
        $comment->colour = 'red';

        page_editor::set_comments($grade->id, 0, array($comment));

        $annotations = array();

        $annotation = new annotation();
        $annotation->path = '';
        $annotation->x = 100;
        $annotation->y = 100;
        $annotation->endx = 200;
        $annotation->endy = 200;
        $annotation->type = 'line';
        $annotation->colour = 'red';
        array_push($annotations, $annotation);

        page_editor::set_annotations($grade->id, 0, $annotations);

        $plugin = $review->get_feedback_plugin_by_type('editpdf');
        $data = new stdClass();
        $data->editpdf_source_userid = $student->id;
        $this->assertTrue($plugin->is_feedback_modified($grade, $data));
        $plugin->save($grade, $data);

        $annotation = new annotation();
        $annotation->gradeid = $grade->id;
        $annotation->pageno = 0;
        $annotation->path = '';
        $annotation->x = 100;
        $annotation->y = 100;
        $annotation->endx = 200;
        $annotation->endy = 200;
        $annotation->type = 'rectangle';
        $annotation->colour = 'yellow';

        $yellowannotationid = page_editor::add_annotation($annotation);

        // Add a comment as well.
        $comment = new comment();
        $comment->gradeid = $grade->id;
        $comment->pageno = 0;
        $comment->rawtext = 'Second Comment text';
        $comment->width = 100;
        $comment->x = 100;
        $comment->y = 100;
        $comment->colour = 'red';
        page_editor::add_comment($comment);

        $this->assertTrue($plugin->is_feedback_modified($grade, $data));
        $plugin->save($grade, $data);

        // We should have two annotations.
        $this->assertCount(2, page_editor::get_annotations($grade->id, 0, false));
        // And two comments.
        $this->assertCount(2, page_editor::get_comments($grade->id, 0, false));

        // Add one annotation and delete another.
        $annotation = new annotation();
        $annotation->gradeid = $grade->id;
        $annotation->pageno = 0;
        $annotation->path = '100,100:105,105:110,100';
        $annotation->x = 100;
        $annotation->y = 100;
        $annotation->endx = 110;
        $annotation->endy = 105;
        $annotation->type = 'pen';
        $annotation->colour = 'black';
        page_editor::add_annotation($annotation);

        $annotations = page_editor::get_annotations($grade->id, 0, true);
        page_editor::remove_annotation($yellowannotationid);
        $this->assertTrue($plugin->is_feedback_modified($grade, $data));
        $plugin->save($grade, $data);

        // We should have two annotations.
        $this->assertCount(2, page_editor::get_annotations($grade->id, 0, false));
        // And two comments.
        $this->assertCount(2, page_editor::get_comments($grade->id, 0, false));

        // Add a comment and then remove it. Should not be considered as modified.
        $comment = new comment();
        $comment->gradeid = $grade->id;
        $comment->pageno = 0;
        $comment->rawtext = 'Third Comment text';
        $comment->width = 400;
        $comment->x = 57;
        $comment->y = 205;
        $comment->colour = 'black';
        $comment->id = page_editor::add_comment($comment);

        // We should now have three comments.
        $this->assertCount(3, page_editor::get_comments($grade->id, 0, true));
        // Now delete the newest record.
        page_editor::remove_comment($comment->id);
        // Back to two comments.
        $this->assertCount(2, page_editor::get_comments($grade->id, 0, true));
        // No modification.
        $this->assertFalse($plugin->is_feedback_modified($grade, $data));
    }
}
