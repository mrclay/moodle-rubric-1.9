<?php

global $CFG;
require_once($CFG->libdir.'/form/select.php');

/**
 * Class to dynamically create an HTML SELECT
 *
 * @author       Spencer Creasey <screayse@gmail.com>
 * @author       Adam Daniel <adaniel1@eesus.jnj.com>
 * @author       Bertrand Mansion <bmansion@mamasam.com>
 * @version      1.1
 * @since        PHP4.04pl1
 * @access       public
 */
class MoodleQuickForm_advselect extends MoodleQuickForm_select {

    var $_avdoptions = array();
    var $_scriptevents = array();
    var $_script = '';
    var $_rubrics = array();

    /*function MoodleQuickForm_advselect($elementName=null, $elementLabel=null, $options=null, $attributes=null)
    {
        MoodleQuickForm_select::MoodleQuickForm_select($elementName, $elementLabel, $options, $attributes);
    } //end constructor */
    
    /**
     * html for help button, if empty then no help
     *
     * @var string
     */
    function toHtml(){
        if ($this->_hiddenLabel){
            $this->_generateId();
            return '<label class="accesshide" for="'.$this->getAttribute('id').'" >'.
                        $this->getLabel().'</label>'.$this->toHtml_helper();
        } else {
            return $this->toHtml_helper();
        }
    }

    function getRubrics(){
        return $this->_rubrics;
    }

    /**
     * Returns the current API version 
     * 
     * @since     1.0
     * @access    public
     * @return    double
     */
    function apiVersion()
    {
        return 2.4;
    } //end func apiVersion

    // }}}
    // {{{ addOption()

    /**
     * Adds a new OPTION to the SELECT
     *
     * @param     string    $text       Display text for the OPTION
     * @param     string    $value      Value for the OPTION
     * @param     mixed     $attributes Either a typical HTML attribute string 
     *                                  or an associative array
     * @since     1.0
     * @access    public
     * @return    void
     */
    function addOption($text, $value, $attributes=null)
    {
        if (null === $attributes) {
            $attributes = array('value' => $value);
        } else {
            $attributes = $this->_parseAttributes($attributes);
            if (isset($attributes['selected'])) {
                // the 'selected' attribute will be set in toHtml()
                $this->_removeAttr('selected', $attributes);
                if (is_null($this->_values)) {
                    $this->_values = array($value);
                } elseif (!in_array($value, $this->_values)) {
                    $this->_values[] = $value;
                }
            }
            $this->_updateAttrArray($attributes, array('value' => $value));
        }

        // default implimentation
        $this->_options[] = array('text' => $text, 'attr' => $attributes); // only submittable values

        // extended ..
        $this->_advoptions[] = array( 'type' => 'DEFAULT', 'options' => array('text' => $text, 'attr' => $attributes));

    } // end func addOption
    
    function addAdvOption($type, $attrs=array()){
        $this->_advoptions[] = array( 'type' => $type, 'options' => $attrs); 
    } // end func addAvdOption

    // }}}
    // {{{ loadArray()

    /**
     * Loads the options from an associative array
     * 
     * @param     array    $arr     Associative array of options
     * @param     mixed    $values  (optional) Array or comma delimited string of selected values
     * @since     1.0
     * @access    public
     * @return    PEAR_Error on err7r or true
     * @throws    PEAR_Error
     */
    function loadArray($arr, $values=null)
    {
        if (!is_array($arr)) {
            return PEAR::raiseError('Argument 1 of MoodleQuickForm_advselect::loadArray is not a valid array');
        }
        if (isset($values)) {
            $this->setSelected($attrs);
        }
       
        while(list(,$item) = each($arr)){
       
            // If the object is an advanced option. 
            if(isset($item['_ADVANCED']) && $item['_ADVANCED']){
                switch($item['_TYPE' ]){ // Add more cases here
                case 'POPUP':
                    $this->addAdvOption('POPUP', array( 'text'   => $item['text'],
                                                        'link'   => $item['link'],
                                                        'params' => $item['params'],
                                                        'js'     => $item['js'],
                                                        'attr'   => array( 'value'  => $item['value'])));
                    break;
                case 'GROUP':
                    $this->addAdvOption('GROUP', array( 'options'=> (isset($item['options']) ? $item['options'] : null),
                                                        'attr'   => array( 'value'  => $item['value'], 
                                                                           'label'  => $item['label'])));
                    break;
                default :
                    return PEAR::raiseError("MoodleQuickForm_advselect::loadArray - Argument '".$item['_TYPE']."' is not a valid option ('GROUP','POPUP', etc.)");
                    /*error('This type ('.$item['_TYPE'].') is not yet implimented. 
                        See /mod/assignment/form/advselect.php to add cases');*/
                }

            // Rubric item
            }elseif(isset($item['_RUBRIC']) && $item['_RUBRIC']){
                
                $newTxt = $item['text'].' ('.$item['points'].' pts)'; // show points

                $this->_rubrics[] = array( 'rubricid' => $item['value'], 'points' => $item['points'] ); 
                $this->addOption($newTxt, $item['value'] );    // it becomes a normal option after 
                                                               // we add it's max points to _rubrics

            // Regular items (i.e. <OPTION VALUE="$value">$text</OPTION> )
            }elseif(is_array($item)){
                
                if(!isset($item['value'])) $item['value'] = $item['text'];
                $this->addOption($item['text'], $item['value'] );

            }else{ $this->addOption($item, $item); }

        }

        return true;
    } // end func loadArray

