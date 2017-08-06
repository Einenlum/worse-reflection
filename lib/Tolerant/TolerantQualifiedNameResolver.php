<?php

namespace Phpactor\WorseReflection\Tolerant;

use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\ResolvedName;
use Microsoft\PhpParser\Node\Expression\CallExpression;

/**
 * This is a hack to allow resolving trait use clauses, which are for some reason
 * not supported in tolerant parser.
 *
 * See: https://github.com/Microsoft/tolerant-php-parser/issues/164
 */
class TolerantQualifiedNameResolver
{
    /**
     * @see \Microsoft\PhpParser\Node\QualifiedName::getResolvedName
     */
    public static function getResolvedName($node, $namespaceDefinition = null) {
        // Name resolution not applicable to constructs that define symbol names or aliases.
        if (($node->parent instanceof Node\Statement\NamespaceDefinition && $node->parent->name->getStart() === $node->getStart()) ||
            $node->parent instanceof Node\Statement\NamespaceUseDeclaration ||
            $node->parent instanceof Node\NamespaceUseClause ||
            $node->parent instanceof Node\NamespaceUseGroupClause ||
            //$node->parent->parent instanceof Node\TraitUseClause ||
            $node->parent instanceof Node\TraitSelectOrAliasClause ||
            ($node->parent instanceof TraitSelectOrAliasClause &&
            ($node->parent->asOrInsteadOfKeyword == null || $node->parent->asOrInsteadOfKeyword->kind === TokenKind::AsKeyword))
        ) {
            return null;
        }

        if (array_search($lowerText = strtolower($node->getText()), ["self", "static", "parent"]) !== false) {
            return $lowerText;
        }

        // FULLY QUALIFIED NAMES
        // - resolve to the name without leading namespace separator.
        if ($node->isFullyQualifiedName()) {
            return ResolvedName::buildName($node->nameParts, $node->getFileContents());
        }

        // RELATIVE NAMES
        // - resolve to the name with namespace replaced by the current namespace.
        // - if current namespace is global, strip leading namespace\ prefix.
        if ($node->isRelativeName()) {
            return $node->getNamespacedName();
        }

        list($namespaceImportTable, $functionImportTable, $constImportTable) = $node->getImportTablesForCurrentScope();

        // QUALIFIED NAMES
        // - first segment of the name is translated according to the current class/namespace import table.
        // - If no import rule applies, the current namespace is prepended to the name.
        if ($node->isQualifiedName()) {
            return self::tryResolveFromImportTable($node, $namespaceImportTable) ?? $node->getNamespacedName();
        }

        // UNQUALIFIED NAMES
        // - translated according to the current import table for the respective symbol type.
        //   (class-like => namespace import table, constant => const import table, function => function import table)
        // - if no import rule applies:
        //   - all symbol types: if current namespace is global, resolve to global namespace.
        //   - class-like symbols: resolve from current namespace.
        //   - function or const: resolved at runtime (from current namespace, with fallback to global namespace).
        if (self::isConstantName($node)) {
            $resolvedName = self::tryResolveFromImportTable($node, $constImportTable, /* case-sensitive */ true);
            $namespaceDefinition = $node->getNamespaceDefinition();
            if ($namespaceDefinition !== null && $namespaceDefinition->name === null) {
                $resolvedName = $resolvedName ?? ResolvedName::buildName($node->nameParts, $node->getFileContents());
            }
            return $resolvedName;
        } elseif ($node->parent instanceof CallExpression) {
            $resolvedName = self::tryResolveFromImportTable($node, $functionImportTable);
            if (($namespaceDefinition = $node->getNamespaceDefinition()) === null || $namespaceDefinition->name === null) {
                $resolvedName = $resolvedName ?? ResolvedName::buildName($node->nameParts, $node->getFileContents());
            }
            return $resolvedName;
        }

        return self::tryResolveFromImportTable($node, $namespaceImportTable) ?? $node->getNamespacedName();
    }

    /**
     * @param ResolvedName[] $importTable
     * @param bool $isCaseSensitive
     * @return null
     */
    private static function tryResolveFromImportTable($node, $importTable, bool $isCaseSensitive = false) {
        $content = $node->getFileContents();
        $index = $node->nameParts[0]->getText($content);
//        if (!$isCaseSensitive) {
//            $index = strtolower($index);
//        }
        if(isset($importTable[$index])) {
            $resolvedName = $importTable[$index];
            $resolvedName->addNameParts(\array_slice($node->nameParts, 1), $content);
            return $resolvedName;
        }
        return null;
    }

    private static function isConstantName($node) : bool {
        return
            ($node->parent instanceof Node\Statement\ExpressionStatement || $node->parent instanceof Expression) &&
            !(
                $node->parent instanceof Node\Expression\MemberAccessExpression || $node->parent instanceof CallExpression ||
                $node->parent instanceof ObjectCreationExpression ||
                $node->parent instanceof Node\Expression\ScopedPropertyAccessExpression || $node->parent instanceof AnonymousFunctionCreationExpression ||
                ($node->parent instanceof Node\Expression\BinaryExpression && $node->parent->operator->kind === TokenKind::InstanceOfKeyword)
            );
    }
}


