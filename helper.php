<?php
/**
 * Helper Component for the TwistieNav Plugin
 *
 * @author   Simon DELAGE <sdelage@gmail.com>
 * @license: CC Attribution-Share Alike 3.0 Unported <http://creativecommons.org/licenses/by-sa/3.0/>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class helper_plugin_twistienav extends DokuWiki_Plugin {

    protected $title_metadata   = array();
    protected $exclusions       = array();
    protected $nsignore         = array();

    function build_titlemetafields() {
        // Known plugins that set title and corresponding metadata keys
        $this->title_metadata = array(
            'croissant' => 'plugin_croissant_bctitle',
            'pagetitle' => 'shorttitle',
        );
        foreach (array_keys($this->title_metadata) as $plugin) {
            if(plugin_isdisabled($plugin)) unset($this->title_metadata[$plugin]);
        }
        $this->title_metadata['dokuwiki'] = 'title';
        return $this->title_metadata;
    }

    function build_exclusions() {
        global $conf;

        // Convert "exclusions" config setting to array
        foreach (explode(',', $this->getConf('exclusions')) as $exclusion) {
            if (substr($exclusion, 0, 1) === '@') {
                $this->nsignore[] = ltrim($exclusion, "@");
            } else {
                switch ($exclusion) {   // care pre-defined keys in multicheckbox
                    case 'start':
                        $this->exclusions[] = $conf['start'];
                        break;
                    case 'sidebar':
                        $this->exclusions[] = $conf['sidebar'];
                        break;
                    default:
                        $this->exclusions[] = $exclusion;
                }
            }
        }
        return array($this->exclusions, $this->nsignore);
    }

    /**
     * Build a namespace index (list sub-namespaces and pages).
     *
     * @param (str)     $idx namespace ID, must not be a page ID.
     *                  Could be provided with : cleanID(getNS($ID))
     * @param (bool)    $useexclusions use `exclusions` setting or not
     * @param (bool)    $split return a simple level or more complex array
     * @return (arr)    list of sub namespaces and pages found within $idx namespace
     *
     * See https://www.dokuwiki.org/plugin:twistienav?do=draft#helper_component for details
     *
     */
    function get_idx_data($idx = null, $useexclusions = true, $split = false) {
        global $conf, $ID;
        // From an ajax call (ie. a click on a TwistieNav), $ID value isn't available so we need to get it from another way
        $ajaxId = ltrim(explode("id=", $_SERVER["HTTP_REFERER"])[1], ":");

        $dir  = utf8_encodeFN(str_replace(':','/',$idx));
        $data = array();
        search($data,$conf['datadir'],'search_index',array('ns' => $idx),$dir);

        if (count($data) != 0) {
            foreach ($data as $datakey => $item) {
                // Unset item if is in 'exclusions'
                if (($useexclusions) && (in_array(noNS($item['id']), $this->exclusions))) {
                    unset($data[$datakey]);
                    continue;
                // Unset item if it is in 'nsignore'
                } elseif (($useexclusions) && (in_array(explode(":", $item['id'])[0], $this->nsignore))) {
                    unset($data[$datakey]);
                    continue;
                // Unset item if it starts with "playground" or is equal to current $ID
                } elseif ((explode(":", $item['id'])[0] == "playground") or ($item['id'] == $ID) or ($item['id'] == $ajaxId)) {
                    unset($data[$datakey]);
                    continue;
                }
                // If item is a directory, we need an ID that points to that namespace's start page (even if it doesn't exist)
                if ($item['type'] == 'd') {
                    $target = $item['id'].':'.$conf['start'];
                    $classes = "is_ns ";
                // Or just keep current item ID
                } else {
                    $target = $item['id'];
                    $classes = "is_page ";
                }
                // Add (non-)existence class
                if (page_exists($target)) {
                    $classes .= "wikilink1";
                } else {
                    $classes .= "wikilink2";
                }
                // Get page title from metadata
                foreach ($this->title_metadata as $plugin => $pluginkey) {
                    $title = p_get_metadata($target, $pluginkey, METADATA_DONT_RENDER);
                    if ($title != null) break;
                }
                $data[$datakey]['id'] = $target;
                $title = @$title ?: hsc(noNS($item['id']));
                // Store a link to the page in the data that will be sent back
                $data[$datakey]['link'] = '<a href="'.wl($target).'" class="'.$classes.'">'.$title.'</a>';
            }
        }
        if ($split) {
            $result = array();
            $result['namespaces'] = array_values(array_filter($data, function ($row) {
                return $row["type"] == "d";
            }));
            $result['pages'] = array_values(array_filter($data, function ($row) {
                return $row["type"] == "f";
            }));
            return $result;
        } else {
            return array_values($data);
        }
    }

}
