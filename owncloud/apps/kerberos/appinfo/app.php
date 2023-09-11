<?php
/**
 * ownCloud
 *
 * @author Jörn Friedrich Dreyer <jfd@owncloud.com>
 * @copyright (C) 2018 ownCloud GmbH
 *
 * This code is covered by the ownCloud Commercial License.
 *
 * You should have received a copy of the ownCloud Commercial License
 * along with this program. If not, see <https://owncloud.com/licenses/owncloud-commercial/>.
 *
 */

if (\OC::$CLI === false) {
	$session = \OC::$server->getSession();
	$userSession = \OC::$server->getUserSession();
	$request = \OC::$server->getRequest();
	$urlGenerator = \OC::$server->getURLGenerator();
	$logger = \OC::$server->getLogger();
	$config = \OC::$server->getConfig();
	$loginPage = new \OCA\Kerberos\LoginPageBehaviour($logger, $userSession, $urlGenerator, $request, $config);
	$loginPage->handleLoginPageBehaviour();

	// register a hook for logout, we set a 'oc_suppress_spnego' cookie
	$handler = new \OCA\Kerberos\HookHandler(
		$config,
		$logger
	);
	\OC::$server->getUserSession()->listen('\OC\User', 'logout', [$handler, 'logout']);

	// make the timeout available as appconfig in js
	\OCP\Util::connectHook('\OCP\Config', 'js', $handler, 'extendJsConfig');

	// register sabre plugin event handler
	$dispatcher = \OC::$server->getEventDispatcher();
	$eventHandler = new \OCA\Kerberos\EventHandler($dispatcher, $request, $userSession, $session);
	$eventHandler->registerEventHandler();
}
