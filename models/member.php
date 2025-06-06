<?php
/**
 * @package    hubzero-cms
 * @copyright  Copyright (c) 2005-2024 The Regents of the University of California.
 * @license    http://opensource.org/licenses/MIT MIT
 */

namespace Components\Members\Models;

use Hubzero\User\User;
use Hubzero\Config\Registry;
use stdClass;
use Request;
use Event;
use Route;

require_once __DIR__ . DS . 'profile.php';
require_once __DIR__ . DS . 'tags.php';
require_once __DIR__ . DS . 'note.php';
require_once __DIR__ . DS . 'quota.php';
require_once __DIR__ . DS . 'host.php';

/**
 * User model
 */
class Member extends User implements \Hubzero\Search\Searchable
{
	/**
	 * The table to which the class pertains
	 *
	 * This will default to #__{namespace}_{modelName} unless otherwise
	 * overwritten by a given subclass. Definition of this property likely
	 * indicates some derivation from standard naming conventions.
	 *
	 * @var  string
	 */
	protected $table = '#__users';

	/**
	 * Has profile data been loaded?
	 *
	 * @var  bool
	 */
	private $profileLoaded = false;


	/**
	 * Get profile fields
	 *
	 * @return  object
	 */
	public function profiles()
	{
		return $this->oneToMany('Profile', 'user_id');
	}

	/**
	 * Get notes
	 *
	 * @return  object
	 */
	public function notes()
	{
		return $this->oneToMany('Note', 'user_id');
	}

	/**
	 * Get quota
	 *
	 * @return  object
	 */
	public function quota()
	{
		return $this->oneToOne('Quota', 'user_id');
	}

	/**
	 * Get hosts
	 *
	 * @return  object
	 */
	public function hosts()
	{
		return $this->oneToMany('Host', 'uidNumber');
	}

	/**
	 * Gets an attribute by key
	 *
	 * This will not retrieve properties directly attached to the model,
	 * even if they are public - those should be accessed directly!
	 *
	 * Also, make sure to access properties in transformers using the get method.
	 * Otherwise you'll just get stuck in a loop!
	 *
	 * @param   string  $key      The attribute key to get
	 * @param   mixed   $default  The value to provide, should the key be non-existent
	 * @return  mixed
	 */
	public function get($key, $default = null)
	{
		if ($key == 'tags')
		{
			return $this->tags();
		}

		if (!$this->hasAttribute($key) && !$this->profileLoaded)
		{
			// Collect multi-value fields into arrays
			$data = Profile::collect($this->profiles);

			foreach ($data as $k => $v)
			{
				$this->set($k, $v);
			}

			$this->profileLoaded = true;
		}

		return parent::get($key, $default);
	}

	/**
	 * Is the user's email confirmed?
	 *
	 * @return  boolean
	 */
	public function isEmailConfirmed()
	{
		return ($this->get('activation') > 0);
	}

	/**
	 * Generate and return various links to the entry
	 * Link will vary depending upon action desired such as edit, delete, etc.
	 *
	 * @param   string  $type  The type of link to return
	 * @return  string
	 */
	public function link($type='')
	{
		if (!$this->get('id'))
		{
			return '';
		}

		$link = 'index.php?option=com_members&id=' . $this->get('id');

		// If it doesn't exist or isn't published
		$type = strtolower($type);
		switch ($type)
		{
			case 'edit':
			case 'changepassword':
				$link .= '&task=' . $type;
			break;

			default:
			break;
		}

		return $link;
	}

	/**
	 * Get tags on an entry
	 *
	 * @param   string   $what   Data format to return (string, array, cloud)
	 * @param   integer  $admin  Get admin tags? 0=no, 1=yes
	 * @return  mixed
	 */
	public function tags($what='cloud', $admin=0)
	{
		if (!$this->get('id'))
		{
			switch (strtolower($what))
			{
				case 'array':
					return array();
				break;

				case 'string':
				case 'cloud':
				case 'html':
				default:
					return '';
				break;
			}
		}

		$cloud = new Tags($this->get('id'));

		return $cloud->render($what, array('admin' => $admin));
	}

	/**
	 * Tag the entry
	 *
	 * @param   string   $tags     Tags to apply
	 * @param   integer  $user_id  ID of tagger
	 * @param   integer  $admin    Tag as admin? 0=no, 1=yes
	 * @return  boolean
	 */
	public function tag($tags=null, $user_id=0, $admin=0)
	{
		$cloud = new Tags($this->get('id'));

		return $cloud->setTags($tags, $user_id, $admin);
	}

