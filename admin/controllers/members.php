<?php
/**
 * @package    hubzero-cms
 * @copyright  Copyright (c) 2005-2024 The Regents of the University of California.
 * @license    http://opensource.org/licenses/MIT MIT
 */

namespace Components\Members\Admin\Controllers;

use Components\Members\Models\Member;
use Components\Members\Helpers;
use Components\Members\Models\Profile;
use Components\Members\Models\Profile\Field;
use Components\Members\Models\Profile\Option;
use Hubzero\Access\Group as Accessgroup;
use Hubzero\Access\Access;
use Hubzero\Component\AdminController;
use Hubzero\Utility\Validate;
use Filesystem;
use Request;
use Notify;
use Config;
use Route;
use Event;
use User;
use Date;
use Lang;
use App;

include_once dirname(dirname(__DIR__)) . DS . 'models' . DS . 'profile' . DS . 'field.php';
include_once dirname(dirname(__DIR__)) . DS . 'helpers' . DS . 'utility.php';
include_once \Component::path('members') . '/models/registration.php';

/**
 * Manage site members
 */
class Members extends AdminController
{
	/**
	 * Execute a task
	 *
	 * @return  void
	 */
	public function execute()
	{
		Lang::load($this->_option . '.members', dirname(__DIR__));

		$this->registerTask('modal', 'display');
		$this->registerTask('add', 'edit');
		$this->registerTask('apply', 'save');
		$this->registerTask('save2new', 'save');
		$this->registerTask('confirm', 'state');
		$this->registerTask('unconfirm', 'state');
		$this->registerTask('applyprofile', 'saveprofile');
		$this->registerTask('unblock', 'block');
		$this->registerTask('block', 'block');
		$this->registerTask('disapprove', 'approve');
		$this->registerTask('queryorganization', 'getOrganizations');

		parent::execute();
	}

	/**
	 * Display a list of site members
	 *
	 * @return  void
	 */
	public function displayTask()
	{
		// Get filters
		$filters = array(
			'search' => urldecode(Request::getState(
				$this->_option . '.' . $this->_controller . '.search',
				'search',
				''
			)),
			'sort' => Request::getState(
				$this->_option . '.' . $this->_controller . '.sort',
				'filter_order',
				'registerDate'
			),
			'sort_Dir' => Request::getState(
				$this->_option . '.' . $this->_controller . '.sortdir',
				'filter_order_Dir',
				'DESC'
			),
			'registerDate' => Request::getState(
				$this->_option . '.' . $this->_controller . '.registerDate',
				'registerDate',
				''
			),
			'activation' => Request::getState(
				$this->_option . '.' . $this->_controller . '.activation',
				'activation',
				0,
				'int'
			),
			'state' => Request::getState(
				$this->_option . '.' . $this->_controller . '.state',
				'state',
				'*'
			),
			'access' => Request::getState(
				$this->_option . '.' . $this->_controller . '.access',
				'access',
				0,
				'int'
			),
			'approved' => Request::getState(
				$this->_option . '.' . $this->_controller . '.approved',
				'approved',
				'*'
			),
			'group_id' => Request::getState(
				$this->_option . '.' . $this->_controller . '.group_id',
				'group_id',
				0,
				'int'
			),
			'range' => Request::getState(
				$this->_option . '.' . $this->_controller . '.range',
				'range',
				''
			)
		);

		// Build query
		$entries = Member::all();

		$a = $entries->getTableName();
		$b = '#__user_usergroup_map';

		$entries
			->select($a . '.*')
			->including(['accessgroups', function ($accessgroup){
				$accessgroup
					->select('*');
			}])
			->including(['notes', function ($note){
				$note
					->select('id')
					->select('user_id');
			}]);

		if ($filters['group_id'])
		{
			$entries
				->join($b, $b . '.user_id', $a . '.id', 'left')
				->whereEquals($b . '.group_id', (int)$filters['group_id']);
				/*->group($a . '.id')
				->group($a . '.name')
				->group($a . '.username')
				->group($a . '.password')
				->group($a . '.usertype')
				->group($a . '.block')
				->group($a . '.sendEmail')
				->group($a . '.registerDate')
				->group($a . '.lastvisitDate')
				->group($a . '.activation')
				->group($a . '.params')
				->group($a . '.email');*/
		}

		if ($filters['search'])
		{
			if (is_numeric($filters['search']))
			{
				$entries->whereEquals($a . '.id', (int)$filters['search']);
			}
			else
			{
				$entries->whereLike($a . '.name', strtolower((string)$filters['search']), 1)
					->orWhereLike($a . '.username', strtolower((string)$filters['search']), 1)
					->orWhereLike($a . '.email', strtolower((string)$filters['search']), 1)
					->resetDepth();
			}
		}

		if ($filters['registerDate'])
		{
			$entries->where($a . '.registerDate', '>=', $filters['registerDate']);
		}

		if ($filters['access'] > 0)
		{
			$entries->whereEquals($a . '.access', (int)$filters['access']);
		}

		if (is_numeric($filters['state']))
		{
			$entries->whereEquals($a . '.block', (int)$filters['state']);
		}

		if (is_numeric($filters['approved']))
		{
			$entries->whereEquals($a . '.approved', (int)$filters['approved']);
		}

		if ($filters['activation'] < 0)
		{
			$entries->where($a . '.activation', '<', 0);
		}
		if ($filters['activation'] > 0)
		{
			$entries->where($a . '.activation', '>', 0);
		}

		// Apply the range filter.
		if ($filters['range'])
		{
			// Get UTC for now.
			$dNow = Date::of('now');
			$dStart = clone $dNow;

			switch ($filters['range'])
			{
				case 'past_week':
					$dStart->modify('-7 day');
					break;

				case 'past_1month':
					$dStart->modify('-1 month');
					break;

				case 'past_3month':
					$dStart->modify('-3 month');
					break;

				case 'past_6month':
					$dStart->modify('-6 month');
					break;

				case 'post_year':
				case 'past_year':
					$dStart->modify('-1 year');
					break;

				case 'today':
					// Ranges that need to align with local 'days' need special treatment.
					$offset = Config::get('offset');

					// Reset the start time to be the beginning of today, local time.
					$dStart = Date::of('now', $offset);
					$dStart->setTime(0, 0, 0);

					// Now change the timezone back to UTC.
					$tz = new \DateTimeZone('GMT');
					$dStart->setTimezone($tz);
					break;
			}

			if ($filters['range'] == 'post_year')
			{
				$entries->where($a . '.registerDate', '<', $dStart->format('Y-m-d H:i:s'));
			}
			else
			{
				$entries->where($a . '.registerDate', '>=', $dStart->format('Y-m-d H:i:s'));
				$entries->where($a . '.registerDate', '<=', $dNow->format('Y-m-d H:i:s'));
			}
		}

		// Get records
		$rows = $entries
			->order($a . '.' . $filters['sort'], $filters['sort_Dir'])
			->paginated('limitstart', 'limit')
			->rows();

		// Access groups
		$accessgroups = Accessgroup::all()
			->ordered()
			->rows();

		// Output the HTML
		$this->view
			->set('rows', $rows)
			->set('accessgroups', $accessgroups)
			->set('filters', $filters)
			->setLayout($this->getTask() == 'modal' ? 'modal' : 'display')
			->display();
	}

