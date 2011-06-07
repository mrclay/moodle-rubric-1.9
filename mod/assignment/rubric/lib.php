<?PHP  /* rubric.php v0.7 : Spencer Creasey <screasey@gmail.com> : March 5, 2008 */

// class for modifying, creating, deleting, importing, and viewing rubric instanaces

class rubric {

    // db attributes
    var $id;
    var $name;
    var $creatorid;
    var $courseid;
    var $points;
    var $timemodified;

    // db specs
    var $specs;
    var $spec_count;

    // db modifications
    var $is_new;

    var $_orig_id;
    var $_orig_name;
    var $_orig_creatorid;
    var $_orig_courseid;
    var $_orig_points;
    var $_orig_timemodified;

    var $specs_loaded;
    var $specs_in_sync;

    // environmental vars
    var $cm;
    var $context;
    var $course;
    var $list;
    var $items;
    var $uses;
    var $maxbytes;

    // specific to user/assignment
    var $userid;
    var $assignmentid;
    var $is_graded;

    /**
     * Pass rubric id - called by /mod/assignment/lib.php
     */
    function rubric($id=NULL, $assignmentid=NULL, $course=NULL){
    
        $this->maxbytes = 102400; // 100 KB

        if(!empty($id)){
            
            if(! $rubric = get_record('assignment_rubric', 'id', $id)){
                error("Invalid rubric (uid $id).");
            }

            $this->_orig_id = $this->id = $rubric->id;
            $this->_orig_name = $this->name = $rubric->name;
            $this->_orig_creatorid = $this->creatorid = $rubric->creatorid;
            $this->_orig_courseid = $this->courseid = $rubric->courseid;
            $this->_orig_points = $this->points = $rubric->points;
            $this->_orig_timemodified = $this->timemodified = $rubric->timemodified;

            $this->list = null;
            $this->assignmentid = $assignmentid;

            $this->specs = array();
            $this->spec_count = 0;
            $this->specs_loaded = false; // if we add a spec, lets load what we have first

            $this->in_sync = true;
            $this->specs_in_sync = true;

            $this->is_new = false;

            if(is_object($course)){
                if($course->id != $this->courseid){
                    error("Rubric (uid {$this->id}) does not belong to course (uid {$course->id}).");
                }
                $this->course = $course;
            }else{
                if (! $this->course = get_record("course", "id", $this->courseid)) {
                    error("This course doesn't exist (uid {$this->courseid}.");
                }
            }
            
        } else {

            if(is_object($course)){

                $this->course = $course;
                $this->courseid = $course->id;

            }else{
                global $COURSE;

                if(is_object($COURSE) && isset($COURSE->id)) 
                        $this->courseid = $COURSE->id;
                else    error('Unable to instantiate Course');

                if (! $this->course = get_record("course", "id", $this->courseid)) {
                    error("This course doesn't exist (uid {$this->courseid}.");
                }
            }

            $this->id = 0;
            $this->points = 0;
            $this->timemodified = time();

            global $USER;

            $this->name = '';
            if(is_object($USER) && isset($USER->id))
               $this->creatorid = $USER->id;
            
            $this->specs = array();
            $this->spec_count = 0;
            $this->specs_loaded = true; // nothing to load

            $this->in_sync = false;
            $this->specs_in_sync = true;

            $this->is_new = true;
        }

        $this->context = get_context_instance(CONTEXT_COURSE, $this->course->id);

    }

    function in_sync(){

        if($this->is_new){
            $this->in_sync = false;

        }else{
            $this->in_sync = ( ($this->_orig_id === $this->id) &&
                               ($this->_orig_name == $this->name) &&
                               ($this->_orig_creatorid == $this->creatorid) &&
                               ($this->_orig_courseid == $this->courseid) &&
                               ($this->_orig_points == $this->points) &&
                               ($this->_orig_timemodified == $this->timemodified) );
        }

        return $this->in_sync;
    }

    function get_submission(){
        return get_record('assignment_submissions', 'assignment', $this->assignmentid, 'userid', $this->userid);
    }

    // prints out standard view of the rubric (adds grade/feedback if available)
    function view($userid=null, $show_box=true, $print_notes=true, $print_comments=true, $font_size=8, $width='40%') {

        global $CFG, $USER;

        if(empty($userid))  $this->userid = $USER->id;
        else                $this->userid = $userid;

        $submission = $this->get_submission();

        // If yet ungraded, then print only rubric
        if(!$this->is_graded = !empty($submission->timemarked)){
            // Can't print out comments on why they got a bad grade when they haven't yet been graded
            $print_comments = false;
        }

        // If there is a rubric for the assignment, print it
        if($this->id){

            if($show_box) print_simple_box_start('center', $width, '', 0, 'generalbox', 'rubric_table');
            $formatoptions = new stdClass;
            $formatoptions->noclean = true;

            if($rec = get_record_sql("SELECT name FROM {$CFG->prefix}assignment_rubric WHERE id = {$this->id}")){
                $this->name = $rec->name;
            }else{
                echo '<font color="red">There was an error accessing assignment rubric informaiton. Invalid rubric data for rubric (#{$rubric->id})</font>';
                print_simple_box_end();
                return;
            }

            if(!$this->items = $this->get_spec_data()){

                echo "<pre>";
                print !$this->items;
                print "\n";
                print $this->get_spec_data();
                echo "</pre>";

                echo '<font color="red">There was an error accessing assignment rubric information. Invalid grade.</font>';
                print_simple_box_end();
                return;
            }

            // Get recursive mess of the specs
            list($size, $html, $this->points, $score) = 
                 $this->view_recurse($this->items, 0, 0, $print_notes, $print_comments);

            if($score < 0) $score = 0;

            if($this->is_graded){
                $total_label = "Score";
                $total = "$score / {$this->points}";
                $percent = round($score / $this->points,3)*100;
            }else{
                $total_label = '';//"Total";
                $total = $this->points.' pts';
                $percent = '';
            }

            // Table
            print '<table border="0" id="rubric">
                      <tr>
                          <th align="left">Name</th>
                          '.($print_notes ? '<th align="left">Notes</th>' : '').'
                          <th>Points</th>
                          '.($print_comments ? '<th align="left">Grade Comments</th>' : '').'
                      </tr>';

            print     $html;

            print    "<tr>
                          ".($print_notes ? "<td class=\"line name\" align=\"left\">{$this->name}</td>":'')."
                          <td class=\"line\" align=\"right\">$total_label</td>
                          <td class=\"line\" align=\"center\"><div style=\"white-space:nowrap\">$total</div></td>
                          ".($print_comments ? "<td class=\"line\">&nbsp;($percent%)</td>" : '')."
                      </tr>
                   </table>";

            // Styles
            print "<style type=\"text/css\">
                   #rubric { width:100%; font-size:".($font_size+2)."pt; }
                   #rubric th { white-space:nowrap; padding:0 5px 0 5px; border-bottom:1px solid grey; font-size:".($font_size+2)."pt}
                   #rubric td.line { border-top:1px solid grey; font-weight:bold; font-size:".($font_size+3)."pt }
                   #rubric td.name { color:grey; font-size:{$font_size}pt; font-style:italic }
                   #rubric tr.colored { background-color: #e1e8ea }
                   #rubric tr.nocolor { background-color: none }
                   #rubric td { padding-left:5px; font-size:{$font_size}pt }
                   #rubric ul { margin:0; padding:0; list-style:none; vertical-align:middle }
                   #rubric ul li { vertical-align: middle; font-size:{$font_size}pt }
                   #rubric ul.level0 { list-style:disc; padding-left:20px;}
                   #rubric ul.level1 { list-style:circle; padding-left:40px; }
                   #rubric ul.level2 { list-style:square; padding-left:60px; }
                   #rubric ul.level3 { list-style:disc; padding-left:80px; }
                   #rubric ul.level4 { list-style:circle; padding-left:100px; }
                   #rubric ul.level5 { list-style:square; padding-left:120px; }
                   #rubric ul.level6 { list-style:circle; padding-left:140px; }
                   #rubric ul.level0-title { list-style:none; padding-left:6px;}
                   #rubric ul.level1-title { list-style:none; padding-left:26px; }
                   #rubric ul.level2-title { list-style:none; padding-left:46px; }
                   #rubric ul.level3-title { list-style:none; padding-left:66px; }
                   #rubric ul.level4-title { list-style:none; padding-left:86px; }
                   #rubric ul.level5-title { list-style:none; padding-left:106px; }
                   #rubric ul.level6-title { list-style:none; padding-left:126px; }
                   #rubric .red { color:red; }
                   #rubric h1 { }
                   #rubric h2 { padding:0;margin:0 } 
                   </style>";

