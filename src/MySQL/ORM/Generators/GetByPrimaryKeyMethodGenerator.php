<?php

namespace AntonioKadid\WAPPKitCore\Generators\MySQL\ORM\Generators;

use AntonioKadid\WAPPKitCore\Generators\MySQL\Column;
use AntonioKadid\WAPPKitCore\Generators\MySQL\Table;
use AntonioKadid\WAPPKitCore\Text\CamelCase;
use LogicException;
use PhpParser\Builder\Class_;
use PhpParser\Builder\Namespace_;
use PhpParser\BuilderFactory;
use PhpParser\Node\Expr\Array_;
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
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Return_;

/**
 * Class GetByPrimaryKeyMethodGenerator.
 *
 * @package AntonioKadid\WAPPKitCore\Generators\MySQL\ORM\Generators
 */
class GetByPrimaryKeyMethodGenerator extends ORMGenerator
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
            ->method($this->makeMethodName())
            ->makePublic()
            ->makeStatic()
            ->addParams(
                array_merge(
                    [
                        $factory->param('connection')->setType('DatabaseConnectionInterface')
                    ],
                    $this->makeMethodParameters()
                )
            )
            ->setReturnType(new NullableType($this->table->getClassName()));

        if ($this->commentsEnabled()) {
            $commentGen = new CommentGenerator();
            $commentGen->addParameter('DatabaseConnectionInterface', 'connection');
            foreach ($this->table->getPrimaryKeys() as $column) {
                $commentGen->addParameter($column->getPhpType(), $column->getPropertyName());
            }
            $commentGen->setReturnType('null|' . $this->table->getClassName());

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
                new Variable('sql'),
                new Array_(
                    array_map(
                        function (Column $column) {
                            return new ArrayItem(new Variable($column->getPropertyName()));
                        },
                        $this->table->getPrimaryKeys()
                    ),
                    [
                        'kind' => Array_::KIND_SHORT
                    ]
                )
            ])
        );

        $method->addStmt($expression);

        // Return

        $returnStmt = new Return_(
            new Ternary(
                new Empty_(new Variable('records')),
                new ConstFetch(new Name('null')),
                new StaticCall(new Name('self'), 'fromRecord', [
                    new Variable('connection'),
                    new FuncCall(new Name('array_shift'), [
                        new Variable('records')
                    ])
                ])
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
            'SELECT %s 
                FROM `%s` 
                WHERE %s',
            implode(
                ', ',
                array_map(function (Column $column) {
                    return sprintf('`%s`', $column->getName());
                }, $this->table->getColumns())
            ),
            $this->table->getName(),
            implode(
                ' AND ',
                array_map(function (Column $column) {
                    return sprintf('`%s` = ?', $column->getName());
                }, $this->table->getPrimaryKeys())
            )
        );
    }

    /**
     * @return string[]
     */
    private function getPrimaryKeyPropertyNames(): array
    {
        return array_map(function (Column $tableColumn) {
            return $tableColumn->getPropertyName();
        }, $this->table->getPrimaryKeys());
    }

    /**
     * @return string
     */
    private function makeMethodName(): string
    {
        $base = 'get by ' . implode(' and ', $this->getPrimaryKeyPropertyNames());

        $case = new CamelCase();
        $case->load($base);

        return $case->toCamelCase();
    }

    /**
     * @return Param[]
     */
    private function makeMethodParameters(): array
    {
        return array_map(function (Column $column) {
            return new Param(new Variable($column->getPropertyName()), null, $column->getPhpType());
        }, $this->table->getPrimaryKeys());
    }
}
