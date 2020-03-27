<?php
/**
 * Options for the TwistieNav plugin
 *
 * @author   Simon DELAGE <sdelage@gmail.com>
 */

$meta['startPagesOnly']     = array('onoff');
$meta['exclusions']         = array('multicheckbox', '_choices' => array('start','sidebar'));
$meta['twistieMap']         = array('onoff');
$meta['pageIdTrace']        = array('onoff');
$meta['pageIdExtraTwistie'] = array('onoff');
$meta['style']              = array('multichoice', '_choices' => array('svg','fa'));
$meta['useheading']         = array('onoff');
