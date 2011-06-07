<?php // $Id: modedit.php,v 1.15.2.1 2007/02/14 14:15:30 moodler Exp $

//  adds or updates rubric in a course

    require_once("../../../config.php");
    require_once("../lib.php"); // includes rubric/lib.php

    $course = required_param('course', PARAM_INT);
    $rubric = optional_param('rubric', 0, PARAM_INT);
    $update_window = optional_param('updatewnd', 0, PARAM_INT);
    $return = optional_param('return', "/mod/assignment/rubric/index.php?id=$course", PARAM_LOCALURL);
    $copyrubric = optional_param('copyrubric', 0, PARAM_INT);

    if (! $course = get_record("course", "id", $course)) {
        error("This course doesn't exist");
    }

    require_login($course->id); 

    $rubric = new rubric($rubric, 0, $course);
    require_capability('moodle/course:manageactivities', $rubric->context);

    $strassignments = get_string("modulenameplural", "assignment");
    $strrubrics = get_string("rubrics", "assignment");
    $stractivity = get_string( $rubric->id ? "update" : "create" , "assignment");

    $navlinks = array();
    $navlinks[] = array('name' => $strassignments, 'link' => "{$CFG->wwwroot}/mod/assignment/index.php?id={$rubric->course->id}", 'type' => 'activity');
    $navlinks[] = array('name' => $strrubrics, 'link' => "{$CFG->wwwroot}/mod/assignment/rubric/index.php?id={$rubric->course->id}", 'type' => 'misc');
    $navlinks[] = array('name' => $stractivity, 'link' => '', 'type' => 'misc');

    // If the rubric is in use, we can't modify it
    if($rubric->get_assoc_assignments()){

        if(!$update_window){    
            print_header_simple($strrubrics, "", build_navigation($navlinks), "", "", true, "", navmenu($course));
        }

        echo '<br />';
        notice('This rubric is in use by an assignment(s). It may not be modified.',"{$CFG->wwwroot}$return");
    }

    // Get commands
    $save = isset($_GET['save']);
    $reset = isset($_GET['reset']);
    $cancel = isset($_GET['cancel']);

    // Pressing buttons
    if($reset) 
        redirect("edit.php?return=$return&course={$course->id}&updatewnd=$updwnd");
    if($cancel){ 
        if($update_window){ 
            close_window(); 
            die;
        } else { 
            redirect("{$CFG->wwwroot}$return"); 
        }
    }

    if(isset($_GET['layout'])) $rubric->specs_loaded = true;
   
    $rubric->get_specs();        // load DB saved specs
    $rubric->parseLayout($_GET);    // parse specs from GET (variables will be cleaned)

    if(isset($_GET['name'])) 
        $rubric->name = optional_param('name', '', PARAM_TEXT);

    // redirected from `case: copy` @ mod.php
    if($copyrubric){

        $old_rubric = new rubric($copyrubric);

        // setup attribs
        $rubric->name = $old_rubric->name;

        $old_rubric->get_specs();
        $rubric->add_specs($old_rubric->specs);

    }

    $error->error = false;

    // Pressing the Save button
    if($save){

        $rubric->computePoints();

        $error = $rubric->validate();

        if(!$error->error){

            if($rubric->commit()){

                if($update_window){
                    print "Success.<br />";
                    $rubric->update_form_rubric();
                    close_window();
                    die;
                }

                redirect("{$CFG->wwwroot}$return"); 

            }else{

                if($update_window){
                    print "Could not complete.<br />";
                    close_window_button();
                    die;
                }

                echo '<br />';
                error("There was a problem creating your rubric. Please contact the developer.", "{$CFG->wwwroot}$return", $rubric->course);
            }
        }

    }

	if(!$copyrubric)
	    $rubric->name = $rubric->deSlash($rubric->name);

    if(!$update_window){
        print_header_simple($strrubrics, "", build_navigation($navlinks), "", "", true, "", navmenu($course));
    } else {
        print_header();
    }

    echo "<br />";
    print_simple_box_start('center', '95%', '', 0, 'generalbox', 'rubric_table');

    if($error->error) $rubric->formerr($error->fatal, $error->message, $return);

    $form_name = 'modrubric';

    echo "<form name=\"$form_name\" method=\"get\" action=\"edit.php\">\n";

    echo $rubric->create($form_name, true);            // print creation/edit form

    echo "<input type=\"hidden\" name=\"course\" value=\"{$course->id}\" />\n";
    echo "<input type=\"hidden\" name=\"rubric\" value=\"{$rubric->id}\" />\n";
    echo "<input type=\"hidden\" name=\"updatewnd\" value=\"$update_window\" />\n";
    echo "<input type=\"hidden\" name=\"return\" value=\"$return\" />\n";

    echo "<br />\n";
    echo "<table width=\"100%\"><tr>\n";
    echo "<td width=\"50%\" align=\"right\"></td>\n";
    echo "<td width=\"50%\" align=\"right\">\n";
    echo "<input type=\"submit\" value=\"Update\" />\n";
    echo "</td></tr><tr><td colspan=\"2\">&nbsp;</td></tr><tr>\n";
    echo "<td width=\"50%\" align=\"left\">\n";
    echo 'Rubric Title: <input type="text" name="name" value="'.$rubric->escHTML($rubric->name).'" />';
    echo "</td>\n";
    echo "<td width=\"50%\" align=\"right\">\n";
    echo "<input type=\"submit\" name=\"save\" value=\"Save Rubric\" />\n";
    echo "<input type=\"submit\" name=\"reset\" value=\"Reset\" />\n";
    echo "<input type=\"submit\" name=\"cancel\" value=\"Cancel\" />\n";
    echo "</td></tr></table>\n";

    echo "</form>\n";

    print_simple_box_end();

    if(!$update_window)    $rubric->view_footer();

?>

