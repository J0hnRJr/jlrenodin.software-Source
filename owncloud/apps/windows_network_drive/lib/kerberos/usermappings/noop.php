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
 * This mapping won't do anything. It will return the uid without any change
 *
 * This mapping can be used when the ownCloud's uid can be used as windows user.
 * For example, if the user_ldap app is configured with "samaccountname" as
 * username, we get these kind of ids.
 */
class Noop implements IMapping {
	/**
	 * @inheritdoc
	 */
	public function mapOcToWindows(string $uid): string {
		return $uid;
	}
}
