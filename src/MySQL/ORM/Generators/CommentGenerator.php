<?php

namespace AntonioKadid\WAPPKitCore\Generators\MySQL\ORM\Generators;

/**
 * Class CommentGenerator.
 *
 * @package AntonioKadid\WAPPKitCore\Generators\MySQL\ORM\Generators
 */
class CommentGenerator
{
    private $description = '';
    private $exceptions  = [];
    private $package     = '';
    private $parameters  = [];
    private $returnType  = '';

    /**
     * @param string $exceptionType
     *
     * @return CommentGenerator
     */
    public function addException(string $exceptionType): CommentGenerator
    {
        $this->exceptions[] = trim(sprintf('@throws %s', $exceptionType));

        return $this;
    }

    /**
     * @param string $type
     * @param string $name
     * @param string $description
     *
     * @return CommentGenerator
     */
    public function addParameter(string $type, string $name, string $description = ''): CommentGenerator
    {
        $this->parameters[] = trim(sprintf('@param %s $%s %s', $type, $name, $description));

        return $this;
    }

    /**
     * @param string $type
     * @param string $name
     * @param string $description
     *
     * @return CommentGenerator
     */
    public function addVar(string $type, string $name = '', string $description = ''): CommentGenerator
    {
        $result = '@var ' . $type;

        if (!empty($name)) {
            $result .= " \${$name}";
        }

        if (!empty($description)) {
            $result .= " {$description}";
        }

        $this->parameters[] = trim($result);

        return $this;
    }

    /**
     * @return string
     */
    public function generate(): string
    {
        $result = ['/**'];

        $begin = true;

        if (!empty($this->description)) {
            $result[] = sprintf(' * %s', $this->description);

            $begin = false;
        }

        if (!empty($this->package)) {
            if ($begin === true) {
                $begin = false;
            } else {
                $result[] = ' *';
            }

            $result[] = sprintf(' * %s', $this->package);
        }

        if (count($this->parameters) > 0) {
            if ($begin === true) {
                $begin = false;
            } else {
                $result[] = ' *';
            }

            foreach ($this->parameters as $parameter) {
                $result[] = sprintf(' * %s', $parameter);
            }
        }

        if (!empty($this->returnType)) {
            if ($begin === true) {
                $begin = false;
            } else {
                $result[] = ' *';
            }

            $result[] = sprintf(' * %s', $this->returnType);
        }

        if (count($this->exceptions) > 0) {
            if ($begin === true) {
                $begin = false;
            } else {
                $result[] = ' *';
            }

            foreach ($this->exceptions as $exception) {
                $result[] = sprintf(' * %s', $exception);
            }
        }

        $result[] = ' */';

        return implode(PHP_EOL, $result);
    }

    /**
     * @param string $message
     *
     * @return CommentGenerator
     */
    public function setDescription(string $message): CommentGenerator
    {
        $this->description = $message;

        return $this;
    }

    /**
     * @param string $package
     *
     * @return CommentGenerator
     */
    public function setPacket(string $package): CommentGenerator
    {
        $this->package = trim(sprintf('@package %s', $package));

        return $this;
    }

    /**
     * @param string $type
     * @param string $description
     *
     * @return CommentGenerator
     */
    public function setReturnType(string $type, string $description = ''): CommentGenerator
    {
        $this->returnType = trim(sprintf('@return %s %s', $type, $description));

        return $this;
    }
}
