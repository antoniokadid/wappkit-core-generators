<?php

namespace AntonioKadid\WAPPKitCore\Generators\MySQL\ORM;

use AntonioKadid\WAPPKitCore\DAL\DatabaseConnectionInterface;
use AntonioKadid\WAPPKitCore\Extensibility\Filter;
use AntonioKadid\WAPPKitCore\Generators\MySQL\ORM\Generators\AddMethodGenerator;
use AntonioKadid\WAPPKitCore\Generators\MySQL\ORM\Generators\AllMethodGenerator;
use AntonioKadid\WAPPKitCore\Generators\MySQL\ORM\Generators\ConstructorGenerator;
use AntonioKadid\WAPPKitCore\Generators\MySQL\ORM\Generators\DeleteMethodGenerator;
use AntonioKadid\WAPPKitCore\Generators\MySQL\ORM\Generators\FromRecordMethodGenerator;
use AntonioKadid\WAPPKitCore\Generators\MySQL\ORM\Generators\GetByForeignKeyMethodGenerator;
use AntonioKadid\WAPPKitCore\Generators\MySQL\ORM\Generators\GetByPrimaryKeyMethodGenerator;
use AntonioKadid\WAPPKitCore\Generators\MySQL\ORM\Generators\JsonSerializeMethodGenerator;
use AntonioKadid\WAPPKitCore\Generators\MySQL\ORM\Generators\PropertiesGenerator;
use AntonioKadid\WAPPKitCore\Generators\MySQL\ORM\Generators\UpdateMethodGenerator;
use AntonioKadid\WAPPKitCore\Generators\MySQL\Table;
use PhpParser\BuilderFactory;
use PhpParser\PrettyPrinter;

/**
 * Class Generator.
 *
 * @package AntonioKadid\WAPPKitCore\Generators\MySQL\ORM
 */
class Generator
{
    /** @var DatabaseConnectionInterface */
    private $connection;
    /** @var array */
    private $options = ['comments' => true];
    /** @var string */
    private $schema;
    /** @var array */
    private $tableMap = [];

    /**
     * @param DatabaseConnectionInterface $connection
     * @param string                      $schema
     */
    public function __construct(DatabaseConnectionInterface $connection, string $schema)
    {
        $this->connection = $connection;
        $this->schema     = $schema;
    }

    /**
     * @param string   $namespace
     * @param string   $outputDirectory
     * @param string[] $tables
     *
     * @return Generator
     */
    public function configureTables(string $namespace, string $outputDirectory, string ...$tables): Generator
    {
        foreach ($tables as $table) {
            $this->tableMap[$table] = ['namespace' => $namespace, 'destination' => $outputDirectory];
        }

        return $this;
    }

    public function generate(): void
    {
        $tables  = Table::all($this->connection, $this->schema);

        foreach ($tables as $table) {
            $factory = new BuilderFactory();

            $namespace   = Filter::apply('class-name', $this->schema);
            $destination = __DIR__ . '/../out';

            if (array_key_exists($table->getName(), $this->tableMap)) {
                $namespace   = $this->tableMap[$table->getName()]['namespace'];
                $destination = $this->tableMap[$table->getName()]['destination'];
            }

            $namespace = $factory->namespace($namespace);
            $namespace->addStmt($factory->use(DatabaseConnectionInterface::class));

            $class = $factory->class($table->getClassName());

            $constructor = new ConstructorGenerator($namespace, $class, $table, $this->options);
            $constructor->generate($factory);

            $properties = new PropertiesGenerator($namespace, $class, $table, $this->options);
            $properties->generate($factory);

            $method = new AllMethodGenerator($namespace, $class, $table, $this->options);
            $method->generate($factory);

            $method = new GetByPrimaryKeyMethodGenerator($namespace, $class, $table, $this->options);
            $method->generate($factory);

            $method = new GetByForeignKeyMethodGenerator($namespace, $class, $table, $this->options);
            $method->generate($factory);

            $method = new AddMethodGenerator($namespace, $class, $table, $this->options);
            $method->generate($factory);

            $method = new DeleteMethodGenerator($namespace, $class, $table, $this->options);
            $method->generate($factory);

            $method = new UpdateMethodGenerator($namespace, $class, $table, $this->options);
            $method->generate($factory);

            $method = new FromRecordMethodGenerator($namespace, $class, $table, $this->options);
            $method->generate($factory);

            $method = new JsonSerializeMethodGenerator($namespace, $class, $table, $this->options);
            $method->generate($factory);

            $namespace->addStmt($class);

            $prettyPrinter = new PrettyPrinter\Standard();
            $code          = $prettyPrinter->prettyPrintFile([$namespace->getNode()]);

            file_put_contents($destination . DIRECTORY_SEPARATOR . $table->getClassName() . '.php', $code);
        }
    }

    /**
     * @param bool $value
     *
     * @return Generator
     */
    public function setCommentsEnabled(bool $value): Generator
    {
        $this->options['comments'] = $value;

        return $this;
    }
}
