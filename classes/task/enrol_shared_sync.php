<?php
/**
 * This script is owned by CBlue SPRL, please contact CBlue regarding any licences issues.
 * @author : xinghels@cblue.be
 * @date: 07.10.19
 * @copyright : CBlue SPRL
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
