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
 * This file contains the ingest manager for the reviewfeedback_editpdf plugin
 *
 * @package   reviewfeedback_editpdf
 * @copyright 2012 Davo Smith
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace reviewfeedback_editpdf;

use DOMDocument;

/**
 * Functions for generating the annotated pdf.
 *
 * This class controls the ingest of student submission files to a normalised
 * PDF 1.4 document with all submission files concatinated together. It also
 * provides the functions to generate a downloadable pdf with all comments and
 * annotations embedded.
 * @copyright 2012 Davo Smith
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class document_services {

    /** Compoment name */
    const COMPONENT = "reviewfeedback_editpdf";
    /** File area for generated pdf */
    const FINAL_PDF_FILEAREA = 'download';
    /** File area for combined pdf */
    const COMBINED_PDF_FILEAREA = 'combined';
    /** File area for partial combined pdf */
    const PARTIAL_PDF_FILEAREA = 'partial';
    /** File area for importing html */
    const IMPORT_HTML_FILEAREA = 'importhtml';
    /** File area for page images */
    const PAGE_IMAGE_FILEAREA = 'pages';
    /** File area for readonly page images */
    const PAGE_IMAGE_READONLY_FILEAREA = 'readonlypages';
    /** File area for the stamps */
    const STAMPS_FILEAREA = 'stamps';
    /** Filename for combined pdf */
    const COMBINED_PDF_FILENAME = 'combined.pdf';
    /** Hash of blank pdf */
    const BLANK_PDF_HASH = '4c803c92c71f21b423d13de570c8a09e0a31c718';

    /** Base64 encoded blank pdf. This is the most reliable/fastest way to generate a blank pdf. */
    const BLANK_PDF_BASE64 = <<<EOD
