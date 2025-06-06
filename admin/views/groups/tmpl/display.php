<?php
/**
 * @package    hubzero-cms
 * @copyright  Copyright (c) 2005-2020 The Regents of the University of California.
 * @license    http://opensource.org/licenses/MIT MIT
 */

// No direct access
defined('_HZEXEC_') or die();

$canDo = (User::authorise('core.admin', 'com_groups') || User::authorise('core.manage', 'com_groups'));
?>
<div id="groups">
	<?php if ($this->getError()) { ?>
		<p class="error"><?php echo $this->getError(); ?></p>
	<?php } ?>

	<?php if ($canDo) { ?>
		<form action="<?php echo Route::url('index.php?option=' . $this->option . '&controller' . $this->controller . '&id=' . $this->id); ?>" method="post">
			<table>
				<tbody>
					<tr>
						<td>
							<input type="hidden" name="option" value="<?php echo $this->option; ?>" />
							<input type="hidden" name="controller" value="<?php echo $this->controller; ?>">
							<input type="hidden" name="tmpl" value="component" />
							<input type="hidden" name="id" value="<?php echo $this->id; ?>" />
							<input type="hidden" name="task" value="add" />
							<?php echo Html::input('token'); ?>

							<select name="gid">
								<option value=""><?php echo Lang::txt('COM_MEMBERS_SELECT'); ?></option>
								<?php
								if ($this->rows) foreach ($this->rows as $row)
								{
									echo '<option value="' . $row->gidNumber . '">' . $row->description . ' (' . $row->cn . ')</option>' . "\n";
								}
								?>
							</select>
							<select name="tbl">
								<option value="invitees"><?php echo Lang::txt('COM_MEMBERS_GROUPS_INVITEES'); ?></option>
								<option value="applicants"><?php echo Lang::txt('COM_MEMBERS_GROUPS_APPLICANTS'); ?></option>
								<option value="members" selected="selected"><?php echo Lang::txt('COM_MEMBERS_GROUPS_MEMBERS'); ?></option>
								<option value="managers"><?php echo Lang::txt('COM_MEMBERS_GROUPS_MANAGERS'); ?></option>
							</select>

							<input type="submit" value="<?php echo Lang::txt('COM_MEMBERS_GROUPS_ADD'); ?>" />
						</td>
					</tr>
				</tbody>
			</table>
		</form>
		<br />
	<?php } ?>

	<form action="<?php echo Route::url('index.php?option=' . $this->option . '&controller' . $this->controller . '&id=' . $this->id); ?>" method="post">
		<input type="hidden" name="option" value="<?php echo $this->option; ?>" />
		<input type="hidden" name="controller" value="<?php echo $this->controller; ?>">
		<input type="hidden" name="tmpl" value="component" />
		<input type="hidden" name="id" value="<?php echo $this->id; ?>" />
		<input type="hidden" name="task" value="update" />

		<table class="paramlist admintable">
			<tbody>
				<?php
				$applicants = \Hubzero\User\Helper::getGroups($this->id, 'applicants');
				$invitees   = \Hubzero\User\Helper::getGroups($this->id, 'invitees');
				$members    = \Hubzero\User\Helper::getGroups($this->id, 'members');
				$managers   = \Hubzero\User\Helper::getGroups($this->id, 'managers');

				$applicants = (is_array($applicants)) ? $applicants : array();
				$invitees   = (is_array($invitees)) ? $invitees : array();
				$members    = (is_array($members)) ? $members : array();
				$managers   = (is_array($managers)) ? $managers : array();

				$groups = array_merge($applicants, $invitees);
				$managerids = array();
				foreach ($managers as $manager)
				{
					$groups[] = $manager;
					$managerids[] = $manager->cn;
				}
				foreach ($members as $mem)
				{
					if (!in_array($mem->cn, $managerids))
					{
						$groups[] = $mem;
					}
				}

				$db = App::get('db');

				if (count($groups) > 0)
				{
					foreach ($groups as $group)
					{
						?>
						<tr>
							<td>
								<?php if ($canDo && User::authorise('core.edit', 'com_groups')) { ?>
									<a href="<?php echo Route::url('index.php?option=com_groups&controller=manage&task=edit&id=' . $group->cn); ?>" target="_parent">
										<?php echo $this->escape($group->description) . ' (' . $this->escape($group->cn) . ')'; ?>
									</a>
								<?php } else { ?>
									<?php echo $this->escape($group->description) . ' (' . $this->escape($group->cn) . ')'; ?>
								<?php } ?>
								<?php
								$db->setQuery("SELECT * FROM `#__xgroups_memberoption` WHERE userid=" . $db->quote($this->id) . " AND gidNumber=" . $db->quote($group->gidNumber));
								$options = $db->loadObjectList();
								if ($options)
								{
									foreach ($options as $option)
									{
										?>
										<div class="input-wrap">
											<label for="memberoption-<?php echo $this->escape($option->id); ?>"><?php echo $this->escape($option->optionname); ?></label>
											<input name="memberoption[<?php echo $this->escape($option->id); ?>]" id="memberoption-<?php echo $this->escape($option->id); ?>" size="3" value="<?php echo $this->escape($option->optionvalue); ?>" />
											<input type="submit" value="<?php echo Lang::txt('COM_MEMBERS_UPDATE'); ?>" />
										</div>
										<?php
									}
								}
								?>
							</td>
							<td>
								<?php
								$seen[] = $group->cn;

								if ($group->registered)
								{
									$status = Lang::txt('COM_MEMBERS_GROUPS_APPLICANT');
									if ($group->regconfirmed)
									{
										$status = Lang::txt('COM_MEMBERS_GROUPS_MEMBER');
										if ($group->manager)
										{
											$status = Lang::txt('COM_MEMBERS_GROUPS_MANAGER');
										}
									}
								}
								else
								{
									$status = Lang::txt('COM_MEMBERS_GROUPS_INVITEE');
								}
								echo $status;
								?>
							</td>
							<td>
								<?php if ($canDo) { ?>
									<a class="state trash icon-trash" href="<?php echo Route::url('index.php?option=' . $this->option . '&controller=' . $this->controller . '&task=remove&tmpl=component&id=' . $this->id . '&gid=' . $group->cn . '&' . Session::getFormToken() . '=1'); ?>">
										<span><?php echo Lang::txt('COM_MEMBERS_GROUPS_REMOVE'); ?></span>
									</a>
								<?php } ?>
							</td>
						</tr>
						<?php
					}
				}
				?>
			</tbody>
		</table>

		<?php echo Html::input('token'); ?>
	</form>
</div>