    // }}}
    // {{{ toHtml()

    /**
     * Returns the SELECT in HTML
     *
     * @since     1.0
     * @access    public
     * @return    string
     */
    function toHtml_helper()
    {
        if ($this->_flagFrozen) {
            return $this->getFrozenHtml();
        } else {
            $tabs    = $this->_getTabs();
            $strHtml = '';

            if ($this->getComment() != '') {
                $strHtml .= $tabs . '<!-- ' . $this->getComment() . " //-->\n";
            }

            if (!$this->getMultiple()) {
                $attrString = $this->_getAttrString($this->_attributes);
            } else {
                $myName = $this->getName();
                $this->setName($myName . '[]');
                $attrString = $this->_getAttrString($this->_attributes);
                $this->setName($myName);
            }
            $strHtml .= $tabs . '<select ' . $attrString . ">\n";

            $option_id = 0;
            
            foreach($this->_advoptions as $o){
    
                switch($o['type']){

                // Regular <OPTION value=""></OPTION>
                case 'DEFAULT':
                    if (is_array($this->_values) && in_array((string)$o['options']['attr']['value'], $this->_values)) {
                        $this->_updateAttrArray($o['options']['attr'], array('selected' => 'selected'));
                    }

                    $strHtml .= "$tabs<option " . $this->_getAttrString($o['options']['attr']) . '>' .
                                $o['options']['text'] . "</option>\n";
                    break;

                // Append option that opens new window
                case 'POPUP':
                    // Allows for graceful javascript failure
                    $strHtml .= "$tabs<option id=\"option-".(++$option_id).'" value="0" disabled="disabled" style="display:none">[disabled javascript function]</option>'."\n";

                    // The option is printed in place (above), but with display=none, value=0, dummy 
                    // text (for IE, of course), and disabled. This does a couple things: (1) matains 
                    // the placement of options (this can be approved upon for IE). (2) renders the 
                    // option useless. Since IE doesn't accept "disabled" or display=block, the text
                    // still shows, so we give it dummy text. 
                    // When javascript is enabled, the call (below) adds scripting that counters all
                    // 3 safeguards. Tested on Safari 3 (Mac & PC), firefox 2 & 3 (Linix, Mac & PC),
                    // IE 6 & 7, Opera 9.5b1 (Linix) even scrapes by.
                    $this->addJS("$$('option-$option_id').text = '".$o['options']['text']."';\n".
                                 "$$('option-$option_id').value = '".$o['options']['attr']['value']."';\n".
                                 "$$('option-$option_id').style.display = 'block';\n".
                                 "$$('option-$option_id').disabled = false;\n");

                    $this->addJSEvent('onchange', 
                        "   if(this.value=='".$o['options']['attr']['value']."'){ \n".
                        "      ".$o['options']['js'].";\n".
                        "      window.open('".$o['options']['link']."','".$o['options']['attr']['value']."', '".$o['options']['params']."');\n".
                        "   }");

                    break;

                // Append option group
                case 'GROUP':
                    $strHtml .= "$tabs\t<optgroup ".$this->_getAttrString($o['options']['attr']).'>';
                    if(isset($o['options']['options'])){
                        foreach($o['options']['options'] as $opt){
                            $strHtml .= "$tabs<option" . $this->_getAttrString($opt['options']['attr']).'>'.
                                            $opt['options']['text'] . "</option>\n";
                        }
                    }
                    $strHtml .= "$tabs</optgroup>\n";
                    break;

                default:
                    return PEAR::raiseError("MoodleQuickForm_advselect::loadArray - Argument '".$item['_TYPE']."' is not a valid option ('GROUP','POPUP', etc.)");
                }
            }
            
            return "{$strHtml}{$tabs}</select>".$this->getJS();
        }
    } //end func toHtml
    
