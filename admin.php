<?php
/**
 * DokuWiki Plugin watchcycle (Admin Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Szymon Olewniczak <dokuwiki@cosmocode.de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) {
    die();
}

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

        /** @var \helper_plugin_sqlite $sqlite */
        $sqlite = plugin_load('helper', 'watchcycle_db')->getDB();
        /* @var \helper_plugin_watchcycle */
        $helper = plugin_load('helper', 'watchcycle');

        ptln('<h1>' . $this->getLang('menu') . '</h1>');

        ptln('<div id="plugin__watchcycle_admin">');

        $form = new \dokuwiki\Form\Form();
        $filter_input = new \dokuwiki\Form\InputElement('text', 'filter');
        $filter_input->attr('placeholder', $this->getLang('search page'));
        $form->addElement($filter_input);

        $form->addButton('', $this->getLang('btn filter'));

        $form->addHTML('<label class="outdated">');
        $form->addCheckbox('outdated');
        $form->addHTML($this->getLang('show outdated only'));
        $form->addHTML('</label>');


        ptln($form->toHTML());
        ptln('<table>');
        ptln('<tr>');
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

            ptln('<th><a href="' . $href . '">' . $icon . ' ' . $lang . '</a></th>');
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

        $res = $sqlite->query($q, $q_args);
        while ($row = $sqlite->res2row($res)) {
            ptln('<tr>');
            ptln('<td><a href="' . wl($row['page']) . '" class="wikilink1">' . $row['page'] . '</a></td>');
            ptln('<td>' . $row['maintainer'] . '</td>');
            ptln('<td>' . $row['cycle'] . '</td>');
            ptln('<td>' . $row['current'] . '</td>');
            $icon = $row['uptodate'] == 1 ? '✓' : '✕';
            ptln('<td>' . $icon . '</td>');
            ptln('</tr>');
        }

        ptln('</tr>');
        ptln('</table>');

        ptln('</div>');
    }
}

// vim:ts=4:sw=4:et:
