<?php
/**
 * @package    hubzero-cms
 * @copyright  Copyright (c) 2005-2020 The Regents of the University of California.
 * @license    http://opensource.org/licenses/MIT MIT
 */

namespace Components\Members\Api;

use Hubzero\Component\Router\Base;

/**
 * Routing class for the component
 */
class Router extends Base
{
	/**
	 * Build the route for the component.
	 *
	 * @param   array  &$query  An array of URL arguments
	 * @return  array  The URL arguments to use to assemble the subsequent URL.
	 */
	public function build(&$query)
	{
		$segments = array();

		if (!empty($query['controller']))
		{
			$segments[] = $query['controller'];
			unset($query['controller']);
		}

		if (!empty($query['task']))
		{
			$segments[] = $query['task'];
			unset($query['task']);
		}

		return $segments;
	}

	/**
	 * Parse the segments of a URL.
	 *
	 * @param   array  &$segments  The segments of the URL to parse.
	 * @return  array  The URL attributes to be used by the application.
	 */
	public function parse(&$segments)
	{
		$vars = array();

		$vars['controller'] = 'profiles';

		if (isset($segments[0]))
		{
			if ( ($segments[0] == 'currentuser') && (\App::get('request')->method() == 'GET'))
			{
				$vars['id'] = $segments[0];
				$vars['task'] = 'read';
			}
			else if (is_numeric($segments[0]))
			{
				$vars['id'] = $segments[0];
				if (isset($segments[1]))
				{
					$vars['task'] = $segments[1];
				}
				else if (\App::get('request')->method() == 'GET')
				{
					$vars['task'] = 'read';
				}
			}
			else if ($segments[0] == 'tools')
			{
				$vars['controller'] = $segments[0];
				$vars['task'] = 'index';
				if (isset($segments[1]))
				{
					$vars['task'] = $segments[1];
				}
			}
			
			// Added this for mentions API
			else if ($segments[0] == 'mentions') 
			{
				$vars['controller'] = $segments[0];
				$vars['task'] = 'index';
				if (isset($segments[1])) {
					$vars['task'] = $segments[1];
				}
			}
			else
			{
				$vars['task'] = $segments[0];
			}
		}

		return $vars;
	}
}
