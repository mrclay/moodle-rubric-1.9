<?php // $Id: index.php,v 1.28.2.1 2007/05/15 18:27:13 skodak Exp $

    require_once("../../../config.php");
    require_once("../lib.php");

    global $CFG;

    $id = required_param('id', PARAM_INT);   // course
    $rubricid = optional_param('rubric', 0, PARAM_INT);   // rubric id

    if (! $course = get_record("course", "id", $id)) {
        error("Course ID is incorrect");
    }

    require_course_login($course);

    $context = get_context_instance(CONTEXT_COURSE, $id);
    require_capability('mod/assignment:grade', $context);

    $strassignments = get_string("modulenameplural", "assignment");
    $strrubrics = get_string("rubrics", "assignment");

    $navlinks = array();
    $navlinks[] = array('name' => $strassignments, 'link' => "{$CFG->wwwroot}/mod/assignment/index.php?id={$course->id}", 'type' => 'activity');

    if(!empty($rubricid)){

        // display a specific rubric

        $rubric = new rubric($rubricid);

        add_to_log($course->id, "assignment", "view rubric (id=$rubricid)", "rubric/index.php?id=$course->id&rubric=$rubricid", "");
        
        $update_rubric_form = 
            "<form method=\"post\" action=\"{$CFG->wwwroot}/mod/assignment/rubric/mod.php\">\n".
            '<input type="hidden" name="course" value="'.$course->id.'" />'.
            '<input type="hidden" name="rubric" value="'.$rubric->id.'" />'.
            '<input type="hidden" name="sesskey" value="'.sesskey().'" />'.
            '<input type="hidden" name="action" value="update" />'.
            '<input type="submit" value="Update Rubric" />'.
            "</form>\n";

        $navlinks[] = array('name' => $strrubrics, 'link' => "{$CFG->wwwroot}/mod/assignment/rubric/index.php?id={$course->id}", 'type' => 'misc');
        $navlinks[] = array('name' => $rubric->name, 'link' => '', 'type' => 'misc');
        print_header_simple("$strassignments - $strrubrics: {$rubric->name}", "", build_navigation($navlinks), "", "", true, $update_rubric_form, navmenu($course));

        echo '<br />';
        $rubric->view('',true,true,true,10,'62%');

    }else{

        // display all rubrics

        add_to_log($course->id, "assignment", "view all rubrics", "rubric/index.php?id=$course->id", "");

        $strassignment = get_string("modulename", "assignment");
        $strrubric = get_string("rubric", "assignment");
        $strusedby = get_string("usedby", "assignment");
        $strcreationdate = get_string("creationdate", "assignment");
        $strweek = get_string("week");
        $strname = get_string("name");
        $strcreatedby = get_string("createdby", "assignment");
        $strpoints = get_string("points", "assignment");
        $strdelete = get_string("delete", "assignment");
        $stroperations = get_string("operations", "assignment");
        $strinuse = get_string("inuse", "assignment");
        $strnodelete = get_string("nodelete", "assignment");

        $navlinks[] = array('name' => $strrubrics, 'link' => '', 'type' => 'misc');
        print_header_simple("$strassignments - $strrubrics", "", build_navigation($navlinks), "", "", true, "", navmenu($course));

        $add_new_rubric_form = 
            "<form method=\"post\" action=\"{$CFG->wwwroot}/mod/assignment/rubric/mod.php\">\n".
            '<input type="hidden" name="course" value="'.$course->id.'" />'.
            '<input type="hidden" name="sesskey" value="'.sesskey().'" />'.
            '<input type="hidden" name="action" value="create" />'.
            '<input type="submit" value="New Rubric" />'.
            "</form>\n";

        $import_rubric_form = // .. from backup/converted files and such
            "<form method=\"post\" action=\"{$CFG->wwwroot}/mod/assignment/rubric/mod.php\">\n".
            '<input type="hidden" name="course" value="'.$course->id.'" />'.
            '<input type="hidden" name="sesskey" value="'.sesskey().'" />'.
            '<input type="hidden" name="action" value="load" />'.
            '<input type="submit" value="Import from file" />'.
            "</form>\n";

        // show only disabled select boxes if there are no other rubrics available to duplication 
        $options = get_rubrics_as_options($course->id);
        if (empty($options)) {

            // nothing to show ...
            $copy_rubric_form = choose_from_menu(array(), '', '', 'Add rubric to course', '', 0, true, true);

        } else {

            $copy_rubric_form = 
                "<div style=\"white-space:nowrap\"><form method=\"post\" name=\"duplicate\" 
                    action=\"{$CFG->wwwroot}/mod/assignment/rubric/mod.php\">\n".
                choose_from_menu($options, 'rubric', '', 'Copy/Duplicate rubric', 'submitform()', 0, true).
                '<input type="submit" id="mod-submit-button" value="Go" />'.
                '<input type="hidden" name="course" value="'.$course->id.'" />'.
                '<input type="hidden" name="sesskey" value="'.sesskey().'" />'.
                '<input type="hidden" name="action" value="copy" />'.
                "</form></div>\n".
                "<script type=\"text/javascript\">\n".
                "document.getElementById('mod-submit-button').style.display = 'none';".
                '</script>';
        }

        // no rubrics in course
        if (!$rubrics = rubric_get_list_in_course($course->id)) {

            echo '<br />';
            print_box_start('generalbox', 'notice');
            print_heading(get_string('norubrics','assignment'));
            print_box_end();

        // print out rubrics
        }else{

            // Javascript's confirm delete
            echo '<script type="text/javascript">
                    function confirmDelete(name, pts) {
                      return confirm("Are you sure you want to delete rubric:\n\n\t"+name+" ("+pts+" points)");
                    }
                  </script>';

            $table->head  = array ($strname, $strpoints, $strcreatedby, $strcreationdate, $strinuse, '');
            $table->align = array ("center", "center", "center", "center", "center", "center");
            $table->size = array( null, null, null, null, null, '170px' );

            foreach($rubrics as $rubric){

                $rubricname = "<a href=\"index.php?id={$course->id}&rubric={$rubric->id}\">{$rubric->name}</a>";
                $user = "<a href=\"{$CFG->wwwroot}/user/view.php?id={$rubric->userid}\">{$rubric->first} {$rubric->last}</a>";
                $count = ($rubric->count == 1 ? "1 $strassignment" : "{$rubric->count} $strassignments");

                $update_rubric_form = 
                    "<form method=\"post\" action=\"{$CFG->wwwroot}/mod/assignment/rubric/mod.php\">\n".
                    '<input type="hidden" name="course" value="'.$course->id.'" />'.
                    '<input type="hidden" name="rubric" value="'.$rubric->id.'" />'.
                    '<input type="hidden" name="sesskey" value="'.sesskey().'" />'.
                    '<input type="hidden" name="action" value="update" />'.
                    '<input type="submit" value="Update" style="font-size:8pt" />'.
                    "</form>\n";

                $delete_rubric_form = 
                    "<form method=\"post\" onsubmit=\"return confirmDelete('".
                        escHTML(str_replace("'","\\'",str_replace("\\","\\\\",$rubric->name)))."',{$rubric->points})\" 
                        action=\"{$CFG->wwwroot}/mod/assignment/rubric/mod.php\">\n".
                    '<input type="hidden" name="course" value="'.$course->id.'" />'.
                    '<input type="hidden" name="rubric" value="'.$rubric->id.'" />'.
                    '<input type="hidden" name="sesskey" value="'.sesskey().'" />'.
                    '<input type="hidden" name="action" value="delete" />'.
                    '<input type="submit" value="Delete" style="font-size:8pt" />'.
                    "</form>\n";

                $xml_link = "<div style=\"float:left\">
                                <a href=\"mod.php?action=xml&rubric={$rubric->id}&course={$course->id}&sesskey=".sesskey()."\">
                                    <img src=\"pix/xml.gif\" width=\"36\" height=\"20\" />
                                </a>
                             </div>";

                $table->data[] = array ($rubricname, $rubric->points, $user, userdate($rubric->timemodified), $count, 
                                        "<table><tr style=\"padding:0;margin:0;vertical-align:top\"><td style=\"padding-right:5px;vertical-align:top\">$update_rubric_form</td>
                                                    <td style=\"padding-right:5px\">$delete_rubric_form</td>
                                                    <td>$xml_link</td></tr></table>");
            }

            echo "<br />";

            print_table($table);
        }

        // Buttons
        echo "<br />
                <table style=\"width:100%;text-align:center\">
                   <tr><td align=\"right\" style=\"width:48%\">$add_new_rubric_form</td>
                       <td align=\"center\" style=\"width:0;padding-left:20px;padding-right:20px\">$import_rubric_form</td>
                       <td align=\"left\" style=\"width:52%\">$copy_rubric_form</td></tr>
                </table>";

        // Get name for duplicates
        echo '<script type="text/javascript">
                  submitform = function(){
                      var obj = document.forms.duplicate.rubric;
                      if(obj.selectedIndex == 0) return;
                      document.forms.duplicate.submit();
                      return;
                  }
              </script>';
    }

    print_footer($course);
?>