	/**
	 * Save data
	 *
	 * @return  boolean
	 */
	public function save()
	{
		if (is_array($this->get('params')))
		{
			$params = new Registry($this->get('params'));

			$this->set('params', $params);
		}
		if (is_object($this->get('params')))
		{
			$this->set('params', $this->get('params')->toString());
		}

		// Map set data to profile fields
		$attribs = $this->getAttributes();
		$columns = $this->getStructure()->getTableColumns($this->getTableName());
		$profile = null;

		foreach ($attribs as $key => $val)
		{
			if ($key == 'accessgroups')
			{
				continue;
			}

			if ($key == 'profile' || $key == 'profiles')
			{
				$profile = $val;
			}

			if (!isset($columns[$key]))
			{
				$this->removeAttribute($key);
			}
		}

		// Save record
		$result = parent::save();

		if ($result)
		{
			if ($profile)
			{
				$result = $this->saveProfile($profile);
			}
		}

		if (!$result)
		{
			// Reset the data to the way it was before save attempt
			$this->set($attribs);
		}

		return $result;
	}

	/**
	 * Save profile data
	 *
	 * @param   array   $profile
	 * @param   array   $access
	 * @return  boolean
	 */
	public function saveProfile($profile, $access = array())
	{
		$profile = (array)$profile;
		$access  = (array)$access;

		// Fire the onUserBeforeSaveProfile event
		$user = $this->toArray();
		$result = Event::trigger('user.onUserBeforeSaveProfile', array($user, $profile, $access));

		if (in_array(false, $result, true))
		{
			// Plugin will have to raise its own error or throw an exception.
			return false;
		}

		$keep = array();

		foreach ($this->profiles as $field)
		{
			// Remove any entries not in the incoming data
			if (!isset($profile[$field->get('profile_key')]))
			{
				if (!$field->destroy())
				{
					$this->addError($field->getError());
					return false;
				}

				continue;
			}

			// Push to the list of fields we want to keep
			if (!isset($keep[$field->get('profile_key')]))
			{
				$keep[$field->get('profile_key')] = $field;
			}
			else
			{
				// Multi-value field
				$values = $keep[$field->get('profile_key')];
				$values = is_array($values) ? $values : array($values->get('profile_value') => $values);
				$values[$field->get('profile_value')] = $field;

				$keep[$field->get('profile_key')] = $values;
			}
		}

		$i = 1;

		foreach ($profile as $key => $data)
		{
			if ($key == 'tag' || $key == 'tags')
			{
				$this->tag($data);
				continue;
			}

			// Is it a multi-value field?
			if (is_array($data))
			{
				if (empty($data))
				{
					continue;
				}

				foreach ($data as $val)
				{
					if (is_array($val) || is_object($val))
					{
						$val = json_encode($val);
					}

					$val = trim($val);

					// Skip empty values
					if (!$val)
					{
						continue;
					}

					$field = null;

					// Try to find an existing entry
					if (isset($keep[$key]))
					{
						if (is_array($keep[$key]))
						{
							if (isset($keep[$key][$val]))
							{
								$field = $keep[$key][$val];
								unset($keep[$key][$val]);
							}
						}
						else
						{
							$field = $keep[$key];
							unset($keep[$key]);
						}
					}

					if (!($field instanceof Profile))
					{
						$field = Profile::blank();
					}

					$field->set(array(
						'user_id'       => $this->get('id'),
						'profile_key'   => $key,
						'profile_value' => $val,
						'ordering'      => $i,
						'access'        => (isset($access[$key]) ? $access[$key] : $field->get('access', 5))
					));

					if (!$field->save())
					{
						$this->addError($field->getError());
						return false;
					}
				}

				// Remove any values not already found
				if (isset($keep[$key]) && is_array($keep[$key]))
				{
					foreach ($keep[$key] as $f)
					{
						if (!$f->destroy())
						{
							$this->addError($f->getError());
							return false;
						}
					}
				}
			}
			else
			{
				$val = trim($data == null ? '' : $data);

				$field = null;

				if (isset($keep[$key]))
				{
					$field = $keep[$key];
				}

				if (!($field instanceof Profile))
				{
					$field = Profile::blank();
				}

				// If value is empty
				if (!$val)
				{
					// If an existing field, remove it
					if ($field->get('id'))
					{
						if (!$field->destroy())
						{
							$this->addError($field->getError());
							return false;
						}
					}

					// Move along. Nothing to see here.
					continue;
				}

				$field->set(array(
					'user_id'       => $this->get('id'),
					'profile_key'   => $key,
					'profile_value' => $val,
					'ordering'      => $i,
					'access'        => (isset($access[$key]) ? $access[$key] : $field->get('access', 5))
				));

				if (!$field->save())
				{
					$this->addError($field->getError());
					return false;
				}
			}

			$i++;
		}

		// Fire the onUserAfterSaveProfile event
		Event::trigger('user.onUserAfterSaveProfile', array($user, $profile, $access));

		return true;
	}

