<?php

require("../../../../config.php");
require("assignment.class.php");

$id = required_param('id', PARAM_INT);      // Course Module ID
$code = required_param('code', PARAM_ALPHANUMEXT);

if (! $cm = get_coursemodule_from_id('assignment', $id)) {
    print_error('invalidcoursemodule');
}

if (! $assignment = $DB->get_record("assignment", array("id"=>$cm->instance))) {
    print_error('invalidid', 'assignment');
}

if (! $course = $DB->get_record("course", array("id"=>$assignment->course))) {
    print_error('coursemisconf', 'assignment');
}

require_login($course->id, false, $cm);

$context = get_context_instance(CONTEXT_MODULE, $cm->id);
if (!has_capability('mod/assignment:view', $context)) {
    print_error('cannotviewassignment', 'assignment');
}

if (!has_capability('mod/assignment:grade', $context)) {
    print_error('cannotviewassignment', 'assignment');
}

if ($assignment->assignmenttype != 'github') {
    print_error('invalidtype', 'assignment');
}

$PAGE->set_url('/mod/assignment/type/github/oauth.php', array('id'=>$id));
$PAGE->set_title(get_string('githubreposettinglist', 'assignment_github'));
$PAGE->set_heading($course->fullname);
$PAGE->set_context($context);
$PAGE->set_cm($cm);
$PAGE->requires->css('/mod/assignment/type/'.$assignment->assignmenttype.'/styles.css');

$assignmentinstance = new assignment_github($cm->id, $assignment, $cm, $course);
$extra = $assignmentinstance->get_extra_settings();
if (!empty($extra)) {
    $server = $ASSIGNMENT_GITHUB->code[$extra->type];
    $git = new git($course->id, $assignment->id);
    $service = $git->get_api_service($server);

    $access_token = $service->exchange_access_token($code);
    if (!empty($access_token)) {
        $extra->data2 = $access_token;
        $DB->update_record('assignment_github_extra', $extra);
    }
}

$url = new moodle_url("/mod/assignment/view.php?id={$cm->id}");
redirect($url);
