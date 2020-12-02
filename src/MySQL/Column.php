<?php

namespace AntonioKadid\WAPPKitCore\Generators\MySQL;

use AntonioKadid\WAPPKitCore\DAL\DatabaseConnectionInterface;
use AntonioKadid\WAPPKitCore\DAL\Exceptions\DatabaseException;
use AntonioKadid\WAPPKitCore\Extensibility\Filter;
use DateTime;

class Column
{
    /** @var string */
    private $columnType;
    /** @var string */
    private $comment;
    /** @var string */
    private $dataType;
    /** @var string */
    private $name;
    /** @var bool */
    private $nullable = false;
    /** @var bool */
    private $primary = false;
    /** @var Table */
    private $table;

    /**
     * Column constructor.
     *
     * @param array $columnDefinition
     */
    public function __construct(Table $table, array $columnDefinition)
    {
        $this->table = $table;

        $this->name         = $columnDefinition['COLUMN_NAME'];
        $this->databaseName = $columnDefinition['TABLE_SCHEMA'];
        $this->tableName    = $columnDefinition['TABLE_NAME'];
        $this->dataType     = $columnDefinition['DATA_TYPE'];
        $this->columnType   = $columnDefinition['COLUMN_TYPE']; // Includes additional type info (ex. length)
        $this->nullable     = $columnDefinition['IS_NULLABLE'] === 'YES';
        $this->comment      = $columnDefinition['COLUMN_COMMENT'];
        $this->primary      = strpos($columnDefinition['COLUMN_KEY'], 'PRI') !== false;
    }

    /**
     * @param DatabaseConnectionInterface $connection
     * @param Table                       $table
     *
     * @throws DatabaseException
     *
     * @return Column[]
     */
    public static function fromTable(DatabaseConnectionInterface $connection, Table $table): array
    {
        $sql = 'SELECT *
                FROM `information_schema`.`columns`
                WHERE `table_schema` = ? AND 
                      `table_name` = ?';

        return
            array_map(
                function (array $columnDefinition) use ($table): Column {
                    return new Column($table, $columnDefinition);
                },
                $connection->query($sql, [
                    $table->getSchema(),
                    $table->getName()
                ])
            );
    }

    /**
     * @param DatabaseConnectionInterface $connection
     * @param string                      $schema
     * @param string                      $table
     * @param string                      $column
     *
     * @throws DatabaseException
     *
     * @return null|Column
     */
    public static function get(DatabaseConnectionInterface $connection, string $schema, string $table, string $column)
    {
        $sql = 'SELECT *
                FROM `information_schema`.`columns`
                WHERE `table_schema` = ? AND 
                      `table_name` = ? AND
                      `column_name` = ?';

        $records = $connection->query($sql, [$schema, $table, $column]);

        return empty($records) ? null : new Column(Table::get($connection, $schema, $table), array_shift($records));
    }

    /**
     * @return string
     */
    public function getColumnType(): string
    {
        return $this->columnType;
    }

    /**
     * @return string
     */
    public function getComment(): string
    {
        return $this->comment;
    }

    /**
     * @return string
     */
    public function getDataType(): string
    {
        return $this->dataType;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPhpType(): string
    {
        $dataType   = strtoupper($this->dataType);
        $columnType = strtoupper($this->columnType);

        switch ($dataType) {
            case 'FLOAT':
            case 'DOUBLE':
            case 'DECIMAL':
                return 'float';
            case 'TINYINT':
                return $columnType === 'TINYINT(1)' ? 'bool' : 'int';
            case 'SMALLINT':
            case 'MEDIUMINT':
            case 'INT':
            case 'BIGINT':
                return 'int';
            case 'DATE':
            case 'DATETIME':
                return DateTime::class;
            case 'CHAR':
            case 'VARCHAR':
            case 'TINYTEXT':
            case 'TEXT':
            case 'MEDIUMTEXT':
            case 'LONGTEXT':
            default:
                return 'string';
        }
    }

    public function getPropertyName(): string
    {
        return Filter::apply('property-name', $this->name);
    }

    /**
     * @return bool
     */
    public function isNullable(): bool
    {
        return $this->nullable;
    }

    /**
     * @return bool
     */
    public function isPrimary(): bool
    {
        return $this->primary;
    }
}
