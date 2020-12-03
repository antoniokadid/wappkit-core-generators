<?php

namespace AntonioKadid\WAPPKitCore\Generators\MySQL\ActiveRecord\Generators;

use AntonioKadid\WAPPKitCore\Generators\MySQL\Table;
use LogicException;
use PhpParser\Builder\Class_;
use PhpParser\Builder\Namespace_;
use PhpParser\BuilderFactory;
use PhpParser\Error;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Expression;

/**
 * Class ConstructorGenerator.
 *
 * @package AntonioKadid\WAPPKitCore\Generators\MySQL\ActiveRecord\Generators
 */
class ConstructorGenerator extends ORMGenerator
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
     * @throws Error
     * @throws LogicException
     */
    public function generate(BuilderFactory $factory): void
    {
        $connectionProperty = $factory
            ->property('connection')
            ->makePrivate();

        if ($this->commentsEnabled()) {
            $commentGen = new CommentGenerator();
            $commentGen->addVar('DatabaseConnectionInterface', 'connection');
            $connectionProperty->setDocComment($commentGen->generate());
        }

        $this->class->addStmt($connectionProperty);

        $constructor = $factory
            ->method('__construct')
            ->makePublic()
            ->addParam($factory->param('connection')->setType('DatabaseConnectionInterface'));

        $expression = new Expression(new Assign(new Variable('this->connection'), new Variable('connection')), []);

        $constructor->addStmt($expression);

        if ($this->commentsEnabled()) {
            $commentGen = new CommentGenerator();
            $commentGen->setDescription(sprintf('%s constructor.', $this->table->getClassName()));
            $commentGen->addParameter('DatabaseConnectionInterface', 'connection');

            $constructor->setDocComment($commentGen->generate());
        }

        $this->class->addStmt($constructor);
    }
}
