<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\PHPStanExtensions;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectShapeType;
use PHPStan\Type\Type;
use Wwwision\DCBLibrary\Projection\CompositeProjection;

class CompositeProjectorCreateReturnTypeExtension implements DynamicStaticMethodReturnTypeExtension
{
    public function getClass(): string
    {
        return CompositeProjection::class;
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'create';
    }

    public function getTypeFromStaticMethodCall(
        MethodReflection $methodReflection,
        StaticCall $methodCall,
        Scope $scope
    ): ?Type {
        $args = $methodCall->getArgs();
        if (count($args) !== 1) {
            return null;
        }

        $firstArg = $args[0];
        if (!$firstArg instanceof Arg) {
            return null;
        }
        $arrayType = $scope->getType($firstArg->value);
        if (!$arrayType instanceof ConstantArrayType) {
            return null;
        }
        $properties = [];
        $keyTypes = $arrayType->getKeyTypes();
        $valueTypes = $arrayType->getValueTypes();

        $keyCount = count($keyTypes);
        for ($i = 0; $i < $keyCount; $i++) {
            $keyType = $keyTypes[$i];
            $valueType = $valueTypes[$i];

            // Extract string key from constant string type
            if ($keyType->isConstantScalarValue()->yes()) {
                $keyValue = $keyType->getConstantScalarValues()[0];
                if (is_string($keyValue)) {
                    // Extract generic type from ClosureProjector
                    if ($valueType instanceof GenericObjectType) {
                        $templateTypes = $valueType->getTypes();
                        if (!empty($templateTypes)) {
                            $propertyType = reset($templateTypes);
                        } else {
                            $propertyType = new MixedType();
                        }
                    } else {
                        $propertyType = new MixedType();
                    }

                    $properties[$keyValue] = $propertyType;
                }
            }
        }

        if (empty($properties)) {
            return null;
        }
        // Create object shape type
        $stateType = new ObjectShapeType($properties, []);

        // Return CompositeProjector<StateType>
        return new GenericObjectType(CompositeProjection::class, [$stateType]);
    }
}
