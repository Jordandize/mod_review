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
 * Upgrade code for install
 *
 * @package   mod_review
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * upgrade this review instance - this function could be skipped but it will be needed later
 * @param int $oldversion The old version of the review module
 * @return bool
 */
function xmldb_review_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2017021500) {
        // Fix event types of review events.
        $params = [
            'modulename' => 'review',
            'eventtype' => 'close'
        ];
        $select = "modulename = :modulename AND eventtype = :eventtype";
        $DB->set_field_select('event', 'eventtype', 'due', $select, $params);

        // Delete 'open' events.
        $params = [
            'modulename' => 'review',
            'eventtype' => 'open'
        ];
        $DB->delete_records('event', $params);

        // Review savepoint reached.
        upgrade_mod_savepoint(true, 2017021500, 'review');
    }

    if ($oldversion < 2017031300) {
        // Add a 'gradingduedate' field to the 'review' table.
        $table = new xmldb_table('review');
        $field = new xmldb_field('gradingduedate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0, 'cutoffdate');

        // Conditionally launch add field.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Review savepoint reached.
        upgrade_mod_savepoint(true, 2017031300, 'review');
    }

    if ($oldversion < 2017042800) {
        // Update query to set the grading due date one week after the due date.
        // Only review instances with grading due date not set and with a due date of not older than 3 weeks will be updated.
        $sql = "UPDATE {review}
                   SET gradingduedate = duedate + :weeksecs
                 WHERE gradingduedate = 0
                       AND duedate > :timelimit";

        // Calculate the time limit, which is 3 weeks before the current date.
        $interval = new DateInterval('P3W');
        $timelimit = new DateTime();
        $timelimit->sub($interval);

        // Update query params.
        $params = [
            'weeksecs' => WEEKSECS,
            'timelimit' => $timelimit->getTimestamp()
        ];

        // Execute DB update for review instances.
        $DB->execute($sql, $params);

        // Review savepoint reached.
        upgrade_mod_savepoint(true, 2017042800, 'review');
    }

    // Automatically generated Moodle v3.3.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2017061200) {
        // Data fix any review group override event priorities which may have been accidentally nulled due to a bug on the group
        // overrides edit form.

        // First, find all review group override events having null priority (and join their corresponding review_overrides entry).
        $sql = "SELECT e.id AS id, o.sortorder AS priority
                  FROM {review_overrides} o
                  JOIN {event} e ON (e.modulename = 'review' AND o.reviewid = e.instance AND e.groupid = o.groupid)
                 WHERE o.groupid IS NOT NULL AND e.priority IS NULL
              ORDER BY o.id";
        $affectedrs = $DB->get_recordset_sql($sql);

        // Now update the event's priority based on the review_overrides sortorder we found. This uses similar logic to
        // review_refresh_events(), except we've restricted the set of reviews and overrides we're dealing with here.
        foreach ($affectedrs as $record) {
            $DB->set_field('event', 'priority', $record->priority, ['id' => $record->id]);
        }
        $affectedrs->close();

        // Main savepoint reached.
        upgrade_mod_savepoint(true, 2017061200, 'review');
    }

    if ($oldversion < 2017061205) {
        require_once($CFG->dirroot.'/mod/review/upgradelib.php');
        $brokenreviews = get_reviews_with_rescaled_null_grades();

        // Set config value.
        foreach ($brokenreviews as $review) {
            set_config('has_rescaled_null_grades_' . $review, 1, 'review');
        }

        // Main savepoint reached.
        upgrade_mod_savepoint(true, 2017061205, 'review');
    }

    // Automatically generated Moodle v3.4.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v3.5.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v3.6.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2018120500) {
        // Define field hidegrader to be added to review.
        $table = new xmldb_table('review');
        $field = new xmldb_field('hidegrader', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'blindmarking');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Review savepoint reached.
        upgrade_mod_savepoint(true, 2018120500, 'review');
    }

    // Automatically generated Moodle v3.7.0 release upgrade line.
    // Put any upgrade step following this.

    return true;
}
