<?php

function xmldb_assignment_github_upgrade($oldversion = 0) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 20120322) {

        // Define table assignment_github_logs to be created
        $table = new xmldb_table('assignment_github_logs');

        // Adding fields to table assignment_github_logs
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('assignment', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('groupid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('commit', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        $table->add_field('author', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('email', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('date', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('subject', XMLDB_TYPE_TEXT, 'small', null, null, null, null);
        $table->add_field('files', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, '0');
        $table->add_field('insertions', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, '0');
        $table->add_field('deletions', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, '0');

        // Adding keys to table assignment_github_logs
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table assignment_github_logs
        $table->add_index('github_log_assignment', XMLDB_INDEX_NOTUNIQUE, array('assignment'));
        $table->add_index('github_log_userid', XMLDB_INDEX_NOTUNIQUE, array('userid'));
        $table->add_index('github_log_groupid', XMLDB_INDEX_NOTUNIQUE, array('groupid'));
        $table->add_index('github_log_commit', XMLDB_INDEX_NOTUNIQUE, array('commit'));
        $table->add_index('github_log_email', XMLDB_INDEX_NOTUNIQUE, array('email'));

        // Conditionally launch create table for assignment_github_logs
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // github savepoint reached
        upgrade_plugin_savepoint(true, 20120322, 'assignment', 'github');
    }

    if ($oldversion < 20120529) {

        $table = new xmldb_table('assignment_github_repos');

        $field = new xmldb_field('members');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        $field = new xmldb_field('synced', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', 'updated');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 20120529, 'assignment', 'github');
    }

    if ($oldversion < 20120608) {

        $table = new xmldb_table('assignment_github_extra');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('assignment', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('type', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('data1', XMLDB_TYPE_TEXT, 'small', null, null, null, null);
        $table->add_field('data2', XMLDB_TYPE_TEXT, 'small', null, null, null, null);
        $table->add_field('data3', XMLDB_TYPE_TEXT, 'small', null, null, null, null);
        $table->add_field('data4', XMLDB_TYPE_TEXT, 'small', null, null, null, null);
        $table->add_field('data5', XMLDB_TYPE_TEXT, 'small', null, null, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        $table->add_index('assignment', XMLDB_INDEX_UNIQUE, array('assignment'));

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 20120608, 'assignment', 'github');
    }
    
}

