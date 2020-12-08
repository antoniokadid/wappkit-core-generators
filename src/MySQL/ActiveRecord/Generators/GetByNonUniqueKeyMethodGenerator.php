<?php

namespace AntonioKadid\WAPPKitCore\Generators\MySQL\ActiveRecord\Generators;

use AntonioKadid\WAPPKitCore\DAL\Exceptions\DatabaseException;
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
 * Class GetByForeignKeyMethodGenerator.
 *
 * @package AntonioKadid\WAPPKitCore\Generators\MySQL\ActiveRecord\Generators
 */
class GetByNonUniqueKeyMethodGenerator extends ActiveRecordSectionGenerator
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
        $foreignKeys = $this->table->getNonUniqueKeys();
        if (count($foreignKeys) === 0) {
            return;
        }

        foreach ($foreignKeys as $columns) {
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
            return new Param(
                new Variable($column->getPropertyName()),
                null,
                ($column->isNullable() ? '?' : '') . $column->getPhpType()
            );
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
                    self::makeMethodParameters($columns),
                    [
                        $factory->param('count')->setType('int')->setDefault(0),
                        $factory->param('skip')->setType('int')->setDefault(0)
                    ]
                )
            )
            ->setReturnType('array');

        if ($this->commentsEnabled()) {
            $commentGen = new CommentGenerator();
            $commentGen->addParameter('DatabaseConnectionInterface', 'connection');
            foreach ($columns as $column) {
                $commentGen->addParameter($column->getPhpType(), $column->getPropertyName());
            }

            $commentGen->addParameter('int', 'count');
            $commentGen->addParameter('int', 'skip');

            $commentGen->setReturnType(sprintf('%s[]', $this->table->className));

            $method->setDocComment($commentGen->generate());
        }

        // Set SQL
        $sqlExpression = new Assign(
            new Variable('sql'),
            new String_($this->generateSql($columns))
        );

        $method->addStmt($sqlExpression);

        // Define the SQL parameters variable
        $parametersExpression = new Assign(new Variable('params'), new Array_([], ['kind' => Array_::KIND_SHORT]));

        $method->addStmt($parametersExpression);

        // add foreign keys into params array
        foreach ($columns as $column) {
            $method->addStmt(
                new Expression(
                    new FuncCall(
                        new Name('array_push'),
                        [
                            new Variable('params'),
                            new Variable($column->getPropertyName())
                        ]
                    )
                )
            );
        }

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

        // Return
        $returnStmt = new Return_(
            new FuncCall(new Name('array_map'), [
                new Closure([
                    'params' => [
                        new Param(new Variable('record'), null, 'array')
                    ],
                    'returnType' => new Name($this->table->className),
                    'stmts'      => [
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
                    return $column->isNullable() ?
                        sprintf('`%s` <=> ?', $column->getName()) :
                        sprintf('`%s` = ?', $column->getName());
                }, $columns)
            )
        );
    }
}
