<?php
/**
 * English language file for watchcycle plugin
 *
 * @author Szymon Olewniczak <dokuwiki@cosmocode.de>
 */

// menu entry for admin plugins
$lang['menu'] = 'Watchcycle Managment';

// custom language strings for the plugin
$lang['maintained by'] = 'Maintained by: %s';
$lang['last check'] = 'Last check %d days ago.';
$lang['check needed'] = 'Check needed!';
$lang['since last check'] = '%s since last check.';
$lang['never checked'] = 'Never checked.';

$lang['change singular'] = 'change';
$lang['change plural'] = 'changes';

$lang['cb only maintained pages'] = 'Only maintained pages';

// admin
$lang['search page'] = 'search page';
$lang['btn filter'] = 'Filter';
$lang['show outdated only'] = 'show outdated only';
$lang['h page'] = 'page';
$lang['h maintainer'] = 'maintainer';
$lang['h cycle'] = 'cycle';
$lang['h current'] = 'current';
$lang['h uptodate'] = 'up-to-date?';

// mail
$lang['mail subject'] = 'Page maintenance needed';
$lang['mail body'] = '%s has exceeded maintenance cycle and needs checking.';

// error
$lang['error mail'] = 'Cannot send mail to maintainer.';
$lang['error sqlite missing'] = 'The watchcycle plugin requires the <a href="https://www.dokuwiki.org/plugin:sqlite">sqlite plugin</a> to work.';
$lang['error invalid maintainers'] = 'watchcycle: maintainer must be a Dokuwiki user or an existing group';

$lang['title toolbar button'] = 'Add new maintenance syntax';

$lang['js']['label_username'] = 'username';
$lang['js']['label_cycle_length'] = 'cycle length';
$lang['js']['button_insert'] = 'Insert';
$lang['js']['button_cancel'] = 'Cancel';
$lang['js']['invalid_maintainers'] = 'Invalid maintainers!';

//Setup VIM: ex: et ts=4 :