            if($show_box) print_simple_box_end();
        }
    }

    function view_recurse($items, $parent, $level, $print_notes, $print_desc, $color=false){

        $html = '';
        $points = $score = $children = 0;

        foreach($items as $item){
            if($item->parent == $parent){

                list($_children, $_html, $_points, $_score) = 
                    $this->view_recurse($items, $item->specid, $level+1, $print_notes, $print_desc, !$color);

                $item->name = deSlashHTML($item->name);
                $item->notes = deSlashHTML($item->notes);

                // No children - we want to grade on this item
                if(!$_children){

                    if($this->is_graded){
                        $total_str = red($item->score).' / '.red($item->points);
                    }else{
                        $item->score = 0;
                        $total_str = red($item->points);
                    }

                    $indent = $this->construct_ul_indentation($level);

                // Has children - print out 
                }else{

                    $total_str = '';

                    $item->name = "<strong>{$item->name}</strong>";
                    $item->notes = "<strong>{$item->notes}</strong>";
                    $item->points = 0; // we don't want to count these points 
                    $item->score = 0;  // or scores (we count them only at
                                       // bottom nodes)

                    $indent = $this->construct_ul_indentation($level, true);
                }

                $score += $item->score + $_score;    // add total score
                $points += ($item->points < 0 ? 0 : $item->points) + $_points; // .. and points
                $children += $_children + 1;         // sum total children

                $class = ($color ? 'colored' : 'nocolor');

                $html .= "<tr class=\"$class\">
                              <td>{$indent->in}{$item->name}{$indent->out}</td>
                              ".($print_notes ? "<td>{$item->notes}</td>" : '')."
                              <td align=\"center\">$total_str</td>
                              ".($print_desc ? "<td>{$item->descr}</td>" : '')."
                          </tr>
                          $_html";

                $color = (($_children+1)%2 xor $color);
            }
        }

        return array($children, $html, $points, $score); 
    }

    /**
     *  Prints out smaller version of 'view()'. TODO condense the structures of these function 
     *
     * Called by display_submission()
     */
    function grade($assignment, $submission, $userid){

        global $CFG;

        // If yet ungraded, then print only rubric
        $this->is_graded = !empty($submission->timemarked);
        $this->assignmentid = $assignment->id;
        $this->userid = $userid;

        if($rec = get_record_sql("SELECT name FROM {$CFG->prefix}assignment_rubric WHERE id = {$this->id}")){
            $rubric->name = $rec->name;
        }else{
            echo '<font color="red">There was an error accessing assignment rubric informaiton. Invalid rubric data for rubric (#{$rubric->id})</font>';
            return;
        }

        if(!$this->items = $this->get_spec_data()){
            echo "<font color=\"red\">
                  There was an error accessing assignment rubric information. Invalid grade 
                  (graded={$this->is_graded} assid={$assignment->id} rubricid={$this->id}).</font>";
            return;
        }

        list($node, $html, $this->points, $score, $js) = $this->grade_recurse($this->items, 0, 0);

        if($this->points != $assignment->grade){
            echo "<font color=\"red\">Please talk to your developer. There is a difference between ".
                 "the range of available grades in tbl.assignment (0 - {$assignment->grade}) and the ".
                 "grade your students can recieve ({$this->points}).</font>";
        }

        if($this->is_graded) {
            $starting_total = $score < 0 ? 0 : $score;
            $starting_total_raw = $score;
        } else {
            $starting_total_raw = $starting_total = $this->points;
        }

        $percent = round($starting_total / $this->points * 100, 1);

        // Generate script to change every parent the child affects
        $node->js = '';
        foreach($node->chain as $child => $parents)
            $node->js .= "\t'$child': [ '".join("', '",$parents)."' ],\n";

        // remove trailing ,\n 
        $node->js = "\n".substr($node->js, 0, strlen($node->js)-2)."\n";
        $js = "\n".substr($js, 0, strlen($js)-2)."\n";

        print_simple_box_start('center', '', '', 0, 'generalbox', 'rubric_border');

        // Table
        print "\n<table id=\"rubric\"><tr>
              <th width=\"\" align=\"left\">Name</th>
              <th width=\"\" >Points</th>
              <th width=\"100%\" >Notes</th>
              </tr>\n";

        print $html;

        print "<tr><td colspan=\"3\" class=\"line\"></td></tr>
               <tr><td class=\"totals\" align=\"right\">Score</td>
               <td class=\"totals\" align=\"center\"><div class=\"nowrap\"><span id=\"span_total\">$starting_total</span> / {$this->points}</div></td>
               <td class=\"totals\" align=\"left\"><div class=\"percent\" id=\"div_percent\">($percent&#37;)</div></td>
               </tr></table>\n";

        print_simple_box_end();

        // Print out hidden value to validate there is a rubric information printed
        print '<input type="hidden" name="grade_by_rubric" value="1" />
               <input type="hidden" name="grade" id="form_grade" value="'.$starting_total.'" />
               <input type="hidden" name="is_graded" value="'.($this->is_graded ? 1 : '0' ).'" />
               ';

        // Styles
        print "
            <style type=\"text/css\">
            #rubric { width: 100%; font-size:10pt; }
            #rubric th { white-space:nowrap;padding:0 5px 0 5px; }
            #rubric th { border-bottom:1px solid #808080; }
            #rubric td.line { border-bottom:1px solid #808080; font-size:0px; height:0px }
            #rubric td.totals { font-weight:bold;font-size:13pt }
            #rubric td { padding-left:5px }
            #rubric ul { margin:0; padding:0; list-style:none; vertical-align:middle }
            #rubric ul li { vertical-align: middle; white-space:nowrap;  }
            #rubric ul.level0 { list-style:disc; padding-left:20px;}
            #rubric ul.level1 { list-style:circle; padding-left:40px; }
            #rubric ul.level2 { list-style:square; padding-left:60px; }
            #rubric ul.level3 { list-style:disc; padding-left:80px; }
            #rubric ul.level4 { list-style:circle; padding-left:100px; }
            #rubric ul.level5 { list-style:square; padding-left:120px; }
            #rubric ul.level6 { list-style:circle; padding-left:140px; }
            #rubric ul.level0-title { list-style:none; padding-left:6px;}
            #rubric ul.level1-title { list-style:none; padding-left:26px; }
            #rubric ul.level2-title { list-style:none; padding-left:46px; }
            #rubric ul.level3-title { list-style:none; padding-left:66px; }
            #rubric ul.level4-title { list-style:none; padding-left:86px; }
            #rubric ul.level5-title { list-style:none; padding-left:106px; }
            #rubric ul.level6-title { list-style:none; padding-left:126px; }
            #rubric .red { color:red; }
            #rubric h1 { } 
            #rubric h2 { padding:0;margin:0 } 
            #rubric select { font-size: 8pt }
            #rubric strong.rp { padding-right:5px; }
            #rubric .nowrap { white-space:nowrap }
            #rubric .percent { padding-left:15px }
            </style>";

        // Scripts
        print "
            <script type=\"text/javascript\">
            function $$(id){return document.getElementById(id);} 
            var form_grade = $$('form_grade');
            var span_grade = $$('span_total');
            var div_percent = $$('div_percent');
            var total_pts = $this->points;
            var grade = $starting_total_raw;
            var selects = { $js };
            var nodes = { $node->js };

            // Called everytime a SELECT is changed
            function updateGrade(key, now){ 
                var change = now - selects[key];
                selects[key] += change;
                grade += change;
                if(nodes[key]){
                    for(id in nodes[key]){
                        selects[nodes[key][id]] += change;
                        $$(nodes[key][id]+'_parent').innerHTML = selects[nodes[key][id]];
                    }
                }

                var pos_grade = grade < 0 ? 0 : grade;
                span_grade.innerHTML = form_grade.value = pos_grade;
                div_percent.innerHTML = '('+ (Math.round( pos_grade / total_pts * 1000 ) / 10 )+'&#37;)';
            }

            // Adjusts any select boxes back to their original state
            for(id in selects){
                if(obj = $$(id.toString())) setValue(obj, selects[id]);
            }

            // Iterates through SELECT obj and selects the option w/ value
            function setValue(obj, value) {
                if(obj.value != value){
                    for(i=0; i<obj.options.length; i++) {
                        if(obj.options[i].value == value){
                            obj.selectedIndex = i;
                            return i;
                        }
                    }
                    return -1;
                }
                return obj.selectedIndex;
            }
            </script>";

        print_simple_box_end();
    }

    function grade_recurse($items, $parent_id, $level){

        $html = $js = '';
        $points = $score = 0;

        // Node tracks the child nodes and number of children under a node. A
        //  node is either a selectbox or hidden input field (both are id's of HTMl 
        //  objects found under 'Points' in the rubric
        $node->size = 0;    // number of child nodes
        $node->start = 0;   // This defines the index of the items in $node->children. It
                            //  acts as a stopping mechanism for associating siblings

        $node->children = array();  // list of children that affect parent nodes

        $node->chain = array();     // an associative array of children and the parent
                                    //  nodes they affect.

        // through each item in the SQL return value of rubric items (to be echoed as HTML)
        while(list(,$item) = each($items)){
            if($item->parent == $parent_id){

                // Recurse children
                list($_node, $_html, $_points, $_score, $_js) = 
                    $this->grade_recurse($items, $item->specid, $level+1);

                // Defines a unique id attribute to use as keys in the arrays and HTML elements
                $id = "rsgrade:{$item->specid}"; // Item's DOM id

                // merge nodes so to include all child nodes for the parent who needs them
                $node->children = array_merge($node->children, $_node->children);
                $node->chain = array_merge($node->chain, $_node->chain);

                if (strlen($item->name) > 25)
                    $item->name = substr($item->name, 0, 25).'...';

                $item->name = deSlashHTML($item->name);

                // No children - we want to grade on this item
                if(!$_node->size){

                    // Add JS reference (for summing total score)
                    $script = "updateGrade('$id',this.options[this.selectedIndex].value)";

                    // generate <input type="select" ...  
                    if($this->is_graded){
                        $js .= "\t'$id': {$item->score},\n"; // for JavaScript
                        if($item->points == 0) 
                             $select = '<input type="text" value="0" style="width:30px" disabled="disabled" />
                                        <input type="hidden" value="0" name="'.$id.'" />';
                        else $select = choose_from_menu(make_grades_list($item->points),$id,$item->score,'',$script,0,1,'','',$id);
                    }else{
                        $item->score = 0;
                        $js .= "\t'$id': ".($item->points < 0 ? 0 : $item->points).",\n"; // for JavaScript
                        if($item->points == 0) 
                             $select = '<input type="text" value="0" style="width:30px" disabled="disabled" />
                                        <input type="hidden" value="0" name="'.$id.'" />';
                        else $select = choose_from_menu(make_grades_list($item->points),$id,$item->points<0?0:'','',$script,0,1,'','',$id);
                    }

                    // Add a description field 
                    $desc_field = "<input type=\"text\" style=\"width:100%\" name=\"DESC{$id}\" value=\"{$item->descr}\" />";

                    // push this node's id on the child list 
                    array_push($node->children, $id);
                    $node->start++;

                    // Form indention
                    $indent = $this->construct_ul_indentation($level);

                // Has children - print out 
                }else{

                    if($this->is_graded) $field = $_score;
                    else        $field = $_points;

                    $js .= "\t'$id': $field,\n{$_js}";
                    $select = "<strong class=\"rp\"><span id=\"{$id}_parent\">$field</span> / $_points</strong>".
                              '<input type="hidden" value="-1" name="'.$id.'" />';

                    $item->points = 0; // we don't want to count these points 
                    $item->score = 0;  // or these scores (we count them only at
                                       // bottom nodes)

                    // add children that affect value of parent
                    for($x = $node->start; $x < sizeof($node->children); $x++){
                        $child = $node->children[$x];
                        $node->chain[$child][] = $id;
                    }
                    $node->start = $x; // Set new index

                    // Don't need a description field on this one
                    $desc_field = '';

                    // Form indention
                    $indent = $this->construct_ul_indentation($level, true);

                }

                $score += $item->score + $_score;    // add total score
                $points += ($item->points < 0 ? 0 : $item->points) + $_points; // .. and points
                $node->size += $_node->size + 1;         // sum total children

                $html .=  "
                    <tr title=\"{$item->notes}\"><td valign=\"middle\">{$indent->in}{$item->name}{$indent->out}</td>
                    <td valign=\"middle\" align=\"right\">$select</td>
                    <td valign=\"middle\">$desc_field</td>                 
                    </tr>
                    $_html";
            }
        }

        return array($node, $html, $points, $score, $js);
    }

    function computePoints() {

        if(!$this->spec_count){
            $this->get_specs();
            if(!$this->spec_count){
                $this->points = 0;
                return;
            }
        }

        $this->_recursion = 0;

        // Get recursive mess
        list($size, $this->points) = $this->compute_points_recurse($this->specs);
    }

    function compute_points_recurse($items, $parent=0){

        $points = $children = 0;

        foreach($items as $id => $item){

            if($item['parent'] == $parent){

                $item = (object)$item;
                $item->id = $id;

                // error checking
                if($this->_recursion++ > 200) error('Recursion error while computing points.');

                // get children
                list($_children, $_points) = $this->compute_points_recurse($items, $item->id);

                // we don't want to count these points 
                if($_children) $item->points = 0;

                $points += ($item->points < 0 ? 0 :$item->points) + $_points; // .. and points
                $children += $_children + 1;         // sum total children
            }
        }

        return array($children, $points); 
    }

    function get_layout() {

        if(!$this->spec_count){
            $this->get_specs();
            if(!$this->spec_count){

                // there are no specs.. add one. 
                $o = null;
                $o->id = 1;
                $o->parent = 0;
                $o->treeorder = 0;
                $o->name = '';
                $o->notes = '';
                $o->points = 0;

                $this->add_spec($o);
            }
        }

        $data = null;
        foreach($this->specs as $id => $spec){
            $key = $spec['parent'].'|'.$spec['treeorder'];
            $spec['id'] = $id;
            $data[$key] = $spec;
        }

        // Get recursive mess
        list($size, $layout) = $this->get_layout_recurse($data);

        return $layout;    

    }

    function get_layout_recurse($items, $parent=0, $level=0, $layout=''){

        $children = $treeorder = 0;

        while(isset($items["{$parent}|{$treeorder}"])){

            $item = $items["{$parent}|{$treeorder}"];

            $treeorder++;
            $layout .= $item['id'];

            list($_children, $layout) = $this->get_layout_recurse($items, $item['id'], $level+1, $layout.'[');

            // No children - we want to grade on this item
            if(!$_children){

                $layout .= ','; // add ','

            // Has children - print out 
            }else{

                $layout .= ']'; // adds ']'
            }

            $children += $_children + 1;         // sum total children

        }

        return array($children, rtrim($layout,",[")); 
    }

    // prints out standard view of the rubric (adds grade/feedback if available)
    function create($form_name, $return=true) {

        global $CFG;

        if(!$this->spec_count){
            $this->get_specs();
            if(!$this->spec_count){

                // there are no specs.. add one. 
                $o = null;
                $o->id = 1;
                $o->parent = 0;
                $o->treeorder = 0;
                $o->name = '';
                $o->notes = '';
                $o->points = 0;

                $this->add_spec($o);
                $this->creation_layout = '';
            }
        }

        $stroptions = get_string("options", "assignment");
        $strtotal = get_string("total", "assignment");
        $strnotes = get_string("notes", "assignment");
        $strpoints = get_string("points", "assignment");
        $strname = get_string("name", "assignment");

        $this->creation_id = 1;

        //ah.. trying something (too unefficient now)
        $data = null;
        foreach($this->specs as $id => $spec){
            $key = $spec['parent'].'|'.$spec['treeorder'];
            $spec['id'] = $id;
            $data[$key] = $spec;
        }

        // Get recursive mess
        list($size, $html, $this->points, $this->creation_layout) = $this->create_recurse($data, 0, 0);

        //echo $this->creation_layout.'<br>';

        // Table
        $pr  = "<table border=\"0\" id=\"rubric\">
                  <tr>
                      <th width=\"33%\">$strname</th>
                      <th width=\"60%\">$strnotes</th>
                      <th width=\"7%\">$strpoints</th>
                      <th colspan=\"2\">$stroptions</th>
                  </tr>";

        $pr.=     $html;

        $pr.=    "<tr>
                      <td class=\"line\" align=\"left\">&nbsp;</td>
                      <td class=\"line\" align=\"right\">$strtotal</td>
                      <td class=\"line\" align=\"left\"><div style=\"white-space:nowrap\">{$this->points}</div></td>
                      <td colspan=\"2\"></td>
                  </tr>
               </table>";

        $pr.= "<input type=\"hidden\" name=\"layout\" value=\"{$this->creation_layout}\" />\n";

        // Styles
        $pr.= "<style type=\"text/css\">
               #rubric { width:100%; font-size:12pt; }
               #rubric th { white-space:nowrap; padding:0 5px 0 5px; font-size:11pt; text-align:left}
               #rubric td.line { font-weight:bold; font-size:13pt }
               #rubric td.name, #rubric td.notes, #rubric td.points { padding-right:5px; }
               #rubric div.rubric-popup { position:absolute; height:30px; width:30px; margin:-5px 0 0 -6px; }
               #rubric ul.rubric-popup li { text-align:left !important }
               #rubric ul.rubric-popup li a:hover { color:black !important }
               #rubric .delete a:hover { color:black !important }
               #rubric .insert a:hover { color:black !important }
               #rubric td.options { text-align:left; }
               #rubric tr.colored { background-color: #e1e8ea }
               #rubric tr.nocolor { background-color: none }
               #rubric td { padding-left:5px; font-size:10pt }
               #rubric ul { margin:0; padding:0; list-style:none; vertical-align:middle }
               #rubric ul li { vertical-align: middle; font-size:10pt; vertical-align:top; white-space:nowrap }
               #rubric ul.level0 { list-style:disc; padding-left:20px;}
               #rubric ul.level1 { list-style:circle; padding-left:40px; }
               #rubric ul.level2 { list-style:square; padding-left:60px; }
               #rubric ul.level3 { list-style:disc; padding-left:80px; }
               #rubric ul.level4 { list-style:circle; padding-left:100px; }
               #rubric ul.level5 { list-style:square; padding-left:120px; }
               #rubric ul.level6 { list-style:circle; padding-left:140px; }
               #rubric h1 { }
               #rubric h2 { padding:0;margin:0 } 
               </style>";
 
        // Scripts
        $pr.= "<script type=\"text/javascript\">
               layout = '{$this->creation_layout}';
               insert_id = '{$this->creation_id}';
               submit = function(){ document.forms.$form_name.submit(); };
               update = function(value){ /*alert('update: '+value);*/ document.forms.$form_name.layout.value = value; };
               // for adding & removing specs
               modifySpecs = function(action, partial, type){

                  switch(action){
                     case 'insert': 

                        var regex = new RegExp('^'+partial.replace(/\[/g,'\\\\['));
                        var rest = layout.replace(regex,'');

                        if(partial.length > 0 && rest.length == layout.length){
                            alert('Error (invalid \'partial\' parameter):\\n'+layout+'\\n'+partial);
                            return false;
                        }

                        //alert(partial +' => '+ rest);

                        switch(type){
                            case 'child':
                                if(partial.substr(partial.length-1) == ']'){
                                    partial = partial.substr(0,partial.length-1);
                                    final = partial + ',' + insert_id + ']' + rest;
                                }else{ // last char is a comma
                                    //partial = partial.substr(0,partial.length-1);
                                    final = partial + '[' + insert_id + ']' + rest;
                                }
                                break;
                            case 'above':
                                final = partial + insert_id+',' + rest;
                                break;
                            case 'below':
                                if(partial.substr(partial.length-1) == ']'){
                                    final = partial + insert_id+',' + rest;
                                }else{
                                    final = partial +','+ insert_id+',' + rest;
                                }
                                break;
                        }

                        final = final.replace(/,$/,'').replace(/,]/,']').replace(/,,/,',');

                        update(final);
                        submit();
                        break;

                     case 'delete':

                        // get remainder of string after this node was created
                        var regex = new RegExp('^'+partial.replace(/\[/g,'\\\\['));
                        var rest = layout.replace(regex,'');

                        // 'lil repetive .. 
                        if(!rest.match(/^\d*,/) && (part = rest.match(/^.*?]/))){    // can be no , before [
                            part = part[0];                                            
                            last = rest.substr(part.length);                        // remove from string
                            if(matches = part.match(/\[/g)){                        
                                brackets = matches.length;
                                while(--brackets > 0){                                // for each [
                                    if(part = last.match(/^.*?]/)){                    // we must find a ]
                                        part = part[0];
                                        last = last.substr(part.length);            // remove from string
                                        if(matches = part.match(/\[/g))                // if more [
                                            brackets += matches.length;
                                    }
                                }

                            }else{
                                last = rest.replace(/^\d+,?/,'');
                            }
                        }else{
                            last = rest.replace(/^\d+,?/,'');
                        }

                        final = (partial+last).replace(/,$/,'').replace(/,]/,']');

                        update(final);
                        submit();
                        break;
                   }
               };
               function $$(id){return document.getElementById(id);}
               showInsert = function(id){
                   obj = $$(id);
                   obj.style.display = 'block';
                   obj.timer = setTimeout('$$(\''+id+'\').style.display=\'none\';$$(\''+id+'\').onmouseout=null;',4000);
                   document.onclick = function(){
                        obj.style.display = 'none';
                        obj.onmouseout = null;
                        clearTimeout(obj.timer);
                   }
               }
               </script>";

        $pr.= g_button_style('delete','0','8pt','000','fff','cc0000');
        $pr.= g_button_style('insert','8pt','8pt','fff','fff','767676');
        $pr.= g_button_style('popup','8pt','8pt','fff','fff','2a6aa1');

        $pr.= "<div id=\"requires-javascript\" style=\"color:red;font-size:12pt;font-weight:bold\">This page requires Javascript</div>";
        $pr.= "<script type=\"text/javascript\">document.getElementById('requires-javascript').style.display = 'none';</script>";

        if(!$return) echo $pr;
        else return $pr;

    }

    function create_recurse($items, $parent, $level, $layout=''){

        $indent = $this->construct_ul_indentation($level);

        $html = '';
        $points = $children = $treeorder = 0;

        while(isset($items["{$parent}|{$treeorder}"])){

            $item = $items["{$parent}|{$treeorder}"];

            $treeorder++;

            $del_layout = $layout;
            $add_above_layout = $layout;

            $ID = $this->creation_id++;
            $layout .= $ID;
            if($this->creation_id > 200) error('Recursion error while parsing rubric.');

            list($_children, $_html, $_points, $layout) = 
                $this->create_recurse($items, $item['id'], $level+1, $layout.'[');

            // No children - we want to grade on this item
            if(!$_children){

                $add_child_layout = $layout;
                $add_below_layout = $layout;

                $layout .= ','; // add ','
                $total_str = $item['points'];
                $red = $item['points'] < 0 ? ';color:red' : '';

            // Has children - print out 
            }else{
                $layout .= ']'; // adds ']'
                $total_str = $_points;
                $item['points'] = 0; // we don't want to count these points 
                $red = '';

                $add_child_layout = $layout;
                $add_below_layout = $layout;
            }

            // Unescape \' \" \\
            $item['name'] = deSlashHTML($item['name']);
            $item['notes'] = deSlashHTML($item['notes']);

            $points += ($item['points'] < 0 ? 0 : $item['points']) + $_points; // .. and points
            $children += $_children + 1;         // sum total children

            $delete = compose_g_button('delete','','X',"javascript:modifySpecs('delete','$del_layout')");

            $popup = compose_g_button('popup',"<ul class=\"rubric-popup\">
                                                    <li><a href=\"javascript:modifySpecs('insert','$add_child_layout','child')\">New Child</li>
                                                    <li><a href=\"javascript:modifySpecs('insert','$add_above_layout','above')\">Sibling (Above)</li>
                                                    <li><a href=\"javascript:modifySpecs('insert','$add_below_layout','below')\">Sibling (Below)</li>
                                                </ul>");

            $insert = compose_g_button('insert',"<div class=\"rubric-popup\" 
                                                      id=\"rubric-popup-$ID\" 
                                                      style=\"display:none\">$popup</div>",
                                                'Insert',"javascript:showInsert('rubric-popup-$ID')");

            $html .= "<tr><td class=\"name\">
                              {$indent->in}
                              <input type=\"text\" style=\"width:100%\"
                                     name=\"_n$ID\" value=\"".$item['name']."\" />
                              {$indent->out}
                          </td>
                          <td class=\"notes\">
                              <input type=\"text\" style=\"width:100%\"
                                     name=\"_d$ID\" value=\"".$item['notes']."\" />
                          </td>
                          <td class=\"points\">
                              ".($_children?"
                              <strong>$total_str</strong>
                              ":"
                              <input type=\"text\" style=\"width:100%$red\"
                                     name=\"_p$ID\" value=\"$total_str\" />
                              ")."
                          </td>
                         <td>$insert</td>
                         <td>$delete</td>
                      </tr>
                      $_html";
        }

        return array($children, $html, $points, rtrim($layout,",[")); 
    }

    // Construct the UL/LI indention to look like each element is a child/parent.
    function construct_ul_indentation($indents, $is_title=null, $class_prefix='level'){

        $html->in = '<ul class="'.$class_prefix.$indents.(is_null($is_title)||!$is_title?'':'-title').' tt"><li>';
        $html->out = "</li></ul>";
        return $html;
    }

    function get_spec_data(){

        global $CFG;

        if($this->is_graded){

            $rubric_array = recordset_to_array(get_recordset_sql( $sql = "
                SELECT rs.id as specid, rs.name, rs.notes, ar.value as score, ar.description as descr, rs.points, rs.parent 
                  FROM {$CFG->prefix}assignment_submission_specs AS ar INNER JOIN 
                       {$CFG->prefix}assignment_rubric_specs AS rs ON rs.id = ar.rubricspecid 
                 WHERE ar.submissionid = ( 
                       SELECT id 
                         FROM {$CFG->prefix}assignment_submissions 
                         WHERE assignment = {$this->assignmentid} 
                           AND userId = {$this->userid}) 
                   AND rs.rubricid = {$this->id} 
              ORDER BY rs.parent, rs.treeorder"));
        }else{

            $rubric_array = recordset_to_array(get_recordset_sql( $sql = "
                SELECT id as specid, name, -1 as score, '' as descr, notes, points, parent 
                  FROM {$CFG->prefix}assignment_rubric_specs 
                 WHERE rubricid = {$this->id} 
              ORDER BY parent, treeorder"));
        }
   
        return $rubric_array;
    }

    // provide class-like functionality for an iterator on a recursive parameter
    function _layoutGetNext(&$str){
        global $m; $m = '';
        $matches = 0;
        $str_orig = $str;
        $str = preg_replace_callback(
            "/^,?(\d+|\[|\])(.*)/",
            create_function('$ms','global $m;$m=$ms[1];return $ms[2];'),
            $str);
        return $str_orig == $str ? null : $m;
    }

    // parse rubric specs from $_GET type variable
    function parseLayout($get){

        // Parse querystring
        $this->_data = $this->parseData($get);

        // if nothing to parse, return
        if(!isset($this->_data['layout']) || empty($this->_data['layout'])){
            return;
        }

        $_tmp = $this->_data['layout']; // for error reporting

        // Get objects
        $this->_parseLayoutRecurse( 0 ); // adds items to $this->specs

        if(!empty($this->_data['layout'])){
            $_tmp = str_replace($this->_data['layout'], '<font color=red>'.$this->_data['layout'].'</font>', $_tmp);
            error("Invalid layout string { <strong>$_tmp</strong> }."); 
        }
    }

    function _parseLayoutRecurse( $parent_id ){

        $treeorder = 0; // resets tree order for child set
        $new_parent_id = -1;

        while(($next = $this->_layoutGetNext($this->_data['layout'])) != null){
            if(is_numeric($next)){

                if($parent_id == -1) error('Invalid layout string (code=3).');

                $elem = null;

                $elem->parent            = $parent_id;
                $elem->treeorder         = $treeorder++;
                $elem->id                = $next;

                if(isset($this->_data[$next])){
                    $ndata = $this->_data[$next];
                    $elem->name          = isset($ndata->name) ? $ndata->name : '';
                    $elem->notes         = isset($ndata->desc) ? $ndata->desc : '';
                    $elem->points        = isset($ndata->points) ? $ndata->points : 0; //abs($ndata->points) : 0;
                }else{
                    $elem->name          = '';
                    $elem->notes         = '';
                    $elem->points        = 0;
                }

                $this->add_spec($elem);    // add to $rubric->specs
                $new_parent_id = $next;

            }elseif($next == '['){

                // get childree1n
                $this->_parseLayoutRecurse( $new_parent_id );

            }elseif($next == ']'){

                return; // no more children, return

            }else{
                error("Invalid layout string (code=2 value='$next' isnum='".is_numeric($next)."').");
            }

        }

        return; // ends on a top node
    }

    // parse Data from $_GET['layout'] | used in edit.php
    function parseData($get){

        $data = array();

        foreach($get as $key => $value){

            // if is preceded by '!' and has enough characters to parse
            if(substr($key,0,1) == '_' && strlen($key)>=2 && !empty($value)){

                switch(substr($key,1,1)){
                case 'n':   // name
                    $i = substr($key, 2);
                    $data[$i]->name = clean_param($value, PARAM_RAW);
                    break;
                case 'p':   // points
                    $i = substr($key, 2);
                    $data[$i]->points = clean_param($value, PARAM_INT);
                    break;
                case 'd':   // description
                    $i = substr($key, 2);
                    $data[$i]->desc = clean_param($value, PARAM_RAW);
                }

            }else{
                $key = clean_param($key, PARAM_TEXT);
                $data[$key] = clean_param($value, PARAM_RAW);
            }

        }

        return $data;
    }

    function process_submission($feedback, $submissionid){

        global $CFG;

        if(empty($feedback->grade_by_rubric)) return;

        $r_submission->id = $submissionid;
        $r_submission->scores = array();
        $r_submission->descs = array();

        foreach($feedback as $key => $value){

            if(strlen($key)>=9 && substr($key,0,8) == 'rsgrade:'){
                $rsid = substr($key,8);
                $r_submission->scores[intval($rsid)] = intval($value);
            }elseif(strlen($key)>=13 && substr($key,0,12) == 'DESCrsgrade:'){
                $rsid = substr($key,12);
                $r_submission->descs[intval($rsid)] = htmlspecialchars($value, ENT_QUOTES);
            }
        }

        if(empty($r_submission->scores)) error('Invalid submission data.');

        // We don't care if any records exist already - delete those that do (since we're just creating new ids upon inserting)
        execute_sql($sql ="DELETE FROM {$CFG->prefix}assignment_submission_specs 
                      WHERE submissionid = {$r_submission->id}", false);

        foreach($r_submission->scores as $specid => $score){
            
            if($r_submission->descs && isset($r_submission->descs[$specid]))
                 $desc = $r_submission->descs[$specid];
            else $desc = '';

            if(!execute_sql($sql = "
                    INSERT INTO {$CFG->prefix}assignment_submission_specs 
                           (submissionid, rubricspecid, value, description) 
                    VALUES ({$r_submission->id}, $specid, $score, '$desc')    ", false)){

                error("DB execution error.");
            }

        }
    
    }

    function print_upload_form($update_window=0){

        global $CFG;

        $struploadafile = get_string("selectfile", "assignment");
        $strmaxsize = get_string("maxsize", "", display_size($this->maxbytes));
        $strloadconfig = get_string("loadconfig", "assignment");

        echo '<div style="text-align:center">';
        echo '<form enctype="multipart/form-data" method="post" '."
            action=\"$CFG->wwwroot/mod/assignment/rubric/upload.php\">";
        echo '<fieldset class="invisiblefieldset">';
        echo "<p>$struploadafile ($strmaxsize)</p>";
        echo '<input type="hidden" name="course" value="'.$this->course->id.'" />';
        echo '<input type="hidden" name="updatewnd" value="'.$update_window.'" />';
        require_once($CFG->libdir.'/uploadlib.php');
        upload_print_form_fragment(1,array('configfile'),false,null,0,$this->maxbytes,false);
        echo '<input type="submit" name="save" value="'.$strloadconfig.'" />';
        echo '</fieldset>';
        echo '</form>';
        echo '</div>';

    }

    function upload() {

        global $CFG;

        require_capability('moodle/course:manageactivities', $this->context);

        $dir = 'rubric_import_files/'; // inside 

        require_once($CFG->libdir.'/uploadlib.php');
        $um = new upload_manager('configfile',true,false,$this->course,false,$this->maxbytes);
        if ($um->process_file_uploads($dir)) {

            unset($file);
            $file->path = $um->files['configfile']['fullpath'];
            $file->name = $um->files['configfile']['name'];
            $file->fh = fopen($file->path, 'r');
            
            while($row = fgets($file->fh)){
                $file->data .= $row;
            }

            fclose($file->fh);
            unset($file->fh);

            return $file;

        } else {
            return null;
        }

    }

    function to_xml(){

        if(!$this->specs_loaded)
            $this->get_specs();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<rubric name="'.htmlentities($this->name).'">'."\n";

        // normalize id numbers
        $offset = NULL;
        foreach($this->specs as $id => $spec){
            if(is_null($offset) || $id < $offset){
                $offset = $id;
            }
        }

        if(is_null($offset))
            error('An error was encountered during processing');

        $offset--;

        foreach($this->specs as $id => $spec){

            $id -= $offset;

            $parent = $spec['parent'] == 0 ? 0 : $spec['parent'] - $offset;
            $treeorder = $spec['treeorder'];
            $name = htmlentities($spec['name']);
            $notes = htmlentities($spec['notes']);
            $points = htmlentities($spec['points']);
            
            $xml .= "\t<spec id=\"$id\" parent=\"$parent\" treeorder=\"$treeorder\">\n";
            $xml .= "\t\t<name>$name</name>\n";
            $xml .= "\t\t<notes>$notes</notes>\n";
            $xml .= "\t\t<points>$points</points>\n";
            $xml .= "\t</spec>\n";

        }

        $xml .= "</rubric>\n";

        return $xml;
    
    }

    function view_footer(){
        print_footer($this->course);
    }

    function delete_instance(){
        return delete_records('assignment_rubric', 'id', $this->id) && 
               delete_records('assignment_rubric_specs', 'rubricid', $this->id);
    }

    function get_specs(){
    
        global $CFG;

        if($this->specs_loaded) return;
    
        $specs = recordset_to_array(get_recordset_sql($sql = "
            SELECT id, treeorder, name, notes, points, parent 
              FROM {$CFG->prefix}assignment_rubric_specs 
             WHERE rubricid = {$this->id};
            "));

        $this->specs = null;    // unset
        $this->specs_loaded = true;

        $this->add_specs($specs);

        $this->specs_in_sync = true;

    }

    function update_spec_field($id, $field, $value){

        $this->specs[$id][$field] = $value;
    }

    function remove_spec($id){

        unset($this->specs[$id]);
    }

    function _add_spec($spec){

        // if we haven't loaded it yet, do it now
        if(!$this->specs_loaded) $this->get_specs();

        $this->specs_in_sync = false;

        $this->array_merge_recursive_unique($this->specs, $spec);
        $this->spec_count = count($this->specs);
    }

    /**
     * Add a spec to the rubric. Nothing is saved until $this->commit() is issued
     * 
     * @param int $id only used as reference (new value will be given at ->commit)
     * @param int $treeorder order given for spec under parent
     * @param string $name 
     * @param string $notes comments about rubric item
     * @param int $parent parent item in rubric (-1 for top level)
     */
    function add_spec($spec){ #$id, $treeorder, $name, $notes, $points, $parent){
        
        if(is_array($spec)) $spec = (object)$spec;

        if(is_object($spec)){
                $this->_add_spec( array( $spec->id =>
                            array( 'treeorder'  => $spec->treeorder,
                                   'name'       => $spec->name,
                                   'notes'      => $spec->notes,
                                   'points'     => $spec->points,
                                   'parent'     => $spec->parent            )));

            return;
        }
    
        // else
        error('Spec must be of either type <i>object</i> (preferred) or <i>array></i>.');

    }   

    function add_specs($specs){

        if(is_array($specs))
            foreach($specs as $id => $spec){
                if(is_array($spec) && !isset($spec['id'])) $spec['id'] = $id; 
                elseif(is_object($spec) && !isset($spec->id)) $spec->id = $id; 
                $this->add_spec( $spec );
            }

    }

    /**
     * Saves changes to DB
     */
    function commit(){
   
        global $CFG;

        // no changes neccessary
        if ($this->specs_in_sync && $this->in_sync()) return 1;

        $error = 0;

        if (!$this->in_sync()){ // rubric values 
            
            $data->name = $this->name;
            $data->creatorid = $this->creatorid;
            $data->courseid = $this->courseid;
            $data->points = $this->points;
            $data->timemodified = time();

            if($this->is_new){

                /* I'm not entirely confident that all DBs (by default) set 
                 *   the AUTO_INCREMENT to 1. So if the returned value is 0 ...
                 */
                if (($this->id = insert_record('assignment_rubric', $data, true)) == 0){
                    $this->id = insert_record('assignment_rubric', $data, true); // do it again
                    delete_records('assignment_rubric', 'id', 0) || $error++;      // and cleanup
                }

                !empty($this->id) || $error++;

            }else{

                $data->id = $this->id;
                update_record('assignment_rubric', $data) || $error++;
            }

        }

        if (!$this->specs_in_sync) {

            // Clear all records
            if(!$this->is_new) delete_records('assignment_rubric_specs', 'rubricid', $this->id);
            
            // Get largest existing 'id' + 1
            $base_id = intval(get_field_select('assignment_rubric_specs', 'MAX(id)', null)) + 1;

            // & Re-insert them
            foreach($this->specs as $id => $spec){
                   
                unset($data);
                $data->id = ($id + $base_id); // Spec ID
                $data->rubricid = $this->id; 
                $data->treeorder = $spec['treeorder'];
                $data->name = $spec['name'];
                $data->notes = $spec['notes'];
                $data->points = $spec['points'];
                $data->parent = ($spec['parent'] == 0 ? 0 : $spec['parent'] + $base_id );
                $data->name = $this->enSlash($data->name);
                $data->notes = $this->enSlash($data->notes);
                
                // must use generalized insert b/c we need to specify the 'id'
                execute_sql("
                    INSERT 
                      INTO {$CFG->prefix}assignment_rubric_specs 
                           (id,             rubricid,           treeorder, 
                            name,           notes,              points, 
                            parent)
                    VALUES ($data->id,      $data->rubricid,    $data->treeorder,
                           '$data->name',  '$data->notes',      $data->points,
                            $data->parent) 
                    ", false /* no feedback */) || $error++;
            }   
        }

        $this->is_new = false;
        $this->in_sync = true;
        $this->specs_in_sync = true;

        return ($error == 0); // checks that all queries evaluated successfully

    }

    function validate(){

        // User must be valid
        if(empty($this->creatorid))
            return (object)array('error'=>true, 
                                 'message'=>'Rubric creation error: Cannot find user.',
                                 'fatal'=>true);

        // Course object much be valid
        if(!is_object($this->course))
            return (object)array('error'=>true, 
                                 'message'=>'Rubric creation error: Invalid Course.',
                                 'fatal'=>true);

        // Name must be something beyond spaces
        if(!preg_match('/[^ ]+/', $this->name))
            return (object)array('error'=>true, 
                                 'message'=>'Rubric must have a title.',
                                 'fatal'=>false);

        // There are any specs?
        if($this->spec_count == 0)
            return (object)array('error'=>true, 
                                 'message'=>'Rubric must atleast 1 item.',
                                 'fatal'=>false);

        // Allow 0 total points
        if(empty($this->points)) $this->points = 0; // guess we can allow this

        // Check each item heading (cant be just spaces)
        foreach($this->specs as $id => $spec)
            if(!preg_match('/[^ ]+/', $spec['name']))
                return (object)array('error'=>true, 
                                     'message'=>'Each item must have a name.',
                                     'fatal'=>false);

        // Is the name unique?
        if($this->is_new && !$this->check_name_availability($this->name))
            return (object)array('error'=>true, 
                                 'message'=>'A rubric with the name "'.$this->deSlash($this->name).'" already exists. Please choose another name.',
                                 'fatal'=>false);

        /*// Are the total points above 0 (since we're allowing negative specs)
        if($this->points < 0)
            return (object)array('error'=>true, 
                                 'message'=>'Rubric\'s total points must be above zero.',
                                 'fatal'=>false);*/

        // no error
        return (object)array('error'=>false);
    }

    function check_name_availability($name){
        return count_records('assignment_rubric', 'name',$name, 'courseid',$this->course->id) > 0 ? false : true;
    }

    function array_merge_recursive_unique(&$current, $updated) {

        // STRATEGY
        // http://us.php.net/manual/en/function.array-merge-recursive.php
        /*      
        Merge current and updated, overwriting 1st array values with 2nd array
        values where they overlap. Use current as the base array and then add
        in values from updated as they exist.

        Walk through each value in updated and see if a value corresponds
        in current. If it does, overwrite with second array value. If it's an

        array, recursively execute this function and return the value. If it's
        a scalar, overwrite the value from current with the value from updated.

        If a value exists in updated that is not found in current, add it to current.
        */

        // LOOP THROUGH $updated
        foreach($updated AS $k => $v) {

            // CHECK IF VALUE EXISTS IN $current
            if(!empty($current[$k])) {
                // IF VALUE EXISTS CHECK IF IT'S AN ARRAY OR A STRING
                if(!is_array($updated[$k])) {
                    // OVERWRITE IF IT'S A SCALAR/OBJECT
                    $current[$k]=$updated[$k];
                } else {
                    // RECURSE IF IT'S AN ARRAY
                    $current[$k] = $this->array_merge_recursive_unique($current[$k], $updated[$k]);
                }   
            } else {
                // IF VALUE DOESN'T EXIST IN $current USE $updated VALUE
                $current[$k]=$v;
            }   
        }   
        unset($k, $v);

        return $current;
    }

    function enSlash(&$string){ return enSlash($string); }
    function deSlash($string){ return deSlash($string); }
    function deSlash2($string){ return deSlashHTML($string); }
    function deSlashHTML($string){ return deSlashHTML($string); }
    function escHTML($string){ return escHTML($string); }

    function formerr($fatal, $message, $link_if_fatal){

        $button = $fatal ? "<br /><br /><input type=\"button\"
                onclick=\"javascript:document.location.href='$link_if_fatal'\" 
                value=\"Continue\" />" : '';

        echo "<table width=\"100%\"><tr><td width=\"100%\" align=\"center\">
                <p style=\"color:red; font-weight:bold\">
                   $message $button
                </p>
              </td></tr></table>";

        if($fatal) exit;

    }

    function get_assoc_assignments(){

        global $CFG;

        if(empty($this->id)) return null;

        $rubrics = recordset_to_array(get_recordset_sql($sql = "
            SELECT A.name, A.id, CM.id as cm
              FROM {$CFG->prefix}assignment A, 
                   {$CFG->prefix}course_modules CM,
                   {$CFG->prefix}modules M
             WHERE A.rubricid = {$this->id}
               AND CM.instance = A.id
               AND CM.module = M.id
               AND M.name = 'assignment'
            "));

        if(!is_array($rubrics)) return null;
        else                    return $rubrics;
    }

    function update_form_rubric(){
        // update listing and close()
        echo "<script type=\"text/javascript\">
              opener.addRubric('{$this->name} ({$this->points} pts)',{$this->id});
              window.close();
              </script>";
    }

} //// END rubric CLASS

function enSlash($string){
    return addslashes($string);
}

function deSlash($string){
    $string = str_replace("\\'",  "'", $string);
    $string = str_replace("\\\"", '"', $string);
    $string = str_replace("\\\\", "\\", $string);
    return $string;
}

function deSlashHTML($string){
    $string = str_replace("\\'", '&#39;', $string);
    $string = str_replace('\\"', '&#34;', $string);
    $string = str_replace('\\\\', '&#92;', $string);
    return $string;
}

function escHTML($string){
    $string = str_replace("'", '&#39;', $string);
    $string = str_replace('"', '&#34;', $string);
    $string = str_replace('\\', '&#92;', $string);
    return $string;
}

/**
 * Given an array of values, output the HTML for a select element with those options.
 * Normally, you only need to use the first few parameters.
 *
 */
function choose_from_menu2 ($options, $name, $selected, $script, $id) {

    $output = '<select id="menu'.$name.'" name="'. $name .'" onchange="'. $script .'">' . "\n";

    foreach ($options as $value => $label) {
        $output .= '   <option value="'. s($value) .'"';
        $output .= '>'. $value .'</option>' . "\n";
    }

    $output .= '</select>' . "\n";

    return $output;
}

/**
 * This is a modified verion of make_grades_menu from moodlelib
 * that allows negative grades. 
 * @param int $scale maximum points (or if negative, minimum points)
 */
function make_grades_list($scale){

    $max = $scale < 0 ? $scale : 0;
    $i = $scale < 0 ? 0 : $scale;

    $grades = array();
    for(; $i>=$max; $i--){
//        if($scale < 0)
//            $grades[
        $grades[$i] = "$i / $scale";
    }
    return $grades;
}

function get_rubrics_as_options($courseid){

    global $CFG;

    $rubrics = recordset_to_array(get_recordset_sql($sql = "
        SELECT id, name, points
          FROM {$CFG->prefix}assignment_rubric
         WHERE courseid = '$courseid'
         ORDER BY name
        "));
    
    $external_rubrics = recordset_to_array(get_recordset_sql($sql = "
        SELECT r.id, r.name, r.points, r.courseid, c.shortname
          FROM {$CFG->prefix}assignment_rubric r,  
                  {$CFG->prefix}course c
         WHERE c.id = r.courseid AND
               courseid != '$courseid'
         ORDER BY c.shortname
        "));

    $options = array();

    if (is_array($rubrics)) {
        foreach($rubrics as $id => $rubric)
            $options[$id] = "{$rubric->name} ({$rubric->points} pts)";
    }
    
    if (is_array($external_rubrics)) {
        foreach($external_rubrics as $id => $rubric)
            $options[$id] = "{$rubric->shortname} :: {$rubric->name} ({$rubric->points} pts)";
    }

    return $options;
}

function red($value){
    if($value < 0)
        return "<span class=\"red\">$value</span>";
    else
        return $value;
}

function get_list_count_in_course(){
    return count_records('assignment_rubric', 'courseid', $this->course->id); 
}

function rubric_get_list($courseid){
    // Returns values for the mod_form.php page (DO NOT CHANGE VALUES)
    global $CFG;

    $rubrics = recordset_to_array(get_recordset_sql($sql = "
        SELECT id as value, name as text, points, 1 as _RUBRIC
        FROM {$CFG->prefix}assignment_rubric 
        WHERE courseid = $courseid
        "));

    if(!is_array($rubrics)) return null;
    else                    return $rubrics;

}

function rubric_get_list_in_course($courseid){
    // Returns all rubrics ...s
    global $CFG;

    $rubrics = recordset_to_array(get_recordset_sql($sql = "
        SELECT r.id, r.name, r.points, r.timemodified, r.courseid, r.creatorid as userid,
        u.firstname as first, u.lastname as last, 
        ( SELECT COUNT(*) 
        FROM {$CFG->prefix}assignment
        WHERE rubricid = r.id ) count
        FROM {$CFG->prefix}assignment_rubric r, {$CFG->prefix}user u
        WHERE r.courseid = $courseid AND 
        u.id = r.creatorid
        "));

    if(!is_array($rubrics)) return null;
    else                    return $rubrics;

}

function g_button_style($id, $label_size='60%',$text_size='60%',$link_color='000',$text_color='000',$back_color='767676', $padding='4px', $decoration='none'){
    
    switch($back_color){
        case '767676': 
        case 'ffffff': 
        case '2a6aa1': 
        case 'cc0000': break;
        default: error('Unknown `background-color`.');
    }

    global $CFG;

    return "<style type=\"text/css\">
            div.$id table{margin:0 auto 0 auto !important;cursor:pointer !important; }
            div.$id .corner,div.$id .span, div.$id table{font-size:0 !important}
            div.$id td {padding:0 0 0 $padding !important}
            div.$id .span,div.$id .message{background:#$back_color !important;margin:0 !important;font-family:arial,sans-serif !important;padding:0 !important;}
            div.$id .corner{background-image: url({$CFG->wwwroot}/mod/assignment/rubric/pix/rc_$back_color.png) !important;width:4px !important;height:4px !important;border-collapse:collapse !important;background-repeat:no-repeat !important;}
            div.$id .message{color:#$link_color !important;font-size:$label_size !important;font-weight:bold !important;white-space:nowrap !important;padding:0 2px 0 2px !important}
            div.$id a{text-decoration:$decoration !important;color:#$text_color !important; font-size:$text_size !important;white-space:nowrap !important}
            </style>";
}

function compose_g_button($id, $label='', $text='', $link=''){

    if(preg_match('/^javascript/',$link)){
        $link2 = $link;
    }else{
        $link2 = "document.location.href='$link'";
    }

    $pr = '<div class="'.$id.'"><table cellspacing="0" cellpadding="0"><tbody><tr>
           <td class="corner" style="background-position: 0px 0px;"/><td class="span"/>
           <td class="corner" style="background-position: -4px 0px;"/></tr><tr>
           <td class="span"/><td class="message">';

    $pr.= "$label<a href=\"$link\">$text</a>";

    $pr.= '</td><td class="span"/></tr><tr><td class="corner" style="background-position: 0px -4px;"/>
           <td class="span"/><td class="corner" style="background-position: -4px -4px;"/>
           </tr></tbody></table></div>';

    return $pr;
}

function alert($message, $url){
?>
<script type="text/javascript">
//<![CDATA[
    alert('<?php echo $message ?>');
    document.location.replace('<?php echo $url ?>');
//]]>
</script>
<?php
    die;
}

// taken from: http://www.phpit.net/code/force-download/
function force_download ($data, $name, $mimetype='', $filesize=false) {
    // File size not set?
    if ($filesize == false OR !is_numeric($filesize)) {
        $filesize = strlen($data);
    }

    // Mimetype not set?
    if (empty($mimetype)) {
        $mimetype = 'application/octet-stream';
    }

    // Make sure there's not anything else left
    ob_clean_all();

    // Start sending headers
    header("Pragma: public"); // required
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Cache-Control: private",false); // required for certain browsers
    header("Content-Transfer-Encoding: binary");
    header("Content-Type: " . $mimetype);
    header("Content-Length: " . $filesize);
    header("Content-Disposition: attachment; filename=\"" . $name . "\";" );

    // Send data
    echo $data;
    die();
}

function ob_clean_all () {
    $ob_active = ob_get_length () !== false;
    while($ob_active) {
        ob_end_clean();
        $ob_active = ob_get_length () !== false;
    }

    return true;
}

function assignment_count_graded($assignment) {
/// Returns the count of all graded submissions by ENROLLED students (even empty)
    global $CFG;

    //$assignment->course == SITEID

    $cm = get_coursemodule_from_instance('assignment', $assignment->id);
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);

    // this is all the users with this capability set, in this context or higher
    if ($users = get_users_by_capability($context, 'mod/assignment:submit', '', '', '', '', 0, '', false)) {
        foreach ($users as $user) {
            $array[] = $user->id;
        }

        $userlists = '('.implode(',',$array).')';

        return count_records_sql("SELECT COUNT(*)
                                    FROM {$CFG->prefix}assignment_submissions
                                   WHERE assignment = '$assignment->id' 
                                     AND timemarked > 0
                                     AND userid IN $userlists ");
    } else {
        return 0; // no users enroled in course
    }
}

