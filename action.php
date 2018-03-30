<?php
/**
 * DokuWiki Plugin watchcycle (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Szymon Olewniczak <dokuwiki@cosmocode.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class action_plugin_watchcycle extends DokuWiki_Action_Plugin {

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(Doku_Event_Handler $controller) {

       $controller->register_hook('PARSER_METADATA_RENDER', 'AFTER', $this, 'handle_parser_metadata_render');
   
    }

    /**
     * [Custom event handler which performs action]
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return void
     */

    public function handle_parser_metadata_render(Doku_Event &$event, $param) {
        /** @var \helper_plugin_sqlite $sqlite */
        $sqlite = plugin_load('helper', 'watchcycle_db')->getDB();

        $pageid = $event->data['current']['last_change']['id'];

        if(isset($event->data['current']['plugin']['watchcycle'])) {
            $watchcycle = $event->data['current']['plugin']['watchcycle'];
            $res = $sqlite->query('SELECT * FROM watchcycle WHERE pageid=?', $pageid);
            $row = $sqlite->res2row($res);
            $changes = $this->getLastMaintainerRev($event->data, $watchcycle['maintainer'], $last_maintainer_rev);
            if ($row === null) {
                $entry = $watchcycle;
                $entry['pageid'] = $pageid;
                $entry['last_maintainer_rev'] = $last_maintainer_rev;

                $sqlite->storeEntry('watchcycle', $entry);
            } else { //check if we need to update something
                $toupdate = array();

                if ($row['cycle'] != $watchcycle['cycle']) {
                    $toupdate['cycle'] = $watchcycle['cycle'];
                }

                if ($row['maintainer'] != $watchcycle['maintainer']) {
                    $toupdate['maintainer'] = $watchcycle['maintainer'];
                    $toupdate['last_maintainer_rev'] = $last_maintainer_rev;
                }

                if (count($toupdate) > 0) {
                    $set = implode(',', array_map(function($v) {
                        return "$v=?";
                    }, array_keys($toupdate)));
                    $toupdate[] = $pageid;
                    $sqlite->query("UPDATE watchcycle SET $set WHERE pageid=?", $toupdate);
                }
            }
            $event->data['current']['plugin']['watchcycle']['last_maintainer_rev'] = $last_maintainer_rev;
            $event->data['current']['plugin']['watchcycle']['changes'] = $changes;
        } else { //maybe we've removed the syntax -> delete from the database
            $sqlite->query('DELETE FROM watchcycle WHERE pageid=?', $pageid);
        }
    }

    /**
     * @param array  $meta metadata of the page
     * @param string $maintanier
     * @param int    $rev revision of the last page edition by maintainer or create date if no edition was made
     * @return int   number of changes since last maintainer's revision
     */
    protected function getLastMaintainerRev($meta, $maintanier, &$rev) {

        $changes = 0;
        if ($meta['current']['last_change']['user'] == $maintanier) {
            $rev = $meta['current']['last_change']['date'];
            return $changes;
        } else {
            $pageid = $meta['current']['last_change']['id'];
            $changelog = new PageChangeLog($pageid);
            $first = 0;
            $num = 100;
            while (count($revs = $changelog->getRevisions($first, $num)) > 0) {
                foreach ($revs as $rev) {
                    $changes += 1;
                    $revInfo = $changelog->getRevisionInfo($rev);
                    if ($revInfo['user'] == $maintanier) {
                        $rev = $revInfo['date'];
                        return $changes;
                    }
                }
                $first += $num;
            }
        }

        $rev = $meta['current']['date']['created'];
        return -1;
    }

}

// vim:ts=4:sw=4:et:
