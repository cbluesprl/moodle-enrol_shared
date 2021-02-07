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

class enrol_shared_sync extends \core\task\scheduled_task
{

    public function get_name()
    {
        return get_string('enrol_shared_sync', 'enrol_shared');
    }

    public function execute()
    {
        $enrol_plugin = enrol_get_plugin('shared');
        $enrol_plugin->sync(new null_progress_trace());
    }
}
