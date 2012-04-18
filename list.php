<?php

require("../../../../config.php");
require("assignment.class.php");

$item_map = array('name', 'project', 'date');
$order_map = array('asc' => 'up', 'desc' => 'down');

$id = required_param('id', PARAM_INT);      // Course Module ID
$sortitem = optional_param('sortitem', 'name', PARAM_ALPHAEXT);
$sortorder = optional_param('sortorder', 'asc', PARAM_ALPHAEXT);
if (!in_array($sortitem, $item_map)) {
    $sortitem = 'name';
}
if (empty($order_map[$sortorder])) {
    $sortorder = $order_map['asc'];
} else {
    $sortorder = $order_map[$sortorder];
}

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
$logger = new git_logger($assignment->id);
$commits = $logger->list_all_latest_commits($groupmode);

$rows = $name_list = $project_list = $date_list = array();

if ($groupmode) {
    $groups = groups_get_all_groups($cm->course, 0, $cm->groupingid);
    foreach($groups as $group) {
        $new_row = new stdClass();
        $new_row->name = $group->name;
        $new_row->project = new stdClass();
        if (array_key_exists($group->id, $repos)) {
            $repo = $repos[$group->id];
            $service =& $git->get_api_service($repo->server);
            $urls = $service->generate_http_from_git($repo->url);
            $new_row->project->name = $repo->repo;
            $new_row->project->url = $urls['repo'];
        } else {
            $new_row->project->name = get_string('repohasnotset', 'assignment_github');
            $new_row->project->url = '';
        }
        $members = $assignmentinstance->get_members_by_id($group->id);
        $new_row->members = $assignmentinstance->print_member_list($members, true);
        if (empty($commits[$group->id])) {
            $new_row->date = 0;
        } else {
            $new_row->date = $commits[$group->id]->date;
        }

        $rows[$group->id] = $new_row;
        $name_list[$group->id] = $new_row->name;
        $project_list[$group->id] = $new_row->project->name;
        $date_list[$group->id] = $new_row->date;
    }
} else {
}

// sort
$final_rows = array();
switch($sortitem) {
case 'name':
    $list = $name_list;
    natsort($list);
    if ($sortorder == 'up') {
    } else if ($sortorder == 'down') {
        $list = array_reverse($list, true);
    }
    foreach(array_keys($list) as $k) {
        $final_rows[$k] = $rows[$k];
    }
    break;
case 'project':
    $list = $project_list;
    natsort($list);
    if ($sortorder == 'up') {
    } else if ($sortorder == 'down') {
        $list = array_reverse($list, true);
    }
    foreach(array_keys($list) as $k) {
        $final_rows[$k] = $rows[$k];
    }
    break;
case 'date':
    if ($sortorder == 'up') {
        asort($date_list);
    } else if ($sortorder == 'down') {
        arsort($date_list);
    }
    foreach(array_keys($date_list) as $k) {
        $final_rows[$k] = $rows[$k];
    }
    break;
default:
    break;
}

if ($groupmode) {

    // table header
    $row = new html_table_row();
    $c1 = new html_table_cell();
    $c2 = new html_table_cell();
    $c3 = new html_table_cell();
    $c4 = new html_table_cell();

    $titles = array(
        'name' => get_string('group'),
        'project' => get_string('project', 'assignment_github'),
        'date' => get_string('lastmodified'),
    );
    $titles[$sortitem] .= print_arrow($sortorder, null, true);

    foreach($titles as $key => $title) {
        if ($key == $sortitem) {
            $sortorder = $sortorder == 'up' ? 'desc' : 'asc';
            $link = new moodle_url("/mod/assignment/type/github/list.php?id={$cm->id}&sortitem={$key}&sortorder={$sortorder}");
            $titles[$key] = html_writer::link($link, $title);
        } else {
            $link = new moodle_url("/mod/assignment/type/github/list.php?id={$cm->id}&sortitem={$key}&sortorder=asc");
            $titles[$key] = html_writer::link($link, $title);
        }
    }

    $c1->text = $titles['name'];
    $c2->text = $titles['project'];
    $c3->text = get_string('memberlist', 'assignment_github');
    $c4->text = $titles['date'];
    $c1->header = $c2->header = $c3->header = $c4->header = true;
    $row->cells = array($c1, $c2, $c3, $c4);
    $table->data[] = $row;

    // table content
    $groups = groups_get_all_groups($cm->course, 0, $cm->groupingid);
    foreach($final_rows as $groupid => $group) {
        $row = new html_table_row();
        $c1 = new html_table_cell();
        $c2 = new html_table_cell();
        $c3 = new html_table_cell();
        $c4 = new html_table_cell();

        if ($groupmode == VISIBLEGROUPS || $can_grade) {
            $link = new moodle_url("/mod/assignment/view.php?id={$cm->id}&group={$groupid}");
            $c1->text = html_writer::link($link, $group->name);
        } else {
            $c1->text = $group->name;
        }

        if ($row->project->url) {
            $c2->text = html_writer::link($group->project->url, $group->project->name, array('target' => '_blank'));
        } else {
            $c2->text = $group->project->name;
        }
        $c3->text = $group->members;

        // last commit date
        if (!$group->date) {
            $c4->text = '';
        } else {
            $c4->text = userdate($group->date);
        }

        $row->cells = array($c1, $c2, $c3, $c4);
        $table->data[] = $row;
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
echo $OUTPUT->box_start('boxaligncenter git_list');
echo html_writer::table($table);
echo $OUTPUT->box_end();
echo $OUTPUT->box_end();
echo $OUTPUT->footer();

// Revert group
$SESSION->activegroup[$cm->course][$groupmode][$cm->groupingid] = $temp_group;
