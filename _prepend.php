<?php
# -- BEGIN LICENSE BLOCK ----------------------------------
# This file is part of pollsFactory, a plugin for Dotclear 2.
# 
# Copyright (c) 2009-2010 JC Denis and contributors
# jcdenis@gdwd.com
# 
# Licensed under the GPL version 2.0 license.
# A copy of this license is available in LICENSE file or at
# http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
# -- END LICENSE BLOCK ------------------------------------

if (!defined('DC_RC_PATH')){return;}

global $core, $_autoload;
$core->blog->settings->addNamespace('pollsFactory');

# Shortcuts for advanced actions
$__autoload['pollsFactory'] = dirname(__FILE__).'/inc/class.pollsfactory.php';
# DB class
$__autoload['postOption'] = dirname(__FILE__).'/inc/class.postoption.php';
# Charts class
$__autoload['pollsFactoryChart'] = dirname(__FILE__).'/inc/class.pollsfactory.chart.php';
# Public poll page (url 'poll' is at plugin dotPoll...)
$core->url->register('pollsFactoryPage','survey','^survey/(.+)$',array('publicUrlPollsFactory','pollPage'));
# Public poll page preview
$core->url->register('pollsFactoryPagePreview','surveypreview','^surveypreview/(.+)$',array('publicUrlPollsFactory','pollPagePreview'));
# Url for graphic charts
$core->url->register('pollsFactoryChart','surveychart','^surveychart/([^/]+/[^/]+).png$',array('publicUrlPollsFactory','pollChart'));
# Post type
$core->setPostType('pollsfactory','plugin.php?p=pollsFactory&tab=poll&id=%s',$core->url->getBase('pollsFactoryPage').'/%s');
# Add pollsFactory reports on plugin activityReport
if (defined('ACTIVITY_REPORT'))
{
	require_once dirname(__FILE__).'/inc/lib.pollsfactory.activityreport.php';
}
?>