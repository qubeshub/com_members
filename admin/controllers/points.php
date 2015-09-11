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
 * @author    Alissa Nedossekina <alisa@purdue.edu>
 * @copyright Copyright 2005-2015 HUBzero Foundation, LLC.
 * @license   http://www.gnu.org/licenses/lgpl-3.0.html LGPLv3
 */

namespace Components\Members\Admin\Controllers;

use Hubzero\Component\AdminController;
use Hubzero\Bank\MarketHistory;
use Notify;
use Request;
use Config;
use Route;
use User;
use Date;
use Lang;
use App;

/**
 * Members controller class for user points
 */
class Points extends AdminController
{
	/**
	 * Display an overview of point earnings
	 *
	 * @return     void
	 */
	public function displayTask()
	{
		// Get top earners
		$this->database->setQuery("SELECT * FROM #__users_points ORDER BY earnings DESC, balance DESC LIMIT 15");
		$this->view->rows = $this->database->loadObjectList();

		$BT = new \Hubzero\Bank\Transaction($this->database);

		$thismonth = Date::of('now')->format('Y-m');
		$lastmonth = Date::of(time() - (32 * 24 * 60 * 60))->format('Y-m');

		// Get overall earnings
		$this->view->stats[] = array(
			'memo'          => 'Earnings - Total',
			'class'         => 'earntotal',
			'alltimepts'    => $BT->getTotals('', 'deposit', '', 0, '', '', 1, ''),
			'thismonthpts'  => $BT->getTotals('', 'deposit', '', 0, '', '', 1, $thismonth),
			'lastmonthpts'  => $BT->getTotals('', 'deposit', '', 0, '', '', 1, $lastmonth),
			'alltimetran'   => $BT->getTotals('', 'deposit', '', 0, '', '', 1, '', $calc=2),
			'thismonthtran' => $BT->getTotals('', 'deposit', '', 0, '', '', 1, $thismonth, $calc=2),
			'lastmonthtran' => $BT->getTotals('', 'deposit', '', 0, '', '', 1, $lastmonth, $calc=2),
			'avg'           => round($BT->getTotals('', 'deposit', '', 0, '', '', 1, '', $calc=1))
		);

		// Get overall earnings on Answers
		$this->view->stats[] = array(
			'memo'          => 'Earnings: Answers',
			'class'         => 'earn',
			'alltimepts'    => $BT->getTotals('answers', 'deposit', '', 0, '', '', 1, ''),
			'thismonthpts'  => $BT->getTotals('answers', 'deposit', '', 0, '', '', 1, $thismonth),
			'lastmonthpts'  => $BT->getTotals('answers', 'deposit', '', 0, '', '', 1, $lastmonth),
			'alltimetran'   => $BT->getTotals('answers', 'deposit', '', 0, '', '', 1, '', $calc=2),
			'thismonthtran' => $BT->getTotals('answers', 'deposit', '', 0, '', '', 1, $thismonth, $calc=2),
			'lastmonthtran' => $BT->getTotals('answers', 'deposit', '', 0, '', '', 1, $lastmonth, $calc=2),
			'avg'           => round($BT->getTotals('answers', 'deposit', '', 0, '', '', 1, '', $calc=1))
		);

		// Get overall earnings on Wishes
		$this->view->stats[] = array(
			'memo'          => 'Earnings: Wish List',
			'class'         => 'earn',
			'alltimepts'    => $BT->getTotals('wish', 'deposit', '', 0, '', '', 1, ''),
			'thismonthpts'  => $BT->getTotals('wish', 'deposit', '', 0, '', '', 1, $thismonth),
			'lastmonthpts'  => $BT->getTotals('wish', 'deposit', '', 0, '', '', 1, $lastmonth),
			'alltimetran'   => $BT->getTotals('wish', 'deposit', '', 0, '', '', 1, '', $calc=2),
			'thismonthtran' => $BT->getTotals('wish', 'deposit', '', 0, '', '', 1, $thismonth, $calc=2),
			'lastmonthtran' => $BT->getTotals('wish', 'deposit', '', 0, '', '', 1, $lastmonth, $calc=2),
			'avg'           => round($BT->getTotals('wish', 'deposit', '', 0, '', '', 1, '', $calc=1))
		);

		// Get overall spending
		$this->view->stats[] = array(
			'memo'          => 'Spending - Total',
			'class'         => 'spendtotal',
			'alltimepts'    => $BT->getTotals('', 'withdraw', '', 0, '', '', 1, ''),
			'thismonthpts'  => $BT->getTotals('', 'withdraw', '', 0, '', '', 1, $thismonth),
			'lastmonthpts'  => $BT->getTotals('', 'withdraw', '', 0, '', '', 1, $lastmonth),
			'alltimetran'   => $BT->getTotals('', 'withdraw', '', 0, '', '', 1, '', $calc=2),
			'thismonthtran' => $BT->getTotals('', 'withdraw', '', 0, '', '', 1, $thismonth, $calc=2),
			'lastmonthtran' => $BT->getTotals('', 'withdraw', '', 0, '', '', 1, $lastmonth, $calc=2),
			'avg'           => round($BT->getTotals('', 'withdraw', '', 0, '', '', 1, '', $calc=1))
		);

		// Get overall spending in Store
		$this->view->stats[] = array(
			'memo'          => 'Spending: Store',
			'class'         => 'spend',
			'alltimepts'    => $BT->getTotals('store', 'withdraw', '', 0, '', '', 1, ''),
			'thismonthpts'  => $BT->getTotals('store', 'withdraw', '', 0, '', '', 1, $thismonth),
			'lastmonthpts'  => $BT->getTotals('store', 'withdraw', '', 0, '', '', 1, $lastmonth),
			'alltimetran'   => $BT->getTotals('store', 'withdraw', '', 0, '', '', 1, '', $calc=2),
			'thismonthtran' => $BT->getTotals('store', 'withdraw', '', 0, '', '', 1, $thismonth, $calc=2),
			'lastmonthtran' => $BT->getTotals('store', 'withdraw', '', 0, '', '', 1, $lastmonth, $calc=2),
			'avg'           => round($BT->getTotals('store', 'withdraw', '', 0, '', '', 1, '', $calc=1))
		);

		// Get overall spending on Answers
		$this->view->stats[] = array(
			'memo'          => 'Spending: Answers',
			'class'         => 'spend',
			'alltimepts'    => $BT->getTotals('answers', 'withdraw', '', 0, '', '', 1, ''),
			'thismonthpts'  => $BT->getTotals('answers', 'withdraw', '', 0, '', '', 1, $thismonth),
			'lastmonthpts'  => $BT->getTotals('answers', 'withdraw', '', 0, '', '', 1, $lastmonth),
			'alltimetran'   => $BT->getTotals('answers', 'withdraw', '', 0, '', '', 1, '', $calc=2),
			'thismonthtran' => $BT->getTotals('answers', 'withdraw', '', 0, '', '', 1, $thismonth, $calc=2),
			'lastmonthtran' => $BT->getTotals('answers', 'withdraw', '', 0, '', '', 1, $lastmonth, $calc=2),
			'avg'           => round($BT->getTotals('answers', 'withdraw', '', 0, '', '', 1, '', $calc=1))
		);

		// Get overall spending on Wishes
		$this->view->stats[] = array(
			'memo'          => 'Spending: Wish List',
			'class'         => 'spend',
			'alltimepts'    => $BT->getTotals('wish', 'withdraw', '', 0, '', '', 1, ''),
			'thismonthpts'  => $BT->getTotals('wish', 'withdraw', '', 0, '', '', 1, $thismonth),
			'lastmonthpts'  => $BT->getTotals('wish', 'withdraw', '', 0, '', '', 1, $lastmonth),
			'alltimetran'   => $BT->getTotals('wish', 'withdraw', '', 0, '', '', 1, '', $calc=2),
			'thismonthtran' => $BT->getTotals('wish', 'withdraw', '', 0, '', '', 1, $thismonth, $calc=2),
			'lastmonthtran' => $BT->getTotals('wish', 'withdraw', '', 0, '', '', 1, $lastmonth, $calc=2),
			'avg'           => round($BT->getTotals('wish', 'withdraw', '', 0, '', '', 1, '', $calc=1))
		);

		// Get royalties
		$this->view->stats[] = array(
			'memo'          => 'Royalties - Total',
			'class'         => 'royaltytotal',
			'alltimepts'    => $BT->getTotals('', 'deposit', '', $royalty=1, '', '', 1, ''),
			'thismonthpts'  => $BT->getTotals('', 'deposit', '', $royalty=1, '', '', 1, $thismonth),
			'lastmonthpts'  => $BT->getTotals('', 'deposit', '', $royalty=1, '', '', 1, $lastmonth),
			'alltimetran'   => $BT->getTotals('', 'deposit', '', $royalty=1, '', '', 1, '', $calc=2),
			'thismonthtran' => $BT->getTotals('', 'deposit', '', $royalty=1, '', '', 1, $thismonth, $calc=2),
			'lastmonthtran' => $BT->getTotals('', 'deposit', '', $royalty=1, '', '', 1, $lastmonth, $calc=2),
			'avg'           => round($BT->getTotals('', 'deposit', '', $royalty=1, '', '', 1, '', $calc=1))
		);

		// Get royalties on answers
		$this->view->stats[] = array(
			'memo'          => 'Royalties: Answers',
			'class'         => 'royalty',
			'alltimepts'    => $BT->getTotals('answers', 'deposit', '', $royalty=1, '', '', 1, ''),
			'thismonthpts'  => $BT->getTotals('answers', 'deposit', '', $royalty=1, '', '', 1, $thismonth),
			'lastmonthpts'  => $BT->getTotals('answers', 'deposit', '', $royalty=1, '', '', 1, $lastmonth),
			'alltimetran'   => $BT->getTotals('answers', 'deposit', '', $royalty=1, '', '', 1, '', $calc=2),
			'thismonthtran' => $BT->getTotals('answers', 'deposit', '', $royalty=1, '', '', 1, $thismonth, $calc=2),
			'lastmonthtran' => $BT->getTotals('answers', 'deposit', '', $royalty=1, '', '', 1, $lastmonth, $calc=2),
			'avg'           => round($BT->getTotals('answers', 'deposit', '', $royalty=1, '', '', 1, '', $calc=1))
		);

		// Get royalties on reviews
		$this->view->stats[] = array(
			'memo'          => 'Royalties: Reviews',
			'class'         => 'royalty',
			'alltimepts'    => $BT->getTotals('review', 'deposit', '', $royalty=1, '', '', 1, ''),
			'thismonthpts'  => $BT->getTotals('review', 'deposit', '', $royalty=1, '', '', 1, $thismonth),
			'lastmonthpts'  => $BT->getTotals('review', 'deposit', '', $royalty=1, '', '', 1, $lastmonth),
			'alltimetran'   => $BT->getTotals('review', 'deposit', '', $royalty=1, '', '', 1, '', $calc=2),
			'thismonthtran' => $BT->getTotals('review', 'deposit', '', $royalty=1, '', '', 1, $thismonth, $calc=2),
			'lastmonthtran' => $BT->getTotals('review', 'deposit', '', $royalty=1, '', '', 1, $lastmonth, $calc=2),
			'avg'           => round($BT->getTotals('review', 'deposit', '', $royalty=1, '', '', 1, '', $calc=1))
		);

		// Get royalties on resource contributions
		$this->view->stats[] = array(
			'memo'          => 'Royalties: Resources',
			'class'         => 'royalty',
			'alltimepts'    => $BT->getTotals('resource', 'deposit', '', $royalty=1, '', '', 1, ''),
			'thismonthpts'  => $BT->getTotals('resource', 'deposit', '', $royalty=1, '', '', 1, $thismonth),
			'lastmonthpts'  => $BT->getTotals('resource', 'deposit', '', $royalty=1, '', '', 1, $lastmonth),
			'alltimetran'   => $BT->getTotals('resource', 'deposit', '', $royalty=1, '', '', 1, '', $calc=2),
			'thismonthtran' => $BT->getTotals('resource', 'deposit', '', $royalty=1, '', '', 1, $thismonth, $calc=2),
			'lastmonthtran' => $BT->getTotals('resource', 'deposit', '', $royalty=1, '', '', 1, $lastmonth, $calc=2),
			'avg'           => round($BT->getTotals('resource', 'deposit', '', $royalty=1, '', '', 1, '', $calc=1))
		);

		// Set any errors
		foreach ($this->getErrors() as $error)
		{
			$this->view->setError($error);
		}

		// Output the HTML
		$this->view->display();
	}

