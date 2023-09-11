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

use OC;
use OCA\Kerberos\Sabre\KerberosSabreAuthBackend;
use OCP\AppFramework\QueryException;
use OCP\IRequest;
use OCP\ISession;
use OCP\IUserSession;
use OCP\SabrePluginEvent;
use Sabre\DAV\Auth\Plugin;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class EventHandler {
	private EventDispatcherInterface $dispatcher;
	private IRequest $request;
	private IUserSession $userSession;
	private ISession $session;

	public function __construct(
		EventDispatcherInterface $dispatcher,
		IRequest $request,
		IUserSession $userSession,
		ISession $session
	) {
		$this->dispatcher = $dispatcher;
		$this->request = $request;
		$this->userSession = $userSession;
		$this->session = $session;
	}

	public function registerEventHandler(): void {
		$this->dispatcher->addListener('OCA\DAV\Connector\Sabre::authInit', function ($event) {
			if (!$event instanceof SabrePluginEvent) {
				return;
			}
			if ($event->getServer() === null) {
				return;
			}
			$authPlugin = $event->getServer()->getPlugin('auth');
			if ($authPlugin instanceof Plugin) {
				$authPlugin->addBackend($this->createAuthBackend());
			}
		});
	}

	/**
	 * @throws QueryException
	 * @throws \Exception
	 * @codeCoverageIgnore
	 */
	protected function createAuthBackend(): KerberosSabreAuthBackend {
		$module = OC::$server->query(AuthModule::class);
		return new KerberosSabreAuthBackend(
			$this->session,
			$this->userSession,
			$this->request,
			$module,
			'principals/'
		);
	}
}
