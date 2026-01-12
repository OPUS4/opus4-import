<?php

/**
 * This file is part of OPUS. The software OPUS has been originally developed
 * at the University of Stuttgart with funding from the German Research Net,
 * the Federal Department of Higher Education and Research and the Ministry
 * of Science, Research and the Arts of the State of Baden-Wuerttemberg.
 *
 * OPUS 4 is a complete rewrite of the original OPUS software and was developed
 * by the Stuttgart University Library, the Library Service Center
 * Baden-Wuerttemberg, the Cooperative Library Network Berlin-Brandenburg,
 * the Saarland University and State Library, the Saxon State Library -
 * Dresden State and University Library, the Bielefeld University Library and
 * the University Library of Hamburg University of Technology with funding from
 * the German Research Foundation and the European Regional Development Fund.
 *
 * LICENCE
 * OPUS is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the Licence, or any later version.
 * OPUS is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU General Public License for more
 * details. You should have received a copy of the GNU General Public License
 * along with OPUS; if not, write to the Free Software Foundation, Inc., 51
 * Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 *
 * @copyright   Copyright (c) 2026, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 */

namespace Opus\Import;

use Opus\Common\DocumentInterface;
use Opus\Import\Xml\MetadataImportSkippedDocumentsException;
use Zend_Log;

use function array_diff;
use function scandir;

/**
 * Importer for SWORD interface.
 *
 * TODO move into opus4-sword
 */
class SwordImporter extends Importer
{
    /** @var mixed */
    private $statusDoc;

    /**
     * @param string        $xml
     * @param bool          $isFile
     * @param null|Zend_Log $logger
     * @param null|string   $logFile
     */
    public function __construct($xml, $isFile = false, $logger = null, $logFile = null)
    {
        parent::__construct($xml, $isFile, $logger, $logFile);

        $this->statusDoc = new ImportStatusDocument();

        // update of existing documents is not supported in SWORD context
        $this->setUpdateExistingDocuments(false);
    }

    /**
     * @return mixed
     */
    public function getStatusDoc()
    {
        return $this->statusDoc;
    }

    /**
     * SWORD imports should not stop at missing objects, like licences.
     *
     * @param string $msg
     * @return void
     */
    protected function errorMissingObject($msg)
    {
        $this->log($msg);
    }

    public function run()
    {
        try {
            parent::run();
        } catch (MetadataImportSkippedDocumentsException $ex) {
            // Exception should not be thrown for SWORD import
        }
    }

    /**
     * Add all files in the root level of the package to the currently processed document.
     *
     * @param DocumentInterface $doc
     * @return void
     */
    protected function processFiles($doc)
    {
        if ($this->isSingleDocImport()) {
            $files = array_diff(scandir($this->getImportDir()), ['..', '.', 'opus.xml']);
            foreach ($files as $file) {
                $this->addSingleFile($doc, $file);
            }
        }
    }

    /**
     * @param DocumentInterface $doc
     */
    protected function postStore($doc): void
    {
        $this->statusDoc->addDoc($doc);
    }
}
