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

namespace OCA\windows_network_drive\Lib\Auth;

use OCP\IL10N;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Files\External\DefinitionParameter;
use OCP\Files\External\Auth\AuthMechanism;
use OCP\Files\External\IStorageConfig;
use OCP\Files\External\InsufficientDataForMeaningfulAnswerException;

/**
 * Username from the user id in the session, password harcoded in the config
 */
class Kerberos extends AuthMechanism {
	/** @var IUserSession */
	private $userSession;

	public function __construct(IL10N $l, IUserSession $userSession) {
		$this->userSession = $userSession;

		$this->setIdentifier('kerberos::kerberos')
			->setScheme('kerberos')
			->setText($l->t('Kerberos'))
			->addParameters([
				new DefinitionParameter('kerberosServerId', $l->t('Kerberos data id')),
			]);
	}

	public function manipulateStorageConfig(IStorageConfig &$storage, IUser $user = null) {
		if ($user === null) {
			$user = $this->userSession->getUser();
		}
		// We require an LDAP user to authenticate with kerberos
		if ($user->getBackendClassName() === 'LDAP') {
			$storage->setBackendOption('user', $user->getUID());
		} else {
			throw new InsufficientDataForMeaningfulAnswerException('Not an LDAP User');
		}
	}
}