	/**
	 * Edit an entry
	 *
	 * @return     void
	 */
	public function editTask()
	{
		if ($uid = Request::getInt('uid', 0))
		{
			$this->view->row = new \Hubzero\Bank\Account($this->database);
			$this->view->row->load_uid($uid);

			if (!$this->view->row->balance)
			{
				$this->view->row->uid = $uid;
				$this->view->row->balance = 0;
				$this->view->row->earnings = 0;
			}

			$this->database->setQuery("SELECT * FROM `#__users_transactions` WHERE uid=" . $uid . " ORDER BY created DESC, id DESC");
			$this->view->history = $this->database->loadObjectList();
		}
		else
		{
			$this->view->setLayout('find');
		}

		// Set any errors
		foreach ($this->getErrors() as $error)
		{
			$this->view->setError($error);
		}

		// Output the HTML
		$this->view->display();
	}

	/**
	 * Cancel a task and redirect to main view
	 *
	 * @return     void
	 */
	public function cancelTask()
	{
		App::redirect(
			Route::url('index.php?option=' . $this->_option . '&controller=' . $this->_controller, false)
		);
	}

	/**
	 * Save an entry
	 *
	 * @return     void
	 */
	public function saveTask()
	{
		// Check for request forgeries
		Request::checkToken();

		$id = Request::getInt('id', 0);

		$row = new \Hubzero\Bank\Account($this->database);
		if (!$row->bind($_POST))
		{
			App::abort(500, $row->getError());
			return;
		}

		$row->uid      = intval($row->uid);
		$row->balance  = intval($row->balance);
		$row->earnings = intval($row->earnings);

		if (isset($_POST['amount']) && intval($_POST['amount'])>0 && intval($_POST['amount']))
		{
			$data = array(
				'uid'         => $row->uid,
				'type'        => Request::getVar('type', ''),
				'category'    => Request::getVar('category', 'general', 'post'),
				'amount'      => Request::getInt('amount', 0),
				'description' => Request::getVar('description', 'Reason unspecified', 'post'),
				'created'     => Date::toSql()
			);

			switch ($data['type'])
			{
				case 'withdraw':
					$row->balance  -= $data['amount'];
				break;
				case 'deposit':
					$row->balance  += $data['amount'];
					$row->earnings += $data['amount'];
				break;
				case 'creation':
					$row->balance  = $data['amount'];
					$row->earnings = $data['amount'];
				break;
			}

			$data['balance'] = $row->balance;

			$BT = new \Hubzero\Bank\Transaction($this->database);
			if ($data['description'] == '')
			{
				$data['description'] = 'Reason unspecified';
			}
			if ($data['category'] == '')
			{
				$data['category'] = 'general';
			}

			if (!$BT->bind($data))
			{
				App::abort(500, $row->getError());
				return;
			}
			if (!$BT->check())
			{
				App::abort(500, $row->getError());
				return;
			}
			if (!$BT->store())
			{
				App::abort(500, $row->getError());
				return;
			}
		}

		if (!$row->check())
		{
			App::abort(500, $row->getError());
			return;
		}

		if (!$row->store())
		{
			App::abort(500, $row->getError());
			return;
		}

		App::redirect(
			Route::url('index.php?option=' . $this->_option . '&controller=' . $this->_controller . 'task=edit&uid=' . $row->uid, false),
			Lang::txt('User info saved')
		);
	}

