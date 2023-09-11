<?php
/**
 * ownCloud Wopi
 *
 * @author Piotr Mrowczynski <piotr@owncloud.com>
 * @copyright 2021 ownCloud GmbH.
 *
 * This code is covered by the ownCloud Commercial License.
 *
 * You should have received a copy of the ownCloud Commercial License
 * along with this program. If not, see <https://owncloud.com/licenses/owncloud-commercial/>.
 *
 */

namespace OCA\WOPI;

use OC\HintException;
use OCP\Files\Storage\IPersistentLockingStorage;
use OCP\Util;

class Hooks {
	/**
	 * @throws HintException
	 */
	public static function publicPage(): void {
		Util::addScript('wopi', 'script');
		Util::addStyle('wopi', 'style');

		if (!\interface_exists(IPersistentLockingStorage::class)) {
			throw new HintException('No locking in core - bye bye');
		}
	}

	/**
	 * Function used to extend global JS config emitted with
	 * OC_Hook::emit('\OCP\Config', 'js', ['array' => &$array]) and available
	 * in JS as oc_appconfig.wopi
	 *
	 * @param array $array holding $array['array'] key with a reference value to config
	 */
	public static function extendJsConfig($array): void {
		$businessFlowEnabled = false;
		if (\OC::$server->getConfig()->getSystemValue('wopi.business-flow.enabled', false) === 'yes') {
			$businessFlowEnabled = true;
		}
		$array['array']['oc_appconfig']['wopi'] = [
			'businessFlowEnabled' => $businessFlowEnabled,
		];
	}
}
