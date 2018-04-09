<?php
/**
 * DokuWiki Plugin struct (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Szymon Olewniczak <dokuwiki@cosmocode.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class helper_plugin_watchcycle extends DokuWiki_Plugin {
    /**
     * @param $time
     * @param $now
     *
     * @return int
     */
    public function daysAgo($time, $now=false) {
        if (!$now) $now = time();

        $diff = ($now - $time) / (60 * 60 * 24);
        return (int) $diff;
    }

    /**
     * Create HTML for an icon showing the maintenance status of the provided pageid
     *
     * @param string $pageid the full pageid
     *
     * @return string span with inline svg icon and classes
     */
    public function getSearchResultIconHTML($pageid) {
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

        $user = $watchcycle['maintainer'];
        $userData = $auth->getUserData($user);
        $title = sprintf($this->getLang('maintained by'), $userData['name']) . ' ';

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
}
