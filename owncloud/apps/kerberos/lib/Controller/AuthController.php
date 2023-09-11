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

namespace OCA\Kerberos\Controller;

use OC\User\Session;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IUserSession;
use RuntimeException;

class AuthController extends Controller {
	private Session $session;

	/**
	 * @throws \Exception
	 */
	public function __construct($appName, IRequest $request, IUserSession $session) {
		parent::__construct($appName, $request);
		if (!$session instanceof Session) {
			# no need to translate - will not happen in real live
			throw new RuntimeException('We rely on internal implementation!');
		}
		$this->session = $session;
	}

	/**
	 * Checks the Auth header
	 *
	 * @param string $redirect_url (already URL encoded)
	 * @return TemplateResponse|RedirectResponse
	 *
	 * @PublicPage
	 * @NoCSRFRequired
	 * @UseSession
	 * @throws \Exception
	 */
	public function auth($redirect_url) {
		// Make it into a real URL
		$redirect_url = \urldecode($redirect_url);
		if ($this->session->isLoggedIn()) {
			return new RedirectResponse($this->getDefaultUrl());
		}
		if ($this->session->tryAuthModuleLogin($this->request)) {
			// TODO hack to provision the filesystem, should be done in core
			\OC::$server->getUserFolder($this->session->getUser()->getUID());
			return new RedirectResponse($this->getDefaultUrl());
		}
		$templateResp = new TemplateResponse(
			$this->appName,
			'auth',
			[
				'location' => $this->getLoginUrl($redirect_url),
				'webroot' => \OC::$WEBROOT
			],
			'guest'
		);
		$templateResp->setStatus(401);
		$templateResp->addHeader('WWW-Authenticate', 'Negotiate');
		return $templateResp;
	}

	/**
	 * @return string
	 */
	protected function getDefaultUrl() {
		return \OC_Util::getDefaultPageUrl();
	}

	/**
	 * @param string $redirect_url
	 * @return string
	 */
	protected function getLoginUrl($redirect_url) {
		$arguments = [];
		if ($redirect_url) {
			$arguments['redirect_url'] = $redirect_url;
		}
		return \OC::$server->getURLGenerator()->linkToRoute(
			'core.login.showLoginForm',
			$arguments
		);
	}
}
