<?php
/**
 * @package    hubzero-cms
 * @copyright  Copyright (c) 2005-2020 The Regents of the University of California.
 * @license    http://opensource.org/licenses/MIT MIT
 */

namespace Components\Members\Site\Controllers;

use Hubzero\Session\Helper as SessionHelper;
use Hubzero\Component\SiteController;
use Components\Members\Models\Profile\Field;
use Components\Members\Models\Profile;
use Components\Members\Models\Member;
use Components\Members\Helpers\Filters;
use Component;
use Document;
use Pathway;
use Request;
use Config;
use Notify;
use Route;
use Cache;
use Event;
use Lang;
use User;
use Date;
use App;

include_once dirname(dirname(__DIR__)) . DS . 'models' . DS . 'registration.php';
include_once dirname(dirname(__DIR__)) . DS . 'models' . DS . 'member.php';
include_once dirname(dirname(__DIR__)) . DS . 'helpers' . DS . 'filters.php';

/**
 * Members controller class for profiles
 */
class Profiles extends SiteController
{
	/**
	 * Execute a task
	 *
	 * @return  void
	 */
	public function execute()
	{
		// Get the view
		$this->_view = strtolower(Request::getCmd('view', 'members'));

		// Get The task
		$task = strtolower(Request::getCmd('task', ''));

		$id = Request::getInt('id', 0);
		if ($id && !$task)
		{
			Request::setVar('task', 'view');
		}

		//$this->registerTask('__default', 'browse');
		$this->registerTask('promo-opt-out', 'incremOptOut');
		$this->registerTask('queryorganization', 'getOrganizations');

		parent::execute();
	}

	/**
	 * Opt out of a promotion
	 *
	 * @return  void
	 */
	public function incremOptOutTask()
	{
		if (!User::get('id'))
		{
			return;
		}

		require_once dirname(dirname(__DIR__)) . '/models/incremental/awards.php';
		require_once dirname(dirname(__DIR__)) . '/models/incremental/groups.php';
		require_once dirname(dirname(__DIR__)) . '/models/incremental/options.php';

		$ia = new \Components\Members\Models\Incremental\Awards($profile);
		$ia->optOut();

		App::redirect(
			Route::url(User::link() . '&active=profile'),
			Lang::txt('You have been successfully opted out of this promotion.'),
			'passed'
		);
	}

