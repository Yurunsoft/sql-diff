<?php

declare(strict_types=1);

namespace Yurun\SqlDiff;

use PhpMyAdmin\SqlParser\Components\AlterOperation;
use PhpMyAdmin\SqlParser\Components\Expression;
use PhpMyAdmin\SqlParser\Components\OptionsArray;
use PhpMyAdmin\SqlParser\Components\PartitionDefinition;
use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\AlterStatement;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use PhpMyAdmin\SqlParser\Token;

class SqlDiff
{
    private Parser $oldParser;

    private Parser $newParser;

    /**
     * @var array<string, CreateStatement>
     */
    private array $oldTableStatements = [];

    /**
     * @var array<string, CreateStatement>
     */
    private array $newTableStatements = [];

    public const INDEX_TYPES = ['INDEX', 'PRIMARY KEY'];

    public const FOREIGN_KEY_TYPE = 'FOREIGN KEY';

    public const INT_TYPES = ['INT', 'SMALLINT', 'TINYINT', 'MEDIUMINT', 'BIGINT'];

    public static function diff(string $oldSql, string $newSql): array
    {
        return (new self($oldSql, $newSql))->getDiffSqls();
    }

    public function __construct(string $oldSql, string $newSql)
    {
        $this->oldParser = new Parser($oldSql);
        $this->newParser = new Parser($newSql);
        foreach ($this->oldParser->statements as $statement)
        {
            if ($statement instanceof CreateStatement)
            {
                $this->oldTableStatements[$statement->name->__toString()] = $statement;
            }
        }
        foreach ($this->newParser->statements as $statement)
        {
            if ($statement instanceof CreateStatement)
            {
                $this->newTableStatements[$statement->name->__toString()] = $statement;
            }
        }
    }

    public function getDiffSqls(): array
    {
        return array_merge(
            $this->getDropTableSqls(),
            $this->getCreateTableSqls(),
            $this->getAlterTableSqls(),
        );
    }

    public function getDropTableSqls(): array
    {
        $sqls = [];
        foreach (array_diff_key($this->oldTableStatements, $this->newTableStatements) as $key => $_)
        {
            if ($this->oldTableStatements[$key]->options->has('VIEW'))
            {
                $sqls[] = 'DROP VIEW ' . $this->oldTableStatements[$key]->name;
            }
            else
            {
                $sqls[] = 'DROP TABLE ' . $this->oldTableStatements[$key]->name;
            }
        }

        return $sqls;
    }

    public function getCreateTableSqls(): array
    {
        $sqls = [];
        /** @var CreateStatement $statement */
        foreach (array_diff_key($this->newTableStatements, $this->oldTableStatements) as $statement)
        {
            $sqls[] = $statement->__toString();
        }

        return $sqls;
    }

    public function getAlterTableSqls(): array
    {
        $sqls = [];
        foreach (array_intersect_key($this->oldTableStatements, $this->newTableStatements) as $key => $_)
        {
            $oldStatement = $this->oldTableStatements[$key];
            $newStatement = $this->newTableStatements[$key];
            if ($this->oldTableStatements[$key]->options->has('VIEW'))
            {
                if ($newStatement->__toString() !== $oldStatement->__toString())
                {
                    if (!$newStatement->options->has('OR REPLACE'))
                    {
                        $newOptions = $newStatement->options->options;
                        array_unshift($newOptions, 'OR REPLACE');
                        $newStatement->options = new OptionsArray($newOptions);
                    }
                    $sqls[] = $newStatement->__toString();
                }
            }
            else
            {
                // 索引
                $indexesSqls = $this->getIndexesDiffSqls($oldStatement, $newStatement);
                // 外键
                $constraintSqls = $this->getConstraintDiffSqls($oldStatement, $newStatement);
                $sqls = array_merge($sqls,
                    // 表
                    $this->getTableOptionsSqls($oldStatement, $newStatement),
                    $constraintSqls['drop'],
                    $indexesSqls['drop'],
                    // 字段
                    $this->getFieldsDiffSqls($oldStatement, $newStatement),
                    $indexesSqls['add'],
                    $constraintSqls['add'],
                    // 分区
                    $this->getPartitionDiffSqls($oldStatement, $newStatement),
                );
            }
        }

        return $sqls;
    }

    public function getTableOptionsSqls(CreateStatement $oldStatement, CreateStatement $newStatement): array
    {
        $options = [];
        foreach ($newStatement->entityOptions->options as $option)
        {
            if ($option['expr'] !== $oldStatement->entityOptions->has($option['name'], true))
            {
                $options[] = $option;
            }
        }
        if ($options)
        {
            $alterStatement = new AlterStatement();
            $alterStatement->options = new OptionsArray(['TABLE']);
            $alterStatement->table = $oldStatement->name;
            $alterOperation = new AlterOperation(new OptionsArray($options));
            $alterStatement->altered[] = $alterOperation;

            return [$alterStatement->__toString()];
        }

        return [];
    }