	/**
	 * Configure items that can earn points
	 *
	 * @return     void
	 */
	public function configTask()
	{
		$this->database->setQuery("SELECT * FROM #__users_points_config");
		$this->view->params = $this->database->loadObjectList();

		// Set any errors
		if ($this->getError())
		{
			$this->view->setError($this->getError());
		}

		// Output the HTML
		$this->view->display();
	}

	/**
	 * Save config settings for items that can earn points
	 *
	 * @return     void
	 */
	public function saveconfigTask()
	{
		// Check for request forgeries
		Request::checkToken();

		$points = Request::getVar('points', array());
		$descriptions = Request::getVar('description', array());
		$aliases = Request::getVar('alias', array());

		$this->database->setQuery('DELETE FROM `#__users_points_config`');
		$this->database->query();

		for ($i=0; $i < count($points); $i++)
		{
			$point = intval($points[$i]);
			$description = $descriptions[$i];
			$alias = $aliases[$i];
			if ($point)
			{
				$id = intval($i);
				$this->database->setQuery("INSERT INTO `#__users_points_config` (`id`,`description`,`alias`,`points`) VALUES ($id,'$description','$alias', '$point')");
				$this->database->query();
			}
		}

		App::redirect(
			Route::url('index.php?option=' . $this->_option . '&controller=' . $this->_controller . '&task=config', false),
			Lang::txt('Config Saved')
		);
	}

