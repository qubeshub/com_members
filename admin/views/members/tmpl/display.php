<?php
/**
 * @package    hubzero-cms
 * @copyright  Copyright (c) 2005-2020 The Regents of the University of California.
 * @license    http://opensource.org/licenses/MIT MIT
 */

// No direct access
defined('_HZEXEC_') or die();

$canDo = Components\Members\Helpers\Admin::getActions('component');

Toolbar::title(Lang::txt('COM_MEMBERS'));

if ($canDo->get('core.admin')):
	Toolbar::preferences($this->option);
	Toolbar::getRoot()->appendButton('Link', 'buildprofile', 'COM_MEMBERS_PROFILE', Route::url('index.php?option=' . $this->option . '&controller=' . $this->controller . '&task=profile'));
	Toolbar::spacer();
	$export = 'index.php?option=' . $this->option . '&controller=exports&task=run';
	foreach ($this->filters as $key => $value):
		$export .= '&' . $key . '=' . $value;
	endforeach;
	Toolbar::getRoot()->appendButton('Link', 'download', 'COM_MEMBERS_MENU_EXPORT', Route::url($export));
	Toolbar::spacer();
endif;

if ($canDo->get('core.edit.state')):
	Toolbar::custom('clearTerms', 'remove', '', 'COM_MEMBERS_CLEAR_TERMS', false);
	Toolbar::spacer();
	Toolbar::publishList('confirm', 'COM_MEMBERS_CONFIRM');
	Toolbar::unpublishList('unconfirm', 'COM_MEMBERS_UNCONFIRM');
	Toolbar::divider();
	Toolbar::custom('block', 'cancel', '', 'COM_MEMBERS_BLOCK', true);
	Toolbar::custom('unblock', 'restore', '', 'COM_MEMBERS_UNBLOCK', true);
	Toolbar::spacer();
endif;

if ($canDo->get('core.create')):
	Toolbar::addNew();
endif;

if ($canDo->get('core.edit')):
	Toolbar::editList();
endif;

if ($canDo->get('core.delete')):
	Toolbar::deleteList('COM_MEMBERS_CONFIRMATION_WARNING');

    if ($canDo->get('core.deidentify')):
        Toolbar::custom('deidentify', 'eye-close', '', 'COM_MEMBERS_DEIDENTIFY', true);
    endif;
endif;

Toolbar::spacer();
Toolbar::help('users');

Html::behavior('tooltip');

$this->css()
	->js();
?>