    function addJSEvent($event, $script){
    
        /* Bug fix for PHP4 */
        static $_scriptevents = array();
    
        if(!isset($_scriptevents[$event])){
            $_scriptevents[$event] = $script;
        }else{
            $_scriptevents[$event] .= "\n$script";
        }
        
        $this->_scriptevents = $_scriptevents;
    }

    function addJS($script){
    
        /* Bug fix for PHP4 */
        static $_script = '';
        $_script .= $script;
        
        $this->_script .= $_script;
    }

    function getJS(){
        
        $html = "
            \n<script type=\"text/javascript\">
            \nfunction $$(id){ return document.getElementById(id); }
            \n/* --- Script --- */\n".$this->_script."
            \n/* --- JSEvents --- */\n".$this->getJSEvents()."\n
            \n</script>\n";

        return $html;
    }
    
    function getJSEvents(){
        $events = null;
        $id = $this->getAttribute('id');
        foreach($this->_scriptevents as $event => $code){
            $events .= "\$\$('$id').$event = function(){\n$code\n};\n";
        }
        return $events;
    }

    /**
     * Returns the value of field without HTML tags
     * 
     * @since     1.0
     * @access    public
     * @return    string
     */
    function getFrozenHtml()
    {
        $value = array();
        if (is_array($this->_values)) {
            foreach ($this->_values as $key => $val) {
                for ($i = 0, $optCount = count($this->_options); $i < $optCount; $i++) {
                    if ((string)$val == (string)$this->_options[$i]['attr']['value']) {
                        $value[$key] = $this->_options[$i]['text'];
                        break;
                    }
                }
            }
        }
        $html = empty($value)? '&nbsp;': join('<br />', $value);
        if ($this->_persistantFreeze) {
            $name = $this->getPrivateName();
            // Only use id attribute if doing single hidden input
            if (1 == count($value)) {
                $id     = $this->getAttribute('id');
                $idAttr = isset($id)? array('id' => $id): array();
            } else {
                $idAttr = array();
            }
            foreach ($value as $key => $item) {
                $html .= '<input' . $this->_getAttrString(array(
                             'type'  => 'hidden',
                             'name'  => $name,
                             'value' => $this->_values[$key]
                         ) + $idAttr) . ' />';
            }
        }
        return $html;
    } //end func getFrozenHtml

   /**
    * We check the options and return only the values that _could_ have been
    * selected. We also return a scalar value if select is not "multiple"
    */
    function exportValue(&$submitValues, $assoc = false)
    {
        $value = $this->_findValue($submitValues);
        if (is_null($value)) {
            $value = $this->getValue();
        } elseif(!is_array($value)) {
            $value = array($value);
        }
        if (is_array($value) && !empty($this->_options)) {
            $cleanValue = null;
            foreach ($value as $v) {
                for ($i = 0, $optCount = count($this->_options); $i < $optCount; $i++) {
                    if ($v == $this->_options[$i]['attr']['value']) {
                        $cleanValue[] = $v;
                        break;
                    }
                }
            }
        } else {
            $cleanValue = $value;
        }
        if (is_array($cleanValue) && !$this->getMultiple()) {
            return $this->_prepareValue($cleanValue[0], $assoc);
        } else {
            return $this->_prepareValue($cleanValue, $assoc);
        }
        
        return null;
    }
    
    // }}}
    // {{{ onQuickFormEvent()

    function onQuickFormEvent($event, $arg, &$caller)
    {
        if ('updateValue' == $event) {
            $value = $this->_findValue($caller->_constantValues);
            if (null === $value) {
                $value = $this->_findValue($caller->_submitValues);
                // Fix for bug #4465 & #5269
                // XXX: should we push this to element::onQuickFormEvent()?
                if (null === $value && (!$caller->isSubmitted() || !$this->getMultiple())) {
                    $value = $this->_findValue($caller->_defaultValues);
                }
            }
            if (null !== $value) {
                $this->setValue($value);
            }
            return true;
        } else {
            return parent::onQuickFormEvent($event, $arg, $caller);
        }

    }

    // }}}
} //end class HTML_QuickForm_advselect
?>