JVBERi0xLjQKJcOkw7zDtsOfCjIgMCBvYmoKPDwvTGVuZ3RoIDMgMCBSL0ZpbHRlci9GbGF0ZURl
Y29kZT4+CnN0cmVhbQp4nDPQM1Qo5ypUMFAwALJMLU31jBQsTAz1LBSKUrnCtRTyuAIVAIcdB3IK
ZW5kc3RyZWFtCmVuZG9iagoKMyAwIG9iago0MgplbmRvYmoKCjUgMCBvYmoKPDwKPj4KZW5kb2Jq
Cgo2IDAgb2JqCjw8L0ZvbnQgNSAwIFIKL1Byb2NTZXRbL1BERi9UZXh0XQo+PgplbmRvYmoKCjEg
MCBvYmoKPDwvVHlwZS9QYWdlL1BhcmVudCA0IDAgUi9SZXNvdXJjZXMgNiAwIFIvTWVkaWFCb3hb
MCAwIDU5NSA4NDJdL0dyb3VwPDwvUy9UcmFuc3BhcmVuY3kvQ1MvRGV2aWNlUkdCL0kgdHJ1ZT4+
L0NvbnRlbnRzIDIgMCBSPj4KZW5kb2JqCgo0IDAgb2JqCjw8L1R5cGUvUGFnZXMKL1Jlc291cmNl
cyA2IDAgUgovTWVkaWFCb3hbIDAgMCA1OTUgODQyIF0KL0tpZHNbIDEgMCBSIF0KL0NvdW50IDE+
PgplbmRvYmoKCjcgMCBvYmoKPDwvVHlwZS9DYXRhbG9nL1BhZ2VzIDQgMCBSCi9PcGVuQWN0aW9u
WzEgMCBSIC9YWVogbnVsbCBudWxsIDBdCi9MYW5nKGVuLUFVKQo+PgplbmRvYmoKCjggMCBvYmoK
PDwvQ3JlYXRvcjxGRUZGMDA1NzAwNzIwMDY5MDA3NDAwNjUwMDcyPgovUHJvZHVjZXI8RkVGRjAw
NEMwMDY5MDA2MjAwNzIwMDY1MDA0RjAwNjYwMDY2MDA2OTAwNjMwMDY1MDAyMDAwMzQwMDJFMDAz
ND4KL0NyZWF0aW9uRGF0ZShEOjIwMTYwMjI2MTMyMzE0KzA4JzAwJyk+PgplbmRvYmoKCnhyZWYK
MCA5CjAwMDAwMDAwMDAgNjU1MzUgZiAKMDAwMDAwMDIyNiAwMDAwMCBuIAowMDAwMDAwMDE5IDAw
MDAwIG4gCjAwMDAwMDAxMzIgMDAwMDAgbiAKMDAwMDAwMDM2OCAwMDAwMCBuIAowMDAwMDAwMTUx
IDAwMDAwIG4gCjAwMDAwMDAxNzMgMDAwMDAgbiAKMDAwMDAwMDQ2NiAwMDAwMCBuIAowMDAwMDAw
NTYyIDAwMDAwIG4gCnRyYWlsZXIKPDwvU2l6ZSA5L1Jvb3QgNyAwIFIKL0luZm8gOCAwIFIKL0lE
IFsgPEJDN0REQUQwRDQyOTQ1OTQ2OUU4NzJCMjI1ODUyNkU4Pgo8QkM3RERBRDBENDI5NDU5NDY5
RTg3MkIyMjU4NTI2RTg+IF0KL0RvY0NoZWNrc3VtIC9BNTYwMEZCMDAzRURCRTg0MTNBNTk3RTZF
MURDQzJBRgo+PgpzdGFydHhyZWYKNzM2CiUlRU9GCg==
EOD;

    /**
     * This function will take an int or an review instance and
     * return an review instance. It is just for convenience.
     * @param int|\review $review
     * @return review
     */
    private static function get_review_from_param($review) {
        global $CFG;

        require_once($CFG->dirroot . '/mod/review/locallib.php');

        if (!is_object($review)) {
            $cm = get_coursemodule_from_instance('review', $review, 0, false, MUST_EXIST);
            $context = \context_module::instance($cm->id);

            $review = new \review($context, null, null);
        }
        return $review;
    }

    /**
     * Get a hash that will be unique and can be used in a path name.
     * @param int|\review $review
     * @param int $userid
     * @param int $attemptnumber (-1 means latest attempt)
     */
    private static function hash($review, $userid, $attemptnumber) {
        if (is_object($review)) {
            $reviewid = $review->get_instance()->id;
        } else {
            $reviewid = $review;
        }
        return sha1($reviewid . '_' . $userid . '_' . $attemptnumber);
    }

    /**
     * Use a DOM parser to accurately replace images with their alt text.
     * @param string $html
     * @return string New html with no image tags.
     */
    protected static function strip_images($html) {
        // Load HTML and suppress any parsing errors (DOMDocument->loadHTML() does not current support HTML5 tags).
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml version="1.0" encoding="UTF-8" ?>' . $html);
        libxml_clear_errors();

        // Find all img tags.
        if ($imgnodes = $dom->getElementsByTagName('img')) {
            // Replace img nodes with the img alt text without overriding DOM elements.
            for ($i = ($imgnodes->length - 1); $i >= 0; $i--) {
                $imgnode = $imgnodes->item($i);
                $alt = ($imgnode->hasAttribute('alt')) ? ' [ ' . $imgnode->getAttribute('alt') . ' ] ' : ' ';
                $textnode = $dom->createTextNode($alt);

                $imgnode->parentNode->replaceChild($textnode, $imgnode);
            }
        }
        $count = 1;
        return str_replace("<?xml version=\"1.0\" encoding=\"UTF-8\" ?>", "", $dom->saveHTML(), $count);
    }

    /**
     * This function will search for all files that can be converted
     * and concatinated into a PDF (1.4) - for any submission plugin
     * for this students attempt.
     *
     * @param int|\review $review
     * @param int $userid
     * @param int $attemptnumber (-1 means latest attempt)
     * @return combined_document
     */
    protected static function list_compatible_submission_files_for_attempt($review, $userid, $attemptnumber) {
        global $USER, $DB;

        $review = self::get_review_from_param($review);

        // Capability checks.
        if (!$review->can_view_submission($userid)) {
            print_error('nopermission');
        }

        $files = array();

        if ($review->get_instance()->teamsubmission) {
            $submission = $review->get_group_submission($userid, 0, false, $attemptnumber);
        } else {
            $submission = $review->get_user_submission($userid, false, $attemptnumber);
        }
        $user = $DB->get_record('user', array('id' => $userid));

        // User has not submitted anything yet.
        if (!$submission) {
            return new combined_document();
        }

        $fs = get_file_storage();
        $converter = new \core_files\converter();
        // Ask each plugin for it's list of files.
        foreach ($review->get_submission_plugins() as $plugin) {
            if ($plugin->is_enabled() && $plugin->is_visible()) {
                $pluginfiles = $plugin->get_files($submission, $user);
                foreach ($pluginfiles as $filename => $file) {
                    if ($file instanceof \stored_file) {
                        if ($file->get_mimetype() === 'application/pdf') {
                            $files[$filename] = $file;
                        } else if ($convertedfile = $converter->start_conversion($file, 'pdf')) {
                            $files[$filename] = $convertedfile;
                        }
                    } else if ($converter->can_convert_format_to('html', 'pdf')) {
                        // Create a tmp stored_file from this html string.
                        $file = reset($file);
                        // Strip image tags, because they will not be resolvable.
                        $file = self::strip_images($file);
                        $record = new \stdClass();
                        $record->contextid = $review->get_context()->id;
                        $record->component = 'reviewfeedback_editpdf';
                        $record->filearea = self::IMPORT_HTML_FILEAREA;
                        $record->itemid = $submission->id;
                        $record->filepath = '/';
                        $record->filename = $plugin->get_type() . '-' . $filename;

                        $htmlfile = $fs->get_file($record->contextid,
                                $record->component,
                                $record->filearea,
                                $record->itemid,
                                $record->filepath,
                                $record->filename);

                        $newhash = sha1($file);

                        // If the file exists, and the content hash doesn't match, remove it.
                        if ($htmlfile && $newhash !== $htmlfile->get_contenthash()) {
                            $htmlfile->delete();
                            $htmlfile = false;
                        }

                        // If the file doesn't exist, or if it was removed above, create a new one.
                        if (!$htmlfile) {
                            $htmlfile = $fs->create_file_from_string($record, $file);
                        }

                        $convertedfile = $converter->start_conversion($htmlfile, 'pdf');

                        if ($convertedfile) {
                            $files[$filename] = $convertedfile;
                        }
                    }
                }
            }
        }
        $combineddocument = new combined_document();
        $combineddocument->set_source_files($files);

        return $combineddocument;
    }

    /**
     * Fetch the current combined document ready for state checking.
     *
     * @param int|\review $review
     * @param int $userid
     * @param int $attemptnumber (-1 means latest attempt)
     * @return combined_document
     */
    public static function get_combined_document_for_attempt($review, $userid, $attemptnumber) {
        global $USER, $DB;

        $review = self::get_review_from_param($review);

        // Capability checks.
        if (!$review->can_view_submission($userid)) {
            print_error('nopermission');
        }

        $grade = $review->get_user_grade($userid, true, $attemptnumber);
        if ($review->get_instance()->teamsubmission) {
            $submission = $review->get_group_submission($userid, 0, false, $attemptnumber);
        } else {
            $submission = $review->get_user_submission($userid, false, $attemptnumber);
        }

        $contextid = $review->get_context()->id;
        $component = 'reviewfeedback_editpdf';
        $filearea = self::COMBINED_PDF_FILEAREA;
        $partialfilearea = self::PARTIAL_PDF_FILEAREA;
        $itemid = $grade->id;
        $filepath = '/';
        $filename = self::COMBINED_PDF_FILENAME;
        $fs = get_file_storage();

        $partialpdf = $fs->get_file($contextid, $component, $partialfilearea, $itemid, $filepath, $filename);
        if (!empty($partialpdf)) {
            $combinedpdf = $partialpdf;
        } else {
            $combinedpdf = $fs->get_file($contextid, $component, $filearea, $itemid, $filepath, $filename);
        }

        if ($combinedpdf && $submission) {
            if ($combinedpdf->get_timemodified() < $submission->timemodified) {
                // The submission has been updated since the PDF was generated.
                $combinedpdf = false;
            } else if ($combinedpdf->get_contenthash() == self::BLANK_PDF_HASH) {
                // The PDF is for a blank page.
                $combinedpdf = false;
            }
        }

        if (empty($combinedpdf)) {
            // The combined PDF does not exist yet. Return the list of files to be combined.
            return self::list_compatible_submission_files_for_attempt($review, $userid, $attemptnumber);
        } else {
            // The combined PDF aleady exists. Return it in a new combined_document object.
            $combineddocument = new combined_document();
            return $combineddocument->set_combined_file($combinedpdf);
        }
    }

    /**
     * This function return the combined pdf for all valid submission files.
     *
     * @param int|\review $review
     * @param int $userid
     * @param int $attemptnumber (-1 means latest attempt)
     * @return combined_document
     */
    public static function get_combined_pdf_for_attempt($review, $userid, $attemptnumber) {
        $document = self::get_combined_document_for_attempt($review, $userid, $attemptnumber);

        if ($document->get_status() === combined_document::STATUS_COMPLETE) {
            // The combined document is already ready.
            return $document;
        } else {
            // Attempt to combined the files in the document.
            $grade = $review->get_user_grade($userid, true, $attemptnumber);
            $document->combine_files($review->get_context()->id, $grade->id);
            return $document;
        }
    }

    /**
     * This function will return the number of pages of a pdf.
     *
     * @param int|\review $review
     * @param int $userid
     * @param int $attemptnumber (-1 means latest attempt)
     * @param bool $readonly When true we get the number of pages for the readonly version.
     * @return int number of pages
     */
    public static function page_number_for_attempt($review, $userid, $attemptnumber, $readonly = false) {
        global $CFG;

        require_once($CFG->libdir . '/pdflib.php');

        $review = self::get_review_from_param($review);

        if (!$review->can_view_submission($userid)) {
            print_error('nopermission');
        }

        // When in readonly we can return the number of images in the DB because they should already exist,
        // if for some reason they do not, then we proceed as for the normal version.
        if ($readonly) {
            $grade = $review->get_user_grade($userid, true, $attemptnumber);
            $fs = get_file_storage();
            $files = $fs->get_directory_files($review->get_context()->id, 'reviewfeedback_editpdf',
                self::PAGE_IMAGE_READONLY_FILEAREA, $grade->id, '/');
            $pagecount = count($files);
            if ($pagecount > 0) {
                return $pagecount;
            }
        }

        // Get a combined pdf file from all submitted pdf files.
        $document = self::get_combined_pdf_for_attempt($review, $userid, $attemptnumber);
        return $document->get_page_count();
    }

    /**
     * This function will generate and return a list of the page images from a pdf.
     * @param int|\review $review
     * @param int $userid
     * @param int $attemptnumber (-1 means latest attempt)
     * @param bool $resetrotation check if need to reset page rotation information
     * @return array(stored_file)
     */
    protected static function generate_page_images_for_attempt($review, $userid, $attemptnumber, $resetrotation = true) {
        global $CFG;

        require_once($CFG->libdir . '/pdflib.php');

        $review = self::get_review_from_param($review);

        if (!$review->can_view_submission($userid)) {
            print_error('nopermission');
        }

        // Need to generate the page images - first get a combined pdf.
        $document = self::get_combined_pdf_for_attempt($review, $userid, $attemptnumber);

        $status = $document->get_status();
        if ($status === combined_document::STATUS_FAILED) {
            print_error('Could not generate combined pdf.');
        } else if ($status === combined_document::STATUS_PENDING_INPUT) {
            // The conversion is still in progress.
            return [];
        }

        $tmpdir = \make_temp_directory('reviewfeedback_editpdf/pageimages/' . self::hash($review, $userid, $attemptnumber));
        $combined = $tmpdir . '/' . self::COMBINED_PDF_FILENAME;

        $document->get_combined_file()->copy_content_to($combined); // Copy the file.

        $pdf = new pdf();

        $pdf->set_image_folder($tmpdir);
        $pagecount = $pdf->set_pdf($combined);

        $grade = $review->get_user_grade($userid, true, $attemptnumber);

        $record = new \stdClass();
        $record->contextid = $review->get_context()->id;
        $record->component = 'reviewfeedback_editpdf';
        $record->filearea = self::PAGE_IMAGE_FILEAREA;
        $record->itemid = $grade->id;
        $record->filepath = '/';
        $fs = get_file_storage();

        // Remove the existing content of the filearea.
        $fs->delete_area_files($record->contextid, $record->component, $record->filearea, $record->itemid);

        $files = array();
        for ($i = 0; $i < $pagecount; $i++) {
            try {
                $image = $pdf->get_image($i);
                if (!$resetrotation) {
                    $pagerotation = page_editor::get_page_rotation($grade->id, $i);
                    $degree = !empty($pagerotation) ? $pagerotation->degree : 0;
                    if ($degree != 0) {
                        $filepath = $tmpdir . '/' . $image;
                        $imageresource = imagecreatefrompng($filepath);
                        $content = imagerotate($imageresource, $degree, 0);
                        imagepng($content, $filepath);
                    }
                }
            } catch (\moodle_exception $e) {
                // We catch only moodle_exception here as other exceptions indicate issue with setup not the pdf.
                $image = pdf::get_error_image($tmpdir, $i);
            }
            $record->filename = basename($image);
            $files[$i] = $fs->create_file_from_pathname($record, $tmpdir . '/' . $image);
            @unlink($tmpdir . '/' . $image);
            // Set page rotation default value.
            if (!empty($files[$i])) {
                if ($resetrotation) {
                    page_editor::set_page_rotation($grade->id, $i, false, $files[$i]->get_pathnamehash());
                }
            }
        }
        $pdf->Close(); // PDF loaded and never saved/outputted needs to be closed.

        @unlink($combined);
        @rmdir($tmpdir);

        return $files;
    }

    /**
     * This function returns a list of the page images from a pdf.
     *
     * The readonly version is different than the normal one. The readonly version contains a copy
     * of the pages in the state they were when the PDF was annotated, by doing so we prevent the
     * the pages that are displayed to change as soon as the submission changes.
     *
     * Though there is an edge case, if the PDF was annotated before MDL-45580, then it is possible
     * that we do not find any readonly version of the pages. In that case, we will get the normal
     * pages and copy them to the readonly area. This ensures that the pages will remain in that
     * state until the submission is updated. When the normal files do not exist, we throw an exception
     * because the readonly pages should only ever be displayed after a teacher has annotated the PDF,
     * they would not exist until they do.
     *
     * @param int|\review $review
     * @param int $userid
     * @param int $attemptnumber (-1 means latest attempt)
     * @param bool $readonly If true, then we are requesting the readonly version.
     * @return array(stored_file)
     */
    public static function get_page_images_for_attempt($review, $userid, $attemptnumber, $readonly = false) {
        global $DB;

        $review = self::get_review_from_param($review);

        if (!$review->can_view_submission($userid)) {
            print_error('nopermission');
        }

        if ($review->get_instance()->teamsubmission) {
            $submission = $review->get_group_submission($userid, 0, false, $attemptnumber);
        } else {
            $submission = $review->get_user_submission($userid, false, $attemptnumber);
        }
        $grade = $review->get_user_grade($userid, true, $attemptnumber);

        $contextid = $review->get_context()->id;
        $component = 'reviewfeedback_editpdf';
        $itemid = $grade->id;
        $filepath = '/';
        $filearea = self::PAGE_IMAGE_FILEAREA;

        $fs = get_file_storage();

        // If we are after the readonly pages...
        if ($readonly) {
            $filearea = self::PAGE_IMAGE_READONLY_FILEAREA;
            if ($fs->is_area_empty($contextid, $component, $filearea, $itemid)) {
                // We have a problem here, we were supposed to find the files.
                // Attempt to re-generate the pages from the combined images.
                self::generate_page_images_for_attempt($review, $userid, $attemptnumber);
                self::copy_pages_to_readonly_area($review, $grade);
            }
        }

        $files = $fs->get_directory_files($contextid, $component, $filearea, $itemid, $filepath);

        $pages = array();
        $resetrotation = false;
        if (!empty($files)) {
            $first = reset($files);
            $pagemodified = $first->get_timemodified();
            // Check that we don't just have a single blank page. The hash of a blank page image can vary with
            // the version of ghostscript used, so we need to examine the combined pdf it was generated from.
            $blankpage = false;
            if (!$readonly && count($files) == 1) {
                $pdfarea = self::COMBINED_PDF_FILEAREA;
                $pdfname = self::COMBINED_PDF_FILENAME;
                if ($pdf = $fs->get_file($contextid, $component, $pdfarea, $itemid, $filepath, $pdfname)) {
                    // The combined pdf may have a different hash if it has been regenerated since the page
                    // image was created. However if this is the case the page image will be stale anyway.
                    if ($pdf->get_contenthash() == self::BLANK_PDF_HASH || $pagemodified < $pdf->get_timemodified()) {
                        $blankpage = true;
                    }
                }
            }
            if (!$readonly && ($pagemodified < $submission->timemodified || $blankpage)) {
                // Image files are stale, we need to regenerate them, except in readonly mode.
                // We also need to remove the draft annotations and comments associated with this attempt.
                $fs->delete_area_files($contextid, $component, $filearea, $itemid);
                page_editor::delete_draft_content($itemid);
                $files = array();
                $resetrotation = true;
            } else {

                // Need to reorder the files following their name.
                // because get_directory_files() return a different order than generate_page_images_for_attempt().
                foreach ($files as $file) {
                    // Extract the page number from the file name image_pageXXXX.png.
                    preg_match('/page([\d]+)\./', $file->get_filename(), $matches);
                    if (empty($matches) or !is_numeric($matches[1])) {
                        throw new \coding_exception("'" . $file->get_filename()
                            . "' file hasn't the expected format filename: image_pageXXXX.png.");
                    }
                    $pagenumber = (int)$matches[1];

                    // Save the page in the ordered array.
                    $pages[$pagenumber] = $file;
                }
                ksort($pages);
            }
        }

        if (empty($pages)) {
            if ($readonly) {
                // This should never happen, there should be a version of the pages available
                // whenever we are requesting the readonly version.
                throw new \moodle_exception('Could not find readonly pages for grade ' . $grade->id);
            }
            $pages = self::generate_page_images_for_attempt($review, $userid, $attemptnumber, $resetrotation);
        }

        return $pages;
    }

    /**
     * This function returns sensible filename for a feedback file.
     * @param int|\review $review
     * @param int $userid
     * @param int $attemptnumber (-1 means latest attempt)
     * @return string
     */
    protected static function get_downloadable_feedback_filename($review, $userid, $attemptnumber) {
        global $DB;

        $review = self::get_review_from_param($review);

        $groupmode = groups_get_activity_groupmode($review->get_course_module());
        $groupname = '';
        if ($groupmode) {
            $groupid = groups_get_activity_group($review->get_course_module(), true);
            $groupname = groups_get_group_name($groupid).'-';
        }
        if ($groupname == '-') {
            $groupname = '';
        }
        $grade = $review->get_user_grade($userid, true, $attemptnumber);
        $user = $DB->get_record('user', array('id'=>$userid), '*', MUST_EXIST);

        if ($review->is_blind_marking()) {
            $prefix = $groupname . get_string('participant', 'review');
            $prefix = str_replace('_', ' ', $prefix);
            $prefix = clean_filename($prefix . '_' . $review->get_uniqueid_for_user($userid) . '_');
        } else {
            $prefix = $groupname . fullname($user);
            $prefix = str_replace('_', ' ', $prefix);
            $prefix = clean_filename($prefix . '_' . $review->get_uniqueid_for_user($userid) . '_');
        }
        $prefix .= $grade->attemptnumber;

        return $prefix . '.pdf';
    }

    /**
     * This function takes the combined pdf and embeds all the comments and annotations.
     *
     * This also moves the annotations and comments from drafts to not drafts. And it will
     * copy all the images stored to the readonly area, so that they can be viewed online, and
     * not be overwritten when a new submission is sent.
     *
     * @param int|\review $review
     * @param int $userid
     * @param int $attemptnumber (-1 means latest attempt)
     * @return stored_file
     */
    public static function generate_feedback_document($review, $userid, $attemptnumber) {

        $review = self::get_review_from_param($review);

        if (!$review->can_view_submission($userid)) {
            print_error('nopermission');
        }
        if (!$review->can_grade()) {
            print_error('nopermission');
        }

        // Need to generate the page images - first get a combined pdf.
        $document = self::get_combined_pdf_for_attempt($review, $userid, $attemptnumber);

        $status = $document->get_status();
        if ($status === combined_document::STATUS_FAILED) {
            print_error('Could not generate combined pdf.');
        } else if ($status === combined_document::STATUS_PENDING_INPUT) {
            // The conversion is still in progress.
            return false;
        }

        $file = $document->get_combined_file();

        $tmpdir = make_temp_directory('reviewfeedback_editpdf/final/' . self::hash($review, $userid, $attemptnumber));
        $combined = $tmpdir . '/' . self::COMBINED_PDF_FILENAME;
        $file->copy_content_to($combined); // Copy the file.

        $pdf = new pdf();

        $fs = get_file_storage();
        $stamptmpdir = make_temp_directory('reviewfeedback_editpdf/stamps/' . self::hash($review, $userid, $attemptnumber));
        $grade = $review->get_user_grade($userid, true, $attemptnumber);
        // Copy any new stamps to this instance.
        if ($files = $fs->get_area_files($review->get_context()->id,
                                         'reviewfeedback_editpdf',
                                         'stamps',
                                         $grade->id,
                                         "filename",
                                         false)) {
            foreach ($files as $file) {
                $filename = $stamptmpdir . '/' . $file->get_filename();
                $file->copy_content_to($filename); // Copy the file.
            }
        }

        $pagecount = $pdf->set_pdf($combined);
        $grade = $review->get_user_grade($userid, true, $attemptnumber);
        page_editor::release_drafts($grade->id);

        $allcomments = array();

        for ($i = 0; $i < $pagecount; $i++) {
            $pagerotation = page_editor::get_page_rotation($grade->id, $i);
            $pagemargin = $pdf->getBreakMargin();
            $autopagebreak = $pdf->getAutoPageBreak();
            if (empty($pagerotation) || !$pagerotation->isrotated) {
                $pdf->copy_page();
            } else {
                $rotatedimagefile = $fs->get_file_by_hash($pagerotation->pathnamehash);
                if (empty($rotatedimagefile)) {
                    $pdf->copy_page();
                } else {
                    $pdf->add_image_page($rotatedimagefile);
                }
            }

            $comments = page_editor::get_comments($grade->id, $i, false);
            $annotations = page_editor::get_annotations($grade->id, $i, false);

            if (!empty($comments)) {
                $allcomments[$i] = $comments;
            }

            foreach ($annotations as $annotation) {
                $pdf->add_annotation($annotation->x,
                                     $annotation->y,
                                     $annotation->endx,
                                     $annotation->endy,
                                     $annotation->colour,
                                     $annotation->type,
                                     $annotation->path,
                                     $stamptmpdir);
            }
            $pdf->SetAutoPageBreak($autopagebreak, $pagemargin);
            $pdf->setPageMark();
        }

        if (!empty($allcomments)) {
            // Append all comments to the end of the document.
            $links = $pdf->append_comments($allcomments);
            // Add the comment markers with links.
            foreach ($allcomments as $pageno => $comments) {
                foreach ($comments as $index => $comment) {
                    $pdf->add_comment_marker($comment->pageno, $index, $comment->x, $comment->y, $links[$pageno][$index],
                            $comment->colour);
                }
            }
        }

        fulldelete($stamptmpdir);

        $filename = self::get_downloadable_feedback_filename($review, $userid, $attemptnumber);
        $filename = clean_param($filename, PARAM_FILE);

        $generatedpdf = $tmpdir . '/' . $filename;
        $pdf->save_pdf($generatedpdf);

        $record = new \stdClass();

        $record->contextid = $review->get_context()->id;
        $record->component = 'reviewfeedback_editpdf';
        $record->filearea = self::FINAL_PDF_FILEAREA;
        $record->itemid = $grade->id;
        $record->filepath = '/';
        $record->filename = $filename;

        // Only keep one current version of the generated pdf.
        $fs->delete_area_files($record->contextid, $record->component, $record->filearea, $record->itemid);

        $file = $fs->create_file_from_pathname($record, $generatedpdf);

        // Cleanup.
        @unlink($generatedpdf);
        @unlink($combined);
        @rmdir($tmpdir);

        self::copy_pages_to_readonly_area($review, $grade);

        return $file;
    }

    /**
     * Copy the pages image to the readonly area.
     *
     * @param int|\review $review The review.
     * @param \stdClass $grade The grade record.
     * @return void
     */
    public static function copy_pages_to_readonly_area($review, $grade) {
        $fs = get_file_storage();
        $review = self::get_review_from_param($review);
        $contextid = $review->get_context()->id;
        $component = 'reviewfeedback_editpdf';
        $itemid = $grade->id;

        // Get all the pages.
        $originalfiles = $fs->get_area_files($contextid, $component, self::PAGE_IMAGE_FILEAREA, $itemid);
        if (empty($originalfiles)) {
            // Nothing to do here...
            return;
        }

        // Delete the old readonly files.
        $fs->delete_area_files($contextid, $component, self::PAGE_IMAGE_READONLY_FILEAREA, $itemid);

        // Do the copying.
        foreach ($originalfiles as $originalfile) {
            $fs->create_file_from_storedfile(array('filearea' => self::PAGE_IMAGE_READONLY_FILEAREA), $originalfile);
        }
    }

    /**
     * This function returns the generated pdf (if it exists).
     * @param int|\review $review
     * @param int $userid
     * @param int $attemptnumber (-1 means latest attempt)
     * @return stored_file
     */
    public static function get_feedback_document($review, $userid, $attemptnumber) {

        $review = self::get_review_from_param($review);

        if (!$review->can_view_submission($userid)) {
            print_error('nopermission');
        }

        $grade = $review->get_user_grade($userid, true, $attemptnumber);

        $contextid = $review->get_context()->id;
        $component = 'reviewfeedback_editpdf';
        $filearea = self::FINAL_PDF_FILEAREA;
        $itemid = $grade->id;
        $filepath = '/';

        $fs = get_file_storage();
        $files = $fs->get_area_files($contextid,
                                     $component,
                                     $filearea,
                                     $itemid,
                                     "itemid, filepath, filename",
                                     false);
        if ($files) {
            return reset($files);
        }
        return false;
    }

    /**
     * This function deletes the generated pdf for a student.
     * @param int|\review $review
     * @param int $userid
     * @param int $attemptnumber (-1 means latest attempt)
     * @return bool
     */
    public static function delete_feedback_document($review, $userid, $attemptnumber) {

        $review = self::get_review_from_param($review);

        if (!$review->can_view_submission($userid)) {
            print_error('nopermission');
        }
        if (!$review->can_grade()) {
            print_error('nopermission');
        }

        $grade = $review->get_user_grade($userid, true, $attemptnumber);

        $contextid = $review->get_context()->id;
        $component = 'reviewfeedback_editpdf';
        $filearea = self::FINAL_PDF_FILEAREA;
        $itemid = $grade->id;

        $fs = get_file_storage();
        return $fs->delete_area_files($contextid, $component, $filearea, $itemid);
    }

    /**
     * Get All files in a File area
     * @param int|\review $review Review
     * @param int $userid User ID
     * @param int $attemptnumber Attempt Number
     * @param string $filearea File Area
     * @param string $filepath File Path
     * @return array
     */
    private static function get_files($review, $userid, $attemptnumber, $filearea, $filepath = '/') {
        $grade = $review->get_user_grade($userid, true, $attemptnumber);
        $itemid = $grade->id;
        $contextid = $review->get_context()->id;
        $component = self::COMPONENT;
        $fs = get_file_storage();
        $files = $fs->get_directory_files($contextid, $component, $filearea, $itemid, $filepath);
        return $files;
    }

    /**
     * Save file.
     * @param int|\review $review Review
     * @param int $userid User ID
     * @param int $attemptnumber Attempt Number
     * @param string $filearea File Area
     * @param string $newfilepath File Path
     * @param string $storedfilepath stored file path
     * @return \stored_file
     * @throws \file_exception
     * @throws \stored_file_creation_exception
     */
    private static function save_file($review, $userid, $attemptnumber, $filearea, $newfilepath, $storedfilepath = '/') {
        $grade = $review->get_user_grade($userid, true, $attemptnumber);
        $itemid = $grade->id;
        $contextid = $review->get_context()->id;

        $record = new \stdClass();
        $record->contextid = $contextid;
        $record->component = self::COMPONENT;
        $record->filearea = $filearea;
        $record->itemid = $itemid;
        $record->filepath = $storedfilepath;
        $record->filename = basename($newfilepath);

        $fs = get_file_storage();

        $oldfile = $fs->get_file($record->contextid, $record->component, $record->filearea,
            $record->itemid, $record->filepath, $record->filename);

        $newhash = sha1($newfilepath);

        // Delete old file if exists.
        if ($oldfile && $newhash !== $oldfile->get_contenthash()) {
            $oldfile->delete();
        }

        return $fs->create_file_from_pathname($record, $newfilepath);
    }

    /**
     * This function rotate a page, and mark the page as rotated.
     * @param int|\review $review Review
     * @param int $userid User ID
     * @param int $attemptnumber Attempt Number
     * @param int $index Index of Current Page
     * @param bool $rotateleft To determine whether the page is rotated left or right.
     * @return null|\stored_file return rotated File
     * @throws \coding_exception
     * @throws \file_exception
     * @throws \moodle_exception
     * @throws \stored_file_creation_exception
     */
    public static function rotate_page($review, $userid, $attemptnumber, $index, $rotateleft) {
        $review = self::get_review_from_param($review);
        $grade = $review->get_user_grade($userid, true, $attemptnumber);
        // Check permission.
        if (!$review->can_view_submission($userid)) {
            print_error('nopermission');
        }

        $filearea = self::PAGE_IMAGE_FILEAREA;
        $files = self::get_files($review, $userid, $attemptnumber, $filearea);
        if (!empty($files)) {
            foreach ($files as $file) {
                preg_match('/' . pdf::IMAGE_PAGE . '([\d]+)\./', $file->get_filename(), $matches);
                if (empty($matches) or !is_numeric($matches[1])) {
                    throw new \coding_exception("'" . $file->get_filename()
                        . "' file hasn't the expected format filename: image_pageXXXX.png.");
                }
                $pagenumber = (int)$matches[1];

                if ($pagenumber == $index) {
                    $source = imagecreatefromstring($file->get_content());
                    $pagerotation = page_editor::get_page_rotation($grade->id, $index);
                    $degree = empty($pagerotation) ? 0 : $pagerotation->degree;
                    if ($rotateleft) {
                        $content = imagerotate($source, 90, 0);
                        $degree = ($degree + 90) % 360;
                    } else {
                        $content = imagerotate($source, -90, 0);
                        $degree = ($degree - 90) % 360;
                    }
                    $filename = $matches[0].'png';
                    $tmpdir = make_temp_directory(self::COMPONENT . '/' . self::PAGE_IMAGE_FILEAREA . '/'
                        . self::hash($review, $userid, $attemptnumber));
                    $tempfile = $tmpdir . '/' . time() . '_' . $filename;
                    imagepng($content, $tempfile);

                    $filearea = self::PAGE_IMAGE_FILEAREA;
                    $newfile = self::save_file($review, $userid, $attemptnumber, $filearea, $tempfile);

                    unlink($tempfile);
                    rmdir($tmpdir);
                    imagedestroy($source);
                    imagedestroy($content);
                    $file->delete();
                    if (!empty($newfile)) {
                        page_editor::set_page_rotation($grade->id, $pagenumber, true, $newfile->get_pathnamehash(), $degree);
                    }
                    return $newfile;
                }
            }
        }
        return null;
    }
}