	/**
	 * Edit a member's information
	 *
	 * @param   object  $user
	 * @return  void
	 */
	public function editTask($user=null)
	{
		if (!User::authorise('core.manage', $this->_option)
		 && !User::authorise('core.admin', $this->_option)
		 && !User::authorise('core.create', $this->_option)
		 && !User::authorise('core.edit', $this->_option))
		{
			return $this->cancelTask();
		}

		Request::setVar('hidemainmenu', 1);

		if (!$user)
		{
			// Incoming
			$id = Request::getArray('id', array(0));

			// Get the single ID we're working with
			if (is_array($id))
			{
				$id = (!empty($id)) ? $id[0] : 0;
			}

			// Initiate database class and load info
			$user = Member::oneOrNew($id);
		}

		$password = \Hubzero\User\Password::getInstance($user->get('id'));

		// Get password rules
		// Get the password rule descriptions
		$password_rules = array();
		foreach (\Hubzero\Password\Rule::all()->whereEquals('enabled', 1)->rows() as $rule)
		{
			if (!empty($rule['description']))
			{
				$password_rules[] = $rule['description'];
			}
		}

		$tabs = Event::trigger('members.onUserEdit', array($user));

		// Output the HTML
		$this->view
			->set('profile', $user)
			->set('password', $password)
			->set('password_rules', $password_rules)
			->set('validated', (isset($this->validated) ? $this->validated : false))
			->set('tabs', $tabs)
			->setErrors($this->getErrors())
			->setLayout('edit')
			->display();
	}

