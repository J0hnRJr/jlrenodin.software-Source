<?php

/**
 * @author Tom Needham <tom@owncloud.com>
 *
 * @copyright Copyright (c) 2017, ownCloud GmbH
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\SystemTags_Management;

use OCP\Settings\ISettings;
use OCP\Template;

class AdminPanel implements ISettings {
	public function getPriority() {
		return 10;
	}

	public function getPanel() {
		$tmpl = new Template('systemtags_management', 'admin');
		return $tmpl;
	}

	public function getSectionID() {
		return 'workflow';
	}
}