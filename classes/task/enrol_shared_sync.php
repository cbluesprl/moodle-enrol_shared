<?php
/**
 * This script is owned by CBlue SPRL, please contact CBlue regarding any licences issues.
 *
 * @package    enrol_shared
 * @copyright : CBlue SPRL 2021
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_shared\task;

use null_progress_trace;

defined('MOODLE_INTERNAL') || die();

class enrol_shared_sync extends \core\task\scheduled_task {

    /**
     * @return \lang_string|string
     * @throws \coding_exception
     */
    public function get_name() {
        return get_string('enrol_shared_sync', 'enrol_shared');
    }

    /**
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function execute() {
        /** @var \enrol_shared_plugin $enrol_plugin */
        $enrol_plugin = enrol_get_plugin('shared');
        $enrol_plugin->sync(new null_progress_trace());
    }
}
