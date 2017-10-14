<?php

namespace Phpactor\WorseReflection\Bridge\TolerantParser\Reflection;

use Phpactor\WorseReflection\Core\Reflection\ReflectionMethodCall as CoreReflectionMethodCall;
use Phpactor\WorseReflection\Core\Position;
use Phpactor\WorseReflection\Core\Reflection\ReflectionClassLike;
use Phpactor\WorseReflection\Core\Reflection\Collection\ReflectionParameterCollection;
use Phpactor\WorseReflection\Core\Visibility;
use Phpactor\WorseReflection\Core\Type;
use Phpactor\WorseReflection\Core\Inference\Frame;
use Microsoft\PhpParser\Node\Expression\MemberAccessExpression;
use Phpactor\WorseReflection\Reflector;
use Phpactor\WorseReflection\Core\Inference\SymbolInformationResolver;
use Microsoft\PhpParser\Node;
use Phpactor\WorseReflection\Bridge\TolerantParser\Reflection\Collection\ReflectionParameterCollection as TolerantReflectionParameterCollection;
use Microsoft\PhpParser\Node\Expression\CallExpression;
use Phpactor\WorseReflection\Bridge\TolerantParser\Reflection\Collection\ReflectionArgumentCollection;
use Phpactor\WorseReflection\Core\ServiceLocator;

abstract class AbstractReflectionMethodCall implements CoreReflectionMethodCall
{
    /**
     * @var Frame
     */
    private $frame;

    /**
     * @var MemberAccessExpression
     */
    private $node;

    /**
     * @var ServiceLocator
     */
    private $services;

    public function __construct(
        ServiceLocator $services,
        Frame $frame,
        Node $node
    ) {
        $this->services = $services;
        $this->frame = $frame;
        $this->node = $node;
    }

    public function position(): Position
    {
        return Position::fromFullStartStartAndEnd(
            $this->node->getFullStart(),
            $this->node->getStart(),
            $this->node->getEndPosition()
        );
    }

    public function class(): ReflectionClassLike
    {
        $info = $this->services->symbolInformationResolver()->resolveNode($this->frame, $this->node);

        return $this->services->reflector()->reflectClassLike((string) $info->containerType());
    }


    abstract public function isStatic(): bool;

    public function arguments(): ReflectionArgumentCollection
    {
        return ReflectionArgumentCollection::fromArgumentListAndFrame(
            $this->services,
            $this->callExpression()->argumentExpressionList,
            $this->frame
        );
    }

    public function name(): string
    {
        return $this->node->memberName->getText($this->node->getFileContents());
    }

    private function callExpression(): CallExpression
    {
        return $this->node->parent;
    }
}

