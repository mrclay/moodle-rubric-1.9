/* called by ../mod_form.php; used to control assigment editing */

// DOM element object -> rubric's select dropdown
var ru = $$('id_rubricid');

// Allows Rubric & Points select dropdown's to interact
function updateElem(value){
    var ob = $$('id_grade'); // old 'points' grading dropdown
    var i = ru.selectedIndex;
    var l = ru.options.length - 2;

    if(i < l && (!isNumeric(value) || value == 0)){
        ob.disabled = false;
    }else{
        ob.disabled = true;
    }
}

// Is called from popup windows after adding new rubrics
function addRubric(text, value){
    ru.options[0] = new Option(text,value); 
    ru.selectedIndex = 0;
    updateElem(value);
}

function isNumeric(num){
    var x = (isNaN(num) || num == null);
    var y = (num.toString() == 'true' || num.toString() == 'false');
    return !(x || y);
}
