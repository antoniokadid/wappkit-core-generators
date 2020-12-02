<?php

namespace AntonioKadid\WAPPKitCore\Generators\MySQL\ORM\Generators;

use AntonioKadid\WAPPKitCore\Generators\MySQL\Table;
use AntonioKadid\WAPPKitCore\Generators\MySQL\Column;
use LogicException;
use PhpParser\Builder\Class_;
use PhpParser\Builder\Namespace_;
use PhpParser\BuilderFactory;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\Greater;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Return_;

/**
 * Class DeleteMethodGenerator.
 *
 * @package AntonioKadid\WAPPKitCore\Generators\MySQL\ORM\Generators
 */
class DeleteMethodGenerator extends ORMGenerator
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
            ->method('delete')
            ->makePublic()
            ->setReturnType('bool');

        if ($this->commentsEnabled()) {
            $commentGen = new CommentGenerator();
            $commentGen->setReturnType('bool');

            $method->setDocComment($commentGen->generate());
        }

        // Set SQL
        $sqlExpression = new Assign(
            new Variable('sql'),
            new String_($this->generateSql())
        );

        $method->addStmt($sqlExpression);

        // Return
        $returnStmt = new Return_(
            new Greater(
                new MethodCall(new PropertyFetch(new Variable('this'), 'connection'), 'execute', [
                    new Variable('sql'),
                    new Array_(
                        array_map(
                            function (Column $column) {
                                return new ArrayItem(new PropertyFetch(new Variable('this'), $column->getPropertyName()));
                            },
                            $this->table->getPrimaryKeys()
                        ),
                        [
                            'kind' => Array_::KIND_SHORT
                        ]
                    )
                ]),
                new LNumber(0)
            )
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
            "DELETE
                FROM `%s`
                WHERE %s",
            $this->table->getName(),
            implode(
                ' AND ',
                array_map(function (Column $column) {
                    return sprintf('`%s` = ?', $column->getName());
                }, $this->table->getPrimaryKeys())
            )
        );
    }
}