	/**
	 * Save an entry and return to main listing
	 *
	 * @return  void
	 */
	public function saveTask()
	{
		// Check for request forgeries
		Request::checkToken();

		if (!User::authorise('core.manage', $this->_option)
		 && !User::authorise('core.admin', $this->_option)
		 && !User::authorise('core.create', $this->_option)
		 && !User::authorise('core.edit', $this->_option))
		{
			App::abort(403, Lang::txt('JERROR_ALERTNOAUTHOR'));
		}

		// Incoming profile edits
		$fields = Request::getArray('fields', array(), 'post');

		// Load the profile
		$user = Member::oneOrNew($fields['id']);

		// Get the user before changes so we can
		// compare how data changed later on
		$prev = clone $user;

		// Set the incoming data
		$user->set($fields);

		if ($user->isNew())
		{
			$newUsertype = $this->config->get('new_usertype');

			if (!$newUsertype)
			{
				$newUsertype = Accessgroup::oneByTitle('Registered')->get('id');
			}

			$user->set('accessgroups', array($newUsertype));

			// Check that username is filled
			if (!Validate::username($user->get('username')))
			{
				Notify::error(Lang::txt('COM_MEMBERS_MEMBER_USERNAME_INVALID'));
				return $this->editTask($user);
			}

			// Check email is valid
			if (!Validate::email($user->get('email')))
			{
				Notify::error(Lang::txt('COM_MEMBERS_MEMBER_EMAIL_INVALID'));
				return $this->editTask($user);
			}

			// Set home directory
			$hubHomeDir = rtrim($this->config->get('homedir'), '/');
			if (!$hubHomeDir)
			{
				// try to deduce a viable home directory based on sitename or live_site
				$sitename = strtolower(Config::get('sitename'));
				$sitename = preg_replace('/^http[s]{0,1}:\/\//', '', $sitename, 1);
				$sitename = trim($sitename, '/ ');
				$sitename_e = explode('.', $sitename, 2);
				if (isset($sitename_e[1]))
				{
					$sitename = $sitename_e[0];
				}
				if (!preg_match("/^[a-zA-Z]+[\-_0-9a-zA-Z\.]+$/i", $sitename))
				{
					$sitename = '';
				}
				if (empty($sitename))
				{
					$sitename = strtolower(Request::base());
					$sitename = preg_replace('/^http[s]{0,1}:\/\//', '', $sitename, 1);
					$sitename = trim($sitename, '/ ');
					$sitename_e = explode('.', $sitename, 2);
					if (isset($sitename_e[1]))
					{
						$sitename = $sitename_e[0];
					}
					if (!preg_match("/^[a-zA-Z]+[\-_0-9a-zA-Z\.]+$/i", $sitename))
					{
						$sitename = '';
					}
				}

				$hubHomeDir = DS . 'home';

				if (!empty($sitename))
				{
					$hubHomeDir .= DS . $sitename;
				}
			}
			$user->set('homeDirectory', $hubHomeDir . DS . $user->get('username'));
			$user->set('loginShell', '/bin/bash');
			$user->set('ftpShell', '/usr/lib/sftp-server');

			$user->set('registerDate', Date::toSql());
		}

		$givenTrimmed = \Hubzero\Utility\Sanitize::cleanProperName($fields['givenName']);
		$middleTrimmed = \Hubzero\Utility\Sanitize::cleanProperName($fields['middleName']);
		$surTrimmed = \Hubzero\Utility\Sanitize::cleanProperName($fields['surname']);

		$user->set('givenName', $givenTrimmed);
		$user->set('middleName', $middleTrimmed);
		$user->set('surname', $surTrimmed);
		$name = array(
			$givenTrimmed,
			$middleTrimmed,
			$surTrimmed
		);
		$name = implode(' ', $name);
		$name = \Hubzero\Utility\Sanitize::cleanProperName($name);

		$user->set('name', $name);
		$user->set('modifiedDate', Date::toSql());

		// Get their current activation code
		$ac = $user->get('activation');

		// If the incoming code is > zero, then the account being activated
		// (unactivated accoutns have a negative-value code, e.g.: -123456)
		if ($ec = Request::getInt('activation', 0, 'post'))
		{
			$user->set('activation', $ec);
		}
		// If the account was previously activated and we're de-activating
		// reset the activation code.
		elseif ($ac > 0)
		{
			$user->set('activation', Helpers\Utility::genemailconfirm());
		}

		// Can't block yourself
		if ($user->get('block') && $user->get('id') == User::get('id') && !User::get('block'))
		{
			Notify::error(Lang::txt('COM_MEMBERS_USERS_ERROR_CANNOT_BLOCK_SELF'));
			return $this->editTask($user);
		}

		// Make sure that we are not removing ourself from Super Admin group
		$iAmSuperAdmin = User::authorise('core.admin');

		if ($iAmSuperAdmin && User::get('id') == $user->get('id'))
		{
			// Check that at least one of our new groups is Super Admin
			$stillSuperAdmin = false;

			foreach ($fields['accessgroups'] as $group)
			{
				$stillSuperAdmin = ($stillSuperAdmin ? $stillSuperAdmin : Access::checkGroup($group, 'core.admin'));
			}

			if (!$stillSuperAdmin)
			{
				Notify::error(Lang::txt('COM_MEMBERS_USERS_ERROR_CANNOT_DEMOTE_SELF'));
				return $this->editTask($user);
			}
		}

		// Save the changes
		if (!$user->save())
		{
			Notify::error($user->getError());
			return $this->editTask($user);
		}

		// Save profile data
		$profile = Request::getArray('profile', array(), 'post');
		$access  = Request::getArray('profileaccess', array(), 'post');

        // Querying the organization id on ror.org. 
		// If RoR Api is turned off because of failed API or if key doesn't exist, don't retrieve list from Api.
        $useRorApi = \Component::params('com_members')->get('rorApi');
        if (isset($profile['organization']) && !empty($profile['organization']) && $useRorApi) {
            $profile['orgid'] = $this->getOrganizationId($profile['organization']);
        }

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

		if (!$user->saveProfile($profile, $access))
		{
			Notify::error($user->getError());
			return $this->editTask($user);
		}

		// Do we have a new pass?
		$newpass = trim(Request::getString('newpass', '', 'post'));

		if ($newpass)
		{
			// Get password rules and validate
			$password_rules = \Hubzero\Password\Rule::all()
					->whereEquals('enabled', 1)
					->rows();

			$validated = \Hubzero\Password\Rule::verify($newpass, $password_rules, $user->get('id'));

			if (!empty($validated))
			{
				// Set error
				Notify::error(Lang::txt('COM_MEMBERS_PASSWORD_DOES_NOT_MEET_REQUIREMENTS'));
				$this->validated = $validated;
				$this->_task = 'apply';
			}
			else
			{
				// Save password
				\Hubzero\User\Password::changePassword($user->get('username'), $newpass);

				// Remove login failures
				$failures = \Hubzero\User\Log\Auth::all()
					->whereEquals('username', $user->get('username'))
					->whereEquals('status', 'failure')
					->rows();

				foreach ($failures as $failure)
				{
					$failure->destroy();
				}
			}
		}

		$passinfo = \Hubzero\User\Password::getInstance($user->get('id'));

		if (is_object($passinfo))
		{
			// Do we have shadow info to change?
			$shadowMax     = Request::getInt('shadowMax', false, 'post');
			$shadowWarning = Request::getInt('shadowWarning', false, 'post');
			$shadowExpire  = Request::getString('shadowExpire', '', 'post');

			if ($shadowMax || $shadowWarning || (!is_null($passinfo->get('shadowExpire')) && empty($shadowExpire)))
			{
				if ($shadowMax)
				{
					$passinfo->set('shadowMax', $shadowMax);
				}
				if ($shadowExpire || (!is_null($passinfo->get('shadowExpire')) && empty($shadowExpire)))
				{
					if (preg_match("/[0-9]{4}-[0-9]{2}-[0-9]{2}/", $shadowExpire))
					{
						$shadowExpire = strtotime($shadowExpire) / 86400;
						$passinfo->set('shadowExpire', $shadowExpire);
					}
					elseif (preg_match("/[0-9]+/", $shadowExpire))
					{
						$passinfo->set('shadowExpire', $shadowExpire);
					}
					elseif (empty($shadowExpire))
					{
						$passinfo->set('shadowExpire', null);
					}
				}
				if ($shadowWarning)
				{
					$passinfo->set('shadowWarning', $shadowWarning);
				}

				$passinfo->update();
			}
		}

		// Was a user secret reset requested?
		$newSecret = trim(Request::getVar('resetSecret', '', 'post'));
		// set new secret if indicated
		if (null !== $newSecret && $newSecret == 1)
		{
			$user->set('secret', Member::generateSecret(null));
			$user->save();
		}

		// Check for spam count
		$reputation = Request::getVar('spam_count', null, 'post');

		if (!is_null($reputation))
		{
			$user->reputation->set('spam_count', $reputation);
			$user->reputation->save();
		}

		// Email the user that their account has been approved
		if (!$prev->get('approved') && $user->get('approved') && $this->config->get('useractivation_email'))
		{
			if (!$this->emailApprovedUser($user))
			{
				Notify::error(Lang::txt('COM_MEMBERS_ERROR_EMAIL_FAILED'));
			}
		}

		// Set success message
		Notify::success(Lang::txt('COM_MEMBERS_MEMBER_SAVED'));

		// Drop through to edit form?
		if ($this->getTask() == 'apply')
		{
			// Force reload te record as it's possible other pieces
			// of code made changes (i.e., password change)
			$user = Member::oneOrNew($user->get('id'));
			return $this->editTask($user);
		}

		if ($this->getTask() == 'save2new')
		{
			return $this->editTask();
		}

		// Redirect
		$this->cancelTask();
	}

