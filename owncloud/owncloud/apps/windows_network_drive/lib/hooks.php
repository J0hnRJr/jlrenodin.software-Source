<?php
/**
 * ownCloud
 *
 * @author Jesus Macias Portela <jesus@owncloud.com>
 * @copyright (C) 2015 ownCloud, Inc.
 *
 * This code is covered by the ownCloud Commercial License.
 *
 * You should have received a copy of the ownCloud Commercial License
 * along with this program. If not, see <https://owncloud.com/licenses/owncloud-commercial/>.
 *
 */
namespace OCA\windows_network_drive\lib;

use OCA\windows_network_drive\Lib\Auth\GlobalAuth;
use OCA\windows_network_drive\Lib\Auth\LoginCredentials;
use OCA\windows_network_drive\Lib\Auth\UserProvided;
use OCA\windows_network_drive\Lib\Auth\HardcodedConfigCredentials;
use OCA\windows_network_drive\Lib\Auth\Kerberos;

class Hooks {
	private static $isLoginCredentialHookSet = false;

	public static function loadWNDBackend() {
		Log::writeLog("preSetup Hook - Loading WND Backend", \OCP\Util::DEBUG);
		$l = \OC::$server->getL10N('windows_network_drive');
		$backend = new \OCA\windows_network_drive\lib\fs_backend\WND($l);
		$backend2 = new \OCA\windows_network_drive\lib\fs_backend\WND2($l);
		$service = \OC::$server->getStoragesBackendService();
		/* @phan-suppress-next-line PhanDeprecatedFunction */
		$service->registerBackends([
			$backend,
			$backend2,
		]);

		$config = \OC::$server->getConfig();
		$userSession = \OC::$server->getUserSession();
		$credentialsManager = \OC::$server->getCredentialsManager();
		$loginAuth = new LoginCredentials($l, $credentialsManager);
		$userAuth = new UserProvided($l, $credentialsManager);
		$globalAuth = new GlobalAuth($l, $credentialsManager);
		$hardcodedConfigAuth = new HardcodedConfigCredentials($l, $config, $userSession);
		$kerberosAuth = new Kerberos($l, $userSession);

		/* @phan-suppress-next-line PhanDeprecatedFunction */
		$service->registerAuthMechanisms([
				$loginAuth,
				$userAuth,
				$globalAuth,
				$hardcodedConfigAuth,
				$kerberosAuth,
		]);

		if (!self::$isLoginCredentialHookSet) {
			\OCP\Util::connectHook('OC_User', 'post_login', Hooks::class, 'loginCredentialsHooks');
			self::$isLoginCredentialHookSet = true;
		}
	}

	public static function loginCredentialsHooks(array $params) {
		if (!isset($params['uid'], $params['password'])) {
			// workaround to prevent deletion of password, oauth has no pw
			return;
		}

		$config = \OC::$server->getConfig();
		$session = \OC::$server->getSession();
		$credentialsManager = \OC::$server->getCredentialsManager();
		$userGSS = \OC::$server->getUserGlobalStoragesService();
		$userSS = \OC::$server->getUserStoragesService();

		$needToStoreCreds = false;
		$storages = \array_merge($userGSS->getAllStorages(), $userSS->getAllStorages());
		foreach ($storages as $storage) {
			$authMech = $storage->getAuthMechanism();
			if ($authMech->getIdentifier() === LoginCredentials::AUTH_IDENTIFIER) {
				$needToStoreCreds = true;
				break;
			}
		}

		if ($needToStoreCreds) {
			$userId = $params['uid'];
			// replace login with the username
			$username = $config->getUserValue(
				$userId,
				'core',
				'username',
				$session->get('loginname')
			);
			$credentials = [
				'user' => $username,
				'password' => $params['password'],
			];
			$credentialsManager->store($userId, LoginCredentials::CREDENTIALS_IDENTIFIER, $credentials);
		}
	}
}
