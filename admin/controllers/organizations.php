<?php
/**
 * HUBzero CMS
 *
 * Copyright 2005-2011 Purdue University. All rights reserved.
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
 * @author    Nicholas J. Kisseberth <nkissebe@purdue.edu>
 * @copyright Copyright 2005-2011 Purdue University. All rights reserved.
 * @license   http://www.gnu.org/licenses/lgpl-3.0.html LGPLv3
 */

namespace Components\Members\Admin\Controllers;

use Hubzero\Component\AdminController;
use Components\Members\Tables\Organization;
use Notify;
use Request;
use Config;
use Route;
use Lang;
use App;

include_once(dirname(dirname(__DIR__)) . DS . 'tables' . DS . 'organization.php');

/**
 * Manage organizations for registration
 */
class Organizations extends AdminController
{
	/**
	 * Display all organizations
	 *
	 * @return  void
	 */
	public function displayTask()
	{
		// Get filters
		$this->view->filters = array(
			'search' => urldecode(Request::getState(
				$this->_option . '.' . $this->_controller . '.search',
				'search',
				''
			)),
			// Get paging variables
			'limit' => Request::getState(
				$this->_option . '.' . $this->_controller . '.limit',
				'limit',
				Config::get('list_limit'),
				'int'
			),
			'start' => Request::getState(
				$this->_option . '.' . $this->_controller . '.limitstart',
				'limitstart',
				0,
				'int'
			),
			'sort' => Request::getState(
				$this->_option . '.' . $this->_controller . '.sort',
				'filter_order',
				'organization'
			),
			'sort_Dir' => Request::getState(
				$this->_option . '.' . $this->_controller . '.sort_Dir',
				'filter_order_Dir',
				'ASC'
			)
		);
		// In case limit has been changed, adjust limitstart accordingly
		$this->view->filters['start'] = ($this->view->filters['limit'] != 0 ? (floor($this->view->filters['start'] / $this->view->filters['limit']) * $this->view->filters['limit']) : 0);

		$obj = new Organization($this->database);

		// Get a record count
		$this->view->total = $obj->find('count', $this->view->filters);

		// Get records
		$this->view->rows  = $obj->find('list', $this->view->filters);

		// Set any errors
		foreach ($this->getErrors() as $error)
		{
			$this->view->setError($error);
		}

		// Output the HTML
		$this->view->display();
	}

	/**
	 * Add a new organization
	 *
	 * @return     void
	 */
	public function addTask()
	{
		$this->editTask();
	}

	/**
	 * Edit an organization
	 *
	 * @param   mixed  $model  \Components\Members\Tables\Organization
	 * @return  void
	 */
	public function editTask($model=null)
	{
		Request::setVar('hidemainmenu', 1);

		if (!is_object($model))
		{
			// Incoming
			$id = Request::getVar('id', array(0));

			// Get the single ID we're working with
			if (is_array($id))
			{
				$id = (!empty($id)) ? $id[0] : 0;
			}

			// Initiate database class and load info
			$model = new Organization($this->database);
			$model->load($id);
		}

		$this->view->model = $model;

		// Set any errors
		foreach ($this->getErrors() as $error)
		{
			Notify::error($error);
		}

		// Output the HTML
		$this->view
			->setLayout('edit')
			->display();
	}

	/**
	 * Save a record and return to edit form
	 *
	 * @return  void
	 */
	public function applyTask()
	{
		$this->saveTask();
	}

	/**
	 * Save a record
	 *
	 * @return  void
	 */
	public function saveTask()
	{
		// Check for request forgeries
		Request::checkToken() or exit('Invalid token');

		// Load the tag object and bind the incoming data to it
		$model = new Organization($this->database);

		if (!$model->bind($_POST))
		{
			App::abort(500, $model->getError());
			return;
		}

		// Check content
		if (!$model->check())
		{
			$this->setError($model->getError());
			$this->editTask($model);
			return;
		}

		// Store new content
		if (!$model->store())
		{
			$this->setError($model->getError());
			$this->editTask($model);
			return;
		}

		Notify::success(Lang::txt('COM_MEMBERS_ORGANIZATIONS_SAVED'));

		if ($this->_task == 'apply')
		{
			return $this->editTask($model);
		}

		// Redirect
		App::redirect(
			Route::url('index.php?option=' . $this->_option . '&controller=' . $this->_controller, false)
		);
	}

	/**
	 * Remove an organization
	 *
	 * @return  void
	 */
	public function removeTask()
	{
		// Check for request forgeries
		Request::checkToken() or exit('Invalid token');

		// Incoming
		$ids = Request::getVar('id', array(), 'post');
		$ids = (!is_array($ids) ? array($ids) : $ids);

		// Get the single ID we're working with
		if (!is_array($ids))
		{
			$ids = array();
		}

		// Do we have any IDs?
		if (!empty($ids))
		{
			$model = new Organization($this->database);

			// Loop through each ID and delete the necessary items
			foreach ($ids as $id)
			{
				// Remove the organization type
				$model->delete(intval($id));
			}
		}

		// Output messsage and redirect
		App::redirect(
			Route::url('index.php?option=' . $this->_option . '&controller=' . $this->_controller, false),
			Lang::txt('COM_MEMBERS_ORGANIZATIONS_REMOVED')
		);
	}
}