	/**
	 * Re-send a confirmation email to a user
	 *
	 * @return  void
	 */
	public function resendConfirmTask()
	{
		// Check for request forgeries
		Request::checkToken(['get', 'post']);

		// Check for permission to perform this aciton
		if (!User::authorise('core.manage', $this->_option)
		 && !User::authorise('core.admin', $this->_option)
		 && !User::authorise('core.create', $this->_option)
		 && !User::authorise('core.edit', $this->_option))
		{
			return $this->cancelTask();
		}

		$id = Request::getInt('id');
		$user = Member::oneOrFail($id);

		$xregistration = new \Components\Members\Models\Registration();
		$xregistration->loadProfile($user);

		// Send confirmation email
		if ($user->get('activation') < 0)
		{
			$sendEmail = \Components\Members\Helpers\Utility::sendConfirmEmail($user, $xregistration);
		}

		if (isset($sendEmail))
		{
			Notify::success(Lang::txt('COM_MEMBERS_RESEND_CONFIRM_SUCCESS'));
		}
		else
		{
			Notify::error(Lang::txt('COM_MEMBERS_RESEND_CONFIRM_ERROR'));
		}

		$return = base64_decode(Request::getString('return', ''));
		if ($return)
		{
			// return $this->editTask($user);
			App::redirect(
				Route::url($return, false)
			);
		}
		else
		{
			$this->cancelTask();
		}

	}

	/**
	 * Removes a profile entry, associated picture, and redirects to main listing
	 *
	 * @return  void
	 */
	public function removeTask()
	{
		// Check for request forgeries
		Request::checkToken();

		if (!User::authorise('core.manage', $this->_option)
		 && !User::authorise('core.admin', $this->_option)
		 && !User::authorise('core.delete', $this->_option))
		{
			App::abort(403, Lang::txt('JERROR_ALERTNOAUTHOR'));
		}

		// Incoming
		$ids = Request::getArray('id', array());
		$ids = (!is_array($ids) ? array($ids) : $ids);

		// Do we have any IDs?
		$i = 0;

		if (!empty($ids))
		{
			// Check if I am a Super Admin
			$iAmSuperAdmin = User::authorise('core.admin');

			// Loop through each ID and delete the necessary items
			foreach ($ids as $id)
			{
				// Remove the profile
				$user = Member::oneOrFail(intval($id));

				// Access checks.
				$allow = User::authorise('core.delete', 'com_members');

				// Don't allow non-super-admin to delete a super admin
				$allow = (!$iAmSuperAdmin && Access::check($user->get('id'), 'core.admin')) ? false : $allow;

				if (!$allow)
				{
					Notify::warning(Lang::txt('JERROR_CORE_DELETE_NOT_PERMITTED'));
					continue;
				}

				$data = $user->toArray();

				if (!$user->destroy())
				{
					Notify::error($user->getError());
					continue;
				}

				Event::trigger('user.onUserAfterDelete', array($data, true, $this->getError()));

				$i++;
			}
		}

		if ($i)
		{
			Notify::success(Lang::txt('COM_MEMBERS_MEMBER_REMOVED'));
		}

		// Output messsage and redirect
		$this->cancelTask();
	}

	/**
	 * Sets the account activation state of a member
	 *
	 * @return  void
	 */
	public function stateTask()
	{
		// Check for request forgeries
		Request::checkToken(['get', 'post']);

		if (!User::authorise('core.manage', $this->_option)
		 && !User::authorise('core.admin', $this->_option)
		 && !User::authorise('core.edit', $this->_option))
		{
			App::abort(403, Lang::txt('JERROR_ALERTNOAUTHOR'));
		}

		$state = ($this->getTask() == 'confirm' ? 1 : 0);

		// Incoming user ID
		$ids = Request::getArray('id', array());
		$ids = (!is_array($ids) ? array($ids) : $ids);

		// Do we have an ID?
		if (empty($ids))
		{
			Notify::warning(Lang::txt('COM_MEMBERS_NO_ID'));
			return $this->cancelTask();
		}

		$i = 0;

		foreach ($ids as $id)
		{
			// Load the profile
			$user = Member::oneOrFail(intval($id));

			if ($state)
			{
				$user->set('activation', $state);
			}
			else
			{
				$user->set('activation', Helpers\Utility::genemailconfirm());
			}

			if (!$user->save())
			{
				Notify::error($user->getError());
				continue;
			}

			$i++;
		}

		if ($i)
		{
			Notify::success(Lang::txt('COM_MEMBERS_CONFIRMATION_CHANGED'));
		}

		$this->cancelTask();
	}

