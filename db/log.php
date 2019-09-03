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
 * Definition of log events
 *
 * @package   mod_review
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$logs = array(
    array('module'=>'review', 'action'=>'add', 'mtable'=>'review', 'field'=>'name'),
    array('module'=>'review', 'action'=>'delete mod', 'mtable'=>'review', 'field'=>'name'),
    array('module'=>'review', 'action'=>'download all submissions', 'mtable'=>'review', 'field'=>'name'),
    array('module'=>'review', 'action'=>'grade submission', 'mtable'=>'review', 'field'=>'name'),
    array('module'=>'review', 'action'=>'lock submission', 'mtable'=>'review', 'field'=>'name'),
    array('module'=>'review', 'action'=>'reveal identities', 'mtable'=>'review', 'field'=>'name'),
    array('module'=>'review', 'action'=>'revert submission to draft', 'mtable'=>'review', 'field'=>'name'),
    array('module'=>'review', 'action'=>'set marking workflow state', 'mtable'=>'review', 'field'=>'name'),
    array('module'=>'review', 'action'=>'submission statement accepted', 'mtable'=>'review', 'field'=>'name'),
    array('module'=>'review', 'action'=>'submit', 'mtable'=>'review', 'field'=>'name'),
    array('module'=>'review', 'action'=>'submit for grading', 'mtable'=>'review', 'field'=>'name'),
    array('module'=>'review', 'action'=>'unlock submission', 'mtable'=>'review', 'field'=>'name'),
    array('module'=>'review', 'action'=>'update', 'mtable'=>'review', 'field'=>'name'),
    array('module'=>'review', 'action'=>'upload', 'mtable'=>'review', 'field'=>'name'),
    array('module'=>'review', 'action'=>'view', 'mtable'=>'review', 'field'=>'name'),
    array('module'=>'review', 'action'=>'view all', 'mtable'=>'course', 'field'=>'fullname'),
    array('module'=>'review', 'action'=>'view confirm submit review form', 'mtable'=>'review', 'field'=>'name'),
    array('module'=>'review', 'action'=>'view grading form', 'mtable'=>'review', 'field'=>'name'),
    array('module'=>'review', 'action'=>'view submission', 'mtable'=>'review', 'field'=>'name'),
    array('module'=>'review', 'action'=>'view submission grading table', 'mtable'=>'review', 'field'=>'name'),
    array('module'=>'review', 'action'=>'view submit review form', 'mtable'=>'review', 'field'=>'name'),
    array('module'=>'review', 'action'=>'view feedback', 'mtable'=>'review', 'field'=>'name'),
    array('module'=>'review', 'action'=>'view batch set marking workflow state', 'mtable'=>'review', 'field'=>'name'),
);
