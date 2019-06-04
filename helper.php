<?php
/**
 * DokuWiki Plugin struct (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Szymon Olewniczak <dokuwiki@cosmocode.de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) {
    die();
}

class helper_plugin_watchcycle extends DokuWiki_Plugin
{
    const MAINTAINERS_RAW = 0;
    const MAINTAINERS_FLAT = 1;
    const MAINTAINERS_EXPANDED = 2;

    /**
     * Create HTML for an icon showing the maintenance status of the provided pageid
     *
     * @param string $pageid the full pageid
     *
     * @return string span with inline svg icon and classes
     */
    public function getSearchResultIconHTML($pageid)
    {
        /* @var \DokuWiki_Auth_Plugin $auth */
        global $auth;
        if ($auth === null) return '';

        /* @var \helper_plugin_watchcycle $helper */
        $helper = plugin_load('helper', 'watchcycle');
        $watchcycle = p_get_metadata($pageid, 'plugin watchcycle');
        if (!$watchcycle) {
            return '';
        }

        $days_ago = $helper->daysAgo($watchcycle['last_maintainer_rev']);

        $check_needed = false;
        if ($days_ago > $watchcycle['cycle']) {
            $check_needed = true;
        }

        $all = $this->getMaintainers($watchcycle['maintainer']);
        $title = $this->getLang('maintained by') . implode(', ', array_keys($all)) . ' ';

        if ($watchcycle['changes'] === -1) {
            $title .= $this->getLang('never checked');
        } else {
            $title .= sprintf($this->getLang('last check'), $days_ago);
        }

        $class = ['plugin__watchcycle_searchresult_icon'];
        if ($check_needed) {
            $class[] = 'check_needed';
            $title .= ' (' . $this->getLang('check needed') . ')';
        }
        $icon = '<span class="' . implode(' ', $class) . '" title="' . $title . '">';
        $icon .= inlineSVG(DOKU_PLUGIN . 'watchcycle/admin.svg');
        $icon .= '</span>';
        return $icon;
    }

    /**
     * @param $time
     * @param $now
     *
     * @return int
     */
    public function daysAgo($time, $now = false)
    {
        if (!$now) {
            $now = time();
        }

        $diff = ($now - $time) / (60 * 60 * 24);
        return (int)$diff;
    }


    /**
     * Returns true if the maintainer definition matches existing users and groups
     *
     * @param string $def
     * @return bool
     */
    public function validateMaintainerString($def)
    {
        /* @var DokuWiki_Auth_Plugin $auth */
        global $auth;
        if ($auth === null) return false; // no valid auth setup

        $all = explode(',', $def);
        foreach ($all as $item) {
            $item = trim($item);
            if (strpos($item, '@') !== false) {
                // check if group exists
                if (empty($auth->retrieveUsers(0, 1, array('grps' => ltrim($item, '@'))))) {
                    return false;
                }
            } else {
                if ($auth->getUserData($item) === false) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Returns a parsed representation of the maintainer string
     *
     * keys are the user and group names, value is either:
     *
     *  - the user data array
     *  - null for groups
     *  - false for unknown users
     *
     * @param string $def maintainer definition as given in the syntax
     * @return array
     */
    public function getMaintainers($def)
    {
        /* @var DokuWiki_Auth_Plugin $auth */
        global $auth;

        $found = [];
        if ($auth === null) return $found;

        $all = explode(',', $def);
        foreach ($all as $item) {
            $item = trim($item);
            if ($item[0] === '@') {
                $found[$item] = null; // no detail info on groups
            } else {
                $found[$item] = $auth->getUserData($item);
            }
        }

        return $found;
    }

    /**
     * @param string $def maintainer definition as given in the syntax
     * @return string[] list of email addresses to inform
     */
    public function getMaintainerMails($def)
    {
        /* @var DokuWiki_Auth_Plugin $auth */
        global $auth;
        if (!$auth) return [];

        $data = $this->getMaintainers($def);
        $mails = [];
        foreach ($data as $name => $info) {
            if (is_array($info)) {
                $mails[] = $info['mail'];
            } elseif ($name[0] === '@' && $auth->canDo('getUsers')) {
                $members = $auth->retrieveUsers(0, 0, array('grps' => ltrim($name, '@')));
                foreach ($members as $user) {
                    $mails[] = $user['mail'];
                }
            }
        }

        return array_values(array_unique($mails));
    }

    /**
     * @param string $user
     * @param string $def
     * @return bool
     */
    public function isMaintainer($user, $def)
    {
        /* @var DokuWiki_Auth_Plugin $auth */
        global $auth;
        if ($auth === null) return false;
        if ($user === '') return false;
        $userData = $auth->getUserData($user);

        $all = explode(',', $def);
        foreach ($all as $item) {
            $item = trim($item);
            if (strpos($item, '@') !== false && in_array(ltrim($item, '@'), $userData['grps'])) {
                return true;
            } elseif ($item === $user) {
                return true;
            }
        }

        return false;
    }

    /**
     * Puts users and groups into a flat array; useful for simple string output
     * @param array $all
     * @return array
     */
    protected function flattenMaintainers($all)
    {
        if (empty($all['users'])) {
            return $all;
        }

        $users = array_map(function ($user) {
            return $user['name'];
        }, $all['users']);

        return array_merge($users, $all['groups']);
    }

    /**
     * Expands groups into users; useful for email notification
     *
     * @param array $all
     * @return array
     */
    protected function expandMaintainers($all)
    {
        if (empty($all['groups'])) {
            return $all;
        }

        /* @var DokuWiki_Auth_Plugin $auth */
        global $auth;
        if ($auth === null) return [];

        $members = array();
        foreach ($all['groups'] as $group) {
            $members = array_merge($members, $auth->retrieveUsers(0, 0, array('grps' => ltrim($group, '@'))));
        }

        // merge eliminates any duplicates since we use string keys
        return array_merge($all['users'], $members);
    }
}
