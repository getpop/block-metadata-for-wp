<?php

declare(strict_types=1);

namespace PoP\BlockMetadataWP\FieldResolvers;

use Leoloso\BlockMetadata\Data;
use Leoloso\BlockMetadata\Metadata;
use PoP\ComponentModel\Schema\SchemaDefinition;
use PoP\ComponentModel\Schema\TypeCastingHelpers;
use PoP\Translation\Facades\TranslationAPIFacade;
use PoP\ComponentModel\TypeResolvers\TypeResolverInterface;
use PoP\ComponentModel\FieldResolvers\AbstractDBDataFieldResolver;
use PoP\Posts\TypeResolvers\PostTypeResolver;

class PostFieldResolver extends AbstractDBDataFieldResolver
{
    public static function getClassesToAttachTo(): array
    {
        return [
            PostTypeResolver::class,
        ];
    }

    public static function getFieldNamesToResolve(): array
    {
        return [
            'blockMetadata',
        ];
    }

    public function getSchemaFieldType(TypeResolverInterface $typeResolver, string $fieldName): ?string
    {
        $types = [
            'blockMetadata' => SchemaDefinition::TYPE_OBJECT,
        ];
        return $types[$fieldName] ?? parent::getSchemaFieldType($typeResolver, $fieldName);
    }

    public function isSchemaFieldResponseNonNullable(TypeResolverInterface $typeResolver, string $fieldName): bool
    {
        switch ($fieldName) {
            case 'blockMetadata':
                return true;
        }
        return parent::isSchemaFieldResponseNonNullable($typeResolver, $fieldName);
    }

    public function getSchemaFieldDescription(TypeResolverInterface $typeResolver, string $fieldName): ?string
    {
        $translationAPI = TranslationAPIFacade::getInstance();
        $descriptions = [
            'blockMetadata' => $translationAPI->__('Metadata for all blocks contained in the post, split on a block by block basis', 'pop-block-metadata'),
        ];
        return $descriptions[$fieldName] ?? parent::getSchemaFieldDescription($typeResolver, $fieldName);
    }

    public function getSchemaFieldArgs(TypeResolverInterface $typeResolver, string $fieldName): array
    {
        $schemaFieldArgs = parent::getSchemaFieldArgs($typeResolver, $fieldName);
        $translationAPI = TranslationAPIFacade::getInstance();
        switch ($fieldName) {
            case 'blockMetadata':
                return array_merge(
                    $schemaFieldArgs,
                    [
                        [
                            SchemaDefinition::ARGNAME_NAME => 'blockName',
                            SchemaDefinition::ARGNAME_TYPE => SchemaDefinition::TYPE_STRING,
                            SchemaDefinition::ARGNAME_DESCRIPTION => $translationAPI->__('Fetch only the block with this name in the post, filtering out all other blocks', 'block-metadata'),
                        ],
                        [
                            SchemaDefinition::ARGNAME_NAME => 'filterBy',
                            SchemaDefinition::ARGNAME_TYPE => SchemaDefinition::TYPE_INPUT_OBJECT,
                            SchemaDefinition::ARGNAME_DESCRIPTION => $translationAPI->__('Filter the block results based on different properties', 'block-metadata'),
                            SchemaDefinition::ARGNAME_ARGS => [
                                [
                                    SchemaDefinition::ARGNAME_NAME => 'blockNameStartsWith',
                                    SchemaDefinition::ARGNAME_TYPE => SchemaDefinition::TYPE_STRING,
                                    SchemaDefinition::ARGNAME_DESCRIPTION => $translationAPI->__('Include only blocks with the given name', 'block-metadata'),
                                ],
                                [
                                    SchemaDefinition::ARGNAME_NAME => 'metaProperties',
                                    SchemaDefinition::ARGNAME_TYPE => TypeCastingHelpers::makeArray(SchemaDefinition::TYPE_STRING),
                                    SchemaDefinition::ARGNAME_DESCRIPTION => $translationAPI->__('Include only these block properties in the meta entry from the block', 'block-metadata'),
                                ]
                            ]
                        ],
                    ]
                );
        }

        return $schemaFieldArgs;
    }

    public function resolveValue(TypeResolverInterface $typeResolver, $resultItem, string $fieldName, array $fieldArgs = [], ?array $variables = null, ?array $expressions = null, array $options = [])
    {
        $post = $resultItem;
        switch ($fieldName) {
            case 'blockMetadata':
                $block_data = Data::get_block_data($post->post_content);
                $block_metadata = Metadata::get_block_metadata($block_data);

                // Filter by blockName
                if ($blockName = $fieldArgs['blockName']) {
                    $block_metadata = array_filter(
                        $block_metadata,
                        function ($block) use ($blockName) {
                            return $block['blockName'] == $blockName;
                        }
                    );
                }
                if ($filterBy = $fieldArgs['filterBy']) {
                    if ($blockNameStartsWith = $filterBy['blockNameStartsWith']) {
                        $block_metadata = array_filter(
                            $block_metadata,
                            function ($block) use ($blockNameStartsWith) {
                                return substr($block['blockName'], 0, strlen($blockNameStartsWith)) == $blockNameStartsWith;
                            }
                        );
                    }
                    if ($metaProperties = $filterBy['metaProperties']) {
                        $block_metadata = array_map(
                            function ($block) use ($metaProperties) {
                                if ($block['meta']) {
                                    $block['meta'] = array_filter(
                                        $block['meta'],
                                        function ($blockMetaProperty) use ($metaProperties) {
                                            return in_array($blockMetaProperty, $metaProperties);
                                        },
                                        ARRAY_FILTER_USE_KEY
                                    );
                                }
                                return $block;
                            },
                            $block_metadata
                        );
                    }
                }
                return $block_metadata;
        }

        return parent::resolveValue($typeResolver, $resultItem, $fieldName, $fieldArgs, $variables, $expressions, $options);
    }
}