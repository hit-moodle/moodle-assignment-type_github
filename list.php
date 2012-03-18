<?php

require("../../../../config.php");
require("assignment.class.php");

$id = required_param('id', PARAM_INT);      // Course Module ID

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

if ($assignment->assignmenttype != 'github') {
    print_error('invalidtype', 'assignment');
}

$PAGE->set_url('/mod/assignment/type/github/list.php', array('id'=>$id));
$PAGE->set_title(get_string('githubreposettinglist', 'assignment_github'));
$PAGE->set_heading($course->fullname);
$PAGE->set_context($context);
$PAGE->set_cm($cm);
$PAGE->requires->css('/mod/assignment/type/'.$assignment->assignmenttype.'/styles.css');

// Temporarily clear group
$groupmode = groups_get_activity_groupmode($cm);
$temp_group = $SESSION->activegroup[$cm->course][$groupmode][$cm->groupingid];
$SESSION->activegroup[$cm->course][$groupmode][$cm->groupingid] = 0;

$assignmentinstance = new assignment_github($cm->id, $assignment, $cm, $course);
$can_grade = has_capability('mod/assignment:grade', $context);
$git = new git($course->id, $assignment->id);

$repos = $assignmentinstance->list_all();
$repo_count = count($repos);
$table = new html_table();
if ($groupmode) {
    $groups = groups_get_all_groups($cm->course, 0, $cm->groupingid);
    $group_names = array();
    $rows = array();
    foreach($groups as $group) {
        $row = new html_table_row();
        $c1 = new html_table_cell();
        $c2 = new html_table_cell();

        if ($groupmode == VISIBLEGROUPS || $can_grade) {
            $link = new moodle_url("/mod/assignment/view.php?id={$cm->id}&group={$group->id}");
            $c1->text = html_writer::link($link, $group->name);
        } else {
            $c1->text = $group->name;
        }
        $group_names[$group->id] = $group->name;
        if (array_key_exists($group->id, $repos)) {
            $repo = $repos[$group->id];
            $service =& $git->get_api_service($repo->server);
            $urls = $service->generate_http_from_git($repo->url);
            $c2->text = html_writer::link($urls['repo'], $repo->repo, array('target' => '_blank'));
        } else {
            $c2->text = get_string('repohasnotset', 'assignment_github');
        }
        $row->cells = array($c1, $c2);
        $rows[$group->id] = $row;
    }
    natsort($group_names);
    foreach($group_names as $group_id => $group_name) {
        if (array_key_exists($group_id, $rows)) {
            $table->data[] = $rows[$group_id];
        }
    }
    $total = count($groups);
} else {
    //TODO: no group mode
}

echo $OUTPUT->header();
if ($can_grade) {
    echo '<div class="reportlink">'.$assignmentinstance->submittedlink().'</div>';
    echo '<div class="clearer"></div>';
}
echo $OUTPUT->box_start('generalbox boxaligncenter', 'intro');
echo html_writer::tag('h3', get_string('githubreposettinglist', 'assignment_github'), array('class' => 'git_h3'));
echo '<div class="git_right">'.get_string('repohasset', 'assignment_github', $repo_count.' / '.$total).'</div>';
echo '<div class="clearer"></div>';
echo $OUTPUT->box_start('generalbox boxaligncenter git_box');
echo html_writer::table($table);
echo $OUTPUT->box_end();
echo $OUTPUT->box_end();
echo $OUTPUT->footer();

// Revert group
$SESSION->activegroup[$cm->course][$groupmode][$cm->groupingid] = $temp_group;
