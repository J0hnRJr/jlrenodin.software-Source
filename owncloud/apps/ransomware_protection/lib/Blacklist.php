<?php
/**
 * ownCloud - Ransomware Protection
 *
 * @author Thomas Heinisch <t.heinisch@bw-tech.de>
 * @copyright 2017 ownCloud GmbH
 *
 * This code is covered by the ownCloud Commercial License.
 *
 * You should have received a copy of the ownCloud Commercial License
 * along with this program. If not, see <https://owncloud.com/licenses/owncloud-commercial/>.
 *
 */

namespace OCA\Ransomware_Protection;

use OCP\ILogger;
use OCP\IConfig;
use OCA\Ransomware_Protection\BlacklistFetcher\FileFetcher;
use OCA\Ransomware_Protection\BlacklistFetcher\BlacklistFetcherException;

class Blacklist {
	/** @var  ILogger */
	protected $logger;

	/** @var IConfig */
	protected $config;

	/** @var FileFetcher */
	protected $fileFetcher;

	/** @var array $blacklist */
	protected $blacklist = [];

	/** @var bool $loaded */
	protected $loaded = false;

	public function __construct(FileFetcher $fileFetcher, IConfig $config, ILogger $logger) {
		$this->fileFetcher = $fileFetcher;
		$this->config = $config;
		$this->logger = $logger;
	}

	/**
	 * Get the path of where the blacklist should be located.
	 */
	private function getBlacklistPath() {
		$defaultPath = $this->config->getSystemValue('datadirectory', \OC::$SERVERROOT . '/data') . '/ransomware_blacklist.txt';
		$blacklistPath = $this->config->getAppValue('ransomware_protection', 'blacklistPath', $defaultPath);

		return $blacklistPath;
	}

	/**
	 * Collect and return Blacklist
	 * If the blacklist hasn't been loaded, it will be read from the
	 * expected location
	 *
	 * @return array
	 */
	public function getBlacklist() {
		if (!$this->loaded) {
			$this->readBlacklist();
		}
		return $this->blacklist;
	}

	/**
	 * Set the blacklist with the data provided. This will overwrite
	 * the previous data if any, and consider the blacklist as loaded
	 *
	 * @param array $wildcards the list of patterns to be used as blacklist.
	 * The patterns must be like "*.foo" or "do*do"
	 */
	public function setBlacklist(array $wildcards) {
		$this->blacklist = [];
		foreach ($wildcards as $wildcard) {
			$this->blacklist[] = $this->wildcard2regex($wildcard);
		}
		$this->loaded = true;
	}

	/**
	 * Read and load the blacklist from the configured location.
	 * @return bool true if blacklist is loaded, false otherwise
	 */
	public function readBlacklist() {
		$targetPath = $this->getBlacklistPath();
		if (!\is_readable($targetPath)) {
			$targetPath = \dirname(__DIR__) . '/blacklist.txt.dist';  // base location, for compatibility
		}

		try {
			$items = $this->fileFetcher->fetchBlacklist($targetPath);
		} catch (BlacklistFetcherException $ex) {
			$this->logger->logException($ex);
			return false;
		}

		$this->blacklist = [];  // reset the blacklist in memory
		foreach ($items as $item) {
			$this->blacklist[] = $this->wildcard2regex($item);
		}

		$this->loaded = true;
		return true;
	}

	/**
	 * Write the blacklist in the target file. Any writeable path
	 * can be used. If no path is used, the configured location will
	 * be used.
	 * @param string|null $targetPath the path to write the blacklist
	 * into, or null to use the configured location
	 * @return bool true if the blacklist is written, false otherwise
	 */
	public function writeBlacklist($targetPath = null) {
		if (!$this->loaded) {
			return false;
		}

		if ($targetPath === null) {
			$targetPath = $this->getBlacklistPath();
		}

		$handle = \fopen($targetPath, 'w');
		if ($handle === false) {
			$this->logger->error('Failed to open blacklist file to write', ['app' => 'ransomware_protection']);
			return false;
		}

		foreach ($this->blacklist as $item) {
			$toWrite = $this->regex2wildcard($item);
			\fwrite($handle, "{$toWrite}\n");
		}
		\fclose($handle);
		return true;
	}

	/**
	 * Sort the items in the blacklist
	 */
	public function sortBlacklistItems() {
		return \sort($this->blacklist);
	}

	/**
	 * Update the blacklist with the new patterns (such as '*.foo' or 'do*do')
	 * The patterns will be added unless the "removeIfNeeded" flag is set.
	 * If the flag is set, the blacklist will be replaced with the list provided
	 * A key-value map will be returned, containing 'added' with the list of
	 * patterns added to the blacklist, and 'removed' with the list of patterns
	 * removed.
	 * @param array $newItems the lists of patterns to be included in the blacklist
	 * @param bool $removeIfNeeded to indicate if we should replace the blacklist or
	 * just add the new items.
	 * @return array a map containing the list of added and removed items
	 */
	public function updateItems(array $newItems, $removeIfNeeded = false) {
		$newItems = \array_map(function ($value) {
			return $this->wildcard2regex($value);
		}, $newItems);
		$addedItems = \array_diff($newItems, $this->blacklist);

		if ($removeIfNeeded) {
			$removedItems = \array_diff($this->blacklist, $newItems);
			$this->blacklist = $newItems;
			return [
				'added' => $addedItems,
				'removed' => $removedItems,
			];
		} else {
			foreach ($addedItems as $item) {
				$this->blacklist[] = $item;
			}
			return [
				'added' => $addedItems,
				'removed' => [],
			];
		}
	}

	/**
	 * Match path with blacklist patterns
	 *
	 * @param string $path
	 * @return array
	 */
	public function match($path) {
		$path = $this->stripPartFileExtension($path);
		$fileName = \pathinfo($path, PATHINFO_BASENAME);
		foreach ($this->getBlacklist() as $pattern) {
			if (\preg_match($pattern, $fileName) === 1) {
				return [
					'pattern' => $this->regex2wildcard($pattern),
					'path' => $path
				];
			}
		}
		return [];
	}

	/**
	 * Remove .part file extension and the ocTransferId from the file
	 * to get the original file name
	 *
	 * @param string $path
	 * @return string
	 */
	protected function stripPartFileExtension($path) {
		if (\pathinfo($path, PATHINFO_EXTENSION) === 'part') {
			$pos = \strrpos($path, '.', -6);
			$path = \substr($path, 0, $pos);
		}

		return $path;
	}

	/**
	 * Convert wildcard strings like '*.ext' to regex patterns like '/^.*?.ext$/'
	 *
	 * @param string $string
	 * @return string
	 */
	protected function wildcard2regex($string) {
		$pattern = \preg_quote(\trim($string), '/');
		$pattern = \str_replace(\preg_quote('*'), '.*', $pattern);
		return "/^$pattern$/";
	}

	/**
	 * Convert patterns back to wildcard strings
	 *
	 * @param string $pattern
	 * @return string
	 */
	protected function regex2wildcard($pattern) {
		$string = \substr(\trim($pattern), 2, -2);
		$string = \stripslashes($string);
		$string = \str_replace('.*', '*', $string);
		return $string;
	}
}
