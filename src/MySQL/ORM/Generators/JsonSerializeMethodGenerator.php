<?php

namespace AntonioKadid\WAPPKitCore\Generators\MySQL\ORM\Generators;

use AntonioKadid\WAPPKitCore\Generators\MySQL\Table;
use AntonioKadid\WAPPKitCore\Generators\MySQL\Column;
use DateTime;
use LogicException;
use PhpParser\Builder\Class_;
use PhpParser\Builder\Namespace_;
use PhpParser\BuilderFactory;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Return_;

/**
 * Class JsonSerializeMethodGenerator.
 *
 * @package AntonioKadid\WAPPKitCore\Generators\MySQL\ORM\Generators
 */
class JsonSerializeMethodGenerator extends ORMGenerator
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
            ->method('jsonSerialize')
            ->makePublic();

        if ($this->commentsEnabled()) {
            $commentGen = new CommentGenerator();
            $commentGen->setReturnType('array');

            $method->setDocComment($commentGen->generate());
        }

        $method->addStmt(
            new Return_(
                new Array_(
                    array_map(
                        function (Column $tableColumn) {
                            return $this->makeArrayItem($tableColumn);
                        },
                        $this->table->getColumns()
                    ),
                    ['kind' => Array_::KIND_SHORT]
                )
            )
        );

        $this->namespace->addStmt($factory->use(\JsonSerializable::class));
        $this->class->implement(\JsonSerializable::class);
        $this->class->addStmt($method);
    }

    /**
     * @param Column $column
     *
     * @return ArrayItem
     */
    private function makeArrayItem(Column $column): ArrayItem
    {
        $type = $column->getPhpType();

        if ($type === DateTime::class) {
            return new ArrayItem(
                new MethodCall(
                    new PropertyFetch(
                        new Variable('this'),
                        $column->getPropertyName()
                    ),
                    'format',
                    [
                        new ConstFetch(new Name('DATE_ISO8601'))
                    ]
                ),
                new String_($column->getPropertyName())
            );
        }

        return new ArrayItem(
            $this->ternarizeProperty(
                new PropertyFetch(
                    new Variable('this'),
                    $column->getPropertyName()
                ),
                $column
            ),
            new String_($column->getPropertyName())
        );
    }
}
