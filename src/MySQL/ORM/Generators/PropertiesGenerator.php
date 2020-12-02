<?php

namespace AntonioKadid\WAPPKitCore\Generators\MySQL\ORM\Generators;

use AntonioKadid\WAPPKitCore\Generators\MySQL\Column;
use AntonioKadid\WAPPKitCore\Generators\MySQL\Table;
use LogicException;
use PhpParser\Builder\Class_;
use PhpParser\Builder\Namespace_;
use PhpParser\BuilderFactory;

/**
 * Class PropertiesGenerator.
 *
 * @package AntonioKadid\WAPPKitCore\Generators\MySQL\ORM\Generators
 */
class PropertiesGenerator extends ORMGenerator
{
    /**
     * @param Namespace_ $namespace
     * @param Class_     $class
     * @param Table      $table
     * @param array      $options
     */
    public function __construct(Namespace_ $namespace, Class_ $class, Table $table, array $options = [])
    {
        parent::__construct($namespace, $class, $table, $options);
    }

    /**
     * @param BuilderFactory $factory
     *
     * @throws LogicException
     */
    public function generate(BuilderFactory $factory): void
    {
        $props = array_map(function (Column $column) use ($factory) {

            $prop = $factory->property($column->getPropertyName());

            if (!$this->commentsEnabled()) {
                return $prop;
            }

            $commentGen = new CommentGenerator();
            $commentGen->addVar($column->getPhpType(), $column->getPropertyName());

            $prop->setDocComment($commentGen->generate());

            return $prop;
        }, $this->table->getColumns());

        $this->class->addStmts($props);
    }
}
