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
 * This file contains the definition for the library class for edit PDF renderer.
 *
 * @package   reviewfeedback_editpdf
 * @copyright 2012 Davo Smith
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * A custom renderer class that extends the plugin_renderer_base and is used by the editpdf feedback plugin.
 *
 * @package reviewfeedback_editpdf
 * @copyright 2013 Davo Smith
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reviewfeedback_editpdf_renderer extends plugin_renderer_base {

    /**
     * Return the PDF button shortcut.
     *
     * @param string $name the name of a specific button.
     * @return string the specific shortcut.
     */
    private function get_shortcut($name) {

        $shortcuts = array('navigate-previous-button' => 'j',
            'rotateleft' => 'q',
            'rotateright' => 'w',
            'navigate-page-select' => 'k',
            'navigate-next-button' => 'l',
            'searchcomments' => 'h',
            'expcolcomments' => 'g',
            'comment' => 'z',
            'commentcolour' => 'x',
            'select' => 'c',
            'drag' => 'd',
            'pen' => 'y',
            'line' => 'u',
            'rectangle' => 'i',
            'oval' => 'o',
            'highlight' => 'p',
            'annotationcolour' => 'r',
            'stamp' => 'n',
            'currentstamp' => 'm');


        // Return the shortcut.
        return $shortcuts[$name];
    }

    /**
     * Render a single colour button.
     *
     * @param string $icon - The key for the icon
     * @param string $tool - The key for the lang string.
     * @param string $accesskey Optional - The access key for the button.
     * @param bool $disabled Optional - Is this button disabled.
     * @return string
     */
    private function render_toolbar_button($icon, $tool, $accesskey = null, $disabled=false) {

        // Build button alt text.
        $alttext = new stdClass();
        $alttext->tool = get_string($tool, 'reviewfeedback_editpdf');
        if (!empty($accesskey)) {
            $alttext->shortcut = '(Alt/Shift-Alt/Ctrl-Option + ' . $accesskey . ')';
        } else {
            $alttext->shortcut = '';
        }
        $iconalt = get_string('toolbarbutton', 'reviewfeedback_editpdf', $alttext);

        $iconhtml = $this->image_icon($icon, $iconalt, 'reviewfeedback_editpdf');
        $iconparams = array('data-tool'=>$tool, 'class'=>$tool . 'button');
        if ($disabled) {
            $iconparams['disabled'] = 'true';
        }
        if (!empty($accesskey)) {
            $iconparams['accesskey'] = $accesskey;
        }

        return html_writer::tag('button', $iconhtml, $iconparams);
    }

    /**
     * Render the editpdf widget in the grading form.
     *
     * @param reviewfeedback_editpdf_widget $widget - Renderable widget containing review, user and attempt number.
     * @return string
     */
    public function render_reviewfeedback_editpdf_widget(reviewfeedback_editpdf_widget $widget) {
        global $CFG;

        $html = '';

        $html .= html_writer::div(get_string('jsrequired', 'reviewfeedback_editpdf'), 'hiddenifjs');
        $linkid = html_writer::random_id();
        if ($widget->readonly) {
            $launcheditorlink = html_writer::tag('a',
                                              get_string('viewfeedbackonline', 'reviewfeedback_editpdf'),
                                              array('id'=>$linkid, 'class'=>'btn', 'href'=>'#'));
        } else {
            $launcheditorlink = html_writer::tag('a',
                                              get_string('launcheditor', 'reviewfeedback_editpdf'),
                                              array('id'=>$linkid, 'class'=>'btn', 'href'=>'#'));
        }
        $links = $launcheditorlink;
        $html .= '<input type="hidden" name="reviewfeedback_editpdf_haschanges" value="false"/>';

        $html .= html_writer::div($links, 'visibleifjs');
        $header = get_string('pluginname', 'reviewfeedback_editpdf');
        $body = '';
        // Create the page navigation.
        $navigation1 = '';
        $navigation2 = '';
        $navigation3 = '';

        // Pick the correct arrow icons for right to left mode.
        if (right_to_left()) {
            $nav_prev = 'nav_next';
            $nav_next = 'nav_prev';
        } else {
            $nav_prev = 'nav_prev';
            $nav_next = 'nav_next';
        }

        $iconshortcut = $this->get_shortcut('navigate-previous-button');
        $iconalt = get_string('navigateprevious', 'reviewfeedback_editpdf', $iconshortcut);
        $iconhtml = $this->image_icon($nav_prev, $iconalt, 'reviewfeedback_editpdf');
        $navigation1 .= html_writer::tag('button', $iconhtml, array('disabled'=>'true',
            'class'=>'navigate-previous-button', 'accesskey' => $this->get_shortcut('navigate-previous-button')));
        $navigation1 .= html_writer::tag('select', null, array('disabled'=>'true',
            'aria-label' => get_string('gotopage', 'reviewfeedback_editpdf'), 'class'=>'navigate-page-select',
            'accesskey' => $this->get_shortcut('navigate-page-select')));
        $iconshortcut = $this->get_shortcut('navigate-next-button');
        $iconalt = get_string('navigatenext', 'reviewfeedback_editpdf', $iconshortcut);
        $iconhtml = $this->image_icon($nav_next, $iconalt, 'reviewfeedback_editpdf');
        $navigation1 .= html_writer::tag('button', $iconhtml, array('disabled'=>'true',
            'class'=>'navigate-next-button', 'accesskey' => $this->get_shortcut('navigate-next-button')));

        $navigation1 = html_writer::div($navigation1, 'navigation', array('role'=>'navigation'));

        $navigation2 .= $this->render_toolbar_button('comment_search', 'searchcomments', $this->get_shortcut('searchcomments'));
        $navigation2 = html_writer::div($navigation2, 'navigation-search', array('role'=>'navigation'));

        $navigation3 .= $this->render_toolbar_button('comment_expcol', 'expcolcomments', $this->get_shortcut('expcolcomments'));
        $navigation3 = html_writer::div($navigation3, 'navigation-expcol', array('role' => 'navigation'));

        $rotationtools = '';
        if (!$widget->readonly) {
            $rotationtools .= $this->render_toolbar_button('rotate_left', 'rotateleft', $this->get_shortcut('rotateleft'));
            $rotationtools .= $this->render_toolbar_button('rotate_right', 'rotateright', $this->get_shortcut('rotateright'));
            $rotationtools = html_writer::div($rotationtools, 'toolbar', array('role' => 'toolbar'));
        }

        $toolbargroup = '';
        $clearfix = html_writer::div('', 'clearfix');
        if (!$widget->readonly) {
            // Comments.
            $toolbar1 = '';
            $toolbar1 .= $this->render_toolbar_button('comment', 'comment', $this->get_shortcut('comment'));
            $toolbar1 .= $this->render_toolbar_button('background_colour_clear', 'commentcolour', $this->get_shortcut('commentcolour'));
            $toolbar1 = html_writer::div($toolbar1, 'toolbar', array('role' => 'toolbar'));

            // Select Tool.
            $toolbar2 = '';
            $toolbar2 .= $this->render_toolbar_button('drag', 'drag', $this->get_shortcut('drag'));
            $toolbar2 .= $this->render_toolbar_button('select', 'select', $this->get_shortcut('select'));
            $toolbar2 = html_writer::div($toolbar2, 'toolbar', array('role' => 'toolbar'));

            // Other Tools.
            $toolbar3 = '';
            $toolbar3 .= $this->render_toolbar_button('pen', 'pen', $this->get_shortcut('pen'));
            $toolbar3 .= $this->render_toolbar_button('line', 'line', $this->get_shortcut('line'));
            $toolbar3 .= $this->render_toolbar_button('rectangle', 'rectangle', $this->get_shortcut('rectangle'));
            $toolbar3 .= $this->render_toolbar_button('oval', 'oval', $this->get_shortcut('oval'));
            $toolbar3 .= $this->render_toolbar_button('highlight', 'highlight', $this->get_shortcut('highlight'));
            $toolbar3 .= $this->render_toolbar_button('background_colour_clear', 'annotationcolour', $this->get_shortcut('annotationcolour'));
            $toolbar3 = html_writer::div($toolbar3, 'toolbar', array('role' => 'toolbar'));

            // Stamps.
            $toolbar4 = '';
            $toolbar4 .= $this->render_toolbar_button('stamp', 'stamp', $this->get_shortcut('stamp'));
            $toolbar4 .= $this->render_toolbar_button('background_colour_clear', 'currentstamp', $this->get_shortcut('currentstamp'));
            $toolbar4 = html_writer::div($toolbar4, 'toolbar', array('role'=>'toolbar'));

            // Add toolbars to toolbar_group in order of display, and float the toolbar_group right.
            $toolbars = $rotationtools . $toolbar1 . $toolbar2 . $toolbar3 . $toolbar4;
            $toolbargroup = html_writer::div($toolbars, 'toolbar_group', array('role' => 'toolbar_group'));
        }

        $pageheader = html_writer::div($navigation1 .
                                       $navigation2 .
                                       $navigation3 .
                                       $toolbargroup .
                                       $clearfix,
                                       'pageheader');
        $body = $pageheader;

        // Loading progress bar.
        $progressbar = html_writer::div('', 'bar', array('style' => 'width: 0%'));
        $progressbar = html_writer::div($progressbar, 'progress progress-info progress-striped active',
            array('title' => get_string('loadingeditor', 'reviewfeedback_editpdf'),
                  'role'=> 'progressbar', 'aria-valuenow' => 0, 'aria-valuemin' => 0,
                  'aria-valuemax' => 100));
        $progressbarlabel = html_writer::div(get_string('generatingpdf', 'reviewfeedback_editpdf'),
            'progressbarlabel');
        $loading = html_writer::div($progressbar . $progressbarlabel, 'loading');

        $canvas = html_writer::div($loading, 'drawingcanvas');
        $canvas = html_writer::div($canvas, 'drawingregion');
        // Place for messages, but no warnings displayed yet.
        $changesmessage = html_writer::div('', 'warningmessages');
        $canvas .= $changesmessage;

        $infoicon = $this->image_icon('i/info', '');
        $infomessage = html_writer::div($infoicon, 'infoicon');
        $canvas .= $infomessage;

        $body .= $canvas;

        $footer = '';

        $editorparams = array(
            array(
                'header' => $header,
                'body' => $body,
                'footer' => $footer,
                'linkid' => $linkid,
                'reviewid' => $widget->review,
                'userid' => $widget->userid,
                'attemptnumber' => $widget->attemptnumber,
                'stampfiles' => $widget->stampfiles,
                'readonly' => $widget->readonly,
            )
        );

        $this->page->requires->yui_module('moodle-reviewfeedback_editpdf-editor',
                                          'M.reviewfeedback_editpdf.editor.init',
                                          $editorparams);

        $this->page->requires->strings_for_js(array(
            'yellow',
            'white',
            'red',
            'blue',
            'green',
            'black',
            'clear',
            'colourpicker',
            'loadingeditor',
            'pagexofy',
            'deletecomment',
            'addtoquicklist',
            'filter',
            'searchcomments',
            'commentcontextmenu',
            'deleteannotation',
            'stamp',
            'stamppicker',
            'cannotopenpdf',
            'pagenumber',
            'partialwarning',
            'draftchangessaved'
        ), 'reviewfeedback_editpdf');

        return $html;
    }
}