	/**
	 * Perform batch operations
	 *
	 * @return     void
	 */
	public function batchTask()
	{
		// Set any errors
		foreach ($this->getErrors() as $error)
		{
			$this->view->setError($error);
		}

		// Output the HTML
		$this->view->display();
	}

	/**
	 * Process a batch of records
	 *
	 * @return     void
	 */
	public function process_batchTask()
	{
		// Check for request forgeries
		Request::checkToken();

		$duplicate = 0;

		$log = Request::getVar('log', array());
		$log = array_map('trim', $log);
		$log['category'] = ($log['category']) ? $log['category'] : 'general';
		$log['action']   = ($log['action'])   ? $log['action']   : 'batch';

		$data = Request::getVar('transaction', array());
		$data = array_map('trim', $data);

		$when = Date::toSql();

		// make sure this function was not already run
		$MH = new MarketHistory($this->database);
		$duplicate = $MH->getRecord($ref, $action, $category, '', $data['description']);

		if ($data['amount'] && $data['description'] && $data['users'])
		{
			if (!$duplicate)
			{ // run only once
				// get array of affected users
				$users = str_replace(' ', ',', $data['users']);
				$users = explode(',', $users);
				$users = array_unique($users); // get rid of duplicates

				foreach ($users as $user)
				{
					$validuser = \Hubzero\User\Profile::getInstance($user);
					if ($user && $validuser)
					{
						$BTL = new \Hubzero\Bank\Teller($this->database, $user);
						switch ($data['type'])
						{
							case 'withdraw':
								$BTL->withdraw($data['amount'], $data['description'], $log['category'], $log['ref']);
							break;
							case 'deposit':
								$BTL->deposit($data['amount'], $data['description'], $log['category'], $log['ref']);
							break;
						}
					}
				}

				// Save log
				$MH = new MarketHistory($this->database);
				$data['itemid']       = $log['ref'];
				$data['date']         = Date::toSql();
				$data['market_value'] = $data['amount'];
				$data['category']     = $log['category'];
				$data['action']       = $log['action'];
				$data['log']          = $data['description'];

				if (!$MH->bind($data))
				{
					$err = $MH->getError();
				}

				if (!$MH->store())
				{
					$err = $MH->getError();
				}

				Notify::success(Lang::txt('Batch transaction was processed successfully.'));
			}
			else
			{
				Notify::warning(Lang::txt('This batch transaction was already processed earlier. Use a different identifier if you need to run it again.'));
			}
		}
		else
		{
			Notify::error(Lang::txt('Could not process. Some required fields are missing.'));
		}

		// show output if run manually
		App::redirect(
			Route::url('index.php?option=' . $this->_option . '&controller=' . $this->_controller . '&task=batch', false)
		);
	}

