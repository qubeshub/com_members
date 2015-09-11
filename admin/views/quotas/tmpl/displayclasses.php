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
 * @copyright Copyright 2005-2015 HUBzero Foundation, LLC.
 * @license   http://www.gnu.org/licenses/lgpl-3.0.html LGPLv3
 */

// No direct access
defined('_HZEXEC_') or die();

$canDo = \Components\Members\Helpers\Permissions::getActions('component');

// Menu
Toolbar::title(Lang::txt('COM_MEMBERS_QUOTA_CLASSES'), 'user.png');
if ($canDo->get('core.edit'))
{
	Toolbar::addNew('addClass');
	Toolbar::editList('editClass');
	Toolbar::deleteList('COM_MEMBERS_QUOTA_CONFIRM_DELETE', 'deleteClass');
	Toolbar::spacer();
}
Toolbar::help('quotaclasses');
?>

<?php
	$this->view('_submenu')
	     ->display();
?>

<form action="<?php echo Route::url('index.php?option=' . $this->option . '&controller=' . $this->controller); ?>" method="post" name="adminForm" id="adminForm">
	<table class="adminlist">
		<thead>
		 	<tr>
				<th><input type="checkbox" name="toggle" value="" onclick="checkAll(<?php echo count($this->rows); ?>);" /></th>
				<th class="priority-5"><?php echo Lang::txt('COM_MEMBERS_QUOTA_ID'); ?></th>
				<th><?php echo Lang::txt('COM_MEMBERS_QUOTA_ALIAS'); ?></th>
				<th class="priority-3"><?php echo Lang::txt('COM_MEMBERS_QUOTA_SOFT_BLOCKS'); ?></th>
				<th><?php echo Lang::txt('COM_MEMBERS_QUOTA_HARD_BLOCKS'); ?></th>
				<th class="priority-3"><?php echo Lang::txt('COM_MEMBERS_QUOTA_SOFT_FILES'); ?></th>
				<th class="priority-2"><?php echo Lang::txt('COM_MEMBERS_QUOTA_HARD_FILES'); ?></th>
			</tr>
		</thead>
		<tfoot>
			<tr>
				<td colspan="7">
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
<?php
$k = 0;
for ($i=0, $n=count($this->rows); $i < $n; $i++)
{
	$row = &$this->rows[$i];
?>
			<tr class="<?php echo "row$k"; ?>">
				<td>
					<input type="checkbox" name="id[]" id="cb<?php echo $i; ?>" value="<?php echo $row->id; ?>" onclick="isChecked(this.checked);" />
				</td>
				<td class="priority-5">
					<a href="<?php echo Route::url('index.php?option=' . $this->option . '&controller=' . $this->controller . '&task=editClass&id=' . $row->id); ?>">
						<?php echo $this->escape($row->id); ?>
					</a>
				</td>
				<td>
					<a href="<?php echo Route::url('index.php?option=' . $this->option . '&controller=' . $this->controller . '&task=editClass&id=' . $row->id); ?>">
						<?php echo $this->escape($row->alias); ?>
					</a>
				</td>
				<td class="priority-3">
					<?php echo $this->escape($row->soft_blocks); ?>
				</td>
				<td>
					<?php echo $this->escape($row->hard_blocks); ?>
				</td>
				<td class="priority-3">
					<?php echo $this->escape($row->soft_files); ?>
				</td>
				<td class="priority-2">
					<?php echo $this->escape($row->hard_files); ?>
				</td>
			</tr>
<?php
	$k = 1 - $k;
}
?>
		</tbody>
	</table>

	<input type="hidden" name="option" value="<?php echo $this->option; ?>" />
	<input type="hidden" name="controller" value="<?php echo $this->controller; ?>" />
	<input type="hidden" name="task" value="displayClasses" />
	<input type="hidden" name="boxchecked" value="0" />
	<?php echo Html::input('token'); ?>
</form>