	/**
	 * Sets the account approved state of a member
	 *
	 * @return  void
	 */
	public function approveTask()
	{
		// Check for request forgeries
		Request::checkToken(['get', 'post']);

		if (!User::authorise('core.manage', $this->_option)
		 && !User::authorise('core.admin', $this->_option)
		 && !User::authorise('core.edit', $this->_option))
		{
			App::abort(403, Lang::txt('JERROR_ALERTNOAUTHOR'));
		}

		$state = ($this->getTask() == 'approve' ? 1 : 0);

		// Incoming user ID
		$ids = Request::getArray('id', array());
		$ids = (!is_array($ids) ? array($ids) : $ids);

		// Do we have an ID?
		if (empty($ids))
		{
			Notify::warning(Lang::txt('COM_MEMBERS_NO_ID'));
			return $this->cancelTask();
		}

		$i = 0;

		foreach ($ids as $id)
		{
			// Load the profile
			$user = Member::oneOrFail(intval($id));

			$prev = $user->get('approved');

			// Extra, paranoid check that we only approve accounts that need it
			if ($prev != $state)
			{
				$user->set('approved', $state);

				if (!$user->save())
				{
					Notify::error($user->getError());
					continue;
				}

				// Email the user that their account has been approved
				if ($state && $this->config->get('useractivation_email'))
				{
					if (!$this->emailApprovedUser($user))
					{
						Notify::error(Lang::txt('COM_MEMBERS_ERROR_EMAIL_FAILED'));
					}
				}

				$i++;
			}
		}

		if ($i)
		{
			Notify::success(Lang::txt('COM_MEMBERS_APPROVED_STATUS_CHANGED'));
		}

		$this->cancelTask();
	}

	/**
	 * Send an email to a user
	 * stating their account has been approved
	 *
	 * @param   object  $user
	 * @return  bool
	 */
	protected function emailApprovedUser($user)
	{
		// Compute the mail subject.
		$emailSubject = Lang::txt(
			'COM_MEMBERS_APPROVED_USER_EMAIL_SUBJECT',
			$user->get('name'),
			Config::get('sitename')
		);

		// Compute the mail body.
		$eview = new \Hubzero\Mail\View(array(
			'base_path' => dirname(dirname(__DIR__)) . DS . 'site',
			'name'      => 'emails',
			'layout'    => 'approved_plain'
		));
		$eview->option     = $this->_option;
		$eview->controller = $this->_controller;
		$eview->config     = $this->config;
		$eview->baseURL    = Request::root();
		$eview->user       = $user;
		$eview->sitename   = Config::get('sitename');

		$plain = $eview->loadTemplate(false);
		$plain = str_replace("\n", "\r\n", $plain);

		$eview->setLayout('approved_html');
		$html = $eview->loadTemplate();
		$html = str_replace("\n", "\r\n", $html);

		// Build the message and send it
		$mail = new \Hubzero\Mail\Message();
		$mail
			->addFrom(
				Config::get('mailfrom'),
				Config::get('fromname')
			)
			->addTo($user->get('email'))
			->setSubject($emailSubject);

		$mail->addPart($plain, 'text/plain');
		$mail->addPart($html, 'text/html');

		if (!$mail->send())
		{
			return false;
		}

		return true;
	}

	/**
	 * Different SQL functions
	 */
	public function runSelectQuery($query) {
        $db = \App::get('db');
        $db->setQuery($query);
        $objRows = $db->loadObjectList();

        // json_encode: returns a string containing the JSON representation from the mySQL -> json_decode: Returns the value encoded in json in appropriate PHP type
        $objString = json_encode($objRows, true);
        return json_decode($objString, true);
    }

    public function runInsertQuery($query, $vars) {
        $db = \App::get('db');
        $db->prepare($query);
        $db->bind($vars);
        return $db->execute();
    }

    public function runUpdateOrDeleteQuery($query) {
        $db = \App::get('db');
        $db->setQuery($query);
        return $db->query();
    }

	/**
	 * Run through SQL statements of deidentifying a member by userId
	 * Task is ran through the toolbar
	 */
	public function deidentifyTask() {
		$db = \App::get('db');

		// Check for request forgeries
		Request::checkToken(['get', 'post']);

		// Incoming user ID
		$ids = Request::getArray('id', array());
		$ids = (!is_array($ids) ? array($ids) : $ids);

		// No Id, throw up a warning
		if (empty($ids)){
			Notify::warning(Lang::txt('COM_MEMBERS_NO_ID'));
			return $this->cancelTask();
		}

		// Loop through the array of user Ids
		// Make sure plugin user/deidentify has been migrated / imported
		// Run through main CMS tables, then run through client specific database tables that pertains to jobs, sessions, views with same trigger name
		foreach ($ids as $id) {
			// Creating New Credentials for each user
			$anonPassword = "anonPassword_" . $id;
			$anonUserName = "anonUsername_" . $id;
			$anonUserNameSpace = "AnonFirst Middle Last" . $id;

			// Can't rely on any order the plugins run in. Setting the deletion profile key in controller before calling the plugins 
			$insert_UserProfileWithStatus_Query = "INSERT INTO `#__user_profiles` (`user_id`, `profile_key`, `profile_value`) values (?, 'deletion', 'marked')";
			$this->runInsertQuery($insert_UserProfileWithStatus_Query, array($id));

			// Running the plugin with all deletions
			$result = Event::trigger('user.onUserDeidentify', $id);

			if ($result) {
				// ----------- UPDATES TO THE PROFILES AND USERS TABLE, and User Profiles Table  ----------
				// Unset the keys and updates the final user records until after all plugins run. 
				// If anything fail, jos_users still exist which is enough to re-run deidentification. 
				$update_UsersById_Query = "UPDATE `#__users` set name=" . $db->quote($anonUserNameSpace) . ", givenName=" . $db->quote($anonUserName) .", middleName='', surname='anonSurName', username=" . $db->quote($anonUserName) . ", password=" .  $db->quote($anonPassword) . ", block='1', registerIP='', params='', homeDirectory='', email=" .  $db->quote($anonUserName . "@example.com") . " where id =" . $db->quote($id);
				$this->runUpdateOrDeleteQuery($update_UsersById_Query);
				
				$update_UserProfiles_Query = "UPDATE `#__user_profiles` SET profile_value='sanitized' WHERE user_id=" . $db->quote($id) . " AND profile_key='deletion'";
				$this->runUpdateOrDeleteQuery($update_UserProfiles_Query);
			} else {
				Notify::warning("Could not deidentify several user id");
				return $this->cancelTask();
			}
			
		}

		Notify::success("Deidentified several user id: " . implode(" ", $ids));
        $this->cancelTask();
	}

