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

$PAGE->set_url('/mod/assignment/type/github/log.php', array('id'=>$id));
$PAGE->set_title(get_string('statistics', 'assignment_github'));
$PAGE->set_heading($course->fullname);
$PAGE->set_context($context);
$PAGE->set_cm($cm);
$PAGE->requires->css('/mod/assignment/type/'.$assignment->assignmenttype.'/styles.css');

$groupmode = groups_get_activity_groupmode($cm);
if ($groupmode) {
    $aag = has_capability('moodle/site:accessallgroups', $context);

    if ($groupmode == VISIBLEGROUPS or $aag) {
        $allowedgroups = groups_get_all_groups($cm->course, 0, $cm->groupingid);
    } else {
        $allowedgroups = groups_get_all_groups($cm->course, $USER->id, $cm->groupingid);
    }

    $groupid = groups_get_activity_group($cm, true, $allowedgroups);

    // Group 0 (all groups) is not allowed to use
    // change groupid to the first allowed group's id
    if (!$groupid) {
        $group = array_shift($allowedgroups);
        $groupid = $group->id;
        $allowedgroups[$groupid] = $group;
    }
    $name = $allowedgroups[$groupid]->name;
} else {
    $name = fullname($USER);
}

$assignmentinstance = new assignment_github($cm->id, $assignment, $cm, $course);
$git = new git($course->id, $assignment->id);
$logger = new git_logger($assignment->id);

// Load emails, repo, logs and statistics infomation
if ($groupmode) {
    $members = groups_get_members($groupid, 'u.*', 'lastname ASC');
    $emails = array();
    foreach($members as $userid => $member) {
        $submission = $assignmentinstance->get_submission($userid);
        if (!empty($submission->data1)) {
            $emails[$submission->data1] = $userid;
        }
    }
    $repo = $git->get_by_group($groupid);
    $statistics = $logger->get_statistics_by_group($groupid);
    $logs = $logger->get_by_group($groupid, '', '', 0, 10);
} else {
    $submission = $assignmentinstance->get_submission($USER->id);
    $emails = array();
    if (!empty($submission->data1)) {
        $emails[$submission->data1] = $USER->id;
    }
    $repo = $git->get_by_user($USER->id);
    $statistics = $logger->get_statistics_by_user($USER->id);
    $logs = $logger->get_by_user($USER->id, '', '', 0, 10);
}

echo $OUTPUT->header();
groups_print_activity_menu($cm, $CFG->wwwroot . '/mod/assignment/type/github/log.php?id=' . $cm->id, false, true);
echo $OUTPUT->box_start('generalbox boxaligncenter', 'intro');
echo html_writer::tag('h3', get_string('statisticsdata', 'assignment_github', $name), array('class' => 'git_h3'));
echo $OUTPUT->box_start('generalbox boxaligncenter git_log');

$url = new moodle_url("/mod/assignment/view.php?id={$cm->id}");
$link = html_writer::link($url, get_string('githubreposetting', 'assignment_github'));
echo '<div class="git_checklink reportlink">'.$link.'</div>';
echo '<div class="clearer"></div>';

if ($repo) {
    $service =& $git->get_api_service($repo->server);
    $service->print_nav_menu($repo->url);
    
    // Statistics
    echo html_writer::tag('h4', get_string('statistics', 'assignment_github'));
    $statistics_title = array('Author', 'Commits', 'Files', '+', '-', 'Total');
    $statistics_table = html_writer::start_tag('table', array('class' => 'generaltable'));
    $statistics_table .= '<tr>';
    foreach($statistics_title as $title) {
        $statistics_table .= '<th class="cell">' . $title . '</th>';
    } 
    $statistics_table .= '</tr>';
    if ($statistics) {
        $total = array_pop($statistics);
        if (!$statistics) {
            array_push($statistics, $total);
        }
    
        foreach($statistics as $line) {
            $statistics_table .= '<tr>';
            if (empty($emails[$line->email])) {
                $author = $line->author;
            } else {
                $author = fullname($members[$emails[$line->email]]);
            }
            $statistics_table .= '<td class="cell">'.$author.'</td>';
            $statistics_table .= '<td class="cell">'.$line->commits.' ('.round($line->commits/$total->commits * 100, 2).'%)</td>';
            $statistics_table .= '<td class="cell">'.$line->files.' ('.round($line->files/$total->files * 100, 2).'%)</td>';
            $statistics_table .= '<td class="cell green">'.$line->insertions.' ('.round($line->insertions/$total->insertions * 100, 2).'%)</td>';
            $statistics_table .= '<td class="cell red">'.$line->deletions.' ('.round($line->deletions/$total->deletions * 100, 2).'%)</td>';
            $statistics_table .= '<td class="cell">'.$line->total.' ('.round($line->total/$total->total * 100, 2).'%)</td>';
            $statistics_table .= '</tr>';
        }
    
        $statistics_table .= '<tr>';
        $statistics_table .= '<td class="cell">'.get_string('total').'</td>';
        $statistics_table .= '<td class="cell">'.$total->commits.'</td>';
        $statistics_table .= '<td class="cell">'.$total->files.'</td>';
        $statistics_table .= '<td class="cell green">'.$total->insertions.'</td>';
        $statistics_table .= '<td class="cell red">'.$total->deletions.'</td>';
        $statistics_table .= '<td class="cell">'.$total->total.'</td>';
        $statistics_table .= '</tr>';
    }
    echo $statistics_table .= html_writer::end_tag('table');
    
    // Log
    echo html_writer::tag('h4', get_string('latestcommits', 'assignment_github'));
    $log_title = array('Commit', 'Author', 'Subject', 'Files', '+', '-', 'Date');
    $log_table = html_writer::start_tag('table', array('class' => 'generaltable'));
    $log_table .= '<tr>';
    foreach($log_title as $title) {
        $log_table .= '<th class="cell">' . $title . '</th>';
    } 
    $log_table .= '</tr>';
    if ($logs) {
        foreach($logs as $commit => $log) {
            $log_table .= '<tr>';
            $commit_link = html_writer::link($service->generate_commit_url($repo->url, $log->commit),
                                             shorten_text($log->commit, 11), array('target' => '_blank'));
            $log_table .= '<td class="cell">'.$commit_link.'</td>';
    
            if (empty($emails[$log->email])) {
                $author = $log->author;
            } else {
                $author = fullname($members[$emails[$log->email]]);
            }
            $log_table .= '<td class="cell">'.$author.'</td>';
    
            $log_table .= '<td class="cell subject">'.$log->subject.'</td>';
            $log_table .= '<td class="cell">'.$log->files.'</td>';
            $log_table .= '<td class="cell green">'.$log->insertions.'</td>';
            $log_table .= '<td class="cell red">'.$log->deletions.'</td>';
            $log_table .= '<td class="cell">'.userdate($log->date).'</td>';
            $log_table .= '</tr>';
        }
    }
    echo $log_table .= html_writer::end_tag('table');
} else {
    echo $OUTPUT->notification(get_string('repohasnotset', 'assignment_github'));
}

echo $OUTPUT->box_end();
echo $OUTPUT->box_end();
echo $OUTPUT->footer();
