<?php
/**
 * HUBzero CMS
 *
 * Copyright 2005-2015 Purdue University. All rights reserved.
 *
 * This file is part of: The HUBzero(R) Platform for Scientific Collaboration
 *
 * The HUBzero(R) Platform for Scientific Collaboration (HUBzero) is free
 * software: you can redistribute it and/or modify it under the terms of
 * the GNU Lesser General Public License as published by the Free Software
 * Foundation, either version 3 of the License, or (at your option) any
 * later version.
 *
 * HUBzero is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * HUBzero is a registered trademark of Purdue University.
 *
 * @package   hubzero-cms
 * @author    Shawn Rice <zooley@purdue.edu>
 * @copyright Copyright 2005-2015 Purdue University. All rights reserved.
 * @license   http://www.gnu.org/licenses/lgpl-3.0.html LGPLv3
 */

namespace Components\Members\Helpers;

use Lang;

/**
 * Members helper class for various HTML output
 */
class Html
{
	/**
	 * Generate a select list for access levels
	 *
	 * @param   string  $name   Select name
	 * @param   integer $value  Value to preselect
	 * @param   string  $class  Class to add to the element
	 * @param   string  $id     Select ID
	 * @return  string  HTML select list
	 */
	public static function selectAccess($name, $value, $class='', $id='')
	{
		$arr = array(
			0 => Lang::txt('COM_MEMBERS_FIELD_ACCESS_PUBLIC'),
			1 => Lang::txt('COM_MEMBERS_FIELD_ACCESS_REGISTERED'),
			2 => Lang::txt('COM_MEMBERS_FIELD_ACCESS_PRIVATE')
		);

		$html  = '<select name="' . $name . '"';
		$html .= ($id)    ? ' id="' . $id . '"'       : '';
		$html .= ($class) ? ' class="' . $class . '"' : '';
		$html .= '>' . "\n";
		foreach ($arr as $k => $v)
		{
			$selected = ($k == $value)
					  ? ' selected="selected"'
					  : '';
			$html .= ' <option value="' . $k . '"' . $selected . '>' . $v . '</option>' . "\n";
		}
		$html .= '</select>' . "\n";
		return $html;
	}

	/**
	 * Output a response for a single option field
	 *
	 * @param   string  $response  Normalized response code
	 * @return  string
	 */
	public static function propercase_singleresponse($response)
	{
		$html = '';
		switch ($response)
		{
			case '':        $html .= Lang::txt('COM_MEMBERS_FIELD_VALUE_NA');      break;
			case 'no':      $html .= Lang::txt('COM_MEMBERS_FIELD_VALUE_NONE');    break;
			case 'refused': $html .= Lang::txt('COM_MEMBERS_FIELD_VALUE_REFUSED'); break;
			default:        $html .= htmlentities(ucfirst($response), ENT_COMPAT, 'UTF-8'); break;
		}
		return $html;
	}

	/**
	 * Output a response for a multi-option field
	 *
	 * @param   array   $response_array  Response codes
	 * @return  string
	 */
	public static function propercase_multiresponse($response_array)
	{
		$html = '';
		if (count($response_array) == 0)
		{
			$html .= Lang::txt('COM_MEMBERS_FIELD_VALUE_NA');
		}
		else
		{
			for ($i = 0; $i < count($response_array); $i++)
			{
				if ($i > 0)
				{
					$html .= ', ';
				}
				if ($response_array[$i] == 'no')
				{
					$html .= Lang::txt('COM_MEMBERS_FIELD_VALUE_NONE');
				}
				elseif ($response_array[$i] == 'refused')
				{
					$html .= Lang::txt('COM_MEMBERS_FIELD_VALUE_REFUSED');
				}
				else
				{
					$html .= htmlentities(ucfirst($response_array[$i]), ENT_COMPAT, 'UTF-8');
				}
			}
		}
		return $html;
	}

	/**
	 * Obfuscate an email address
	 *
	 * @param   string  $email  Email address
	 * @return  string
	 */
	public static function obfuscate($email)
	{
		$length = strlen($email);
		$obfuscatedEmail = '';
		for ($i = 0; $i < $length; $i++)
		{
			$obfuscatedEmail .= '&#' . ord($email[$i]) . ';';
		}

		return $obfuscatedEmail;
	}

	/**
	 * Transform a date to an epoch
	 *
	 * @param   string   $datestr  Datetime (0000-00-00 00:00:00)
	 * @return  integer
	 */
	public static function date2epoch($datestr)
	{
		if (empty($datestr))
		{
			return null;
		}
		list ($date, $time) = explode(' ', $datestr);
		list ($y, $m, $d) = explode('-', $date);
		list ($h, $i, $s) = explode(':', $time);
		return (mktime($h, $i, $s, $m, $d, $y));
	}

	/**
	 * Format a value
	 *
	 * @param   number   $value   Value to format
	 * @param   integer  $format  Format to apply
	 * @return  mixed
	 */
	public static function valformat($value, $format)
	{
		if ($format == 1)
		{
			return(number_format($value));
		}
		elseif ($format == 2 || $format == 3)
		{
			if ($format == 2)
			{
				$min = round($value / 60);
			}
			else
			{
				$min = floor($value / 60);
				$sec = $value - ($min * 60);
			}
			$hr = floor($min / 60);
			$min -= ($hr * 60);
			$day = floor($hr / 24);
			$hr -= ($day * 24);
			if ($day == 1)
			{
				$day = '1 day, ';
			}
			elseif ($day > 1)
			{
				$day = number_format($day) . ' days, ';
			}
			else
			{
				$day = '';
			}
			if ($format == 2)
			{
				return(sprintf("%s%d:%02d", $day, $hr, $min));
			}
			else
			{
				return(sprintf("%s%d:%02d:%02d", $day, $hr, $min, $sec));
			}
		}
		else
		{
			return($value);
		}
	}
}

