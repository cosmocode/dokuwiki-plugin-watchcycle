<?php
/**
 * DokuWiki Plugin watchcycle (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Szymon Olewniczak <dokuwiki@cosmocode.de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

class syntax_plugin_watchcycle extends DokuWiki_Syntax_Plugin {
    /**
     * @return string Syntax mode type
     */
    public function getType() {
        return 'disabled';
    }

    /**
     * @return int Sort order - Low numbers go before high numbers
     */
    public function getSort() {
        return 100;
    }

    /**
     * Connect lookup pattern to lexer.
     *
     * @param string $mode Parser mode
     */
    public function connectTo($mode) {
        $this->Lexer->addSpecialPattern('~~WATCHCYCLE.*~~',$mode,'plugin_watchcycle');
    }

    /**
     * Handle matches of the watchcycle syntax. We assume that maintainer name doesn't contain semicolons.
     *
     * @param string          $match   The match of the syntax
     * @param int             $state   The state of the handler
     * @param int             $pos     The position in the document
     * @param Doku_Handler    $handler The handler
     * @return array Data for the renderer
     */
    public function handle($match, $state, $pos, Doku_Handler $handler){
        /* @var DokuWiki_Auth_Plugin */
        global $auth;

        $data = array();
        if (!preg_match('/~~WATCHCYCLE:[^:]+:\d+~~/', $match)) {
            msg('watchcycle: invalid syntax', -1);
            return false;
        }

        $match = substr($match, strlen('~~WATCHCYCLE:'), strlen($match)-2);

        list($maintainer, $cycle) = array_map('trim', explode(':', $match));

        if ($auth->getUserData($maintainer) === false) {
            msg( 'watchcycle: maintainer must be a dokuwiki user', -1);
            return false;
        }

        $data = ['maintainer' => $maintainer, 'cycle' => (int) $cycle];

        return $data;
    }

    /**
     * Render xhtml output or metadata
     *
     * @param string         $mode      Renderer mode (supported modes: xhtml)
     * @param Doku_Renderer  $renderer  The renderer
     * @param array          $data      The data from the handler() function
     * @return bool If rendering was successful.
     */

    public function render($mode, Doku_Renderer $renderer, $data) {
        if(!$data) return false;

        $method = "render_$mode";
        if (method_exists($this, $method)) {
            call_user_func(array($this, $method), $renderer, $data);
            return true;
        }
        return false;
    }

    /**
     * Render metadata
     *
     * @param Doku_Renderer  $renderer  The renderer
     * @param array          $data      The data from the handler() function
     */
    public function render_metadata(Doku_Renderer $renderer, $data) {
        $plugin_name = $this->getPluginName();

        $renderer->meta['plugin'][$plugin_name] = $data;
    }

    /**
     * Render xhtml
     *
     * @param Doku_Renderer  $renderer  The renderer
     * @param array          $data      The data from the handler() function
     */
    public function render_xhtml(Doku_Renderer $renderer, $data) {
        global $ID;
        /** @var \DokuWiki_Auth_Plugin $auth */
        global $auth;

        /* @var \helper_plugin_watchcycle */
        $helper = plugin_load('helper', 'watchcycle');

        $watchcycle = p_get_metadata($ID, 'plugin watchcycle');

        $days_ago = $helper->daysAgo($watchcycle['last_maintainer_rev']);

        $check_needed = false;
        if ($days_ago > $watchcycle['cycle']) {
            $check_needed = true;
        }

        $class = '';
        if ($check_needed) {
            $class = 'class="check_needed"';
        }
        $renderer->doc .= '<div id="plugin__watchcycle" ' . $class . '>' . NL;
        $renderer->doc .= '<div class="column">';
        $renderer->doc  .= inlineSVG(DOKU_PLUGIN . 'watchcycle/admin.svg');
        $renderer->doc .= '</div>';

        $renderer->doc .= '<div class="column">';
        $user = $watchcycle['maintainer'];
        $userData = $auth->getUserData($user);
        $maintainer_link = $this->email($userData['mail'], $userData['name']);
        $renderer->doc .= sprintf($this->getLang('maintained by'), $maintainer_link) . '<br />'. NL;

        $renderer->doc .= sprintf($this->getLang('last check'), $days_ago);
        if ($check_needed) {
            $renderer->doc .= ' (' . $this->getLang('check needed') . ')';
        }
        $renderer->doc .= '<br />'. NL;

        if ($watchcycle['changes'] == -1) {
            $renderer->doc .= $this->getLang('never checked') .  '<br />'. NL;
        } else {
            $urlParameters = ['rev' => $watchcycle['last_maintainer_rev'], 'do' => 'diff'];
            $changes_lang = $this->getLang('change ' . ($watchcycle['changes'] == 1 ? 'singular' : 'plural'));
            $changes_link = $watchcycle['changes'] . ' ' . $changes_lang;
            $changes_link = '<a href="'.wl($ID, $urlParameters).'">' . $changes_link . '</a>';
            $renderer->doc .= sprintf($this->getLang('since last check'), $changes_link) . '<br />'. NL;
        }

        $renderer->doc .= '</div>';

        $renderer->doc .= '</div>';
    }

}

// vim:ts=4:sw=4:et:
