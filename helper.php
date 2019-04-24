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

        $all = $this->getMaintainers($watchcycle['maintainer'], self::MAINTAINERS_FLAT);
        $title = $this->getLang('maintained by') . implode(', ', $all). ' ';

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

        $all = explode(',', $def);
        foreach ($all as $item) {
            $item = trim($item);
            if (strpos($item, '@') !== false) {
                // check if group exists
                if (empty($auth->retrieveUsers(0,1, array('grps' => ltrim($item, '@'))))) {
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
     * Returns an array of users and groups as specified in the maintainer string
     *
     * @param string $def
     * @param int $format
     * @return array
     */
    public function getMaintainers($def, $format = self::MAINTAINERS_RAW)
    {
        /* @var DokuWiki_Auth_Plugin $auth */
        global $auth;

        $found = array('users' => array(), 'groups' => array());

        $all = explode(',', $def);
        foreach ($all as $item) {
            $item = trim($item);
            if (strpos($item, '@') !== false) {
                $found['groups'][] = $item;
            } else {
                $found['users'][] = $auth->getUserData($item);
            }
        }

        switch ($format) {
            case self::MAINTAINERS_FLAT:
                return $this->flattenMaintainers($found);
        }

        return $found;
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

        $users = array_map(function($user) {
            return $user['name'];
        }, $all['users']);

        return array_merge($users, $all['groups']);
    }
}
