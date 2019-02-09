/**
 * AJAX functions for the pagename quicksearch
 *
 * @license  GPL2 (http://www.gnu.org/licenses/gpl.html)
 * @author   Håkan Sandell <sandell.hakan@gmail.com>
 */

var twistienav_plugin = {

    $callerObj: null,

    init: function () {
		var $match = 0;
        if ((JSINFO['conf']['breadcrumbs'] > 0) && (jQuery('.youarehere').length !== 0)) {
            twistienav_plugin.breadcrumbs('.youarehere', 'yah_ns');
			$match++;
        }
        if ((JSINFO['conf']['youarehere'] == 1) && (jQuery('.trace').length !== 0)) {
            twistienav_plugin.breadcrumbs('.trace', 'bc_ns');
			$match++;
        }
        if ($match == 0) {
			if ((JSINFO['conf']['breadcrumbs'] > 0) && (jQuery('.breadcrumbs:has("span.bcsep")').length !== 0)) {
				twistienav_plugin.breadcrumbs('.breadcrumbs:has("span.bcsep")', 'bc_ns');
			}
			if ((JSINFO['conf']['youarehere'] == 1) && (jQuery('.breadcrumbs:not(:has("span.bcsep"))').length !== 0)) {
				twistienav_plugin.breadcrumbs('.breadcrumbs:not(:has("span.bcsep"))', 'yah_ns');
			}
		}
        if ((JSINFO['plugin_twistienav']['pit_skeleton'] != null) && (jQuery('.pageId').length !== 0)) {
            twistienav_plugin.pageIdTrace('.pageId', 'yah_ns');
        }

        return;
    },

    /**
     * Add twisties and link events 
     */
    breadcrumbs: function(div, ns_list){
        var do_search;
        var $traceObj = jQuery(div);
        var $list = JSINFO['plugin_twistienav'][ns_list];

        jQuery(document).click(function(e) {
            twistienav_plugin.clear_results();
        });
        
        do_search = function (caller, namespace) {
            twistienav_plugin.$callerObj = jQuery(caller);
            jQuery.post(
                DOKU_BASE + 'lib/exe/ajax.php',
                {
                    call: 'plugin_twistienav',
                    idx: encodeURI(namespace)
                },
                twistienav_plugin.onCompletion,
                'html'
            );
        };

        // NOT REQUIRED ANYMORE
        // remove current id highlight because it is inherited by popup
        //trace = $traceObj.html();
        //trace = trace.replace(/<span class="curid">/gi,'')
        //             .replace(/<\/span>$/gi,'');
        //$traceObj.html(trace);

        // add new twisties
        var linkNo = 0;
        $links = $traceObj.find('a');
        $links.each(function () {
            var ns = $list[linkNo];
            if (ns == false) {
                ns = '';
            }
            if ($list[linkNo] || $list[linkNo] == '') {
                var $classes = 'twistienav_twistie' + ' ' + JSINFO['plugin_twistienav']['style'];
                if ((JSINFO['plugin_twistienav']['twistiemap'] == 1) && (ns == '')) {
                    $classes = 'twistienav_map' + ' ' + JSINFO['plugin_twistienav']['style'];
                }
                jQuery(document.createElement('span'))
                            .addClass($classes)
                            .show()
                            .insertAfter(jQuery(this).parent())
                            .click(function() {
                                twistie_active = jQuery(this).hasClass('twistienav_down'); 
                                twistienav_plugin.clear_results();
                                if (!twistie_active) {
                                    do_search(this, ns);
                                }
                            });
            }
            linkNo++;
        });
    },

    /**
     * Turn 'pageId' element into a minimalistic hierarchical trace
     */
    pageIdTrace: function(div, ns_list){
        var do_search;
        var $traceObj = jQuery(div);
        var $list = JSINFO['plugin_twistienav'][ns_list];

        jQuery(document).click(function(e) {
            twistienav_plugin.clear_results();
        });
        
        do_search = function (caller, namespace) {
            twistienav_plugin.$callerObj = jQuery(caller);
            jQuery.post(
                DOKU_BASE + 'lib/exe/ajax.php',
                {
                    call: 'plugin_twistienav_pageid',
                    idx: encodeURI(namespace)
                },
                twistienav_plugin.onCompletion,
                'html'
            );
        };

        // Replace pageId text by prepared skeleton
        $traceObj.html(JSINFO['plugin_twistienav']['pit_skeleton']);

        // transform links into text "twisties"
        var linkNo = 1;
        $links = $traceObj.find('a');
        $links.each(function () {
            var ns = $list[linkNo];
            if (ns == false) {
                ns = '';
            }
            if ($list[linkNo] || $list[linkNo] == '') {
                jQuery(this)
                            .addClass('twistienav_twistie')
                            .show()
                            .insertAfter(this)
                            .click(function() {
                                twistie_active = jQuery(this).hasClass('twistienav_down'); 
                                twistienav_plugin.clear_results();
                                if (!twistie_active) {
                                    do_search(this, ns);
                                }
                            });
            } else {
                jQuery(this)
                            .addClass('twistienav_twistie')
                            .show()
                            .insertAfter(this)
                            .click(function() {
                                twistie_active = jQuery(this).hasClass('twistienav_down'); 
                                twistienav_plugin.clear_results();
                                if (!twistie_active) {
                                    do_search(this, '');
                                }
                            });
            }
            linkNo++;
        });
    },

    /**
     * Remove output div
     */
    clear_results: function(){
        jQuery('.twistienav_twistie').removeClass('twistienav_down');
        jQuery('.twistienav_map').removeClass('twistienav_down');
        jQuery('#twistienav__popup').remove();
    },

    /**
     * Callback. Reformat and display the results.
     *
     * Namespaces are shortened here to keep the results from overflowing
     * or wrapping
     *
     * @param data The result HTML
     */
    onCompletion: function(data) {
        var pos = twistienav_plugin.$callerObj.position();

        if (data === '') { return; }

        twistienav_plugin.$callerObj.addClass('twistienav_down');

        jQuery(document.createElement('div'))
                        .html(data)
                        .attr('id','twistienav__popup')
                        .css({
                            'position':    'absolute'
                        })
                        .appendTo("body")
                        .position({
                            "my": "left top",
                            "at": "right bottom",
                            "of": twistienav_plugin.$callerObj,
                            "collision": "flipfit"
                        })
                        .click(function() {
                            twistienav_plugin.clear_results();
                        });
    }
};

jQuery(function () {
    twistienav_plugin.init();
});
// vim: set fileencoding=utf-8 expandtab ts=4 sw=4 :
