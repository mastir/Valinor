<?php

declare(strict_types=1);

namespace CuyZ\Valinor\Mapper\Tree\Builder;

use CuyZ\Valinor\Mapper\Tree\Exception\SourceMustBeIterable;
use CuyZ\Valinor\Mapper\Tree\Shell;
use CuyZ\Valinor\Type\Types\ShapedArrayType;

use function array_key_exists;
use function assert;
use function is_array;
use function is_iterable;

/** @internal */
final class ShapedArrayNodeBuilder implements NodeBuilder
{
    public function build(Shell $shell, RootNodeBuilder $rootBuilder): TreeNode
    {
        $type = $shell->type();
        $value = $shell->value();

        assert($type instanceof ShapedArrayType);

        if (! is_iterable($value)) {
            return TreeNode::error($shell, new SourceMustBeIterable($value, $type));
        }

        $children = $this->children($type, $shell, $rootBuilder);

        $array = $this->buildArray($children);

        $node = TreeNode::branch($shell, $array, $children);
        $node = $node->checkUnexpectedKeys();

        return $node;
    }

    /**
     * @return array<TreeNode>
     */
    private function children(ShapedArrayType $type, Shell $shell, RootNodeBuilder $rootBuilder): array
    {
        /** @var iterable<mixed> $value */
        $value = $shell->value();
        $elements = $type->elements();
        $children = [];

        if (! is_array($value)) {
            $value = iterator_to_array($value);
        }

        foreach ($elements as $element) {
            $key = $element->key()->value();

            $child = $shell->child((string)$key, $element->type());

            if (array_key_exists($key, $value)) {
                $child = $child->withValue($value[$key]);
            } elseif ($element->isOptional()) {
                continue;
            }

            $children[$key] = $rootBuilder->build($child);

            unset($value[$key]);
        }

        if ($type->isUnsealed()) {
            $unsealedShell = $shell->withType($type->unsealedType())->withValue($value);
            $unsealedChildren = $rootBuilder->build($unsealedShell)->children();

            foreach ($unsealedChildren as $unsealedChild) {
                $children[$unsealedChild->name()] = $unsealedChild;
            }
        }

        return $children;
    }

    /**
     * @param array<TreeNode> $children
     * @return mixed[]|null
     */
    private function buildArray(array $children): ?array
    {
        $array = [];

        foreach ($children as $key => $child) {
            if (! $child->isValid()) {
                return null;
            }

            $array[$key] = $child->value();
        }

        return $array;
    }
}
