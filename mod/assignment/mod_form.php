<?php
require_once ($CFG->dirroot.'/course/moodleform_mod.php');

class mod_assignment_mod_form extends moodleform_mod {

    function definition() {
        global $CFG, $COURSE;
        $mform =& $this->_form;

        $assignment = new object();

        // this hack is needed for different settings of each subtype
        if (!empty($this->_instance)) {
            if($assignment = get_record('assignment', 'id', (int)$this->_instance)) {
                $type = $assignment->assignmenttype;
            } else {
                error('incorrect assignment');
            }
        } else {
            $type = required_param('type', PARAM_ALPHA);
            $assignment->rubricid = 0; // default (means no rubric)
        }

        $mform->addElement('hidden', 'assignmenttype', $type);
        $mform->setDefault('assignmenttype', $type);
        $mform->addElement('hidden', 'type', $type);
        $mform->setDefault('type', $type);

        require($CFG->dirroot.'/mod/assignment/type/'.$type.'/assignment.class.php');
        $assignmentclass = 'assignment_'.$type;
        $assignmentinstance = new $assignmentclass();

//-------------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

//        $mform->addElement('static', 'statictype', get_string('assignmenttype', 'assignment'), get_string('type'.$type,'assignment'));

        $mform->addElement('text', 'name', get_string('assignmentname', 'assignment'), array('size'=>'64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('htmleditor', 'description', get_string('description', 'assignment'));
        $mform->setType('description', PARAM_RAW);
        $mform->setHelpButton('description', array('writing', 'questions', 'richtext'), false, 'editorhelpbutton');
        $mform->addRule('description', get_string('required'), 'required', null, 'client');

        // ---------- START RUBRIC DROPDOWN ELEMENT -----------
 
        // Add new element
        if(class_exists('MoodleQuickForm') && !MoodleQuickForm::isTypeRegistered('advselect')){

            MoodleQuickForm::registerElementType(
                                'advselect', 
                                "$CFG->dirroot/mod/assignment/form/advselect.php", 
                                'MoodleQuickForm_advselect');

            // Verify
            if(!MoodleQuickForm::isTypeRegistered('advselect')) 
                error('Unable to load form. Type "advselect" could not be created.');
        }

        $options = array();
        $options[] = array( 'text' => get_string('singlegrade', 'assignment'), 'value' => 0 );

        $options[] = array( '_ADVANCED' => true, 
                            '_TYPE' => 'GROUP', 
                            'value' => 'line1',
                            'label' => '-----------------------------------------' );

        // Get list of rubrics
        if(!$rubrics = rubric_get_list($COURSE->id)){
            $rubrics = array( '_ADVANCED' => true, 
                              '_TYPE'     => 'GROUP', 
                              'value'     => 'line3',
                              'label'     =>  get_string('norubrics', 'assignment') );     
        }

        foreach($rubrics as $rub_key => $rubric){
            if(!is_object($rubric)) break; // TOP_COURSE produces this
            $items = array();
            foreach($rubric as $key => $value) $items[$key] = $value;
            $options[] = $items;
        }

        $options[] = array( '_ADVANCED' => true, 
                            '_TYPE'     => 'GROUP', 
                            'value'     => 'line2',
                            'label'     => '-----------------------------------------' );

        $options[] = array( '_ADVANCED' => true, 
                            '_TYPE'     => 'POPUP', 
                            'value'     => 'import',
                            'js'        => 'this.selectedIndex = 0; updateElem(this.value);',
                            'text'      => get_string('importrubric', 'assignment'),
                            'link'      => $CFG->wwwroot.'/mod/assignment/rubric/mod.php?course='.$COURSE->id.'&action=popupcopyimport&sesskey='.sesskey(),
                            'params'    => 'location=1,status=1,scrollbars=1,width=750,height=400');

        $options[] = array( '_ADVANCED' => true, 
                            '_TYPE'     => 'POPUP', 
                            'value'     => 'new',
                            'js'        => 'this.selectedIndex = 0; updateElem(this.value);',
                            'text'      => get_string('createnewrubric', 'assignment'),
                            'link'      => $CFG->wwwroot.'/mod/assignment/rubric/mod.php?course='.$COURSE->id.'&action=popupcreate&sesskey='.sesskey(),
                            'params'    => 'location=1,status=1,scrollbars=1,width=750,height=400');

        $elem = $mform->addElement('advselect', 'rubricid', get_string('loadrubric', 'assignment'), $options);        
        $mform->setHelpButton('rubricid', array('selectrubric', get_string('howtoselectrubric', 'assignment'), 'assignment'));
        $mform->setDefault('rubricid', 0);

        // if this assignment has a rubric, the disable the grade dropdown by default
        if (!empty($assignment->rubricid)) { // > 0
            $elem_grade = $mform->addElement('modgrade', 'grade', get_string('grade'), array( 'id' => 'id_grade', 'disabled'=>'disabled'));
        } else {
            $elem_grade = $mform->addElement('modgrade', 'grade', get_string('grade'), array( 'id' => 'id_grade'));
        }
        $mform->setDefault('grade', 100); 

        // if there are student submissions, they can no longer modify the grade parameters
        if (isset($assignment->timedue) && assignment_count_graded($assignment) > 0) {
            $elem->freeze();
        }

        // The following Javascript allows the 'grade' & 'rubricid' dropdowns to coordinate together
        $elem->addJSEvent('onchange', "updateElem(this.value);");
        $elem->addJSEvent('onkeypress', "updateElem(this.value);");

        // load supporting javascript
        $scriptpath = $CFG->dirroot.'/mod/assignment/mod_form-script.js';
        if (is_file($scriptpath)) {
            $elem->addJS(file_get_contents($scriptpath));
        } else {
            error("Script file '[moodle]/mod/assignment/mod_form-script.js' could not be found. See your developer.");
        }

        // ---------- END RUBRIC DROPDOWN ELEMENT -----------

        $mform->addElement('date_time_selector', 'timeavailable', get_string('availabledate', 'assignment'), array('optional'=>true));
        $mform->setDefault('timeavailable', time());
        $mform->addElement('date_time_selector', 'timedue', get_string('duedate', 'assignment'), array('optional'=>true));
        $mform->setDefault('timedue', time()+7*24*3600);

        $ynoptions = array( 0 => get_string('no'), 1 => get_string('yes'));

        $mform->addElement('select', 'preventlate', get_string('preventlate', 'assignment'), $ynoptions);
        $mform->setDefault('preventlate', 0);

        $mform->addElement('header', 'typedesc', get_string('type'.$type,'assignment'));
        $assignmentinstance->setup_elements($mform);

        $features = new stdClass;
        $features->groups = true;
        $features->groupings = true;
        $features->groupmembersonly = true;
        $this->standard_coursemodule_elements($features);

        $this->add_action_buttons();
    }



}
?>