<form action="<?php echo Route::url('index.php?option=' . $this->option . '&controller=' . $this->controller); ?>" method="post" name="adminForm" id="adminForm">
	<fieldset id="filter-bar">
		<div class="grid">
			<div class="col span4">
				<label for="filter_search"><?php echo Lang::txt('COM_MEMBERS_SEARCH_FOR'); ?></label>
				<input type="text" name="search" id="filter_search" class="filter" value="<?php echo $this->escape($this->filters['search']); ?>" placeholder="<?php echo Lang::txt('COM_MEMBERS_SEARCH_PLACEHOLDER'); ?>" />

				<input type="submit" value="<?php echo Lang::txt('COM_MEMBERS_GO'); ?>" />
				<button type="button" class="filter-clear"><?php echo Lang::txt('JSEARCH_RESET'); ?></button>
			</div>
			<div class="col span8">
				<label for="filter_emailConfirmed"><?php echo Lang::txt('COM_MEMBERS_FILTER_EMAIL_CONFIRMED'); ?></label>
				<select name="activation" id="filter_emailConfirmed" class="inputbox filter filter-submit">
					<option value="0"<?php if ($this->filters['activation'] == 0) { echo ' selected="selected"'; } ?>><?php echo Lang::txt('COM_MEMBERS_FILTER_EMAIL_CONFIRMED'); ?></option>
					<option value="1"<?php if ($this->filters['activation'] == 1) { echo ' selected="selected"'; } ?>><?php echo Lang::txt('COM_MEMBERS_FIELD_EMAIL_CONFIRMED_CONFIRMED'); ?></option>
					<option value="-1"<?php if ($this->filters['activation'] == -1) { echo ' selected="selected"'; } ?>><?php echo Lang::txt('COM_MEMBERS_FIELD_EMAIL_CONFIRMED_UNCONFIRMED'); ?></option>
				</select>

				<label for="filter-access"><?php echo Lang::txt('JFIELD_ACCESS_LABEL'); ?>:</label>
				<select name="access" id="filter-access" class="inputbox filter filter-submit">
					<option value=""><?php echo Lang::txt('JOPTION_SELECT_ACCESS');?></option>
					<?php echo Html::select('options', Html::access('assetgroups'), 'value', 'text', $this->filters['access']); ?>
				</select>

				<label for="filter-state"><?php echo Lang::txt('COM_MEMBERS_FILTER_STATE'); ?>:</label>
				<select name="state" id="filter-state" class="inputbox filter filter-submit">
					<option value="*"><?php echo Lang::txt('COM_MEMBERS_FILTER_STATE');?></option>
					<?php echo Html::select('options', Components\Members\Helpers\Admin::getStateOptions(), 'value', 'text', $this->filters['state']); ?>
				</select>

				<label for="filter-approved"><?php echo Lang::txt('COM_MEMBERS_FILTER_APPROVED'); ?>:</label>
				<select name="approved" id="filter-approved" class="inputbox filter filter-submit">
					<option value="*"><?php echo Lang::txt('COM_MEMBERS_FILTER_APPROVED');?></option>
					<?php echo Html::select('options', Components\Members\Helpers\Admin::getApprovedOptions(), 'value', 'text', $this->filters['approved']); ?>
				</select>

				<label for="filter-group_id"><?php echo Lang::txt('COM_MEMBERS_FILTER_USERGROUP'); ?>:</label>
				<select name="group_id" id="filter-group_id" class="inputbox filter filter-submit">
					<option value=""><?php echo Lang::txt('COM_MEMBERS_FILTER_USERGROUP');?></option>
					<?php echo Html::select('options', Components\Members\Helpers\Admin::getAccessGroups(), 'value', 'text', $this->filters['group_id']); ?>
				</select>

				<label for="filter-range"><?php echo Lang::txt('COM_MEMBERS_OPTION_FILTER_DATE'); ?>:</label>
				<select name="range" id="filter-range" class="inputbox filter filter-submit">
					<option value=""><?php echo Lang::txt('COM_MEMBERS_OPTION_FILTER_DATE');?></option>
					<?php echo Html::select('options', Components\Members\Helpers\Admin::getRangeOptions(), 'value', 'text', $this->filters['range']); ?>
				</select>
			</div>
		</div>
	</fieldset>

	<table class="adminlist">
		<thead>
			<tr>
				<th scope="col">
					<input type="checkbox" name="checkall-toggle" id="checkall-toggle" value="" class="checkbox-toggle toggle-all" />
					<label for="checkall-toggle" class="sr-only visually-hidden"><?php echo Lang::txt('JGLOBAL_CHECK_ALL'); ?></label>
				</th>
				<th scope="col" class="priority-2"><?php echo Html::grid('sort', 'COM_MEMBERS_COL_ID', 'id', @$this->filters['sort_Dir'], @$this->filters['sort']); ?></th>
				<th scope="col"><?php echo Html::grid('sort', 'COM_MEMBERS_COL_NAME', 'name', @$this->filters['sort_Dir'], @$this->filters['sort']); ?></th>
				<th scope="col" class="priority-5"><?php echo Html::grid('sort', 'COM_MEMBERS_COL_USERNAME', 'username', @$this->filters['sort_Dir'], @$this->filters['sort']); ?></th>
				<th scope="col" class="priority-6"><?php echo Html::grid('sort', 'COM_MEMBERS_COL_EMAIL', 'email', @$this->filters['sort_Dir'], @$this->filters['sort']); ?></th>
				<th scope="col" class="priority-3 nowrap"><?php echo Lang::txt('COM_MEMBERS_COL_GROUPS'); ?></th>
				<th scope="col" class="priority-4"><?php echo Lang::txt('COM_MEMBERS_STATUS'); ?></th>
				<th scope="col" class="priority-3"><?php echo Html::grid('sort', 'COM_MEMBERS_COL_REGISTERED', 'registerDate', @$this->filters['sort_Dir'], @$this->filters['sort']); ?></th>
				<th scope="col" class="priority-6"><?php echo Html::grid('sort', 'COM_MEMBERS_COL_LAST_VISIT', 'lastvisitDate', @$this->filters['sort_Dir'], @$this->filters['sort']); ?></th>
			</tr>
		</thead>
		<tfoot>
			<tr>
				<td colspan="9">
					<?php
					// Initiate paging
					echo $this->rows->pagination;
					?>
				</td>
			</tr>
		</tfoot>
		<tbody>
		<?php
		$k = 0;
		$i = 0;
		foreach ($this->rows as $row):
			$canEdit   = $canDo->get('core.edit');
			$canChange = User::authorise('core.edit.state', $this->option);

			// If this group is super admin and this user is not super admin, $canEdit is false
			if ((!User::authorise('core.admin')) && Hubzero\Access\Access::check($row->get('id'), 'core.admin')):
				$canEdit   = false;
				$canChange = false;
			endif;

			if (!$row->get('surname') && !$row->get('givenName')):
				$bits = explode(' ', $row->get('name'));

				$row->set('surname', array_pop($bits));

				if (count($bits) >= 1):
					$row->set('givenName', array_shift($bits));
				endif;

				if (count($bits) >= 1):
					$row->set('middleName', implode(' ', $bits));
				endif;
			endif;

			$row->set('name', $row->get('surname', Lang::txt('COM_MEMBERS_UNDEFINED')) . ', ' . $row->get('givenName', Lang::txt('COM_MEMBERS_UNDEFINED')) . ' ' . $row->get('middleName'));

			switch ($row->get('activation')):
				case '1':
					$task = 'unconfirm';
					$img = 'publish_g.png';
					$alt = Lang::txt('JYES');
					$state = 'publish';
					break;
				case '3':
					$task = 'unconfirm';
					$img = 'publish_g.png';
					$alt = Lang::txt('COM_MEMBERS_DOMAIN_SUPPLIED_EMAIL');
					$state = 'publish';
					break;
				default:
					$task = 'confirm';
					$img = 'publish_x.png';
					$alt = Lang::txt('JNO');
					$state = 'unpublish';
					break;
			endswitch;

			$groups = array();
			foreach ($row->accessgroups as $agroup):
				$groups[] = $this->accessgroups->seek($agroup->get('group_id'))->get('title');
			endforeach;
			$row->set('group_names', implode('<br />', $groups));

			$incomplete = false;
			$authenticator = 'hub';
			if (substr($row->get('email'), -8) == '@invalid'):
				$authenticator = Lang::txt('COM_MEMBERS_UNKNOWN');
				if ($lnk = Hubzero\Auth\Link::find_by_id(abs(intval($row->get('username'))))):
					$domain = Hubzero\Auth\Domain::find_by_id($lnk->auth_domain_id);
					$authenticator = $domain->authenticator;
				endif;
				$incomplete = true;
			endif;
			?>
			<tr class="<?php echo "row$k"; ?>">
				<td>
					<?php if ($canEdit) : ?>
						<input type="checkbox" name="id[]" id="cb<?php echo $i; ?>" value="<?php echo $row->get('id'); ?>" class="checkbox-toggle" />
						<label for="cb<?php echo $i; ?>" class="sr-only visually-hidden"><?php echo $row->get('id'); ?></label>
					<?php endif; ?>
				</td>
				<td class="priority-2">
					<?php echo $row->get('id'); ?>
				</td>
				<td>
					<div class="fltrt">
						<?php if ($count = $row->notes->count()) : ?>
							<a class="state filter" href="<?php echo Route::url('index.php?option=' . $this->option . '&controller=notes&search=uid%3A' . (int) $row->get('id')); ?>" title="<?php echo Lang::txt('COM_MEMBERS_FILTER_NOTES'); ?>">
								<span><?php echo Lang::txt('COM_MEMBERS_NOTES'); ?></span>
							</a>
							<a class="modal state notes" href="<?php echo Route::url('index.php?option=' . $this->option . '&controller=notes&tmpl=component&task=modal&id=' . (int) $row->get('id')); ?>" rel="{handler: 'iframe', size: {x: 800, y: 450}}" title="<?php echo Lang::txts('COM_MEMBERS_N_USER_NOTES', $count); ?>">
								<span><?php echo Lang::txt('COM_MEMBERS_NOTES'); ?></span>
							</a>
						<?php endif; ?>
						<a class="state notes" href="<?php echo Route::url('index.php?option=' . $this->option . '&controller=notes&task=add&user_id=' . (int) $row->get('id')); ?>" title="<?php echo Lang::txt('COM_MEMBERS_ADD_NOTE'); ?>">
							<span><?php echo Lang::txt('COM_MEMBERS_NOTES'); ?></span>
						</a>
					</div>
					<?php if ($canEdit) : ?>
						<a class="editlinktip hasTip" href="<?php echo Route::url('index.php?option=' . $this->option . '&controller=' . $this->controller . '&task=edit&id=' . $row->get('id')); ?>" title="<?php echo $this->escape(stripslashes($row->get('name')));/* ?>::<img border=&quot;1&quot; src=&quot;<?php echo $base . $picture; ?>&quot; name=&quot;imagelib&quot; alt=&quot;User photo&quot; width=&quot;40&quot; height=&quot;40&quot; /><br /><span class=&quot;glyph <?php echo ($row->public) ? 'public' : 'private'; ?>&quot;><?php echo ($row->public) ? 'public profile' : 'private profile'; </span>*/?>">
							<?php echo $this->escape($row->get('name')); ?>
						</a>
					<?php else : ?>
						<?php echo $this->escape($row->get('name')); ?>
					<?php endif; ?>
					<?php if (Config::get('debug')) : ?>
						<a class="permissions button" href="<?php echo Route::url('index.php?option=' . $this->option . '&controller=' . $this->controller . '&task=debug&id=' . $row->get('id'));?>">
							<?php echo Lang::txt('COM_MEMBERS_DEBUG_USER');?>
						</a>
					<?php endif; ?>
				</td>
				<td class="priority-5">
					<?php echo $incomplete ? '--' : $this->escape($row->get('username')); ?>
				</td>
				<td class="priority-6">
					<?php echo $incomplete ? '--' : $this->escape($row->get('email')); ?>
				</td>
				<td class="center priority-3">
					<?php if (substr_count($row->get('group_names'), "\n") > 1) : ?>
						<span class="hasTip" title="<?php echo Lang::txt('COM_MEMBERS_HEADING_GROUPS') . '::' . $row->get('group_names'); ?>"><?php echo Lang::txt('COM_MEMBERS_MULTIPLE_GROUPS'); ?></span>
					<?php else : ?>
						<?php echo $row->get('group_names'); ?>
					<?php endif; ?>
				</td>
				<td class="center priority-4">
					<?php if ($row->get('block')): ?>
						<div class="btn-group dropdown user-state blocked">
							<span class="btn hasTip" title="<?php echo Lang::txt('COM_MEMBERS_STATUS_BLOCKED_DESC'); ?>">
								<?php echo Lang::txt('COM_MEMBERS_STATUS_BLOCKED'); ?>
							</span>
							<?php if ($canChange) : ?>
								<span class="btn dropdown-toggle"></span>
								<ul class="dropdown-menu">
									<li>
										<a class="grid-action grid-boolean icon-unban" data-id="cb<?php echo $i; ?>" data-task="unblock" href="#toggle"><?php echo Lang::txt('COM_MEMBERS_ACTION_UNBLOCK'); ?></a>
									</li>
								</ul>
							<?php endif; ?>
						</div>
					<?php else : ?>
						<?php if ($incomplete): ?>
							<div class="btn-group dropdown user-state incomplete">
								<span class="btn hasTip" title="<?php echo Lang::txt('COM_MEMBERS_STATUS_INCOMPLETE_DESC'); ?>">
									<?php echo Lang::txt('COM_MEMBERS_STATUS_INCOMPLETE', $authenticator); ?>
								</span>
							</div>
							<!-- <span class="authenticator"><?php echo $authenticator; ?></span> -->
						<?php elseif ($row->get('activation') <= 0): ?>
							<div class="btn-group dropdown user-state unconfrmed">
								<span class="btn hasTip" title="<?php echo Lang::txt('COM_MEMBERS_STATUS_UNCONFIRMED_DESC'); ?>">
									<?php echo Lang::txt('COM_MEMBERS_STATUS_UNCONFIRMED'); ?>
								</span>
								<?php if ($canChange) : ?>
									<span class="btn dropdown-toggle"></span>
									<ul class="dropdown-menu">
										<li>
											<a class="grid-action grid-boolean icon-success" data-id="cb<?php echo $i; ?>" data-task="confirm" href="#toggle"><?php echo Lang::txt('COM_MEMBERS_ACTION_CONFIRM'); ?></a>
										</li>
										<li>
											<a class="grid-action grid-boolean icon-resend" data-id="cb<?php echo $i; ?>" data-task="resendConfirm" href="#toggle"><?php echo Lang::txt('COM_MEMBERS_ACTION_RESEND'); ?></a>
										</li>
										<li class="divider"></li>
										<li>
											<a class="grid-action grid-boolean icon-ban" data-id="cb<?php echo $i; ?>" data-task="block" href="#toggle"><?php echo Lang::txt('COM_MEMBERS_ACTION_BLOCK'); ?></a>
										</li>
									</ul>
								<?php endif; ?>
							</div>
						<?php elseif (!$row->get('approved')): ?>
							<div class="btn-group dropdown user-state confirmed unapproved">
								<span class="btn hasTip" title="<?php echo Lang::txt('COM_MEMBERS_STATUS_UNAPPROVED_DESC'); ?>">
									<?php echo Lang::txt('COM_MEMBERS_STATUS_UNAPPROVED'); ?>
								</span>
								<?php if ($canChange) : ?>
									<span class="btn dropdown-toggle"></span>
									<ul class="dropdown-menu">
										<li>
											<a class="grid-action grid-boolean icon-approve" data-id="cb<?php echo $i; ?>" data-task="approve" href="#toggle"><?php echo Lang::txt('COM_MEMBERS_ACTION_APPROVE'); ?></a>
										</li>
										<li class="divider"></li>
										<li>
											<a class="grid-action grid-boolean icon-ban" data-id="cb<?php echo $i; ?>" data-task="block" href="#toggle"><?php echo Lang::txt('COM_MEMBERS_ACTION_BLOCK'); ?></a>
										</li>
									</ul>
								<?php endif; ?>
							</div>
						<?php else: ?>
							<div class="btn-group dropdown user-state confirmed approved enabled">
								<span class="btn hasTip" title="<?php echo Lang::txt('COM_MEMBERS_STATUS_APPROVED_DESC'); ?>">
									<?php echo Lang::txt('COM_MEMBERS_STATUS_APPROVED'); ?>
								</span>
								<?php if ($canChange) : ?>
									<span class="btn dropdown-toggle"></span>
									<ul class="dropdown-menu">
										<li>
											<a class="grid-action grid-boolean icon-unapprove" data-id="cb<?php echo $i; ?>" data-task="disapprove" href="#toggle"><?php echo Lang::txt('COM_MEMBERS_ACTION_UNAPPROVE'); ?></a>
										</li>
										<li class="divider"></li>
										<li>
											<a class="grid-action grid-boolean icon-ban" data-id="cb<?php echo $i; ?>" data-task="block" href="#toggle"><?php echo Lang::txt('COM_MEMBERS_ACTION_BLOCK'); ?></a>
										</li>
									</ul>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					<?php endif; ?>
				</td>
				<td class="priority-3">
					<time datetime="<?php echo Date::of($row->get('registerDate'))->format('Y-m-dTh:i:s'); ?>"><?php echo Date::of($row->get('registerDate'))->toLocal('Y-m-d'); ?></time>
				</td>
				<td class="priority-6">
					<?php if (!$row->get('lastvisitDate') || $row->get('lastvisitDate') == '0000-00-00 00:00:00') : ?>
						<span class="never"><?php echo Lang::txt('COM_MEMBERS_NEVER'); ?></span>
					<?php else: ?>
						<time datetime="<?php echo Date::of($row->get('lastvisitDate'))->format('Y-m-dTh:i:s'); ?>"><?php echo Date::of($row->get('lastvisitDate'))->toLocal('Y-m-d'); ?></time>
					<?php endif; ?>
				</td>
			</tr>
			<?php
			$i++;
			$k = 1 - $k;
		endforeach;
		?>
		</tbody>
	</table>

	<?php if (User::authorise('core.create', $this->option) && User::authorise('core.edit', $this->option) && User::authorise('core.edit.state', $this->option)) : ?>
		<?php echo $this->loadTemplate('batch'); ?>
	<?php endif;?>

	<input type="hidden" name="option" value="<?php echo $this->option; ?>" />
	<input type="hidden" name="controller" value="<?php echo $this->controller; ?>" />
	<input type="hidden" name="task" value="" autocomplete="off" />
	<input type="hidden" name="boxchecked" value="0" />

	<input type="hidden" name="filter_order" value="<?php echo $this->escape($this->filters['sort']); ?>" />
	<input type="hidden" name="filter_order_Dir" value="<?php echo $this->escape($this->filters['sort_Dir']); ?>" />
	<?php echo Html::input('token'); ?>
</form>
