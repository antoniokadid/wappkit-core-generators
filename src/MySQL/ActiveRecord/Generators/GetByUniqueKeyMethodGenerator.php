<?php

namespace AntonioKadid\WAPPKitCore\Generators\MySQL\ActiveRecord\Generators;

use AntonioKadid\WAPPKitCore\Extensibility\Filter;
use AntonioKadid\WAPPKitCore\Generators\MySQL\Column;
use AntonioKadid\WAPPKitCore\Generators\MySQL\Table;
use AntonioKadid\WAPPKitCore\Text\TextCase;
use LogicException;
use PhpParser\Builder\Class_;
use PhpParser\Builder\Method;
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

class GetByUniqueKeyMethodGenerator extends ActiveRecordSectionGenerator
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
        $uniqueKeys = $this->table->getUniqueKeys();
        if (count($uniqueKeys) === 0) {
            return;
        }

        foreach ($uniqueKeys as $columns) {
            $this->class->addStmt($this->generateMethod($factory, $columns));
        }
    }

    /**
     * @param Column[] $columns
     *
     * @return string[]
     */
    private static function getColumnNames(array $columns): array
    {
        return array_map(function (Column $tableColumn) {
            return new TextCase($tableColumn->getName());
        }, $columns);
    }

    /**
     * @param Column[] $columns
     *
     * @throws DatabaseException
     *
     * @return string
     */
    private static function makeMethodName(array $columns): string
    {
        $textCase = new TextCase('get by ' . implode(' and ', self::getColumnNames($columns)));

        return Filter::apply('method-name', $textCase->toCamelCase());
    }

    /**
     * @param Column[] $columns
     *
     * @return Param[]
     */
    private static function makeMethodParameters(array $columns): array
    {
        return array_map(function (Column $column) {
            return new Param(new Variable($column->getPropertyName()), null, $column->getPhpType());
        }, $columns);
    }

    private function generateMethod(BuilderFactory $factory, array $columns): Method
    {
        $method = $factory
            ->method(self::makeMethodName($columns))
            ->makePublic()
            ->makeStatic()
            ->addParams(
                array_merge(
                    [
                        $factory->param('connection')->setType('DatabaseConnectionInterface')
                    ],
                    self::makeMethodParameters($columns)
                )
            )
            ->setReturnType(new NullableType($this->table->className));

        if ($this->commentsEnabled()) {
            $commentGen = new CommentGenerator();
            $commentGen->addParameter('DatabaseConnectionInterface', 'connection');
            foreach ($columns as $column) {
                $commentGen->addParameter($column->getPhpType(), $column->getPropertyName());
            }

            $commentGen->setReturnType('null|' . $this->table->className);

            $method->setDocComment($commentGen->generate());
        }

        // Set SQL
        $sqlExpression = new Assign(
            new Variable('sql'),
            new String_($this->generateSql($columns))
        );

        $method->addStmt($sqlExpression);

        // Return

        // Get records
        $expression = new Assign(
            new Variable('records'),
            new MethodCall(new Variable('connection'), 'query', [
                new Variable('sql'),
                new Array_(
                    array_map(
                        function (Column $column): ArrayItem {
                            return new ArrayItem(new Variable($column->getPropertyName()));
                        },
                        $columns
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
                    new FuncCall(new Name('array_shift'), [
                        new Variable('records')
                    ])
                ])
            )
        );

        $method->addStmt($returnStmt);

        return $method;
    }

    /**
     * @param Column[] $columns
     *
     * @return string
     */
    private function generateSql(array $columns): string
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
                }, $columns)
            )
        );
    }
}
