<?php

namespace AntonioKadid\WAPPKitCore\Generators\MySQL\ActiveRecord\Generators;

use AntonioKadid\WAPPKitCore\Generators\MySQL\Column;
use AntonioKadid\WAPPKitCore\Generators\MySQL\Table;
use LogicException;
use PhpParser\Builder\Class_;
use PhpParser\Builder\Method;
use PhpParser\Builder\Namespace_;
use PhpParser\BuilderFactory;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Return_;

/**
 * Class FromRecordMethodGenerator.
 *
 * @package AntonioKadid\WAPPKitCore\Generators\MySQL\ActiveRecord\Generators
 */
class FromRecordMethodGenerator extends ORMGenerator
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
            ->method('fromRecord')
            ->makePrivate()
            ->makeStatic()
            ->addParams([
                $factory->param('record')->setType('array')
            ])
            ->setReturnType($this->table->getClassName());

        if ($this->commentsEnabled()) {
            $commentGen = new CommentGenerator();
            $commentGen->addParameter('array', 'record');
            $commentGen->setReturnType($this->table->getClassName());

            $method->setDocComment($commentGen->generate());
        }

        $expression = new Expression(
            new Assign(
                new Variable('instance'),
                $factory->new($this->table->getClassName(), [])
            )
        );

        $method->addStmt($expression);

        $this->assignValuesToProperties($method);

        $expression = new Return_(new Variable('instance'));

        $method->addStmt($expression);

        $this->class->addStmt($method);
    }

    private function assignValuesToProperties(Method $method): void
    {
        foreach ($this->table->getColumns() as $column) {
            $expression = new Assign(
                new PropertyFetch(
                    new Variable('instance'),
                    $column->getPropertyName()
                ),
                $this->getValueExpression($column)
            );

            $method->addStmt($expression);
        }
    }

    private function getDateTimeExpression(Column $column): Expr
    {
        return new StaticCall(
            new Name(\DateTime::class),
            'createFromFormat',
            [
                new String_('Y-m-d H:i:s'),
                new ArrayDimFetch(new Variable('record'), new String_($column->getName())),
                new New_(new Name(\DateTimeZone::class), [new String_('UTC')])
            ]
        );
    }

    private function getValueExpression(Column $column): Expr
    {
        $type = $column->getPhpType();

        if ($type === \DateTime::class) {
            return $this->ternarizeArrayField(
                $this->getDateTimeExpression($column),
                $column
            );
        }

        return $this->ternarizeArrayField(
            new ArrayDimFetch(new Variable('record'), new String_($column->getName())),
            $column
        );
    }
}
