<?php

use dokuwiki\Cache\Cache;
use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Extension\Event;
use dokuwiki\Form\Form;
use dokuwiki\ChangeLog\PageChangeLog;
use dokuwiki\plugin\sqlite\SQLiteDB;

/**
 * DokuWiki Plugin watchcycle (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Szymon Olewniczak <dokuwiki@cosmocode.de>
 */

class action_plugin_watchcycle extends ActionPlugin
{
    /**
     * Registers a callback function for a given event
     *
     * @param EventHandler $controller DokuWiki's event controller object
     *
     * @return void
     */
    public function register(EventHandler $controller)
    {

        $controller->register_hook('PARSER_METADATA_RENDER', 'AFTER', $this, 'handleParserMetadataRender');
        $controller->register_hook('PARSER_CACHE_USE', 'AFTER', $this, 'handleParserCacheUse');
        // ensure a page revision is created when summary changes:
        $controller->register_hook('COMMON_WIKIPAGE_SAVE', 'BEFORE', $this, 'handlePagesaveBefore');
        $controller->register_hook('SEARCH_RESULT_PAGELOOKUP', 'BEFORE', $this, 'addIconToPageLookupResult');
        $controller->register_hook('SEARCH_RESULT_FULLPAGE', 'BEFORE', $this, 'addIconToFullPageResult');
        $controller->register_hook('FORM_SEARCH_OUTPUT', 'BEFORE', $this, 'addFilterToSearchForm');
        $controller->register_hook('FORM_QUICKSEARCH_OUTPUT', 'BEFORE', $this, 'handleFormQuicksearchOutput');
        $controller->register_hook('SEARCH_QUERY_FULLPAGE', 'AFTER', $this, 'filterSearchResults');
        $controller->register_hook('SEARCH_QUERY_PAGELOOKUP', 'AFTER', $this, 'filterSearchResults');

        $controller->register_hook('TOOLBAR_DEFINE', 'AFTER', $this, 'handleToolbarDefine');
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'handleAjaxGet');
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'handleAjaxValidate');
    }


    /**
     * Register a new toolbar button
     *
     * @param Event $event event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     *
     * @return void
     */
    public function handleToolbarDefine(Event $event, $param)
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
     * @param Event $event
     * @param            $param
     */
    public function addFilterToSearchForm(Event $event, $param)
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
     * @param Event $event event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     *
     * @return void
     */
    public function handleFormQuicksearchOutput(Event $event, $param)
    {
        /** @var Form $qsearchForm */
        $qsearchForm = $event->data;
        if ($this->getConf('default_maintained_only')) {
            $qsearchForm->setHiddenField('watchcycle_only', '1');
        }
    }

    /**
     * Filter the search results to show only maintained pages, if  watchcycle_only is true in $INPUT
     *
     * @param Event $event
     * @param            $param
     */
    public function filterSearchResults(Event $event, $param)
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
     * @param Event $event event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     *
     * @return void
     */
    public function handleParserMetadataRender(Event $event, $param)
    {
        global $ID;

        /** @var \helper_plugin_watchcycle_db $dbHelper */
        $dbHelper = plugin_load('helper', 'watchcycle_db');

        /** @var SQLiteDB */
        $sqlite = $dbHelper->getDB();

        /* @var \helper_plugin_watchcycle $helper */
        $helper = plugin_load('helper', 'watchcycle');

        $page = $event->data['current']['last_change']['id'];

        if (isset($event->data['current']['plugin']['watchcycle'])) {
            $watchcycle = $event->data['current']['plugin']['watchcycle'];
            $row = $sqlite->queryRecord('SELECT * FROM watchcycle WHERE page=?', $page);
            $changes = $this->getLastMaintainerRev($event->data, $watchcycle['maintainer'], $last_maintainer_rev);
            //false if page needs checking
            $uptodate = $helper->daysAgo($last_maintainer_rev) <= (int)$watchcycle['cycle'];

            if ($uptodate === false) {
                $helper->informMaintainer($watchcycle['maintainer'], $ID);
            }

            if (!$row) {
                $entry = $watchcycle;
                $entry['page'] = $page;
                $entry['last_maintainer_rev'] = $last_maintainer_rev;
                // uptodate is an int in the database
                $entry['uptodate'] = (int)$uptodate;
                $sqlite->saveRecord('watchcycle', $entry);
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

                //uptodate value has changed?
                if ($row['uptodate'] !== (int)$uptodate) {
                    $toupdate['uptodate'] = (int)$uptodate;
                }

                if ($toupdate !== []) {
                    $set = implode(',', array_map(static fn($v) => "$v=?", array_keys($toupdate)));
                    $toupdate[] = $page;
                    $sqlite->query("UPDATE watchcycle SET $set WHERE page=?", array_values($toupdate));
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
     * @param Event $event
     * @param string $param
     */
    public function handleAjaxGet(Event $event, $param)
    {
        if ($event->data != 'plugin_watchcycle_get') return;
        $event->preventDefault();
        $event->stopPropagation();
        global $conf;

        header('Content-Type: application/json');
        try {
            $result = $this->fetchUsersAndGroups();
        } catch (\Exception $e) {
            $result = [
                'error' => $e->getMessage() . ' ' . basename($e->getFile()) . ':' . $e->getLine()
            ];
            if ($conf['allowdebug']) {
                $result['stacktrace'] = $e->getTraceAsString();
            }
            http_status(500);
        }

        echo json_encode($result);
    }

    /**
     * JSON result of validation of maintainers definition
     *
     * @param Event $event
     * @param $param
     */
    public function handleAjaxValidate(Event $event, $param)
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
            $users = array_map(
                static fn($name, $user) => ['label' => $user['name'] . " ($name)", 'value' => $name],
                array_keys($foundUsers),
                $foundUsers
            );
        }

        $groups = [];

        // check cache
        $cachedGroups = new Cache('retrievedGroups', '.txt');
        if ($cachedGroups->useCache(['age' => 30])) {
            $foundGroups = unserialize($cachedGroups->retrieveCache());
        } else {
            $foundGroups = $auth->retrieveGroups();
            $cachedGroups->storeCache(serialize($foundGroups));
        }

        if (!empty($foundGroups)) {
            $groups = array_filter(
                array_map(function ($grp) use ($term) {
                    // filter groups
                    if (strpos($grp, (string) $term) !== false) {
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
            $changelog = new PageChangeLog($page);
            $first = 0;
            $num = 100;
            while (count($revs = $changelog->getRevisions($first, $num)) > 0) {
                foreach ($revs as $rev) {
                    ++$changes;
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
     * clean the cache every 24 hours
     *
     * @param Event $event event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     *
     * @return void
     */
    public function handleParserCacheUse(Event $event, $param)
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
     * @param Event $event event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     *
     * @return void
     */
    public function handlePagesaveBefore(Event $event, $param)
    {
        if ($event->data['contentChanged']) {
            return;
        } // will be saved for page changes

        //save page if summary is provided
        if (!empty($event->data['summary'])) {
            $event->data['contentChanged'] = true;
        }
    }

    /**
     * called for event SEARCH_RESULT_PAGELOOKUP
     *
     * @param Event $event
     * @param            $param
     */
    public function addIconToPageLookupResult(Event $event, $param)
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
     * @param Event $event
     * @param            $param
     */
    public function addIconToFullPageResult(Event $event, $param)
    {
        /* @var \helper_plugin_watchcycle $helper */
        $helper = plugin_load('helper', 'watchcycle');

        $icon = $helper->getSearchResultIconHTML($event->data['page']);
        if ($icon) {
            $event->data['resultHeader'][] = $icon;
        }
    }
}
