<?php

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir."/formslib.php");
require_once($CFG->libdir.'/gradelib.php');
require_once($CFG->libdir.'/tablelib.php');
require_once($CFG->dirroot."/repository/lib.php");
require_once($CFG->dirroot."/grade/grading/lib.php");

class mod_assign_grading_form_fn extends moodleform {

    /** @var stores the advaned grading instance (if used in grading) */
    private $advancegradinginstance;

    function definition() {
        
        global $OUTPUT, $CFG, $DB, $PAGE, $USER;
        
        $mform = & $this->_form;  
        if (isset($this->_customdata->advancedgradinginstance)) {
            $this->use_advanced_grading($this->_customdata->advancedgradinginstance);
        }
        
        list($assignment, $data, $params) = $this->_customdata;        
        
        $rownum = $params['rownum'];
        $last = $params['last'];
        $useridlist = $params['useridlist']; //echo $rownum; print_r($useridlist);
        $userid = $useridlist[$rownum];
        if ($params['resubmission']){ 
            $submissionnum = $params['submissionnum'];
            $maxsubmissionnum = isset($params['maxsubmissionnum']) ? $params['maxsubmissionnum'] : $params['submissionnum'];
        }
        
        $user = $DB->get_record('user', array('id' => $userid));       
        
        
        $submission = get_user_submission($assignment, $userid, false); //print_r($submission);
        $submissiongroup = null;
        $submissiongroupmemberswhohavenotsubmitted = array();
        $teamsubmission = null;
        $notsubmitted = array();
        if ($assignment->get_instance()->teamsubmission) {
            $teamsubmission = $assignment->get_group_submission($userid, 0, false);
            $submissiongroup = $assignment->get_submission_group($userid);
            $groupid = 0;
            if ($submissiongroup) {
                $groupid = $submissiongroup->id;
            }
            $notsubmitted = $assignment->get_submission_group_members_who_have_not_submitted($groupid, false);
            if(isset($submissiongroup->name)){
                $groupname = ' ('.$submissiongroup->name.')';
            }else{
                $groupname = ' (Default group)'; //
            }
            

        }else{
            $groupname = '';
        }  
        
        
        
        ///  start the table
        $mform->addElement('html', '<span style="text-align: right;"');
        $mform->addElement('static', 'progress', '', get_string('gradingstudentprogress', 'block_fn_marking', array('index'=>$rownum+1, 'count'=>count($useridlist))));
        $mform->addElement('html', '</span>');
        $mform->addElement('html', '<table border="0" cellpadding="0" cellspacing="0" border="1" width="100%" class="saprate-table">');

        //print the marking header in first tr
        $mform->addElement('html', '<tr>');         

        $this->add_marking_header($user, 
                                  $assignment->get_instance()->name, 
                                  $assignment->is_blind_marking(), 
                                  $assignment->get_uniqueid_for_user($userid),  
                                  $assignment->get_course()->id, 
                                  has_capability('moodle/site:viewfullnames', $assignment->get_course_context()),
                                  $rownum , 
                                  $last,
                                  $groupname,
                                  $assignment->get_course_module(),
                                  $params);
                                  
        $mform->addElement('html', '</tr>');
        
        //GRADING
        $mform->addElement('html', '<tr>');
        $mform->addElement('html', '<td class="yellowcell" colspan="2">');
        
        
        if ($params['resubmission']){ 
            $grade = $assignment->get_user_grade($userid, false, $submissionnum);
        }else{
            $grade = $assignment->get_user_grade($userid, false);
        }
        
        // add advanced grading
        $gradingdisabled = $assignment->grading_disabled($userid);
        $gradinginstance = fn_get_grading_instance($userid, $gradingdisabled, $assignment);

        $gradinginfo = grade_get_grades($assignment->get_course()->id,
                                'mod',
                                'assign',
                                $assignment->get_instance()->id,
                                $userid);
                                
        //Fix grade string for select form
        if ($gradinginfo->items[0]->grades[$userid]->str_grade == "-"){
            $stu_grade = '-1';
        }else{
            $stu_grade = $gradinginfo->items[0]->grades[$userid]->str_grade;
        }
        
        ############
        $mform->addElement('html', '<table class="teacherfeedback" border="0" cellpadding="0" cellspacing="0" width="100%">');
        $mform->addElement('html', '<tr>');
        $mform->addElement('html', '<td width="50%" align="left">');
        $mform->addElement('html', '<b>Teacher\'s Feedback </b> <br /> '.$USER->firstname.' '.$USER->lastname.' <br /> '.userdate(time()));
        $mform->addElement('html', '</td>');        
        $mform->addElement('html', '<td width="50%" align="right">');
        
        
                
        if ($gradinginstance) {       
            
            $gradingelement = $mform->addElement('grading', 'advancedgrading', get_string('grade').':', array('gradinginstance' => $gradinginstance));
            if ($gradingdisabled) {
                $gradingelement->freeze();
            } else {
                $mform->addElement('hidden', 'advancedgradinginstanceid', $gradinginstance->get_id());
            }
        } else {
            // use simple direct grading
            if ($assignment->get_instance()->grade > 0) {
                
                $attributes = array();
                if ($gradingdisabled) {
                    $attributes['disabled'] = 'disabled';
                }
                        
                $grademenu = make_grades_menu($assignment->get_instance()->grade);
                $grademenu['-1'] = get_string('nograde');
                $mform->addElement('select', 'grade', get_string('grade', 'block_fn_marking'), $grademenu, $attributes);
                $mform->setDefault('grade', $stu_grade); //@fixme some bug when element called 'grade' makes it break
                $mform->setType('grade', PARAM_INT);                
                
                if ($gradingdisabled) {
                    $gradingelement->freeze();
                }
            } else {
                $grademenu = make_grades_menu($assignment->get_instance()->grade);
                if (count($grademenu) > 0) {
                    $gradingelement = $mform->addElement('select', 'grade', get_string('grade').':', $grademenu);
                    $mform->setType('grade', PARAM_INT);
                    if ($gradingdisabled) {
                        $gradingelement->freeze();
                    }
                }
            }
        }
    
        
        
        if ($params['resubmission']){
            //$maxsubmissionnum = isset($params['maxsubmissionnum']) ? $params['maxsubmissionnum'] : $params['submissionnum'];
            $resubtype = $assignment->get_instance()->resubmission;
            if ($resubtype != assign::RESUBMISSION_NONE && $submissionnum == $maxsubmissionnum) {
                if ($assignment->reached_resubmission_limit($submissionnum)) {
                    $mform->addElement('static', 'staticresubmission', get_string('resubmission', 'assign'),
                                       get_string('atmaxresubmission', 'assign'));
                } else if ($resubtype == assign::RESUBMISSION_MANUAL) {
                    $mform->addElement('checkbox', 'resubmission', 'Allow student to resubmit');
                    //$mform->setDefault('resubmission', 1);
                } else if ($resubtype == assign::RESUBMISSION_FAILEDGRADE) {
                    $gradepass = $gradinginfo->items[0]->gradepass;
                    if ($gradepass > 0) {
                        $mform->addElement('static', 'staticresubmission', get_string('resubmission', 'assign'),
                                           get_string('resubmissiononfailedgrade', 'assign', $gradepass));
                    }
                }
            }
        }        
        //$mform->addElement('html', 'Submission comments (3)');
        $mform->addElement('html', '</td>');
        $mform->addElement('html', '</table>');
        
        ############ 
        

        // Let feedback plugins add elements to the grading form.
        //fn_add_plugin_grade_elements($grade, $mform, $data, $userid, $assignment);  
          
        $feedbackplugins = fn_load_plugins('assignfeedback', $assignment);
        
        foreach ($feedbackplugins as $plugin) {  
            if ($plugin->is_enabled() && $plugin->is_visible()) {
                
                if($plugin->get_type() == 'file'){
                    $mform->addElement('html', '<br /><div style="text-align: left; font-weight: bold;">Feedback files </div>');
                } 
                
                if ($plugin->get_form_elements_for_user($grade, $mform, $data, $userid)) {
                 //print_r($data);die;   
                }
            }
        }
            

        // hidden params
        $mform->addElement('hidden', 'id', $assignment->get_course_module()->id);
        $mform->setType('id', PARAM_INT);
        
        $mform->addElement('hidden', 'rownum', $rownum);
        $mform->setType('rownum', PARAM_INT);        
        $mform->setConstant('rownum', $rownum);
        
        $mform->addElement('hidden', 'useridlist', implode(',', $useridlist));         
        $mform->setType('useridlist', PARAM_TEXT);
        
        $mform->addElement('hidden', 'ajax', optional_param('ajax', 0, PARAM_INT));
        $mform->setType('ajax', PARAM_INT);

        if ($assignment->get_instance()->teamsubmission) {
            $mform->addElement('selectyesno', 'applytoall', get_string('applytoteam', 'assign'));
            $mform->setDefault('applytoall', 1);
        }

        $mform->addElement('hidden', 'action', 'submitgrade');
        $mform->setType('action', PARAM_ALPHA);

        
        
        $mform->addElement('html', '</td>');
        $mform->addElement('html', '</tr>');
        

          
        $mform->addElement('html', '<tr>');
        $mform->addElement('html', '<td class="bluecell" colspan="2">');

        $mform->addElement('html', '<table class="studentsubmission" border="0" cellpadding="0" cellspacing="0" width="100%">');
        $mform->addElement('html', '<tr>');
        $mform->addElement('html', '<td valign="top" width="50%" align="left">');
 
        
  
        
        
        
        if (($assignment->can_view_submission($userid)) || ($params['readonly'])) {
            //$gradelocked = ($grade && $grade->locked) || $assignment->grading_disabled($userid);
            
          $gradelocked = ($grade && $grade->locked) || $assignment->grading_disabled($userid);
          $extensionduedate = null;
          if ($grade) {
              $extensionduedate = $grade->extensionduedate;
          }
          $showedit = $assignment->submissions_open($userid) && ($assignment->is_any_submission_plugin_enabled());

          if ($teamsubmission) {
              $showsubmit = $showedit && $teamsubmission && ($teamsubmission->status == ASSIGN_SUBMISSION_STATUS_DRAFT);
          } else {
              $showsubmit = $showedit && $submission && ($submission->status == ASSIGN_SUBMISSION_STATUS_DRAFT);
          }
          $viewfullnames = has_capability('moodle/site:viewfullnames', $assignment->get_course_context());                 
                                                           
          $status = new assign_submission_status($assignment->get_instance()->allowsubmissionsfromdate,
                                                                $assignment->get_instance()->alwaysshowdescription,
                                                                $submission,
                                                                $assignment->get_instance()->teamsubmission,
                                                                $teamsubmission,
                                                                $submissiongroup,
                                                                $notsubmitted,
                                                                $assignment->is_any_submission_plugin_enabled(),
                                                                $gradelocked,
                                                                fn_is_graded($userid, $assignment),//$assignment->is_graded($userid),
                                                                $assignment->get_instance()->duedate,
                                                                $assignment->get_instance()->cutoffdate,
                                                                $assignment->get_submission_plugins(),
                                                                $assignment->get_return_action(),
                                                                $assignment->get_return_params(),
                                                                $assignment->get_course_module()->id,
                                                                $assignment->get_course()->id,
                                                                assign_submission_status::GRADER_VIEW,
                                                                $showedit,
                                                                $showsubmit,
                                                                $viewfullnames,
                                                                $extensionduedate,
                                                                $assignment->get_context(),
                                                                $assignment->is_blind_marking(),
                                                                '');
                                                                
                                                                
                                                                
            //$mform->addElement('html', '<tr>');
            
            //$this->add_assign_submission_status($status);
                                      
            //$mform->addElement('html', '</tr>');                                                                
                                                              
        } 
 
        // Show graders whether this submission is editable by students.
        if ($status->view == assign_submission_status::GRADER_VIEW) {
            //$row = new html_table_row();
            //$cell1 = new html_table_cell(get_string('editingstatus', 'assign'));
            if ($status->canedit) {
                $editingstatus = get_string('submissioneditable', 'assign');
            
            } else {
                $editingstatus = get_string('submissionnoteditable', 'assign');
            
            }

        }
        
        
        
        
        
        // Last modified.
        $tsubmission = $status->teamsubmission ? $status->teamsubmission : $status->submission;
        
        if ($tsubmission) {
            $submissiontime = userdate($tsubmission->timemodified);              
        }else{
            $submissiontime = '-';
        }
        
        $mform->addElement('html', '<b>Student\'s Submission </b> <br /> <span class="editingstatus">'.$submissiontime.'</span>');
        $mform->addElement('html', '</td>');        
        $mform->addElement('html', '<td valign="top" width="50%" align="right">'); 
        $mform->addElement('html', '<span class="editingstatus">Editing Status: <span class="editingstatus_msg">' . $editingstatus.'</span></span><br />');       
        $mform->addElement('html', '</td>'); 
        $mform->addElement('html', '</tr>'); 
        $mform->addElement('html', '</table>');         
        
        if ($tsubmission) {           
            foreach ($status->submissionplugins as $plugin) { 
                $pluginshowsummary = !$plugin->is_empty($submission) || !$plugin->allow_submissions();
                if ($plugin->is_enabled() &&
                    $plugin->is_visible() &&
                    $plugin->has_user_summary() &&
                    $pluginshowsummary) {
                        
                    
                    $pluginname = $plugin->get_name();
                    //$mform->addElement('html', $pluginname.'<br />');
                    
                    $submissionplugin = new assign_submission_plugin_submission($plugin,
                                                                                $tsubmission,
                                                                                assign_submission_plugin_submission::SUMMARY,
                                                                                $status->coursemoduleid,
                                                                                $status->returnaction,
                                                                                $status->returnparams);
                    
                    if ($plugin->get_name() == 'Online text'){                                                
                        $onlinetext = $DB->get_record('assignsubmission_onlinetext', array('submission'=>$submission->id));//print_r($onlinetext);   
                        
                        $mform->addElement('hidden', 'submissionid', $submission->id);
                        $mform->setType('action', PARAM_INT);

                                              
                        $options = array('cols'=>'82'
                                         //'subdirs'=>0,
                                         //'maxbytes'=>0,
                                         //'maxfiles'=>0,
                                         //'changeformat'=>0,
                                         //'context'=>null,
                                         //'noclean'=>0,
                                         //'trusttext'=>0
                                         );
                                                                         
                        $mform->addElement('editor', 'onlinetext');
                        $mform->setType('onlinetext', PARAM_RAW);
                        $mform->setDefault('onlinetext', array('text'=>$onlinetext->onlinetext,'format'=>FORMAT_HTML));

                    } else {
                        if ((! isset($params['savegrade'])) && ((! $params['readonly']) || ($plugin->get_name() != 'Submission comments'))){
                            $mform->addElement('html', '<div class="fn_plugin_wrapper">'.$pluginname.'<br />');
                            //$o = $plugin->view($submissionplugin->submission);                  
                            $o = $assignment->get_renderer()->render($submissionplugin);                  
                            $mform->addElement('html', $o.'</div>');
                        }
                    }
                                                             
                    
                    
                    
                    
                    
                    
                    
                    
                    //$//row->cells = array($cell1, $cell2);
                    //$t->data[] = $row;
                }
            }            

        }
        
        $mform->addElement('html', '</td>');
        $mform->addElement('html', '</tr>');
        ///close the table
        $mform->addElement('html', '</table>');
        //       
        
        
        

        $mform->addElement('hidden', 'courseid', $params['courseid']);
        $mform->setType('courseid', PARAM_INT); 


        $mform->addElement('hidden', 'show', $params['show']);
        $mform->setType('show', PARAM_RAW); 


        $mform->addElement('hidden', 'mid', $params['mid']);
        $mform->setType('mid', PARAM_INT); 

        
        $mform->addElement('hidden', 'dir', $params['dir']);
        $mform->setType('dir', PARAM_RAW); 

        
        $mform->addElement('hidden', 'timenow', $params['timenow']);
        $mform->setType('timenow', PARAM_INT); 

        $mform->addElement('hidden', 'sort', $params['sort']);
        $mform->setType('sort', PARAM_RAW); 

        $mform->addElement('hidden', 'view', $params['view']);
        $mform->setType('view', PARAM_RAW);
                                                      
        if ($params['resubmission']){                                                        
            $mform->addElement('hidden', 'submissionnum', $params['submissionnum']);
            $mform->setType('submissionnum', PARAM_INT);   
                                                  
            //$mform->addElement('hidden', 'maxsubmissionnum', $params['maxsubmissionnum']);
            //$mform->setType('maxsubmissionnum', PARAM_RAW); 
        }
        
        if ($data) {
            $this->set_data($data);
        }

        
    }

    /**
     * print the marking header section     
     * 
     */
    public function add_marking_header($user, $name, $blindmarking, $uniqueidforuser, $courseid, $viewfullnames, $rownum , $last, $groupname, $cm, $params) {
        global $CFG, $DB, $OUTPUT;
        
        
        $mform = & $this->_form;
        $mform->addElement('html', '<td width="40" valign="top" align="center" class="markingmanager-head marking_rightBRD">' . "\n");
        
        
        $o = '';
        if ($blindmarking) {
            $o .= get_string('hiddenuser', 'assign') . $uniqueidforuser;
        } else {
            $o .= $OUTPUT->user_picture($user);
            //$o .= $OUTPUT->spacer(array('width'=>30));
            //$o .= $OUTPUT->action_link(new moodle_url('/user/view.php', array('id' => $user->id, 'course'=>$courseid)), fullname($user, $viewfullnames));
        }
        $mform->addElement('html', $o);
        
        
        $mform->addElement('html', '</td>');

        $mform->addElement('html', '<td width="100%" valign="top" align="left" class="markingmanager-head">');

        $mform->addElement('html', '<table cellpadding="0" cellspacing="0" border="0" width="100%" class="name-date">');
        $mform->addElement('html', '<tr>');
        $mform->addElement('html', '<td valign="middle" width="65%" class="leftSide">');
        $mform->addElement('html', '<a target="_blank" class="marking_header_link" href="'.$CFG->wwwroot.'/user/view.php?id='.$user->id.'&course='.$courseid.'">' . fullname($user, true) . '</a>'. $groupname);
        $mform->addElement('html', '<br / >Assignment: <a target="_blank" class="marking_header_link" title="Assignment" href="'.$CFG->wwwroot.'/mod/assign/view.php?id='.$cm->id.'">' .$name.'</a>');
        $mform->addElement('html', '</td>');
        
        $mform->addElement('html', '<td width="35%" align="right" class="rightSide">');
        
        $buttonarray=array();
        if (isset( $params['readonly'])){
            if (! $params['readonly']){
                $buttonarray[] = $mform->createElement('submit', 'savegrade', get_string('savechanges', 'assign'));
            }
        }else{
            $buttonarray[] = $mform->createElement('submit', 'savegrade', get_string('savechanges', 'assign'));
        }
        /*
        if (!$last) {
            $buttonarray[] = $mform->createElement('submit', 'saveandshownext', get_string('savenext','assign'));
        }
        
        $buttonarray[] = $mform->createElement('cancel', 'cancelbutton', get_string('cancel'));
        
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        $mform->closeHeaderBefore('buttonar');
        
        $buttonarray=array();
        */
        if ($rownum > 0) {
            $buttonarray[] = $mform->createElement('submit', 'nosaveandprevious', get_string('previous','assign'));
        }

        if (!$last) {
            $buttonarray[] = $mform->createElement('submit', 'nosaveandnext', get_string('nosavebutnext', 'assign'));
        }
        if (!empty($buttonarray)) {
            $mform->addGroup($buttonarray, 'navar', '', array(' '), false);
        }
        $mform->addElement('html', '&nbsp;</td>');
        
        
        $mform->addElement('html', '</tr>');
        $mform->addElement('html', '</table>');

        $mform->addElement('html', '</td>');
    }   

}