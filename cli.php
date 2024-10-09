<?php

use dokuwiki\Extension\CLIPlugin;
use splitbrain\phpcli\Exception;
use splitbrain\phpcli\Options;

class cli_plugin_watchcycle extends CLIPlugin
{
    /** @var helper_plugin_watchcycle_db */
    protected $dbHelper;

    /** @var helper_plugin_watchcycle */
    protected $helper;

    /**
     * Initialize helper plugins
     */
    public function __construct()
    {
        parent::__construct();
        $this->dbHelper = plugin_load('helper', 'watchcycle_db');
        $this->helper = plugin_load('helper', 'watchcycle');
    }

    /**
     * Register options and arguments on the given $options object
     *
     * @param Options $options
     * @return void
     * @throws Exception
     */
    protected function setup(Options $options)
    {
        $options->setHelp('Watchcycle notification dispatcher');
        $options->registerCommand('send', 'Notify maintainers if necessary');
    }

    /**
     * Your main program
     *
     * Arguments and options have been parsed when this is run
     *
     * @param Options $options
     * @return void
     */
    protected function main(Options $options)
    {
        $cmd = $options->getCmd();
        switch ($cmd) {
            case 'send':
                $this->sendNotifications();
                break;
            default:
                $this->error('No command provided');
                exit(1);
        }
    }

    /**
     * Check and send notifications
     */
    protected function sendNotifications()
    {
        $rows = $this->dbHelper->getAll();
        if (!is_array($rows)) {
            $this->info('Exiting: no users to notify found.');
            return;
        }

        auth_setup();

        foreach ($rows as $row) {
            if (!$row['uptodate']) {
                $this->helper->informMaintainer($row['maintainer'], $row['page']);
            }
        }
    }
}
