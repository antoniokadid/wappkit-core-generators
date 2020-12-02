<?php

namespace AntonioKadid\WAPPKitCore\Generators\MySQL\ORM\Generators;

use AntonioKadid\WAPPKitCore\DAL\Exceptions\DatabaseException;
use AntonioKadid\WAPPKitCore\Generators\MySQL\Column;
use AntonioKadid\WAPPKitCore\Generators\MySQL\Table;
use AntonioKadid\WAPPKitCore\Text\CamelCase;
use LogicException;
use PhpParser\Builder\Class_;
use PhpParser\Builder\Method;
use PhpParser\Builder\Namespace_;
use PhpParser\BuilderFactory;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
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
 * Class GetByForeignKeyMethodGenerator.
 *
 * @package AntonioKadid\WAPPKitCore\Generators\MySQL\ORM\Generators
 */
class GetByForeignKeyMethodGenerator extends ORMGenerator
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
        if (count($this->table->getForeignKeys()) === 0) {
            return;
        }

        foreach ($this->table->getForeignKeys() as $foreignKey) {
            $columns = array_column($foreignKey, 'own');
            if (empty($columns)) {
                continue;
            }

            $keys = array_shift($columns);
            if ($keys == null) {
                continue;
            }

            $this->class->addStmt($this->generateMethod($factory, $keys));
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
            return $tableColumn->getPropertyName();
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
        $base = 'get by ' . implode(' and ', self::getColumnNames($columns));

        $case = new CamelCase();
        $case->load($base);

        return $case->toCamelCase();
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
            ->setReturnType('array');

        if ($this->commentsEnabled()) {
            $commentGen = new CommentGenerator();
            $commentGen->addParameter('DatabaseConnectionInterface', 'connection');
            foreach ($columns as $column) {
                $commentGen->addParameter($column->getPhpType(), $column->getPropertyName());
            }
            $commentGen->setReturnType(sprintf('%s[]', $this->table->getClassName()));

            $method->setDocComment($commentGen->generate());
        }

        // Set SQL
        $sqlExpression = new Assign(
            new Variable('sql'),
            new String_($this->generateSql($columns))
        );

        $method->addStmt($sqlExpression);

        // Return
        $returnStmt = new Return_(
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
                    new Variable('sql'),
                    new Array_(
                        array_map(
                            function (Column $column) {
                                return new ArrayItem(new Variable($column->getPropertyName()));
                            },
                            $columns
                        ),
                        [
                            'kind' => Array_::KIND_SHORT
                        ]
                    )
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
                }, $columns)
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
