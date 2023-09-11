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

namespace OCA\windows_network_drive\lib\kerberos;

use OCP\IConfig;
use OCP\ITempManager;
use OCP\ILogger;
use OCA\windows_network_drive\lib\kerberos\ccachestorage\ICCacheStorage;
use OCA\windows_network_drive\lib\kerberos\ccachestorage\DBStorage;
use OCA\windows_network_drive\lib\kerberos\CCacheCreator;
use OCA\windows_network_drive\lib\kerberos\MappingManager;

class CCacheHandler {
	/** @var IConfig */
	private $config;
	/** @var ICCacheStorage */
	private $ccacheStorage;
	/** @var CCacheCreator */
	private $ccacheCreator;
	/** @var MappingManager */
	private $mappingManager;
	/** @var ITempManager */
	private $tempManager;
	/** @var ILogger */
	private $logger;
	/** @var array */
	private $dataCache;

	public function __construct(
		IConfig $config,
		ICCacheStorage $ccacheStorage,
		CCacheCreator $ccacheCreator,
		MappingManager $mappingManager,
		ITempManager $tempManager,
		ILogger $logger
	) {
		$this->config = $config;
		$this->ccacheStorage = $ccacheStorage;
		$this->ccacheCreator = $ccacheCreator;
		$this->mappingManager = $mappingManager;
		$this->tempManager = $tempManager;
		$this->logger = $logger;
	}

	/**
	 * Return an array containing 2 elements: the actual serverId used (specially
	 * if it was empty) as first item, and the data associated to that serverId as
	 * second item.
	 * In case of error, both items will return false, as in [false, false]
	 * @param string $serverId the id to get the associated configuration
	 * @return array an array containing the serverId used and the associated
	 * configuration
	 */
	private function getConfiguredServerData($serverId = '') {
		if ($this->dataCache === null) {
			$this->dataCache = $this->config->getSystemValue('wnd.kerberos.servers', []);
			if (!\is_array($this->dataCache) || empty($this->dataCache)) {
				$this->logger->error('kerberos data for WND not set', ['app' => 'wnd']);
				$this->dataCache = [];
			} else {
				$valResult = $this->validateServerData($this->dataCache);
				// unset the ids failing the validation since they'll give problems.
				foreach ($valResult as $key => $value) {
					unset($this->dataCache[$key]);
				}
			}
		}

		if (empty($this->dataCache)) {
			return [false, false];
		}

		$serverData = [false, false];
		if ($serverId === '') {
			$data = \reset($this->dataCache);
			$key = \key($this->dataCache);  // will return the first key after resetting the pointer
			$serverData = [$key, $data];
		} else {
			if (isset($this->dataCache[$serverId])) {
				$serverData = [$serverId, $this->dataCache[$serverId]];
			}
		}

		return $serverData;
	}

	/**
	 * Validate the configuration. Errors will be logged for each wrong item.
	 * The method will return an array whose keys are the failed serverIds. The array
	 * will be empty if there is no error.
	 * The failing serverIds are expected to be removed from the cache (this method
	 * won't do it)
	 */
	private function validateServerData($dataCache) {
		$result = [];
		foreach ($dataCache as $serverId => $serverData) {
			if (!isset($serverData['ockeytab']) || !\is_string($serverData['ockeytab'])) {
				$this->logger->error("kerberos {$serverId}: required key 'ockeytab' doesn't have an appropiate value", ['app' => 'wnd']);
				$result[$serverId] = false;
			}
			if (!isset($serverData['ocservice']) || !\is_string($serverData['ocservice'])) {
				$this->logger->error("kerberos {$serverId}: required key 'ocservice' doesn't have an appropiate value", ['app' => 'wnd']);
				$result[$serverId] = false;
			}
		}
		return $result;
	}

