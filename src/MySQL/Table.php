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
    /** @var string */
    private $schema;

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

    /**
     * @param DatabaseConnectionInterface $connection
     * @param string                      $schema
     * @param string                      $name
     *
     * @throws DatabaseException
     *
     * @return null|Table
     */
    public static function get(DatabaseConnectionInterface $connection, string $schema, string $name): ?Table
    {
        $sql = 'SELECT * 
                FROM `information_schema`.`tables` 
                WHERE `table_schema` = ? AND 
                      `table_type` = ? AND 
                      `table_name` = ?';

        $records = $connection->query($sql, [$schema, 'BASE TABLE', $name]);

        return empty($records) ? null : new Table($connection, array_shift($records));
    }

    /**
     * @return string
     */
    public function getClassName(): string
    {
        return Filter::apply('class-name', $this->name);
    }

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
     * @return Column[]
     */
    public function getPrimaryKeys(): array
    {
        return array_filter(
            $this->getColumns(),
            function (Column $column) {
                return $column->isPrimary();
            }
        );
    }

    /**
     * @return string
     */
    public function getSchema(): string
    {
        return $this->schema;
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
        $sql = 'SELECT CONSTRAINT_NAME,
                       COLUMN_NAME,
                       REFERENCED_TABLE_NAME,
                       REFERENCED_COLUMN_NAME
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = ? AND 
                      REFERENCED_TABLE_SCHEMA = ? AND 
                      TABLE_NAME = ?';

        $records = $connection->query($sql, [$this->getSchema(), $this->getSchema(), $this->getName()]);

        $result = [];
        foreach ($records as $record) {
            $referencedTableName = $record['REFERENCED_TABLE_NAME'];
            $referencedColumnName = $record['REFERENCED_COLUMN_NAME'];

            if (!is_string($referencedTableName) || !is_string($referencedColumnName)) {
                continue;
            }

            $foreignColumn = Column::get(
                $connection,
                $this->getSchema(),
                $referencedTableName,
                $referencedColumnName
            );

            if ($foreignColumn == null) {
                continue;
            }

            $ownColumn = array_filter(
                $this->getColumns(),
                function (Column $column) use ($record) {
                    return $column->getName() == $record['COLUMN_NAME'];
                }
            );

            if ($ownColumn == null) {
                continue;
            }

            $constraintName = $record['CONSTRAINT_NAME'];
            if (!isset($result[$constraintName])) {
                $result[$constraintName] = [];
            }

            array_push($result[$constraintName], ['own' => $ownColumn, 'foreign' => $foreignColumn]);
        }

        return $result;
    }
}
