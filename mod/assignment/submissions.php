<?php  // $Id: submissions.php,v 1.43 2006/08/28 08:42:30 toyomoyo Exp $

    require_once("../../config.php");
    require_once("lib.php");

    $id   = optional_param('id', 0, PARAM_INT);          // Course module ID
    $a    = optional_param('a', 0, PARAM_INT);           // Assignment ID
    $mode = optional_param('mode', 'all', PARAM_ALPHA);  // What mode are we in?

    if ($id) {
        if (! $cm = get_coursemodule_from_id('assignment', $id)) {
            error("Course Module ID was incorrect");
        }

        if (! $assignment = get_record("assignment", "id", $cm->instance)) {
            error("assignment ID was incorrect");
        }

        if (! $course = get_record("course", "id", $assignment->course)) {
            error("Course is misconfigured");
        }
    } else {
        if (!$assignment = get_record("assignment", "id", $a)) {
            error("Course module is incorrect");
        }
        if (! $course = get_record("course", "id", $assignment->course)) {
            error("Course is misconfigured");
        }
        if (! $cm = get_coursemodule_from_instance("assignment", $assignment->id, $course->id)) {
            error("Course Module ID was incorrect");
        }
    }

    require_login($course->id, false, $cm);

    require_capability('mod/assignment:grade', get_context_instance(CONTEXT_MODULE, $cm->id));

/// Load up the required assignment code
    require($CFG->dirroot.'/mod/assignment/type/'.$assignment->assignmenttype.'/assignment.class.php');
    $assignmentclass = 'assignment_'.$assignment->assignmenttype;
    $assignmentinstance = new $assignmentclass($cm->id, $assignment, $cm, $course);

    /****************
     * Changes by Nigel Pegram 20101230
     * Workaround for problem that when "Enable Send for Marking" is set, the
     * edit/update form does not work properly.
     *
     * The basic problem seems to be two copies of the hidden mode field.
     * Instead of being set to "grade" it is set to "single".
     *
     * My workaround is to detect if the input buttons have been pressed and
     * reset the mode to "grade".
     */

    $feedback = data_submitted() ; //get incoming data
    if ($feedback){
        if ((!empty($feedback->cancel)) ||          // User hit cancel button
            (!empty ($feedback->submit)))           // user hit save changes button
        {
            $mode = "grade" ;
        }
        elseif (!empty($feedback->next)) {
            $mode = "next" ;
        }
        elseif (!empty ($feedback->saveandnext)) {
            $mode = "saveandnext" ;
        }
        elseif (!empty ($feedback->action)) {
            /** this has to go last since if "Enable send for marking" is set
             * action is always set to unfinalize.
             *
             * The "Revert to draft"/"No more submissions" button is not named, so does not have a
             * corresponding variable we can test for in $feedback. All we can
             * test for is a call to this function without any of the other
             * above vars being set.
             */
             if ($feedback->action == "unfinalize") {
                 $assignmentinstance->unfinalize() ;
                 $mode = "single" ; // should be right, but make sure.
             }
             elseif ($feedback->action == "finalizeclose") {
                 /**
                  * not sure of the difference between finalize and
                  * finalizeclose. For now, use method which matches param.
                  */
                 $assignmentinstance->finalizeclose() ;
                 $mode = "single" ; // should be right, but make sure.
             }
        }
    }

    /*
     * END CHANGES
     */

    $assignmentinstance->submissions($mode);   // Display or process the submissions

?>
