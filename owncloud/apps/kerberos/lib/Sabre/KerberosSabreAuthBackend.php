<?php
/**
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 *
 * @copyright Copyright (c) 2022, ownCloud GmbH
 *
 * This code is covered by the ownCloud Commercial License.
 *
 * You should have received a copy of the ownCloud Commercial License
 * along with this program. If not, see <https://owncloud.com/licenses/owncloud-commercial/>.
 *
 */
namespace OCA\Kerberos\Sabre;

use Exception;
use OC;
use OC\User\Session;
use OC_Util;
use OCA\DAV\Connector\Sabre\Auth;
use OCA\Kerberos\AuthModule;
use OCP\IRequest;
use OCP\ISession;
use OCP\IUserSession;
use RuntimeException;
use Sabre\DAV\Auth\Backend\BackendInterface;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

class KerberosSabreAuthBackend implements BackendInterface {
	public const DAV_AUTHENTICATED = Auth::DAV_AUTHENTICATED;

	/**
	 * This is the prefix that will be used to generate principal urls.
	 */
	protected string $principalPrefix;
	private ISession $session;
	private Session $userSession;
	private IRequest $request;
	private AuthModule $authModule;

	/**
	 * @throws Exception
	 */
	public function __construct(
		ISession $session,
		IUserSession $userSession,
		IRequest $request,
		AuthModule $authModule,
		$principalPrefix = 'principals/users/'
	) {
		if (!$userSession instanceof Session) {
			# no need to translate - will not happen in real live
			throw new RuntimeException('We rely on internal implementation!');
		}
		$this->session = $session;
		$this->userSession = $userSession;
		$this->request = $request;
		$this->authModule = $authModule;
		$this->principalPrefix = $principalPrefix;
	}

	/**
	 * Checks whether the user has initially authenticated via DAV.
	 *
	 * This is required for WebDAV clients that resent the cookies even when the
	 * account was changed.
	 *
	 * @see https://github.com/owncloud/core/issues/13245
	 *
	 * @param string $username The username.
	 * @return bool True if the user initially authenticated via DAV, false otherwise.
	 */
	private function isDavAuthenticated($username) {
		return $this->session->get(self::DAV_AUTHENTICATED) !== null &&
			$this->session->get(self::DAV_AUTHENTICATED) === $username;
	}

	/**
	 * @param string $userId
	 * @codeCoverageIgnore
	 */
	protected function setupFilesystem(string $userId = ''): void {
		OC_Util::setupFS($userId);
	}

	public function check(RequestInterface $request, ResponseInterface $response) {
		$headers = json_encode($request->getHeaders());
		OC::$server->getLogger()->info("Request headers: $headers");

		if ($this->userSession->isLoggedIn() &&
			$this->isDavAuthenticated($this->userSession->getUser()->getUID())) {
			try {
				$authHeader = $request->getHeader('Authorization');
				$tokenUser = $this->authModule->authByHeader($authHeader);
				if ($tokenUser === null) {
					OC::$server->getLogger()->error("KERBEROS: no user from auth header");
					return [false, "SPNEGO failed"];
				}

				// setup the user
				$userId = $this->userSession->getUser()->getUID();
				$this->setupFilesystem($userId);
				$this->session->close();
				return [true, $this->principalPrefix . $userId];
			} catch (Exception $ex) {
				$this->session->close();
				OC::$server->getLogger()->logException($ex);
				return [false, "SPNEGO failed"];
			}
		}

		$this->setupFilesystem();

		try {
			// we have to go through IUserSession here to login the user properly
			if ($this->userSession->tryAuthModuleLogin($this->request)) {
				$userId = $this->userSession->getUser()->getUID();
				$this->setupFilesystem($userId);
				$this->session->set(self::DAV_AUTHENTICATED, $userId);
				$this->session->close();
				return [true, $this->principalPrefix . $userId];
			}

			$this->session->close();
			OC::$server->getLogger()->error("KERBEROS: no login via any auth module");
			return [false, "SPNEGO failed"];
		} catch (Exception $ex) {
			$this->session->close();
			OC::$server->getLogger()->logException($ex);
			return [false, "SPNEGO failed"];
		}
	}

	public function challenge(RequestInterface $request, ResponseInterface $response) {
		$response->addHeader('WWW-Authenticate', 'Negotiate');
		$response->setStatus(401);
	}
}