    /**
     * @return array{drop: string[], add: string[]}
     */
    public function getIndexesDiffSqls(CreateStatement $oldStatement, CreateStatement $newStatement): array
    {
        $sqls = [
            'drop' => [],
            'add'  => [],
        ];
        $oldIndexes = [];
        // 删除字段
        foreach ($oldStatement->fields as $fieldOld)
        {
            if (!$fieldOld->key || !\in_array($fieldOld->key->type, self::INDEX_TYPES))
            {
                continue;
            }
            $oldIndexes[] = $fieldOld->name;
            foreach ($newStatement->fields as $field)
            {
                if (!$field->key || !\in_array($field->key->type, self::INDEX_TYPES))
                {
                    continue;
                }
                if ($fieldOld->key->name === $field->key->name)
                {
                    continue 2;
                }
            }
            $alterStatement = new AlterStatement();
            $alterStatement->options = new OptionsArray(['TABLE']);
            $alterStatement->table = $oldStatement->name;
            $alterOperation = new AlterOperation(new OptionsArray(['DROP', $fieldOld->key->type]), new Expression(null, null, $fieldOld->key->name));
            $alterStatement->altered[] = $alterOperation;
            $sqls['drop'][] = $alterStatement->__toString();
        }

        // 修改字段
        foreach ($oldStatement->fields as $fieldOld)
        {
            if (!$fieldOld->key || !\in_array($fieldOld->key->type, self::INDEX_TYPES))
            {
                continue;
            }
            foreach ($newStatement->fields as $field)
            {
                if (!$field->key || !\in_array($field->key->type, self::INDEX_TYPES))
                {
                    continue;
                }
                if ($fieldOld->key->name === $field->key->name)
                {
                    if ($field->__toString() !== $fieldOld->__toString())
                    {
                        $alterStatement = new AlterStatement();
                        $alterStatement->options = new OptionsArray(['TABLE']);
                        $alterStatement->table = $oldStatement->name;
                        $alterOperation = new AlterOperation(new OptionsArray(['DROP', $fieldOld->key->type]), new Expression(null, null, $fieldOld->key->name));
                        $alterStatement->altered[] = $alterOperation;
                        $sqls['drop'][] = $alterStatement->__toString();

                        $alterStatement = new AlterStatement();
                        $alterStatement->options = new OptionsArray(['TABLE']);
                        $alterStatement->table = $oldStatement->name;
                        $alterOperation = new AlterOperation(new OptionsArray(['ADD']), $field);
                        $alterStatement->altered[] = $alterOperation;
                        $sqls['add'][] = $alterStatement->__toString();
                    }
                    break;
                }
            }
        }

        // 新增字段
        foreach ($newStatement->fields as $field)
        {
            if (!$field->key || !\in_array($field->key->type, self::INDEX_TYPES))
            {
                continue;
            }
            foreach ($oldStatement->fields as $fieldOld)
            {
                if (!$fieldOld->key || !\in_array($fieldOld->key->type, self::INDEX_TYPES))
                {
                    continue;
                }
                if ($fieldOld->key->name === $field->key->name)
                {
                    continue 2;
                }
            }

            $alterStatement = new AlterStatement();
            $alterStatement->options = new OptionsArray(['TABLE']);
            $alterStatement->table = $oldStatement->name;
            $alterOperation = new AlterOperation(new OptionsArray(['ADD']), $field);
            $alterStatement->altered[] = $alterOperation;
            $sqls['add'][] = $alterStatement->__toString();
        }

        return $sqls;
    }

