<?php
/**
 * ownCloud - Files_antivirus
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Viktar Dubiniuk <dubiniuk@owncloud.com>
 *
 * @copyright Viktar Dubiniuk 2015-2018
 * @license AGPL-3.0
 */

namespace OCA\Files_Antivirus\Db;

use OCP\AppFramework\Db\Entity;
use JsonSerializable;

/**
 * Class Rule
 *
 * @package OCA\Files_Antivirus\Db
 */
class Rule extends Entity implements JsonSerializable {
	/*
	 * Rule needs to be validated by the exit code returned by scanner
	 */
	public const RULE_TYPE_CODE = 1;
	
	/*
	 * Rule needs to be validated by parsing the scanner output with regexp
	 */
	public const RULE_TYPE_MATCH = 2;

	/**
	 *
	 * @var int groupId - used for sorting
	 */
	protected $groupId;
	
	/**
	 *
	 * @var int statusType - RULE_TYPE_CODE or RULE_TYPE_MATCH defines whether
	 *   rule should be checked by the shell exit code or regexp
	 */
	protected $statusType;
	
	/**
	 *
	 * @var int result - shell exit code for rules
	 *   of the type RULE_TYPE_CODE, 0 otherwise
	 */
	protected $result;
	
	/**
	 *
	 * @var string match - regexp to match for rules
	 *   of the type RULE_TYPE_MATCH, '' otherwise
	 */
	protected $match;
	
	/**
	 *
	 * @var string description - shell exit code meaning for rules
	 *   of the type RULE_TYPE_CODE, '' otherwise
	 */
	protected $description;
	
	/**
	 *
	 * @var int status - file check status.
	 * SCANRESULT_UNCHECKED, SCANRESULT_INFECTED, SCANRESULT_CLEAN
	 * are matching Unknown, Infected and Clean files accordingly.
	 */
	protected $status;

	/**
	 * Pack data into json
	 *
	 * @return array
	 */
	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'group_id' => $this->groupId,
			'status_type' => $this->statusType,
			'result' => $this->result,
			'match' => $this->match,
			'description' => $this->description,
			'status' => $this->status
		];
	}
}
