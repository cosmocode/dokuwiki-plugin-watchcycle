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
        if (!$this->sqlite instanceof SQLiteDB) {
            if (!class_exists(SQLiteDB::class)) {
                if ($throw || defined('DOKU_UNITTEST')) throw new StructException('no sqlite');
                return null;
            }

            try {
                $this->init();
            } catch (\Exception $exception) {
                ErrorHandler::logException($exception);
                if ($throw) throw $exception;
                return null;
            }
        }
        return $this->sqlite;
    }
}