	/**
	 * Calculate royalties
	 *
	 * @return     void
	 */
	public function royaltyTask()
	{
		$auto = Request::getInt('auto', 0);
		$action = 'royalty';

		if (!$auto)
		{
			$who = User::get('id');
		}
		else
		{
			$who = 0;
		}

		// What month/year is it now?
		$curmonth = Date::of('now')->format("F");
		$curyear = Date::of('now')->format("Y-m");
		$ref = strtotime($curyear);
		$this->_message = 'Royalties on Answers for '.$curyear.' were distributed successfully.';
		$rmsg = 'Royalties on Reviews for '.$curyear.' were distributed successfully.';
		$resmsg = 'Royalties on Resources for '.$curyear.' were distributed successfully.';

		// Make sure we distribute royalties only once/ month
		$MH = new MarketHistory($this->database);
		$royaltyAnswers = $MH->getRecord('', $action, 'answers', $curyear, $this->_message);
		$royaltyReviews = $MH->getRecord('', $action, 'reviews', $curyear, $rmsg);
		$royaltyResources = $MH->getRecord('', $action, 'resources', $curyear, $resmsg);

		// Include economy classes
		if (is_file(PATH_CORE . DS . 'components'. DS .'com_answers' . DS . 'helpers' . DS . 'economy.php'))
		{
			require_once(PATH_CORE . DS . 'components'. DS .'com_answers' . DS . 'helpers' . DS . 'economy.php');
		}
		if (is_file(PATH_CORE . DS . 'components'. DS .'com_resources' . DS . 'helpers' . DS . 'economy.php'))
		{
			require_once(PATH_CORE . DS . 'components'. DS .'com_resources' . DS . 'helpers' . DS . 'economy.php');
		}

		$AE = new \Components\Answers\Helpers\Economy($this->database);
		$accumulated = 0;

		// Get Royalties on Answers
		if (!$royaltyAnswers)
		{
			$rows = $AE->getQuestions();

			if ($rows)
			{
				foreach ($rows as $r)
				{
					$AE->distribute_points($r->id, $r->q_owner, $r->a_owner, $action);
					$accumulated = $accumulated + $AE->calculate_marketvalue($r->id, $action);
				}

				// make a record of royalty payment
				if (intval($accumulated) > 0)
				{
					$MH = new MarketHistory($this->database);
					$data['itemid']       = $ref;
					$data['date']         = Date::toSql();
					$data['market_value'] = $accumulated;
					$data['category']     = 'answers';
					$data['action']       = $action;
					$data['log']          = $this->_message;

					if (!$MH->bind($data))
					{
						$err = $MH->getError();
					}

					if (!$MH->store())
					{
						$err = $MH->getError();
					}
				}
			}
			else
			{
				$this->_message = 'There were no questions eligible for royalty payment. ';
			}
		}
		else
		{
			$this->_message = 'Royalties on Answers for '.$curyear.' were previously distributed. ';
		}

		// Get Royalties on Resource Reviews
		if (!$royaltyReviews)
		{
			// get eligible
			$RE = new \Components\Resources\Helpers\Economy\Reviews($this->database);
			$reviews = $RE->getReviews();

			// do we have ratings on reviews enabled?
			$plparam = Plugin::params('resources', 'reviews');
			$voting = $plparam->get('voting');

			$accumulated = 0;
			if ($reviews && $voting)
			{
				foreach ($reviews as $r)
				{
					$RE->distribute_points($r, $action);
					$accumulated = $accumulated + $RE->calculate_marketvalue($r, $action);
				}

				$this->_message .= $rmsg;
			}
			else
			{
				$this->_message .= 'There were no reviews eligible for royalty payment. ';
			}

			// make a record of royalty payment
			if (intval($accumulated) > 0)
			{
				$MH = new MarketHistory($this->database);
				$data['itemid']       = $ref;
				$data['date']         = Date::toSql();
				$data['market_value'] = $accumulated;
				$data['category']     = 'reviews';
				$data['action']       = $action;
				$data['log']          = $rmsg;

				if (!$MH->bind($data))
				{
					$err = $MH->getError();
				}

				if (!$MH->store())
				{
					$err = $MH->getError();
				}
			}
		}
		else
		{
			$this->_message .= 'Royalties on Reviews for '.$curyear.' were previously distributed. ';
		}

		// Get Royalties on Resources
		if (!$royaltyResources)
		{
			// get eligible
			$ResE = new \Components\Resources\Helpers\Economy\Reviews($this->database);
			$cons = $ResE->getCons();

			$accumulated = 0;
			if ($cons)
			{
				foreach ($cons as $con)
				{
					$ResE->distribute_points($con, $action);
					$accumulated = $accumulated + $con->ranking;
				}

				$this->_message .= $resmsg;
			}
			else
			{
				$this->_message .= 'There were no resources eligible for royalty payment.';
			}

			// make a record of royalty payment
			if (intval($accumulated) > 0)
			{
				$MH = new MarketHistory($this->database);
				$data['itemid']       = $ref;
				$data['date']         = Date::toSql();
				$data['market_value'] = $accumulated;
				$data['category']     = 'resources';
				$data['action']       = $action;
				$data['log']          = $resmsg;

				if (!$MH->bind($data))
				{
					$err = $MH->getError();
				}

				if (!$MH->store())
				{
					$err = $MH->getError();
				}
			}
		}
		else
		{
			$this->_message .= 'Royalties on Resources for ' . $curyear . ' were previously distributed.';
		}

		if (!$auto)
		{
			// show output if run manually
			App::redirect(
				Route::url('index.php?option=' . $this->_option . '&controller=' . $this->_controller, false),
				Lang::txt($this->_message)
			);
		}
	}
}