    public function getFieldsDiffSqls(CreateStatement $oldStatement, CreateStatement $newStatement): array
    {
        $sqls = [];
        $oldFields = [];
        // 删除字段
        foreach ($oldStatement->fields as $fieldOld)
        {
            if (!$fieldOld->type)
            {
                continue;
            }
            $oldFields[] = $fieldOld->name;
            foreach ($newStatement->fields as $field)
            {
                if (!$field->type)
                {
                    continue;
                }
                if ($fieldOld->name === $field->name)
                {
                    continue 2;
                }
            }
            $alterStatement = new AlterStatement();
            $alterStatement->options = new OptionsArray(['TABLE']);
            $alterStatement->table = $oldStatement->name;
            $alterOperation = new AlterOperation(new OptionsArray(['DROP', 'COLUMN']), new Expression(null, null, $fieldOld->name));
            $alterStatement->altered[] = $alterOperation;
            $sqls[] = $alterStatement->__toString();
        }

        // 修改字段
        foreach ($oldStatement->fields as $indexOld => $fieldOld)
        {
            if (!$fieldOld->type)
            {
                continue;
            }
            foreach ($newStatement->fields as $index => $field)
            {
                if (!$field->type)
                {
                    continue;
                }
                if ($fieldOld->name === $field->name)
                {
                    if ($field->__toString() !== $fieldOld->__toString())
                    {
                        // ---兼容处理开始---
                        // MySQL 8.0 对 INT 类型的长度失效兼容
                        $parametersBackup = $field->type->parameters;
                        $parametersBackupOld = $fieldOld->type->parameters;
                        if (\in_array($field->type->name, self::INT_TYPES) && \in_array($fieldOld->type->name, self::INT_TYPES))
                        {
                            $field->type->parameters = $fieldOld->type->parameters = [];
                        }
                        // MySQL 8.0 对 CHARACTER SET 的兼容
                        $optionsBackup = $field->type->options->options;
                        $optionsBackupOld = $fieldOld->type->options->options;
                        if ($field->type->options->has('COLLATE') === $fieldOld->type->options->has('COLLATE') && (!$field->type->options->has('CHARACTER SET') || !$fieldOld->type->options->has('CHARACTER SET')))
                        {
                            $field->type->options->remove('CHARACTER SET');
                            $fieldOld->type->options->remove('CHARACTER SET');
                        }
                        $equals = $field->__toString() === $fieldOld->__toString();
                        $field->type->parameters = $parametersBackup;
                        $fieldOld->type->parameters = $parametersBackupOld;
                        $field->type->options = new OptionsArray($optionsBackup);
                        $fieldOld->type->options->options = new OptionsArray($optionsBackupOld);
                        if ($equals)
                        {
                            break;
                        }
                        // ---兼容处理结束---
                        $alterStatement = new AlterStatement();
                        $alterStatement->options = new OptionsArray(['TABLE']);
                        $alterStatement->table = $oldStatement->name;
                        $alterOperation = new AlterOperation(new OptionsArray(['MODIFY', 'COLUMN']), $field);
                        if ($indexOld !== $index)
                        {
                            if (0 === $index)
                            {
                                $alterOperation->unknown[] = new Token('FIRST');
                            }
                            else
                            {
                                for ($i = $index - 1; $i >= 0; --$i)
                                {
                                    if (\in_array($newStatement->fields[$i]->name, $oldFields))
                                    {
                                        $alterOperation->unknown[] = new Token('AFTER');
                                        $alterOperation->unknown[] = new Token(' ');
                                        $alterOperation->unknown[] = new Token((new Expression(null, null, $newStatement->fields[$i]->name))->__toString());
                                        break;
                                    }
                                }
                            }
                        }
                        $alterStatement->altered[] = $alterOperation;
                        $sqls[] = $alterStatement->__toString();
                    }
                    break;
                }
            }
        }

        // 新增字段
        foreach ($newStatement->fields as $index => $field)
        {
            if (!$field->type)
            {
                continue;
            }
            foreach ($oldStatement->fields as $fieldOld)
            {
                if (!$fieldOld->type)
                {
                    continue;
                }
                if ($fieldOld->name === $field->name)
                {
                    continue 2;
                }
            }

            $alterStatement = new AlterStatement();
            $alterStatement->options = new OptionsArray(['TABLE']);
            $alterStatement->table = $oldStatement->name;
            $alterOperation = new AlterOperation(new OptionsArray(['ADD', 'COLUMN']), $field);
            if (0 === $index)
            {
                $alterOperation->unknown[] = new Token('FIRST');
            }
            else
            {
                $alterOperation->unknown[] = new Token('AFTER');
                $alterOperation->unknown[] = new Token(' ');
                $alterOperation->unknown[] = new Token((new Expression(null, null, $newStatement->fields[$index - 1]->name))->__toString());
            }
            $alterStatement->altered[] = $alterOperation;
            $sqls[] = $alterStatement->__toString();
        }

        return $sqls;
    }

    /**
     * @return array{drop: string[], add: string[]}
     */
    public function getConstraintDiffSqls(CreateStatement $oldStatement, CreateStatement $newStatement): array
    {
        $sqls = [
            'drop' => [],
            'add'  => [],
        ];
        $oldFields = [];
        // 删除字段
        foreach ($oldStatement->fields as $fieldOld)
        {
            if (!$fieldOld->key || self::FOREIGN_KEY_TYPE !== $fieldOld->key->type)
            {
                continue;
            }
            $oldFields[] = $fieldOld->name;
            foreach ($newStatement->fields as $field)
            {
                if (!$field->key || self::FOREIGN_KEY_TYPE !== $field->key->type)
                {
                    continue;
                }
                if ($fieldOld->name === $field->name)
                {
                    continue 2;
                }
            }
            $alterStatement = new AlterStatement();
            $alterStatement->options = new OptionsArray(['TABLE']);
            $alterStatement->table = $oldStatement->name;
            $alterOperation = new AlterOperation(new OptionsArray(['DROP', self::FOREIGN_KEY_TYPE]), new Expression(null, null, $fieldOld->name));
            $alterStatement->altered[] = $alterOperation;
            $sqls['drop'][] = $alterStatement->__toString();
        }

        // 修改字段
        foreach ($oldStatement->fields as $fieldOld)
        {
            if (!$fieldOld->key || self::FOREIGN_KEY_TYPE !== $fieldOld->key->type)
            {
                continue;
            }
            foreach ($newStatement->fields as $field)
            {
                if (!$field->key || self::FOREIGN_KEY_TYPE !== $field->key->type)
                {
                    continue;
                }
                if ($fieldOld->name === $field->name)
                {
                    if ($field->__toString() !== $fieldOld->__toString())
                    {
                        $alterStatement = new AlterStatement();
                        $alterStatement->options = new OptionsArray(['TABLE']);
                        $alterStatement->table = $oldStatement->name;
                        $alterOperation = new AlterOperation(new OptionsArray(['DROP', self::FOREIGN_KEY_TYPE]), new Expression(null, null, $fieldOld->name));
                        $alterStatement->altered[] = $alterOperation;
                        $sqls['drop'][] = $alterStatement->__toString();

                        $alterStatement = new AlterStatement();
                        $alterStatement->options = new OptionsArray(['TABLE']);
                        $alterStatement->table = $oldStatement->name;
                        $alterOperation = new AlterOperation(new OptionsArray(['ADD']), $field);
                        $alterStatement->altered[] = $alterOperation;
                        $sqls['add'][] = $alterStatement->__toString();
                    }
                    break;
                }
            }
        }

        // 新增字段
        foreach ($newStatement->fields as $field)
        {
            if (!$field->key || self::FOREIGN_KEY_TYPE !== $field->key->type)
            {
                continue;
            }
            foreach ($oldStatement->fields as $fieldOld)
            {
                if (!$fieldOld->key || self::FOREIGN_KEY_TYPE !== $fieldOld->key->type)
                {
                    continue;
                }
                if ($fieldOld->name === $field->name)
                {
                    continue 2;
                }
            }

            $alterStatement = new AlterStatement();
            $alterStatement->options = new OptionsArray(['TABLE']);
            $alterStatement->table = $oldStatement->name;
            $alterOperation = new AlterOperation(new OptionsArray(['ADD']), $field);
            $alterStatement->altered[] = $alterOperation;
            $sqls['add'][] = $alterStatement->__toString();
        }

        return $sqls;
    }

    public function getPartitionDiffSqls(CreateStatement $oldStatement, CreateStatement $newStatement): array
    {
        if ($oldStatement->partitionBy !== $newStatement->partitionBy || $oldStatement->partitionsNum !== $newStatement->partitionsNum || $oldStatement->subpartitionBy !== $newStatement->subpartitionBy || $oldStatement->subpartitionsNum !== $newStatement->subpartitionsNum || (($oldStatement->partitions ? PartitionDefinition::build($oldStatement->partitions) : '') !== ($newStatement->partitions ? PartitionDefinition::build($newStatement->partitions) : '')))
        {
            $sql = '';

            if (!empty($newStatement->partitionBy))
            {
                $sql .= "\nPARTITION BY " . $newStatement->partitionBy;
            }

            if (!empty($newStatement->partitionsNum))
            {
                $sql .= "\nPARTITIONS " . $newStatement->partitionsNum;
            }

            if (!empty($newStatement->subpartitionBy))
            {
                $sql .= "\nSUBPARTITION BY " . $newStatement->subpartitionBy;
            }

            if (!empty($newStatement->subpartitionsNum))
            {
                $sql .= "\nSUBPARTITIONS " . $newStatement->subpartitionsNum;
            }

            if (!empty($newStatement->partitions))
            {
                $sql .= "\n" . PartitionDefinition::build($newStatement->partitions);
            }

            if ($sql)
            {
                $alterStatement = new AlterStatement();
                $alterStatement->options = new OptionsArray(['TABLE']);
                $alterStatement->table = $oldStatement->name;
                $alterOperation = new AlterOperation(new OptionsArray([$sql]));
                $alterStatement->altered[] = $alterOperation;

                return [$alterStatement->__toString()];
            }
        }

        return [];
    }
}