	/**
	 * Sets the account blocked state of a member
	 *
	 * @return  void
	 */
	public function blockTask()
	{
		// Check for request forgeries
		Request::checkToken(['get', 'post']);

		$state = ($this->getTask() == 'block' ? 1 : 0);

		// Incoming user ID
		$ids = Request::getArray('id', array());
		$ids = (!is_array($ids) ? array($ids) : $ids);

		// Do we have an ID?
		if (empty($ids))
		{
			Notify::warning(Lang::txt('COM_MEMBERS_NO_ID'));
			return $this->cancelTask();
		}

		$i = 0;

		foreach ($ids as $id)
		{
			// Load the profile
			$user = Member::oneOrFail(intval($id));
			// Block user
			$user->set('block', $state);

			// Load user password object
			$passinfo = \Hubzero\User\Password::getInstance($user->get('id'));
			// If blocking

			if ($state == 1)
			{
				// Set user Shadow Expiration to a past date.  This disabled the account in LDAP.  "10957" is the number of days to January 1, 2000 from epoch start
				$passinfo->set('shadowExpire', "10957");
				// Randomize and Save password
				$newrandompass = \Components\Members\Helpers\Utility::userpassgen(16);
				\Hubzero\User\Password::changePassword($user->get('username'), $newrandompass);
			}
			elseif ($state == 0)
			{
				// Set user Shadow Expiration to a past date.  This disabled the account in LDAP.  "10957" is the number of days to January 1, 2000 from epoch start
				$passinfo->set('shadowExpire', null);
			}
			$passinfo->update();

			if (!$user->save())
			{
				Notify::error($user->getError());
				continue;
			}

			$i++;
		}

		if ($i)
		{
			Notify::success(Lang::txt('COM_MEMBERS_BLOCK_STATUS_CHANGED'));
		}

		$this->cancelTask();
	}

	/**
	 * Resets the terms of use agreement for all users (requiring re-agreement)
	 *
	 * @return  void
	 */
	public function clearTermsTask()
	{
		// Check for request forgeries
		Request::checkToken(['get', 'post']);

		if (!User::authorise('core.manage', $this->_option)
		 && !User::authorise('core.admin', $this->_option)
		 && !User::authorise('core.edit', $this->_option))
		{
			App::abort(403, Lang::txt('JERROR_ALERTNOAUTHOR'));
		}

		// Update registration config value to require re-agreeing upon next login
		$currentTOU = $this->config->get('registrationTOU', 'RHRH');
		$newTOU     = substr_replace($currentTOU, 'R', 2, 1);
		$this->config->set('registrationTOU', $newTOU);

		// Get db object
		$dbo = App::get('db');
		$migration = new \Hubzero\Content\Migration\Base($dbo);

		if (!$migration->saveParams('com_members', $this->config))
		{
			Notify::error(Lang::txt('COM_MEMBERS_FAILED_TO_UPDATE_REGISTRATION_TOU'));

			return $this->cancelTask();
		}

		// Clear all old TOU states
		if (!Member::clearTerms())
		{
			Notify::error(Lang::txt('COM_MEMBERS_FAILED_TO_CLEAR_TOU'));

			return $this->cancelTask();
		}

		// Output message to let admin know everything went well
		Notify::success(Lang::txt('COM_MEMBERS_SUCESSFULLY_CLEARED_TOU'));

		$this->cancelTask();
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

		$filters = array(
			'limit'  => 20,
			'start'  => 0,
			'search' => strtolower(trim(Request::getString('value', '')))
		);

		// Fetch results
		$entries = Member::all()
			->whereEquals('block', 0);

		if ($filters['search'])
		{
			$entries->whereLike('name', strtolower((string)$filters['search']), 1)
				->orWhereLike('username', strtolower((string)$filters['search']), 1)
				->orWhereLike('email', strtolower((string)$filters['search']), 1)
				->resetDepth();
		}

		$rows = $entries
			->order('name', 'asc')
			->limit($filters['limit'])
			->rows();

		// Output search results in JSON format
		$json = array();

		foreach ($rows as $row)
		{
			$obj = array();
			$obj['id']      = $row->get('id');
			$obj['name']    = str_replace(array("\n", "\r", '\\'), '', $row->get('name'));
			$obj['picture'] = $row->picture();

			$json[] = $obj;
		}

		echo json_encode($json);
	}

