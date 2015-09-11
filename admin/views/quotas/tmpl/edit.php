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

$text = ($this->task == 'edit' ? Lang::txt('JACTION_EDIT') : Lang::txt('JACTION_CREATE'));

Toolbar::title(Lang::txt('COM_MEMBERS_QUOTAS') . ': ' . $text, 'user.png');
if ($canDo->get('core.edit'))
{
	Toolbar::apply();
	Toolbar::save();
	Toolbar::spacer();
}
Toolbar::cancel();

$base = str_replace('/administrator', '', rtrim(Request::base(true), '/'));
?>

<script type="text/javascript">
	function submitbutton(pressbutton)
	{
		var form = document.adminForm;

		if (pressbutton == 'cancel') {
			submitform( pressbutton );
			return;
		}

		submitform( pressbutton );
	}

	jQuery(document).ready(function($){
		$('#class_id').on('change', function (e) {
			//e.preventDefault();

			var req = $.getJSON('<?php echo Route::url('index.php?option=' . $this->option . '&controller=quotas&task=getClassValues&class_id='); ?>' + $(this).val(), {}, function (data) {
				$.each(data, function (key, val) {
					var item = $('#field-'+key);
					item.val(val);

					if (e.target.options[e.target.selectedIndex].text == 'custom') {
						item.prop("readonly", false);
					} else {
						item.prop("readonly", true);
					}
				});
			});
		});
	});
</script>
<form action="<?php echo Route::url('index.php?option=' . $this->option); ?>" method="post" name="adminForm" id="item-form">
	<?php if ($this->getError()) : ?>
		<p class="error"><?php echo $this->getError(); ?></p>
	<?php endif; ?>

	<div class="col width-60 fltlft">
		<fieldset class="adminform">
			<legend><span><?php echo Lang::txt('COM_MEMBERS_QUOTA_LEGEND'); ?></span></legend>

			<input type="hidden" name="fields[id]" value="<?php echo $this->row->id; ?>" />
			<input type="hidden" name="option" value="<?php echo $this->option; ?>" />
			<input type="hidden" name="controller" value="<?php echo $this->controller; ?>" />
			<input type="hidden" name="task" value="save" />

			<?php if (!$this->row->id) : ?>
				<div class="input-wrap">
					<script type="text/javascript" src="<?php echo $base; ?>/plugins/hubzero/autocompleter/autocompleter.js"></script>
					<script type="text/javascript">var plgAutocompleterCss = "<?php echo $base; ?>/plugins/hubzero/autocompleter/autocompleter.css";</script>

					<label for="field-user_id"><?php echo Lang::txt('COM_MEMBERS_QUOTA_USER'); ?>:</label>
					<input type="text" name="fields[user_id]" id="field-user_id" data-options="members,multi," id="acmembers" class="autocomplete" value="" autocomplete="off" data-css="" data-script="<?php echo $base; ?>/administrator/index.php" />
					<span><?php echo Lang::txt('COM_MEMBERS_QUOTA_USER_HINT'); ?></span>
				</div>
			<?php else : ?>
				<input type="hidden" name="fields[user_id]" id="field-user_id" value="<?php echo $this->row->user_id; ?>" />
			<?php endif; ?>
			<div class="input-wrap">
				<label for="class_id"><?php echo Lang::txt('COM_MEMBERS_QUOTA_CLASS'); ?>:</label>
				<?php echo $this->classes; ?>
			</div>
			<div class="input-wrap">
				<label for="field-soft_blocks"><?php echo Lang::txt('COM_MEMBERS_QUOTA_SOFT_BLOCKS'); ?>:</label>
				<input <?php echo ($this->row->class_id) ? 'readonly' : ''; ?> type="text" name="fields[soft_blocks]" id="field-soft_blocks" value="<?php echo $this->escape(stripslashes($this->row->soft_blocks)); ?>" />
			</div>
			<div class="input-wrap">
				<label for="field-hard_blocks"><?php echo Lang::txt('COM_MEMBERS_QUOTA_HARD_BLOCKS'); ?>:</label>
				<input <?php echo ($this->row->class_id) ? 'readonly' : ''; ?> type="text" name="fields[hard_blocks]" id="field-hard_blocks" value="<?php echo $this->escape(stripslashes($this->row->hard_blocks)); ?>" />
			</div>
			<div class="input-wrap">
				<label for="field-soft_files"><?php echo Lang::txt('COM_MEMBERS_QUOTA_SOFT_FILES'); ?>:</label>
				<input <?php echo ($this->row->class_id) ? 'readonly' : ''; ?> type="text" name="fields[soft_files]" id="field-soft_files" value="<?php echo $this->escape(stripslashes($this->row->soft_files)); ?>" />
			</div>
			<div class="input-wrap">
				<label for="field-hard_files"><?php echo Lang::txt('COM_MEMBERS_QUOTA_HARD_FILES'); ?>:</label>
				<input <?php echo ($this->row->class_id) ? 'readonly' : ''; ?> type="text" name="fields[hard_files]" id="field-hard_files" value="<?php echo $this->escape(stripslashes($this->row->hard_files)); ?>" />
			</div>
		</fieldset>
	</div>
	<div class="col width-40 fltrt">
		<table class="meta">
			<tbody>
				<tr>
					<th><?php echo Lang::txt('COM_MEMBERS_QUOTA_ID'); ?></th>
					<td><?php echo $this->row->user_id; ?></td>
				</tr>
				<tr>
					<th><?php echo Lang::txt('COM_MEMBERS_QUOTA_USERNAME'); ?></th>
					<td><?php echo $this->row->username; ?></td>
				</tr>
				<tr>
					<th><?php echo Lang::txt('COM_MEMBERS_QUOTA_NAME'); ?></th>
					<td><?php echo $this->row->name; ?></td>
				</tr>
			</tbody>
		</table>
		<table class="meta">
			<tbody>
				<tr>
					<th><?php echo Lang::txt('COM_MEMBERS_QUOTA_SPACE'); ?></th>
					<td><?php echo Lang::txt('COM_MEMBERS_QUOTA_SPACE_DISPLAY', (isset($this->du['info']['space']) ? $this->du['info']['space'] / 1024 : 0), $this->du['percent']); ?></td>
				</tr>
				<tr>
					<th><?php echo Lang::txt('COM_MEMBERS_QUOTA_FILES'); ?></th>
					<td><?php echo (isset($this->du['info']['files']) ? $this->du['info']['files'] : 0); ?></td>
				</tr>
			</tbody>
		</table>
	</div>
	<div class="clr"></div>
	<?php echo Html::input('token'); ?>
</form>