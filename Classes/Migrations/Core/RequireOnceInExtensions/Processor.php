<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013 Peter Beernink <p.beernink@drecomm.nl>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Class Tx_Smoothmigration_Migrations_Core_RequireOnceInExtensions_Processor
 *
 * @author Peter Beernink
 */
class Tx_Smoothmigration_Migrations_Core_RequireOnceInExtensions_Processor extends Tx_Smoothmigration_Migrations_AbstractMigrationProcessor {

	/**
	 * Removed line count registry
	 *
	 * @var array
	 */
	private $locationCounters = array();

	/**
	 * Execute migration
	 *
	 * @return void
	 */
	public function execute() {
		$this->getPendingIssues($this->parentMigration->getIdentifier());
		if (count($this->issues)) {
			$this->messageService->message(count($this->issues) . ' issues found');

			foreach ($this->issues as $issue) {
				$this->handleIssue($issue);
				$this->messageService->message();
				$this->issueRepository->update($issue);
			}
		} else {
			$this->messageService->successMessage('No issues found', TRUE);
		}

		$persistenceManger = $this->objectManager->get('Tx_Extbase_Persistence_Manager');
		$persistenceManger->persistAll();
	}

	/**
	 * Handle issue
	 *
	 * @param Tx_Smoothmigration_Domain_Model_Issue $issue
	 * @return void
	 */
	protected function handleIssue(Tx_Smoothmigration_Domain_Model_Issue $issue) {
		if (is_string($issue->getLocationInfo())) {
			$locationInfo = unserialize($issue->getLocationInfo());
		} else {
			$locationInfo = $issue->getLocationInfo();
		}

		$this->messageService->message($locationInfo->getFilePath() . ':' . $locationInfo->getLineNumber() . ' [' . trim($locationInfo->getMatchedString()) . '] => ');

		if ($issue->getMigrationStatus() != 0) {
			$this->messageService->successMessage('already migrated', TRUE);
			return;
		}
		$newFileContent = '';
		if (!file_exists($locationInfo->getFilePath())) {
			$issue->setMigrationStatus(Tx_Smoothmigration_Domain_Interface_Migration::ERROR_FILE_NOT_FOUND);
			$this->messageService->errorMessage('Error, file not found', TRUE);
			return;
		}
		if (!is_writable($locationInfo->getFilePath())) {
			$issue->setMigrationStatus(Tx_Smoothmigration_Domain_Interface_Migration::ERROR_FILE_NOT_WRITABLE);
			$this->messageService->errorMessage('Error, file not writable', TRUE);
			return;
		}
		$fileObject = new SplFileObject($locationInfo->getFilePath());
		// If more than one line needs to be removed from the same file, only the
		// first would succeed as the line numbering will change after removing
		// the first line. Therefore we need to keep track of the number of lines
		// removed per file. We use this offset to read the file.
		$lineOffset = $this->locationCounters[$locationInfo->getFilePath()];

		foreach ($fileObject as $lineNumber => $lineContent) {
			if ($lineNumber + 1 + $lineOffset != $locationInfo->getLineNumber()) {
				$newFileContent .= $lineContent;
			} else {
				$newLineContent = str_replace($locationInfo->getMatchedString(), LF, $lineContent);
				if ($newLineContent == $lineContent) {
					$issue->setMigrationStatus(Tx_Smoothmigration_Domain_Interface_Migration::ERROR_FILE_NOT_CHANGED);
					$this->messageService->errorMessage($this->ll('migrationsstatus.4'), TRUE);
					return;
				}
				$newFileContent .= $newLineContent;
			}
		}
		file_put_contents($locationInfo->getFilePath(), $newFileContent);
		$issue->setMigrationStatus(Tx_Smoothmigration_Domain_Interface_Migration::SUCCESS);
		$this->messageService->successMessage('Succes', TRUE);
		if (!isset($this->locationCounters[$locationInfo->getFilePath()])) {
			$this->locationCounters[$locationInfo->getFilePath()] = 1;
		} else {
			$this->locationCounters[$locationInfo->getFilePath()]++;
		}
	}

}