	/**
	 * Download a picture
	 *
	 * @return  void
	 */
	public function pictureTask()
	{
		// Get vars
		$id = Request::getInt('id', 0);

		// Check to make sure we have an id
		if (!$id || $id == 0)
		{
			return;
		}

		// Load member
		$member = Member::oneOrFail($id);

		$file  = DS . trim($this->config->get('webpath', '/site/members'), DS);
		$file .= DS . Profile\Helper::niceidformat($member->get('uidNumber'));
		$file .= DS . Request::getString('image', $member->get('picture'));

		// Ensure the file exist
		if (!file_exists(PATH_APP . DS . $file))
		{
			App::abort(404, Lang::txt('COM_MEMBERS_FILE_NOT_FOUND') . ' ' . $file);
		}

		// Serve up the image
		$xserver = new \Hubzero\Content\Server();
		$xserver->filename(PATH_APP . DS . $file);
		$xserver->disposition('attachment');
		$xserver->acceptranges(false); // @TODO fix byte range support

		// Serve up file
		if (!$xserver->serve())
		{
			// Should only get here on error
			App::abort(404, Lang::txt('COM_MEMBERS_MEDIA_ERROR_SERVING_FILE'));
		}

		exit;
	}

	/**
	 * Debug user permissions
	 *
	 * @return  void
	 */
	public function debugTask()
	{
		include_once dirname(dirname(__DIR__)) . DS . 'helpers' . DS . 'debug.php';

		// Get filters
		$filters = array(
			'search' => urldecode(Request::getState(
				$this->_option . '.' . $this->_controller . '.debug.search',
				'search',
				''
			)),
			'sort' => Request::getState(
				$this->_option . '.' . $this->_controller . '.debug.sort',
				'filter_order',
				'lft'
			),
			'sort_Dir' => Request::getState(
				$this->_option . '.' . $this->_controller . '.debug.sortdir',
				'filter_order_Dir',
				'ASC'
			),
			'level_start' => Request::getState(
				$this->_option . '.' . $this->_controller . '.debug.filter_level_start',
				'filter_level_start',
				0,
				'int'
			),
			'level_end' => Request::getState(
				$this->_option . '.' . $this->_controller . '.debug.filter_level_end',
				'filter_level_end',
				0,
				'int'
			),
			'component' => Request::getState(
				$this->_option . '.' . $this->_controller . '.debug.filter_component',
				'filter_component',
				''
			)
		);

		if ($filters['level_end'] > 0 && $filters['level_end'] < $filters['level_start'])
		{
			$filters['level_end'] = $filters['level_start'];
		}

		$id = Request::getInt('id', 0);

		// Load member
		$member = Member::oneOrFail($id);

		// Select the required fields from the table.
		$entries = \Hubzero\Access\Asset::all();

		if ($filters['search'])
		{
			$entries->whereLike('name', $filters['search'], 1)
				->orWhereLike('title', $filters['search'], 1)
				->resetDepth();
		}

		if ($filters['level_start'] > 0)
		{
			$entries->where('level', '>=', $filters['level_start']);
		}
		if ($filters['level_end'] > 0)
		{
			$entries->where('level', '<=', $filters['level_end']);
		}

		// Filter the items over the component if set.
		if ($filters['component'])
		{
			$entries->whereEquals('name', $filters['component'], 1)
				->orWhereLike('name', $filters['component'], 1)
				->resetDepth();
		}

		$assets = $entries
			->order($filters['sort'], $filters['sort_Dir'])
			->paginated('limitstart', 'limit')
			->rows();

		$actions = \Components\Members\Helpers\Debug::getActions($filters['component']);

		$data = $assets->raw();
		$assets->clear();

		foreach ($data as $key => $asset)
		{
			$checks = array();

			foreach ($actions as $action)
			{
				$name  = $action[0];
				$level = $action[1];

				// Check that we check this action for the level of the asset.
				if ($level === null || $level >= $asset->get('level'))
				{
					// We need to test this action.
					$checks[$name] = Access::check($id, $name, $asset->get('name'));
				}
				else
				{
					// We ignore this action.
					$checks[$name] = 'skip';
				}
			}

			$asset->set('checks', $checks);

			$assets->push($asset);
		}

		$levels     = \Components\Members\Helpers\Debug::getLevelsOptions();
		$components = \Components\Members\Helpers\Debug::getComponents();

		// Output the HTML
		$this->view
			->set('user', $member)
			->set('filters', $filters)
			->set('assets', $assets)
			->set('actions', $actions)
			->set('levels', $levels)
			->set('components', $components)
			->display();
	}

	/**
	 * Show a form for building a profile schema
	 *
	 * @return  void
	 */
	public function profileTask()
	{
		Request::setVar('hidemainmenu', 1);

		if (!User::authorise('core.manage', $this->_option)
		 && !User::authorise('core.admin', $this->_option))
		{
			return $this->cancelTask();
		}

		$fields = Field::all()
			->including(['options', function ($option){
				$option
					->select('*')
					->ordered();
			}])
			->ordered()
			->rows();

		$this->view
			->set('fields', $fields)
			->setLayout('profile')
			->display();
	}

