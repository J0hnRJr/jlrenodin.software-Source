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

use OCP\Authentication\IAuthModule;
use OCP\IConfig;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;

class AuthModule implements IAuthModule {
	private ILogger $logger;
	private IUserManager $manager;
	private IConfig $config;

	public function __construct(ILogger $logger, IUserManager $manager, IConfig $config) {
		$this->logger = $logger;
		$this->manager = $manager;
		$this->config = $config;
	}

	/**
	 * Authenticates a request.
	 *
	 * @param IRequest $request The request.
	 *
	 * @return null|IUser The user if the request is authenticated, null otherwise.
	 * @since 10.0.0
	 */
	public function auth(IRequest $request) {
		$authHeader = $request->getHeader('Authorization');
		return $this->authByHeader($authHeader);
	}

	public function authByHeader($authHeader) {
		if (!\extension_loaded('krb5')) {
			return null;
		}
		// do not authenticate when suppress timeout is active
		if ($_COOKIE && \array_key_exists('oc_suppress_spnego', $_COOKIE)) {
			return null;
		}

		if ($authHeader && preg_match('/Negotiate\s+(.*)$/i', $authHeader, $matches)) {
			$config = \OC::$server->getConfig();
			$keytab = $config->getSystemValue('kerberos.keytab', '/etc/krb5.keytab');
			$auth = new \KRB5NegotiateAuth($keytab);

			$reply = false;
			try {
				$reply = $auth->doAuthentication();
			} catch (\Exception $e) {
				$this->logger->logException($e, ['app'=>__METHOD__]);
			}
			if (!$reply) {
				$this->logger->warning('failed auth', ['app'=>__METHOD__]);
				return null;
			}

			$principal = $auth->getAuthenticatedUser();
			$this->logger->debug("successful auth as $principal");

			// TODO make core look up the user for auto-provisioning, see user_shibboleth app
			list($uid, $backend) = $this->determineBackendFor($principal);
			$domain = $this->config->getSystemValue('kerberos.domain', null);
			if ($backend === null && $domain !== null) {
				$principal = substr($principal, 0, \strrpos($principal, "@$domain"));
				list($uid, $backend) = $this->determineBackendFor($principal);
			}
			$backendClass = \get_class($backend);
			$this->logger->debug("$principal mapped to $uid at $backendClass");

			// TODO do we need auto-provisioning? or do we rely on ldap and assume the user is available there?
			return $this->manager->get($uid);
		}

		$this->logger->debug('no \'Authorization: Negotiate\' header found', ['app'=>__METHOD__]);
		return null;
	}

	/**
	 * TODO Taken from user_shibboleth, move to core!
	 */
	private function determineBackendFor($samlNameId) {
		foreach ($this->manager->getBackends() as $backend) {
			$class = \get_class($backend);
			// FIXME the next line can return zombie999 for zombie99 because it does a prefix based search, needs a new api, or exact parameter. maybe prefix|medial|exact $matchtype? or a better yet a query object
			// TODO for now recommend any attribute that has a clear suffix like email or userprincipalname
			$this->logger->debug(
				"Searching Backend $class for $samlNameId",
				['app' => __METHOD__]
			);
			$userIds = $backend->getUsers($samlNameId, 2);
			switch (\count($userIds)) {
				case 0:
					$this->logger->debug(
						"Backend $class returned no matching user for $samlNameId",
						['app' => __METHOD__]
					);
					break;
				case 1:
					$uid = array_pop($userIds);
					$this->logger->debug(
						"Backend $class returned $uid for $samlNameId",
						['app' => __METHOD__]
					);
					// Found the user in a different backend
					return [$uid, $backend];
				default:
					throw new \InvalidArgumentException("Backend $class returned more than one user for $samlNameId: " . implode(', ', $userIds));
			}
		}
		return [$samlNameId, null];
	}

	public function getUserPassword(IRequest $request) {
		return '';
	}
}