	/**
	 * Create the credential cache for the server using the configuration stored
	 * as serverId. If the serverId is the empty string, the first configuration
	 * found will be used.
	 * The location of the created credential cache will be returned. Note that
	 * this might be a temporary location that might be removed when the script
	 * finish. In any case, this method will store the credential cache in the
	 * configured storage.
	 * The intention to return the temporary location is to be able to use the
	 * credential cache directly without having to retrieve it again.
	 * @param string $serverId the id of the configuration to be used to create
	 * the credential cache
	 * @return string|false the location of the created credential cache or false
	 * in case of error
	 */
	public function createServerCCache($serverId = '') {
		list($serverKey, $serverData) = $this->getConfiguredServerData($serverId);
		if ($serverData === false) {
			return false;
		}

		$targetLocation = $this->ccacheStorage->getRecommendedLocation($serverKey, '');
		if ($targetLocation === null) {
			$targetLocation = $this->tempManager->getTemporaryFile('krb5');
		}

		$this->ccacheCreator->createNewCCache(
			$targetLocation,
			$serverData['ocservice'],
			$serverData['ockeytab'],
			['forwardable' => true, 'proxiable' => true]
		);

		$result = $this->ccacheStorage->storeFrom($targetLocation, $serverKey, '');
		if ($result) {
			$this->logger->debug("server credential cache associated to {$serverKey} stored successfully", ['app' => 'wnd']);
		} else {
			$this->logger->warning("server credential cache associated to {$serverKey} failed to be stored", ['app' => 'wnd']);
			return false;
		}
		return $targetLocation;
	}

	/**
	 * Create the credential cache for the user using the configuration stored
	 * as serverId. If the serverId is the empty string, the first configuration
	 * found will be used.
	 * The location of the created credential cache will be returned. Note that
	 * this might be a temporary location that might be removed when the script
	 * finish. In any case, this method will store the credential cache in the
	 * configured storage.
	 *
	 * Note that this method runs a `kvno` shell command, so shell access is
	 * required.
	 *
	 * @param string $serverCCache the path where the server ccache is located.
	 * You usually retrieve this path from the `retrieveServerCCache` method
	 * @param string $remoteServer the remote server where we want to connect to.
	 * For example, "WIN-abcd.my.custom.domain"
	 * @param string $userId the windows' user id associated to this credential
	 * cache.
	 * @param string $serverId the id of the configuration to be used to create
	 * the credential cache
	 * @return string|false the location of the created credential cache or false
	 * in case of error
	 */
	public function createUserCCache($serverCCache, $remoteServer, $userId, $serverId = '') {
		list($serverKey, $serverData) = $this->getConfiguredServerData($serverId);
		if ($serverData === false) {
			return false;
		}

		$targetLocation = $this->ccacheStorage->getRecommendedLocation($serverKey, $userId);
		if ($targetLocation === null) {
			$targetLocation = $this->tempManager->getTemporaryFile('krb5');
		}

		$result = $this->ccacheCreator->acquireTicketForUser(
			$userId,
			$remoteServer,
			$serverData['ockeytab'],
			$serverCCache,
			$targetLocation
		);
		if ($result['exitCode'] !== 0) {
			$this->logger->debug("user credential cache for {$userId} associated to {$serverKey} failed to be created: " . \implode("\n", $result['output']), ['app' => 'wnd']);
			return false;
		}

		$result = $this->ccacheStorage->storeFrom($targetLocation, $serverKey, $userId);
		if ($result) {
			$this->logger->debug("user credential cache for {$userId} associated to {$serverKey} stored successfully", ['app' => 'wnd']);
		} else {
			$this->logger->warning("user credential cache for {$userId} associated to {$serverKey} failed to be stored", ['app' => 'wnd']);
			return false;
		}
		return $targetLocation;
	}

	/**
	 * Retrieve the credential cache for the server using the configuration stored
	 * as serverId. If the serverId is the empty string, the first configuration
	 * found will be used.
	 * The location of the created credential cache will be returned. Note that
	 * this might be a temporary location that might be removed when the script
	 * finish.
	 * @param string $serverId the id of the configuration to be used to retrieve
	 * the credential cache
	 * @return string|false the location of the created credential cache or false
	 * in case of error
	 */
	public function retrieveServerCCache($serverId = '') {
		list($serverKey, $serverData) = $this->getConfiguredServerData($serverId);
		if ($serverData === false) {
			return false;
		}

		$targetLocation = $this->ccacheStorage->getRecommendedLocation($serverKey, '');
		if ($targetLocation === null) {
			$targetLocation = $this->tempManager->getTemporaryFile('krb5');
		}
		$data = $this->ccacheStorage->retrieveTo($targetLocation, $serverKey, '');

		if (empty($data)) {
			$this->logger->debug("server credential cache associated to {$serverKey} not found", ['app' => 'wnd']);
			return false;
		}

		$ccacheTtl = 60 * 60 * 9; // 9 hours
		if (isset($serverData['ccachettl']) && \is_int($serverData['ccachettl'])) {
			$ccacheTtl = $serverData['ccachettl'];
		}

		if ($data['creationTime'] + $ccacheTtl < \time()) {
			// credential cache is outdated -> return false so it can be recreated
			$this->logger->debug("server credential cache associated to {$serverKey} is outdated", ['app' => 'wnd']);
			return false;
		}

		// check validity and recreate if needed
		return $data['ccacheFilename'];
	}

	/**
	 * Retrieve the credential cache for the user using the configuration stored
	 * as serverId. If the serverId is the empty string, the first configuration
	 * found will be used.
	 * The location of the created credential cache will be returned. Note that
	 * this might be a temporary location that might be removed when the script
	 * finish.
	 * @param string $userId the windows' user id associated to the credential
	 * cache
	 * @param string $serverId the id of the configuration to be used to retrieve
	 * the credential cache
	 * @return string|false the location of the created credential cache or false
	 * in case of error
	 */
	public function retrieveUserCCache($userId, $serverId = '') {
		list($serverKey, $serverData) = $this->getConfiguredServerData($serverId);
		if ($serverData === false) {
			return false;
		}

		$targetLocation = $this->ccacheStorage->getRecommendedLocation($serverKey, $userId);
		if ($targetLocation === null) {
			$targetLocation = $this->tempManager->getTemporaryFile('krb5');
		}
		$data = $this->ccacheStorage->retrieveTo($targetLocation, $serverKey, $userId);

		if (empty($data)) {
			$this->logger->debug("user credential cache for {$userId} associated to {$serverKey} not found", ['app' => 'wnd']);
			return false;
		}

		$ccacheTtl = 60 * 60 * 9; // 9 hours
		if (isset($serverData['ccachettl']) && \is_int($serverData['ccachettl'])) {
			$ccacheTtl = $serverData['ccachettl'];
		}

		if ($data['creationTime'] + $ccacheTtl < \time()) {
			// credential cache is outdated -> return false so it can be recreated
			$this->logger->debug("user credential cache for {$userId} associated to {$serverKey} is outdated", ['app' => 'wnd']);
			return false;
		}

		// check validity and recreate if needed
		return $data['ccacheFilename'];
	}

	/**
	 * Remove the credentials older than the timestamp from the credentials storage
	 * linked to this ccache handler
	 * @param int $timestamp
	 * @return int the number of entries deleted
	 */
	public function removeObsoleteCredentials($timestamp) {
		return $this->ccacheStorage->deleteEntriesOlderThan($timestamp);
	}

	private function getMapping($serverData) {
		$mappingType = 'Noop';
		$mappingParams = [];
		if (isset($serverData['usermapping']['type'])) {
			$mappingType = $serverData['usermapping']['type'];
			if (isset($serverData['usermapping']['params'])) {
				$mappingParams = $serverData['usermapping']['params'];
			}
		}
		return $this->mappingManager->getMapping($mappingType, $mappingParams);
	}

	/**
	 * Map the userId based on the mapping currently used by this CCacheHandler.
	 * @param string $userId the user id to be mapped
	 * @return string the mapped user id
	 */
	public function getMappedUser($userId, $serverId = '') {
		list($serverKey, $serverData) = $this->getConfiguredServerData($serverId);
		if ($serverData === false) {
			return '';
		}

		$mapping = $this->getMapping($serverData);
		if ($mapping === false) {
			$this->logger->debug("Failed to get appropiated user mapping for {$serverKey}. Falling back to 'Noop' mapping", ['app' => 'wnd']);
			$mapping = $this->mappingManager->getMapping('Noop', []);
		}
		return $mapping->mapOcToWindows($userId);
	}

	/**
	 * Get a CCacheHandler with the specified userMapping. If the mappingId is missing
	 * a "Noop" mapping will be used
	 * Note that the CCacheHandler will use a DB storage to store the credential cache
	 * since there isn't any other available at the moment.
	 * @param IConfig $config
	 * @param string $userMappingId the mapping id in the config.php
	 * @return CCacheHandler
	 */
	public static function getCCacheHandlerFromConfig(IConfig $config) {
		// ccache storage type should be configurable. Right now, only DB is available
		$ccacheStorage = new DBStorage(\OC::$server->getDatabaseConnection(), \OC::$server->getTimeFactory());
		$mappingManager = new MappingManager();

		return new CCacheHandler($config, $ccacheStorage, new CCacheCreator(), $mappingManager, \OC::$server->getTempManager(), \OC::$server->getLogger());
	}
}