	/**
	 * Return results for autocompleter
	 *
	 * @return  void
	 */
	public function autocompleteTask()
	{
		if (User::isGuest())
		{
			return;
		}

		$restrict = '';

		$referrer = Request::getString('HTTP_REFERER', null, 'server');
		if ($referrer && preg_match('/members\/\d+\/messages/i', $referrer))
		{
			if (!User::authorise('core.admin', $this->_option)
			 && !User::authorise('core.manage', $this->_option))
			{
				switch ($this->config->get('user_messaging'))
				{
					case 2:
						$restrict = " AND u.access=1";
					break;

					case 1:
					default:
						$profile = User::groups();
						$usersgroups = array();
						if (!empty($xgroups))
						{
							foreach ($xgroups as $group)
							{
								if ($group->regconfirmed)
								{
									$usersgroups[] = $group->gidNumber;
								}
							}
						}

						$members = null;
						if (!empty($usersgroups))
						{
							$query = "SELECT DISTINCT uidNumber
									FROM `#__xgroups_members`
									WHERE gidNumber IN (" . implode(',', $usersgroups) . ")";

							$this->database->setQuery($query);
							$members = $this->database->loadColumn();
						}

						if (!$members || empty($members))
						{
							$members = array(User::get('id'));
						}

						$restrict = " AND u.id IN (" . implode(',', $members) . ")";
					break;
				}
			}
		}

		$filters = array();
		$filters['limit']  = 20;
		$filters['start']  = 0;
		$filters['search'] = strtolower(trim(Request::getString('value', '')));
		$originalQuery = $filters['search'];

		// match against orcid id
		if (preg_match('/\d{4}-\d{4}-\d{4}-\d{4}/', $filters['search']))
		{
			$query = "SELECT u.id, u.name, u.username, u.access
					FROM `#__users` AS u
					WHERE u.block = 0 AND orcid= " . $this->database->quote($filters['search']) . " AND u.activation>0 $restrict
					ORDER BY u.name ASC
					LIMIT " . $filters['start'] . "," . $filters['limit'];
		}
		else
		{
			// add trailing wildcard
			//$filters['search'] = $filters['search'] . '*';

			// match member names on all three name parts
			//$match = "MATCH(u.givenName,u.middleName,u.surname) AGAINST(" . $this->database->quote($filters['search']) . " IN BOOLEAN MODE)";
			if (strstr($filters['search'], ' '))
			{
				$parts = explode(' ', $filters['search']);

				// Someone typed a name with a space so it could be "first middle last", "first last", or "first middle"
				$match = "(LOWER(u.name) LIKE " . $this->database->quote('%' . strtolower($filters['search']) . '%') . "
					OR (LOWER(u.givenName) LIKE " . $this->database->quote('%' . strtolower($parts[0]) . '%') . "
					AND (LOWER(u.middleName) LIKE " . $this->database->quote('%' . strtolower($parts[1]) . '%') . " OR LOWER(u.surname) LIKE " . $this->database->quote('%' . strtolower($parts[1]) . '%') . ")))";
			}
			else
			{
				$match = "(LOWER(u.name) LIKE " . $this->database->quote('%' . strtolower($filters['search']) . '%') . "
					OR LOWER(u.givenName) LIKE " . $this->database->quote('%' . strtolower($filters['search']) . '%') . "
					OR LOWER(u.surname) LIKE " . $this->database->quote('%' . strtolower($filters['search']) . '%') . ")";
			}
			$query = "SELECT u.id, u.name, u.username, u.access, $match as rel
					FROM `#__users` AS u
					WHERE $match AND u.block=0 AND u.activation>0 AND u.email NOT LIKE '%@invalid' $restrict
					ORDER BY rel DESC, u.name ASC
					LIMIT " . $filters['start'] . "," . $filters['limit'];
		}

		$this->database->setQuery($query);
		$rows = $this->database->loadObjectList();

		// Output search results in JSON format
		$json = array();
		if (count($rows) > 0)
		{
			foreach ($rows as $row)
			{
				$user = Member::blank()->set($row);

				$obj = array();
				$obj['id']       = $user->get('id');
				$obj['name']     = $user->get('name');
				$obj['username'] = $user->get('username');
				$obj['org']      = (in_array($user->get('access'), User::getAuthorisedViewLevels()) ? $user->get('organization', '') : '');
				$obj['picture']  = $user->picture();

				$json[] = $obj;
			}
		}

		// formats names in the autocompleter
		if (!\Hubzero\Utility\Validate::email($originalQuery) && str_word_count($originalQuery) >= 2)
		{
			$originalQuery = ucwords($originalQuery);
		}

		//original query
		$obj = array();
		$obj['name'] = $originalQuery;
		$obj['id'] = $originalQuery;
		$obj['org'] = '';
		$obj['picture'] = '';
		$obj['orig'] = true;

		//add back original query
		// [!] Removing. Seems to confuse people.
		//array_unshift($json, $obj);

		echo json_encode($json);
	}

	/**
	 * Display main page
	 *
	 * @return  void
	 */
	public function displayTask()
	{
		$title = Lang::txt('COM_MEMBERS');

		// Set the page title
		Document::setTitle($title);

		// Set the document pathway
		if (Pathway::count() <= 0)
		{
			Pathway::append(
				Lang::txt(strtoupper($this->_option)),
				'index.php?option=' . $this->_option
			);
		}

		// Output view
		$this->view
			->set('title', $title)
			->display();
	}

	/**
	 * Display a list of members
	 *
	 * @return  void
	 */
	public function browseTask()
	{
		// Get all the fields we can use on this page
		$fields = Field::all()
			->whereIn('action_browse', User::getAuthorisedViewLevels())
			->where('type', '!=', 'tags')
			->ordered()
			->rows();

		$q = (array)Request::getArray('q', array());

		$filters = array(
			'search'   => Request::getString('search', ''),
			'q'        => array(),
			'tags'     => Request::getString('tags', ''),
			'sort'     => strtolower(Request::getWord('sort', 'name')),
			'sort_Dir' => strtolower(Request::getWord('sort_Dir', 'asc'))
		);

		// If we have a search and it's not an array (i.e. it's coming in fresh with this request)
		if ($filters['search'])
		{
			$q[] = array(
				'field'    => 'search',
				'human_field' => Lang::txt('COM_MEMBERS_SEARCH'),
				'operator' => 'like',
				'value'    => $filters['search']
			);
		}

		// Make sure sort direction is valid
		if (!in_array($filters['sort_Dir'], array('asc', 'desc')))
		{
			$filters['sort_Dir'] = 'asc';
		}

		// Make sure sort value is valid
		$searches = array();
		$fieldnames = array('surname', 'name');
		foreach ($fields as $field)
		{
			if (in_array($field->get('type'), array('text', 'textarea', 'orcid', 'address')))
			{
				$searches[] = $field->get('name');
			}
			$fieldnames[] = $field->get('name');
		}

		if (!in_array($filters['sort'], $fieldnames))
		{
			$filters['sort'] = 'name';
		}

		$filters['sqlsort'] = $filters['sort'];

		$sortFound = false;
		if ($filters['sort'] == 'name')
		{
			$sortFound = true;
			$filters['sqlsort'] = 'surname';
		}

		// Process incoming filters
		foreach ($q as $key => $val)
		{
			if (!is_int($key))
			{
				if (!$val)
				{
					continue;
				}

				$val = array(
					'field'    => $key,
					'operator' => 'e',
					'value'    => $val
				);
			}

			if (!isset($val['field']) || !isset($val['operator']) || !isset($val['value']))
			{
				continue;
			}

			$val['field']          = preg_replace('/[^0-9a-zA-z\-_\.]/i', '', $val['field']);
			$val['o']              = Filters::translateOperator($val['operator']);
			$val['human_operator'] = Filters::mapOperator($val['o']);
			$val['human_value']    = $val['value'];

			foreach ($fields as $field)
			{
				if ($field->get('name') != $val['field'])
				{
					continue;
				}

				$val['human_field'] = $field->get('label');

				$options = $field->options;

				// Text-based field
				if ($options->count() <= 0)
				{
					continue;
				}

				// Multi-value field (checkboxes)
				if (is_array($val['value']))
				{
					$val['human_value'] = array();
					foreach ($val['value'] as $value)
					{
						$multi = $val;
						$multi['value'] = $value;
						$multi['human_value'] = $value;
						foreach ($field->options as $option)
						{
							if ($option->get('value') == $value)
							{
								//$multi['human_value'] = $option->get('label');
								$val['human_value'][] = $option->get('label');
								break;
							}
						}
						//$filters['q'][] = $multi;
					}
				}
				// Single-value field (select list)
				else
				{
					foreach ($field->options as $option)
					{
						if ($option->get('value') == $val['value'])
						{
							$val['human_value'] = $option->get('label');
						}
					}
				}
			}

			// No associated profile field was found
			if (!isset($val['human_field']))// || is_array($val['value']))
			{
				continue;
			}

			$filters['q'][] = $val;
		}

		// Distil down the results to only unique filters
		$filters['q'] = array_map('unserialize', array_unique(array_map('serialize', $filters['q'])));

		// Build query
		$entries = Member::all();

		$a = $entries->getTableName();
		$b = Profile::blank()->getTableName();
		$access = User::getAuthorisedViewLevels();
		$access[] = 0;

		$entries
			->select($a . '.*')
			->including(['profiles', function ($profile) use ($access) {
				$profile
					->select('*')
					->whereIn('access', $access);
			}])
			->whereEquals($a . '.block', 0)
			->where($a . '.activation', '>', 0)
			->where($a . '.sendEmail', '>=', 0)
			->where($a . '.email', 'NOT LIKE', '%@invalid%')
			->where($a . '.approved', '>', 0);

		// Tags
		if ($filters['tags'])
		{
			$to = '#__tags_object';
			$t  = '#__tags';

			$tags = explode(',', $filters['tags']);
			$tags = array_map('trim', $tags);

			$entries->select($a . '.*, COUNT(DISTINCT ' . $to . '.tagid) AS uniques');
			$entries->join($to, $to . '.objectid', $a . '.id', 'inner');
			$entries->join($t, $t . '.id', $to . '.tagid', 'inner');

			$entries->whereIn($t . '.tag', $tags);
			$entries->whereEquals($to . '.tbl', 'xprofiles');

			$entries->having('uniques', '=', count($tags));
			$entries->group($a . '.id');
		}

		$db = App::get('db');
		$i = 1;

		if ($filters['q'])
		{
			foreach ($filters['q'] as $q)
			{
				if ($q['field'] == 'search')
				{
					if ($q['o'] == 'LIKE')
					{
						// Explode multiple words into array
						$search = explode(' ', $q['value']);

						foreach ($search as $v => $term)
						{
							$term = '%' . trim($term) . '%';

							if ($v == 0)
							{
								$entries->where($a . '.name', ' ' . $q['o'] . ' ', strtolower((string)$term), 'and', 1);
							}
							else
							{
								$entries->orWhere($a . '.name', ' ' . $q['o'] . ' ', strtolower((string)$term), 1);
							}

							foreach ($searches as $z => $searching)
							{
								$entries->joinRaw(
									$b . ' AS s' . ($v . '_' . $z),
									's' . ($v . '_' . $z) . '.user_id=' . $a . '.id AND s' . ($v . '_' . $z) . '.profile_key=' . $db->quote($searching),
									'left'
								);

								$entries->orWhere('s' . ($v . '_' . $z) . '.profile_value', ' ' . $q['o'] . ' ', strtolower((string)$term), 1);
							}
						}
						$entries->resetDepth();
					}
					else
					{
						$entries->where($a . '.name', ' ' . $q['o'] . ' ', (string)$q['value'], 1);

						foreach ($searches as $searching)
						{
							$entries->joinRaw(
								$b . ' AS s' . $i,
								's' . $i . '.user_id=' . $a . '.id AND s' . $i . '.profile_key=' . $db->quote($searching),
								'left'
							);

							$entries->orWhere('s' . $i . '.profile_value', ' ' . $q['o'] . ' ', (string)$q['value'], 1);
						}
						$entries->resetDepth();
					}

					continue;
				}

				if ($q['o'] == 'LIKE')
				{
					$q['value'] = '%' . $q['value'] . '%';
				}

				if (in_array($q['o'], array('>', '>=', '<', '<=')) && is_numeric($q['value']))
				{
					$entries->joinRaw(
						$b . ' AS t' . $i,
						't' . $i . '.user_id=' . $a . '.id AND t' . $i . '.profile_key=' . $db->quote($q['field']) . ' AND t' . $i . '.profile_value ' . $q['o'] . ' ' . (int)$q['value'],
						'inner'
					);
				}
				else if (is_array($q['value']))
				{
					foreach ($q['value'] as $key => $val)
					{
						$q['value'][$key] = $db->quote($val);
					}
					$entries->joinRaw(
						$b . ' AS t' . $i,
						't' . $i . '.user_id=' . $a . '.id AND t' . $i . '.profile_key=' . $db->quote($q['field']) . ' AND t' . $i . '.profile_value IN (' . implode(', ', $q['value']) . ')',
						'inner'
					);
				}
				else
				{
					$entries->joinRaw(
						$b . ' AS t' . $i,
						't' . $i . '.user_id=' . $a . '.id AND t' . $i . '.profile_key=' . $db->quote($q['field']) . ' AND t' . $i . '.profile_value ' . $q['o'] . ' ' . $db->quote($q['value']),
						'inner'
					);
				}

				if ($filters['sort'] == $q['field'])
				{
					$filters['sqlsort'] = 't' . $i . '.profile_value';
					$sortFound = true;
				}

				$entries->whereIn('t' . $i . '.access', $access);
				$i++;
			}
		}

		// If the sort value wasn't already an applied filter
		// we need to join on the profiles table to be able to sort by that value
		if (!$sortFound)
		{
			$entries->joinRaw($b . ' AS t' . $i, 't' . $i . '.user_id=' . $a . '.id AND t' . $i . '.profile_key=' . $db->quote($filters['sort']), 'inner');
			$filters['sqlsort'] = 't' . $i . '.profile_value';
		}

		$entries->whereIn($a . '.access', $access);

		$rows = $entries
			->order($filters['sqlsort'], $filters['sort_Dir'])
			->paginated('limitstart', 'limit')
			->rows();

		// Set the page title
		$title  = Lang::txt('COM_MEMBERS');
		$title .= ($this->_task) ? ': ' . Lang::txt(strtoupper($this->_task)) : '';

		Document::setTitle($title);

		// Set the document pathway
		if (Pathway::count() <= 0)
		{
			Pathway::append(
				Lang::txt(strtoupper($this->_name)),
				'index.php?option=' . $this->_option
			);
		}
		// Add to the pathway
		Pathway::append(
			Lang::txt(strtoupper($this->_task)),
			'index.php?option=' . $this->_option . '&task=' . $this->_task
		);

		// Get stats
		if (!($stats = Cache::get('members.stats')))
		{
			$stats = $this->stats();

			Cache::put('members.stats', $stats, intval($this->config->get('cache_time', 15)));
		}

		// Instantiate the view
		$this->view
			->set('config', $this->config)
			->set('fields', $fields)
			->set('filters', $filters)
			->set('title', $title)
			->set('rows', $rows)
			->set('past_day_members', $stats->past_day_members)
			->set('past_month_members', $stats->past_month_members)
			->set('total_members', $stats->total_members)
			->set('total_public_members', $stats->total_public_members)
			->display();
	}

	/**
	 * Retrieves option values for a profile field
	 *
	 * @apiMethod GET
	 * @apiUri    /members/fieldValues
	 * @apiParameter {
	 * 		"name":        "field",
	 * 		"description": "Profile field of interest",
	 * 		"type":        "string",
	 * 		"required":    true,
	 * 		"default":     ""
	 * }
	 * @return  void
	 */
	public function fieldValuesTask()
	{
		$name = Request::getString('field', '');

		$field = Field::all()
			->whereEquals('name', $name)
			->row();

		if (!$field->get('id'))
		{
			$field->set('type', 'text');
			$field->set('name', $name);
		}

		// Create object with values
		$response = new \stdClass();
		$response->type = $field->get('type');

		$values = array();

		if ($field->get('type') == 'country')
		{
			$countries = \Hubzero\Geocode\Geocode::countries();

			foreach ($countries as $option)
			{
				// Create a new option object based on the <option /> element.
				$tmp = new \stdClass;
				$tmp->value = (string) $option->code;
				$tmp->label = trim((string) $option->name);

				// Add the option object to the result set.
				$values[] = $tmp;
			}
		}
		else
		{
			foreach ($field->options()->ordered()->rows() as $option)
			{
				$values[] = $option->toObject();
			}
		}

		$response->values = $values;

		// Return object
		echo json_encode($response);
		exit();
	}

	/**
	 * Calculate stats
	 *
	 * @return  object
	 */
	public function stats()
	{
		$stats = new \stdClass;

		// Get record count of all members
		$stats->total_members = Member::all()
			->whereEquals('block', 0)
			->where('activation', '>', 0)
			->where('approved', '>', 0)
			->total();

		$stats->total_public_members = Member::all()
			->whereEquals('block', 0)
			->where('activation', '>', 0)
			->where('approved', '>', 0)
			->whereEquals('access', 1)
			->total();

		// Get record count of new members in the past day
		$stats->past_day_members = Member::all()
			->where('registerDate', '>', Date::of(strtotime('-1 DAY'))->toSql())
			->total();

		// Get record count of new members in the past month
		$stats->past_month_members = Member::all()
			->where('registerDate', '>', Date::of(strtotime('-1 MONTH'))->toSql())
			->total();

		return $stats;
	}

	/**
	 * A shortcut task for displaying a logged-in user's account page
	 *
	 * @return  void
	 */
	public function myaccountTask()
	{
		if (User::isGuest())
		{
			App::redirect(
				Route::url('index.php?option=com_users&view=login&return=' . base64_encode(Route::url('index.php?option=' . $this->_option . '&task=myaccount', false, true)), false),
				Lang::txt('COM_MEMBERS_NOT_LOGGEDIN'),
				'warning'
			);
		}

		Request::setVar('id', User::get('id'));

		return $this->viewTask();
	}

	/**
	 * Display a user profile
	 *
	 * @return  void
	 */
	public function viewTask()
	{
		// Incoming
		$id  = Request::getInt('id', 0);
		$tab = Request::getString('active');  // The active tab (section)

		// Get the member's info
		if (is_numeric($id))
		{
			$profile = Member::oneOrNew(intval($id));
		}
		else
		{
			$profile = Member::oneByUsername((string)$id);
		}

		// Ensure we have a member
		if (!$profile->get('id'))
		{
			App::abort(404, Lang::txt('COM_MEMBERS_NOT_FOUND'));
		}

		// Make sure member is approved
		// Removed the !$profile->get('approved') check from conditional since the Unapproved System plugin will handle this check.
		if ($profile->get('block'))
		{
			App::abort(404, Lang::txt('COM_MEMBERS_NOT_FOUND'));
		}

		// Check subscription to Employer Services
		//   NOTE: This must occur after the initial plugins import and
		//   do not specifically call Plugin::import('members', 'resume');
		//   Doing so can have negative affects.
		/*if ($this->config->get('employeraccess') && $tab == 'resume')
		{
			$checkemp   = Event::trigger('members.isEmployer', array());
			$emp        = is_array($checkemp) ? $checkemp[0] : 0;
			$this->view->authorized = $emp ? 1 : $this->view->authorized;
		}*/

		// Check if the profile is public/private and the user has access
		if (User::get('id') != $profile->get('id') && !in_array($profile->get('access'), User::getAuthorisedViewLevels()))
		{
			// Check if they're logged in
			if (User::isGuest())
			{
				$rtrn = Request::getString('REQUEST_URI', Route::url($profile->link()), 'server');

				App::redirect(
					Route::url('index.php?option=com_users&view=login&return=' . base64_encode($rtrn))
				);
			}

			App::abort(403, Lang::txt('COM_MEMBERS_NOT_PUBLIC'));
		}

		// Check if unconfirmed

		$loggedInUserId = User::getInstance()->get('id');
		if ($profile->get('activation') < 1 && $loggedInUserId != $profile->get('id') && (User::isGuest() || !User::authorise('core.manage', $this->_option)))
		{
			App::abort(404, Lang::txt('COM_MEMBERS_NOT_FOUND'));

			// Offer explanation and eternal redemption to the user, instead of leaving them high and dry
			/*
			$this->view
				->set('title', Lang::txt('COM_MEMBERS_REGISTER_UNCONFIRMED'))
				->set('email', $profile->get('email'))
				->set('sitename', Config::get('sitename'))
				->set('return', urlencode($rtrn))
				->setErrors($this->getErrors())
				->setName('register')
				->setLayout('unconfirmed')
				->display();
			return;
			*/
		}

		// Check for name
		if (!$profile->get('name'))
		{
			$name  = $profile->get('givenName') . ' ';
			$name .= ($profile->get('middleName')) ? $profile->get('middleName') . ' ' : '';
			$name .= $profile->get('surname');

			$profile->set('name', $name);
		}

		// Trigger the functions that return the areas we'll be using
		$cats = Event::trigger('members.onMembersAreas', array(User::getInstance(), $profile));

		$available = array();

		foreach ($cats as $cat)
		{
			$name = key($cat);

			if ($name != '')
			{
				$available[] = $name;
			}
		}

		// If no active tab specified, default to the first available
		if (!in_array($tab, $available) && isset($available[0]))
		{
			$tab = $available[0];
		}

		// Get the sections
		$sections = Event::trigger('members.onMembers', array(User::getInstance(), $profile, $this->_option, array($tab)));

		// Build the page title
		$title  = Lang::txt(strtoupper($this->_option));
		$title .= ($this->_task) ? ': ' . Lang::txt(strtoupper($this->_task)) : '';

		// Set the page title
		Document::setTitle($title . ': ' . stripslashes($profile->get('name')));

		// Set the pathway
		if (Pathway::count() <= 0)
		{
			Pathway::append(
				Lang::txt(strtoupper($this->_option)),
				'index.php?option=' . $this->_option
			);
		}
		Pathway::append(
			stripslashes($profile->get('name')),
			'index.php?option=' . $this->_option . '&id=' . $profile->get('id')
		);
		foreach ($cats as $k => $c)
		{
			if ($tab == key($c))
			{
				Pathway::append(
					$c[$tab],
					'index.php?option=' . $this->_option . '&id=' . $profile->get('id') . '&active=' . $tab
				);
				break;
			}
		}

		// Output HTML
		$this->view
			->set('config', $this->config)
			->set('active', $tab)
			->set('profile', $profile)
			->set('title', $title)
			->set('cats', $cats)
			->set('sections', $sections)
			->set('overwrite_content', '')
			->setErrors($this->getErrors())
			->setLayout('view')
			->display();
	}

	/**
	 * Show a form for changing user password
	 *
	 * @return  void
	 */
	public function changepasswordTask()
	{
		// Check if they're logged in
		if (User::isGuest())
		{
			$rtrn = Request::getString('REQUEST_URI', Route::url('index.php?option=' . $this->_controller . '&task=changepassword', false, true), 'server');

			App::redirect(
				Route::url('index.php?option=com_users&view=login&return=' . base64_encode($rtrn), false)
			);
		}

		// Incoming
		$id = Request::getInt('id', 0);
		$id = $id ?: User::get('id');

		// Ensure we have an ID
		if (!$id)
		{
			App::abort(404, Lang::txt('COM_MEMBERS_NO_ID'));
		}

		// Check authorization
		if (!User::authorise('core.manage', $this->_option) && User::get('id') != $id)
		{
			App::abort(403, Lang::txt('MEMBERS_NOT_AUTH'));
		}

		// Initiate profile class
		$profile = Member::oneOrFail($id);

		// Ensure we have a member
		if (!$profile->get('id'))
		{
			App::abort(404, Lang::txt('COM_MEMBERS_NOT_FOUND'));
		}

		// Set the page title
		$title  = Lang::txt(strtoupper($this->_option));
		$title .= ($this->_task) ? ': ' . Lang::txt(strtoupper($this->_option . '_' . $this->_task)) : '';

		Document::setTitle($title);

		// Set the pathway
		if (Pathway::count() <= 0)
		{
			Pathway::append(
				Lang::txt(strtoupper($this->_option)),
				'index.php?option=' . $this->_option
			);
		}
		Pathway::append(
			stripslashes($profile->get('name')),
			'index.php?option=' . $this->_option . '&id=' . $profile->get('id')
		);
		Pathway::append(
			Lang::txt('COM_MEMBERS_' . strtoupper($this->_task)),
			'index.php?option=' . $this->_option . '&id=' . $profile->get('id') . '&task=' . $this->_task
		);

		// Load some needed libraries
		if (\Hubzero\User\Helper::isXDomainUser(User::get('id')))
		{
			App::abort(403, Lang::txt('COM_MEMBERS_PASS_CHANGE_LINKED_ACCOUNT'));
		}

		// Incoming data
		$change   = Request::getString('change', '', 'post');
		$oldpass  = Request::getString('oldpass', '', 'post');
		$newpass  = Request::getString('newpass', '', 'post');
		$newpass2 = Request::getString('newpass2', '', 'post');
		$message  = Request::getString('message', '');

		if (!empty($message))
		{
			$this->setError($message);
		}

		$this->view->title = $title;
		$this->view->profile = $profile;
		$this->view->change = $change;
		$this->view->oldpass = $oldpass;
		$this->view->newpass = $newpass;
		$this->view->newpass2 = $newpass2;
		$this->view->validated = true;

		$password_rules = \Hubzero\Password\Rule::all()
			->whereEquals('enabled', 1)
			->rows();

		$this->view->password_rules = array();

		foreach ($password_rules as $rule)
		{
			if (!empty($rule['description']))
			{
				$this->view->password_rules[] = $rule['description'];
			}
		}

		if (!empty($newpass))
		{
			$msg = \Hubzero\Password\Rule::verify($newpass, $password_rules, $profile->get('username'));
		}
		else
		{
			$msg = array();
		}

		// Blank form request (no data submitted)
		if (empty($change))
		{
			$this->view
				->setErrors($this->getErrors())
				->display();
			return;
		}

		$passrules = false;

		// Check if they have a previously set pass
		// If not, then they created an account via a 3rd-party
		// authenticator and are setting a new local password
		$hzup = \Hubzero\User\Password::getInstance($profile->get('id'));

		if (($hzup && !empty($hzup->passhash)) && !\Hubzero\User\Password::passwordMatches($profile->get('id'), $oldpass, true))
		{
			$this->setError(Lang::txt('COM_MEMBERS_PASS_INCORRECT'));
		}
		elseif (!$newpass || !$newpass2)
		{
			$this->setError(Lang::txt('COM_MEMBERS_PASS_MUST_BE_ENTERED_TWICE'));
		}
		elseif ($newpass != $newpass2)
		{
			$this->setError(Lang::txt('COM_MEMBERS_PASS_NEW_CONFIRMATION_MISMATCH'));
		}
		elseif (($hzup && !empty($hzup->passhash)) && $oldpass == $newpass)
		{
			// make sure the current password and new password are not the same
			// this should really be done in the password rules validation step
			$this->setError(Lang::txt('Your new password must be different from your current password'));
		}
		elseif (!empty($msg))
		{
			$this->setError(Lang::txt('Password does not meet site password requirements. Please choose a password meeting all the requirements listed below.'));
			$this->view->set('validated', $msg);
			$passrules = true;
		}

		if ($this->getError())
		{
			$change = array();
			$change['_missing']['password'] = $this->getError();

			if (!empty($msg) && $passrules)
			{
				$change['_missing']['password'] .= '<ul>';
				foreach ($msg as $m)
				{
					$change['_missing']['password'] .= '<li>';
					$change['_missing']['password'] .= $m;
					$change['_missing']['password'] .= '</li>';
				}
				$change['_missing']['password'] .= '</ul>';
			}

			if (Request::getInt('no_html', 0))
			{
				echo json_encode($change);
				exit();
			}
			else
			{
				$this->view
					->setError($this->getError())
					->display();
				return;
			}
		}

		// Encrypt the password and update the profile
		$result = \Hubzero\User\Password::changePassword($profile->get('id'), $newpass);

		// Save the changes
		if (!$result)
		{
			$this->view
				->setError(Lang::txt('MEMBERS_PASS_CHANGE_FAILED'))
				->display();
			return;
		}

		Event::trigger('onUserAfterChangePassword', array($profile->toArray()));

		// Redirect user back to main account page
		$return = base64_decode(Request::getString('return', '', 'method', 'base64'));
		$this->_redirect = $return ? $return : Route::url('index.php?option=' . $this->_option . '&id=' . $id);
		$session = App::get('session');

		// Redirect user back to main account page
		if (Request::getInt('no_html', 0))
		{
			if ($session->get('badpassword', '0') || $session->get('expiredpassword', '0'))
			{
				$session->set('badpassword', '0');
				$session->set('expiredpassword', '0');
			}

			echo json_encode(array("success" => true));
			exit();
		}
		else
		{
			if ($session->get('badpassword', '0') || $session->get('expiredpassword', '0'))
			{
				$session->set('badpassword', '0');
				$session->set('expiredpassword', '0');
			}
		}
	}

	/**
	 * Show a form for raising a user's allowed sessions, storage, etc.
	 *
	 * @return  void
	 */
	public function raiselimitTask()
	{
		// Check if they're logged in
		if (User::isGuest())
		{
			$rtrn = Request::getString('REQUEST_URI', Route::url('index.php?option=' . $this->_controller . '&task=raiselimit', false, true), 'server');

			App::redirect(
				Route::url('index.php?option=com_users&view=login&return=' . base64_encode($rtrn), false)
			);
		}

		// Incoming
		$id = Request::getInt('id', 0);

		// Initiate profile class
		$profile = Member::oneOrFail($id);

		// Ensure we have a member
		if (!$profile->get('id'))
		{
			App::abort(404, Lang::txt('MEMBERS_NOT_FOUND'));
		}

		// Check authorization
		if (!User::authorise('core.manage', $this->_option) && User::get('id') != $id)
		{
			App::abort(403, Lang::txt('COM_MEMBERS_NOT_AUTH'));
		}

		// Set the page title
		$title  = Lang::txt(strtoupper($this->_option));
		$title .= ($this->_task) ? ': ' . Lang::txt(strtoupper($this->_task)) : '';

		Document::setTitle($title);

		// Set the pathway
		if (Pathway::count() <= 0)
		{
			Pathway::append(
				Lang::txt(strtoupper($this->_option)),
				'index.php?option=' . $this->_option
			);
		}
		Pathway::append(
			stripslashes($profile->get('name')),
			'index.php?option=' . $this->_option . '&id=' . $profile->get('id')
		);
		Pathway::append(
			Lang::txt(strtoupper($this->_task)),
			'index.php?option=' . $this->_option . '&id=' . $profile->get('id') . '&task=' . $this->_task
		);

		// Incoming
		$request = Request::getString('request', null, 'post');
		$raiselimit = Request::getString('raiselimit', null, 'post');

		if ($raiselimit)
		{
			$k = '';
			if (is_array($raiselimit))
			{
				$k = key($raiselimit);
			}

			switch ($k)
			{
				case 'sessions':
					include_once Component::path('com_tools') . DS . 'tables' . DS . 'preferences.php';

					$preferences = new \Components\Tools\Tables\Preferences($this->database);
					$preferences->loadByUser($profile->get('id'));
					if (!$preferences || !$preferences->id)
					{
						$default = $preferences->find('one', array('alias' => 'default'));
						$preferences->user_id  = $profile->get('id');
						$preferences->class_id = $default->id;
						$preferences->jobs     = $default->jobs;
						$preferences->store();
					}

					$oldlimit = $preferences->jobs;
					$newlimit = $oldlimit + 3;

					$resourcemessage = 'session limit from '. $oldlimit .' to '. $newlimit .' sessions ';

					if ($this->view->authorized == 'admin')
					{
						$preferences->class_id = 0;
						$preferences->jobs     = $newlimit;
						$preferences->store();
						$resourcemessage = 'The session limit for [' . $profile->get('username') . '] has been raised from ' . $oldlimit . ' to ' . $newlimit . ' sessions.';
					}
					else if ($request === null)
					{
						$this->view
							->set('title', $title)
							->set('resource', $k)
							->setLayout('select')
							->display();
						return;
					}
				break;

				case 'storage':
					$oldlimit = 'unknown'; // $profile->get('quota');
					$newlimit = 'unknown'; // $profile->get('quota') + 100;

					$resourcemessage = ' storage limit has been raised from '. $oldlimit .' to '. $newlimit .'.';

					if (User::authorise('core.manage', $this->_option))
					{
						$resourcemessage = 'The storage limit for [' . $profile->get('username') . '] has been raised from '. $oldlimit .' to '. $newlimit .'.';
					}
					else
					{
						$this->view
							->set('title', $title)
							->set('resource', $k)
							->setLayout('select')
							->display();
						return;
					}
				break;

				case 'meetings':
					$oldlimit = 'unknown'; // $profile->get('max_meetings');
					$newlimit = 'unknown'; // $profile->get('max_meetings') + 3;

					$resourcemessage = ' meeting limit has been raised from '. $oldlimit .' to '. $newlimit .'.';

					if (User::authorise('core.manage', $this->_option))
					{
						// $profile->set('max_meetings', $newlimit);
						// $profile->update();

						$resourcemessage = 'The meeting limit for [' . $profile->get('username') . '] has been raised from '. $oldlimit .' to '. $newlimit .'.';
					}
					else
					{
						$this->view
							->set('title', $title)
							->set('resource', $k)
							->setLayout('select')
							->display();
						return;
					}
				break;

				default:
					// Show limit selection form
					$this->view
						->set('title', $title)
						->display();
					return;
				break;
			}
		}

		// Do we need to email admin?
		if ($request !== null && !empty($resourcemessage))
		{
			$sitename =  Config::get('sitename');
			$live_site = rtrim(Request::base(), '/');

			// Email subject
			$subject = $hubName . " Account Resource Request";

			// Email message
			$message = 'Name: ' . $profile->get('name');
			if ($profile->get('organization'))
			{
				$message .= " / " . $profile->get('organization');
			}
			$message .= "\r\n";
			$message .= "Email: " . $profile->get('email') . "\r\n";
			$message .= "Username: " . $profile->get('username') . "\r\n\r\n";
			$message .= 'Has requested an increases in their ' . $hubName;
			$message .= $resourcemessage . "\r\n\r\n";
			$message .= "Reason: ";
			if (empty($request))
			{
				$message .= "NONE GIVEN\r\n\r\n";
			}
			else
			{
				$message .= $request . "\r\n\r\n";
			}
			$message .= "Click the following link to grant this request:\r\n";

			$sef = Route::url('index.php?option=' . $this->_option . '&id=' . $profile->get('id') . '&task=' . $this->_task);
			$url = Request::base() . ltrim($sef, DS);

			$message .= $url . "\r\n\r\n";
			$message .= "Click the following link to review this user's account:\r\n";

			$sef = Route::url('index.php?option=' . $this->_option . '&id=' . $profile->get('id'));
			$url = Request::base() . ltrim($sef, DS);

			$message .= $url . "\r\n";

			$msg = new \Hubzero\Mail\Message();
			$msg->setSubject($subject)
			    ->addTo(Config::get('mailfrom'))
			    ->addFrom(Config::get('mailfrom'), Config::get('sitename') . ' Administrator')
			    ->addHeader('X-Component', $this->_option)
			    ->setBody($message);

			// Send an e-mail to admin
			if (!$msg->send())
			{
				return App::abort(500, 'xHUB Internal Error: Error mailing resource request to site administrator(s).');
			}

			// Output the view
			$this->view
				->set('resourcemessage', $resourcemessage)
				->setLayout('success')
				->display();
			return;
		}
		else if (User::authorise('core.manage', $this->_option) && !empty($resourcemessage))
		{
			// Output the view
			$this->view
				->set('resourcemessage', $resourcemessage)
				->setLayout('success')
				->display();
			return;
		}

		// Output the view
		$this->view
			->set('resource', null)
			->set('title', $title)
			->display();
	}

	/**
	 * Show a form for editing a profile
	 *
	 * @param   object  $profile  Profile
	 * @return  void
	 */
	public function editTask($profile=null)
	{
		// Incoming
		$id = Request::getInt('id', 0);

		// Check if they're logged in
		if (User::isGuest())
		{
			$rtrn = Request::getString('REQUEST_URI', Route::url('index.php?option=' . $this->_controller . '&task=activity', false, true), 'server');
			App::redirect(
				Route::url('index.php?option=com_users&view=login&return=' . base64_encode($rtrn), false)
			);
		}

		// Ensure we have an ID
		if (!$id)
		{
			App::abort(404, Lang::txt('COM_MEMBERS_NO_ID'));
		}

		// Check authorization
		if (!User::authorise('core.manage', $this->_option) && $id != User::get('id'))
		{
			App::abort(403, Lang::txt('COM_MEMBERS_NOT_AUTH'));
		}

		// Initiate profile class if we don't already have one and load info
		// Note: if we already have one then we just came from $this->save()
		if (!is_object($profile))
		{
			$profile = Member::oneOrFail($id);
		}

		// Ensure we have a member
		if (!$profile->get('id'))
		{
			App::abort(404, Lang::txt('COM_MEMBERS_NOT_FOUND'));
		}

		// Get the user's interests (tags)
		$mt = new \Components\Members\Models\Tags($id);
		$this->view->tags = $mt->render('string');

		// Set the page title
		$title  = Lang::txt(strtoupper($this->_option));
		$title .= ($this->_task) ? ': ' . Lang::txt(strtoupper($this->_task)) : '';

		Document::setTitle($title);

		// Set the pathway
		if (Pathway::count() <= 0)
		{
			Pathway::append(
				Lang::txt(strtoupper($this->_option)),
				'index.php?option=' . $this->_option
			);
		}
		Pathway::append(
			stripslashes($profile->get('name')),
			'index.php?option=' . $this->_option . '&id=' . $profile->get('id')
		);
		Pathway::append(
			Lang::txt(strtoupper($this->_task)),
			'index.php?option=' . $this->_option . '&id=' . $profile->get('id') . '&task=' . $this->_task
		);

		foreach ($this->getErrors() as $error)
		{
			Notify::error($error);
		}

		$fields = Field::all()
			->including(['options', function ($option){
				$option
					->select('*')
					->ordered();
			}])
			->where('action_edit', '!=', Field::STATE_HIDDEN)
			->ordered()
			->rows();

		// Output HTML
		$this->view
			->set('title', $title)
			->set('profile', $profile)
			->set('fields', $fields)
			->setLayout('edit')
			->display();
	}

	/**
	 * Save changes to a profile
	 * Outputs JSON when called via AJAX, redirects to profile otherwise
	 *
	 * @return  string  JSON
	 */
	public function saveTask()
	{
		// Check if they are logged in
		if (User::isGuest())
		{
			return false;
		}

		Request::checkToken(array('get', 'post'));

		$no_html = Request::getInt('no_html', 0);

		// Incoming user ID
		$id = Request::getInt('id', 0, 'post');

		// Do we have an ID?
		if (!$id)
		{
			App::abort(404, Lang::txt('COM_MEMBERS_NO_ID'));
		}

		// Load the profile
		$member = Member::oneOrFail($id);

		// Name changed?
		$name = Request::getArray('name', array(), 'post');

		if ($name && !empty($name))
		{
			$name['first']  = strip_tags($name['first']);
			$name['middle'] = strip_tags($name['middle']);
			$name['last']   = strip_tags($name['last']);
			$name['first']  = preg_replace('/[<>]/', '', trim($name['first']));
			$name['middle'] = preg_replace('/[<>]/', ' ', trim($name['middle']));
			$name['last']   = preg_replace('/[<>]/', ' ', trim($name['last']));
			$name['first']  = htmlentities($name['first'], ENT_QUOTES, "UTF-8");
			$name['middle']  = htmlentities($name['middle'], ENT_QUOTES, "UTF-8");
			$name['last']  = htmlentities($name['last'], ENT_QUOTES, "UTF-8");
			$member->set('givenName', \Hubzero\Utility\Sanitize::cleanProperName($name['first']));
			$member->set('middleName', \Hubzero\Utility\Sanitize::cleanProperName($name['middle']));
			$member->set('surname', \Hubzero\Utility\Sanitize::cleanProperName($name['last']));

			$name = implode(' ', $name);
			$name = preg_replace('/\s+/', ' ', $name);

			$member->set('name', \Hubzero\Utility\Sanitize::cleanProperName($name));
		}

		// Set profile access
		$visibility = Request::getVar('profileaccess', null, 'post');

		if (!is_null($visibility))
		{
			$member->set('access', $visibility);
		}

		// Check email
		$oldemail = $member->get('email');
		$email = Request::getVar('email', null, 'post');

		if (!is_null($email))
		{
			$member->set('email', (string)$email);

			// Unconfirm if the email address changed
			if ($oldemail != $email)
			{
				// Get a new confirmation code
				$confirm = \Components\Members\Helpers\Utility::genemailconfirm();

				$member->set('activation', $confirm);
			}
		}

		// Receieve email updates?
		$sendEmail = Request::getVar('sendEmail', null, 'post');

		if (!is_null($sendEmail))
		{
			$member->set('sendEmail', $sendEmail);
		}

		// Usage agreement
		$usageAgreement = Request::getVar('usageAgreement', null, 'post');

		if (!is_null($usageAgreement))
		{
			$member->set('usageAgreement', (int)$usageAgreement);
		}

		// Are we declining the terms of use?
		// If yes we want to set the usage agreement to 0 and profile to private
		$declineTOU = Request::getInt('declinetou', 0);

		if ($declineTOU)
		{
			$member->set('access', 0);
			$member->set('usageAgreement', 0);
		}

		$access  = Request::getArray('access', array(), 'post');

		if (is_array($access) && !empty($access))
		{
			foreach ($access as $k => $v)
			{
				$member->setParam('access_' . $k, intval($v));
			}
			$member->set('params', $member->params->toString());
		}

		// Save the changes
		if (!$member->save())
		{
			$this->setError($member->getError());
			if ($no_html)
			{
				echo json_encode($this->getErrors());
				exit();
			}
			return $this->editTask($member);
		}

		// Incoming profile edits
		$profile = Request::getArray('profile', array(), 'post');
		$field_to_check = Request::getArray('field_to_check', array());

		// Querying the organization id on ror.org
		// If RoR Api is turned off because of failed API or if key doesn't exist, don't retrieve list from Api.
        $useRorApi = \Component::params('com_members')->get('rorApi');
		if (isset($profile['organization']) && !empty($profile['organization']) && $useRorApi){
			$profile['orgid'] = $this->getOrganizationId($profile['organization']);
		}

		$old = Profile::collect($member->profiles);
		$profile = array_merge($old, $profile);

		// Compile profile data
		foreach ($profile as $key => $data)
		{
			if (isset($profile[$key]) && is_array($profile[$key]))
			{
				$profile[$key] = array_filter($profile[$key]);
			}
			if (isset($profile[$key . '_other']) && trim($profile[$key . '_other']))
			{
				if (is_array($profile[$key]))
				{
					$profile[$key][] = $profile[$key . '_other'];
				}
				else
				{
					$profile[$key] = $profile[$key . '_other'];
				}

				unset($profile[$key . '_other']);
			}
		}

		// Validate profile data
		$fields = Field::all()
			->including(['options', function ($option){
				$option
					->select('*');
			}])
			->where('action_edit', '!=', Field::STATE_HIDDEN)
			->ordered()
			->rows();

		$form = new \Hubzero\Form\Form('profile', array('control' => 'profile'));
		$form->load(Field::toXml($fields, 'edit', $profile));
		$form->bind(new \Hubzero\Config\Registry($profile));

		$errors = array(
			'_missing' => array(),
			'_invalid' => array()
		);

		if (!$form->validate($profile))
		{
			foreach ($form->getErrors() as $key => $error)
			{
				// Filter out fields
				if (!empty($field_to_check) && !in_array($key, $field_to_check))
				{
					continue;
				}

				if ($error instanceof \Hubzero\Form\Exception\MissingData)
				{
					$errors['_missing'][$key] = (string)$error;
				}

				$errors['_invalid'][$key] = (string)$error;

				$this->setError((string)$error);
			}
		}

		if ($this->getError())
		{
			if ($no_html)
			{
				echo json_encode($errors);
				exit();
			}
			return $this->editTask($member);
		}

		// Save profile data
		if (!$member->saveProfile($profile, $access))
		{
			$this->setError($member->getError());
			if ($no_html)
			{
				echo json_encode($this->getErrors());
				exit();
			}
			return $this->editTask($member);
		}

		$email = $member->get('email');

		// Make sure certain changes make it back to the user table
		if ($member->get('id') == User::get('id'))
		{
			$user = App::get('session')->get('user');

			if ($member->get('name') != $user->get('name'))
			{
				$user->set('name', $member->get('name'));
			}

			// Update session if email is changing
			if ($member->get('email') != $user->get('email'))
			{
				$user->set('email', $member->get('email'));
				$user->set('activation', $confirm);

				// Add item to session to mark that the user changed emails
				// this way we can serve profile images for these users but not all
				// unconfirmed users
				App::get('session')->set('userchangedemail', 1);
			}

			App::get('session')->set('user', $user);
		}

		// Send a new confirmation code AFTER we've successfully saved the changes to the e-mail address
		if ($email != $oldemail)
		{
                        $result = \Components\Members\Helpers\Utility::sendConfirmEmail($user, null, false);

			if ($result)
			{
				Notify::success('A confirmation email has been sent to "'. htmlentities($email, ENT_COMPAT, 'UTF-8') .'". You must click the link in that email to re-activate your account.');
			}
			else
			{
				Notify::error('An error occurred emailing "'. htmlentities($email, ENT_COMPAT, 'UTF-8') .'" your confirmation.');
			}

			//$this->_sendConfirmationCode($member->get('username'), $email, $confirm, $member->get('registerDate'),$member->get('name'));


			Event::trigger('onUserAfterChangeEmail', array($member->toArray()));
		}

		// If were declinging the terms we want to logout user and tell the javascript
		if ($declineTOU)
		{
			App::get('auth')->logout();
			echo json_encode(array('loggedout' => true));
			return;
		}

		if ($no_html)
		{
			// Output JSON
			echo json_encode(array('success' => true));
			exit();
		}

		// Redirect
		App::redirect(
			Route::url('index.php?option=' . $this->_option . ($id ? '&id=' . $id . '&active=profile' : ''))
		);
	}

	/**
	 * Send a confirmation code to a user's email address
	 *
	 * @param   strong   $login    Username
	 * @param   string   $email    User email address
	 * @param   string   $confirm  Confirmation code
	 * @return  boolean
	 */
	private function _sendConfirmationCode($login, $email, $confirm, $name, $registerDate)
	{
		// Email subject
		$subject = Config::get('sitename') .' account email confirmation';

		// Email message
		$eview = new \Hubzero\Component\View(array(
			'name'   => 'emails',
			'layout' => 'confirm'
		));

		$eview->set('option', $this->_option)
			->set('sitename', Config::get('sitename'))
			->set('login', $login)
			->set('email', $email)
			->set('confirm', $confirm)
			->set('baseURL', Request::base())
			->set('registerDate', $registerDate)
			->set('name', $name);

		$message = $eview->loadTemplate();
		$message = str_replace("\n", "\r\n", $message);

		$msg = new \Hubzero\Mail\Message();
		$msg->setSubject($subject)
		    ->addTo($email)
		    ->addFrom(Config::get('mailfrom'), Config::get('sitename') . ' Administrator')
		    ->addHeader('X-Component', $this->_option)
		    ->setBody($message);

		$result = false;

		// Send the email
		if ($msg->send())
		{
			Notify::success('A confirmation email has been sent to "'. htmlentities($email, ENT_COMPAT, 'UTF-8') .'". You must click the link in that email to re-activate your account.');
			$result = true;
		}
		else
		{
			Notify::error('An error occurred emailing "'. htmlentities($email, ENT_COMPAT, 'UTF-8') .'" your confirmation.');
		}

		return $result;
	}

	/**
	 * Show the current user activity
	 *
	 * @return  void
	 */
	public function activityTask()
	{
		// Set the page title
		Document::setTitle(Lang::txt(strtoupper($this->_option)) . ': ' . Lang::txt(strtoupper($this->_task)));

		// Set the pathway
		if (Pathway::count() <= 0)
		{
			Pathway::append(
				Lang::txt(strtoupper($this->_option)),
				'index.php?option=' . $this->_option
			);
		}
		Pathway::append(
			Lang::txt(strtoupper($this->_task)),
			'index.php?option=' . $this->_option . '&task=' . $this->_task
		);

		// Check if they're logged in
		if (User::isGuest())
		{
			$rtrn = Request::getString('REQUEST_URI', Route::url('index.php?option=' . $this->_controller . '&task=activity', false, true), 'server');
			App::redirect(
				Route::url('index.php?option=com_users&view=login&return=' . base64_encode($rtrn), false)
			);
		}

		// Check authorization
		if (!User::authorise('core.manage', $this->_option))
		{
			App::redirect(
				Route::url('index.php?option=' . $this->_option)
			);
		}

		// Get logged-in users
		$prevuser = '';
		$user     = array();
		$users    = array();
		$guests   = array();

		// get sessions
		$result = SessionHelper::getAllSessions(array(
			'guest' => 0
		));

		if ($result && count($result) > 0)
		{
			foreach ($result as $row)
			{
				$row->idle = time() - $row->time;

				if ($prevuser != $row->username)
				{
					if ($user)
					{
						$profile = Member::oneOrNew($prevuser);

						$users[$prevuser] = $user;
						$users[$prevuser]['uidNumber']       = $profile->get('id');
						$users[$prevuser]['name']            = $profile->get('name');
						$users[$prevuser]['org']             = $profile->get('organization');
						$users[$prevuser]['orgtype']         = $profile->get('orgtype');
						$users[$prevuser]['countryresident'] = $profile->get('countryresident');
					}
					$prevuser = $row->username;
					$user = array();
				}
				array_push($user, array('ip' => $row->ip, 'idle' => $row->idle));
			}

			if ($user)
			{
				$profile = Member::oneOrNew($prevuser);

				$users[$prevuser] = $user;
				$users[$prevuser]['uidNumber']       = $profile->get('id');
				$users[$prevuser]['name']            = $profile->get('name');
				$users[$prevuser]['org']             = $profile->get('organization');
				$users[$prevuser]['orgtype']         = $profile->get('orgtype');
				$users[$prevuser]['countryresident'] = $profile->get('countryresident');
			}
		}

		// get sessions
		$result = SessionHelper::getAllSessions(array(
			'guest' => 1
		));

		if (count($result) > 0)
		{
			foreach ($result as $row)
			{
				$row->idle = time() - $row->time;
				array_push($guests, array('ip' => $row->ip, 'idle' => $row->idle));
			}
		}

		// Output View
		$this->view
			->set('title', Lang::txt('Active Users and Guests'))
			->set('users', $users)
			->set('guests', $guests)
			->setErrors($this->getErrors())
			->display();
	}

	/**
	 * Cancel a task and redirect to profile
	 *
	 * @return  void
	 */
	public function cancelTask()
	{
		// Incoming
		$id = Request::getInt('id', 0);

		// Redirect
		App::redirect(
			Route::url('index.php?option=' . $this->_option . '&id=' . $id . '&active=profile')
		);
	}

	/**
	 * Display a message noting the person is in spamjail
	 *
	 * @return  void
	 */
	public function spamjailTask()
	{
		$this->view->display();
	}

	/**
	 * Display a message showing the person's account is waiting approval
	 *
	 * @return  void
	 */
	public function unapprovedTask()
	{
		// Only logged-in users should ever get to this page
		if (User::isGuest())
		{
			App::redirect(
				Route::url('index.php?option=com_users&view=login&return=' . base64_encode(Request::current(true)), false)
			);
		}

		// Get the user and then check the database to see if the session and database are out of sync
		$real = User::getInstance(User::get('id'));

		if ($real->get('approved'))
		{
			// Update the session and redirect
			$session = App::get('session');

			$sessionUser = $session->get('user');
			$sessionUser->set('approved', $real->get('approved'));
			$session->set('user', $sessionUser);

			// Redirect
			App::redirect(Request::current(true));
		}

		$this->view->display();
	}

	/**
	 * Perform querying of research organization based on the input value
	 *
	 * @return  array   matched research organization names
	 */
	public function getOrganizationsTask() {
		$term = trim(Request::getString('term', ''));
		$term = \Components\Members\Helpers\Utility::escapeSpecialChars($term);
		
		$verNum = \Component::params('com_members')->get('rorApiVersion');
		
		if (!empty($verNum))
		{
			$queryURL = "https://api.ror.org/$verNum/organizations?query.advanced=names.value:" . urlencode($term);
			
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $queryURL);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

			$result = curl_exec($ch);

			if (!$result){
				return false;
			}

			$info = curl_getinfo($ch);

			$code = $info['http_code'];

			if (($code != 201) && ($code != 200)){
				return false;
			}

			$organizations = [];

			$resultObj = json_decode($result);			

			foreach ($resultObj->items as $orgObj)
			{
				foreach ($orgObj->names as $nameObj)
				{
					if ($nameObj->lang == "en" && !in_array($nameObj->value, $organizations))
					{
						$organizations[] = $nameObj->value;
					}
				}
			}

			curl_close($ch);

			echo json_encode($organizations);
			exit();
		}
	}

	/**
	 * Perform querying of research organization id on ror.org
	 * @param   string   $organization
	 *
	 * @return  string   organization id
	 */
	public function getOrganizationId($organization){
		$org = trim($organization);
		$orgQry = \Components\Members\Helpers\Utility::escapeSpecialChars($org);
		
		$verNum = \Component::params('com_members')->get('rorApiVersion');
		
		if (!empty($verNum))
		{
			$queryURL = "https://api.ror.org/$verNum/organizations?query.advanced=names.value:" . urlencode($orgQry);
			
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $queryURL);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

			$result = curl_exec($ch);

			if (!$result){
				return false;
			}

			$info = curl_getinfo($ch);

			$code = $info['http_code'];

			if (($code != 201) && ($code != 200)){
				return false;
			}

			$resultObj = json_decode($result);
			
			foreach ($resultObj->items as $orgObj)
			{
				foreach ($orgObj->names as $nameObj)
				{
					if (strcmp($nameObj->value, $org) == 0)
					{
						curl_close($ch);
						return $orgObj->id;
					}
				}
			}
			
			curl_close($ch);
			return "";
		}
	}
}
