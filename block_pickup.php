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
 * Block definition class for the block_pickup plugin.
 *
 * @package   block_pickup
 * @copyright 2023 Stuart Lamour
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_completion\progress;

class block_pickup extends block_base {

    /**
     * Initialises the block.
     *
     * @return void
     */
    public function init() {
        global $USER;
        $this->title = get_string('pluginname', 'block_pickup');
    }

    /**
     * Gets the block settings.
     *
     */
    public function specialization() {
        if (isset($this->config->title)) {
            $this->title = $this->title = format_string($this->config->title, true, ['context' => $this->context]);
        } else {
            $this->title = get_string('pluginname', 'block_pickup');
        }
    }

    /**
     * Gets the block contents.
     *
     * @return stdClass - the block content.
     */
    public function get_content() : stdClass {
        global $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->footer = '';

        $template = new stdClass();
        $template->courses = $this->fetch_recent_courses();
        $itemcount = count($template->courses);

        // Hide the block when no content.
        if (!$itemcount) {
            // return $this->content;
        }

        $template->mods = $this->fetch_recent_mods();

        // Render from template.
        $this->content->text = $OUTPUT->render_from_template('block_pickup/content', $template);

        return $this->content;
    }

    /**
     *  Get recently accessed mods.
     *
     * @return array mods.
     */
    public function fetch_recent_mods() : array {
        global $DB, $USER, $CFG;
        /* TODO
        * logged in as, so change user id.
        */

        /* DB query. */
        $sql = "SELECT *
                  FROM {block_recentlyaccesseditems}
                 WHERE userid = :userid
              ORDER BY timeaccess DESC
                 LIMIT 4";

        $params = array(
            'userid' => $USER->id,
        );
        $mod_records = $DB->get_records_sql($sql, $params);

        // Template data for mustache.
        $template = new stdClass();

        foreach ($mod_records as $cm) {
            $contextmodule = context_module::instance($cm->cmid);
            $modinfo = get_fast_modinfo($cm->courseid)->get_cm($cm->cmid);
            $iconurl = get_fast_modinfo($cm->courseid)->get_cm($cm->cmid)->get_icon_url()->out(false);
            /*
             echo "<hr><h1>Mod stuff: </h1>";
            // var_dump($modinfo);
            var_dump($modinfo->name);
            echo "<hr>";
            */


            /* Template per mod. */
            $mod = new stdClass();
            $mod->name = $modinfo->name;
            $mod->type = $modinfo->modname;
            $mod->icon = $iconurl;
            $mod->purpose = plugin_supports('mod', $modinfo->modname, FEATURE_MOD_PURPOSE);
            $mod->url = $modinfo->url;
            $mod->coursename = $modinfo->get_course()->fullname;
            $template->mods[] = $mod;
        }

        return $template->mods;

    }

    /**
     *  Get recent courses.
     *
     * @return array courses.
     */
    public function fetch_recent_courses() : array {
        global $DB, $USER, $CFG;
        /* TODO
        * logged in as, so change user id.
        */


        /* DB query. */
        $sql = "SELECT c.*, cc.name
                  FROM {user_lastaccess} ula
                  JOIN {course} c ON c.id = ula.courseid
                  JOIN {course_categories} cc ON cc.id = c.category
                 WHERE ula.userid = :userid
              ORDER BY ula.timeaccess DESC
                 LIMIT 3";

        $params = array(
            'userid' => $USER->id,
        );
        $course_records = $DB->get_records_sql($sql, $params);

        // Template data for mustache.
        $template = new stdClass();

        foreach ($course_records as $cr) {
            /* Template per course. */
            $course = new stdClass();
            $course->fullname = $cr->fullname;
            $course->viewurl = new moodle_url('/course/view.php', array('id' => $cr->id));
            $course->coursecategory = $cr->name;

            // Progress.
            if ($cr->enablecompletion) {
                $percentage = \core_completion\progress::get_course_progress_percentage($cr, $USER->id);
                if (!is_null($percentage)) {
                    $percentage = floor($percentage);
                    $course->progress = $percentage;

                }
            }

            /* Course list item. */
            $cle = new \core_course_list_element($cr);

            /* Course image. */
            foreach ($cle->get_course_overviewfiles() as $file) {
                $course->courseimage = file_encode_url("$CFG->wwwroot/pluginfile.php",
                '/' . $file->get_contextid() .
                '/' . $file->get_component() .
                '/' . $file->get_filearea() . $file->get_filepath() . $file->get_filename());
            }

            $template->courses[] = $course;
        }

        // TODO - empty array if no data.

        // var_dump($template);

        return  $template->courses;
    }

    /**
     * Defines on which pages this block can be added.
     *
     * @return array of the pages where the block can be added.
     */
    public function applicable_formats() : array {
        return [
            'admin' => false,
            'site-index' => false,
            'course-view' => false,
            'mod' => false,
            'my' => true,
        ];
    }

    /**
     * Defines if the block can be added multiple times.
     *
     * @return bool.
     */
    public function instance_allow_multiple() : bool {
        return false;
    }
    /**
     * Defines if the has config.
     *
     * @return bool.
     */
    public function has_config() : bool {
        return false;
    }
}

