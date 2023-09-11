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

namespace OCA\windows_network_drive\Command;

use OC\Core\Command\Base;
use OCP\AppFramework\Utility\ITimeFactory;
use OCA\windows_network_drive\lib\kerberos\CCacheHandler;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class KerberosCleanup extends Base {
	public const ERROR_WRONG_TIME = 1;

	/** @var CCacheHandler */
	private $ccacheHandler;
	/** @var ITimeFactory */
	private $timeFactory;

	public function __construct(CCacheHandler $ccacheHandler, ITimeFactory $timeFactory) {
		parent::__construct();
		$this->ccacheHandler = $ccacheHandler;
		$this->timeFactory = $timeFactory;
	}

	protected function configure() {
		$this->setName('wnd:kerberos:cleanup')
			->setDescription('Cleanup obsolete kerberos credentials')
			->addOption(
				'olderThan',
				null,
				InputOption::VALUE_REQUIRED,
				'Remove credentials older than this number of hours',
				'24'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$olderThan = (int)$input->getOption('olderThan');
		if ($olderThan <= 0) {
			$output->writeln("<error>Refusing to delete credentials older than {$olderThan} hours</error>");
			return self::ERROR_WRONG_TIME;
		}

		$removedCredentials = $this->ccacheHandler->removeObsoleteCredentials($this->timeFactory->getTime() - ($olderThan * 3600));
		$output->writeln("Removed {$removedCredentials} credentials");
		return 0;
	}
}
