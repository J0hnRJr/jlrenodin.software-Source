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

namespace OCA\windows_network_drive\lib\kerberos\usermappings;

/**
 * Provide a way to map OC user ids to windows users
 */
interface IMapping {
	/**
	 * Map an ownCloud user id to a windows / samba one
	 * @param string $uid the ownCloud user id
	 * @return string the mapped id
	 */
	public function mapOcToWindows(string $uid): string;
}
