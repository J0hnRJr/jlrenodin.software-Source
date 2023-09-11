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
 * This mapping will remove the domain part of the ownCloud uid (if any).
 * It will also affect emails if they're used as uid (the email of the ownCloud
 * account won't be touched)
 *
 * For example, if the uid is "myuser@my.comp.eu", only "myuser" will be returned
 *
 * This mapping won't do anything if the if there is no "@" char in the uid.
 *
 * This mapping can be used if the user_ldap app is used with "userPrincipalName"
 * as username. We can get uids such as "myuser@oc.srv.com"
 * If kerberos is used as authentication against ownCloud (with the kerberos app),
 * we might get uids such as "myuser@OC.SRV.COM"
 */
class RemoveDomain implements IMapping {
	/**
	 * @inheritdoc
	 */
	public function mapOcToWindows(string $uid): string {
		$lastAt = \strrpos($uid, '@');
		if ($lastAt === false) {
			return $uid;
		}

		$mappedUser = \substr($uid, 0, $lastAt);
		return $mappedUser;
	}
}
