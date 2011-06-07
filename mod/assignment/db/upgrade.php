<?php  //$Id: upgrade.php,v 1.7.2.5 2008/05/01 20:37:22 skodak Exp $

// This file keeps track of upgrades to
// the assignment module
//
// Sometimes, changes between versions involve
// alterations to database structures and other
// major things that may break installations.
//
// The upgrade function in this file will attempt
// to perform all the necessary actions to upgrade
// your older installtion to the current version.
//
// If there's something it cannot do itself, it
// will tell you what you need to do.
//
// The commands in here will all be database-neutral,
// using the functions defined in lib/ddllib.php

function xmldb_assignment_upgrade($oldversion=0) {

    global $CFG, $THEME, $db;

    $result = true;

    if ($result && $oldversion < 2007091900) { /// MDL-11268

    /// Changing nullability of field data1 on table assignment_submissions to null
        $table = new XMLDBTable('assignment_submissions');
        $field = new XMLDBField('data1');
        $field->setAttributes(XMLDB_TYPE_TEXT, 'small', null, null, null, null, null, null, 'numfiles');

    /// Launch change of nullability for field data1
        $result = $result && change_field_notnull($table, $field);

    /// Changing nullability of field data2 on table assignment_submissions to null
        $field = new XMLDBField('data2');
        $field->setAttributes(XMLDB_TYPE_TEXT, 'small', null, null, null, null, null, null, 'data1');

    /// Launch change of nullability for field data2
        $result = $result && change_field_notnull($table, $field);
    }

    if ($result && $oldversion < 2007091902) {
        // add draft tracking default to existing upload assignments
        $sql = "UPDATE {$CFG->prefix}assignment SET var4=1 WHERE assignmenttype='upload'";
        $result = $result && execute_sql($sql);
    }

//===== 1.9.0 upgrade line ======//

    if ($result && $oldversion < 2007101511) {
        notify('Processing assignment grades, this may take a while if there are many assignments...', 'notifysuccess');
        // change grade typo to text if no grades MDL-13920
        require_once $CFG->dirroot.'/mod/assignment/lib.php';
        // too much debug output
        $db->debug = false;
        assignment_update_grades();
        $db->debug = true;
    }

    /// Upgrade for assignment grading with Rubrics

    if ($result /*&& $oldversion < 2007101514*/) {
        // At this time, this is only contrib. (which is excluded from all versions) -- update forced

        $table = new XMLDBTable('assignment');
        $rubricid = new XMLDBField('rubricid');
        $rubricid->setAttributes(XMLDB_TYPE_INTEGER, 10, true, true, null, null, null, 0, 'id');
        $data1 = new XMLDBField('data1');
        $data1->setAttributes(XMLDB_TYPE_TEXT, null, null, false, null, null, null, null, 'var5');
        $key = new XMLDBKey('rubric_ref');
        $key->setAttributes(XMLDB_KEY_FOREIGN, array('rubricid'), 'rubric', array('id'));
        $result = $result && add_field($table, $rubricid);
        $result = $result && add_field($table, $data1);
        $result = $result && add_key($table, $key);

        $table = new XMLDBTable('assignment_submission_specs');
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, 10, true, true, true);
        $table->addFieldInfo('submissionid', XMLDB_TYPE_INTEGER, 10, true, true);
        $table->addFieldInfo('rubricspecid', XMLDB_TYPE_INTEGER, 10, true, true);
        $table->addFieldInfo('value', XMLDB_TYPE_INTEGER, 10, false, true, null, null, null, 0);
        $table->addFieldInfo('description', XMLDB_TYPE_TEXT, null, null, false);
        $table->addKeyInfo('identifer', XMLDB_KEY_PRIMARY, array('id'));
        $table->addKeyInfo('submission_ref', XMLDB_KEY_FOREIGN, array('submissionid'), 'assignment_submission', array('id'));
        $table->addKeyInfo('rubricspec_ref', XMLDB_KEY_FOREIGN, array('rubricspecid'), 'rubric_specs', array('id'));
        $result = $result && create_table($table);
        
        $table = new XMLDBTable('assignment_rubric');
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, 10, true, true, true);
        $table->addFieldInfo('name', XMLDB_TYPE_CHAR, 255, null, true);
        $table->addFieldInfo('creatorid', XMLDB_TYPE_INTEGER, 10, true, true);
        $table->addFieldInfo('courseid', XMLDB_TYPE_INTEGER, 10, true, true);
        $table->addFieldInfo('points', XMLDB_TYPE_INTEGER, 10, false, true, null, null, null, 0);
        $table->addFieldInfo('timemodified', XMLDB_TYPE_INTEGER, 10, true, true);
        $table->addKeyInfo('identifer', XMLDB_KEY_PRIMARY, array('id'));
        $table->addKeyInfo('user_ref', XMLDB_KEY_FOREIGN, array('creatorid'), 'users', array('id'));
        $table->addKeyInfo('course_ref', XMLDB_KEY_FOREIGN, array('courseid'), 'course', array('id'));
        $result = $result && create_table($table);

        $table = new XMLDBTable('assignment_rubric_specs');
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, 10, true, true, false);
        $table->addFieldInfo('rubricid', XMLDB_TYPE_INTEGER, 10, true, true);
        $table->addFieldInfo('treeorder', XMLDB_TYPE_INTEGER, 5, true, true);
        $table->addFieldInfo('name', XMLDB_TYPE_CHAR, 255, null, true); 
        $table->addFieldInfo('notes', XMLDB_TYPE_TEXT, null, null, false);
        $table->addFieldInfo('points', XMLDB_TYPE_INTEGER, 10, false, true, null, null, null, 0);
        $table->addFieldInfo('parent', XMLDB_TYPE_INTEGER, 5, true, true);
        $table->addKeyInfo('identifer', XMLDB_KEY_PRIMARY, array('id'));
        $table->addKeyInfo('rubric_ref', XMLDB_KEY_FOREIGN, array('rubricid'), 'rubric', array('id'));
        $result = $result && create_table($table);

    }
    
    return $result;
}

?>
