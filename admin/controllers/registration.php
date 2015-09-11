<?php
/**
 * HUBzero CMS
 *
 * Copyright 2005-2015 HUBzero Foundation, LLC.
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
 * @copyright Copyright 2005-2015 HUBzero Foundation, LLC.
 * @license   http://www.gnu.org/licenses/lgpl-3.0.html LGPLv3
 */

namespace Components\Members\Admin\Controllers;

use Hubzero\Component\AdminController;
use Request;
use Config;
use Route;
use Lang;
use App;

include_once(PATH_CORE . DS . 'libraries' . DS . 'joomla' . DS . 'database' . DS . 'table' . DS . 'extension.php');

/**
 * Controller class for registration configuration
 */
class Registration extends AdminController
{
	/**
	 * Display configurations for registration
	 *
	 * @return  void
	 */
	public function displayTask()
	{
		$config = new \JForm('com_members.registration');
		$config->loadFile(dirname(dirname(__DIR__)) . DS . 'config' . DS . 'config.xml', true, '/config');
		$config->bind($this->config->toArray());

		$this->config = $config;

		$this->view->params = $config->getFieldset('registration');

		// Set any errors
		if ($this->getError())
		{
			$this->view->setError($this->getError());
		}

		// Output the HTML
		$this->view->display();
	}

	/**
	 * Save changes to the registration
	 *
	 * @return  void
	 */
	public function saveTask()
	{
		// Check for request forgeries
		Request::checkToken();

		$settings = Request::getVar('settings', array(), 'post');

		if (!is_array($settings) || empty($settings))
		{
			App::redirect(
				Route::url('index.php?option=' . $this->_option . '&controller=' . $this->_controller, false),
				Lang::txt('COM_MEMBERS_REGISTRATION_ERROR_MISSING_DATA'),
				'error'
			);
			return;
		}

		$arr = array();

		$component = new \JTableExtension($this->database);
		$component->load($component->find(array('element' => $this->_option, 'type' => 'component')));

		$params = new \Hubzero\Config\Registry($component->params);

		foreach ($settings as $name => $value)
		{
			$r = $value['create'] . $value['proxy'] . $value['update'] . $value['edit'];

			$params->set('registration' . trim($name), trim($r));
		}

		$component->params = $params->toString();
		$component->store();

		if (App::get('config')->get('caching'))
		{
			$handler = App::get('config')->get('cache_handler');

			App::get('config')->set($handler, array(
				'cachebase' => PATH_APP . '/cache/site'
			));

			$cache = new \Hubzero\Cache\Manager(\App::getRoot());
			$cache->storage($handler);
			$cache->clean('_system');
		}

		App::redirect(
			Route::url('index.php?option=' . $this->_option . '&controller=' . $this->_controller, false),
			Lang::txt('COM_MEMBERS_REGISTRATION_SAVED')
		);
	}
}
