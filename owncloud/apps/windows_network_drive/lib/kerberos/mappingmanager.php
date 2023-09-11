<?php
/**
 * ownCloud
 *
 * @author Juan Pablo Villafañez Ramos <jvillafanez@owncloud.com>
 * @copyright Copyright (c) 2023, ownCloud GmbH
 *
 * This code is covered by the ownCloud Commercial License.
 *
 * You should have received a copy of the ownCloud Commercial License
 * along with this program. If not, see <https://owncloud.com/licenses/owncloud-commercial/>.
 *
 */

namespace OCA\windows_network_drive\lib\kerberos;

use OCA\windows_network_drive\lib\kerberos\usermappings\IMapping;
use OCA\windows_network_drive\lib\kerberos\usermappings\Noop;
use OCA\windows_network_drive\lib\kerberos\usermappings\RemoveDomain;

class MappingManager {
	/**
	 * @param string $mappingName the name of the mapping to use
	 * @param mixed $params the parameters to construct the mapping, if any.
	 * This will be ignored if the mapping doesn't need any parameter
	 * @return IMapping|false the chosen mapping or false
	 */
	public function getMapping($mappingName, $params) {
		switch ($mappingName) {
			case 'Noop':
				return new Noop();
			case 'RemoveDomain':
				return new RemoveDomain();
			default:
				return false;
		}
	}
}
