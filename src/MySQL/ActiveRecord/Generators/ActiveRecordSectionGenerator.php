<?php

namespace AntonioKadid\WAPPKitCore\Generators\MySQL\ActiveRecord\Generators;

use AntonioKadid\WAPPKitCore\Generators\MySQL\Column;
use AntonioKadid\WAPPKitCore\Generators\MySQL\Table;
use PhpParser\Builder\Class_;
use PhpParser\Builder\Namespace_;
use PhpParser\BuilderFactory;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\BinaryOp\Equal;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;

/**
 * Class ActiveRecordSectionGenerator.
 *
 * @package AntonioKadid\WAPPKitCore\Generators\MySQL\ActiveRecord\Generators
 */
abstract class ActiveRecordSectionGenerator
{
    /** @var Class_ */
    protected $class;
    /** @var Namespace_ */
    protected $namespace;
    /** @var Table */
    protected $table;
    /** @var array */
    private $options;

    /**
     * @param Namespace_ $namespace
     * @param Class_     $class
     * @param Table      $table
     */
    protected function __construct(Namespace_ $namespace, Class_ $class, Table $table, array $options = [])
    {
        $this->namespace = $namespace;
        $this->class     = $class;
        $this->table     = $table;
        $this->options   = $options;
    }

    /**
     * @return bool
     */
    public function commentsEnabled(): bool
    {
        return isset($this->options['comments']) && $this->options['comments'] === true;
    }

    /**
     * @param BuilderFactory $factory
     */
    abstract public function generate(BuilderFactory $factory): void;

    protected function ternarizeArrayField(Expr $expr, Column $column, string $arrayVarName = 'record'): Expr
    {
        if (!$column->isNullable()) {
            return $expr;
        }

        return new Ternary(
            new Equal(
                new ArrayDimFetch(new Variable($arrayVarName), new String_($column->getName())),
                new ConstFetch(new Name('null'))
            ),
            new ConstFetch(new Name('null')),
            $expr
        );
    }

    protected function ternarizeProperty(Expr $expr, Column $column): Expr
    {
        if (!$column->isNullable()) {
            return $expr;
        }

        return new Ternary(
            new Equal(
                new PropertyFetch(new Variable('this'), $column->getPropertyName()),
                new ConstFetch(new Name('null'))
            ),
            new ConstFetch(new Name('null')),
            $expr
        );
    }
}
