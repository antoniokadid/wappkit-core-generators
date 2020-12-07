<?php

namespace AntonioKadid\WAPPKitCore\Generators\MySQL\ActiveRecord;

use AntonioKadid\WAPPKitCore\Arrays\Offset;
use AntonioKadid\WAPPKitCore\Data\DatabaseConnectionInterface;
use AntonioKadid\WAPPKitCore\Extensibility\Filter;
use AntonioKadid\WAPPKitCore\Generators\MySQL\ActiveRecord\Generators\AddMethodGenerator;
use AntonioKadid\WAPPKitCore\Generators\MySQL\ActiveRecord\Generators\AllMethodGenerator;
use AntonioKadid\WAPPKitCore\Generators\MySQL\ActiveRecord\Generators\ConstructorGenerator;
use AntonioKadid\WAPPKitCore\Generators\MySQL\ActiveRecord\Generators\DeleteMethodGenerator;
use AntonioKadid\WAPPKitCore\Generators\MySQL\ActiveRecord\Generators\FromRecordMethodGenerator;
use AntonioKadid\WAPPKitCore\Generators\MySQL\ActiveRecord\Generators\GetByForeignKeyMethodGenerator;
use AntonioKadid\WAPPKitCore\Generators\MySQL\ActiveRecord\Generators\GetByPrimaryKeyMethodGenerator;
use AntonioKadid\WAPPKitCore\Generators\MySQL\ActiveRecord\Generators\JsonSerializeMethodGenerator;
use AntonioKadid\WAPPKitCore\Generators\MySQL\ActiveRecord\Generators\PropertiesGenerator;
use AntonioKadid\WAPPKitCore\Generators\MySQL\ActiveRecord\Generators\UpdateMethodGenerator;
use AntonioKadid\WAPPKitCore\Generators\MySQL\Table;
use AntonioKadid\WAPPKitCore\IO\Path;
use AntonioKadid\WAPPKitCore\Text\Sanitizers\VariableNameSanitizer;
use AntonioKadid\WAPPKitCore\Text\TextCase;
use PhpParser\BuilderFactory;
use PhpParser\PrettyPrinter;

/**
 * Class Generator.
 *
 * @package AntonioKadid\WAPPKitCore\Generators\MySQL\ActiveRecord
 */
class Generator
{
    /** @var string */
    private $classFilter;
    /** @var DatabaseConnectionInterface */
    private $connection;

    /** @var string */
    private $namespaceFilter;
    /** @var array */
    private $options = ['comments' => true];
    /** @var string */
    private $propertyFilter;
    /** @var VariableNameSanitizer */
    private $sanitizer;
    /** @var string */
    private $schema;

    /**
     * @param DatabaseConnectionInterface $connection
     * @param string                      $schema
     */
    public function __construct(DatabaseConnectionInterface $connection, string $schema)
    {
        $this->connection = $connection;
        $this->schema     = $schema;
        $this->sanitizer  = new VariableNameSanitizer();
    }

    public function generate(array $config): void
    {
        $this->addFilters($config);

        $this->options['comments'] = Offset::get($config, 'comments')->getBool(true);

        $defaultTableConfiguration = [
            'namespace' => Offset::get($config, 'namespace')->getTrimString(Filter::apply('namespace-name', $this->schema)),
            'outDir'    => Offset::get($config, 'outDir')->getTrimString(getcwd() . '/out'),
            'add'       => true,
            'update'    => true,
            'delete'    => true
        ];

        $tables  = Table::all($this->connection, $this->schema);
        foreach ($tables as $table) {
            $tableConfig = array_merge(
                $defaultTableConfiguration,
                Offset::get($config, 'tables/' . $table->getName())->getArray($defaultTableConfiguration)
            );

            $namespace    = $tableConfig['namespace'];
            $outDir       = $tableConfig['outDir'];
            $allowAdd     = $tableConfig['add'] === true;
            $allowUpdate  = $tableConfig['update'] === true;
            $allowDelete  = $tableConfig['delete'] === true;

            if (!file_exists($outDir) && !mkdir($outDir, 0777, true)) {
                echo sprintf("Cannot process table %s. Failed to write to output path.\n", $table->getName());
                continue;
            }

            $factory = new BuilderFactory();

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

            if ($allowAdd) {
                $method = new AddMethodGenerator($namespace, $class, $table, $this->options);
                $method->generate($factory);
            }

            if ($allowDelete) {
                $method = new DeleteMethodGenerator($namespace, $class, $table, $this->options);
                $method->generate($factory);
            }

            if ($allowUpdate) {
                $method = new UpdateMethodGenerator($namespace, $class, $table, $this->options);
                $method->generate($factory);
            }

            $method = new FromRecordMethodGenerator($namespace, $class, $table, $this->options);
            $method->generate($factory);

            $method = new JsonSerializeMethodGenerator($namespace, $class, $table, $this->options);
            $method->generate($factory);

            $namespace->addStmt($class);

            $prettyPrinter = new PrettyPrinter\Standard();
            $code          = $prettyPrinter->prettyPrintFile([$namespace->getNode()]);

            file_put_contents(Path::combine($outDir, $table->getClassName() . '.php'), $code);
        }

        $this->clearFilters();
    }

    private function addFilters(array $config): void
    {
        $this->namespaceFilter = Filter::add(
            'namespace-name',
            function (string $input) use ($config): string {
                return $this->convertCase(
                    Offset::get($config, 'names/database')
                        ->getTrimString(''),
                    $input
                );
            }
        );

        $this->classFilter = Filter::add(
            'class-name',
            function (string $input) use ($config): string {
                return $this->convertCase(
                    Offset::get($config, 'names/table')
                        ->getTrimString(''),
                    $input
                );
            }
        );

        $this->propertyFilter = Filter::add(
            'property-name',
            function (string $input) use ($config): string {
                return $this->convertCase(
                    Offset::get($config, 'names/column')
                        ->getTrimString(''),
                    $input
                );
            }
        );
    }

    private function clearFilters(): void
    {
        Filter::remove($this->namespaceFilter);
        Filter::remove($this->classFilter);
        Filter::remove($this->propertyFilter);
    }

    /**
     * @param string $target
     * @param string $input
     *
     * @return string
     */
    private function convertCase(string $target, string $input): string
    {
        $input = $this->sanitizer->setText($input)->sanitize();

        if (empty($target)) {
            return $input;
        }

        $currentTextCase = TextCase::identify($input);

        switch ($currentTextCase) {
            case TextCase::CAMEL_SNAKE:
            case TextCase::KEBAB:
            case TextCase::LOWER_CAMEL:
            case TextCase::SCREAMING_KEBAB:
            case TextCase::SCREAMING_SNAKE:
            case TextCase::SNAKE:
            case TextCase::TRAIN:
            case TextCase::UPPER_CAMEL:
            case TextCase::FLAT:
            case TextCase::UPPER_FLAT:
                $textCase = new TextCase($input);
                switch ($target) {
                    case 'snake_case':
                        return $textCase->toSnakeCase();
                    case 'SCREAMING_SNAKE_CASE':
                        return $textCase->toScreamingSnakeCase();
                    case 'Camel_Snake_Case':
                        return $textCase->toCamelSnakeCase();
                    case 'camelCase':
                        return $textCase->toCamelCase();
                    case 'UpperCamelCase':
                        return $textCase->toUpperCamelCase();
                    default:
                        return $input;
                }
                break;
            default:
                return $input;
        }
    }
}
