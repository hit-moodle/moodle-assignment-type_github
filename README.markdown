# Assignment Type Github

An assignment type integrate git project hosting site(for example, Github, Google Code, etc.) which provides source code version controll functions into Moodle so that teachers can easily check and manage students' code through Moodle. To create a package of project management, tracking and grading solution.

## Download And Installation

You have to install [Git](http://git-scm.com/download) and [Moodle](https://github.com/hit-moodle/moodle) first and make them work.

### Download Method 1 (Recommanded)

Change directory to **&lt;moodleroot&gt;/mod/assignment/type** , use git to clone this project. After that, you can use `git pull` command to keep up to date.

```
git clone git://github.com/hit-moodle/moodle-assignment-type_github.git github
```

### Download Method 2

Download the zip package on [moodle-assignment-type_github](https://github.com/hit-moodle/moodle-assignment-type_github). Unpack the package, named the source code directory **github** and move it to **&lt;moodleroot&gt;/mod/assignment/type**.

### Installation

Login to Moodle as web site administrator after you finish downloading. Moodle will leads you to the notification page, telling you it find a new plugin. Just click the install button.

**For clean installation of Moodle 2.3 or 2.4 (rather than an upgraded site):**

you need to enable the 2.2 assignment type in administration under:

```
 Site admin > Plugins > Activity modules > Manage activities
```

## Settings

### Sync Script

Students' projects are downloaded by a php script: *cli/sync_repos.php* . You must run this script as a web server user.

```
# This will download all the repos
sudo -u _www php cli/sync_repos.php
```

Use argument *cm(Course Module ID)* to download the repos in specified assignments.

```
# Download repos of assignment which its course module id is 1024
sudo -u _www php cli/sync_repos.php --cm=1024
# Download repos of assignments which thire course module id are 256 and 1024
sudo -u _www php cli/sync_repos.php --cm=256 --cm=1024
```

### Cron

There is no need to run the script by administrator manually. Making a cron job instead is a good idea. The sync script will place students' projects in directory **&lt;moodledata&gt;/github**. In Linux/Unix, you have to edit crontab as web server user to make sure your site have the right permission to operate students' projects. Run crontab, assuming the User of Apache is **_www** (You can change it in httpd.conf):

```
sudo crontab -u _www -e
```

The following line specifies that sync_repos.php will run at 5:00 every day.

```
# MOODLEROOT: absolute path of Moodle
* 5 * * * php MOODLEROOT/mod/assignment/type/github/cli/sync_repos.php
```
