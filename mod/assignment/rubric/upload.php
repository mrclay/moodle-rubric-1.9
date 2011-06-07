<?php  // $Id: upload.php,v 1.26 2006/08/08 22:09:56 skodak Exp $

    require_once("../../../config.php");
    require_once("../lib.php");

    $course = required_param('course', PARAM_INT);  // Course ID
    $update_window = optional_param('updatewnd', 0, PARAM_INT);  // Course ID

    if (! $course = get_record("course", "id", $course)) {
        error("Course is misconfigured. Contact Developer.");
    }

    require_login($course->id);

    $context = get_context_instance(CONTEXT_COURSE, $course->id);
    require_capability('moodle/course:manageactivities', $context);

    // XML Parser
    include 'xmlize-php5.inc';

    $rubric = new rubric(0,0,$course);

    if($file = $rubric->upload()){   // Upload file

        $query = "?&course={$course->id}";

        $xml = xmlize($file->data);

        // The following is the syntax the XML files we're dealing with 
        // will follow:

        // <rubric>
        //   <name/>
        //     <spec id="" parent="" treeorder="">
        //     <name/>
        //       <notes/>
        //     <points/>
        //   </spec>
        //   ...
        //   <spec/>
        // </rubric>

        $specs = $xml['rubric']['#']['spec'];
        $name = urlencode($xml['rubric']['@']['name']);

        foreach($specs as $spec){

            unset($item);
            $item->id            = $spec['@']['id'];
            $item->parent        = $spec['@']['parent'];
            $item->treeorder    = $spec['@']['treeorder'];
            $item->name            = '';
            $item->notes        = '';
            $item->points        = '';

            $id = $spec['@']['id'];
            $n  = urlencode($spec['#']['name'][0]['#']);
            $d  = urlencode($spec['#']['notes'][0]['#']);
            $p  = urlencode($spec['#']['points'][0]['#']);
    
            $rubric->add_spec($item);

            $query .= "&_n$id=$n&_d$id=$d&_p$id=$p";

            // In the form (where # = id):
            // _n# = name1    
            // _d# = notes1
            // _p# = points1    

        }

        $layout = $rubric->get_layout();
        $query .= "&layout=$layout";
        $query .= "&name=$name";

        if($update_window) $query .= '&updatewnd=1';

        echo "Success";

        #print_r($rubric->specs);
        #print "$query<br>";

        global $CFG;

        redirect("edit.php$query"); //'&save'

    }else{
        // FAIL
        notify(get_string("configuploaderror", "assignment"));
    }
?>
