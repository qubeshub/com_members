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
 * @author    Shawn Rice <zooley@purdue.edu>
 * @copyright Copyright 2005-2011 Purdue University. All rights reserved.
 * @license   http://www.gnu.org/licenses/lgpl-3.0.html LGPLv3
 */

namespace Components\Members\Admin\Controllers;

use Hubzero\Component\AdminController;
use Filesystem;
use Request;
use Route;
use Lang;
use App;

/**
 * Manage files for a member
 */
class Media extends AdminController
{
	/**
	 * Upload a file
	 *
	 * @return  void
	 */
	public function uploadTask()
	{
		// Check for request forgeries
		Request::checkToken() or exit('Invalid token');

		// Incoming
		$id      = Request::getInt('id', 0);
		$curfile = Request::getVar('file', '');
		$file    = Request::getVar('upload', '', 'files', 'array');

		// Build upload path
		$dir  = \Hubzero\Utility\String::pad($id);
		$path = PATH_APP . DS . trim($this->config->get('webpath', '/site/members'), DS) . DS . $dir;

		//allowed extensions for uplaod
		$allowedExtensions = array('png', 'jpe', 'jpeg', 'jpg', 'gif');

		//max upload size
		$sizeLimit = $this->config->get('maxAllowed', '40000000');

		// make sure we have id
		if (!$id)
		{
			$this->setError(Lang::txt('COM_MEMBERS_NO_ID'));
			$this->displayTask($curfile, $id);
			return;
		}

		// make sure we have a file
		if (!$file['name'])
		{
			$this->setError(Lang::txt('COM_MEMBERS_NO_FILE'));
			$this->displayTask($curfile, $id);
			return;
		}

		// make sure we have an upload path
		if (!is_dir($path))
		{
			if (!Filesystem::makeDirectory($path))
			{
				$this->setError(Lang::txt('COM_MEMBERS_UNABLE_TO_CREATE_UPLOAD_PATH'));
				$this->displayTask($curfile, $id);
				return;
			}
		}

		// make sure file is not empty
		if ($file['size'] == 0)
		{
			$this->setError(Lang::txt('COM_MEMBERS_FILE_HAS_NO_SIZE'));
			$this->displayTask($curfile, $id);
			return;
		}

		// make sure file is not empty
		if ($file['size'] > $sizeLimit)
		{
			$max = preg_replace('/<abbr \w+=\\"\w+\\">(\w{1,3})<\\/abbr>/', '$1', \Hubzero\Utility\Number::formatBytes($sizeLimit));
			$this->setError(Lang::txt('FILE_SIZE_TOO_BIG', $max));
			$this->displayTask($curfile, $id);
			return;
		}

		// must be in allowed extensions
		$pathInfo = pathinfo($file['name']);
		$ext = $pathInfo['extension'];
		if (!in_array($ext, $allowedExtensions))
		{
			$these = implode(', ', $allowedExtensions);
			$this->setError(Lang::txt('COM_MEMBERS_FILE_TYPE_NOT_ALLOWED', $these));
			$this->displayTask($curfile, $id);
			return;
		}

		// build needed paths
		$filePath    = $path . DS . $file['name'];
		$profilePath = $path . DS . 'profile.png';
		$thumbPath   = $path . DS . 'thumb.png';

		// upload image
		if (!Filesystem::upload($file['tmp_name'], $filePath))
		{
			$this->setError(Lang::txt('COM_MEMBERS_ERROR_UPLOADING'));
			$this->displayTask($curfile, $id);
			return;
		}

		// create profile pic
		$imageProcessor = new \Hubzero\Image\Processor($filePath);
		if (count($imageProcessor->getErrors()) == 0)
		{
			$imageProcessor->autoRotate();
			$imageProcessor->resize(400);
			$imageProcessor->setImageType(IMAGETYPE_PNG);
			$imageProcessor->save($profilePath);
		}

		// create thumb
		$imageProcessor = new \Hubzero\Image\Processor($filePath);
		if (count($imageProcessor->getErrors()) == 0)
		{
			$imageProcessor->resize(50, false, true, true);
			$imageProcessor->save($thumbPath);
		}

		// update profile
		$profile = \Hubzero\User\Profile::getInstance($id);
		$profile->set('picture', 'profile.png');
		if (!$profile->update())
		{
			$this->setError($profile->getError());
		}

		// remove orig file
		unlink($filePath);

		// Push through to the image view
		$this->displayTask('profile.png', $id);
	}

	/**
	 * Delete a file
	 *
	 * @return  void
	 */
	public function removeTask()
	{
		// Check for request forgeries
		Request::checkToken('get') or exit('Invalid token');

		// Incoming member ID
		$id = Request::getInt('id', 0);
		if (!$id)
		{
			$this->setError(Lang::txt('COM_MEMBERS_NO_ID'));
			$this->displayTask('', $id);
			return;
		}

		// Incoming file
		$file = Request::getVar('file', '');
		if (!$file)
		{
			$this->setError(Lang::txt('COM_MEMBERS_NO_FILE'));
			$this->displayTask('', $id);
			return;
		}

		// Build the file path
		$dir  = \Hubzero\Utility\String::pad($id);
		$path = PATH_APP . DS . trim($this->config->get('webpath', '/site/members'), DS) . DS . $dir;

		// if we have file
		if (!file_exists($path . DS . $file) or !$file)
		{
			$this->setError(Lang::txt('COM_MEMBERS_FILE_NOT_FOUND'));
		}
		else
		{
			$ih = new \Components\Members\Helpers\ImgHandler();

			// Attempt to delete the file
			if (!Filesystem::delete($path . DS . $file))
			{
				$this->setError(Lang::txt('COM_MEMBERS_UNABLE_TO_DELETE_FILE'));
				$this->displayTask($file, $id);
				return;
			}

			// Get the file thumbnail name
			if ($file == 'profile.png')
			{
				$curthumb = 'thumb.png';
			}
			else
			{
				$curthumb = $ih->createThumbName($file);
			}

			// Remove the thumbnail
			if (file_exists($path . DS . $curthumb))
			{
				if (!Filesystem::delete($path . DS . $curthumb))
				{
					$this->setError(Lang::txt('COM_MEMBERS_UNABLE_TO_DELETE_FILE'));
					$this->displayTask($file, $id);
					return;
				}
			}

			// Instantiate a profile, change some info and save
			$profile = \Hubzero\User\Profile::getInstance($id);
			$profile->set('picture', '');
			if (!$profile->update())
			{
				$this->setError($profile->getError());
			}

			$file = '';
		}

		$this->displayTask($file, $id);
	}

	/**
	 * Display a file and its info
	 *
	 * @param   string   $file  File name
	 * @param   integer  $id    User ID
	 * @return  void
	 */
	public function displayTask($file='', $id=0)
	{
		// Load the component config
		$this->view->config = $this->config;

		// Incoming
		if (!$id)
		{
			$id = Request::getInt('id', 0);
		}
		$this->view->id = $id;

		if (!$file)
		{
			$file = Request::getVar('file', '');
		}
		$this->view->file = $file;

		// Build the file path
		$this->view->dir  = \Hubzero\Utility\String::pad($id);
		$this->view->path = PATH_APP . DS . trim($this->config->get('webpath', '/site/members'), DS) . DS . $this->view->dir;

		// Set any errors
		foreach ($this->getErrors() as $error)
		{
			$this->view->setError($error);
		}

		// Output the HTML
		$this->view
			->setLayout('display')
			->display();
	}
}

