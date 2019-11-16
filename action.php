<?php
/**
 * DokuWiki Plugin twistienav (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Håkan Sandell <sandell.hakan@gmail.com>
 * @maintainer: Simon DELAGE <sdelage@gmail.com>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once DOKU_PLUGIN.'action.php';

class action_plugin_twistienav extends DokuWiki_Action_Plugin {

//    protected $title_metadata = array();
//    protected $exclusions     = array();

    function __construct() {
        global $conf;

        // Load TwistieNav helper component
        $this->helper = plugin_load('helper','twistienav');

        // Get some variables frome helper
        $this->title_metadata = $this->helper->build_titlemetafields();
        list($this->exclusions, $this->nsignore) = $this->helper->build_exclusions();

    }

    /**
     * Register event handlers
     */
    public function register(Doku_Event_Handler $controller) {
        $controller->register_hook('DOKUWIKI_STARTED', 'BEFORE', $this, 'populate_jsinfo', array());
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'handle_ajax_call', array());
    }

    /**
     * Populate configuration settings to JSINFO
     */
    function populate_jsinfo(Doku_Event $event, $params) {
        global $JSINFO, $conf, $ID, $ACT;

        // Store settings values in JSINFO
        $JSINFO['conf']['start'] = $conf['start'];
        $JSINFO['conf']['breadcrumbs'] = $conf['breadcrumbs'];
        $JSINFO['conf']['youarehere'] = $conf['youarehere'];
        $JSINFO['plugin_twistienav']['twistiemap'] = $this->getConf('twistieMap');
        $JSINFO['plugin_twistienav']['style'] = $this->getConf('style');

        // List namespaces for YOUAREHERE breadcrumbs
        $yah_ns = array(0 => '');
        if ($conf['youarehere'] or ($this->getConf('pageIdTrace')) or ($this->getConf('pageIdExtraTwistie'))) {
            $parts = explode(':', $ID);
            $count = count($parts);
            $part = '';
            for($i = 0; $i < $count - 1; $i++) {
                $part .= $parts[$i].':';
                if ($part == $conf['start']) continue; // Skip start page
                $elements = 0;
                // Get index of current crumb namespace
                $idx  = cleanID(getNS($part));
                $dir  = utf8_encodeFN(str_replace(':','/',$idx));
                $data = array();
                search($data,$conf['datadir'],'search_index',array('ns' => $idx),$dir);
                // Count pages that are not in configured exclusions
                foreach ($data as $item) {
                    if (!in_array(noNS($item['id']), $this->exclusions)) {
                        $elements++;
                    }
                }
                // If there's at least one page that isn't excluded, prepare JSINFO data for that crumb
                if ($elements > 0) {
                    $yah_ns[$i+1] = $idx;
                }
            }
            $JSINFO['plugin_twistienav']['yah_ns'] = $yah_ns;
        }

        // List namespaces for TRACE breadcrumbs
        $bc_ns = array();
        if ($conf['breadcrumbs'] > 0) {
            $crumbs = breadcrumbs();
            // get namespaces currently in $crumbs
            $i = -1;
            foreach ($crumbs as $crumbId => $crumb) {
                $i++;
                // Don't do anything unless 'startPagesOnly' setting is off
                //  or current breadcrumb leads to a namespace start page
                if (($this->getConf('startPagesOnly') == 0) or (noNS($crumbId) == $conf['start'])) {
                    $elements = 0;
                    // Get index of current crumb namespace
                    $idx  = cleanID(getNS($crumbId));
                    $dir  = utf8_encodeFN(str_replace(':','/',$idx));
                    $data = array();
                    search($data,$conf['datadir'],'search_index',array('ns' => $idx),$dir);
                    // Count pages that are not in configured exclusions
                    foreach ($data as $item) {
                        if (!in_array(noNS($item['id']), $this->exclusions)) {
                            $elements++;
                        }
                    }
                    // If there's at least one page that isn't excluded, prepare JSINFO data for that crumb
                    if ($elements > 0) {
                        $bc_ns[$i] = $idx;
                    }
                }
            }
            $JSINFO['plugin_twistienav']['bc_ns'] = $bc_ns;
        }

        // Build 'pageIdTrace' skeleton if required
        if ((($this->getConf('pageIdTrace')) or ($this->getConf('pageIdExtraTwistie'))) and ($ACT == "show")) {
            $skeleton = '<span>';
            if ($this->getConf('pageIdTrace')) {
                $parts = explode(':', $ID);
                $count = count($parts);
                $part = '';
                for($i = 1; $i < $count; $i++) {
                    $part .= $parts[$i-1].':';
                    if ($part == $conf['start']) continue; // Skip startpage
                    if (isset($yah_ns[$i])) {
                        $skeleton .= '<a href="javascript:void(0)">'.$parts[$i-1].'</a>:';
                    } else {
                        $skeleton .= $parts[$i-1].':';
                    }
                }
                $skeleton .= end($parts);
            } else {
                $skeleton .= $ID;
            }
            if ($this->getConf('pageIdExtraTwistie')) {
                $skeleton .= '<a href="javascript:void(0)" ';
                $skeleton .= 'class="twistienav_extratwistie'.' '.$this->getConf('style');
                $skeleton .= ($this->getConf('twistieMap')) ? ' twistienav_map' : '';
                $skeleton .= '"></a>';
            }
            $skeleton .= '</span>';
            $JSINFO['plugin_twistienav']['pit_skeleton'] = $skeleton;
        }
    }

    /**
     * Ajax handler
     */
    function handle_ajax_call(Doku_Event $event, $params) {
        global $conf;

        $idx  = cleanID($_POST['idx']);

        // Process AJAX calls from 'plugin_twistienav' or 'plugin_twistienav_pageid'
        if (($event->data != 'plugin_twistienav') && ($event->data != 'plugin_twistienav_pageid') && ($event->data != 'plugin_twistienav_nsindex')) return;
        $event->preventDefault();
        $event->stopPropagation();

        // If AJAX caller is from 'pageId' we don't wan't to exclude start pages
        if ($event->data == 'plugin_twistienav_pageid') {
            $exclusions = array_diff($this->exclusions, array($conf['start']));
        } else {
            $exclusions = $this->exclusions;
        }
        // If AJAX caller is from 'pageId' we don't wan't to exclude any pages
        if ($event->data == 'plugin_twistienav_pageid') {
            $useexclusions = false;
        } else {
            $useexclusions = true;
        }

        $data = array();
        $this->data = $this->helper->get_idx_data($idx, $useexclusions);
        if (count($this->data) > 0) {
            echo '<ul>';
            foreach ($this->data as $item) {
                echo '<li>'.$item['link'].'</li>';
            }
            echo '</ul>';
        }
    }

}
// vim: set fileencoding=utf-8 expandtab ts=4 sw=4 :
