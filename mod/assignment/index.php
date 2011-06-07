<?php // $Id: index.php,v 1.35.2.5 2008/03/11 14:24:12 thepurpleblob Exp $

    require_once("../../config.php");
    require_once("lib.php");
    require_once($CFG->libdir.'/gradelib.php');

    $id = required_param('id', PARAM_INT);   // course

    if (! $course = get_record("course", "id", $id)) {
        error("Course ID is incorrect");
    }

    require_course_login($course);
    add_to_log($course->id, "assignment", "view all", "index.php?id=$course->id", "");

    $strassignments = get_string("modulenameplural", "assignment");
    $strassignment = get_string("modulename", "assignment");
    $strassignmenttype = get_string("assignmenttype", "assignment");
    $strweek = get_string("week");
    $strtopic = get_string("topic");
    $strname = get_string("name");
    $strduedate = get_string("duedate", "assignment");
    $strsubmitted = get_string("submitted", "assignment");
    $strgrade = get_string("grade");
    $strrubric = get_string("rubric", "assignment");
    $strviewrubrics = get_string("viewrubrics", "assignment");
    $strpoints = get_string("points", "assignment");

    $context = get_context_instance(CONTEXT_COURSE,$id);
    $types = assignment_types();

    $allowhidden = has_capability('moodle/course:viewhiddensections', $context);
    $allowgrader = has_capability('mod/assignment:grade', $context);

    /* Builds New assignment selectbox */
    $add_new_assignment_form = '';
    if($sections = get_all_sections($course->id)){ // no sections

        $options_week = '';
        foreach($sections as $section){
            if($allowhidden or $section->visible){
                $week = $section->section;
                $text = "Week $week";
                if($week == 0) $text = 'Fixed';
                $options_week .= "<option value=\"$week\">$text</option>\n";
            }
        }

        $options_type = ""; 
        while(list($value,$text) = each($types)){
            $options_type .= "<option value=\"$value\">$text</option>\n";
        }

        $add_new_assignment_form = 
            "<br /><center>".
            "<form action=\"{$CFG->wwwroot}/course/mod.php\">".
            "<select name=\"section\">\n$options_week</select>".
            "<select name=\"type\">\n$options_type</select>".
            '<input type="hidden" name="id" value="'.$course->id.'" />'.
            '<input type="hidden" name="sesskey" vSubmittedalue="'.sesskey ().'" />'.
            '<input type="hidden" name="add" value="assignment" />'.
            '<input type="submit" value="New Assignment" style="margin-left:20px" />'.
            "</form></center>";
    }

    $navlinks = array();
    $navlinks[] = array('name' => $strassignments, 'link' => '', 'type' => 'activity');
    $navigation = build_navigation($navlinks);

    print_header_simple($strassignments, "", $navigation, "", "", true, 
        ($allowgrader
            ? "<div class=\"breadcrumb\"><a href=\"{$CFG->wwwroot}/mod/assignment/rubric/index.php?id={$course->id}\">$strviewrubrics</a></div>"
            : ""), navmenu($course));

    if (!$cms = get_coursemodules_in_course('assignment', $course->id, 'm.assignmenttype, m.timedue')) {
        notice(get_string('noassignments', 'assignment'), "../../course/view.php?id=$course->id");
        die;
    }

    $timenow = time();

    if ($course->format == "weeks") {
        $table->head  = array ($strweek, $strname, $strassignmenttype, $strduedate, $strsubmitted, $strgrade);
        $table->align = array ("center", "left", "left", "left", "right");
    } else if ($course->format == "topics") {
        $table->head  = array ($strtopic, $strname, $strassignmenttype, $strduedate, $strsubmitted, $strgrade);
        $table->align = array ("center", "left", "left", "left", "right");
    } else {
        $table->head  = array ($strname, $strassignmenttype, $strduedate, $strsubmitted, $strgrade);
        $table->align = array ("left", "left", "left", "right");
    }

    $currentsection = "";

    $modinfo = get_fast_modinfo($course);
    foreach ($modinfo->instances['assignment'] as $cm) {
        if (!$cm->uservisible) {
            continue;
        }

        $cm->timedue        = $cms[$cm->id]->timedue;
        $cm->assignmenttype = $cms[$cm->id]->assignmenttype;

        //Show dimmed if the mod is hidden
        $class = $cm->visible ? '' : 'class="dimmed"';

        $link = "<a $class href=\"view.php?id=$cm->id\">".format_string($cm->name)."</a>";

        $printsection = "";
        if ($cm->sectionnum !== $currentsection) {
            if ($cm->sectionnum) {
                $printsection = $cm->sectionnum;
            }
            if ($currentsection !== "") {
                $table->data[] = 'hr';
            }
            $currentsection = $cm->sectionnum;
        }

        if (!file_exists($CFG->dirroot.'/mod/assignment/type/'.$cm->assignmenttype.'/assignment.class.php')) {
            continue;
        }

        require_once ($CFG->dirroot.'/mod/assignment/type/'.$cm->assignmenttype.'/assignment.class.php');
        $assignmentclass = 'assignment_'.$cm->assignmenttype;
        $assignmentinstance = new $assignmentclass($cm->id, NULL, $cm, $course);

        $submitted = $assignmentinstance->submittedlink(true);

        $grading_info = grade_get_grades($course->id, 'mod', 'assignment', $cm->instance, $USER->id);
        if (isset($grading_info->items[0])) {
            $grade = $grading_info->items[0]->grades[$USER->id]->str_grade;
        }
        else {
            $grade = '-';
        }

        $type = $types[$cm->assignmenttype];

        $due = $cm->timedue ? userdate($cm->timedue) : '-';
        $points = $assignmentinstance->assignment->grade;
        $points = $points < 0 ? '-' : $points;

        if ($course->format == "weeks" or $course->format == "topics") {
            if ($allowgrader && $assignmentinstance->assignment->rubricid) {
                $rubriclink = "$points (<a href=\"rubric/index.php?id=$id&rubric={$assignmentinstance->rubric->id}\">".
                              "{$assignmentinstance->rubric->name}</a>)";
                $table->data[] = array ($printsection, $link, $type, $due, $submitted, "$grade / $rubriclink");
            } else {
                $table->data[] = array ($printsection, $link, $type, $due, $submitted, "$grade / $points");
            }
        } else {
            $table->data[] = array ($link, $type, $due, $submitted, "$grade / $points");
        }
    }

    echo "<br />";

    print_table($table);

    if ($allowgrader) echo "$add_new_assignment_form";

    print_footer($course);

?>
