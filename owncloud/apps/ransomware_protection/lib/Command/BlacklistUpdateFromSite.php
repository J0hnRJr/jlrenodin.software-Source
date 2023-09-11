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

use OCA\Ransomware_Protection\Blacklist;
use OCA\Ransomware_Protection\BlacklistFetcher\SiteFetcher;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BlacklistUpdateFromSite extends Command {
	/** @var Blacklist */
	private $blacklist;

	/** @var SiteFetcher */
	private $fetcher;

	public function __construct(Blacklist $blacklist, SiteFetcher $fetcher) {
		$this->blacklist = $blacklist;
		$this->fetcher = $fetcher;
		parent::__construct();
	}

	protected function configure() {
		parent::configure();
		$this
			->setName('ransomguard:blacklist:update:from-site')
			->setDescription('Update the blacklist with the contents from the site')
			->addArgument(
				'siteUrl',
				InputArgument::OPTIONAL,
				'The URL to get the data from',
				'https://fsrm.experiant.ca/api/v1/get'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$site = $input->getArgument('siteUrl');
		if (\trim($site) === '') {
			$output->writeln("<error>Invalid URL.</error>");
			return 1;
		}

		$this->blacklist->getBlacklist();  // load the blacklist into memory

		$newData = $this->fetcher->fetchBlacklist($site);

		$dataChanged = $this->blacklist->updateItems($newData);
		$this->blacklist->sortBlacklistItems();

		$writeOk = $this->blacklist->writeBlacklist();
		if ($writeOk) {
			$output->writeln('Updated blacklist');
			if (empty($dataChanged['added']) && empty($dataChanged['removed'])) {
				$output->writeln('No changes made');
			} else {
				if (!empty($dataChanged['added'])) {
					$output->writeln('Added the following items');
					foreach ($dataChanged['added'] as $addedItem) {
						$output->writeln("- {$addedItem}");
					}
				}

				if (!empty($dataChanged['removed'])) {
					$output->writeln('Removed the following items');
					foreach ($dataChanged['removed'] as $removedItem) {
						$output->writeln("- {$removedItem}");
					}
				}
			}
		} else {
			$output->writeln('<error>Could not write the blacklist</error>');
			return 1;
		}
		return 0;
	}
}
