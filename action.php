<?php
/**
 * DokuWiki Plugin watchcycle (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Szymon Olewniczak <dokuwiki@cosmocode.de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) {
    die();
}

class action_plugin_watchcycle extends DokuWiki_Action_Plugin
{

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     *
     * @return void
     */
    public function register(Doku_Event_Handler $controller)
    {

        $controller->register_hook('PARSER_METADATA_RENDER', 'AFTER', $this, 'handle_parser_metadata_render');
        $controller->register_hook('PARSER_CACHE_USE', 'AFTER', $this, 'handle_parser_cache_use');
        // ensure a page revision is created when summary changes:
        $controller->register_hook('COMMON_WIKIPAGE_SAVE', 'BEFORE', $this, 'handle_pagesave_before');
        $controller->register_hook('SEARCH_RESULT_PAGELOOKUP', 'BEFORE', $this, 'addIconToPageLookupResult');
        $controller->register_hook('SEARCH_RESULT_FULLPAGE', 'BEFORE', $this, 'addIconToFullPageResult');
        $controller->register_hook('FORM_SEARCH_OUTPUT', 'BEFORE', $this, 'addFilterToSearchForm');
        $controller->register_hook('FORM_QUICKSEARCH_OUTPUT', 'BEFORE', $this, 'handle_form_quicksearch_output');
        $controller->register_hook('SEARCH_QUERY_FULLPAGE', 'AFTER', $this, 'filterSearchResults');
        $controller->register_hook('SEARCH_QUERY_PAGELOOKUP', 'AFTER', $this, 'filterSearchResults');

        $controller->register_hook('TOOLBAR_DEFINE', 'AFTER', $this, 'handle_toolbar_define');
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'handle_ajax_get');
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'handle_ajax_validate');
    }


    /**
     * Register a new toolbar button
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     *
     * @return void
     */
    public function handle_toolbar_define(Doku_Event $event, $param)
    {
        $event->data[] = [
            'type' => 'plugin_watchcycle',
            'title' => $this->getLang('title toolbar button'),
            'icon' => '../../plugins/watchcycle/images/eye-plus16Green.png',
        ];
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
     * Handles the FORM_QUICKSEARCH_OUTPUT event
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     *
     * @return void
     */
    public function handle_form_quicksearch_output(Doku_Event $event, $param)
    {
        /** @var \dokuwiki\Form\Form $qsearchForm */
        $qsearchForm = $event->data;
        if ($this->getConf('default_maintained_only')) {
            $qsearchForm->setHiddenField('watchcycle_only', '1');
        }
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
     *
     * @return void
     */
    public function handle_parser_metadata_render(Doku_Event $event, $param)
    {
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

        if (isset($event->data['current']['plugin']['watchcycle'])) {
            $watchcycle = $event->data['current']['plugin']['watchcycle'];
            $res = $sqlite->query('SELECT * FROM watchcycle WHERE page=?', $page);
            $row = $sqlite->res2row($res);
            $changes = $this->getLastMaintainerRev($event->data, $watchcycle['maintainer'], $last_maintainer_rev);
            //false if page needs checking
            $uptodate = $helper->daysAgo($last_maintainer_rev) <= (int)$watchcycle['cycle'];

            if ($uptodate === false) {
                $this->informMaintainer($watchcycle['maintainer'], $ID);
            }

            if (!$row) {
                $entry = $watchcycle;
                $entry['page'] = $page;
                $entry['last_maintainer_rev'] = $last_maintainer_rev;
                // uptodate is an int in the database
                $entry['uptodate'] = (int)$uptodate;
                $sqlite->storeEntry('watchcycle', $entry);
            } else { //check if we need to update something
                $toupdate = [];

                if ($row['cycle'] != $watchcycle['cycle']) {
                    $toupdate['cycle'] = $watchcycle['cycle'];
                }

                if ($row['maintainer'] != $watchcycle['maintainer']) {
                    $toupdate['maintainer'] = $watchcycle['maintainer'];
                }

                if ($row['last_maintainer_rev'] != $last_maintainer_rev) {
                    $toupdate['last_maintainer_rev'] = $last_maintainer_rev;
                }

                //uptodate value has changed? compare with the string we got from the database
                if ($row['uptodate'] !== (string)(int)$uptodate) {
                    $toupdate['uptodate'] = (int)$uptodate;
                }

                if (count($toupdate) > 0) {
                    $set = implode(',', array_map(function ($v) {
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
     * Returns JSON with filtered users and groups
     *
     * @param Doku_Event $event
     * @param string $param
     */
    public function handle_ajax_get(Doku_Event $event, $param)
    {
        if ($event->data != 'plugin_watchcycle_get') return;
        $event->preventDefault();
        $event->stopPropagation();
        global $conf;

        header('Content-Type: application/json');
        try {
            $result = $this->fetchUsersAndGroups();
        } catch(\Exception $e) {
            $result = [
                'error' => $e->getMessage().' '.basename($e->getFile()).':'.$e->getLine()
            ];
            if($conf['allowdebug']) {
                $result['stacktrace'] = $e->getTraceAsString();
            }
            http_status(500);
        }

        echo json_encode($result);
    }

    /**
     * JSON result of validation of maintainers definition
     *
     * @param Doku_Event $event
     * @param $param
     */
    public function handle_ajax_validate(Doku_Event $event, $param)
    {
        if ($event->data != 'plugin_watchcycle_validate') return;
        $event->preventDefault();
        $event->stopPropagation();

        global $INPUT;
        $maintainers = $INPUT->str('param');

        if (empty($maintainers)) return;

        header('Content-Type: application/json');

        /* @var \helper_plugin_watchcycle $helper */
        $helper = plugin_load('helper', 'watchcycle');

        echo json_encode($helper->validateMaintainerString($maintainers));
    }

    /**
     * Returns filtered users and groups, if supported by the current authentication
     *
     * @return array
     */
    protected function fetchUsersAndGroups()
    {
        global $INPUT;
        $term = $INPUT->str('param');

        if (empty($term)) return [];

        /* @var DokuWiki_Auth_Plugin $auth */
        global $auth;

        $users = [];
        $foundUsers = $auth->retrieveUsers(0, 50, ['user' => $term]);
        if (!empty($foundUsers)) {
            $users = array_map(function ($name, $user) use ($term) {
                return ['label' => $user['name'] . " ($name)", 'value' => $name];
            }, array_keys($foundUsers), $foundUsers);
        }

        $groups = [];

        // check cache
        $cachedGroups = new cache('retrievedGroups', '.txt');
        if($cachedGroups->useCache(['age' => 30])) {
            $foundGroups = unserialize($cachedGroups->retrieveCache());
        } else {
            $foundGroups = $auth->retrieveGroups();
            $cachedGroups->storeCache(serialize($foundGroups));
        }

        if (!empty($foundGroups)) {
            $groups = array_filter(
                array_map(function ($grp) use ($term) {
                    // filter groups
                    if (strpos($grp, $term) !== false) {
                        return ['label' => '@' . $grp, 'value' => '@' . $grp];
                    }
                }, $foundGroups)
            );
        }

        return array_merge($users, $groups);
    }

    /**
     * @param array  $meta metadata of the page
     * @param string $maintainer
     * @param int    $rev  revision of the last page edition by maintainer or -1 if no edition was made
     *
     * @return int   number of changes since last maintainer's revision or -1 if no changes was made
     */
    protected function getLastMaintainerRev($meta, $maintainer, &$rev)
    {
        $changes = 0;

        /* @var \helper_plugin_watchcycle $helper */
        $helper = plugin_load('helper', 'watchcycle');

        if ($helper->isMaintainer($meta['current']['last_change']['user'], $maintainer)) {
            $rev = $meta['current']['last_change']['date'];
            return $changes;
        } else {
            $page = $meta['current']['last_change']['id'];
            $changelog = new PageChangelog($page);
            $first = 0;
            $num = 100;
            while (count($revs = $changelog->getRevisions($first, $num)) > 0) {
                foreach ($revs as $rev) {
                    $changes += 1;
                    $revInfo = $changelog->getRevisionInfo($rev);
                    if ($helper->isMaintainer($revInfo['user'], $maintainer)) {
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
     * Inform all maintainers that the page needs checking
     *
     * @param string $def defined maintainers
     * @param string $page that needs checking
     */
    protected function informMaintainer($def, $page)
    {
        /* @var DokuWiki_Auth_Plugin $auth */
        global $auth;

        /* @var \helper_plugin_watchcycle $helper */
        $helper = plugin_load('helper', 'watchcycle');
        $mails = $helper->getMaintainerMails($def);
        foreach ($mails as $mail) {
            $this->sendMail($mail, $page);
        }
    }

    /**
     * clean the cache every 24 hours
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     *
     * @return void
     */
    public function handle_parser_cache_use(Doku_Event $event, $param)
    {
        /* @var \helper_plugin_watchcycle $helper */
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
     *
     * @return void
     */
    public function handle_pagesave_before(Doku_Event $event, $param)
    {
        if ($event->data['contentChanged']) {
            return;
        } // will be saved for page changes
        global $ACT;

        //save page if summary is provided
        if (!empty($event->data['summary'])) {
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
        /* @var \helper_plugin_watchcycle $helper */
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
        /* @var \helper_plugin_watchcycle $helper */
        $helper = plugin_load('helper', 'watchcycle');

        $icon = $helper->getSearchResultIconHTML($event->data['page']);
        if ($icon) {
            $event->data['resultHeader'][] = $icon;
        }
    }

    /**
     * Sends an email
     *
     * @param array $mail
     * @param string $page
     */
    protected function sendMail($mail, $page)
    {
        $mailer = new Mailer();
        $mailer->to($mail);
        $mailer->subject($this->getLang('mail subject'));
        $text = sprintf($this->getLang('mail body'), $page);
        $link = '<a href="' . wl($page, '', true) . '">' . $page . '</a>';
        $html = sprintf($this->getLang('mail body'), $link);
        $mailer->setBody($text, null, null, $html);

        if (!$mailer->send()) {
            msg($this->getLang('error mail'), -1);
        }
    }
}

// vim:ts=4:sw=4:et:
