<?php // Spencer Creasey

//  Duplicate, update, create, delete, or load a rubric

    require_once("../../../config.php");
    require_once("../lib.php"); // which includes rubric/lib.php

    $course        = required_param('course', PARAM_INT);
    $action        = required_param('action', PARAM_ALPHA); // update, create, delete, load, copy, view
    $return        = optional_param('return', "/mod/assignment/rubric/index.php?id=$course", PARAM_LOCALURL);

    require_login($course);

    confirm_sesskey();
    
    if (! $course = get_record("course", "id", $course)) {
        error("This course doesn't exist");
    }

    $context = get_context_instance(CONTEXT_COURSE, $course->id);
    require_capability('moodle/course:manageactivities', $context);

	$strassignments = get_string("modulenameplural", "assignment");
	$strrubrics = get_string("rubrics", "assignment");

	$navlinks = array();
	$navlinks[] = array('name' => $strassignments, 'link' => "{$CFG->wwwroot}/mod/assignment/index.php?id={$course->id}", 'type' => 'activity');
	$navlinks[] = array('name' => $strrubrics, 'link' => "{$CFG->wwwroot}/mod/assignment/rubric/index.php?id={$course->id}", 'type' => 'misc');

    switch($action){
        case 'create': 

            // Redirect to edit.php
            redirect("edit.php?return=$return&course={$course->id}");

            break;
        case 'update': 

            // Redirect to edit.php

            $id = required_param('rubric', PARAM_INT);
            $rubric = new rubric($id);
	
            if($course->id != $rubric->course->id){
                error("Course (uid {$course->id}) mismatch with Rubric (uid {$rubric->id}).");
            }

            redirect("edit.php?return=$return&course={$course->id}&rubric=$id");

            break;
        case 'delete': 

            // checks to see if there are any dependent assignments, if not, deletes the rubric

            $id = required_param('rubric', PARAM_INT);

            $rubric = new rubric($id);
    
            if($course->id != $rubric->course->id){
                error("Course (uid {$course->id}) mismatch with Rubric (uid {$rubric->id}).");
            }

            $strrubric = get_string("rubric", "assignment");
            $strdelete = get_string("delete");

			$navlinks[] = array('name' => $strdelete, 'link' => '', 'type' => 'misc');

            // prints table of Assignments
            if($assoc_list = $rubric->get_assoc_assignments() ){

                $table->head = array( $strassignments, '' );
                $table->align = array( 'left', 'center' );
                $table->size = array( '75%', '25%' );
				$table->width = '450';

                foreach($assoc_list as $item){
                    $table->data[] = array( "<a href=\"{$CFG->wwwroot}/mod/assignment/view.php?id={$item->cm}\">{$item->name}</a>",
                                            "<a href=\"{$CFG->wwwroot}/course/modedit.php?update={$item->cm}&return=1\">Update</a>",
                                          );
                }

                $tableHTML = print_table($table, true);
                echo '<br />';

				global $THEME;

				print_header_simple($strrubrics, "", build_navigation($navlinks), "", "", true, "", navmenu($course));
				print_container_end_all(false, $THEME->open_header_containers);
                print_box("<div style=\"width:80%;text-align:center;margin-left:auto;margin-right:auto\">
							<p>Rubrics cannot be edited after grades have been submitted using them nor can they 
							be deleted while it's associated with an assignment. To remove this rubric you must update the following 
							assignments:</p></div>$tableHTML<br />", 'generalbox', 'notice');
				print_continue("{$CFG->wwwroot}$return");

				$rubric->view_footer();

            // delete it
            } else if(! $rubric->delete_instance()){

				print_header_simple($strrubrics, "", build_navigation($navlinks), "", "", true, "", navmenu($course));
                error('Could not remove rubric. Please contact Moodle Admin.', "{$CFG->wwwroot}/mod/assignment/rubric/index.php?id={$course->id}");
				$rubric->view_footer();

            } else { // success
                add_to_log($course->id, "assignment", "delete rubric (uid $id)", "rubric/index.php?id={$course->id}", "");
   
				//notice('Rubric has been successfully removed from course.', "{$CFG->wwwroot}$return");
				redirect("{$CFG->wwwroot}$return");
            }

            break;
        case 'load': 
            
            // Submits to upload.php

            $strrubric = get_string("rubric", "assignment");
            $strloadfromfile = get_string("loadfromfile", "assignment");

			$navlinks[] = array('name' => $strloadfromfile, 'link' => '', 'type' => 'misc');
			print_header_simple($strrubrics, "", build_navigation($navlinks), "", "", true, "", navmenu($course));

            $rubric = new rubric(0,0,$course);

			$rubric->print_upload_form();

			$rubric->view_footer();

            break;
		case 'xml':

            $id = required_param('rubric', PARAM_INT);
            $rubric = new rubric($id);

			$xml = $rubric->to_xml();
			$name = clean_filename($rubric->name).'.rubric.xml';
			$mime = 'application/xhtml+xml';

			force_download($xml, $name, $mime);
			// quits

			break;
        case 'copy': 

            // duplicates all rubric data for current course and returns to edit it

            $updatewnd = optional_param('updatewnd', 0, PARAM_INT);
			$rubric	   = required_param('rubric', PARAM_INT);

            // Redirect to edit.php
            redirect("edit.php?&course={$course->id}&copyrubric=$rubric&updatewnd=$updatewnd");

            break;
		case 'popupcreate':

            redirect("edit.php?return=$return&course={$course->id}&updatewnd=1");
	
			break;
		case 'popupcopyimport':

			// show only disabled select boxes if there are no other rubrics available for duplication 
			$options = get_rubrics_as_options($course->id);
			if (empty($options)) {

				// nothing to show ...
				echo '<div style="text-align:center"><form><fieldset class="invisiblefieldset">'.
					'<p>Create a rubric from an existing one:</p>'.
					choose_from_menu(array(), '', '', 'Select a rubric', '', 0, true, true).
					"</fieldset></form></div>\n";

			} else {

				echo "<div style=\"text-align:center\"><form method=\"post\" name=\"duplicate\" 
						action=\"{$CFG->wwwroot}/mod/assignment/rubric/mod.php\">\n".
					'<fieldset class="invisiblefieldset">'.
					'<p>Create a rubric from an existing one:</p>'.
					choose_from_menu($options, 'rubric', '', 'Select a rubric', 'submitform()', 0, true).
					'<input type="hidden" name="course" value="'.$course->id.'" />'.
					'<input type="hidden" name="sesskey" value="'.sesskey().'" />'.
					'<input type="hidden" name="action" value="copy" />'.
					'<input type="hidden" name="updatewnd" value="1" />'.
					"</fieldset></form></div>\n";
			}
		
			// Get name for duplicates
			echo '<script type="text/javascript">
					submitform = function(){
						var obj = document.forms.duplicate.rubric;
						if(obj.selectedIndex == 0) return;
						document.forms.duplicate.submit();
						return;
					}
				  </script>';

            $rubric = new rubric(0,0,$course);
			$rubric->print_upload_form(1);

			// Close button
			echo '<center><input type="button" onclick="window.close()" value="Close Window" /></center>';
	
			break;
        case 'view': 

            // prints out rubric (showing grades if available)
            // useful for previewing rubric (when deciding what to copy)

            $id = required_param('rubric', PARAM_INT);
            if(! $rubric = new rubric($id)){
                error('Cannot display rubric data');
            }

            $rubric->view();

            break;
        default:
            error('Invalid parameter (action).');
    }

