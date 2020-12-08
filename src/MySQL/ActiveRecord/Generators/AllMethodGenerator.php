<?php

namespace AntonioKadid\WAPPKitCore\Generators\MySQL\ActiveRecord\Generators;

use AntonioKadid\WAPPKitCore\Generators\MySQL\Column;
use AntonioKadid\WAPPKitCore\Generators\MySQL\Table;
use LogicException;
use PhpParser\Builder\Class_;
use PhpParser\Builder\Namespace_;
use PhpParser\BuilderFactory;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp\Concat;
use PhpParser\Node\Expr\BinaryOp\Greater;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Return_;

/**
 * Class AllMethodGenerator.
 *
 * @package AntonioKadid\WAPPKitCore\Generators\MySQL\ActiveRecord\Generators
 */
class AllMethodGenerator extends ActiveRecordSectionGenerator
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
                $factory->param('count')->setType('int')->setDefault(0),
                $factory->param('skip')->setType('int')->setDefault(0)
             ])
             ->setReturnType('array');

        if ($this->commentsEnabled()) {
            $commentGen = new CommentGenerator();
            $commentGen->addParameter('DatabaseConnectionInterface', 'connection');
            $commentGen->addParameter('int', 'count');
            $commentGen->addParameter('int', 'skip');
            $commentGen->setReturnType(sprintf('%s[]', $this->table->className));

            $method->setDocComment($commentGen->generate());
        }

        // Set SQL
        $sqlExpression = new Assign(
            new Variable('sql'),
            new String_($this->generateSql())
        );

        $method->addStmt($sqlExpression);

        // Define the SQL parameters variable
        $parametersExpression = new Assign(new Variable('params'), new Array_([], ['kind' => Array_::KIND_SHORT]));

        $method->addStmt($parametersExpression);

        // check if should append limit condition
        $countExpression = new If_(new Greater(new Variable('count'), new LNumber(0)), [
            'stmts' => [
                new Expression(new Concat(new Variable('sql'), new String_(' LIMIT ?'))),
                new Expression(new FuncCall(new Name('array_push'), [new Variable('params'), new Variable('count')]))
            ]
        ]);

        $method->addStmt($countExpression);

        // check if should append offset condition
        $countExpression = new If_(new Greater(new Variable('skip'), new LNumber(0)), [
            'stmts' => [
                new Expression(new Concat(new Variable('sql'), new String_(' OFFSET ?'))),
                new Expression(new FuncCall(new Name('array_push'), [new Variable('params'), new Variable('skip')]))
            ]
        ]);

        $method->addStmt($countExpression);

        // Return array map
        $returnExpression = new Return_(
            new FuncCall(new Name('array_map'), [
                new Closure([
                    'params' => [
                        new Param(new Variable('record'), null, 'array')
                    ],
                    'returnType' => new Name($this->table->className),
                    'stmts' => [
                        new Return_(
                            new StaticCall(new Name('self'), 'fromRecord', [
                                new Variable('record')
                            ])
                        )
                    ]
                ]),
                new MethodCall(new Variable('connection'), 'query', [
                    new Variable('sql'),
                    new Variable('params')
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
