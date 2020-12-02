<?php

namespace AntonioKadid\WAPPKitCore\Generators\MySQL\ORM\Generators;

use AntonioKadid\WAPPKitCore\Generators\MySQL\Column;
use AntonioKadid\WAPPKitCore\Generators\MySQL\Table;
use LogicException;
use PhpParser\Builder\Class_;
use PhpParser\Builder\Namespace_;
use PhpParser\BuilderFactory;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Return_;

/**
 * Class AllMethodGenerator.
 *
 * @package AntonioKadid\WAPPKitCore\Generators\MySQL\ORM\Generators
 */
class AllMethodGenerator extends ORMGenerator
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
         $method = $factory
             ->method('all')
             ->makePublic()
             ->makeStatic()
             ->addParams([
                 $factory->param('connection')->setType('DatabaseConnectionInterface'),
             ])
             ->setReturnType('array');

        if ($this->commentsEnabled()) {
            $commentGen = new CommentGenerator();
            $commentGen->addParameter('DatabaseConnectionInterface', 'connection');
            $commentGen->setReturnType(sprintf('%s[]', $this->table->getClassName()));

            $method->setDocComment($commentGen->generate());
        }

        // Set SQL
        $sqlExpression = new Assign(
            new Variable('sql'),
            new String_($this->generateSql())
        );

        $method->addStmt($sqlExpression);

        // Return array map
        $returnExpression = new Return_(
            new FuncCall(new Name('array_map'), [
                new Closure([
                    'params' => [
                        new Param(new Variable('record'), null, 'array')
                    ],
                    'returnType' => new Name($this->table->getClassName()),
                    'uses'       => [
                        new Variable('connection')
                    ],
                    'stmts' => [
                        new Return_(
                            new StaticCall(new Name('self'), 'fromRecord', [
                                new Variable('connection'),
                                new Variable('record')
                            ])
                        )
                    ]
                ]),
                new MethodCall(new Variable('connection'), 'query', [
                    new Variable('sql')
                ])
            ])
        );

        $method->addStmt($returnExpression);

        $this->class->addStmt($method);
    }

    /**
     * @return string
     */
    private function generateSql(): string
    {
        return sprintf(
            'SELECT %s
                FROM `%s`',
            implode(
                ', ',
                array_map(
                    function (Column $column) {
                        return sprintf('`%s`', $column->getName());
                    },
                    $this->table->getColumns()
                )
            ),
            $this->table->getName()
        );
    }
}
