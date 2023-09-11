<?php
/**
 * ownCloud - Ransomware Protection
 *
 * @copyright 2022 ownCloud GmbH
 *
 * This code is covered by the ownCloud Commercial License.
 *
 * You should have received a copy of the ownCloud Commercial License
 * along with this program. If not, see <https://owncloud.com/licenses/owncloud-commercial/>.
 *
 */

namespace OCA\Ransomware_Protection\Command;

use OCP\IConfig;
use OCA\Ransomware_Protection\Blacklist;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BlacklistSetFile extends Command {
	/** @var IConfig */
	private $config;

	/** @var Blacklist */
	private $blacklist;

	public function __construct(Blacklist $blacklist, IConfig $config) {
		$this->blacklist = $blacklist;
		$this->config = $config;
		parent::__construct();
	}

	protected function configure() {
		parent::configure();
		$this
			->setName('ransomguard:blacklist:set-file')
			->setDescription('Set the file that will contain the blacklist. A new file will be created if it doesn\'t exist')
			->addArgument(
				'filePath',
				InputArgument::REQUIRED,
				'The location of the file'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$targetPath = $input->getArgument('filePath');
		if (\trim($targetPath) === '') {
			$output->writeln("<error>Invalid path.</error>");
			return 1;
		}

		if (\substr($targetPath, 0, 1) !== '/') {
			$output->writeln("<error>Relative paths are not allowed. Please enter an absolute one</error>");
			return 1;
		}

		$currentDir = \dirname(__DIR__);
		$blacklistDefaultPath = \dirname($currentDir) . '/blacklist.txt.dist';
		if ($targetPath === $blacklistDefaultPath) {
			$output->writeln("<error>Overwriting the blacklist.txt.dist file is not allowed. Please select another location</error>");
			return 1;
		}

		$this->blacklist->getBlacklist();  // this will load the blacklist into memory
		$this->blacklist->sortBlacklistItems();

		$writeOk = $this->blacklist->writeBlacklist($targetPath);
		if ($writeOk) {
			$this->config->setAppValue('ransomware_protection', 'blacklistPath', $targetPath);
			$output->writeln("Blacklist written in {$targetPath}");
		} else {
			$output->writeln("<error>Could not write blacklist in {$targetPath}</error>");
			return 1;
		}
		return 0;
	}
}
