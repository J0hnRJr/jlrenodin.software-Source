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
namespace OCA\Kerberos;

use OC_App;
use OCP\IConfig;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;

class LoginPageBehaviour {
	/** @var ILogger */
	private $logger;
	/** @var IUserSession */
	private $userSession;
	/** @var IURLGenerator */
	private $urlGenerator;
	/** @var IRequest */
	private $request;
	/** @var IConfig */
	private $config;

	public function __construct(
		ILogger $logger,
		IUserSession $userSession,
		IURLGenerator $urlGenerator,
		IRequest $request,
		IConfig $config
	) {
		$this->logger = $logger;
		$this->userSession = $userSession;
		$this->urlGenerator = $urlGenerator;
		$this->request = $request;
		$this->config = $config;
	}

	public function handleLoginPageBehaviour(): void {
		// logged in? nothing to do
		if ($this->userSession->isLoggedIn()) {
			return;
		}
		# only GET and OPTIONS requests are of interest
		if ($this->request->getMethod() !== 'GET' && $this->request->getMethod() !== 'OPTIONS') {
			return;
		}

		# only requests on the login page are of interest
		$components = \parse_url($this->request->getRequestUri());
		/** @phan-suppress-next-line PhanTypePossiblyInvalidDimOffset */
		$uri = $components['path'];
		if (\substr($uri, -6) !== '/login') {
			return;
		}

		# DavClnt / Mini ReDir handling for mapping drives under windows
		if ($this->request->getMethod() === 'OPTIONS') {
			$userAgent = $this->request->getHeader('User-Agent');
			if (strpos($userAgent, 'Microsoft-WebDAV-MiniRedir') !== false || strpos($userAgent, 'DavClnt') !== false) {
				header('HTTP/1.1 401 Authorization Required', true, 401);
				header('WWW-Authenticate: Negotiate');
				exit();
			}
		}
		$suppressed = \OC::$server->getRequest()->getCookie('oc_suppress_spnego');
		if ($suppressed === 'true') {
			return;
		}

		// register alternative login
		$loginName = $this->config->getSystemValue('kerberos.login.buttonName', 'Windows Domain Login');
		$this->registerAlternativeLogin($loginName);

		// if configured perform redirect right away if not logged in ....
		$autoRedirectOnLoginPage = $this->config->getSystemValue('kerberos.login.autoRedirect', false);
		if (!$autoRedirectOnLoginPage) {
			return;
		}

		$req = $this->request->getRequestUri();
		$this->logger->debug("Redirecting to IdP - request url: $req");
		$loginUrl = $this->urlGenerator->linkToRoute('kerberos.auth.auth', $this->request->getParams());
		$this->redirect($loginUrl);
	}

	/**
	 * @param string $loginUrl
	 * @codeCoverageIgnore
	 */
	public function redirect(string $loginUrl): void {
		\header('Location: ' . $loginUrl);
		exit;
	}

	/**
	 * @param string $loginName
	 * @codeCoverageIgnore
	 */
	public function registerAlternativeLogin(string $loginName): void {
		OC_App::registerLogIn([
			'name' => $loginName,
			'href' => $this->urlGenerator->linkToRoute('kerberos.auth.auth', $this->request->getParams()),
		]);
	}
}