	/**
	 * Delete the record and all associated data
	 *
	 * @return  boolean  False if error, True on success
	 */
	public function destroy()
	{
		$data = $this->toArray();

		Event::trigger('user.onUserBeforeDelete', array($data));

		// Remove profile fields
		foreach ($this->profiles()->rows() as $field)
		{
			if (!$field->destroy())
			{
				$this->addError($field->getError());
				return false;
			}
		}

		// Remove notes
		foreach ($this->notes()->rows() as $note)
		{
			if (!$note->destroy())
			{
				$this->addError($note->getError());
				return false;
			}
		}

		// Remove hosts
		foreach ($this->hosts()->rows() as $host)
		{
			if (!$host->destroy())
			{
				$this->addError($host->getError());
				return false;
			}
		}

		// Remove tags
		$this->tag('');

		// Attempt to delete the record
		$result = parent::destroy();

		if ($result)
		{
			Event::trigger('user.onUserAfterDelete', array($data, true, $this->getError()));
		}

		return $result;
	}

	/**
	 * Clears all terms of use agreements
	 *
	 * @return  bool
	 */
	public static function clearTerms()
	{
		$tbl = self::blank();

		$query = $tbl->getQuery()
			->update($tbl->getTableName())
			->set(array('usageAgreement' => 0));

		return $query->execute();
	}

	/**
	 * Get records
	 *
	 * @param   integer  $limit
	 * @param   integer  $offset
	 * @return  object
	 */
	public static function searchResults($limit, $offset = 0)
	{
		return self::all()
			->start($offset)
			->limit($limit)
			->whereEquals('block', 0)
			->where('activation', '>', 0)
			->where('approved', '>', 0)
			->rows();
	}

	/**
	 * Get a record count
	 *
	 * @return  integer
	 */
	public static function searchTotal()
	{
		return self::all()
			->whereEquals('block', 0)
			->where('activation', '>', 0)
			->where('approved', '>', 0)
			->total();
	}

	/**
	 * Namespace used for solr Search
	 * @return string
	 */
	public static function searchNamespace()
	{
		$searchNamespace = 'member';
		return $searchNamespace;
	}

	/*
	 * Generate solr search Id
	 * @return string
	 */
	public function searchId()
	{
		$searchId = self::searchNamespace() . '-' . $this->id;
		return $searchId;
	}

	/**
	 * Get a record
	 *
	 * @return  array
	 */
	public function searchResult()
	{
		if ($this->get('activation') <= 0 || $this->get('block') > 0)
		{
			return false;
		}
		$obj = new stdClass;
		$obj->hubtype = self::searchNamespace();
		$obj->id      = $this->searchId();
		$obj->title   = $this->get('name');

		$base = rtrim(Request::base(), '/');
		$obj->url = rtrim(Request::root(), '/') . Route::urlForClient('site', 'index.php?option=com_members' . '&id=' . $this->get('id'));

		// @TODO: Add more fields to the SOLR core.
		$fields = $this->profiles()
		  ->whereIn('profile_key', ['organization', 'bio'])
		  ->rows()
		  ->toObject();
		$description = '';
		foreach ($fields as $field)
		{
			if ($field->access == 1)
			{
				$description .= $field->profile_value . ' ';
			}
		}
		$obj->description = $description;

		$access = $this->get('access');
		if ($access == 1)
		{
			$obj->access_level = 'public';
			$obj->owner = $this->get('id');
			$obj->owner_type = 'user';
		}
		else
		{
			$obj->access_level = 'private';
			$obj->owner = $this->get('id');
			$obj->owner_type = 'user';
		}

		return $obj;
	}

	/*
	 * Generate new user secret value
	 * @return string
	 */
	public static function generateSecret()
	{
		// create 32-character secret
		$secretLength = 32;
		return \Hubzero\User\Password::genRandomPassword($secretLength);
	}
}
