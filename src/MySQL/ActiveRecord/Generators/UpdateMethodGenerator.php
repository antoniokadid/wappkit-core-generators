<?php

namespace AntonioKadid\WAPPKitCore\Generators\MySQL\ActiveRecord\Generators;

use AntonioKadid\WAPPKitCore\Generators\MySQL\Column;
use AntonioKadid\WAPPKitCore\Generators\MySQL\Table;
use LogicException;
use PhpParser\Builder\Class_;
use PhpParser\Builder\Namespace_;
use PhpParser\BuilderFactory;
use PhpParser\Node\Expr;
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
 * Class UpdateMethodGenerator.
 *
 * @package AntonioKadid\WAPPKitCore\Generators\MySQL\ActiveRecord\Generators
 */
class UpdateMethodGenerator extends ORMGenerator
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
            ->method('update')
            ->makePublic()
            ->addParams([
                $factory->param('connection')->setType('DatabaseConnectionInterface')
            ])
            ->setReturnType('bool');

        if ($this->commentsEnabled()) {
            $commentGen = new CommentGenerator();
            $commentGen->addParameter('DatabaseConnectionInterface', 'connection');
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
                new MethodCall(new Variable('connection'), 'execute', [
                    new Variable('sql'),
                    new Array_(
                        array_merge(
                            array_map(
                                function (Column $column) {
                                    return $this->makeArrayItem($column);
                                },
                                $this->table->getColumns()
                            ),
                            array_map(
                                function (Column $column) {
                                    return $this->makeArrayItem($column);
                                },
                                $this->table->getPrimaryKeys()
                            )
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
            'UPDATE `%s` 
                SET %s
                WHERE %s',
            $this->table->getName(),
            implode(
                ', ',
                array_map(function (Column $column) {
                    return sprintf('`%s` = ?', $column->getName());
                }, $this->table->getColumns())
            ),
            implode(
                ' AND ',
                array_map(function (Column $column) {
                    return sprintf('`%s` = ?', $column->getName());
                }, $this->table->getPrimaryKeys())
            )
        );
    }

    private function getValueExpression(Column $column): Expr
    {
        $name = $column->getPropertyName();
        $type = $column->getPhpType();

        if ($type === \DateTime::class) {
            return new MethodCall(new PropertyFetch(new Variable('this'), $name), 'format', [
                new String_('Y-m-d H:i:s')
            ]);
        }

        return new PropertyFetch(new Variable('this'), $name);
    }

    private function makeArrayItem(Column $column): Expr
    {
        return new ArrayItem(
            $this->ternarizeProperty(
                $this->getValueExpression($column),
                $column
            )
        );
    }
}
