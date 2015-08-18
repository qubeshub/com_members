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

// No direct access
defined('_HZEXEC_') or die();

$canDo = \Components\Members\Helpers\Permissions::getActions('component');

Toolbar::title(Lang::txt('COM_MEMBERS') . ': ' . Lang::txt('COM_MEMBERS_IMPORT_TITLE_IMPORTS'), 'import.png');

if ($canDo->get('core.admin'))
{
	Toolbar::custom('sample', 'sample', 'sample', 'COM_MEMBERS_IMPORT_SAMPLE', false);
	Toolbar::spacer();
	Toolbar::custom('run', 'script', 'script', 'COM_MEMBERS_RUN');
	Toolbar::custom('runtest', 'runtest', 'script', 'COM_MEMBERS_TEST_RUN');
	Toolbar::spacer();
	Toolbar::addNew();
	Toolbar::editList();
	Toolbar::deleteList();
}

Toolbar::spacer();
Toolbar::help('import');

$this->css('import');
?>

<nav role="navigation" class="sub sub-navigation">
	<ul>
		<li>
			<a<?php if ($this->controller == 'import') { echo ' class="active"'; } ?> href="<?php echo Route::url('index.php?option=' . $this->option . '&controller=import'); ?>"><?php echo Lang::txt('COM_MEMBERS_IMPORT_TITLE_IMPORTS'); ?></a>
		</li>
		<li>
			<a<?php if ($this->controller == 'importhooks') { echo ' class="active"'; } ?> href="<?php echo Route::url('index.php?option=' . $this->option . '&controller=importhooks'); ?>"><?php echo Lang::txt('COM_MEMBERS_IMPORT_HOOKS'); ?></a>
		</li>
	</ul>
</nav>

<script type="text/javascript">
function submitbutton(pressbutton)
{
	var form = document.adminForm;
	if (pressbutton == 'cancel') {
		submitform( pressbutton );
		return;
	}
	// do field validation
	submitform( pressbutton );
}
</script>

<form action="<?php echo Route::url('index.php?option=' . $this->option . '&controller=' . $this->controller); ?>" method="post" name="adminForm" id="adminForm">

	<table class="adminlist">
		<thead>
			<tr>
				<th scope="col"><input type="checkbox" name="toggle" value="" onclick="checkAll(<?php echo $this->imports->count(); ?>);" /></th>
				<th scope="col"><?php echo Lang::txt('COM_MEMBERS_IMPORT_DISPLAY_FIELD_NAME'); ?></th>
				<th scope="col" class="priority-4"><?php echo Lang::txt('COM_MEMBERS_IMPORT_DISPLAY_FIELD_NUMRECORDS'); ?></th>
				<th scope="col" class="priority-3"><?php echo Lang::txt('COM_MEMBERS_IMPORT_DISPLAY_FIELD_CREATED'); ?></th>
				<th scope="col"><?php echo Lang::txt('COM_MEMBERS_IMPORT_DISPLAY_FIELD_LASTRUN'); ?></th>
				<th scope="col" class="priority-4"><?php echo Lang::txt('COM_MEMBERS_IMPORT_DISPLAY_FIELD_RUNCOUNT'); ?></th>
			</tr>
		</thead>
		<tfoot>
			<tr>
				<td colspan="6">
					<?php
					// Initiate paging
					echo $this->pagination(
						$this->total,
						$this->filters['start'],
						$this->filters['limit']
					);
					?>
				</td>
			</tr>
		</tfoot>
		<tbody>
			<?php if ($this->imports->count() > 0) : ?>
				<?php foreach ($this->imports as $i => $import) : ?>
					<tr>
						<td>
							<?php if ($canDo->get('core.admin')) { ?>
								<input type="checkbox" name="id[]" id="cb<?php echo $i;?>" value="<?php echo $import->get('id'); ?>" onclick="isChecked(this.checked);" />
							<?php } ?>
						</td>
						<td>
							<?php if ($canDo->get('core.admin')) { ?>
								<a href="<?php echo Route::url('index.php?option=' . $this->option . '&controller=' . $this->controller . '&task=edit&id=' . $import->get('id')); ?>">
									<?php echo $this->escape($import->get('name')); ?>
								</a>
							<?php } else { ?>
								<?php echo $this->escape($import->get('name')); ?>
							<?php } ?>
							<br />
							<span class="hint">
								<?php echo nl2br($this->escape($import->get('notes'))); ?>
							</span>
						</td>
						<td class="priority-4">
							<?php echo $this->escape($import->get('count', 0)); ?>
						</td>
						<td class="priority-3">
							<strong><?php echo Lang::txt('COM_MEMBERS_IMPORT_DISPLAY_ON'); ?></strong>
							<time datetime="<?php echo $import->get('created_at'); ?>"><?php echo Date::of($import->get('created_at'))->toLocal('m/d/Y @ g:i a'); ?></time><br />
							<strong><?php echo Lang::txt('COM_MEMBERS_IMPORT_DISPLAY_BY'); ?></strong>
							<?php
								if ($created_by = Hubzero\User\Profile::getInstance($import->get('created_by')))
								{
									echo $created_by->get('name');
								}
							?>
						</td>
						<td>
							<?php
								$lastRun = $import->runs('list', array(
									'import'  => $import->get('id'),
									'dry_run' => 0,
									''
								))->first();
							?>
							<?php if ($lastRun) : ?>
								<strong><?php echo Lang::txt('COM_MEMBERS_IMPORT_DISPLAY_ON'); ?></strong>
								<time datetime="<?php echo $import->get('ran_at'); ?>"><?php echo Date::of($lastRun->get('ran_at'))->toLocal('m/d/Y @ g:i a'); ?></time><br />
								<strong><?php echo Lang::txt('COM_MEMBERS_IMPORT_DISPLAY_BY'); ?></strong>
								<?php
									if ($created_by = Hubzero\User\Profile::getInstance($lastRun->get('ran_by')))
									{
										echo $created_by->get('name');
									}
								?>
							<?php else: ?>
								n/a
							<?php endif; ?>
						</td>
						<td class="priority-4">
							<?php
								$runs = $import->runs('list', array(
									'import'  => $import->get('id'),
									'dry_run' => 0
								));
								echo $runs->count();
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr>
					<td colspan="6"><?php echo Lang::txt('COM_MEMBERS_IMPORT_NONE'); ?></td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>

	<input type="hidden" name="option" value="<?php echo $this->option ?>" />
	<input type="hidden" name="controller" value="<?php echo $this->controller; ?>">
	<input type="hidden" name="task" value="" autocomplete="off" />
	<input type="hidden" name="boxchecked" value="0" />

	<?php echo Html::input('token'); ?>
</form>