	/**
	 * Save profile schema
	 *
	 * @return  void
	 */
	public function saveprofileTask()
	{
		// Check for request forgeries
		Request::checkToken();

		if (!User::authorise('core.manage', $this->_option)
		 && !User::authorise('core.admin', $this->_option))
		{
			return $this->cancelTask();
		}

		// Incoming data
		$profile = json_decode(Request::getString('profile', '{}', 'post'));

		// Get the old schema
		$fields = Field::all()
			->including(['options', function ($option){
				$option
					->select('*')
					->ordered();
			}])
			->ordered()
			->rows();

		// Collect old fields
		$oldFields = array();
		foreach ($fields as $oldField)
		{
			$oldFields[$oldField->get('id')] = $oldField;
		}

		foreach ($profile->fields as $i => $element)
		{
			$field = null;

			$fid = (isset($element->field_id) ? $element->field_id : 0);

			if ($fid && isset($oldFields[$fid]))
			{
				$field = $oldFields[$fid];

				// Remove found fields from the list
				// Anything remaining will be deleted
				unset($oldFields[$fid]);
			}

			$field = ($field ?: Field::oneOrNew($fid));
			$field->set(array(
				'type'          => (string) $element->field_type,
				'label'         => (string) $element->label,
				'name'          => (string) $element->name,
				'description'   => (isset($element->field_options->description) ? (string) $element->field_options->description : ''),
				//'required'     => (isset($element->required) ? (int) $element->required : 0),
				//'readonly'     => (isset($element->readonly) ? (int) $element->readonly : 0),
				//'disabled'     => (isset($element->disabled) ? (int) $element->disabled : 0),
				'ordering'      => ($i + 1),
				'access'        => (isset($element->access) ? (int) $element->access : 0),
				'option_other'  => (isset($element->field_options->include_other_option) ? (int) $element->field_options->include_other_option : ''),
				'option_blank'  => (isset($element->field_options->include_blank_option) ? (int) $element->field_options->include_blank_option : ''),
				'action_create' => (isset($element->create) ? (int) $element->create : 1),
				'action_update' => (isset($element->update) ? (int) $element->update : 1),
				'action_edit'   => (isset($element->edit)   ? (int) $element->edit   : 1),
				'action_browse' => (isset($element->browse) ? (int) $element->browse : 0),
				'min'           => (isset($element->field_options->min) ? (int) $element->field_options->min : 0),
				'max'           => (isset($element->field_options->max) ? (int) $element->field_options->max : 0),
				'default_value' => (isset($element->field_options->value) ? (string) $element->field_options->value : ''),
				'placeholder'   => (isset($element->field_options->placeholder) ? (string) $element->field_options->placeholder : '')
			));

			if ($field->get('type') == 'dropdown')
			{
				$field->set('type', 'select');
			}
			if ($field->get('type') == 'paragraph')
			{
				$field->set('type', 'textarea');
			}

			if (!$field->save())
			{
				Notify::error($field->getError());
				continue;
			}

			// Collect old options
			$oldOptions = array();
			foreach ($field->options as $oldOption)
			{
				$oldOptions[$oldOption->get('id')] = $oldOption;
			}

			// Does this field have any set options?
			if (isset($element->field_options->options))
			{
				foreach ($element->field_options->options as $k => $opt)
				{
					$option = null;

					$oid = (isset($opt->field_id) ? $opt->field_id : 0);

					if ($oid && isset($oldOptions[$oid]))
					{
						$option = $oldOptions[$oid];

						// Remove found options from the list
						// Anything remaining will be deleted
						unset($oldOptions[$oid]);
					}

					$dependents = array();
					if (isset($opt->dependents))
					{
						$dependents = explode(',', trim($opt->dependents));
						$dependents = array_map('trim', $dependents);
						foreach ($dependents as $j => $dependent)
						{
							if (!$dependent)
							{
								unset($dependents[$j]);
							}
						}
					}

					$option = ($option ?: Option::oneOrNew($oid));
					$option->set(array(
						'field_id'   => $field->get('id'),
						'label'      => (string) $opt->label,
						'value'      => (isset($opt->value)   ? (string) $opt->value : ''),
						'checked'    => (isset($opt->checked) ? (int) $opt->checked : 0),
						'ordering'   => ($k + 1),
						'dependents' => json_encode($dependents)
					));

					if (!$option->save())
					{
						Notify::error($option->getError());
						continue;
					}
				}
			}

			// Remove any options not in the incoming list
			foreach ($oldOptions as $option)
			{
				if (!$option->destroy())
				{
					Notify::error($option->getError());
					continue;
				}
			}
		}

		// Remove any fields not in the incoming list
		foreach ($oldFields as $field)
		{
			if (!$field->destroy())
			{
				Notify::error($field->getError());
				continue;
			}
		}

		// Set success message
		Notify::success(Lang::txt('COM_MEMBERS_PROFILE_SCHEMA_SAVED'));

		// Drop through to edit form?
		if ($this->getTask() == 'applyprofile')
		{
			// Redirect, instead of falling through, to avoid caching issues
			App::redirect(
				Route::url('index.php?option=' . $this->_option . '&controller=' . $this->_controller . '&task=profile', false)
			);
		}

		// Redirect
		$this->cancelTask();
	}

	/**
	 * Check password
	 *
	 * Copies from the /api controller to work around
	 * an issue with which user ID was being used for verification
	 *
	 * @return  void
	 */
	public function checkpassTask()
	{
		$userid = Request::getInt('user_id', User::get('id'), 'post');

		// Get the password rules
		$password_rules = \Hubzero\Password\Rule::all()
			->whereEquals('enabled', 1)
			->rows();

		$pw_rules = array();

		// Get the password rule descriptions
		foreach ($password_rules as $rule)
		{
			if (!empty($rule['description']))
			{
				$pw_rules[] = $rule['description'];
			}
		}

		// Get the password
		$pw = Request::getString('password1', null, 'post');

		// Validate the password
		if (!empty($pw))
		{
			$msg = \Hubzero\Password\Rule::verify($pw, $password_rules, $userid);
		}
		else
		{
			$msg = array();
		}

		$html = '';

		// Iterate through the rules and add the appropriate classes (passed/error)
		if (count($pw_rules) > 0)
		{
			foreach ($pw_rules as $rule)
			{
				if (!empty($rule))
				{
					if (!empty($msg) && is_array($msg))
					{
						$err = in_array($rule, $msg);
					}
					else
					{
						$err = '';
					}
					$mclass = ($err)  ? ' class="error"' : 'class="passed"';
					$html .= "<li $mclass>" . $rule . '</li>';
				}
			}

			if (!empty($msg) && is_array($msg))
			{
				foreach ($msg as $message)
				{
					if (!in_array($message, $pw_rules))
					{
						$html .= '<li class="error">' . $message . '</li>';
					}
				}
			}
		}

		// Encode sessions for return
		$object = new \stdClass();
		$object->html = $html;

		echo json_encode($object);
	}


	/**
     * Perform querying of research organization based on the input value
     *
     * @return  array or false  matched research organization names
     */
    public function getOrganizationsTask(){
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
