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

$this->css('register')
     ->js('register');
?>
<header id="content-header">
	<h2><?php echo $this->title; ?></h2>
</header>

<section class="main section">
<?php if ($this->getError()) { ?>
	<p class="error"><?php echo $this->getError(); ?></p>
<?php } else { ?>
	<p class="passed">A confirmation email has been sent to "<?php echo $this->escape($this->email); ?>".  You must click the link in that email to activate your account and resume using <?php echo $this->hubName; ?>.</p>
	<?php if ($this->show_correction_faq) { ?>
		<h4>Wrong email address?</h4>
		<p>You can correct your email address by <a href="<?php echo Route::url('index.php?option=' . $this->option . '&controller=' . $this->controller . '&task=change&return=' . $this->return); ?>">clicking here</a>.</p>
	<?php } ?>
	<h4>Never received or cannot find the confirmation email?</h4>
	<p>You can have a new confirmation email sent to "<?php echo $this->escape($this->email); ?>" by <a href="<?php echo Route::url('index.php?option=' . $this->option . '&controller=' . $this->controller . '&task=resend&return=' . $this->return); ?>">clicking here</a>.</p>
<?php } ?>
</section><!-- / .section -->
