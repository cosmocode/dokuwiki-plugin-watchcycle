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

}