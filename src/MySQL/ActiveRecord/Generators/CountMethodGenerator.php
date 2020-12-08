<?php

namespace AntonioKadid\WAPPKitCore\Generators\MySQL\ActiveRecord\Generators;

use AntonioKadid\WAPPKitCore\Extensibility\Filter;
use AntonioKadid\WAPPKitCore\Generators\MySQL\Column;
use AntonioKadid\WAPPKitCore\Generators\MySQL\Table;
use AntonioKadid\WAPPKitCore\Text\TextCase;
use LogicException;
use PhpParser\Builder\Class_;
use PhpParser\Builder\Namespace_;
use PhpParser\BuilderFactory;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Empty_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Return_;

/**
 * Class CountMethodGenerator.
 *
 * @package AntonioKadid\WAPPKitCore\Generators\MySQL\ActiveRecord\Generators
 */
class CountMethodGenerator extends ActiveRecordSectionGenerator
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
        if (count($this->table->getPrimaryKeys()) === 0) {
            return;
        }

        $method = $factory
            ->method('count')
            ->makePublic()
            ->makeStatic()
            ->addParams(
                array_merge(
                    [
                        $factory->param('connection')->setType('DatabaseConnectionInterface')
                    ]
                )
            )
            ->setReturnType('int');

        if ($this->commentsEnabled()) {
            $commentGen = new CommentGenerator();
            $commentGen->addParameter('DatabaseConnectionInterface', 'connection');
            $commentGen->setReturnType('int');
            $method->setDocComment($commentGen->generate());
        }

        // Set SQL
        $sqlExpression = new Assign(
            new Variable('sql'),
            new String_($this->generateSql())
        );

        $method->addStmt($sqlExpression);

        // Get records

        $expression = new Assign(
            new Variable('records'),
            new MethodCall(new Variable('connection'), 'query', [
                new Variable('sql')
            ])
        );

        $method->addStmt($expression);

        // Return

        $returnStmt = new Return_(
            new ArrayDimFetch(new ArrayDimFetch(new Variable('records'), new LNumber(0)), new String_('count'))
        );

        $method->addStmt($returnStmt);

        $this->class->addStmt($method);
    }

    /**
     * @return string
     */
    private function generateSql(): string
    {
        return sprintf(
            'SELECT COUNT(DISTINCT %s) AS `count`
                FROM `%s`',
            implode(
                ', ',
                array_map(function (Column $column) {
                    return sprintf('`%s`', $column->getName());
                }, $this->table->getPrimaryKeys())
            ),
            $this->table->getName()
        );
    }
}
