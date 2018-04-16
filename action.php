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
       $controller->register_hook('PARSER_CACHE_USE', 'AFTER', $this, 'handle_parser_cache_use');
       // ensure a page revision is created when summary changes:
       $controller->register_hook('COMMON_WIKIPAGE_SAVE', 'BEFORE', $this, 'handle_pagesave_before');
       $controller->register_hook('SEARCH_RESULT_PAGELOOKUP', 'BEFORE', $this, 'addIconToPageLookupResult');
       $controller->register_hook('SEARCH_RESULT_FULLPAGE', 'BEFORE', $this, 'addIconToFullPageResult');
       $controller->register_hook('FORM_SEARCH_OUTPUT', 'BEFORE', $this, 'addFilterToSearchForm');
       $controller->register_hook('SEARCH_QUERY_FULLPAGE', 'AFTER', $this, 'filterSearchResults');
       $controller->register_hook('SEARCH_QUERY_PAGELOOKUP', 'AFTER', $this, 'filterSearchResults');
    }

    /**
     * Add a checkbox to the search form to allow limiting the search to maintained pages only
     *
     * @param Doku_Event $event
     * @param            $param
     */
    public function addFilterToSearchForm(Doku_Event $event, $param)
    {
        /* @var \dokuwiki\Form\Form $searchForm */
        $searchForm = $event->data;
        $advOptionsPos = $searchForm->findPositionByAttribute('class', 'advancedOptions');
        $searchForm->addCheckbox('watchcycle_only', $this->getLang('cb only maintained pages'), $advOptionsPos + 1)
        ->addClass('plugin__watchcycle_searchform_cb');
    }

    /**
     * Filter the search results to show only maintained pages, if  watchcycle_only is true in $INPUT
     *
     * @param Doku_Event $event
     * @param            $param
     */
    public function filterSearchResults(Doku_Event $event, $param)
    {
        global $INPUT;
        if (!$INPUT->bool('watchcycle_only')) {
            return;
        }
        $event->result = array_filter($event->result, function ($key) {
            $watchcycle = p_get_metadata($key, 'plugin watchcycle');
            return !empty($watchcycle);
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * [Custom event handler which performs action]
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return void
     */
    public function handle_parser_metadata_render(Doku_Event $event, $param) {
        global $ID;

        /** @var \helper_plugin_sqlite $sqlite */
        $sqlite = plugin_load('helper', 'watchcycle_db')->getDB();
        if (!$sqlite) {
            msg($this->getLang('error sqlite missing'), -1);
            return;
        }
        /* @var \helper_plugin_watchcycle $helper */
        $helper = plugin_load('helper', 'watchcycle');

        $page = $event->data['current']['last_change']['id'];

        if(isset($event->data['current']['plugin']['watchcycle'])) {
            $watchcycle = $event->data['current']['plugin']['watchcycle'];
            $res = $sqlite->query('SELECT * FROM watchcycle WHERE page=?', $page);
            $row = $sqlite->res2row($res);
            $changes = $this->getLastMaintainerRev($event->data, $watchcycle['maintainer'], $last_maintainer_rev);
            //false if page needs checking
            $uptodate = $helper->daysAgo($last_maintainer_rev) <= $watchcycle['cycle'] ? '1' : '0';
            if (!$row) {
                $entry = $watchcycle;
                $entry['page'] = $page;
                $entry['last_maintainer_rev'] = $last_maintainer_rev;
                $entry['uptodate'] = $uptodate;
                if ($uptodate == '0') {
                    $this->informMaintainer($watchcycle['maintainer'], $ID);
                }

                $sqlite->storeEntry('watchcycle', $entry);
            } else { //check if we need to update something
                $toupdate = array();

                if ($row['cycle'] != $watchcycle['cycle']) {
                    $toupdate['cycle'] = $watchcycle['cycle'];
                }

                if ($row['maintainer'] != $watchcycle['maintainer']) {
                    $toupdate['maintainer'] = $watchcycle['maintainer'];
                }

                if ($row['last_maintainer_rev'] != $last_maintainer_rev) {
                    $toupdate['last_maintainer_rev'] = $last_maintainer_rev;
                }

                //uptodate value has chaned
                if ($row['uptodate'] != $uptodate) {
                    $toupdate['uptodate'] = $uptodate;
                    if (!$uptodate) {
                        $this->informMaintainer($watchcycle['maintainer'], $ID);
                    }
                }

                if (count($toupdate) > 0) {
                    $set = implode(',', array_map(function($v) {
                        return "$v=?";
                    }, array_keys($toupdate)));
                    $toupdate[] = $page;
                    $sqlite->query("UPDATE watchcycle SET $set WHERE page=?", $toupdate);
                }
            }
            $event->data['current']['plugin']['watchcycle']['last_maintainer_rev'] = $last_maintainer_rev;
            $event->data['current']['plugin']['watchcycle']['changes'] = $changes;
        } else { //maybe we've removed the syntax -> delete from the database
            $sqlite->query('DELETE FROM watchcycle WHERE page=?', $page);
        }
    }

    /**
     * @param array  $meta metadata of the page
     * @param string $maintanier
     * @param int    $rev revision of the last page edition by maintainer or -1 if no edition was made
     * @return int   number of changes since last maintainer's revision or -1 if no changes was made
     */
    protected function getLastMaintainerRev($meta, $maintanier, &$rev) {

        $changes = 0;
        if ($meta['current']['last_change']['user'] == $maintanier) {
            $rev = $meta['current']['last_change']['date'];
            return $changes;
        } else {
            $page = $meta['current']['last_change']['id'];
            $changelog = new PageChangeLog($page);
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

        $rev = -1;
        return -1;
    }

    /**
     * inform the maintanier that the page needs checking
     *
     * @param string $user name of the maintanier
     * @param string $page that needs checking
     */
    protected function informMaintainer($user, $page) {
        /* @var DokuWiki_Auth_Plugin */
        global $auth;

        $data = $auth->getUserData($user);

        $mailer = new Mailer();
        $mailer->to($data['mail']);
        $mailer->subject($this->getLang('mail subject'));
        $text = sprintf($this->getLang('mail body'), $page);
        $link = '<a href="' . wl($page, '', true) . '">' . $page . '</a>';
        $html = sprintf($this->getLang('mail body'), $link);
        $mailer->setBody($text, null, null, $html);

        if (!$mailer->send()) {
            msg($this->getLang('error mail'), -1);
        }
    }

    /**
     * clean the cache every 24 hours
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return void
     */
    public function handle_parser_cache_use(Doku_Event $event, $param) {
        /* @var \helper_plugin_watchcycle $helper*/
        $helper = plugin_load('helper', 'watchcycle');

        if ($helper->daysAgo($event->data->_time) >= 1) {
            $event->result = false;
        }
    }

    /**
     * Check if the page has to be changed
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return void
     */
    public function handle_pagesave_before(Doku_Event $event, $param) {
        if($event->data['contentChanged']) return; // will be saved for page changes
        global $ACT;

        //save page if summary is provided
        if(!empty($event->data['summary'])) {
            $event->data['contentChanged'] = true;
        }
    }

    /**
     * called for event SEARCH_RESULT_PAGELOOKUP
     *
     * @param Doku_Event $event
     * @param            $param
     */
    public function addIconToPageLookupResult(Doku_Event $event, $param)
    {
        /* @var \helper_plugin_watchcycle $helper*/
        $helper = plugin_load('helper', 'watchcycle');

        $icon = $helper->getSearchResultIconHTML($event->data['page']);
        if ($icon) {
            $event->data['listItemContent'][] = $icon;
        }
    }

    /**
     * called for event SEARCH_RESULT_FULLPAGE
     *
     * @param Doku_Event $event
     * @param            $param
     */
    public function addIconToFullPageResult(Doku_Event $event, $param)
    {
        /* @var \helper_plugin_watchcycle $helper*/
        $helper = plugin_load('helper', 'watchcycle');

        $icon = $helper->getSearchResultIconHTML($event->data['page']);
        if ($icon) {
            $event->data['resultHeader'][] = $icon;
        }
    }
}

// vim:ts=4:sw=4:et:
