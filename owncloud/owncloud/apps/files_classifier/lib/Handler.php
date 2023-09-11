<?php
/**
 *
 * @copyright Copyright (c) 2022, ownCloud GmbH
 * @license OCL
 *
 * This code is covered by the ownCloud Commercial License.
 *
 * You should have received a copy of the ownCloud Commercial License
 * along with this program. If not, see <https://owncloud.com/licenses/owncloud-commercial/>.
 *
 */

namespace OCA\FilesClassifier;

use OC\Files\Filesystem;
use OC\Files\Node\Folder;
use OCA\DAV\Connector\Sabre\Exception\Forbidden;
use OCA\FilesClassifier\Model\RuleCollection;
use OCP\Files\File;
use OCP\Files\ForbiddenException;
use OCP\Files\Node;
use OCP\Files\NotPermittedException;
use OCP\Files\IRootFolder;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Lock\ILockingProvider;
use OCP\Share;
use OCP\Share\IManager as IShareManager;
use OCP\Share\Exceptions\GenericShareException;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\ISystemTagObjectMapper;
use OCP\ILogger;
use OCP\IL10N;

// FIXME: refactor this class into more specialized classes as it is hard to understand and unit test

class Handler {
	/** @var IUser  */
	private $user;
	/** @var IUserSession  */
	private $userSession;
	/** @var ISystemTagManager */
	private $tagManager;
	/** @var ISystemTagObjectMapper */
	private $tagObjectMapper;
	/** @var IShareManager */
	private $shareManager;
	/** @var IRootFolder */
	private $rootFolder;
	/** @var Persistence  */
	private $persistence;
	/** @var DocumentProperties */
	private $documentProperties;
	/** @var IL10N */
	private $l10n;
	/** @var ILogger */
	private $logger;

	private $propertiesCache = [];

	/**
	 * @var RuleCollection|null
	 */
	private $classificationRules;

	public function __construct(
		IUserSession $userSession,
		ISystemTagManager $tagManager,
		ISystemTagObjectMapper $tagObjectMapper,
		IShareManager $shareManager,
		IRootFolder $rootFolder,
		Persistence $persistence,
		DocumentProperties $documentProperties,
		IL10N $l10n,
		ILogger $logger
	) {
		$this->user = $userSession->getUser();
		$this->userSession = $userSession;
		$this->tagManager = $tagManager;
		$this->tagObjectMapper = $tagObjectMapper;
		$this->persistence = $persistence;
		$this->documentProperties = $documentProperties;
		$this->shareManager = $shareManager;
		$this->rootFolder = $rootFolder;
		$this->l10n = $l10n;
		$this->logger = $logger;
	}

	/**
	 * Hook handler method. Prevents creation of classified file after it is uploaded
	 *
	 * @param array $args
	 *
	 * @throws ForbiddenException
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotFoundException
	 * @throws \OCP\Lock\LockedException
	 */
	public function postWrite($args) {
		if (!isset($args['path'])) {
			return;
		}
		$file = $this->rootFolder->get($args['path']);
		if ($file instanceof File) {
			$this->postOperationClassify($file);
		}
	}

	/**
	 * @param string $path
	 * @return Node
	 * @throws \OCP\Files\NotFoundException
	 */
	private function getNodeByPath($path) {
		// FIXME: this is wrong check for public share,
		// as user can be logged-in and access public share or some operation
		// can move from/to public share. Use with caution.
		if ($this->user === null) {
			// public share ... get the node the old way ...
			$view = Filesystem::getView();
			$absolutePath = $view->getAbsolutePath($path);
			$node = $this->rootFolder->get($absolutePath);
		} else {
			$node = $this->rootFolder->getUserFolder($this->user->getUID())->get($path);
		}
		return $node;
	}

	/**
	 * @param int $fileId
	 * @return Node|null
	 */
	private function getNodeById($fileId) {
		// FIXME: this is wrong check for public share,
		// as user can be logged-in and access public share or some operation
		// can move from/to public share. Use with caution.
		if ($this->user === null) {
			// public share ...
			$nodes = $this->rootFolder->getById($fileId, true);
		} else {
			$nodes = $this->rootFolder->getUserFolder($this->user->getUID())->getById($fileId, true);
		}
		if (!empty($nodes)) {
			return $nodes[0]; // we only need one node, path does not matter
		}
		return null;
	}

	/**
	 * Given a node this method returns all parent
	 * and all child node Ids recursively.
	 *
	 * @param Node $node
	 * @param bool $parentOnly set to only get parent nodeIds
	 * @return array
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotFoundException
	 */
	private function getAllNodeIds(Node $node, $parentOnly = false) {
		$nodes = $this->getAllNodes($node, $parentOnly);

		return \array_unique(\array_map(
			function ($node) {
				return $node->getId();
			},
			$nodes
		));
	}

	/**
	 * Given a node this method returns all parent
	 * and all child nodes recursively.
	 *
	 * @param Node $node
	 * @param bool $parentOnly set to only get parent nodeIds
	 * @return array
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotFoundException
	 */
	private function getAllNodes(Node $node, $parentOnly = false) {
		$user = $this->userSession->getUser();
		if (!$user) {
			$home = null;
		} else {
			$home = $this->rootFolder->getUserFolder($user->getUID());
		}
		$nodes = [];

		if ($home !== null) {
			if (!$parentOnly && $node instanceof Folder) {
				foreach ($this->getRecursiveIterator($node) as $n) {
					$nodes[] = $n;
				}
			}

			// Check parents
			do {
				$nodes[] = $node;
				$node = $node->getParent();
				$node->getId();
			} while ($node->getId() !== $home->getId() && $node->getPath() !== '/');
		}

		return $nodes;
	}

	/**
	 * Given a node this method returns all parent
	 * and all child classification tags recursively.
	 *
	 * Set parentOnly to only get parent tags
	 *
	 * @param Node $node
	 * @param bool $parentOnly
	 * @return ClassificationTag[]
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotFoundException
	 */
	private function getTags(Node $node, $parentOnly = false) {
		$allNodes = $this->getAllNodeIds($node, $parentOnly);

		$objectsTags = $this->tagObjectMapper
			->getTagIdsForObjects($allNodes, 'files');

		$rules = $this->getClassificationRules();
		/** @var ClassificationTag[] $tags */
		$tags = [];
		foreach ($objectsTags as $objectFileId => $objectTags) {
			foreach ($objectTags as $tagId) {
				if (isset($rules[$tagId])) {
					if (!isset($tags[$tagId])) {
						$tags[$tagId] = new ClassificationTag($rules[$tagId]);
					}
					// Save each tagged fileId with tag to show error message later
					$tags[$tagId]->addFileId($objectFileId);
				}
			}
		}

		return $tags;
	}

	/**
	 * preShare listener, check if shared directory contains
	 * classified files.
	 *
	 * @param array $params
	 * @throws GenericShareException
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotFoundException
	 */
	public function policyDisallowLinkShares($params) {
		if ($params['shareType'] === Share::SHARE_TYPE_LINK) {
			$node = $this->getNodeById($params['fileSource']);
			if ($this->isLinkShareDisallowed($node, $disallowedIds, $disallowedTag)) {
				$fileIds = \array_slice($disallowedIds, 0, 3);
				$fileNames = \implode(", ", $this->formatFileIdsToPaths($fileIds));

				$msg = $this->l10n->n(
					'The file \'%1$s\' can\'t be shared via public link (classified as \'%2$s\')',
					'The files \'%1$s\' can\'t be shared via public link (classified as \'%2$s\')',
					\count($fileIds),
					[$fileNames, $disallowedTag]
				);

				throw new GenericShareException($msg);
			}
		}
	}

	/**
	 * Recursively scans all parent and child nodes for classification tags
	 * which disallow link-shares.
	 *
	 * @param Node $node
	 * @param array $disallowedFileIds Returns which fileIds are disallowed (NOTE: legacy)
	 * @param string $disallowedTag Returns which tag makes the file disallowed (NOTE: legacy)
	 * @return bool is linkshare disallowed
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotFoundException
	 */
	private function isLinkShareDisallowed($node, &$disallowedFileIds = [], &$disallowedTag = null) {
		$tags = $this->getTags($node);
		foreach ($tags as $tagId => $tag) {
			if (!$tag->getRule()->getIsLinkShareAllowed()) {
				$disallowedFileIds = $tags[$tagId]->getFileIds();
				$disallowedTag = $this->tagManager->getTagsByIds($tagId)[$tagId]->getName();
				return true;
			}
		}

		return false;
	}

	/**
	 * Used for display only, if there are more than 3 fileIds
	 * only the first 3 are shown (file1, file2, file3, ...)
	 *
	 * @param array $fileIds
	 * @return array
	 */
	private function formatFileIdsToPaths(array $fileIds) {
		$paths = [];
		$moreFileIdsExist = false;
		foreach ($fileIds as $fileId) {
			$node = $this->getNodeById($fileId);
			if ($node) {
				if (\count($paths) < 3) {
					$paths[] = $node->getName();
				} else {
					$moreFileIdsExist = true;
				}
			}
		}

		if ($moreFileIdsExist) {
			$paths[] = '...';
		}

		return $paths;
	}

	/**
	 * @param Folder $folder
	 * @return \RecursiveIteratorIterator
	 * @throws \OCP\Files\NotFoundException
	 */
	public function getRecursiveIterator(Folder $folder) {
		return new \RecursiveIteratorIterator(
			new RecursiveNodeIterator($folder->getDirectoryListing()),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
	}

	/**
	 * @param array $params
	 * @throws Share\Exceptions\ShareNotFound
	 * @throws \Exception
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotFoundException
	 */
	public function policyExpireLinkNoPassword($params) {
		if ($params['passwordSet'] === true) {
			return;
		}

		// FIXME: refactor this class to move this call to constructor
		$urlPath = \OC::$server->getRequest()->getPathInfo();
		// get id from url on updates
		if (\preg_match('#/apps/files_sharing/api/v1/shares/(.*)$#', $urlPath, $matches)) {
			$shareId = $matches[1];
			$providerId = $params['shareType'] === Share::SHARE_TYPE_REMOTE ? 'ocFederatedSharing' : 'ocinternal';
			$share = $this->shareManager->getShareById("$providerId:$shareId");
			$node = $share->getNode();
		} else {
			//get path from POST when creating a link
			$path = \OC::$server->getRequest()->getParam('path');
			$node = $this->getNodeByPath($path);
		}

		$minExpireAfterDays = null;
		$minExpiryTagName = 'unknown-tag-name';

		foreach ($this->getTags($node) as $tagId => $tag) {
			$rule = $tag->getRule();
			$expireAfterDays = $rule->getDaysUntilPasswordlessLinkSharesExpire();
			if ($expireAfterDays !== null && $expireAfterDays > 0) {
				if ($minExpireAfterDays === null || $expireAfterDays < $minExpireAfterDays) {
					$minExpireAfterDays = $expireAfterDays;
					$minExpiryTagName = $this->tagManager->getTagsByIds($tagId)[$tagId]->getName();
				}
			}
		}

		if ($minExpireAfterDays !== null) {
			$ruleExpiryDate = new \DateTime();
			$ruleExpiryDate->setTime(0, 0, 0);
			$ruleExpiryDate->add(new \DateInterval("P{$minExpireAfterDays}D"));
			if ($params['expirationDate'] === null) {
				$params['expirationDate'] = $ruleExpiryDate;
			}

			if ($params['expirationDate'] > $ruleExpiryDate) {
				$params['accepted'] = false;
				$params['message'] = $this->l10n->t(
					'The expiration date cannot exceed %1$s days (classified as \'%2$s\').',
					[$minExpireAfterDays, $minExpiryTagName]
				);
			}
		}
	}

	/**
	 * @param array $params
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotFoundException
	 */
	public function policyExpireLinkNoPasswordOnPasswordChange($params) {
		if ($params['disabled'] === true) {
			$maxDays = null;

			$node = $this->getNodeById($params['itemSource']);
			foreach ($this->getTags($node) as $tagId => $tag) {
				/* @phan-suppress-next-line PhanTypeArraySuspicious */
				if ($tag['policies']['expireLinkNoPassword'] > 0 &&
					/* @phan-suppress-next-line PhanTypeArraySuspicious */
					($maxDays === null || $tag['policies']['expireLinkNoPassword'] < $maxDays)) {
					/* @phan-suppress-next-line PhanTypeArraySuspicious */
					$maxDays = $tag['policies']['expireLinkNoPassword'];
				}
			}
			if ($maxDays) {
				$date = \date('d-m-Y', \strtotime("+$maxDays days"));
				Share::setExpirationDate($params['itemType'], $params['itemSource'], $date);
			}
		}
	}

	/**
	 * Hook handler method. Make sure classification tags are set and validated at new path
	 *
	 * @param array $args
	 *
	 * @throws ForbiddenException
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotFoundException
	 */
	public function postMoveAndCopy($args) {
		if (!isset($args['newpath'])) {
			return;
		}

		// Skip if just file rename without dir move
		if (\dirname($args['oldpath']) === \dirname($args['newpath'])) {
			return;
		};

		$file = $this->rootFolder->get($args['newpath']);
		if ($file instanceof File) {
			$this->postOperationClassify($file);
		}
	}

	/**
	 * Hook handler method. Prevents move or copy of classified files to directories which
	 * are shared by public link.
	 *
	 * @param array $args
	 *
	 * @throws ForbiddenException
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotFoundException
	 */
	public function preMoveAndCopy($args) {
		// Skip if just file rename without dir move
		if (\dirname($args['oldpath']) === \dirname($args['newpath'])) {
			return;
		};

		// Get rename target dir path and corresponding node
		$targetDirPath = \dirname($args['newpath']);
		$targetDirNode = $this->rootFolder->get($targetDirPath);

		// Get old path node
		$oldPathNode = $this->rootFolder->get($args['oldpath']);

		// iterate over target (newpath) parent nodes to validate tag policies
		$targetParentNodes = $this->getAllNodes($targetDirNode, true);
		foreach ($targetParentNodes as $targetParentNode) {
			$ownerId = $targetParentNode->getOwner()->getUID();
			$shares = $this->shareManager->getAllSharesBy(
				$ownerId,
				[Share::SHARE_TYPE_LINK],
				[$targetParentNode->getId()],
				true
			);

			// check tags of source node whether it allows move into public link folder
			if (!empty($shares) && $this->isLinkShareDisallowed($oldPathNode, $disallowedFileIds, $disallowedTag)) {
				$msg = $this->l10n->t(
					"A policy prohibits moving files classified as '%s' into publicly shared folders.",
					[$disallowedTag]
				);

				throw new ForbiddenException($msg, false);
			}
		}
	}

	/**
	 * @return RuleCollection
	 */
	public function getClassificationRules() {
		if ($this->classificationRules === null) {
			$this->classificationRules = $this->persistence->loadRulesIndexedByTagId();
		}
		return $this->classificationRules;
	}

	/**
	 * Common helper method for post operation hook
	 * classification and enforcement of policies on the file
	 *
	 * @param File $file
	 * @return array
	 *
	 * @throws Forbidden
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotFoundException
	 * @throws \OCP\Lock\LockedException
	 */
	public function postOperationClassify(File $file) {
		if (!$this->documentProperties->isDocumentSupported($file->getName())) {
			return [];
		}

		// FIXME: refactor this class to move this call to constructor
		$tempFile = \OC::$server->getTempManager()->getTemporaryFile();

		try {
			// zip archive needs a temp file for scan
			\file_put_contents($tempFile, $file->fopen('rb'));
			$properties = $this->documentProperties->scan($tempFile);
		} catch (NotPermittedException $e) {
			$originalPath = $file->getInternalPath();
			if ($originalPath !== '' && isset($this->propertiesCache[$originalPath])) {
				$properties = $this->propertiesCache[$originalPath];
			} else {
				throw $e;
			}
		}

		if (!$properties) {
			return [];
		}

		$assignedTags = [];

		foreach ($this->getClassificationRules() as $tagId => $rule) {
			$tag = $this->tagManager->getTagsByIds($tagId)[$tagId];
			if ($rule->hasDocumentIdQuery()) {
				$docIds = $properties->xpath($rule->getDocumentIdXpath());
				$this->logger->info("Checking classified file '{$file->getName()}' with document id '{$docIds[0]}'", ['app' => 'files_classifier']);
			}

			// check if file matches classification
			foreach ($properties->xpath($rule->getXpath()) as $property) {
				$this->logger->info($this->getUserId()." uploaded a classified file '{$file->getName()}' with document class '$property'", ['app' => 'files_classifier']);
				if ((string) $property === $rule->getValue()) {
					$this->logger->debug("Assigning tag '{$tag->getName()}' to '{$file->getName()}'", ['app' => 'files_classifier']);
					$this->tagObjectMapper->assignTags((string) $file->getId(), 'files', $tagId);

					if (!$rule->getIsUploadAllowed()) {
						// NOTE: if result of MOVE, this is ok as this document should not have been uploaded or should no longer exist
						// in the system (cases where tag got created before or after this file was uploaded).
						$file->unlock(ILockingProvider::LOCK_SHARED);
						$file->delete();
						$msg = $this->l10n->t(
							"A policy prohibits uploading files classified as '%s'.",
							[$tag->getName()]
						);

						throw new Forbidden($msg, false);
					}

					$parentNodes = $this->getAllNodeIds($file, true);

					foreach ($parentNodes as $nodeId) {
						$ownerId = $this->getNodeById($nodeId)->getOwner()->getUID();
						$shares = $this->shareManager->getAllSharesBy(
							$ownerId,
							[Share::SHARE_TYPE_LINK],
							[$nodeId],
							true
						);

						if (!empty($shares) && $this->isLinkShareDisallowed($file, $disallowedFileIds, $disallowedTag)) {
							// NOTE: if it is a result of MOVE, this should have been checked by preMoveAndCopy,
							//       otherwise it will be deleted from the system overall.
							$file->unlock(ILockingProvider::LOCK_SHARED);
							$file->delete();
							$msg = $this->l10n->t(
								"A policy prohibits uploading files classified as '%s' into publicly shared folders.",
								[$disallowedTag]
							);

							throw new Forbidden($msg, false);
						}
					}
					$assignedTags[] = $tag->getName();
				}
			}
		}
		return $assignedTags;
	}

	/**
	 * Common helper method for fopen operation hook for
	 * classification and enforcement of policies on the file
	 *
	 * @param string $filePath path to file contents (e.g. temp)
	 * @param string $originalPath path to owncloud destination file
	 *
	 * @throws Forbidden
	 */
	public function fopenClassify($filePath, $originalPath) {
		// FIXME: refactor this caching as a bit confusing
		// and exposes caching logic to outside (filePath&originalPath logic..)
		$fileName = \basename($filePath);
		$properties = $this->documentProperties->scan($filePath);
		$this->propertiesCache[$originalPath] = $properties;

		if (!$properties) {
			return;
		}

		foreach ($this->getClassificationRules() as $tagId => $rule) {
			$tag = $this->tagManager->getTagsByIds($tagId)[$tagId];
			if ($rule->hasDocumentIdQuery()) {
				$docIds = $properties->xpath($rule->getDocumentIdXpath());
				$this->logger->info("Checking classified file '{$fileName}' with document id '{$docIds[0]}'", ['app' => 'files_classifier']);
			}

			// check if file matches classification
			foreach ($properties->xpath($rule->getXpath()) as $property) {
				if ((string) $property === $rule->getValue()) {
					if (!$rule->getIsUploadAllowed()) {
						$msg = $this->l10n->t(
							"A policy prohibits uploading files classified as '%s'.",
							[$tag->getName()]
						);
						throw new Forbidden($msg, false);
					}
				}
				$this->logger->info($this->getUserId()." uploaded a classified file '{$fileName}' with document class '$property'", ['app' => 'files_classifier']);
			}
		}
	}

	/**
	 * @return string
	 */
	private function getUserId() {
		if ($this->user === null) {
			$uid = 'Unauthenticated user';
		} else {
			$uid = $this->user->getUID();
		}
		return $uid;
	}
}
