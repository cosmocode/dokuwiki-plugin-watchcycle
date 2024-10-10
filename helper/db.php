<?php

use dokuwiki\ErrorHandler;
use dokuwiki\Extension\Plugin;
use dokuwiki\plugin\sqlite\SQLiteDB;

/**
 * DokuWiki Plugin watchcycle (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Szymon Olewniczak <dokuwiki@cosmocode.de>
 * @author  Anna Dabrowska <dokuwiki@cosmocode.de>
 */

class helper_plugin_watchcycle_db extends Plugin
{
    /** @var SQLiteDB */
    protected $sqlite;

    public function __construct()
    {
        $this->init();
    }

    /**
     * Initialize the database
     *
     * @throws Exception
     */
    protected function init()
    {
        $this->sqlite = new SQLiteDB('watchcycle', DOKU_PLUGIN . 'watchcycle/db/');

        $helper = plugin_load('helper', 'watchcycle');
        $this->sqlite->getPdo()->sqliteCreateFunction('DAYS_AGO', [$helper, 'daysAgo'], 1);
    }

    /**
     * @param bool $throw throw an Exception when sqlite not available or fails to load
     * @return SQLiteDB|null
     * @throws Exception
     */
    public function getDB($throw = true)
    {
        return $this->sqlite;
    }

    /**
     * @param array $headers
     * @return array
     */
    public function getAll(array $headers = [])
    {
        global $INPUT;

        $q = 'SELECT page, maintainer, cycle, DAYS_AGO(last_maintainer_rev) AS current, uptodate FROM watchcycle';
        $where = [];
        $q_args = [];
        if ($INPUT->str('filter') != '') {
            $where[] = 'page LIKE ?';
            $q_args[] = '%' . $INPUT->str('filter') . '%';
        }
        if ($INPUT->has('outdated')) {
            $where[] = 'uptodate=0';
        }

        if ($where !== []) {
            $q .= ' WHERE ';
            $q .= implode(' AND ', $where);
        }

        if ($INPUT->has('sortby') && in_array($INPUT->str('sortby'), $headers)) {
            $q .= ' ORDER BY ' . $INPUT->str('sortby');
            if ($INPUT->int('desc') == 1) {
                $q .= ' DESC';
            }
        }

        return $this->sqlite->queryAll($q, $q_args);
    }
}
