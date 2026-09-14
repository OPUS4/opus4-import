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

namespace Opus\Import\Csv;

use Opus\Common\DocumentInterface;
use Opus\Common\Repository;
use Opus\ModelFactory;

use function in_array;
use function ucfirst;

/**
 * TODO there probably should be an Interface as well
 * TODO make full default processor for simple fields?
 */
class DefaultColumnProcessor implements ColumnProcessorInterface
{
    /** Column number */
    private int $columnNo;

    /** Allow multiple values in column */
    private bool $multiValueEnabled = false;

    /** Separator for multiple values */
    private string $multiValueSeparator = '||';

    /** Model type */
    private string $modelType = 'Document';

    /** Field names existing in model */
    private ?array $modelFields = null;

    /** Field name */
    private ?string $fieldName = null;

    public function setColumnNo(int $columnNo): self
    {
        $this->columnNo = $columnNo;
        return $this;
    }

    public function getColumnNo(): int
    {
        return $this->columnNo;
    }

    /**
     * Returns number of columns a processor uses.
     *
     * This is useful when the fields of an object, like a title, are spread across multiple columns.
     */
    public function getColumnCount(): int
    {
        return 1;
    }

    public function setMultiValueEnabled(bool $multiValueEnabled): self
    {
        $this->multiValueEnabled = $multiValueEnabled;
        return $this;
    }

    public function isMultiValueEnabled(): bool
    {
        return $this->multiValueEnabled;
    }

    public function setMultiValueSeparator(?string $separator): self
    {
        $this->multiValueSeparator = $separator ?? '||';
        return $this;
    }

    public function getMultiValueSeparator(): string
    {
        return $this->multiValueSeparator;
    }

    public function setModel(string $modelName): self
    {
        $model = ModelFactory::get($modelName);
        if (null !== $model) {
            $this->modelDescriptor = $model->getModelDescriptor();
        }
        return $this;
    }

    public function process(array $row, DocumentInterface $document): void
    {
        $value = $row[$this->getColumnNo()];

        $method = 'set' . ucfirst($this->getFieldName());

        $document->$method($value);
    }

    /**
     * TODO use ModelDescriptor
     */
    protected function fieldExists(string $fieldName): bool
    {
        return in_array($fieldName, $this->getModelFields());
    }

    public function getModelType(): string
    {
        return $this->modelType;
    }

    public function setModelType(string $modelType): self
    {
        $this->modelType   = $modelType;
        $this->modelFields = null;
        return $this;
    }

    public function getModelFields(): array
    {
        // TODO deal with modelType === null OR unknown

        if (null === $this->modelFields) {
            $modelFactory      = Repository::getInstance()->getModelFactory();
            $model             = $modelFactory->create($this->getModelType());
            $this->modelFields = $model->describe();
        }

        return $this->modelFields;
    }

    public function setFieldName(?string $name): self
    {
        $this->fieldName = $name;
        return $this;
    }

    public function getFieldName(): ?string
    {
        return $this->fieldName;
    }
}
