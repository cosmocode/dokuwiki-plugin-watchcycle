<?php

use dokuwiki\Form\Form;
use dokuwiki\Form\InputElement;
use dokuwiki\plugin\sqlite\SQLiteDB;

/**
 * DokuWiki Plugin watchcycle (Admin Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Szymon Olewniczak <dokuwiki@cosmocode.de>
 */

class admin_plugin_watchcycle extends DokuWiki_Admin_Plugin
{

    /**
     * @return int sort number in admin menu
     */
    public function getMenuSort()
    {
        return 1;
    }

    /**
     * @return bool true if only access for superuser, false is for superusers and moderators
     */
    public function forAdminOnly()
    {
        return false;
    }

    /**
     * Should carry out any processing required by the plugin.
     */
    public function handle()
    {
    }

    /**
     * Render HTML output, e.g. helpful text and a form
     */
    public function html()
    {
        global $ID;
        /* @var Input */
        global $INPUT;

        /** @var \helper_plugin_watchcycle_db $dbHelper */
        $dbHelper = plugin_load('helper', 'watchcycle_db');

        /** @var SQLiteDB */
        $sqlite = $dbHelper->getDB();

        echo '<h1>' . $this->getLang('menu') . '</h1>';

        echo '<div id="plugin__watchcycle_admin">';

        $form = new Form();
        $filter_input = new InputElement('text', 'filter');
        $filter_input->attr('placeholder', $this->getLang('search page'));
        $form->addElement($filter_input);

        $form->addButton('', $this->getLang('btn filter'));

        $form->addHTML('<label class="outdated">');
        $form->addCheckbox('outdated');
        $form->addHTML($this->getLang('show outdated only'));
        $form->addHTML('</label>');


        echo $form->toHTML();
        echo '<table>';
        echo '<tr>';
        $headers = ['page', 'maintainer', 'cycle', 'current', 'uptodate'];
        foreach ($headers as $header) {
            $lang = $this->getLang("h $header");
            $param = [
                'do' => 'admin',
                'page' => 'watchcycle',
                'sortby' => $header,
            ];
            $icon = '';
            if ($INPUT->str('sortby') == $header) {
                if ($INPUT->int('desc') == 0) {
                    $param['desc'] = 1;
                    $icon = '↑';
                } else {
                    $param['desc'] = 0;
                    $icon = '↓';
                }
            }
            $href = wl($ID, $param);

            echo '<th><a href="' . $href . '">' . $icon . ' ' . $lang . '</a></th>';
        }

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

        if (count($where) > 0) {
            $q .= ' WHERE ';
            $q .= implode(' AND ', $where);
        }

        if ($INPUT->has('sortby') && in_array($INPUT->str('sortby'), $headers)) {
            $q .= ' ORDER BY ' . $INPUT->str('sortby');
            if ($INPUT->int('desc') == 1) {
                $q .= ' DESC';
            }
        }

        $rows = $sqlite->queryAll($q, $q_args);
        foreach ($rows as $row) {
            echo '<tr>';
            echo '<td><a href="' . wl($row['page']) . '" class="wikilink1">' . $row['page'] . '</a></td>';
            echo '<td>' . $row['maintainer'] . '</td>';
            echo '<td>' . $row['cycle'] . '</td>';
            echo '<td>' . $row['current'] . '</td>';
            $icon = $row['uptodate'] == 1 ? '✓' : '✕';
            echo '<td>' . $icon . '</td>';
            echo '</tr>';
        }

        echo '</tr>';
        echo '</table>';
        echo '</div>';
    }
}
