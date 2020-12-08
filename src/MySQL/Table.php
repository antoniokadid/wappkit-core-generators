<?php

namespace AntonioKadid\WAPPKitCore\Generators\MySQL;

use AntonioKadid\WAPPKitCore\Data\DatabaseConnectionInterface;
use AntonioKadid\WAPPKitCore\Data\Exceptions\DatabaseException;
use AntonioKadid\WAPPKitCore\Extensibility\Filter;

class Table
{
    /** @var Column[] */
    private $columns;
    /** @var DatabaseConnectionInterface */
    private $connection;
    /** @var array */
    private $foreignKeys;
    /** @var string */
    private $name;
    /** @var array */
    private $nonUniqueKeys;
    /** @var string */
    private $schema;
    /** @var array */
    private $uniqueKeys;

    /**
     * @param array $tableDefinition
     */
    public function __construct(DatabaseConnectionInterface $connection, array $tableDefinition)
    {
        $this->connection = $connection;

        $this->name   = $tableDefinition['TABLE_NAME'];
        $this->schema = $tableDefinition['TABLE_SCHEMA'];
    }

    /**
     * @param DatabaseConnectionInterface $connection
     * @param string                      $schema
     *
     * @throws DatabaseException
     *
     * @return Table[]
     */
    public static function all(DatabaseConnectionInterface $connection, string $schema): array
    {
        $sql = 'SELECT * 
                FROM `information_schema`.`tables` 
                WHERE `table_schema` = ? AND 
                      `table_type` = ?';

        return array_map(
            function (array $tableDefinition) use ($connection): Table {
                return new Table($connection, $tableDefinition);
            },
            $connection->query($sql, [$schema, 'BASE TABLE'])
        );
    }

    /** @var string */
    public $className;

    /**
     * @return Column[]
     */
    public function getColumns(): array
    {
        if (!is_array($this->columns)) {
            $this->columns = Column::fromTable($this->connection, $this);
        }

        return $this->columns;
    }

    /**
     * @throws DatabaseException
     *
     * @return array
     */
    public function getForeignKeys(): array
    {
        if (!is_array($this->foreignKeys)) {
            $this->foreignKeys = $this->loadForeignKeys($this->connection);
        }

        return $this->foreignKeys;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @throws DatabaseException
     *
     * @return array
     */
    public function getNonUniqueKeys(): array
    {
        if (!is_array($this->nonUniqueKeys)) {
            $this->nonUniqueKeys = $this->loadNonUniqueKeys($this->connection);
        }

        return $this->nonUniqueKeys;
    }

    /**
     * @return Column[]
     */
    public function getPrimaryKeys(): array
    {
        $uniqueKeys = $this->getUniqueKeys();
        return (!empty($uniqueKeys) && isset($uniqueKeys['PRIMARY'])) ? $uniqueKeys['PRIMARY'] : [];
    }

    /**
     * @return string
     */
    public function getSchema(): string
    {
        return $this->schema;
    }

    /**
     * @throws DatabaseException
     *
     * @return array
     */
    public function getUniqueKeys(): array
    {
        if (!is_array($this->uniqueKeys)) {
            $this->uniqueKeys = $this->loadUniqueKeys($this->connection);
        }

        return $this->uniqueKeys;
    }

    /**
     * @param DatabaseConnectionInterface $connection
     *
     * @throws DatabaseException
     *
     * @return array
     */
    private function loadForeignKeys(DatabaseConnectionInterface $connection): array
    {
        $sql = 'SELECT DISTINCT CONSTRAINT_NAME,
                                COLUMN_NAME
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = ? AND 
                      TABLE_NAME = ? AND
                      REFERENCED_TABLE_SCHEMA IS NOT NULL AND
                      REFERENCED_TABLE_NAME IS NOT NULL AND 
                      REFERENCED_COLUMN_NAME IS NOT NULL';

        $records = $connection->query($sql, [$this->getSchema(), $this->getName()]);

        $result = [];
        foreach ($records as $record) {
            $constraintName = $record['CONSTRAINT_NAME'];
            $columnName     = $record['COLUMN_NAME'];

            $columns = array_filter(
                $this->getColumns(),
                function (Column $column) use ($columnName) {
                    return $column->getName() == $columnName;
                }
            );

            $column = array_shift($columns);

            if ($column === null) {
                continue;
            }

            if (!isset($result[$constraintName])) {
                $result[$constraintName] = [];
            }

            array_push($result[$constraintName], $column);
        }

        return $result;
    }

    private function loadKeys(DatabaseConnectionInterface $connection, bool $unique): array
    {
        $sql = 'SHOW INDEX 
                FROM `%s`.`%s`
                WHERE  `Non_unique` = ?';

        $sql = sprintf($sql, $this->schema, $this->name);

        $records = $connection->query($sql, [$unique ? 0 : 1]);

        $result = [];
        foreach ($records as $record) {
            $indexName  = $record['Key_name'];
            $columnName = $record['Column_name'];

            if (!isset($result[$indexName])) {
                $result[$indexName] = [];
            }

            $columns = array_filter(
                $this->getColumns(),
                function (Column $column) use ($columnName) {
                    return $column->getName() == $columnName;
                }
            );

            $column = array_shift($columns);

            if ($column === null) {
                continue;
            }

            array_push($result[$indexName], $column);
        }

        return $result;
    }

    private function loadNonUniqueKeys(DatabaseConnectionInterface $connection): array
    {
        return $this->loadKeys($connection, false);
    }

    private function loadUniqueKeys(DatabaseConnectionInterface $connection): array
    {
        return $this->loadKeys($connection, true);
    }
}
