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

namespace OCA\Kerberos;

use OCP\IConfig;
use OCP\ILogger;

class HookHandler {
	private IConfig $config;
	private ILogger $logger;

	public function __construct(IConfig $config, ILogger $logger) {
		$this->config = $config;
		$this->logger = $logger;
	}

	public function logout(): void {
		// When a spnego user logs out, allow them to access the login form in case they want to log-in
		// as another user. We temporarily suppress the SPNEGO invitation until the login page is rendered.
		$timeout = (int)$this->config->getSystemValue('kerberos.suppress.timeout', 60);
		if ($timeout > 0) {
			$this->logger->debug('setting suppress cookie', ['app'=>__METHOD__]);
			// TODO Request should be injected, however the hooks are registered during app loading ... which AFAIR produces a cycle
			$secureCookie = \OC::$server->getRequest()->getServerProtocol() === 'https';
			setcookie('oc_suppress_spnego', 'true', time() + $timeout, \OC::$WEBROOT, '', $secureCookie, true);
			setcookie('oc_suppress_spnego', 'true', time() + $timeout, \OC::$WEBROOT . '/', '', $secureCookie, true);
		}
	}

	public function extendJsConfig($array): void {
		$timeout = (int)$this->config->getSystemValue('kerberos.suppress.timeout', 60);
		$array['array']['oc_appconfig']['kerberos'] = [
			'suppress_timeout' => $timeout,
		];
	}
}
